# Epic F4-06 — Kubernetes + CI/CD per service (GitHub side pending)

Code is done and verified locally: `k8s/` + `scripts/deploy-kind.sh` in each
service repo, `.github/workflows/ci-cd.yml` (test → quality → build → deploy),
platform in `15SOAT-Fase1-kubernetes`. All three services deployed to kind
with `deploy-kind.sh`; smoke tests and e2e demos green.

## Pending (outward-facing — needs the dev's go-ahead)

- [ ] `gh repo create CandidoRPNeto/15SOAT-Fase1-billing --public` and `-execution`; push `main`; create `homolog`
- [ ] Push Fase 4 branches of `15SOAT-Fase1` (stacked on `feature/2026/09/datadog-apm-local`) and `15SOAT-Fase1-kubernetes`; open PRs
- [ ] Branch protection (`main`/`master` + `homolog`): PR required, checks `test` + `quality`, no force-push, `enforce_admins`
- [ ] SonarCloud: import the 3 service repos (org `candidorpneto`), add `SONAR_TOKEN` secret to each
- [ ] Self-hosted runners: `15SOAT-Fase1-kubernetes/scripts/setup-runner.sh <owner/repo>` ×3; enable "Require approval for all outside collaborators"
- [ ] Mercado Pago: `MERCADOPAGO_ACCESS_TOKEN=TEST-...` + `PAYMENT_GATEWAY=mercadopago` in `~/.workshop-os/secrets/billing.env`, re-run `bootstrap-secrets.sh`, restart billing pods
- [ ] First green pipeline with deploy on each repo (evidence for README/video)
