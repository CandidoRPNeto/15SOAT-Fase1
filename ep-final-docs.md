# Epic 7 — Documentação final de arquitetura

**Depende de**: Epics 1–6.
**Repos**: todos os 4 (só docs).
**Objetivo**: diagrama de componentes cloud-wide (só fica preciso com tudo
já existindo) + consolidação de READMEs. Sem RFC/ADR nova — só
consolidação, conforme decidido no epic 1 (RFCs/ADRs são escritas onde a
decisão é tomada, não empilhadas no fim).

## Documentação

- [x] `docs/architecture/diagrams/components.md` — diagrama cloud-wide + tabela de status por componente (código/validate/apply)
- [x] `README.md` raiz: seção Fase 3 expandida (status real, tabela dos 4 repos, links pras RFCs/ADRs/diagramas, exemplo de curl pro fluxo de CPF)
- [x] Tabela de Stack do README raiz atualizada (JWT RS256, Dokploy, Datadog)

## Fechamento do epic

- [x] Push da branch (sem PR ainda)
- [ ] Apagar este arquivo e a linha em `spec.md` só depois do PR mergeado
- [ ] **Depois de todos os PRs mergeados** (epics 1–7): apagar todos os `ep-*.md`, limpar a fila em `spec.md`, mover o resumo da Fase 3 pra `history.md` (mesmo tratamento que a Fase 2 recebeu)
