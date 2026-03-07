# Rules Directory — Master Orchestrator

> **Purpose:** Serve as the single entry point for AI agents to understand all mandatory rules, dependencies, and execution order.
> **Context:** Read this file first. Follow the steps exactly before touching any code, schema, or documentation.
> **Version:** 1.0

## 1. Core Principle

Before any task:

1. Understand the rules directory structure
2. Identify which files are relevant to your task
3. Follow rules strictly in order
4. Produce complete and auditable output

> The agent **must never skip reading any required file**. All domain, process, and meta rules are part of the source of truth.

---

## 2. Directory Overview

```
rules/
├─ README.md                          ← You are here
├─ master-promt.md                    ← Template for generating rules (do not edit)
├─ _meta/
│  └─ how-to-write-rules.md           ← File structure and writing standards
├─ database/
│  └─ schema.md                       ← Database schema documentation
├─ domain/
│  ├─ messaging.md                    ← Core messaging domain (Telegram, VK, External)
│  ├─ bot-users.md                    ← Bot user management domain
│  ├─ ai-assistant.md                 ← AI assistant integration domain
│  ├─ external-sources.md             ← External source integration domain
│  └─ admin-panel.md                  ← Admin panel domain (Filament 3, ManagerInterfaceContract)
├─ api/
│  └─ endpoints.md                    ← API contract rules and Swagger reference
└─ process/
   ├─ architecture-design.md          ← Design before implementation
   ├─ observability.md                ← Logging, metrics, monitoring (Loki + Grafana)
   ├─ ai-workflow.md                  ← AI agent lifecycle rules
   ├─ ci-cd.md                        ← CI/CD and pipeline rules
   ├─ security.md                     ← Security and safe coding rules
   └─ testing-strategy.md             ← Test strategy and coverage rules
```

---

## 3. Project Overview

**TG Support Bot** — Laravel 12 application for customer support via Telegram and VK.

| Property | Value |
|---|---|
| Framework | Laravel 12 |
| Language | PHP 8.2+ |
| Database | PostgreSQL |
| Queue | Laravel Queue (Redis) |
| Containers | Docker |
| Monitoring | Grafana + Loki + Sentry |
| Live Chat | Node.js (port 3001) |
| AI Providers | OpenAI, DeepSeek, GigaChat |

**Key platforms:**
- **Telegram** — main support channel via forum topics in supergroups
- **VK** — secondary support channel
- **External Sources** — third-party integrations via REST API

---

## 4. Reading & Execution Order

When performing a task, the AI agent **must follow this sequence**:

| # | File | Purpose |
|---|------|---------|
| 1 | `_meta/how-to-write-rules.md` | Understand formatting, structure, and standards for all rules files |
| 2 | `process/architecture-design.md` | Perform pre-implementation design. Document diagrams, affected layers, boundaries |
| 3 | `process/ai-workflow.md` | Review lifecycle rules: understand, design, implement, verify, document |
| 4 | `database/schema.md` | Read database schema before touching migrations, tables, or relations |
| 5 | `domain/messaging.md` | Core messaging domain — rules, state machines, invariants |
| 6 | `domain/bot-users.md` | Bot user management domain rules |
| 7 | `domain/ai-assistant.md` | AI assistant domain — read before touching AI logic |
| 8 | `domain/external-sources.md` | External integrations domain rules |
| 9 | `api/endpoints.md` | Understand API contracts. Do not implement endpoints not defined in Swagger |
| 10 | `process/observability.md` | Ensure logs, metrics, health checks, and request tracing will be included |
| 11 | `domain/admin-panel.md` | Admin panel domain rules — read before modifying `/admin`, Filament resources, or `SendReplyAction` |
| 12 | `process/ci-cd.md` | Validate pipeline rules. Ensure automated testing and deployment constraints |
| 13 | `process/security.md` | Read security rules before modifying auth, input handling, or secrets |
| 14 | `process/testing-strategy.md` | Ensure tests will cover all changes and critical paths |

> **Rule:** Never implement before design and lifecycle steps are confirmed.

---

## 5. Architecture Summary

```
HTTP Layer (Controllers + Middleware)
    ↓
DTO Layer (TelegramUpdateDto, VkUpdateDto, ExternalMessageDto)
    ↓
Business Logic Layer (Services + Actions)
    ↓
ManagerInterfaceContract
   /                    \
TelegramGroupInterface   AdminPanelInterface
(Telegram forum topics)  (Filament web panel /admin)
    ↓
Integration Layer (Modules/Telegram/Api/, Modules/Vk/Api/)
    ↓
Queue Layer (Modules/*/Jobs/ — async processing)
    ↓
Data Layer (Models + PostgreSQL)
```

**Key patterns used in this project:**
- **Action Pattern** — `app/Modules/*/Actions/` — one action per operation
- **Service Pattern** — `app/Services/`, `app/Modules/*/Services/` — reusable business logic
- **DTO Pattern** — `app/DTOs/`, `app/Modules/*/DTOs/` — typed data between layers
- **Queue Pattern** — `app/Modules/*/Jobs/` — async operations (send messages, webhooks)
- **Middleware Pattern** — validation of incoming webhooks
- **Contract Pattern** — `ManagerInterfaceContract` — decouples manager UI from message routing

---

## 6. Self-Verification

Before reporting task completion, the agent **must check**:

- [ ] All relevant rules files read and applied
- [ ] Design artifacts exist if required
- [ ] Database, domain, API changes validated against rules
- [ ] Logging, metrics, and observability confirmed
- [ ] CI/CD compliance verified
- [ ] Security rules enforced
- [ ] Tests created or updated as mandated
- [ ] No forbidden behaviors introduced
- [ ] Documentation updated in `rules/` as needed

---

## 7. Reporting / Task Output

At the end of a task, the AI agent **must produce a structured report**:

1. **Files affected** — List all files touched
2. **Rules read** — Confirm each relevant rules file was read
3. **Design artifacts** — Include diagrams, impact analysis, or placeholders
4. **Verification checks** — List all checklist items passed
5. **Deviations** — Document any items skipped or marked `_Not applicable_`
6. **Summary** — State task completion, blockers, and next steps

---

## 8. Best Practices

- Always create placeholders for non-existent domains or files
- Never assume defaults — follow explicit rules
- Stop immediately if a required rules file is missing
- Maintain consistency with existing code and rules
- Incremental and verifiable steps are mandatory

| Step | Action |
|------|--------|
| 1 | Read this `README.md` first |
| 2 | Follow the reading & execution order strictly |
| 3 | Apply rules and document all steps |
| 4 | Verify using checklist in [Section 6](#6-self-verification) |
| 5 | Produce structured task report as in [Section 7](#7-reporting--task-output) |
