# spec.md

Entry point for any spec-driven action: starting or closing an epic, adding a rule, checking the backlog, or resolving a gap `CLAUDE.md` doesn't cover. Read this file first. Descend into a linked file only when the specific need arises — don't read speculatively.

## Files

- `backlog.md` — future epic ideas, not yet started. Read only when scoping the next epic.
- `rules.md` — decision log. A rule is added only when the dev corrects a proposed approach and explicitly asks to save it (e.g. "use X, not Y — save that"). Consult only when a question isn't answered by this file, the active `ep-*.md`, or `CLAUDE.md`. If `rules.md` doesn't answer it either, ask the dev — never guess.
- `history.md` — summary of what already exists (past epics, technologies, decisions). Read only when explicitly asked to retrieve something from the past. Never load by default.
- `ep-<name>.md` — the active epic's task list + references to code and other `.md` files. One file per epic in flight.

## Epic queue

- `ep-multirepo-foundation.md` — Fase 3 Epic 1: multi-repo foundation + branch protection (PR #1 open, merge pending)
- `ep-managed-database-iac.md` — Fase 3 Epic 2: managed Postgres IaC (`workshop-os-infra-database`)
- `ep-app-infra-iac.md` — Fase 3 Epic 3: app/domain IaC (`workshop-os-infra-kubernetes`)
- `ep-cloud-adaptation.md` — Fase 3 Epic 4: app cloud adaptation + deploy wiring
- `ep-cpf-auth.md` — Fase 3 Epic 5: CPF auth Lambda + API Gateway + JWT guard

All five in flight at once by explicit request (stacked branches, PRs
merged in order later) rather than the usual one-at-a-time sequencing —
see each `ep-*.md`'s "nota de sequenciamento".

## Epic lifecycle

1. Starting an epic: create `ep-<name>.md` — tasks plus references to code/other files only, no duplicated business-rule prose. Add one line to the queue above.
2. Working an epic: update `ep-<name>.md` task statuses as you go.
3. Finishing an epic: once every task is done and verified, delete `ep-<name>.md` and remove its line from the queue above. `git log` is the record of what happened. Only add to `rules.md` if the dev explicitly asks a decision from this epic be saved as a standing rule.

## File-size rule

Any file here except `ep-*.md` (temporary by design) that exceeds ~300 lines gets split: move the overflow into `<file>-<topic>.md`, and leave exactly one line in the main file — a link to the sub-file plus a one-sentence summary. Read the head file by default; open a sub-file only when its specific topic is actually needed.

## Language

- Use US English.
- Write in a concise LLM-friendly style.
- Use short imperative sentences.
- One rule per bullet.
- Prefer consistent terminology over synonyms.
- Avoid filler, repetition and unnecessary adjectives.
- Use technical terms and structured lists.
- Keep instructions deterministic and unambiguous.
