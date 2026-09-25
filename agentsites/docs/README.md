# Documentation index

| Document | Status | Purpose |
| --- | --- | --- |
| [DISCOVERY.md](DISCOVERY.md) | partial (server inventory pending) | Phase 0 findings, coexistence plan, risks, proposed deviations |
| [DECISIONS.md](DECISIONS.md) | live | Every decision: date, decision, alternatives, reason |
| [reports/](reports/) | live | One report per phase stop, in the spec's §20 format |
| [ADD-SITE.md](ADD-SITE.md) | live | How a site is created, published and managed from the CLI |
| ONBOARDING-FLOW.md | Phase 2 | Screen-by-screen flow and funnel events |
| CHANGE-DOMAIN.md | Phase 7 | `platform:domain:change` runbook |
| CUSTOM-DOMAINS.md | Phase 4 | Agent-facing and operator-facing custom domain guide |
| BILLING.md | Phase 5 | Plans, state machine, webhooks, invoices |
| RUNBOOK.md | Phase 7 | Operations: deploy, backup/restore drill, alerts, incident steps |
| SECURITY.md | Phase 7 | Isolation, auth, abuse, headers, secrets |
| SCALING.md | Phase 7 | Capacity table for 1k / 5k / 20k tenants and trigger points |
| API.md | Phase 6 | Generated OpenAPI reference |

Every command in these documents is executed by the doc-test script in CI (Phase 7, §19).
