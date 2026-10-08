# Progress Tracker

| | |
|---|---|
| **Product** | Fundly LOS |
| **Plan** | `06-development-plan.md` (task IDs, dependencies, sizes) |
| **Initialised** | 2026-10-08 |
| **Maintained by** | Tech Lead agent; updated on every PR merge and at each gate |

**Status values:** Not started · In progress · In review · Done · Blocked. A task is **Done** only when it meets the Definition of Done in `05-agent-roster.md` §5; "Test evidence" must name the CI run and the Pest groups that pass. A Blocked task names its blocker (risk ID, gap ID or task ID) in Notes.

Requirement IDs marked ◐→Pn are partial in that task and completed in phase Pn; † marks a phase proposed by this plan because `phase_map.py` does not assign one (see plan §8).

## Summary

| Phase | Gate status | Tasks | Not started | In progress | In review | Done | Blocked | Phase traceability (reqs with passing tests / reqs in phase) |
|---|---|---|---|---|---|---|---|---|
| P0 Foundation | Open (build started) | 24 | 0 | 24 | 0 | 0 | 0 | 0 / 53 |
| P1 MVP origination | Open (build started 2026-10-08) | 51 | 41 | 5 | 6 | 0 | 0 | 0 / 155 |
| P2 Documents & IDP | Not open | 13 | 13 | 0 | 0 | 0 | 0 | 0 / 24 |
| P3 Decisioning & workflow depth | Not open | 21 | 21 | 0 | 0 | 0 | 0 | 0 / 25 |
| P4 Live integrations & channels | Not open | 21 | 21 | 0 | 0 | 0 | 0 | 0 / 27 |
| P5 Compliance, reporting & data | Not open | 15 | 15 | 0 | 0 | 0 | 0 | 0 / 36 |
| P6 Hardening & certification | Not open | 10 | 10 | 0 | 0 | 0 | 0 | 0 / 12 |

## P0: Foundation

| Task | Title | Requirement IDs | Owner | Status | PR | Test evidence | Notes |
|---|---|---|---|---|---|---|---|
| P0-ORC-01 | Monorepo scaffold (TRD §2.2), Pint, Larastan L8, Deptrac module rules, ESLint/tsc stric… | `LOS-CON-013` † | Tech Lead | In progress | — | — | |
| P0-DEV-01 | CI pipeline stages (TRD §13) | `NFR-009`, `LOS-CON-010` †, `LOS-CON-011` † | DevOps | In progress | — | — | |
| P0-DEV-02 | Single-node compose profile (api, worker, scheduler, nginx, PG16, Redis, MinIO+Object L… | `LOS-CON-012` † | DevOps | In progress | — | — | |
| P0-OBS-01 | Correlation-ID middleware, OTel PHP SDK, Monolog JSON, RED metrics, health/readiness en… | `NFR-011` | DevOps | In progress | — | — | |
| P0-ARC-01 | OpenAPI 3.1 source tree, Spectral ruleset (RFC 9457 problems, Idempotency-Key, security… | `NFR-019`, `LOS-CON-001` † | Architect | In progress | — | — | |
| P0-ARC-02 | ADRs + module Contracts namespaces M01–M21, domain-event catalogue, problem-type catalo… | — | Architect | In progress | — | — | |
| P0-PLT-01 | Shared kernel | `LOS-CON-004` † | BE Platform & Access | In progress | — | — | |
| P0-DB-01 | Tenancy | `FR-TEN-001`, `FR-TEN-003` | Database | In progress | — | — | |
| P0-AUD-01 | Hash-chained audit_events (partitioned, per-tenant seq, advisory lock), UPDATE/DELETE/T… | `FR-AUD-001`, `FR-AUD-002`, `FR-AUD-003`, `FR-AUD-004`, `FR-AUD-005`, `FR-AUD-012` | Database | In progress | — | — | |
| P0-PLT-02 | Configuration artefact lifecycle (draft→review→approved→active), versioning, config lay… | `FR-TEN-009`, `LOS-FR-284`, `LOS-CON-003` † | BE Platform & Access | In progress | — | — | |
| P0-SEC-01 | Permission catalogue, roles, assignments with all scope dimensions, deny-by-default, un… | `FR-SEC-001`, `FR-SEC-002`, `FR-SEC-003`, `FR-SEC-004`, `FR-SEC-005`, `LOS-CON-007` † | BE Platform & Access | In progress | — | — | |
| P0-SEC-02 | SoD matrix (assignment-time + action-time), generic maker-checker engine (change_reques… | `FR-SEC-006`, `FR-SEC-007`, `FR-SEC-008` | BE Platform & Access | In progress | — | — | |
| P0-SEC-03 | Local identity store (Argon2id, offline breached-password list, lockout), TOTP MFA, ste… | `LOS-FR-302`, `FR-SEC-013`, `FR-SEC-015` | BE Platform & Access | In progress | — | — | |
| P0-SEC-04 | Standard role library (19 templates), effective-access view, service-account principals… | `LOS-FR-278`, `FR-SEC-011`, `FR-SEC-014` | BE Platform & Access | In progress | — | — | |
| P0-SEC-05 | KeyManagementPort (local keyfile), per-tenant DEK envelope encryption, field-level encr… | `FR-SEC-016` ◐→P6, `FR-SEC-017`, `FR-CMP-036`, `LOS-CON-009` † | Security & Compliance | In progress | — | — | |
| P0-SEC-06 | AppSec baseline | `FR-SEC-019`, `FR-AUD-007` | Security & Compliance | In progress | — | — | |
| P0-INT-01 | Integration/Ports namespace, canonical CBI v1.0 contract (OpenAPI/JSON Schema), adapter… | `FR-CBA-001`, `FR-CBA-002`, `FR-CBA-020`, `LOS-CON-002` † | Integration | In progress | — | — | |
| P0-INT-02 | Integration Runtime | `FR-CBA-007`, `FR-CBA-008`, `FR-CBA-010`, `FR-CBA-011`, `FR-CBA-016`, `LOS-CON-005` †, `LOS-CON-008` † | BE Disbursement & Integration Runtime | In progress | — | — | |
| P0-LIC-01 | LicensingPort + OfflineSignedFileLicensing (Ed25519, installation fingerprint), enforce… | `LOS-FR-316` | BE Platform & Access | In progress | — | — | |
| P0-UX-01 | Design-system port | — | UI/UX | In review | — | local pest 209 passed; frontend `npm run verify` green | Tokens re-themed to loan-ui (D-042), contrast check 116 pairs / 0 failing, 28 components |
| P0-FE-01 | SPA shell | — | Frontend | In review | — | local pest 209 passed; frontend `npm run verify` green | React 19 SPA shell, Sanctum login + MFA + enrolment, step-up dialog/retry, session expiry, permission-driven nav; dashboard data mocked until P1-RPT-01 |
| P0-QA-01 | Test harness | — | QA/Test | In progress | — | — | |
| P0-REQ-01 | Acceptance criteria for all P0/P1 requirements; 08-traceability-matrix generator wired … | — | Req Analyst | In progress | — | — | |
| P0-DOC-01 | Developer guide, ADR index, API style guide, contribution rules (D-035), runbook skeleton | — | Documentation | In progress | — | — | |

## P1: MVP origination

| Task | Title | Requirement IDs | Owner | Status | PR | Test evidence | Notes |
|---|---|---|---|---|---|---|---|
| P1-PLT-01 | Reference data, calendars, FX rates, multi-currency, effective-dated prudential paramet… | `FR-TEN-006`, `FR-TEN-007`, `LOS-FR-307`, `LOS-FR-310`, `NFR-017` | BE Platform & Access | Not started | — | — | |
| P1-PLT-02 | Admin console API for layers 1–4 (products, rules, matrices, users, workflow JSON), con… | `FR-CFG-001` ◐→P5, `FR-CFG-002`, `NFR-012` | BE Platform & Access | Not started | — | — | |
| P1-SEC-01 | LDAP/AD bind + group mapping (DirectoryPort), Auditor role (read-only, unrestricted scope) | `LOS-FR-305`, `LOS-FR-279` | BE Platform & Access | Not started | — | — | |
| P1-PRD-01 | Product factory | `FR-PRD-001`, `FR-PRD-002`, `FR-PRD-003`, `FR-PRD-004`, `FR-PRD-005`, `FR-PRD-006` | BE Origination & Application | In progress | — | `tests/Feature/Origination`, `tests/Unit/Application` (local PG17 run, 204 passed) | M1: `product` config type + validator (FR-PRD-001..006), catalogue API, pinning; simulate endpoint and availability rules pending |
| P1-PRD-02 | CBA product/GL/branch/currency/customer-type mapping per binding (maker-checker) | `FR-PRD-009`, `FR-CBA-012` | Integration | Not started | — | — | |
| P1-CHN-01 | Staff-assisted channel + API capture, channel attribution, save-and-resume with expiry,… | `FR-CHN-001` ◐→P4, `FR-CHN-002`, `FR-CHN-003`, `FR-CHN-007` | BE Origination & Application | In progress | — | `tests/Feature/Origination`, `tests/Unit/Application` (local PG17 run, 204 passed) | M1: staff + API channel attribution, draft expiry, intake dedupe (flag policy); reminder nudges pending |
| P1-CUS-01 | Party model | `FR-CUS-001` ◐→P3, `FR-CUS-006` | BE Origination & Application | In review | — | `tests/Feature/Origination`, `tests/Unit/Application` (local PG17 run, 204 passed) | M1: individual + limited company, encrypted PII + blind index, relationships, look-through UBO; sole prop/partnership/group are P3 |
| P1-CUS-02 | CBA pre-population, BVN/NIN via IdentityVerificationPort (stub), customer 360 via simul… | `FR-CUS-002`, `FR-CUS-003` ◐→P4, `FR-CUS-009` ◐→P4, `FR-CUS-011` | BE Origination & Application | In progress | — | local pest 209 passed; frontend `npm run verify` green | M2: IdentityVerificationPort + simulator, BVN/NIN verify endpoint; CBA pre-population and customer 360 pending |
| P1-CUS-03 | Risk-based CDD rule table + progression gate, granular consent with withdrawal and hist… | `FR-CUS-007`, `FR-CUS-008`, `FR-CMP-010`, `FR-CMP-021`, `FR-CMP-032` | BE Origination & Application | In progress | — | local pest 209 passed; frontend `npm run verify` green | M2: CDD progression gate (code-defined rule table), granular consent with withdrawal + history; bureau-consent precondition lands with CRD-02 |
| P1-CMP-01 | ScreeningPort (stub) | `FR-CUS-005` ◐→P4, `FR-CMP-011`, `FR-CMP-013` ◐→P4, `FR-CMP-014`, `FR-CMP-017` | BE Decisioning & Approvals | In review | — | local pest 209 passed; frontend `npm run verify` green | M2: ScreeningPort + simulator, intake screening via outbox, alerts with four-eyes disposition, originator exclusion, cleared-hit suppression, sanctions block |
| P1-APP-01 | Event-sourced application aggregate, human reference, canonical state machine + cross-c… | `FR-APP-001`, `FR-APP-006`, `LOS-FR-282`, `LOS-FR-283` | BE Origination & Application | In review | — | `tests/Feature/Origination`, `tests/Unit/Application` (local PG17 run, 204 passed) | M1: event-sourced aggregate, gap-free `{LE}-{YYYY}-{SEQ:6}` reference, canonical state machine + cross-cutting, ETag/If-Match, as-at replay, projection verify |
| P1-APP-02 | Schema-driven dynamic forms, multiple applicants/guarantors, pre-approval amendment wit… | `FR-APP-002`, `FR-APP-004`, `FR-APP-005`, `FR-APP-008`, `LOS-FR-301`, `NFR-013` | BE Origination & Application | In progress | — | `tests/Feature/Origination`, `tests/Unit/Application` (local PG17 run, 204 passed) | M1: multiple applicants/guarantors, pre-approval amendment with field history, provenance; schema-driven forms pending |
| P1-APP-03 | Application- and stage-level SLA clocks over the tenant calendar with pause/resume | `FR-APP-009` | BE Origination & Application | Not started | — | — | |
| P1-DOC-01 | Upload API (web + API, tus resumable), format/size policy, ClamAV gate, SHA-256 before … | `FR-DOC-001` ◐→P4, `FR-DOC-002`, `FR-DOC-004`, `FR-DOC-005`, `FR-DOC-006` | BE Origination & Application | In review | — | local pest 219 passed; frontend verify 107 tests | M3: malware port (clamd + EICAR simulator), SHA-256 before store, encrypted write-once store, immutable versions, hash duplicates; tus resumable and content-similarity duplicates pending |
| P1-DOC-02 | Per-application checklist with statuses, waivers via authority, expiry tracking, manual… | `FR-DOC-007`, `FR-DOC-008`, `FR-DOC-009` | BE Origination & Application | In review | — | local pest 219 passed; frontend verify 107 tests | M3: checklist from pinned product, verify/reject with uploader SoD, validity + documents:expire, waivers via maker-checker at configured authority, auto Documentation→Assessment |
| P1-CRD-01 | Rules engine | `FR-CRD-001`, `FR-CRD-002` ◐→P3 | BE Decisioning & Approvals | Not started | — | — | |
| P1-CRD-02 | CreditBureauPort (one bureau stub), canonical credit profile parser, bureau-before-appr… | `FR-CRD-003` ◐→P4, `FR-CRD-004`, `FR-CMP-020` | BE Decisioning & Approvals | Not started | — | — | |
| P1-CRD-03 | Decision flow | `FR-CRD-008`, `FR-CRD-010`, `FR-CRD-011`, `FR-CRD-014`, `FR-CRD-015`, `FR-CMP-027`, `FR-CMP-043`, `LOS-CON-006` † | BE Decisioning & Approvals | Not started | — | — | |
| P1-CRD-04 | Credit memo | `FR-CRD-013` | BE Decisioning & Approvals | Not started | — | — | |
| P1-CMP-02 | Single-obligor limit check against prudential parameters and CBA exposure | `FR-CMP-023` ◐→P3 | BE Decisioning & Approvals | Not started | — | — | |
| P1-CMP-03 | Jurisdiction-pack framework | `FR-CMP-001` ◐→P5, `FR-CMP-003` | BE Decisioning & Approvals | Not started | — | — | |
| P1-CMP-04 | Verify every Nigeria pack v0 rule against primary source (TRD §9.4); record citation, U… | — | Security & Compliance | Not started | — | — | |
| P1-COL-01 | Collateral | `FR-COL-001`, `FR-COL-002`, `FR-COL-003`, `FR-COL-004`, `FR-COL-005`, `FR-COL-006`, `FR-COL-008` | BE Decisioning & Approvals | Not started | — | — | |
| P1-WFL-01 | Workflow engine | `FR-WFL-001`, `FR-WFL-012` | BE Origination & Application | Not started | — | — | |
| P1-WFL-02 | Routing (role/skill/branch/product/band/load), pull + push queues, reassignment and del… | `FR-WFL-003`, `FR-WFL-004`, `FR-WFL-005` ◐→P3, `FR-WFL-009`, `FR-WFL-010` | BE Origination & Application | Not started | — | — | |
| P1-WFL-03 | Stage SLA enforcement + escalation, send-back with targeted rework, hold with SLA pause… | `FR-WFL-006`, `FR-WFL-007`, `FR-WFL-008`, `FR-WFL-011` | BE Origination & Application | Not started | — | — | |
| P1-APV-01 | Approval matrix as decision table, effective limits (min user/role/org), sequential cha… | `FR-APV-001`, `FR-APV-002`, `FR-APV-003` ◐→P3, `FR-APV-005`, `FR-APV-006`, `FR-APV-007` | BE Decisioning & Approvals | Not started | — | — | |
| P1-APV-02 | Votes with rationale/reason codes/authority basis/step-up ref, conditional approval → C… | `FR-APV-008`, `FR-APV-009`, `FR-APV-010`, `FR-APV-012` | BE Decisioning & Approvals | Not started | — | — | |
| P1-OFR-01 | Offer templates (versioned), key-facts/cost-of-credit per pack method, schedule from CB… | `FR-OFR-001`, `FR-OFR-002`, `FR-OFR-003`, `FR-CMP-025` | BE Disbursement & Integration Runtime | Not started | — | — | |
| P1-OFR-02 | Offer delivery (email + printable), acceptance (wet-sign + e-sign stub), validity/remin… | `FR-OFR-004` ◐→P4, `FR-OFR-005`, `FR-OFR-006` ◐→P4, `FR-OFR-007`, `FR-OFR-008`, `FR-OFR-009`, `FR-OFR-010`, `LOS-FR-314` | BE Disbursement & Integration Runtime | Not started | — | — | |
| P1-CPR-01 | CP/CS items, mandatory-CP hard block, pre-disbursement checklist, pre-disbursement re-s… | `FR-CPR-001`, `FR-CPR-002`, `FR-CPR-003`, `FR-CPR-004` ◐→P4, `FR-CPR-005` ◐→P4 | BE Disbursement & Integration Runtime | Not started | — | — | |
| P1-DSB-01 | Booking saga | `FR-DSB-001`, `FR-DSB-002` ◐→P3, `FR-DSB-004`, `FR-DSB-005`, `FR-DSB-006`, `FR-DSB-007`, `FR-CBA-009`, `LOS-FR-313` | BE Disbursement & Integration Runtime | Not started | — | — | |
| P1-DSB-02 | Disbursement maker-checker (checker ≠ maker ≠ approver, step-up), advice + final schedu… | `FR-DSB-008`, `FR-DSB-009`, `FR-DSB-011`, `FR-CBA-017` | BE Disbursement & Integration Runtime | Not started | — | — | |
| P1-HND-01 | Booked transition, facility.created event + signed webhook, CS/collateral/insurance han… | `FR-HND-001`, `FR-HND-003`, `FR-HND-004` | BE Disbursement & Integration Runtime | Not started | — | — | |
| P1-NTF-01 | Notification module | `FR-NTF-001` ◐→P4, `FR-NTF-002`, `FR-NTF-005` | BE Disbursement & Integration Runtime | Not started | — | — | |
| P1-INT-01 | Capability manifests, unsupported-operation substitution (manual task / batch / LOS com… | `FR-CBA-003`, `FR-CBA-004`, `FR-CBA-005`, `FR-CBA-019` | Integration | Not started | — | — | |
| P1-INT-02 | CBA simulator + mock providers for every MVP port with fault scripts; production-bind r… | `FR-CBA-015`, `FR-CBA-013` ◐→P6, `NFR-006`, `NFR-018` | Integration | Not started | — | — | |
| P1-AUD-01 | PII/application read-access log, integration-call audit, audit explorer API, as-at temp… | `FR-AUD-006`, `FR-AUD-008`, `FR-AUD-009`, `FR-AUD-010` ◐→P5, `FR-AUD-011`, `FR-AUD-016` | BE Platform & Access | Not started | — | — | |
| P1-RPT-01 | Operational dashboard (pipeline, TAT, SLA breaches, queues) with ScopeFilter enforcemen… | `FR-RPT-001` ◐→P5, `FR-RPT-010` | BE Platform & Access | Not started | — | — | |
| P1-UX-01 | MVP screen designs per role journey (capture → booked, auditor), states, empty/error st… | — | UI/UX | Not started | — | — | |
| P1-FE-01 | Admin console UI | — | Frontend | Not started | — | — | |
| P1-FE-02 | Origination UI | — | Frontend | In review | — | local pest 219 passed; frontend verify 107 tests | M3: applications list/pipeline, new-application wizard with dedupe, case workspace (summary, KYC, documents, timeline/as-at), compliance alert queue with four-eyes, customers |
| P1-FE-03 | Assessment UI | — | Frontend | Not started | — | — | |
| P1-FE-04 | Execution UI | — | Frontend | Not started | — | — | |
| P1-FE-05 | Accessibility and browser matrix certification of MVP screens (axe on every screen, man… | `NFR-014`, `NFR-015` † | Frontend | Not started | — | — | |
| P1-QA-01 | MVP E2E suite (demo script §2.3) in Playwright, resilience tests with fault injection, … | — | QA/Test | Not started | — | — | |
| P1-QA-02 | Authorisation-matrix, out-of-scope and RLS tests for every P1 endpoint; OpenAPI conform… | — | QA/Test | Not started | — | — | |
| P1-SEC-02 | MVP security assurance | — | Security & Compliance | Not started | — | — | |
| P1-DEV-01 | MVP install bundle (single-node), installation-qualification checklist, nightly restore… | — | DevOps | Not started | — | — | |
| P1-REQ-01 | UAT scripts per requirement from the matrix; P1 traceability report | — | Req Analyst | Not started | — | — | |
| P1-DOC-03 | MVP user guides per role, admin guide, API guide v1, release notes v0.1 | — | Documentation | Not started | — | — | |

## P2: Documents & IDP

| Task | Title | Requirement IDs | Owner | Status | PR | Test evidence | Notes |
|---|---|---|---|---|---|---|---|
| P2-IDP-01 | IDP service scaffold (Python 3.12/FastAPI, own `idp` schema, OTel, signed image) + Docu… | `FR-DOC-054` | IDP Service | Not started | — | — | |
| P2-IDP-02 | Image pre-processing | `FR-DOC-003` | IDP Service | Not started | — | — | |
| P2-IDP-03 | Classification and bundled-PDF page splitting with routing to checklist | `FR-DOC-020`, `FR-DOC-021` | IDP Service | Not started | — | — | |
| P2-IDP-04 | Field and table extraction for the BRD document set, handwriting (mandatory review), te… | `FR-DOC-022`, `FR-DOC-023`, `FR-DOC-027`, `FR-DOC-028`, `FR-DOC-029` | IDP Service | Not started | — | — | |
| P2-IDP-05 | Per-field confidence with per-field/doc-type/tenant thresholds | `FR-DOC-024` | IDP Service | Not started | — | — | |
| P2-IDP-06 | Statement parsing (PDF/CSV/scanned/API) and transaction categorisation | `FR-DOC-040`, `FR-DOC-041` | IDP Service | Not started | — | — | |
| P2-IDP-07 | Income regularity, debt-service detection, affordability metrics from statements | `FR-DOC-042`, `FR-DOC-043`, `FR-DOC-044` | IDP Service | Not started | — | — | |
| P2-IDP-08 | Statement tampering, forgery indicators, cross-validation against application and other… | `FR-DOC-045`, `FR-DOC-050`, `FR-DOC-052` | IDP Service | Not started | — | — | |
| P2-DOC-01 | HITL verification queue in LOS, correction capture as training feedback, document lineage | `FR-DOC-025`, `FR-DOC-026`, `FR-DOC-053` | BE Origination & Application | Not started | — | — | |
| P2-DOC-02 | Statement-analysis drill-through API (metric → transactions) | `FR-DOC-046` | BE Origination & Application | Not started | — | — | |
| P2-FE-01 | Document viewer (annotation, redaction, side-by-side, page accept/reject), HITL screen,… | `FR-DOC-010` | Frontend | Not started | — | — | |
| P2-QA-01 | Nigerian document benchmark corpus (anonymised), accuracy and p95 latency harness on ce… | `NFR-003` † | QA/Test | Not started | — | — | |
| P2-SEC-01 | IDP service security review | — | Security & Compliance | Not started | — | — | |

## P3: Decisioning & workflow depth

| Task | Title | Requirement IDs | Owner | Status | PR | Test evidence | Notes |
|---|---|---|---|---|---|---|---|
| P3-PRD-01 | Product availability rules (channel/branch/segment/date) and product simulation sandbox | `FR-PRD-007`, `FR-PRD-008` | BE Origination & Application | Not started | — | — | |
| P3-TEN-01 | Tenant terminology overrides across UI, documents and notifications | `FR-TEN-005` | BE Platform & Access | Not started | — | — | |
| P3-APP-01 | Extension attributes (JSON Schema, GIN) usable in rules/templates/reports; application … | `FR-APP-003`, `FR-APP-007` | BE Origination & Application | Not started | — | — | |
| P3-CUS-01 | Remaining applicant types (sole proprietor, partnership, group/cooperative) | completes `FR-CUS-001` | BE Origination & Application | Not started | — | — | |
| P3-CUS-02 | Related-party/connected-exposure linkage and aggregated exposure (CBA + in-flight) | `FR-CUS-010`, `FR-CRD-007` | BE Decisioning & Approvals | Not started | — | — | |
| P3-CRD-01 | Application and behavioural scorecards (versioned) + common scoring interface for exter… | `FR-CRD-005`, `FR-CRD-006` | BE Decisioning & Approvals | Not started | — | — | |
| P3-CRD-02 | Risk-based pricing matrices | `FR-CRD-009` | BE Decisioning & Approvals | Not started | — | — | |
| P3-CRD-03 | Financial spreading for SME/corporate (manual + extracted), ratios, projections | `FR-CRD-012` | BE Decisioning & Approvals | Not started | — | — | |
| P3-CRD-04 | Decision-time fraud checks: velocity, device/IP, internal blacklist | `FR-CRD-017` | BE Decisioning & Approvals | Not started | — | — | |
| P3-CRD-05 | Historical application import and simulation against stored snapshots with outcome diff | `LOS-FR-308`; completes `FR-CRD-002` | BE Decisioning & Approvals | Not started | — | — | |
| P3-WFL-01 | Parallel branches with join conditions; out-of-office cover | `FR-WFL-002`; completes `FR-WFL-005` | BE Origination & Application | Not started | — | — | |
| P3-APV-01 | Parallel and quorum approval; committee workflow (agenda, packet, e-voting, minutes); m… | `FR-APV-004`, `FR-APV-011`; completes `FR-APV-003` | BE Decisioning & Approvals | Not started | — | — | |
| P3-CMP-01 | Insider/related-party register, automatic insider flag + elevated path, sector/insider … | `LOS-FR-306`, `FR-CMP-024`; completes `FR-CMP-023` | BE Decisioning & Approvals | Not started | — | — | |
| P3-CMP-02 | Model inventory, validation record + maker-checker before activation, drift monitoring | `FR-CMP-040`, `FR-CMP-041`, `FR-CMP-042` | BE Decisioning & Approvals | Not started | — | — | |
| P3-CPR-01 | Post-disbursement conditions subsequent tracking, servicing handover, breach escalation | `FR-CPR-006` | BE Disbursement & Integration Runtime | Not started | — | — | |
| P3-DSB-01 | Tranched/milestone, revolving activation, third-party payout modes; tranche conditions/… | `FR-DSB-003`; completes `FR-DSB-002` | BE Disbursement & Integration Runtime | Not started | — | — | |
| P3-DSB-02 | Disbursement cancellation/reversal within window with compensating CBA instructions | `FR-DSB-012` | BE Disbursement & Integration Runtime | Not started | — | — | |
| P3-HND-01 | Post-booking audited amendment process | `FR-HND-005` | BE Disbursement & Integration Runtime | Not started | — | — | |
| P3-UX-01 | Designs | — | UI/UX | Not started | — | — | |
| P3-FE-01 | UI for P3 capabilities incl. mobile-responsive approval | — | Frontend | Not started | — | — | |
| P3-QA-01 | Replay and simulation regression, committee E2E, tranche saga fault tests | — | QA/Test | Not started | — | — | |

## P4: Live integrations & channels

| Task | Title | Requirement IDs | Owner | Status | PR | Test evidence | Notes |
|---|---|---|---|---|---|---|---|
| P4-INT-01 | Finacle live adapter | completes `FR-CUS-009` | Integration | Not started | — | — | |
| P4-INT-02 | Generic file + manual-queue fallback CBA adapter | `FR-CBA-006` | Integration | Not started | — | — | |
| P4-INT-03 | NIBSS BVN / NIMC NIN live, liveness/selfie match, issuing-authority verification (CAC, … | `FR-CUS-004`, `FR-DOC-051`; completes `FR-CUS-003` | Integration | Not started | — | — | |
| P4-INT-04 | Live bureaus (CRC, FirstCentral, CreditRegistry) with selection and fallback order | completes `FR-CRD-003` | Integration | Not started | — | — | |
| P4-INT-05 | Screening provider live, offline list snapshots, watchlist-update batch re-screen | `FR-CMP-012`; completes `FR-CUS-005`, `FR-CPR-004`, `FR-CMP-013` | Integration | Not started | — | — | |
| P4-INT-06 | Payments (NIP name enquiry/transfer), e-mandate/standing instruction, Remita/payroll ve… | `FR-DSB-010`, `LOS-FR-287`, `LOS-FR-315`, `LOS-FR-311`; completes `FR-CPR-005` | Integration | Not started | — | — | |
| P4-INT-07 | E-signature provider live (in-country/on-prem processing) | completes `FR-OFR-006` | Integration | Not started | — | — | |
| P4-INT-08 | Valuation, insurance and collateral-registry adapters | `LOS-FR-285`, `LOS-FR-286`, `FR-COL-007` | Integration | Not started | — | — | |
| P4-INT-09 | Records/DMS handover (CMIS / file drop) | `FR-HND-002` | Integration | Not started | — | — | |
| P4-INT-11 | AtherisLicensingServer adapter behind LicensingPort (online check-in, activation, revoc… | — | BE Platform & Access | Not started | — | — | |
| P4-INT-10 | Processing-location declaration in manifests; activation refused on residency breach | `LOS-FR-309` | BE Disbursement & Integration Runtime | Not started | — | — | |
| P4-NTF-01 | SMS, WhatsApp, Web Push channels; communication preferences/opt-out; duplicate suppress… | `LOS-FR-304`, `FR-NTF-003`, `FR-NTF-006`; completes `FR-NTF-001`, `FR-OFR-004` | BE Disbursement & Integration Runtime | Not started | — | — | |
| P4-SEC-01 | SAML 2.0/OIDC SSO + SCIM 2.0, WebAuthn, IP allow-list and device binding | `FR-SEC-012`, `FR-SEC-020` | BE Platform & Access | Not started | — | — | |
| P4-CHN-01 | Applicant portal API (/portal/v1) with OTP auth, status tracking and outstanding-item p… | `LOS-FR-303`, `FR-NTF-004` | BE Origination & Application | Not started | — | — | |
| P4-CHN-02 | Leads (capture/assign/convert/loss reason) and indicative pre-qualification | `FR-CHN-005`, `FR-CHN-006` | BE Origination & Application | Not started | — | — | |
| P4-CHN-03 | Partner API (/partner/v1), partner-scoped credentials and rate limits, Partner/DSA own-… | `FR-CHN-008`, `LOS-FR-281` | BE Origination & Application | Not started | — | — | |
| P4-CHN-04 | Canonical bulk-upload template with row-level validation report | `LOS-FR-312` | BE Origination & Application | Not started | — | — | |
| P4-FE-01 | Applicant/agent PWA | `FR-CHN-004`, `NFR-016` †; completes `FR-CHN-001`, `FR-DOC-001` | Frontend | Not started | — | — | |
| P4-UX-01 | Portal/agent PWA designs and low-bandwidth patterns | — | UI/UX | Not started | — | — | |
| P4-SEC-02 | External-facing surface assurance | — | Security & Compliance | Not started | — | — | |
| P4-QA-01 | Certification-kit runs per live adapter; contract tests; portal E2E on throttled network | — | QA/Test | Not started | — | — | |

## P5: Compliance, reporting & data

| Task | Title | Requirement IDs | Owner | Status | PR | Test evidence | Notes |
|---|---|---|---|---|---|---|---|
| P5-TEN-01 | Tenant branding (letterhead, templates, domains) and multi-language UI/documents | `FR-TEN-004`, `FR-TEN-008` | BE Platform & Access | Not started | — | — | |
| P5-CFG-01 | Config export/import between environments, diff and rollback, onboarding accelerator te… | `FR-TEN-010`, `FR-TEN-011`, `FR-CFG-003`; completes `FR-CFG-001` | BE Platform & Access | Not started | — | — | |
| P5-CMP-01 | Source of funds/wealth capture at EDD; goAML-shaped STR extracts [verify schema] | `FR-CMP-015`, `FR-CMP-016` | BE Decisioning & Approvals | Not started | — | — | |
| P5-CMP-02 | CRMS origination obligations and regulatory report generation with submission log | `FR-CMP-022`, `FR-RPT-006` | BE Decisioning & Approvals | Not started | — | — | |
| P5-CMP-03 | Adverse action notices, human review of automated decisions, fair-lending distributions… | `FR-CMP-026`, `FR-CMP-044`, `FR-CMP-045` | BE Decisioning & Approvals | Not started | — | — | |
| P5-CMP-04 | NDPA/GAID | `FR-CMP-030`, `FR-CMP-031`, `FR-CMP-033`, `FR-CMP-034`, `FR-CMP-035`, `FR-CMP-037` | BE Decisioning & Approvals | Not started | — | — | |
| P5-CMP-05 | Nigeria commercial-bank pack v1 | completes `FR-CMP-001` | Security & Compliance | Not started | — | — | |
| P5-AUD-01 | SIEM streaming (CEF/JSON syslog-TLS), suspicious-pattern alerts; evidence pack with Ed2… | `FR-AUD-014`, `FR-AUD-015`; completes `FR-AUD-010` | BE Platform & Access | Not started | — | — | |
| P5-SEC-01 | Break-glass, access recertification campaigns, deterministic anonymiser + refresh guard… | `FR-SEC-009`, `FR-SEC-010`, `FR-SEC-018`, `LOS-FR-280` | BE Platform & Access | Not started | — | — | |
| P5-RPT-01 | Management, risk, productivity and funnel reporting | `FR-RPT-002`, `FR-RPT-003`, `FR-RPT-004`, `FR-RPT-005`; completes `FR-RPT-001` | BE Platform & Access | Not started | — | — | |
| P5-RPT-02 | Governed semantic layer for user-defined reports, scheduled delivery (CSV/XLSX/PDF) wit… | `FR-RPT-007`, `FR-RPT-008`, `FR-RPT-009` | BE Platform & Access | Not started | — | — | |
| P5-LIC-01 | Licence and support console | `LOS-FR-274`, `LOS-FR-275`, `LOS-FR-276`, `LOS-FR-277` | BE Platform & Access | Not started | — | — | |
| P5-DAT-01 | Full tenant data export in open formats (JSONL + CSV + originals + manifest) | `NFR-020` † | BE Platform & Access | Not started | — | — | |
| P5-FE-01 | UI for compliance workspaces, DSR, reports, console | — | Frontend | Not started | — | — | |
| P5-QA-01 | Report scope tests, DSR/retention tests (incl. legal hold), regulatory extract format t… | — | QA/Test | Not started | — | — | |

## P6: Hardening & certification

| Task | Title | Requirement IDs | Owner | Status | PR | Test evidence | Notes |
|---|---|---|---|---|---|---|---|
| P6-TEN-01 | Dedicated isolation option (schema/database per tenant via connection resolver) | `FR-TEN-002` | Database | Not started | — | — | |
| P6-AUD-01 | WORM certification for audit and document stores (Object Lock compliance mode, detached… | `FR-AUD-013` | Database | Not started | — | — | |
| P6-INT-01 | Adapter SDK + docs, out-of-process adapters over mTLS, hot-swap with version pinning; c… | `FR-CBA-014`, `FR-CBA-018`; completes `FR-CBA-013` | Integration | Not started | — | — | |
| P6-CFG-01 | Upgrade-safe configuration and obsolete-config detection | `FR-CFG-004` | BE Platform & Access | Not started | — | — | |
| P6-SEC-01 | Vault Transit + PKCS#11 KEK, customer-managed keys, key rotation; external pen test; AS… | completes `FR-SEC-016` | Security & Compliance | Not started | — | — | |
| P6-DEV-01 | HA reference profile, Helm chart, Ansible roles, signed air-gap bundle; zero-downtime u… | `NFR-010` | DevOps | Not started | — | — | |
| P6-DEV-02 | Synchronous standby failover, pgBackRest PITR, DR runbook and exercise | `NFR-008`, `NFR-007` † | DevOps | Not started | — | — | |
| P6-DEV-03 | Availability SLOs and alert pack (outbox lag, breaker, audit verify, recon, licence, re… | `NFR-005` † | DevOps | Not started | — | — | |
| P6-QA-01 | k6 performance and scale certification at reference profile (300 staff, 2,000 apps/day)… | `NFR-001` †, `NFR-002` †, `NFR-004` † | QA/Test | Not started | — | — | |
| P6-DOC-01 | Installation qualification, operations runbooks, security evidence pack for bank InfoSe… | — | Documentation | Not started | — | — | |

## Gate log

| Gate | Date | Evidence pack | PO decision | Conditions |
|---|---|---|---|---|
| P0 exit | | | | |
| P1 exit | | | | |
| P2 exit | | | | |
| P3 exit | | | | |
| P4 exit | | | | |
| P5 exit | | | | |
| P6 exit | | | | |

## Change log

| Date | Change | By |
|---|---|---|
| 2026-10-08 | Tracker initialised from `06-development-plan.md`; P0 tasks set to In progress | Tech Lead |
