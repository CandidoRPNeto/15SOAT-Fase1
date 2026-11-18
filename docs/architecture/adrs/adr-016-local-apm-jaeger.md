# ADR-016: APM local com Jaeger (OpenTelemetry Collector) no lugar do Datadog

**Status**: Aceita
**Data**: 2026-11-17
**Substitui**: o papel de APM do [ADR-009](adr-009-datadog-agent-placement.md) na Fase 4

## Contexto

- O vídeo da Fase 4 exige "monitoramento e rastreamento dos fluxos distribuídos".
- A conta Datadog do time é do plano free, que não inclui APM, coleta de logs nem os monitores de log/trace. Os monitores de `observability/` nunca foram aplicados.
- Os traces da Fase 4 não ligavam os serviços:
  - o transactional outbox (ADR-011) publica depois, num loop do worker sem span ativo;
  - por isso o `amqp.basic.publish` virava raiz de um trace novo, sem relação com a requisição HTTP de origem.
- As APIs do Billing e do Execution não enviavam traces HTTP: o `php artisan serve` apaga as `DD_*` do servidor embutido. O OS já tinha a correção (`ServeCommandDatadogEnvTest`).

## Decisão

- **Backend**: Jaeger all-in-one (armazenamento em memória) no namespace `platform`. A UI fica em `kubectl -n platform port-forward svc/jaeger 16686`.
- **Coleta**:
  - os serviços mantêm o dd-trace nas imagens e enviam ao `trace-agent:8126`, um OpenTelemetry Collector (contrib) com o receiver `datadog`, que repassa ao Jaeger via OTLP;
  - no código, a única mudança é `DD_TRACE_AGENT_URL`.
- **Outbox com contexto**:
  - a porta `TraceContext` grava, junto com a mensagem, os headers do span ativo (`outbox_messages.trace_context`), via `OutboxRecorder`;
  - o `OutboxRelay` publica dentro de um span `outbox.relay` que continua esse contexto;
  - o consumidor trata a mensagem num span `message.handle` do próprio serviço.
- **Gateway**: o Traefik exporta OTLP direto ao Jaeger e injeta `traceparent` no ForwardAuth e no serviço de destino.
  - O receiver roda com a gate `receiver.datadogreceiver.Enable128BitTraceID`, que remonta o id W3C de 128 bits a partir de `_dd.p.tid`.
- **Ruído**:
  - o Collector mantém só os lotes com `laravel.request` (exceto as probes em `/up`), `outbox.relay` ou `message.handle`;
  - o Collector descarta os spans `laravel.event.handle`.
- **Nomes**: `DD_SERVICE_MAPPING=amqp:<serviço>`. O receiver rotula o lote inteiro com o serviço do span raiz, que no consumidor é o `amqp.basic.deliver`.
- **Logs**: o rastreamento por log continua com `saga_id`/`correlation_id` (`demo/trace-saga.sh` no repo kubernetes).
- **Métricas no Datadog**: métricas de infraestrutura entram no plano free. O Datadog Agent roda no cluster como DaemonSet versionado (`15SOAT-Fase1-kubernetes/k8s/platform/datadog-agent.yaml`), com as checks de kubelet e containerd, e envia métricas de host, Kubernetes e containers. APM, logs e processos ficam desligados no agente.

## Consequências

- Positivas:
  - um trace único mostra `traefik → os-service → (outbox) → RabbitMQ → execution/billing → os-service`;
  - o grafo de dependências do Jaeger reproduz a arquitetura;
  - tudo é grátis e roda no kind;
  - trocar de backend (Tempo, Datadog pago) mexe só no Collector.
- Negativas:
  - os traces somem quando o pod do Jaeger reinicia;
  - não há alertas: o monitoramento é feito pela UI do RabbitMQ (filas e DLQ), por HPA e `kubectl top`, e pela linha do tempo por `saga_id`.
- Os monitores Datadog (`15SOAT-Fase1-kubernetes/observability/`), nunca aplicados, foram removidos. Eles continuam no histórico do git.
