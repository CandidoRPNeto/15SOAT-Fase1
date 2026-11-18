#!/usr/bin/env node
// Teste de carga da Fase 4: gera tráfego real pelos 3 microsserviços, via
// gateway (Traefik), para encher o Jaeger, o RabbitMQ e o HPA no vídeo.
//
// Fases:
//   1. CRUD no OS Service (clientes, veículos, serviços, peças) — POST/PUT/DELETE
//   2. Sagas completas em paralelo: OS → diagnóstico (Execution) → orçamento e
//      aprovação (Billing) → reparo (Execution) → pagamento (Billing) → entrega.
//      Parte delas termina em compensação: orçamento rejeitado ou falta de
//      estoque na aprovação (mesmos cenários do demo/e2e-compensation.sh).
//   3. Leituras nos 3 serviços (listas, detalhes, histórico da saga)
//
// Tiers (multiplicador de tudo):
//   light   — smoke: 6 sagas, CRUD 6x, leituras 10x
//   heavy   — 40 sagas, CRUD 60x, leituras 100x
//   extreme — 150 sagas, CRUD 300x, leituras 500x
//   irregular — sorteia tudo a cada execução (sagas, concorrência, peso de cada
//               serviço) e dispara erros reais (401/403/404/422); feito para
//               rodar em loop (trafego-continuo.sh) e deixar os serviços com
//               volumes e taxas de erro diferentes no Jaeger
//
// Uso:
//   node scripts/route-load-test.js <light|heavy|extreme> [concorrência] [orçamento_de_tempo_s]
//   BASE_URL=http://127.0.0.1:30080 node scripts/route-load-test.js heavy 20 300
//
// O orçamento de tempo é a válvula de segurança; 0/none/unlimited/infinite
// destrava (roda até completar). Use 127.0.0.1: o Node resolve localhost para
// ::1 primeiro e o kind só expõe IPv4.
//
// Estoque: as sagas usam uma peça criada pelo próprio script (estoque alto),
// nunca as peças do seed — o demo/e2e-saga.sh do vídeo continua funcionando.
'use strict';

const BASE_URL = (process.env.BASE_URL || 'http://127.0.0.1:30080').replace(/\/$/, '');
const TIER = process.argv[2] || 'light';

const rand = (min, max) => min + Math.floor(Math.random() * (max - min + 1));

const PLAN = {
  light: { sagas: 6, crud: 6, reads: 10, concurrency: 6, budgetS: 120 },
  heavy: { sagas: 40, crud: 60, reads: 100, concurrency: 20, budgetS: 600 },
  extreme: { sagas: 150, crud: 300, reads: 500, concurrency: 40, budgetS: 1200 },
  // Leituras/erros moderados: o Jaeger (memória) guarda os N traces mais
  // recentes, e traces curtos em excesso expulsariam os das sagas.
  irregular: { sagas: rand(4, 14), crud: rand(0, 8), reads: rand(2, 12), concurrency: rand(3, 14), budgetS: 180, errors: rand(2, 12) },
};
if (!(TIER in PLAN)) {
  console.error(`Tier inválido: "${TIER}". Use: light | heavy | extreme | irregular`);
  process.exit(1);
}
const plan = PLAN[TIER];
// Peso por serviço: 1 nos tiers fixos; sorteado no irregular, para cada serviço
// receber um volume diferente de leituras e de erros.
const WEIGHT = TIER === 'irregular'
  ? { os: rand(5, 30) / 10, billing: rand(1, 30) / 10, execution: rand(1, 30) / 10 }
  : { os: 1, billing: 1, execution: 1 };
const CONCURRENCY = parseInt(process.argv[3], 10) || plan.concurrency;
const UNLIMITED = ['0', 'none', 'unlimited', 'infinite'];
const rawBudget = process.argv[4];
const TIME_BUDGET_MS = rawBudget === undefined || rawBudget === ''
  ? plan.budgetS * 1000
  : UNLIMITED.includes(rawBudget.toLowerCase()) ? Infinity : parseInt(rawBudget, 10) * 1000;

const startTime = Date.now();
const deadline = TIME_BUDGET_MS === Infinity ? Infinity : startTime + TIME_BUDGET_MS;
const REQUEST_TIMEOUT_MS = 15000;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
// Consulta de status das sagas: cada consulta vira um trace; 1,5 s basta.
const POLL_MS = 1500;

// ---------------------------------------------------------------------
// HTTP + estatísticas por rota
// ---------------------------------------------------------------------
const stats = new Map();

function record(name, status, ms) {
  if (!stats.has(name)) stats.set(name, { attempted: 0, ok: 0, clientErr: 0, serverErr: 0, totalMs: 0 });
  const s = stats.get(name);
  s.attempted++;
  s.totalMs += ms;
  if (status >= 200 && status < 300) s.ok++;
  else if (status >= 400 && status < 500) s.clientErr++;
  else s.serverErr++;
}

async function call(name, method, path, { token, body } = {}) {
  const t0 = Date.now();
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);
  let status = 0;
  let json = null;
  try {
    const res = await fetch(BASE_URL + path, {
      method,
      signal: controller.signal,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    status = res.status;
    const text = await res.text();
    try { json = text ? JSON.parse(text) : null; } catch { json = null; }
  } catch {
    status = 0; // timeout / erro de rede
  } finally {
    clearTimeout(timer);
  }
  record(name, status, Date.now() - t0);
  return { status, json };
}

function range(n) { return Array.from({ length: n }, (_, i) => i); }
function suf(i) { return `${Date.now()}-${i}-${Math.floor(Math.random() * 1e6)}`; }
function pick(arr, i) { return arr.length ? arr[i % arr.length] : null; }

async function pool(concurrency, items, worker) {
  let i = 0;
  const workers = range(Math.max(1, Math.min(concurrency, items.length || 1))).map(async () => {
    while (i < items.length) {
      if (Date.now() > deadline) return;
      const idx = i++;
      await worker(items[idx], idx);
    }
  });
  await Promise.all(workers);
}

// Intercala as rotas num único pool: se o tempo acabar, todas já rodaram.
async function interleave(concurrency, count, workers) {
  const tasks = [];
  for (let i = 0; i < count; i++) for (const worker of workers) tasks.push(() => worker(i));
  await pool(concurrency, tasks, (task) => task());
}

// ---------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------
async function setupCall(name, method, path, opts) {
  const res = await call(`${name} (setup)`, method, path, opts);
  stats.delete(`${name} (setup)`); // bootstrap não conta no resumo
  return res.json;
}

async function login(email) {
  return (await setupCall('POST /auth/login', 'POST', '/api/v1/auth/login', { body: { email, password: 'password' } }))?.token;
}

async function setup() {
  const t = {
    recep: await login('recepcao@workshop.com'),
    mec: await login('mecanico@workshop.com'),
    cli: await login('carlos@example.com'),
  };
  if (!t.recep || !t.mec || !t.cli) throw new Error(`login de setup falhou — a API está no ar em ${BASE_URL}?`);
  const clientId = (await setupCall('GET /auth/me', 'GET', '/api/v1/auth/me', { token: t.cli }))?.id;
  const vehicleId = (await setupCall('GET /vehicles', 'GET', `/api/v1/vehicles?client_id=${clientId}`, { token: t.recep }))?.data?.[0]?.id;
  const loadItem = await setupCall('POST /items', 'POST', '/api/v1/items', {
    token: t.mec,
    body: { name: `Peça de carga ${suf(0)}`, price: 25, stock_quantity: 100000, type: 'peca' },
  });
  if (!clientId || !vehicleId || !loadItem?.id) throw new Error('setup sem cliente/veículo/peça — rode o seed do OS Service');
  return { t, clientId, vehicleId, loadItemId: loadItem.id };
}

// ---------------------------------------------------------------------
// Fase 1: CRUD no OS Service
// ---------------------------------------------------------------------
async function crudPhase(ctx, n) {
  const { t } = ctx;
  const created = { clients: [], vehicles: [], services: [], items: [] };
  await interleave(CONCURRENCY, n, [
    async (i) => {
      const s = suf(i);
      const { json } = await call('POST /clients', 'POST', '/api/v1/clients', {
        token: t.recep, body: { name: `Cliente carga ${s}`, email: `carga-${s}@example.com`, password: 'password', cpf_cnpj: `LT${i}${Date.now() % 1e6}` },
      });
      if (json?.id) created.clients.push(json.id);
    },
    async (i) => {
      const { json } = await call('POST /vehicles', 'POST', '/api/v1/vehicles', {
        token: t.recep, body: { client_id: ctx.clientId, plate: `LT${i}${Date.now() % 1e5}`, brand: 'Carga', model: 'Teste', year: 2024 },
      });
      if (json?.id) created.vehicles.push(json.id);
    },
    async (i) => {
      const { json } = await call('POST /services', 'POST', '/api/v1/services', { token: t.mec, body: { name: `Serviço carga ${suf(i)}`, price: 10 + (i % 50) } });
      if (json?.id) created.services.push(json.id);
    },
    async (i) => {
      const { json } = await call('POST /items', 'POST', '/api/v1/items', { token: t.mec, body: { name: `Peça carga ${suf(i)}`, price: 5 + (i % 20), stock_quantity: 100, type: 'peca' } });
      if (json?.id) created.items.push(json.id);
    },
  ]);
  await interleave(CONCURRENCY, Math.ceil(n / 2), [
    async (i) => { const id = pick(created.clients, i); if (id) await call('PUT /clients/{id}', 'PUT', `/api/v1/clients/${id}`, { token: t.recep, body: { name: `Cliente atualizado ${suf(i)}` } }); },
    async (i) => { const id = pick(created.vehicles, i); if (id) await call('PUT /vehicles/{id}', 'PUT', `/api/v1/vehicles/${id}`, { token: t.recep, body: { color: 'Prata' } }); },
    async (i) => { const id = pick(created.services, i); if (id) await call('PUT /services/{id}', 'PUT', `/api/v1/services/${id}`, { token: t.mec, body: { price: 20 + (i % 30) } }); },
    async (i) => { const id = pick(created.items, i); if (id) await call('PUT /items/{id}', 'PUT', `/api/v1/items/${id}`, { token: t.mec, body: { stock_quantity: 50 + (i % 10) } }); },
  ]);
  // Só apaga o que este script criou (nunca dados do seed usados no vídeo).
  const del = Math.ceil(n / 4);
  await interleave(CONCURRENCY, del, [
    async (i) => { const id = created.clients[i]; if (id) await call('DELETE /clients/{id}', 'DELETE', `/api/v1/clients/${id}`, { token: t.recep }); },
    async (i) => { const id = created.vehicles[i]; if (id) await call('DELETE /vehicles/{id}', 'DELETE', `/api/v1/vehicles/${id}`, { token: t.recep }); },
    async (i) => { const id = created.services[i]; if (id) await call('DELETE /services/{id}', 'DELETE', `/api/v1/services/${id}`, { token: t.mec }); },
    async (i) => { const id = created.items[i]; if (id) await call('DELETE /items/{id}', 'DELETE', `/api/v1/items/${id}`, { token: t.mec }); },
  ]);
}

// ---------------------------------------------------------------------
// Fase 2: sagas pelos 3 serviços
// ---------------------------------------------------------------------
async function waitStatus(ctx, orderId, expected, timeoutMs = 60000) {
  const until = Date.now() + timeoutMs;
  while (Date.now() < until && Date.now() < deadline) {
    const { json } = await call('GET /service-orders/{id}/status', 'GET', `/api/v1/service-orders/${orderId}/status`, { token: ctx.t.recep });
    if (json?.status === expected) return true;
    if (json?.status === 'cancelled' && expected !== 'cancelled') return false;
    await sleep(POLL_MS);
  }
  return false;
}

async function firstOf(name, path, token, timeoutMs = 30000) {
  const until = Date.now() + timeoutMs;
  while (Date.now() < until && Date.now() < deadline) {
    const { json } = await call(name, 'GET', path, { token });
    if (json?.data?.[0]) return json.data[0];
    await sleep(POLL_MS);
  }
  return null;
}

// Retenta 5xx com espera aleatória: criações simultâneas podem colidir no
// número sequencial da OS (unique service_orders_number) — bug conhecido do
// OS Service sob concorrência; o cliente real também retentaria.
async function openOrder(ctx, itemId, quantity) {
  for (let attempt = 0; attempt < 4; attempt++) {
    const { status, json } = await call('POST /service-orders', 'POST', '/api/v1/service-orders', {
      token: ctx.t.recep,
      body: {
        client_id: ctx.clientId, vehicle_id: ctx.vehicleId, notes: `OS de carga ${suf(0)}`,
        // Só a peça do script: serviços do seed (ex.: "Troca de óleo") trazem
        // peças do seed junto e esgotariam o estoque que o vídeo usa.
        items: [{ item_id: itemId, quantity }],
      },
    });
    if (json?.id) return json.id;
    if (status < 500) return null;
    await sleep(200 + Math.random() * 800);
  }
  return null;
}

async function diagnose(ctx, orderId) {
  if (!(await waitStatus(ctx, orderId, 'received'))) return false;
  await sleep(1000); // Execution precisa ter enfileirado o diagnóstico
  await call('POST /executions/{id}/diagnosis/start', 'POST', `/api/v1/executions/${orderId}/diagnosis/start`, { token: ctx.t.mec });
  if (!(await waitStatus(ctx, orderId, 'in_diagnosis'))) return false;
  await call('POST /executions/{id}/diagnosis/finish', 'POST', `/api/v1/executions/${orderId}/diagnosis/finish`, { token: ctx.t.mec, body: { notes: 'Diagnóstico do teste de carga' } });
  return waitStatus(ctx, orderId, 'awaiting_approval');
}

async function decideBudget(ctx, orderId, approve) {
  const budget = await firstOf('GET /budgets?service_order_id', `/api/v1/budgets?service_order_id=${orderId}`, ctx.t.cli);
  if (!budget) return false;
  await call('GET /budgets/{id}', 'GET', `/api/v1/budgets/${budget.id}`, { token: ctx.t.cli });
  const action = approve ? 'approve' : 'reject';
  await call(`POST /budgets/{id}/${action}`, 'POST', `/api/v1/budgets/${budget.id}/${action}`, { token: ctx.t.cli });
  return waitStatus(ctx, orderId, approve ? 'approved' : 'cancelled');
}

async function repair(ctx, orderId) {
  await call('POST /executions/{id}/repair/start', 'POST', `/api/v1/executions/${orderId}/repair/start`, { token: ctx.t.mec });
  if (!(await waitStatus(ctx, orderId, 'in_execution'))) return false;
  await call('POST /executions/{id}/repair/finish', 'POST', `/api/v1/executions/${orderId}/repair/finish`, { token: ctx.t.mec });
  return waitStatus(ctx, orderId, 'finalized');
}

async function payAndDeliver(ctx, orderId) {
  const payment = await firstOf('GET /payments?service_order_id', `/api/v1/payments?service_order_id=${orderId}`, ctx.t.cli);
  if (!payment) return false;
  if (payment.provider === 'offline') {
    await call('GET /payments/offline/{ref}/approve', 'GET', new URL(payment.checkout_url).pathname, { token: ctx.t.cli });
  }
  await call('POST /payments/{id}/verify', 'POST', `/api/v1/payments/${payment.id}/verify`, { token: ctx.t.cli });
  const until = Date.now() + 30000;
  while (Date.now() < until) {
    const { json } = await call('GET /service-orders/{id}', 'GET', `/api/v1/service-orders/${orderId}`, { token: ctx.t.recep });
    if (json?.paid_at) break;
    await sleep(POLL_MS);
  }
  await call('POST /service-orders/{id}/deliver', 'POST', `/api/v1/service-orders/${orderId}/deliver`, { token: ctx.t.recep });
  return waitStatus(ctx, orderId, 'delivered');
}

// Variante pela posição: ~70% fluxo completo, ~15% rejeição, ~15% falta de estoque.
async function runSaga(ctx, i, outcome) {
  const kind = i % 7 === 3 ? 'reject' : i % 7 === 5 ? 'stock' : 'happy';
  const itemId = kind === 'stock' ? ctx.scarceItemId : ctx.loadItemId;
  const orderId = await openOrder(ctx, itemId, kind === 'stock' ? 5 : 1);
  if (!orderId || !(await diagnose(ctx, orderId))) return outcome.failed++;
  if (kind === 'reject') return (await decideBudget(ctx, orderId, false)) ? outcome.rejected++ : outcome.failed++;
  if (kind === 'stock') {
    await decideBudget(ctx, orderId, true);
    return (await waitStatus(ctx, orderId, 'cancelled')) ? outcome.compensated++ : outcome.failed++;
  }
  if (!(await decideBudget(ctx, orderId, true)) || !(await repair(ctx, orderId))) return outcome.failed++;
  return (await payAndDeliver(ctx, orderId)) ? outcome.delivered++ : outcome.failed++;
}

async function sagaPhase(ctx, n) {
  // Peça com estoque 1: toda OS pedindo 5 unidades compensa na aprovação.
  const scarce = await setupCall('POST /items', 'POST', '/api/v1/items', {
    token: ctx.t.mec, body: { name: `Peça escassa ${suf(0)}`, price: 40, stock_quantity: 1, type: 'peca' },
  });
  ctx.scarceItemId = scarce?.id ?? ctx.loadItemId;
  const outcome = { delivered: 0, rejected: 0, compensated: 0, failed: 0 };
  await pool(CONCURRENCY, range(n), (_, i) => runSaga(ctx, i, outcome));
  return outcome;
}

// ---------------------------------------------------------------------
// Fase 3: leituras nos 3 serviços
// ---------------------------------------------------------------------
function shuffle(arr) {
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

// Repete cada rota n × peso do serviço dela e embaralha: a ordem e o volume
// por serviço mudam a cada execução no tier irregular.
async function weightedRun(n, routes) {
  const tasks = [];
  for (const [svc, run] of routes) {
    const times = Math.max(1, Math.round(n * WEIGHT[svc]));
    for (let i = 0; i < times; i++) tasks.push(() => run(i));
  }
  await pool(CONCURRENCY, shuffle(tasks), (task) => task());
}

async function readPhase(ctx, n) {
  const { t } = ctx;
  const orders = (await setupCall('GET /service-orders', 'GET', '/api/v1/service-orders', { token: t.recep }))?.data ?? [];
  const ids = orders.map((o) => o.id);
  const get = (svc, name, build) => [svc, async (i) => {
    const [path, token] = build(i);
    if (!path.includes('null')) await call(name, 'GET', path, { token });
  }];
  await weightedRun(n, [
    get('os', 'GET /auth/me', () => ['/api/v1/auth/me', t.recep]),
    get('os', 'GET /service-orders', () => ['/api/v1/service-orders', t.recep]),
    get('os', 'GET /service-orders/stats', () => ['/api/v1/service-orders/stats', t.recep]),
    get('os', 'GET /clients', () => ['/api/v1/clients', t.recep]),
    get('os', 'GET /vehicles', () => ['/api/v1/vehicles', t.recep]),
    get('os', 'GET /services', () => ['/api/v1/services', t.recep]),
    get('os', 'GET /items', () => ['/api/v1/items', t.recep]),
    get('os', 'GET /service-orders/{id}/history', (i) => [`/api/v1/service-orders/${pick(ids, i)}/history`, t.recep]),
    get('billing', 'GET /budgets', () => ['/api/v1/budgets', t.cli]),
    get('billing', 'GET /payments', () => ['/api/v1/payments', t.cli]),
    get('execution', 'GET /executions', () => ['/api/v1/executions', t.mec]),
    get('execution', 'GET /executions/{id}', (i) => [`/api/v1/executions/${pick(ids, i)}`, t.mec]),
  ]);
}

// ---------------------------------------------------------------------
// Erros (tier irregular): falhas reais de cliente em cada serviço, para o
// Jaeger e as métricas mostrarem taxas de erro diferentes por serviço.
// ---------------------------------------------------------------------
const MISSING_UUID = '00000000-0000-4000-8000-000000000000';

async function errorPhase(ctx, n) {
  const { t } = ctx;
  const req = (svc, name, method, path, opts = {}) => [svc, () => call(`${name} [erro]`, method, path, opts)];
  await weightedRun(n, [
    req('os', 'GET /clients sem token (401)', 'GET', '/api/v1/clients'),
    req('os', 'POST /clients como cliente (403)', 'POST', '/api/v1/clients', { token: t.cli, body: { name: 'x' } }),
    req('os', 'GET /service-orders/{id} inexistente (404)', 'GET', '/api/v1/service-orders/999999', { token: t.recep }),
    req('os', 'POST /service-orders vazio (422)', 'POST', '/api/v1/service-orders', { token: t.recep, body: {} }),
    req('os', 'POST /clients e-mail inválido (422)', 'POST', '/api/v1/clients', { token: t.recep, body: { name: 'x', email: 'não-é-email', password: 'p' } }),
    req('billing', 'GET /payments sem token (401)', 'GET', '/api/v1/payments'),
    req('billing', 'POST /budgets/{id}/approve como mecânico (403)', 'POST', `/api/v1/budgets/${MISSING_UUID}/approve`, { token: t.mec }),
    req('billing', 'GET /budgets/{id} inexistente (404)', 'GET', `/api/v1/budgets/${MISSING_UUID}`, { token: t.cli }),
    req('billing', 'POST /payments/{id}/verify inexistente (404)', 'POST', `/api/v1/payments/${MISSING_UUID}/verify`, { token: t.cli }),
    req('execution', 'GET /executions sem token (401)', 'GET', '/api/v1/executions'),
    req('execution', 'GET /executions como cliente (403)', 'GET', '/api/v1/executions', { token: t.cli }),
    req('execution', 'GET /executions/{id} inexistente (404)', 'GET', '/api/v1/executions/999999', { token: t.mec }),
    req('execution', 'POST /executions/{id}/diagnosis/start inexistente (404)', 'POST', '/api/v1/executions/999999/diagnosis/start', { token: t.mec }),
  ]);
}

// ---------------------------------------------------------------------
// Resumo
// ---------------------------------------------------------------------
function printSummary(outcome) {
  const elapsed = ((Date.now() - startTime) / 1000).toFixed(1);
  console.log(`\n=== Resumo — tier="${TIER}"  tempo decorrido=${elapsed}s ===`);
  console.log(`sagas: entregues=${outcome.delivered} rejeitadas=${outcome.rejected} compensadas(estoque)=${outcome.compensated} incompletas=${outcome.failed}\n`);
  const rows = [...stats.entries()].sort((a, b) => b[1].attempted - a[1].attempted);
  const tot = { attempted: 0, ok: 0, clientErr: 0, serverErr: 0 };
  console.log('rota'.padEnd(46), 'tentativas'.padStart(11), '2xx'.padStart(7), '4xx'.padStart(7), '5xx/timeout'.padStart(12), 'ms médio'.padStart(9));
  for (const [name, s] of rows) {
    tot.attempted += s.attempted; tot.ok += s.ok; tot.clientErr += s.clientErr; tot.serverErr += s.serverErr;
    console.log(name.padEnd(46), String(s.attempted).padStart(11), String(s.ok).padStart(7), String(s.clientErr).padStart(7), String(s.serverErr).padStart(12), (s.totalMs / s.attempted).toFixed(0).padStart(9));
  }
  console.log('-'.repeat(96));
  console.log('TOTAL'.padEnd(46), String(tot.attempted).padStart(11), String(tot.ok).padStart(7), String(tot.clientErr).padStart(7), String(tot.serverErr).padStart(12));
  console.log('\nVeja: Jaeger http://localhost:16686 · RabbitMQ http://localhost:15672 · kubectl get hpa -A\n');
}

async function main() {
  console.log(`\n=== Teste de carga Fase 4 — tier="${TIER}" ===`);
  console.log(`BASE_URL=${BASE_URL}  concorrência=${CONCURRENCY}  orçamento=${TIME_BUDGET_MS === Infinity ? 'sem limite' : `${TIME_BUDGET_MS / 1000}s`}`);
  console.log(`plano: ${plan.sagas} sagas · CRUD ${plan.crud}x · leituras ${plan.reads}x${plan.errors ? ` · erros ${plan.errors}x` : ''}`);
  console.log(`peso por serviço: os=${WEIGHT.os} billing=${WEIGHT.billing} execution=${WEIGHT.execution}\n`);
  const ctx = await setup();
  console.log('--- fase 1/3: CRUD no OS Service ---');
  await crudPhase(ctx, plan.crud);
  console.log(`--- fase 2/3: ${plan.sagas} sagas pelos 3 serviços (concorrência ${CONCURRENCY}) ---`);
  const outcome = await sagaPhase(ctx, plan.sagas);
  console.log('--- fase 3/3: leituras nos 3 serviços ---');
  await readPhase(ctx, plan.reads);
  if (plan.errors) {
    console.log('--- extra: erros de cliente nos 3 serviços ---');
    await errorPhase(ctx, plan.errors);
  }
  printSummary(outcome);
}

main().catch((err) => { console.error(err); process.exit(1); });
