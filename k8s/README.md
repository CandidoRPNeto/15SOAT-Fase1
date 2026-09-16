# `/k8s` — referência Kubernetes local (Fase 2)

Estes manifests foram entregues e validados estaticamente na Fase 2 contra
um cluster [kind](https://kind.sigs.k8s.io/) local (ver `infra/cluster`).
Continuam funcionais e são a evidência de uma implementação Kubernetes
literal, com HPA de verdade — mas **não são o alvo de deploy em nuvem da
Fase 3**.

A partir da Fase 3, o deploy em nuvem usa Dokploy como plataforma primária
(ver [`docs/architecture/adrs/adr-001-dokploy-as-cloud.md`](../docs/architecture/adrs/adr-001-dokploy-as-cloud.md)
e [`adr-003-keep-local-k8s-reference.md`](../docs/architecture/adrs/adr-003-keep-local-k8s-reference.md)
para o porquê de manter este diretório mesmo assim). Use `kubectl apply -f
k8s/` num cluster `kind` local (`infra/cluster`) para rodar/demonstrar a
implementação K8s literal; para o ambiente cloud vigente, ver o README raiz.
