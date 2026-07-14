#!/usr/bin/env node
// Teste de carga por rota para a demo de escalabilidade automática (HPA) da
// Fase 2. Chama as 39 rotas da API (GET/POST/PUT/DELETE) um número de vezes
// definido pelo "tier":
//
//   light   — GET 10x/8x (metade/metade), POST 6x, PUT 4x, DELETE 1x cada
//   heavy   — os mesmos multiplicadores x100 (DELETE x50)
//   extreme — os multiplicadores do heavy x10 (GET/POST/PUT ~1000x, DELETE 500x)
//
// Uso:
//   node scripts/route-load-test.js <light|heavy|extreme> [concorrência] [orçamento_de_tempo_s]
//   BASE_URL=http://localhost:30080 node scripts/route-load-test.js heavy 30 90
//
// Algumas rotas de transição de status da OS (generate-budget, approve,
// cancel, start-execution, finalize, pay, deliver) só têm 1 execução
// "de verdade" possível por OS — a API atual não expõe uma rota para mover
// uma OS recém-criada para "em_diagnostico", pré-requisito dessas ações.
// As chamadas repetidas retornam 422 (regra de negócio rejeitando a
// transição), o que ainda é uma requisição HTTP real (auth, banco, JSON) —
// válida para o teste de carga, mesmo sem sucesso de negócio.
'use strict';

const BASE_URL = (process.env.BASE_URL || 'http://localhost:30080').replace(/\/$/, '');
const TIER = process.argv[2] || 'light';

const MULT = { light: 1, heavy: 100, extreme: 1000 };
const DEL_MULT = { light: 1, heavy: 50, extreme: 500 };
if (!(TIER in MULT)) {
  console.error(`Tier inválido: "${TIER}". Use: light | heavy | extreme`);
  process.exit(1);
}

const DEFAULT_CONCURRENCY = { light: 5, heavy: 30, extreme: 60 };
const DEFAULT_BUDGET_S = { light: 60, heavy: 150, extreme: 240 };

const CONCURRENCY = parseInt(process.argv[3], 10) || DEFAULT_CONCURRENCY[TIER];
const TIME_BUDGET_MS = (parseInt(process.argv[4], 10) || DEFAULT_BUDGET_S[TIER]) * 1000;

const m = MULT[TIER];
const dm = DEL_MULT[TIER];
const GET_HIGH = 10 * m;
const GET_LOW = 8 * m;
const POST_N = 6 * m;
const PUT_N = 4 * m;
const DEL_N = 1 * dm;

const deadline = Date.now() + TIME_BUDGET_MS;
const REQUEST_TIMEOUT_MS = 15000;

// ---------------------------------------------------------------------
// Estatísticas por rota
// ---------------------------------------------------------------------
const stats = new Map();

function record(name, status, ms) {
  if (!stats.has(name)) {
    stats.set(name, { attempted: 0, ok: 0, clientErr: 0, serverErr: 0, totalMs: 0 });
  }
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

// ---------------------------------------------------------------------
// Utilidades
// ---------------------------------------------------------------------
function range(n) { return Array.from({ length: n }, (_, i) => i); }
function suf(i) { return `${Date.now()}-${i}-${Math.floor(Math.random() * 1e6)}`; }
function pick(arr, i) { return arr.length ? arr[i % arr.length] : null; }

async function pool(concurrency, items, worker) {
  let i = 0;
  const n = items.length;
  const workers = range(Math.max(1, Math.min(concurrency, n || concurrency))).map(async () => {
    while (i < n) {
      if (Date.now() > deadline) return;
      const idx = i++;
      await worker(items[idx], idx);
    }
  });
  await Promise.all(workers);
}

// Intercala N chamadas de várias rotas independentes num único pool, em vez
// de rodar rota por rota sequencialmente. Se o orçamento de tempo acabar no
// meio, TODAS as rotas do grupo já tiveram alguma execução (round-robin),
// em vez de zerar as últimas da lista — importante quando uma rota é bem
// mais lenta que as outras (ex.: criar cliente faz hash de senha).
async function interleave(concurrency, count, workers) {
  const tasks = [];
  for (let i = 0; i < count; i++) {
    for (const worker of workers) tasks.push(() => worker(i));
  }
  await pool(concurrency, tasks, (task) => task());
}

async function login(email) {
  const { json } = await call('POST /auth/login', 'POST', '/api/v1/auth/login', {
    body: { email, password: 'password' },
  });
  return json?.token;
}

// Login descartável usado só para obter um token válido pra outra rota
// (ex.: logout) — não deve contar como uma chamada de 'POST /auth/login'.
async function loginNoStat(email) {
  const { json } = await call('POST /auth/login (setup)', 'POST', '/api/v1/auth/login', {
    body: { email, password: 'password' },
  });
  stats.delete('POST /auth/login (setup)');
  return json?.token;
}

async function firstId(path, token) {
  const { json } = await call(`GET ${path} (setup)`, 'GET', path, { token });
  const id = json?.data?.[0]?.id;
  stats.delete(`GET ${path} (setup)`); // não conta no total — é só bootstrap
  return id;
}

// ---------------------------------------------------------------------
// Execução
// ---------------------------------------------------------------------
async function main() {
  console.log(`\n=== Teste de carga por rota — tier="${TIER}" ===`);
  console.log(`BASE_URL=${BASE_URL}  concorrência=${CONCURRENCY}  orçamento de tempo=${TIME_BUDGET_MS / 1000}s`);
  console.log(`Plano por rota: GET ${GET_HIGH}x / ${GET_LOW}x · POST ${POST_N}x · PUT ${PUT_N}x · DELETE ${DEL_N}x\n`);

  console.log('--- setup: login (3 papéis) + IDs de referência ---');
  const recepToken = await loginNoStat('recepcao@workshop.com');
  const mecToken = await loginNoStat('mecanico@workshop.com');
  const cliToken = await loginNoStat('carlos@example.com');
  if (!recepToken || !mecToken || !cliToken) {
    console.error('Falha no login de setup — verifique se a API está no ar em', BASE_URL);
    process.exit(1);
  }
  const refClientId = await firstId('/api/v1/clients', recepToken);
  const refVehicleId = await firstId('/api/v1/vehicles', recepToken);
  const refServiceId = await firstId('/api/v1/services', recepToken);
  console.log(`referência: client=${refClientId} vehicle=${refVehicleId} service=${refServiceId}\n`);

  // ---- fase 1: criação em massa (também é o "POST Nx" dessas 5 rotas) ----
  console.log(`--- fase 1/7: criação em massa (POST ${POST_N}x cada: clients, vehicles, services, items, service-orders) ---`);
  const clientIds = [];
  const vehicleIds = [];
  const serviceIds = [];
  const itemIds = [];
  const orderIds = [];

  await interleave(CONCURRENCY, POST_N, [
    async (i) => {
      const s = suf(i);
      const { json } = await call('POST /clients', 'POST', '/api/v1/clients', {
        token: recepToken,
        body: { name: `Load Client ${s}`, email: `load-client-${s}@example.com`, password: 'password', cpf_cnpj: `LT${i}-${Date.now() % 100000}` },
      });
      if (json?.id) clientIds.push(json.id);
    },
    async (i) => {
      const { json } = await call('POST /vehicles', 'POST', '/api/v1/vehicles', {
        token: recepToken,
        body: { client_id: refClientId, plate: `LT${i}${Date.now() % 100000}`, brand: 'LoadBrand', model: 'LoadModel', year: 2024 },
      });
      if (json?.id) vehicleIds.push(json.id);
    },
    async (i) => {
      const { json } = await call('POST /services', 'POST', '/api/v1/services', {
        token: recepToken,
        body: { name: `Load Service ${suf(i)}`, price: 10 + (i % 50) },
      });
      if (json?.id) serviceIds.push(json.id);
    },
    async (i) => {
      const s = suf(i);
      const { json } = await call('POST /items', 'POST', '/api/v1/items', {
        token: recepToken,
        body: { name: `Load Item ${s}`, part_number: `LT-${s}`, price: 5 + (i % 20), stock_quantity: 100, type: 'peca' },
      });
      if (json?.id) itemIds.push(json.id);
    },
    async (i) => {
      const { json } = await call('POST /service-orders', 'POST', '/api/v1/service-orders', {
        token: recepToken,
        body: { client_id: refClientId, vehicle_id: refVehicleId, notes: `OS de carga ${suf(i)}` },
      });
      if (json?.id) orderIds.push(json.id);
    },
  ]);

  console.log(`criados: clients=${clientIds.length} vehicles=${vehicleIds.length} services=${serviceIds.length} items=${itemIds.length} service-orders=${orderIds.length}\n`);

  // ---- fase 2: addService / addItem (mecânico) ----
  console.log(`--- fase 2/7: addService + addItem (POST ${POST_N}x cada, mecânico) ---`);
  // pares {orderId, assocId} — não índices soltos: sob concorrência, as
  // respostas chegam fora de ordem, então recalcular o índice do pedido
  // depois (via pick(orderIds, i)) não bate com o item realmente criado
  // naquele pedido.
  const itemAssocs = [];
  // reserva os últimos DEL_N itens fora do addItem — deletar um item já
  // vinculado a uma OS viola a FK de service_order_items (500, não 404/422)
  const addItemPool = itemIds.slice(0, Math.max(1, itemIds.length - DEL_N)) || itemIds;
  await interleave(CONCURRENCY, POST_N, [
    async (i) => {
      const orderId = pick(orderIds, i);
      if (!orderId) return;
      await call('POST /service-orders/{id}/services', 'POST', `/api/v1/service-orders/${orderId}/services`, {
        token: mecToken,
        body: { service_id: refServiceId, quantity: 1 },
      });
    },
    async (i) => {
      const orderId = pick(orderIds, i);
      const itemId = pick(addItemPool, i) || addItemPool[0];
      if (!orderId || !itemId) return;
      const { json } = await call('POST /service-orders/{id}/items', 'POST', `/api/v1/service-orders/${orderId}/items`, {
        token: mecToken,
        body: { item_id: itemId, quantity: 1 },
      });
      const assocId = json?.items?.[0]?.id;
      if (assocId) itemAssocs.push({ orderId, assocId });
    },
  ]);
  console.log(`associações item-OS criadas para remoção posterior: ${itemAssocs.length}\n`);

  // ---- fase 3: transições de status (best-effort — ver nota no topo) ----
  console.log(`--- fase 3/7: transições de status da OS (POST ${POST_N}x cada — 422 esperado após a 1ª tentativa por OS) ---`);
  const lifecycle = [
    ['POST /service-orders/{id}/generate-budget', mecToken, (id) => `/api/v1/service-orders/${id}/generate-budget`],
    ['POST /service-orders/{id}/start-execution', mecToken, (id) => `/api/v1/service-orders/${id}/start-execution`],
    ['POST /service-orders/{id}/finalize', mecToken, (id) => `/api/v1/service-orders/${id}/finalize`],
    ['POST /service-orders/{id}/approve', cliToken, (id) => `/api/v1/service-orders/${id}/approve`],
    ['POST /service-orders/{id}/cancel', cliToken, (id) => `/api/v1/service-orders/${id}/cancel`],
    ['POST /service-orders/{id}/pay', cliToken, (id) => `/api/v1/service-orders/${id}/pay`],
    ['POST /service-orders/{id}/deliver', recepToken, (id) => `/api/v1/service-orders/${id}/deliver`],
  ];
  await interleave(CONCURRENCY, POST_N, lifecycle.map(([name, token, pathFn]) => async (i) => {
    const orderId = pick(orderIds, i);
    if (!orderId) return;
    await call(name, 'POST', pathFn(orderId), { token });
  }));

  // ---- fase 4: login/logout/webhook ----
  console.log(`--- fase 4/7: auth/login, auth/logout, webhook (POST ${POST_N}x cada) ---`);
  await interleave(CONCURRENCY, POST_N, [
    async () => { await login('recepcao@workshop.com'); }, // conta em 'POST /auth/login'
    async () => {
      const tok = await loginNoStat('mecanico@workshop.com');
      if (tok) await call('POST /auth/logout', 'POST', '/api/v1/auth/logout', { token: tok });
    },
    async () => { await call('POST /webhook/messaging', 'POST', '/webhook/messaging', {}); },
  ]);

  // ---- fase 5: PUT ----
  console.log(`--- fase 5/7: updates (PUT ${PUT_N}x cada) ---`);
  await interleave(CONCURRENCY, PUT_N, [
    async (i) => {
      const id = pick(clientIds, i);
      if (id) await call('PUT /clients/{id}', 'PUT', `/api/v1/clients/${id}`, { token: recepToken, body: { name: `Load Client Upd ${suf(i)}` } });
    },
    async (i) => {
      const id = pick(vehicleIds, i);
      if (id) await call('PUT /vehicles/{id}', 'PUT', `/api/v1/vehicles/${id}`, { token: recepToken, body: { color: 'Load Color' } });
    },
    async (i) => {
      const id = pick(serviceIds, i);
      if (id) await call('PUT /services/{id}', 'PUT', `/api/v1/services/${id}`, { token: recepToken, body: { price: 20 + (i % 30) } });
    },
    async (i) => {
      const id = pick(itemIds, i);
      if (id) await call('PUT /items/{id}', 'PUT', `/api/v1/items/${id}`, { token: recepToken, body: { stock_quantity: 50 + (i % 10) } });
    },
  ]);

  // ---- fase 6: DELETE ----
  console.log(`--- fase 6/7: deletes (DELETE ${DEL_N}x cada) ---`);
  const deletableItemIds = itemIds.slice(-DEL_N); // os reservados fora do addItem
  await interleave(CONCURRENCY, DEL_N, [
    async (i) => {
      const id = clientIds[i];
      if (id) await call('DELETE /clients/{id}', 'DELETE', `/api/v1/clients/${id}`, { token: recepToken });
    },
    async (i) => {
      const id = vehicleIds[i];
      if (id) await call('DELETE /vehicles/{id}', 'DELETE', `/api/v1/vehicles/${id}`, { token: recepToken });
    },
    async (i) => {
      const id = serviceIds[i];
      if (id) await call('DELETE /services/{id}', 'DELETE', `/api/v1/services/${id}`, { token: recepToken });
    },
    async (i) => {
      const id = deletableItemIds[i];
      if (id) await call('DELETE /items/{id}', 'DELETE', `/api/v1/items/${id}`, { token: recepToken });
    },
    async (i) => {
      const pair = itemAssocs[i];
      if (pair) await call('DELETE /service-orders/{id}/items/{itemId}', 'DELETE', `/api/v1/service-orders/${pair.orderId}/items/${pair.assocId}`, { token: mecToken });
    },
  ]);

  // ---- fase 7: GETs (metade 10x, metade 8x) ----
  console.log(`--- fase 7/7: leituras — listagens ${GET_HIGH}x, detalhes ${GET_LOW}x ---`);
  const sampleOrderId = orderIds[0];
  const listRoutes = [
    ['GET /auth/me', () => ['/api/v1/auth/me', recepToken]],
    ['GET /service-orders/stats', () => ['/api/v1/service-orders/stats', recepToken]],
    ['GET /clients', () => ['/api/v1/clients', recepToken]],
    ['GET /vehicles', () => ['/api/v1/vehicles', recepToken]],
    ['GET /services', () => ['/api/v1/services', recepToken]],
    ['GET /items', () => ['/api/v1/items', recepToken]],
    ['GET /service-orders', () => ['/api/v1/service-orders', recepToken]],
  ];
  const detailRoutes = [
    ['GET /clients/{id}', () => [`/api/v1/clients/${refClientId}`, recepToken]],
    ['GET /vehicles/{id}', () => [`/api/v1/vehicles/${refVehicleId}`, recepToken]],
    ['GET /services/{id}', () => [`/api/v1/services/${refServiceId}`, recepToken]],
    ['GET /items/{id}', () => [`/api/v1/items/${itemIds[0] || refServiceId}`, recepToken]],
    ['GET /service-orders/{id}', () => [`/api/v1/service-orders/${sampleOrderId}`, recepToken]],
    ['GET /service-orders/{id}/status', () => [`/api/v1/service-orders/${sampleOrderId}/status`, recepToken]],
  ];
  await interleave(CONCURRENCY, GET_HIGH, listRoutes.map(([name, build]) => async () => {
    const [path, token] = build();
    await call(name, 'GET', path, { token });
  }));
  await interleave(CONCURRENCY, GET_LOW, detailRoutes.map(([name, build]) => async () => {
    const [path, token] = build();
    if (path.includes('undefined') || path.includes('null')) return;
    await call(name, 'GET', path, { token });
  }));

  // ---- resumo ----
  const elapsed = ((Date.now() - (deadline - TIME_BUDGET_MS)) / 1000).toFixed(1);
  console.log(`\n=== Resumo — tier="${TIER}"  tempo decorrido=${elapsed}s ===`);
  const rows = [...stats.entries()].sort((a, b) => b[1].attempted - a[1].attempted);
  const totals = { attempted: 0, ok: 0, clientErr: 0, serverErr: 0 };
  console.log('rota'.padEnd(42), 'tentativas'.padStart(11), '2xx'.padStart(8), '4xx'.padStart(8), '5xx/timeout'.padStart(12), 'ms médio'.padStart(10));
  for (const [name, s] of rows) {
    totals.attempted += s.attempted; totals.ok += s.ok; totals.clientErr += s.clientErr; totals.serverErr += s.serverErr;
    console.log(
      name.padEnd(42),
      String(s.attempted).padStart(11),
      String(s.ok).padStart(8),
      String(s.clientErr).padStart(8),
      String(s.serverErr).padStart(12),
      (s.totalMs / s.attempted).toFixed(0).padStart(10),
    );
  }
  console.log('-'.repeat(95));
  console.log(
    'TOTAL'.padEnd(42),
    String(totals.attempted).padStart(11),
    String(totals.ok).padStart(8),
    String(totals.clientErr).padStart(8),
    String(totals.serverErr).padStart(12),
  );
  console.log(`\nAcompanhe o HPA em outro terminal: kubectl get hpa workshop-os-app -w  (ou aba k9s :hpa)\n`);
}

main().catch((err) => { console.error(err); process.exit(1); });
