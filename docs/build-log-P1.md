# Build log — Phase 1 (MVP origination)

Running log of P1 milestones against `06-development-plan.md` §3.1. Each
milestone records scope, requirement IDs with evidence (`pest --group=<ID>`),
design decisions and known gaps. Gate evidence is assembled in
`docs/gates/P1.md` at the end of the phase.

## Milestone 1 — Product, Party and Application core (2026-10-08)

**Gate results (local, PostgreSQL 17 as `fundly_app`):** Pest **204 passed,
0 failed** (7,990 assertions; 176 P0 + 28 new). Larastan level 8: 0 errors, no
baseline. Pint: pass. OpenAPI: 14 new operations; every response in the new
tests is validated against the contract.

### Architecture (loose coupling)

Three new modules, each with its own `Contracts` namespace and service
provider. No module imports another module's Domain, Application or
Infrastructure (enforced by `tests/Architecture/LayeringTest`).

| Module | Publishes (Contracts) | Consumes |
|---|---|---|
| M03 Product | `ProductCatalogue`, `ProductView` | Platform `ConfigTypeCatalogue`, `ActiveConfiguration` |
| M05 Party | `PartyDirectory`, `PartySummary` | Shared crypto/masking only |
| M06 Application | `ApplicationReader`, `ApplicationLifecycle`, `CanonicalStatus`, events `ApplicationCreated` / `ApplicationStatusChanged` | `ProductCatalogue`, `PartyDirectory`, Platform `OrganisationDirectory` |

Platform gained three read contracts (`ConfigTypeCatalogue`,
`OrganisationDirectory`, an extended `ActiveConfiguration` with pinned-version
reads). Other modules drive the application through `ApplicationLifecycle`,
which dispatches an audited `AdvanceApplication` command; cross-module reactions
subscribe to `ApplicationStatusChanged` (D-043).

**Products are configuration.** A product is a `product` configuration
artefact, so it inherits P0's draft → review → approved → active lifecycle,
maker-checker activation (D-040a), immutability trigger and versioning. M03
contributes the content validator (`Domain/ProductDefinition`, pure, decimal
strings only) and the read catalogue.

### Requirements

| ID | Status | Evidence |
|---|---|---|
| FR-PRD-001 | Done | `ProductCatalogueTest` |
| FR-PRD-002 | Done (salary-backed refused per D-038c) | `ProductCatalogueTest` |
| FR-PRD-003 | Done (limits enforced at submit) | `ProductCatalogueTest`, `ApplicationTest` |
| FR-PRD-004 | Done (applicant type / segment / channel / amount band) | `ProductCatalogueTest` |
| FR-PRD-005 | Partial: bindings stored and validated; engines bind in P1-WFL-01 / CRD-01 / APV-01 | `ProductCatalogueTest` |
| FR-PRD-006 | Done | `ProductCatalogueTest`, `ApplicationTest` |
| FR-CUS-001 | Partial: individual + limited company (others P3) | `PartyTest` |
| FR-CUS-006 | Done (layered look-through ownership) | `PartyTest` |
| FR-CHN-002 | Done | `ApplicationTest` |
| FR-CHN-007 | Done for intake (flag policy; block policy is configuration in CHN-01) | `PartyTest` |
| FR-APP-001 | Done (gap-free `{LE}-{YYYY}-{SEQ:6}`) | `ApplicationTest` |
| FR-APP-004 | Done | `ApplicationTest`, `StateMachineTest` |
| FR-APP-005 | Done (re-triggering of checks arrives with the checks) | `ApplicationTest`, `StateMachineTest` |
| FR-APP-006 | Done (decline/expiry are driven by Credit/Approval) | `ApplicationTest`, `StateMachineTest` |
| FR-APP-008 | Partial: data completeness + product checklist; document status joins in DOC-02 | `ApplicationTest` |
| LOS-FR-282 | Done | `StateMachineTest`, `ApplicationTest` |
| LOS-FR-283 | Done | `StateMachineTest`, `ApplicationTest` |
| LOS-FR-301 | Done | `ApplicationTest` |
| FR-AUD-010 | Partial: application as-at replay; audit-explorer as-at in AUD-01 | `ApplicationTest`, `StateMachineTest` |
| FR-SEC-017 / FR-CMP-036 | Extended to party PII | `PartyTest` |

### Data and controls
- `application_events`: append-only (INSERT/SELECT grant only, mutation and
  truncate triggers), unique `(application_id, version)`. Concurrent writers
  get `409 application-concurrent-update`; stale `If-Match` gets `412`.
- `applications` / `application_applicants` are projections;
  `php artisan applications:verify-projection [--fix]` replays and compares.
- `field_provenance`: append-only, one row per field path per version.
- Party PII (BVN/NIN/passport, DOB, phone, email, TIN, address) is
  XChaCha20-Poly1305 encrypted with a per-field blind index; names use a
  `pg_trgm` index for fuzzy dedupe. Responses carry masked values only.
- All new tables: `tenant_id`, `FORCE ROW LEVEL SECURITY`, composite tenant FKs.

### Housekeeping
- `backend/.env.testing` isolates the suite from a developer's local `.env`.
- PHPStan's result cache (`storage/framework/phpstan`) is no longer tracked.
- `CommitsToDatabase` discovers append-only tables instead of a fixed list.
- OpenAPI: new operations are authored as PHP fragments in
  `api/openapi/fragments/` and spliced into `openapi.yaml` between generated
  markers by `php api/openapi/fragments/build.php`.

### Known gaps carried forward
- Dynamic schema-driven forms (FR-APP-002), SLA clocks (APP-03), reminder
  nudges (CHN-03), CBA pre-population and BVN verification (CUS-02).
- The local run used PostgreSQL 17; CI pins 16. Nothing here is 17-specific.

## Milestone 2 — KYC, screening and the staff SPA foundation (2026-10-08)

**Gate results:** Pest **209 passed, 0 failed** (9,341 assertions). Larastan L8
0 errors, Pint pass. Frontend `npm run verify`: tsc strict, ESLint (0
warnings), Vitest 38 passed, contrast 116 pairs / 0 failing, production build.
CI gains a `frontend` job that also fails on API-client drift.

### Journey now supported (demo steps 4–7)
Capture company + directors + guarantor → verify BVNs → record consents →
submit → **automatic** pre-qualification → KycScreening → intake screening
(outbox, off the request path) → alert → four-eyes disposition →
**automatic** move to Documentation when the CDD gate is clear.

### Architecture
- **New ports** `identity_verification` and `screening` with deterministic
  simulators (`integration:bind-simulators` for non-production). Modules call
  them only through `IntegrationGateway` (breaker, inline read retries,
  masked call log).
- **M18 Compliance** (new): screening runs (append-only evidence with list
  version and provider reference), alerts, `FourEyes` and `KycGate` (pure
  domain), `KycProgression` listener.
- **M10 Workflow** (new, slice): `IntakeAutomation` — Submitted → PreQualified
  (no auto-decline, D-038b) → KycScreening.
- Cross-module flow is entirely event-driven (D-043):
  `ApplicationStatusChanged` → Workflow / Compliance; `PartyKycChanged`,
  `ApplicationScreened`, `ScreeningAlertResolved` → gate re-evaluation →
  `ApplicationLifecycle::advance` as `system:workflow`. Listener failures are
  logged and never undo the triggering change.
- New system identity `system:workflow` (FR-AUD-005 attribution).

### Requirements
| ID | Status | Evidence |
|---|---|---|
| FR-CUS-002 / 003 | Partial: verification via port (simulator); CBA pre-population in CUS-02 | `KycFlowTest` |
| FR-CUS-005 | Done for intake (applicants, directors, signatories, UBOs); pre-disbursement re-screen in CPR-01 | `KycFlowTest` |
| FR-CUS-007 | Partial: MVP rule table in code (`cdd-mvp-1`); tenant tables with the jurisdiction pack | `KycFlowTest` |
| FR-CUS-008, FR-CMP-010/021/032 | Done | `KycFlowTest` |
| FR-CMP-011 / 013 / 014 / 017 | Done (simulator lists) | `KycFlowTest` |

### Frontend (P0-UX-01 / P0-FE-01)
`frontend/`: React 19 + TS strict + Vite, TanStack Query, generated
`openapi-fetch` client, Tailwind preset from the re-themed tokens (D-042),
self-hosted Plus Jakarta Sans. Sign-in, MFA, enrolment, step-up retry, session
expiry and permission-driven navigation run against the real API. The
dashboard reproduces the loan-ui layout on mock data (`TODO(P1-RPT-01)`).
Local dev: `deploy/dev/bootstrap-local.sh` (synthetic dev accounts only).

### Gaps carried forward
- Confirmed PEP holds the gate until the EDD / source-of-funds workflow
  (FR-CMP-015, D-038a) ships.
- ProblemRenderer returns `http-error` for Laravel's 419; give it
  `csrf-token-mismatch` (small backend fix).
- Tailwind v3 dev-dependency advisories (build-time only) clear with Tailwind v4.

## Milestone 3 — Documents and the origination UI (2026-10-08)

**Gate results:** Pest **219 passed, 0 failed** (10,983 assertions); Larastan
L8 0 errors; Pint pass. Frontend `npm run verify`: 107 Vitest tests, ESLint 0
warnings, contrast 125 pairs / 0 failing, build OK.

### Backend — M07 Documents (P1-DOC-01/02)
- `MalwareScanPort` (new): `clamav-clamd` INSTREAM adapter for installations,
  EICAR-detecting simulator for UAT/tests.
- Upload pipeline: sniff type → size/format policy → **SHA-256 before storage**
  → scan → clean files encrypted (XChaCha20-Poly1305, per-tenant
  `document_dek`, version id as AAD) and written once; infected files keep
  only hash + signature (`document_versions_quarantine_chk` makes a stored
  infected version impossible); content reads return 423 for quarantined
  versions and are audited; downloads carry the sniffed type + `Digest`.
- Immutable `document_versions`; cross-application duplicate detection by hash.
- Checklist derived from the pinned product (type / amount band), statuses per
  FR-DOC-007, verify (with validity) / reject, uploader ≠ verifier,
  `documents:expire`, waivers through maker-checker at the item's configured
  authority (`document:waive_approve` or `application:approve`), and an
  event-driven Documentation → Assessment move.

| ID | Status | Evidence |
|---|---|---|
| FR-DOC-001 | Partial: web/API multipart; camera, email and scanner channels P4 | `DocumentTest` |
| FR-DOC-002 / 004 / 005 | Done | `DocumentTest` |
| FR-DOC-006 | Partial: hash duplicates; content similarity with IDP (P2) | `DocumentTest` |
| FR-DOC-007 / 008 / 009 | Done (`extracted` status arrives with IDP) | `DocumentTest` |

### Frontend — P1-FE-02 origination slice
Applications list / pipeline, new-application wizard (product → customer with
live dedupe and inline directors → branch and terms → review), case workspace
with stage tracker and tabs (Summary, Applicant & KYC with the CDD gate,
Documents with upload / quarantine / verify / waiver, Timeline with as-at),
compliance alert queue with four-eyes drawer, customers. If-Match on every
mutation with a 412 recovery banner. `deploy/dev/bootstrap-local.sh seed-demo`
builds a demo tenant (users lola / chidi / ngozi) through the API.

### Fixes from UI review
Loan officer template can read legal entities / org units; disposition
response carries the party name; 419 renders `csrf-token-mismatch`.

### Gaps carried forward
Submit response reflects the pre-automation state (UI refetches); timeline has
no actor display names; screening needs a queue worker locally
(`php artisan queue:work`); PII unmask endpoint and resumable uploads.

## Milestone 4 — Rules engine and credit decisioning (2026-10-08)

**Gate results:** Pest **231 passed, 0 failed** (13,125 assertions); Larastan
L8 0 errors; Pint pass. Frontend `npm run verify`: 134 tests, contrast 138
pairs / 0 failing, build OK.

### Rules engine (P1-CRD-01, D-020)
`Shared/Rules`: an in-house V1 lexer, Pratt parser and evaluator instead of
symfony/expression-language, because the latter parses `0.4` as a float.
Every number is an exact brick/math decimal (division 10 dp, half-even);
only whitelisted pure functions; missing facts are null with defined
semantics; `DecisionTable` with UNIQUE / FIRST / PRIORITY / COLLECT and a
per-row trace; `EvaluatorRegistry` keeps released evaluators loadable for
replay. Rule sets are `credit.rule_set` configuration (maker-checker
activation); the validator checks fact roots, functions and reason codes,
and rows cannot set the outcome, so P1 never auto-declines (D-038b).

### Credit (M08, P1-CRD-02..04)
- `CreditBureauPort` + deterministic simulator; pull requires the party's
  `credit_bureau` consent (`bureau-consent-missing`), stores the canonical
  profile with a 30-day validity window.
- Decision flow: formulas → knock-out → policy → grade → affordability →
  pricing → outcome (approve / refer / counter_offer at the maximum
  affordable amount); immutable `decision_snapshots` with facts, rule-set
  and evaluator versions, outputs and trace; replay compares against the
  recorded versions.
- Policy exceptions tied to the decision's own reason codes (severity feeds
  approval escalation via the `CreditFile` contract).
- Versioned credit memo with frozen auto-populated sections.
- `TransitionGuard` (Application contract, dependency inversion): Credit
  blocks `recommend` until bureau + decision + memo exist.

| ID | Status | Evidence |
|---|---|---|
| FR-CRD-001 | Done | `EvaluatorTest`, `CreditDecisionTest` |
| FR-CRD-002 | Partial: tables + expressions; decision trees and simulation P3 | `EvaluatorTest` |
| FR-CRD-003 | Partial: one bureau (simulator); live adapters P4 | `CreditDecisionTest` |
| FR-CRD-004 / 008 / 010 / 011 / 013 / 014 / 015 | Done | `CreditDecisionTest` |
| FR-CMP-020 / 027 / 043, LOS-CON-006 | Done | `CreditDecisionTest` |

### Frontend (P1-FE-03 slice)
Credit tab: bureau pull per applicant with profile, decision card (outcome,
grade, reason codes, terms, DSR meter), replay with result banner and
expandable trace, exceptions, memo editor with version history, and a
"ready to recommend" checklist mirrored in the Recommend dialog. Seed adds
the `sme-policy` rule set, analyst user tunde and cases in Assessment.

### Gaps carried forward
Actor display names on credit records; tighter OpenAPI types for `facts` /
`trace`; approval matrix and voting (P1-APV-01/02) are next.

## Milestone 5 — Approval matrix, authority and voting (2026-10-13)

**Gate results:** Pest **245 passed, 0 failed** (18,009 assertions); Larastan
L8 0 errors. Frontend `npm run verify`: 142 tests, contrast pass, build OK.
Checked in the browser against the seeded stack: kemi (L1) voted
"approve with conditions" on DEMO-2026-000002, which moved it to Approved
with a 60-day validity and one open CP.

### Approvals (M11, P1-APV-01/02)
- New `Approval` module (Contracts / Domain / Application / Infrastructure /
  Http). Other modules are reached only through contracts: `ApplicationReader`,
  `ApplicationLifecycle`, `CreditFile`, `ProductCatalogue`,
  `ActiveConfiguration`, `OrganisationDirectory`, plus a new Access contract
  `AuthorityDirectory`. It answers "who holds `application:approve` for this
  resource, through which assignment or delegation". It also returns staff
  display names, which closes the actor-name gap for approvals.
- **Matrix** = `approval.matrix` configuration, bound by the product's
  `bindings.approval_matrix` and activated through maker-checker. It holds
  ordered levels (code, name, eligible role codes, mode) and a routing
  decision table over `exposure`, `decision`, `exceptions`, `product`,
  `application`, `collateral` and `customer` facts, run by the same V1
  evaluator. It also sets escalation rules, a step-up threshold and an
  optional validity. Only `single` mode validates; parallel and quorum are
  P3 (FR-APV-003 ◐, D-038d).
- **Routing:** when an application becomes Recommended, `ApprovalProgression`
  opens a request as `system:workflow` and advances Recommended → Approval in
  the same transaction. The request records the matrix version and row, the
  facts, the routing trace and the chain. Escalation (FR-APV-007) works in two
  ways:
  - a medium-or-higher exception adds the next level up;
  - a level nobody can approve is replaced by the next level up.
- **Authority limits:** `approval.authority_limits` holds limits per role,
  org unit and user. The effective limit is the minimum of these and the
  assignment scope's `max_amount` (FR-APV-002). If no limit applies there is
  no authority: approvals fail closed (D-044). Delegations reuse Access
  delegations, and a delegated grant is capped by the delegator's limits
  (FR-APV-006).
- **Votes** are append-only and immutable (FR-APV-008/009). Each records:
  - decision: approve, approve with conditions, decline or counter-offer
  - rationale (mandatory)
  - reason codes (mandatory except for a plain approval)
  - conditions, which become `approval_conditions` CP/CS rows
  - counter terms, which must be below the exposure
  - the full authority basis: assignment, role, delegation, effective limit
    and its source, all limit components, level, matrix row, exposure
  - the step-up reference
- **Approver SoD** (FR-APV-005): the voter cannot be the originator, the
  recommender or an earlier voter on the request. Refusals are 403s and are
  audited as `authz.denied` (`approver_sod` / `approval_authority`). Approval
  also needs a current bureau report (FR-CMP-020). Above the matrix threshold
  a step-up within the last 5 minutes is required (FR-SEC-013).
- **Validity** (FR-APV-012): the final approval sets `valid_until` from the
  matrix or the product's `approval_validity_days`. The hourly
  `approvals:expire` job moves lapsed approvals, and an application still in
  Approved, to Expired. `ApprovalRegister::current()->isValidAt()` is the
  hook for the disbursement re-check.
- **Decision packet** (FR-APV-010): `GET /approval-requests/{id}` returns the
  application, the routed vs latest credit decision, exceptions, the memo
  recommendation, bureau currency, earlier requests and `my_eligibility`
  (can_vote, blockers, basis, step_up_required).
- API: `GET /applications/{id}/approval-requests`, `GET /approval-requests`
  (my inbox), `GET /approval-requests/{id}`,
  `POST /approval-requests/{id}/actions/vote` (fragment `p5-approvals.php`).

| ID | Status | Evidence |
|---|---|---|
| FR-APV-001 | Done | `ApprovalTest` (routing, matrix validation) |
| FR-APV-002 | Done | `ApprovalTest` (role / org-unit / user / scope limits), `ApprovalDomainTest` |
| FR-APV-003 | Partial: sequential single-approver levels; parallel / quorum P3 | `ApprovalTest` |
| FR-APV-005 | Done | `ApprovalTest` (originator, recommender), `ApprovalDomainTest` (earlier voter) |
| FR-APV-006 | Done | `ApprovalTest` (delegate capped by delegator limit) |
| FR-APV-007 | Done | `ApprovalTest`, `ApprovalDomainTest` |
| FR-APV-008 / 009 / 010 / 012 | Done | `ApprovalTest` |

### Frontend (P1-FE-03 approval slice)
- **Approval tab** on the case:
  - the chain, with why each level is there
  - votes with rationale, reason codes and authority basis
  - conditions (CP/CS)
  - a "Your vote" panel showing eligibility or the reasons you can't vote
  - the decision packet
  - earlier requests
- **Vote dialog**: client rules mirror the API. Step-up is handled by the
  existing `StepUpProvider` retry.
- **Approvals inbox** (`/approvals`, nav item for `application:approve`).
- `features/approvals/domain.ts` has 8 unit tests.

### Demo seed
`seed-demo` now activates `approval.matrix/sme-matrix` and
`approval.authority_limits/default`. It adds kemi (Credit Approver, L1,
NGN 20M) and segun (Head of Credit, L2, NGN 100M), and takes Chinedu Eze
(NGN 2M) through bureau, decision, memo and recommend into Approval at L1.

### Gaps carried forward
- Vote → application status runs as a nested command in the vote
  transaction, so `ApplicationStatusChanged` listeners for
  approved / declined / counter_offered run before the outer commit. No
  listener reacts to those states yet. Offer (M6) should listen to
  `ApprovalDecided` instead.
- Exposure is the requested amount. Connected exposure (FR-CRD-007) and
  collateral coverage are null facts until P1-COL-01 / P3.
- Conditions are recorded as `open`. Satisfying and waiving them belongs to
  P1-CPR-01.
- A routing failure (no matrix bound or active) is only logged. The case
  stays Recommended and the tab explains this. There is no "re-route" action
  yet.
