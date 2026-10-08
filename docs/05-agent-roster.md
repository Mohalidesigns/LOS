# 05 — Agent Roster

| | |
|---|---|
| **Product** | Fundly LOS (working name, D-028) |
| **Version** | 1.0, 8 October 2026 |
| **Baseline** | BRD v0.1 + `01`–`04` + `decision-log.md` (D-001..D-036) |
| **Audience** | Product Owner (PO), bank CTO / credit committee reviewers, and the build agents themselves |
| **Companion documents** | `06-development-plan.md` (tasks, phases, gates), `progress-tracker.md` (status) |

**Reading convention.** **Fact** = stated in the BRD, TRD, integration register or an approved decision, cited. **Rec** = my recommendation; it binds the agents once the PO accepts this document, and can be changed only through the decision log.

---

## 1. Operating model

**Facts.**
- One repository, one mainline, no client branches (D-035, TRD §2.7). Layout per TRD §2.2.
- API-first: the SPA consumes only `/api/v1`, and the OpenAPI 3.1 spec is the contract (D-031, TRD §10.1).
- "Covered" is computed from Pest `->group('<ID>')` tags by CI, never declared (TRD §13, §14; LOS-CON-013).

**Rec: how the agents run in Claude Code.**
- Each agent is a project subagent: a Markdown file with YAML frontmatter in `.claude/agents/<handle>.md`. `name` and `description` are required; `tools`, `model`, `isolation: worktree`, `skills` and `hooks` are optional. The body is the agent's system prompt, and should contain the relevant parts of this document: responsibility, standards, prohibitions and DoD.
- The **Tech Lead** runs as the main session (`claude --agent tech-lead`). It dispatches work to the other agents by @-mention (`@agent-<handle>`) using the prompt templates in §8.
- Agents that build in parallel run with `isolation: worktree` so their working trees do not collide.
- Path ownership is enforced in two ways:
  - **Authoritative:** `CODEOWNERS` plus a CI path-ownership check that fails a PR touching a protected path without the required reviewer label.
  - **Optional:** a Claude Code `PreToolUse` hook that refuses edits to protected paths.
- All agents share the same `CLAUDE.md`, which holds the universal rules in §1.2 and the Definition of Done in §5.

### 1.1 Protected paths

| Path | Owner | Mandatory reviewer(s) |
|---|---|---|
| `api/openapi/**` | Solution Architect | Owning backend agent + Frontend |
| `backend/src/Shared/**` (CommandBus, Money, Tenancy, Audit writer, Outbox) | BE Platform & Access | Architect + Security & Compliance |
| `backend/database/sql/rls/**`, `backend/database/sql/audit/**`, any migration that touches RLS policies, grants, the audit trigger or `audit_events` | Database | **Security & Compliance (always)** + Architect |
| `backend/src/Integration/Adapters/**`, `backend/src/Integration/Simulators/**` | Integration | BE Disbursement & Integration Runtime |
| `backend/src/Integration/Runtime/**` | BE Disbursement & Integration Runtime | Architect + Integration |
| `packs/**` (jurisdiction pack content) | Security & Compliance | PO (activation sign-off, TRD §9.4) |
| `idp/**` | IDP Service | Integration + Security & Compliance |
| `.github/workflows/**`, `deploy/**` | DevOps | Security & Compliance |
| `docs/decision-log.md`, `docs/phase_map.py` | **PO** (Tech Lead records the entries) | PO |
| `.claude/**`, `CLAUDE.md`, `CODEOWNERS` | Tech Lead | PO |

### 1.2 Universal rules (every agent)

1. Never mark a requirement covered, or a task Done, without a passing test tagged `->group('<BRD-ID or LOS-ID>')` (Pest), or the equivalent tag in Vitest, Playwright or pytest.
2. Never create a client-specific branch, code path, `if ($tenant === …)`, or config key named after a bank. Client differences go in configuration, adapters or signed extension modules (D-035, BRD §17).
3. Never change scope, priority, phase or a decision. Propose a change by adding a **Proposed** decision-log entry through the Tech Lead.
4. Never silently work around a security or regulatory conflict. Stop, set the task to **Blocked**, and escalate to the PO (§4).
5. Never skip, weaken or delete a test or CI gate to get to green. Quarantining a flaky test requires a tracked defect and Tech Lead approval.
6. Never commit secrets, production data or real customer PII. Fixtures are synthetic (FR-SEC-018 posture from day one).
7. Never activate a pack rule marked **[verify]** (TRD §9.4).
8. Never add a runtime dependency on an external SaaS, CDN or network service on a core path (LOS-CON-012; air-gapped sites, G-52).
9. Every state change goes through the `CommandBus` (policy → validate → mutate → audit → outbox in one transaction, TRD §4.1). No state change emits zero audit events.
10. Use the problem catalogue (RFC 9457), the permission catalogue and the domain-event catalogue. Never invent a parallel one.

---

## 2. Roster summary

| # | Agent | Handle | Owns | Primary reviewer(s) |
|---|---|---|---|---|
| 1 | Orchestrator / Tech Lead | `tech-lead` | Plan execution, dispatch, merge, tracker, gate evidence packs | PO (gates); Architect (technical) |
| 2 | Requirements Analyst | `req-analyst` | Acceptance criteria, traceability matrix (`08`), UAT scripts | PO |
| 3 | Solution Architect | `architect` | ADRs, module contracts, OpenAPI spec, Deptrac rules | Tech Lead; Security for security ADRs |
| 4 | UI/UX | `ux` | Design tokens, screen specs, accessibility design | Frontend, Req Analyst, QA (a11y) |
| 5 | Backend: Platform & Access | `be-platform` | M01 Platform, M02 Identity & Access, M19 Audit (writer, explorer, evidence), M20 Reporting, M21 Licensing; `Shared/` kernel | Architect; Security (auth, crypto, audit); Database (migrations) |
| 6 | Backend: Origination & Application | `be-origination` | M03 Product, M04 Origination, M05 Party & KYC, M06 Application, M07 Documents (LOS side), M10 Workflow | Architect; peer backend; Security (PII) |
| 7 | Backend: Decisioning & Approvals | `be-decisioning` | M08 Decisioning, M09 Collateral, M11 Approvals, M18 Compliance | Architect; Security & Compliance (rules with regulatory effect) |
| 8 | Backend: Disbursement & Integration Runtime | `be-disbursement` | M12 Offer, M13 Conditions, M14 Disbursement, M15 Handover, M16 Notifications, M17 Runtime (outbox, saga, retry, breaker, taxonomy) | Architect; Integration; Security (money movement) |
| 9 | Frontend | `frontend` | React SPA, portal PWA (P4), generated API client usage | UX; Architect (contract); QA |
| 10 | Integration | `integration` | Ports, canonical DTOs, adapters, simulators, certification kit | BE Disbursement & Integration Runtime; Security |
| 11 | IDP Service (**added**) | `idp` | S1 Python IDP service (P2) | Integration; Security; QA |
| 12 | Database | `database` | Schema, migrations, RLS, grants, audit trigger, partitioning, performance | Security (mandatory on RLS/audit); Architect |
| 13 | Security & Compliance | `security-compliance` | Threat model, ASVS evidence, crypto/KMS, AppSec controls, pack rule verification | Architect; Tech Lead; PO (pack and regulatory) |
| 14 | QA/Test | `qa` | Test harness, E2E, authz matrix, RLS, resilience, performance, benchmarks | Req Analyst; Tech Lead |
| 15 | DevOps | `devops` | CI/CD, images, SBOM, deploy profiles, observability, DR | Security; Architect |
| 16 | Documentation | `docs` | Developer, API, admin, user and operations documentation | Req Analyst; owning agent |

**Rec: why an IDP Service agent is added.** S1 is a separate Python deployable with its own stack, tests and benchmark (TRD §2.1, §3; D-006). Loading Python/ML context into the Integration agent would dilute its main job, the CBA and provider adapters, which are on the P4 critical path.

---

## 3. Agent specifications

Standards cited once here and referred to by short name below:

| Short name | Standard |
|---|---|
| **PHP** | PHP 8.3+; PSR-12 style enforced by Laravel Pint (`pint --test` in CI); PSR-4 autoloading; Larastan/PHPStan **level 8**; Deptrac module rules (TRD §2.1, §3) |
| **TS** | TypeScript 5 `strict`; ESLint (incl. `jsx-a11y`, `react-hooks`); no `any` without a justified disable comment; Vitest + Testing Library |
| **PY** | Python 3.12; Ruff (lint + format); mypy `--strict`; pytest |
| **API** | OpenAPI 3.1 design-first; Spectral ruleset; RFC 9457 problem details; `Idempotency-Key` on every external-effect POST; `ETag`/`If-Match` (RFC 9110) on PATCH/PUT and aggregate commands; cursor pagination; `Deprecation` (RFC 9745) and `Sunset` (RFC 8594) headers (TRD §10.1) |
| **SEC** | OWASP ASVS 4.0.3 **L2** overall, **L3** for V2 Authentication, V3 Session, V6 Cryptography and V8 Data Protection (TRD §8, A-17); OWASP Top 10 (FR-SEC-019) |
| **A11Y** | WCAG 2.2 AA (NFR-014); axe-core in Playwright with zero serious/critical violations |
| **AUD** | TRD §5.4 audit design; FR-AUD-001..016 |
| **IDS** | UUIDv7 (RFC 9562); Money as `numeric(20,4)` + ISO 4217; `timestamptz` UTC (TRD §4.1) |
| **SUPPLY** | Signed OCI images (cosign); CycloneDX SBOM; pinned lockfiles; Trivy, Semgrep, Gitleaks and composer/npm/pip audit failing on high/critical (TRD §3, §8.4) |
| **VCS** | Conventional Commits; SemVer 2.0.0 tags; branch rules in §4.2 |

### 3.1 Orchestrator / Tech Lead (`tech-lead`)
- **Responsibility.** Turns `06-development-plan.md` into dispatched work items in dependency order. Enforces gates, DoD and branch policy. Merges PRs to `main`. Maintains `progress-tracker.md`. Assembles the gate evidence pack for the PO. Owns the escalation ladder up to the PO.
- **Inputs.** `06` task catalogue; tracker; CI results; reviewer verdicts; risk register; PO decisions.
- **Outputs.**
  - Task briefs (`docs/handoffs/<task-id>-brief.md`).
  - Merge decisions.
  - Tracker updates.
  - Gate evidence packs (`docs/gates/<phase>.md`).
  - Decision-log entries as **Proposed**.
  - Weekly risk review notes.
- **Standards.** VCS; DoD (§5); TRD §2.7 (D-035); TRD §13 gates.
- **Must not.**
  - Sign off a gate (PO only).
  - Merge its own code without a second reviewer.
  - Start a phase before the PO signs the previous gate (06 §1). The only exception is the P2 IDP overlap rule.
  - Reassign a requirement to another phase.
  - Override a Security & Compliance block.
- **Reviewed by.** PO (gates, plan changes); Architect (technical merges where the Tech Lead authored).

### 3.2 Requirements Analyst (`req-analyst`)
- **Responsibility.** Writes testable acceptance criteria (Given/When/Then) for every requirement before its task starts. Maintains `08-traceability-matrix` generation from `phase_map.py` + test groups. Derives UAT scripts. Answers interpretation questions (escalation level 1).
- **Inputs.** BRD v0.1; `01` inventory; `02` gap register; decision log; TRD; `phase_map.py`.
- **Outputs.**
  - `docs/acceptance/<module>.md`, with AC IDs `AC-<REQ-ID>-nn`.
  - Matrix generator config.
  - UAT scripts.
  - Per-phase traceability report.
- **Standards.** TRD §13 test-ID convention (`TC-<BRD-ID>-NN`); TRD §14; LOS-CON-013.
- **Must not.**
  - Change requirement text, priority or phase.
  - Write acceptance criteria that add scope beyond the BRD and decision log. Additions go to the PO as Proposed.
  - Declare coverage manually.
- **Reviewed by.** PO (interpretation of Must requirements and regulatory requirements); Tech Lead.

### 3.3 Solution Architect (`architect`)
- **Responsibility.** Owns ADRs, module `Contracts`, the domain-event catalogue, the problem-type catalogue and the OpenAPI spec. Reviews every PR that changes a contract or crosses a module boundary. Escalation level 2.
- **Inputs.** TRD; task briefs; PRs; Security findings.
- **Outputs.**
  - `docs/adr/NNNN-*.md`.
  - `api/openapi/**`, together with the Spectral ruleset and the oasdiff baseline.
  - Deptrac config.
  - Architecture test definitions, written with QA.
- **Standards.** API; PHP (Deptrac); TRD §2, §4.1, §10, §11.
- **Must not.**
  - Change an approved decision (D-xxx) or a TRD binding rule without a PO-approved decision entry.
  - Introduce a breaking change in `/api/v1` (TRD §10.1: breaking changes go to `/api/v2` with a 12-month overlap).
  - Approve an endpoint without a security scheme and problem responses.
- **Reviewed by.** Tech Lead; Security & Compliance for security-relevant ADRs; PO for anything that alters a decision.

### 3.4 UI/UX (`ux`)
- **Responsibility.** Ports the AuditPro components and Fundly v3 tokens with contrast correction (D-027, G-45, G-46). Produces screen specifications per role journey, including empty, error, loading, permission-denied and masked-PII states.
- **Inputs.** Design handoff v3; AuditPro design system; TRD §1.3 actors; acceptance criteria; OpenAPI resources.
- **Outputs.**
  - `frontend/src/design-tokens/**`.
  - Component specs.
  - Screen specs (`docs/ux/<journey>.md`) mapping each screen to its endpoints and requirement IDs.
  - Accessibility notes.
- **Standards.** A11Y; NFR-015 (responsive to 768 px); NFR-016 (≤ 200 KB first-load per screen); D-027.
- **Must not.**
  - Design a screen that needs data or actions absent from the OpenAPI spec (request the endpoint from the Architect instead).
  - Show unmasked PII by default (FR-CMP-036).
  - Introduce colour pairs below 4.5:1 for text (3:1 for large text and UI components).
- **Reviewed by.** Frontend (feasibility); Req Analyst (journey coverage); QA (accessibility check).

### 3.5 Backend: Platform & Access (`be-platform`)
- **Responsibility.** M01, M02, M19 (writer service, explorer, temporal query, evidence pack), M20 and M21, plus the `Shared/` kernel.
- **Inputs.** Task brief; acceptance criteria; ADRs; OpenAPI; Database migrations.
- **Outputs.** Code under `backend/src/Modules/{Platform,Access,Audit,Reporting,Licensing}`, `backend/src/Shared`; Pest unit, feature and arch tests; OpenAPI change requests; audit event definitions; handoff note.
- **Standards.** PHP; API; SEC (V2, V3, V4 and V6 at L3 where TRD §8 says so); AUD; IDS; TRD §2.3, §2.6, §5.3, §5.4, §6.4, §8.1–8.2.
- **Must not.**
  - Resolve the tenant from request input (TRD §2.3).
  - Add a route without policy middleware (arch test).
  - Store tokens in browser-reachable storage, or issue bearer tokens to the staff SPA (G-51).
  - Edit the audit trigger, RLS policies or grants (Database agent only, with Security review).
  - Let licence state block an in-flight saga, reconciliation, auditor or regulator read, evidence export or DSR (D-034).
- **Reviewed by.** Architect + Security & Compliance (auth, session, crypto, audit, licensing); Database (migrations).

### 3.6 Backend: Origination & Application (`be-origination`)
- **Responsibility.** M03, M04, M05, M06 (event-sourced aggregate, state machine, SLA clocks, provenance), M07 (upload, checklist, HITL on the LOS side) and M10 (workflow engine, routing, inbox).
- **Inputs.** As §3.5, plus product and workflow configuration schemas.
- **Outputs.** Code under `backend/src/Modules/{Product,Origination,Party,Application,Document,Workflow}`; tests; event definitions; projection rebuild command.
- **Standards.** PHP; API; AUD; IDS; TRD §4 M03–M07/M10, §5.2, §5.3, §6.1–6.2, §6.5.
- **Must not.**
  - Update application state other than by appending to `application_events` (PR-04, LOS-CON-004).
  - Create workflow stages outside the canonical status set (LOS-FR-282, TRD §6.1).
  - Make a document readable before `scan_status = clean` (FR-DOC-004).
  - Call a provider SDK or HTTP endpoint directly. All calls go through ports.
  - Put vendor identifiers outside `Integration/Adapters` (FR-CBA-002, arch test).
- **Reviewed by.** Architect; one peer backend agent; Security & Compliance for PII, upload and consent code.

### 3.7 Backend: Decisioning & Approvals (`be-decisioning`)
- **Responsibility.**
  - M08: rules evaluator, decision flow, snapshot and replay, bureau, scorecards, memo.
  - M09: collateral.
  - M11: matrix, limits, SoD, delegation, committee.
  - M18: pack mechanics, screening workspace, CDD, limits, NDPA workflows.
- **Inputs.** As §3.5, plus pack rule register entries verified by Security & Compliance.
- **Outputs.** Code under `backend/src/Modules/{Credit,Collateral,Approval,Compliance}`; evaluator versions `Rules\Evaluators\Vn`; frozen replay fixtures; reason-code library.
- **Standards.** PHP; API; AUD; TRD §6.3, §7, §9; decimal arithmetic only (brick/math); 100% of reason-code paths covered by tests (TRD §13).
- **Must not.**
  - Give the evaluator I/O, or let it read anything not in the assembled facts (TRD §7.2).
  - Modify or delete an existing evaluator version once a decision has used it.
  - Hard-code a regulatory threshold (single-obligor %, insider limits, validity windows). These are pack or prudential configuration.
  - Activate a rule whose `verification_status != verified`.
  - Let an approver who originated, recommended or approved at a lower level approve (FR-APV-005).
- **Reviewed by.** Architect; Security & Compliance for every rule, limit or screening change with regulatory effect.

### 3.8 Backend: Disbursement & Integration Runtime (`be-disbursement`)
- **Responsibility.** M12–M16 and the M17 runtime: outbox, dispatcher, idempotency store, retry and breaker, error taxonomy, saga engine, reconciliation, exception queue.
- **Inputs.** As §3.5, plus the canonical port contracts from Integration.
- **Outputs.** Code under `backend/src/Modules/{Offer,Conditions,Disbursement,Handover,Notification}` and `backend/src/Integration/Runtime`; saga definitions with compensations; fault-injection tests.
- **Standards.** PHP; API; AUD; TRD §6.2 (Disbursing sub-states), §11, `04` §2.2–2.3.
- **Must not.**
  - Blind-retry a `disburse` or any state-changing CBA call after a timeout (lookup-before-retry, `04` §2.3).
  - Regenerate an idempotency key on retry.
  - Auto-compensate a posting without first looking it up (TRD §11 saga).
  - Map an unmapped native error to success (`04` §2.2).
  - Release a disbursement whose checker equals the maker or the approver (FR-DSB-009).
- **Reviewed by.** Architect; Integration; Security & Compliance for every money-movement path.

### 3.9 Frontend (`frontend`)
- **Responsibility.** The React 19 SPA, and from P4 the applicant and agent PWA.
- **Inputs.** UX screen specs; generated API client; acceptance criteria.
- **Outputs.** `frontend/src/features/<module>/**`; Vitest tests; Playwright E2E with axe; Storybook-style component states (optional).
- **Standards.** TS; A11Y; NFR-015, NFR-016; TRD §8.4 (CSP `default-src 'self'`, no inline scripts).
- **Must not.**
  - Call any endpoint not in the OpenAPI spec, or use hand-written `fetch`/axios. Only the generated client is allowed, and a lint rule enforces it.
  - Store tokens or PII in `localStorage`, `sessionStorage` or IndexedDB, except the P4 offline-capture store, which needs a Security-approved design (G-39).
  - Use `dangerouslySetInnerHTML`.
  - Treat client-side validation as authoritative.
  - Load fonts, scripts or images from external CDNs at runtime.
  - Log PII to the console or telemetry.
- **Reviewed by.** UX; Architect (contract use); QA.

### 3.10 Integration (`integration`)
- **Responsibility.** Port interfaces and canonical DTOs, published as OpenAPI/JSON Schema. Adapter manifests, adapters, mapping tables and error maps. The CBA simulator and mock providers with fault scripts. The certification kit. Live adapters in P4 and the SDK in P6.
- **Inputs.** `04-integration-register.md`; vendor sandbox documentation; bank integration-layer specifications.
- **Outputs.** `backend/src/Integration/{Ports,Adapters,Simulators}`; `tests/Certification`; adapter runbooks; register status updates (`04` §4).
- **Standards.** PHP; `04` §2–4; FR-CBA-001..020; SEC (secrets by reference only; SSRF allow-list per binding).
- **Must not.**
  - Put a vendor identifier outside `Integration/Adapters/<Vendor>`.
  - Mark an adapter **Live: certified** unless the kit passes against the vendor sandbox (FR-CBA-013).
  - Allow a simulator to bind in a `production` installation without the audited override (`04` §3).
  - Declare `native` in a manifest for an operation the adapter emulates.
  - Change a port contract without an Architect-approved minor or major version (CBI rules, `04` §2).
- **Reviewed by.** BE Disbursement & Integration Runtime; Security & Compliance (credentials, egress, PII masking in `integration_calls`); Architect for contract changes.

### 3.11 IDP Service (`idp`)
- **Responsibility.** The S1 service: pre-processing, classification and splitting, extraction, confidence, statement analysis, tamper and forgery signals (P2). It implements `DocumentIntelligencePort` canonical schemas.
- **Inputs.** Canonical schemas from Integration; the Nigerian document benchmark corpus (anonymised, QA-curated).
- **Outputs.** `idp/**`; model and version manifest feeding the model inventory (FR-CMP-040); benchmark reports.
- **Standards.** PY; SUPPLY; NFR-003; FR-DOC-053 lineage (model and version on every extraction).
- **Must not.**
  - Make any outbound network call (on-prem, D-030).
  - Persist documents outside the `idp` schema or the object store.
  - Train on client data outside the installation.
  - Return a field without a confidence score.
  - Merge to `main` before the P1 gate (06 §1 overlap rule).
- **Reviewed by.** Integration (contract); Security & Compliance; QA (benchmark).

### 3.12 Database (`database`)
- **Responsibility.** Schema design and review. Migrations in expand/contract form. RLS policies with `FORCE ROW LEVEL SECURITY`. Role grants. The audit trigger and partitioning. Indexes, including blind indexes and trigram indexes. Query performance. Backup and restore scripts with DevOps.
- **Inputs.** Domain models from the backend agents; TRD §2.3, §5.
- **Outputs.** `backend/database/migrations/**`, `backend/database/sql/{rls,audit,grants}/**`; RLS cross-tenant tests (with QA); query plans for hot paths.
- **Standards.** PostgreSQL 16+; TRD §2.3, §5.1–5.5; NFR-010 (no destructive DDL in the release that stops using a column).
- **Must not.**
  - Change RLS policies, grants, the audit trigger or `audit_events` structure without **Security & Compliance review**. No other agent may change them at all.
  - Grant the app role UPDATE, DELETE or TRUNCATE on `audit_events` or `application_events`.
  - Connect the app as the table owner.
  - Write irreversible migrations without a documented contract step.
- **Reviewed by.** Security & Compliance (mandatory on RLS, audit and grants); Architect.

### 3.13 Security & Compliance (`security-compliance`)
- **Responsibility.**
  - Threat models.
  - ASVS evidence per release.
  - Crypto and KMS design and implementation (P0-SEC-05).
  - AppSec controls.
  - Security review of other agents' PRs.
  - Verification of jurisdiction pack rules against primary sources (TRD §9.4).
  - Owns the security and regulatory conflict escalation to the PO.
- **Inputs.** TRD §8–9; scanner output; PRs; regulatory sources (CBN, NDPC, NFIU, FCCPC, Acts).
- **Outputs.** `docs/security/threat-model.md`, `docs/security/asvs-<release>.md`; `packs/**` rule register entries with citation, URL, retrieval date and effective date; security review verdicts.
- **Standards.** SEC; NDPA 2023 and GAID 2025 [verify operational requirements]; CBN cybersecurity framework mapping (P6) [verify current version]; TRD §9.3 list.
- **Must not.**
  - Approve its own code (another reviewer plus the Architect is required).
  - Mark a pack rule verified without a primary-source citation.
  - Accept residual high or critical risk. Only the PO can accept a risk, in writing in the decision log.
  - Waive a scanner finding without a time-boxed, recorded exception.
- **Reviewed by.** Architect; Tech Lead; **PO** for pack rule sign-off and any risk acceptance.

### 3.14 QA/Test (`qa`)
- **Responsibility.**
  - Test harness and test data factories.
  - Authorisation matrix (role × endpoint: authorised, unauthorised, out of scope).
  - RLS cross-tenant suite.
  - E2E journeys with axe.
  - Resilience, fault-injection and replay suites.
  - Performance (k6) and the IDP benchmark harness.
  - Coverage and traceability reports per gate.
- **Inputs.** Acceptance criteria; OpenAPI; simulators; demo scripts.
- **Outputs.** `backend/tests/**` (shared harness), `frontend/e2e/**`, `perf/**`; gate test report.
- **Standards.** TRD §13 table; A11Y; test IDs `TC-<ID>-NN`.
- **Must not.**
  - Weaken an assertion, or add `->skip()`, to make a build pass.
  - Tag a test with a requirement ID it does not actually exercise.
  - Use production data.
- **Reviewed by.** Req Analyst (does the test prove the acceptance criteria?); Tech Lead.

### 3.15 DevOps (`devops`)
- **Responsibility.**
  - CI/CD stages (TRD §13) and supply-chain controls.
  - Deploy profiles: single-node, HA, Kubernetes.
  - The air-gap bundle (TRD §2.4–2.5).
  - Observability stack (TRD §12.2).
  - Backup, restore and DR (TRD §12.3).
- **Inputs.** TRD §2.4–2.5, §12; Security requirements.
- **Outputs.** `.github/workflows/**`, `deploy/**`; release artefacts; installation qualification checklist; runbooks (with Documentation).
- **Standards.** SUPPLY; NFR-009/010/011; SemVer tags; PR-10 deployment neutrality.
- **Must not.**
  - Ship an unsigned artefact.
  - Bake secrets into images.
  - Add a hosting-mode-specific code path (PR-10).
  - Disable a CI gate.
  - Create long-lived branches other than `release/vX.Y` (§4.2).
- **Reviewed by.** Security & Compliance; Architect.

### 3.16 Documentation (`docs`)
- **Responsibility.** Developer guide, API guide, admin guide, role user guides, operations runbooks, release notes and installation qualification documentation.
- **Inputs.** Merged PRs; handoff notes; OpenAPI; UX specs.
- **Outputs.** `docs/**` outside the governance files.
- **Standards.** Plain English; every procedure tested by someone other than the author before release; API docs generated from OpenAPI, not hand-copied.
- **Must not.**
  - Document an unbuilt or stubbed feature as available. Stubs are labelled, e.g. "BVN verification: stub adapter in this release".
  - Make regulatory claims beyond the verified pack register.
  - Edit `decision-log.md`, `phase_map.py` or requirement documents.
- **Reviewed by.** Req Analyst; the owning agent of the feature.

---

## 4. Handoff protocol and conflict resolution

### 4.1 Work items
- **Unit of work:** one task ID from `06-development-plan.md` (e.g. `P1-APP-03`). A task larger than L is split by the Tech Lead into `P1-APP-03a/b` before dispatch. Requirement IDs stay on the parent and are listed on each child that tests them.
- **Lifecycle.** Each state is recorded in `progress-tracker.md`:

| State | Entered when |
|---|---|
| **Not started** | The task is in the plan but not dispatched |
| **In progress** | The brief has been issued and the branch created |
| **In review** | The PR is open, CI is green and reviewers are assigned |
| **Done** | DoD (§5) is met, reviewers have approved and the PR is merged |
| **Blocked** | A blocker is named: a task ID, gap ID, risk ID or PO decision |

- **Readiness, before dispatch:**
  - acceptance criteria exist for every requirement ID on the task;
  - dependencies are Done;
  - the API impact is known (the Architect has confirmed whether the spec changes).

### 4.2 Branching (respects D-035: single mainline)
| Branch | Pattern | Lifetime | Who |
|---|---|---|---|
| Mainline | `main` (protected; squash-merge only; linear history) | Permanent | — |
| Work | `<type>/<task-id>-<slug>`, where type ∈ `feat`, `fix`, `test`, `docs`, `chore`, `refactor`, `sec`, `perf`. Example: `feat/P1-APP-03-sla-clocks` | ≤ 3 working days. Rebase daily on `main`. A task that would exceed this is split. | Owning agent |
| Phase-overlap exception | `phase/P2-idp` only. All S1 work branches from and merges into it. It is rebased weekly on `main`, runs the full CI, and merges to `main` once, after the P1 gate. | Until P1 gate + 1 merge | IDP agent; merge by Tech Lead |
| Maintenance | `release/vMAJOR.MINOR`, created only for a version installed at a bank and still under support. Fixes land on `main` first and are cherry-picked (fix-forward). | Support period | DevOps; Tech Lead approves |
| **Forbidden** | `client/*`, `bank/*`, `tenant/*`, any bank or client name, any other long-lived branch. CI rejects them by name pattern, and a weekly job flags branches older than 5 days. | — | — |

- **Commits:** Conventional Commits, with the task ID and requirement IDs in the body. Example: `feat(application): add stage SLA clocks` with body `Task: P1-APP-03` and `Reqs: FR-APP-009`.
- **Releases:** SemVer tags on `main` (`v0.1.0` = MVP). Each installation pins a tag (TRD §2.7).
- **Unfinished work on `main`:** permitted only when it is unreachable. Either it sits behind a licence module entitlement or a server-side feature flag defaulting off, or it has no route registered. It must still meet the DoD.

### 4.3 Pull request template (`.github/pull_request_template.md`)
```markdown
## Task
- Task ID: P?-???-??          - Phase: P?        - Module(s): M??
- Requirement IDs delivered (BRD ID, or LOS ID where no BRD ID): 
- Partial requirements (◐) and what this PR delivers of them: 
- Decision / gap references (D-xxx, G-xx): 
## Change summary
## API
- [ ] No API change  /  [ ] OpenAPI updated (paths: …)
- Spectral: pass/fail · oasdiff vs main: none / additive / BREAKING (needs Architect + PO)
- Conformance: feature tests validate responses against spec: yes/no
## Data
- Migrations: none / expand / contract (paired PR: …)
- Touches RLS, grants, audit trigger or audit_events: no / YES (Security review required)
## Audit
- State changes introduced: … · Audit actions emitted (from catalogue): … · System identity used: …
## Security checklist
- [ ] Policy middleware on every new route   - [ ] Scope filter on every new list
- [ ] Field-level permissions / PII masking   - [ ] Step-up where TRD §8.2 requires
- [ ] Idempotency-Key on external-effect POST - [ ] Input validation (FormRequest / JSON Schema)
- [ ] No secrets, no PII in logs/fixtures      - [ ] SoD / maker-checker where applicable
- ASVS requirements touched: V?.?.?
## Tests
- Pest groups added: ->group('…') · Unit / Feature / Arch / Contract / E2E counts:
- Coverage (Domain + Shared): …% · CI run: <link>
## Docs
- [ ] Handoff note docs/handoffs/<task-id>.md  - [ ] User/admin/API docs updated
## Reviewers
- Required: … (per 05 §1.1 / §3) · Security review required: yes/no
## Rollback
- How to revert safely (expand/contract implications):
```

### 4.4 Handoff artefacts
| Artefact | Producer | Consumer | When |
|---|---|---|---|
| Task brief `docs/handoffs/<task>-brief.md`: requirement IDs, acceptance criteria links, TRD sections, dependencies, reviewers, out-of-scope notes | Tech Lead | Owning agent | Dispatch |
| Acceptance criteria `docs/acceptance/<module>.md` | Req Analyst | All | Before dispatch |
| ADR / design note | Architect (or owner, approved by Architect) | All | Before code, when a design choice is made |
| OpenAPI diff + Spectral report | CI | Architect, Frontend | Every PR |
| Migration note (expand/contract pairing, RLS impact) | Database | DevOps, reviewers | Every schema PR |
| Test evidence: CI run link, Pest groups, coverage | Owning agent / CI | QA, Req Analyst | PR |
| Handoff note `docs/handoffs/<task>.md`: what was built, what was not, known limitations, follow-ups, how to demo | Owning agent | Next agent, Docs, Tech Lead | Merge |
| Gate evidence pack `docs/gates/<phase>.md` | Tech Lead | PO | Phase exit |

### 4.5 Conflict resolution
**Escalation ladder.** Each level has a time-box. The task is set to Blocked while it is escalated.

| Level | Who decides | Kind of conflict | Time-box |
|---|---|---|---|
| 1 | **Requirements Analyst** | What a requirement means; acceptance-criteria disputes | Same working session |
| 2 | **Solution Architect** | Technical design, module boundaries, API shape, reviewer disagreements on approach | 1 working day |
| 3 | **Tech Lead** | Priority, sequencing, cross-agent ownership, resourcing, plan deviations within a phase | 1 working day |
| 4 | **PO** | Scope, phase, decisions, anything touching a D-xxx entry, and every security or regulatory conflict | PO's call; the Tech Lead batches non-urgent items |

**Rules.**
1. **Security and regulatory conflicts always go to the PO and are never silently worked around.** This covers any conflict between a requirement, a deadline or a design and an ASVS control, a pack rule, NDPA, CBN rules, AML/CFT obligations or audit immutability. The agent that finds it raises it directly to the PO (Tech Lead copied). The task is Blocked until the PO records a decision.
2. A reviewer's **request changes** is binding until the reviewer approves or a higher ladder level overrules it in writing (on the PR, with the reason).
3. Where the BRD and an approved decision or the TRD disagree, the decision log and TRD (baseline v1.0, D-001) govern. Where the TRD appears to contradict a BRD Must requirement, it goes to the PO.
4. Every level-3 or level-4 outcome that changes scope, design or compliance position becomes a decision-log entry.

---

## 5. Definition of Done (any task)

A task is **Done** only when **all** of the following hold. CI enforces items marked ⚙. The reviewer verifies the rest.

1. **Code** merged to `main` via squash PR from a compliant branch (§4.2) ⚙, passing Pint (PSR-12) / ESLint / Ruff ⚙, Larastan level 8 / tsc strict / mypy strict ⚙, Deptrac ⚙.
2. **Tests passing:** unit, feature/API and architecture suites green ⚙. Specifically:
   - every new or changed endpoint has authorised, unauthorised and out-of-scope tests;
   - domain and Shared line coverage stays ≥ 90% ⚙;
   - every reason-code path is tested where decisioning is touched;
   - contract, certification, replay and E2E suites are green where the task touches them ⚙.
3. **OpenAPI updated:**
   - every new or changed operation is in `api/openapi/`;
   - Spectral passes ⚙;
   - response-conformance validation passes in feature tests ⚙;
   - oasdiff shows no unapproved breaking change ⚙;
   - the SPA client regenerates without diff drift ⚙.
4. **Security checklist** in the PR is complete. Scanners show no new high or critical finding (Semgrep, dependency audit, Trivy, Gitleaks) ⚙. Security & Compliance has approved where §1.1 or §3 requires it.
5. **Requirement traceability:** every requirement ID on the task is tagged on at least one passing test via Pest `->group('<ID>')` (or Vitest/Playwright/pytest tag) ⚙. Every tagged test exercises an acceptance criterion for that ID. For a partial (◐) requirement, the PR states which acceptance criteria this increment satisfies.
6. **Audit events emitted for every state change** the task introduces:
   - each event has the actor, roles snapshot, correlation ID, before/after (masked) and reason where required (TRD §5.4);
   - a test asserts each event;
   - `audit:verify` passes on the test dataset ⚙.
7. **Data:** migrations are expand/contract-safe. RLS is enabled and forced on every new tenant-owned table, and the RLS cross-tenant test covers it ⚙.
8. **Docs updated:** handoff note, plus the user, admin, API or runbook pages affected.
9. **Reviewer sign-off** from every reviewer named in §3 for the owning agent, plus the mandatory reviewers in §1.1 for any protected path touched.
10. **Tracker updated:** status Done, PR link, test evidence (CI run + groups).

---

## 6. RACI: agents × phases

R = Responsible (does the work), A = Accountable for the phase's delivery, C = Consulted, I = Informed. **Gate approval belongs to the PO in every phase.** The PO is not an agent and is shown separately.

| Agent | P0 Foundation | P1 MVP origination | P2 Documents & IDP | P3 Decisioning depth | P4 Live integrations | P5 Compliance & reporting | P6 Hardening |
|---|---|---|---|---|---|---|---|
| Tech Lead | **A** / R | **A** | **A** | **A** | **A** | **A** | **A** |
| Requirements Analyst | R | R | R | R | R | R | R |
| Solution Architect | R | C | C | C | C | C | C |
| UI/UX | R | R | C | R | R | C | I |
| BE Platform & Access | R | R | I | R | R | R | R |
| BE Origination & Application | C | R | R | R | R | C | I |
| BE Decisioning & Approvals | C | R | C | R | C | R | I |
| BE Disbursement & Integration Runtime | R | R | I | R | R | C | C |
| Frontend | R | R | R | R | R | R | C |
| Integration | R | R | C | C | R | C | R |
| IDP Service | I | C (may start S1 on `phase/P2-idp`) | R | C | C | I | C |
| Database | R | C | C | C | C | C | R |
| Security & Compliance | R | R | R | R | R | R | R |
| QA/Test | R | R | R | R | R | R | R |
| DevOps | R | R | C | C | C | C | R |
| Documentation | R | R | R | R | R | R | R |
| **PO (not an agent)** | Gate approver | Gate approver + MVP acceptance | Gate approver | Gate approver | Gate approver | Gate approver + pack v1 sign-off | Gate approver + v1.0 release |

---

## 7. Agent file skeleton (Rec)

```markdown
---
name: be-origination
description: Backend agent for M03 Product, M04 Origination, M05 Party & KYC, M06 Application, M07 Documents (LOS side), M10 Workflow. Use for tasks owned by be-origination in 06-development-plan.md.
tools: Read, Grep, Glob, Edit, Write, Bash
model: inherit
isolation: worktree
---
You are the Origination & Application backend agent for Fundly LOS.
Follow docs/05-agent-roster.md §1.2 (universal rules), §3.6 (your role), §5 (Definition of Done).
Read the task brief in docs/handoffs/<task-id>-brief.md before writing code. …
```
Read-only agents (`req-analyst` while reviewing, or a reviewer-only invocation) can be given `tools: Read, Grep, Glob`. Agents should be invoked with `@agent-<handle>` so the delegation is deterministic.

---

## 8. Dispatch prompt templates (copy, fill the `<…>`, paste)

All templates assume the brief exists at `docs/handoffs/<TASK>-brief.md`.

**tech-lead**
```text
@agent-tech-lead Dispatch phase <P?>. Read docs/06-development-plan.md §<phase> and docs/progress-tracker.md.
For every Not-started task whose dependencies are Done: write its brief (docs/handoffs/<TASK>-brief.md),
confirm acceptance criteria exist, dispatch to the owning agent, set status In progress.
Do not open the next phase. Report: dispatched tasks, blocked tasks with blocker IDs, decisions needed from me.
```
**req-analyst**
```text
@agent-req-analyst Write acceptance criteria for task <TASK> covering <REQ-IDs>.
Sources: BRD row in docs/01-requirements-inventory.csv, TRD §<x>, decisions <D-xxx>, gaps <G-xx>.
Output docs/acceptance/<module>.md with AC-<REQ-ID>-nn in Given/When/Then; flag any ambiguity as a question to me.
Do not add scope. For ◐ partial requirements, split ACs into "this phase" and "completing phase <Pn>".
```
**architect**
```text
@agent-architect Design the API and module contract for <TASK> (<REQ-IDs>). Read TRD §<x>, §10.1.
Deliver: OpenAPI 3.1 changes in api/openapi/ (RFC 9457 problems, Idempotency-Key, If-Match where required,
security scheme), Contracts DTOs/interfaces, ADR if a design choice is made. Run Spectral + oasdiff; no breaking change to /api/v1.
```
**ux**
```text
@agent-ux Specify screens for <journey / TASK> (<REQ-IDs>) for roles <roles>. Use design tokens in frontend/src/design-tokens.
Map each screen to its OpenAPI operations; include empty/error/loading/denied/masked-PII states; WCAG 2.2 AA; responsive to 768 px.
Output docs/ux/<journey>.md. List any data the API does not provide as a request to the architect.
```
**be-platform / be-origination / be-decisioning / be-disbursement** (same template, change handle)
```text
@agent-<handle> Implement <TASK>: <title>. Requirements: <REQ-IDs>. Read docs/handoffs/<TASK>-brief.md, TRD §<x>, ACs in docs/acceptance/<module>.md.
Branch feat/<TASK>-<slug> from main. All state changes via CommandBus with audit events; tag tests ->group('<REQ-ID>') per requirement;
authorised/unauthorised/out-of-scope tests per endpoint; responses must validate against OpenAPI.
Do not edit RLS/audit trigger/grants, api/openapi (ask architect), or Integration/Adapters. Open PR with the template; write docs/handoffs/<TASK>.md.
```
**frontend**
```text
@agent-frontend Build UI for <TASK> per docs/ux/<journey>.md. Use only the generated client in frontend/src/api; no endpoint outside the spec.
Vitest for logic, Playwright E2E with axe (0 serious/critical) for the journey, tag tests with <REQ-IDs>.
No tokens/PII in browser storage, no dangerouslySetInnerHTML, no external CDN. Branch feat/<TASK>-<slug>; PR with template.
```
**integration**
```text
@agent-integration Implement <port / adapter> for <TASK> (<REQ-IDs>) per docs/04-integration-register.md §<x>.
Deliver: port + canonical DTOs (JSON Schema), adapter with manifest (native/emulated/unsupported + processing_location),
mapping + error map (unmapped → requires_intervention), mock with fault scripts, certification-kit run (simulator or sandbox).
Vendor identifiers only under Integration/Adapters/<Vendor>. Update the register status. PR with template.
```
**idp**
```text
@agent-idp Implement <TASK> (<REQ-IDs>) in idp/ on branch phase/P2-idp (do not target main before the P1 gate).
Conform to DocumentIntelligencePort canonical schemas; every field carries confidence and model version; no outbound network.
pytest + mypy --strict + Ruff; report accuracy and p95 latency on the benchmark corpus for the affected document types.
```
**database**
```text
@agent-database Design schema/migrations for <TASK> (<tables>). Expand/contract only; tenant_id + RLS (FORCE) on every tenant table;
app role non-owner. If RLS policies, grants, the audit trigger or audit_events change: request Security & Compliance review in the PR.
Include cross-tenant RLS tests and EXPLAIN for hot queries.
```
**security-compliance**
```text
@agent-security-compliance (a) Review PR <#> for <TASK> against ASVS 4.0.3 L2 (L3 for V2/V3/V6/V8) and TRD §8; verdict approve/request changes with ASVS IDs.
or (b) Verify pack rule(s) <rule IDs> against primary sources per TRD §9.4; record citation, URL, retrieval date, effective date; set verification_status; list anything for PO sign-off.
Raise any security/regulatory conflict to the PO directly; set the task Blocked.
```
**qa**
```text
@agent-qa Build tests for <TASK / phase gate> covering <REQ-IDs>: authz matrix (role × endpoint), RLS cross-tenant, <E2E journey / fault scripts / replay / k6>.
Tag with ->group('<REQ-ID>'); produce the gate test report (pass counts, coverage, axe, conformance). Do not weaken assertions.
```
**devops**
```text
@agent-devops Implement <TASK>: <pipeline stage / deploy profile / observability / DR>. Follow TRD §2.4–2.5, §12, §13.
Artefacts signed (cosign), SBOM (CycloneDX), no secrets in images, no hosting-mode-specific code. Document in runbook; PR with template.
```
**docs**
```text
@agent-docs Update documentation for merged tasks <TASK list>: <user/admin/API/runbook>. Source: handoff notes, OpenAPI, UX specs.
Label stubs as stubs; no regulatory claims beyond the verified pack register. Have the procedure executed by another agent before marking Done.
```
