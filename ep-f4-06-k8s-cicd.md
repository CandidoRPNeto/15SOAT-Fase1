# Epic F4-06 — Kubernetes + CI/CD per service (GitHub side pending)

Code is done and verified locally: `k8s/` + `scripts/deploy-kind.sh` in each
service repo, `.github/workflows/ci-cd.yml` (test → quality → build → deploy),
platform in `15SOAT-Fase1-kubernetes`. All three services deployed to kind
with `deploy-kind.sh`; smoke tests and e2e demos green.

## Pending (outward-facing — needs the dev's go-ahead)

- [x] Repos `15SOAT-Fase1-billing` / `-execution` created (public); `main` + `homolog` pushed
- [x] One PR per repo carrying Fase 3 + Fase 4: `15SOAT-Fase1` (`f4-08-docs-delivery` → `master`), `-kubernetes` (`f4-02-platform` → `main`), `-database` (`epic4-deploy-workflow` → `main`). Fase 3 stacked PRs were merged into their parent branches, never into the default branch.
- [ ] Dev merges the PRs above
- [x] Branch protection: PR required, checks `test` + `quality` (kubernetes: `terraform-validate` + `platform-validate`), no force-push, `enforce_admins`
- [ ] SonarCloud: import the 3 service repos (org `candidorpneto`), add `SONAR_TOKEN` secret to each
- [x] Self-hosted runners ×3 (`systemctl --user status 'actions-runner-*'`); fork PRs need approval (`all_external_contributors`)
- [ ] Mercado Pago: `MERCADOPAGO_ACCESS_TOKEN=TEST-...` + `PAYMENT_GATEWAY=mercadopago` in `~/.workshop-os/secrets/billing.env`, re-run `bootstrap-secrets.sh`, restart billing pods
- [ ] First green pipeline with deploy on each repo (evidence for README/video)
