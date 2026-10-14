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

## Milestone 6 — Offer, delivery, signing and acceptance (2026-10-13)

**Gate results:** Pest **257 passed, 0 failed**; Larastan L8 0 errors; Pint
pass. Frontend `npm run verify`: 148 tests, contrast pass, build OK. Smoke
test on the seeded dev stack as femi (legal officer), on DEMO-2026-000006
(Amaka Nwosu, NGN 3M / 12 months at 24.5%):
- issue: instalment NGN 284,405.20, total cost of credit NGN 457,862.41,
  APR 27.47%, effective annual rate 31.20%
- email sent through the outbox; printed copy recorded
- borrower, then bank, signed in order
- accepted with the executed upload, and the case is now Accepted

### Offer & execution (M12, P1-OFR-01/02)
- New `Offer` module. It reaches other modules only through contracts:
  `ApprovalRegister` (standing approval, counter terms, conditions),
  `CreditFile` (rate), `ProductCatalogue`, `ActiveConfiguration`,
  `PartyDirectory` (new `contactEmail()`), `OrganisationDirectory`,
  `AuthorityDirectory` and `ApplicationLifecycle`. It also uses two new
  contracts:
  - `Document/DocumentArchive` files generated and uploaded documents in
    the encrypted, immutable vault, so offer letters and executed
    agreements appear under Documents and download through the existing
    content endpoint.
  - `Offer/OfferRegister` exposes the accepted terms, schedule and
    agreement hash for booking (LOS-FR-313).
- **Template** = `offer.template` configuration, bound by the product's new
  `bindings.offer_template` and activated through maker-checker. It holds
  HTML with whitelisted `{{ variables }}` and required `{{ key_facts }}`,
  `{{ schedule }}` and `{{ signatures }}` blocks (`{{ conditions }}` is
  optional). The template also sets the execution method, signing order,
  validity, re-issue limit and disclosure method. Scripts, event handlers
  and remote URLs are rejected. `e_sign` is refused until the P4 provider
  (FR-OFR-006).
- **Terms** come from the standing approval: the requested amount and tenor
  when approved and in date, the counter terms when counter-offered. The
  rate comes from the credit decision.
- **Schedule** comes from the core banking system through
  `SchedulePort::generateSchedule` (the CBA simulator in dev), so the
  booked schedule is the one the customer signs (FR-OFR-003).
- **Key facts** (FR-OFR-002, FR-CMP-025), method `ng_v0`, computed in exact
  decimals:
  - total interest
  - non-contingent fees (contingent fees are listed but not costed)
  - total cost of credit
  - total repayable
  - net disbursed
  - APR, from the monthly IRR by bisection; checked to equal the nominal
    rate when there are no fees
  - effective annual rate
  - cooling-off period

  This is not verified against the CBN rules yet (G-24, D-049).
- **PDF:** a new `PdfRenderPort` (I-34) with a `gotenberg` adapter for
  production and `pdf-render-simulator` for dev. The simulator writes a real
  text PDF; `RenderedPdf` keeps the bytes out of the integration call log.
- **Delivery** (FR-OFR-004 ◐): email is queued through the outbox and sent
  with the PDF attached through the installation's mailer. The recipient is
  masked in the API and audit. A printed branch copy is recorded. SMS and
  in-app delivery wait for P1-NTF-01 / P4.
- **Signing** (FR-OFR-005/009): signatories are expanded from the template
  order (primary, joint, guarantor, co_signer, bank), and out-of-order
  signatures are refused. Signing time must fall between issue and now
  (compared to the second).
- **Acceptance** (FR-OFR-010): needs every signature and an in-date offer.
  The executed upload is sniffed, malware-scanned and stored. An immutable
  `agreements` row records:
  - the executed document hash and the issued PDF hash
  - the template version
  - the signature log

  The case moves Accepted and `OfferAccepted` is raised for P1-CPR-01.
- **Re-issue** (FR-OFR-007): only an unsigned live offer, within
  `max_reissues`. The old offer is superseded, and the new one gets a fresh
  schedule, PDF and validity.
- **Decline:** reject or negotiate both send the case back to Assessment
  with a reason code (FR-OFR-008, G-14d).
- **Expiry:** hourly `offers:expire` (FR-OFR-007). Signing and acceptance
  are refused past `valid_until`. Reminders wait for P1-NTF-01.
- API: `GET/POST /applications/{id}/offers`, `GET /offers/{id}`,
  `POST /offers/{id}/actions/reissue|accept|decline`,
  `POST /offers/{id}/deliveries`,
  `POST /offers/{id}/signatories/{signatoryId}/actions/sign`
  (fragment `p6-offers.php`).

| ID | Status | Evidence |
|---|---|---|
| FR-OFR-001 / 003 / 005 / 009 / 010, LOS-FR-314 | Done | `OfferTest`, `OfferDomainTest` |
| FR-OFR-002, FR-CMP-025 | Done for method `ng_v0`; rule verification pending (G-24) | `OfferTest`, `OfferDomainTest` |
| FR-OFR-004 | Partial: email + print; SMS / in-app P4 | `OfferTest` |
| FR-OFR-006 | Partial: wet sign only; e-sign provider P4 | template validation in `OfferTest` |
| FR-OFR-007 | Partial: validity, expiry, re-issue limit; reminders with P1-NTF-01 | `OfferTest` |
| FR-OFR-008 | Done | `OfferTest` |

### Frontend (P1-FE-04 offer slice)
- **Offer tab** on the case:
  - an issue panel, with the reason when an offer can't be issued
  - the offer: terms, validity, PDF download, a kebab menu with re-issue
    and customer response
  - key facts and fees, with the schedule in a collapsible table
  - the ordered signing list: record the next signature, then accept with
    an executed-agreement upload; the agreement hash is shown
  - deliveries: email, or record a printed copy
  - earlier offers
- `features/offers/domain.ts` has 7 unit tests.

### Demo seed
- Adds femi (Legal Officer).
- Activates `offer.template/sme-offer`.
- Authors product v2 with the `offer_template` binding. In-flight cases stay
  pinned to v1, which has no template (FR-PRD-006).
- Takes a new case, Amaka Nwosu (NGN 3M), through KYC, documents, credit
  and approval to Approved.
- Fixes `seed_credit_demo` so it re-reads the status after the KYC gate.

### Gaps carried forward
- The Offer tab was not checked in the browser this milestone: the browser
  pane was in use. The flow was exercised against the live API instead.
- The bank signatory is recorded by the officer. There is no check yet that
  the bank signs within delegated authority.
- No pre-issue preview of key facts. The schedule start date is the issue
  date.

## Milestone 7 — Conditions precedent and the pre-disbursement check (2026-10-13)

**Gate results:** Pest **264 passed, 0 failed**; Larastan L8 0 errors; Pint
pass. Frontend `npm run verify`: 152 tests, contrast pass, build OK. Smoke
test on the seeded dev stack as femi, on DEMO-2026-000006: `conditions:open`
moved it from Accepted to Conditions precedent, then to Ready for
disbursement (it has no CPs).
- Check against 058/0123456790: failed on the account name only.
- Check against 058/0123456789: passed, with "NWOSU AMAKA" a 100% name match.
- Readiness then reported no blockers.

### Conditions (M13, P1-CPR-01)
- New `Conditions` module. It reaches other modules only through contracts:
  `ApprovalRegister`, `OfferRegister` and its `OfferAccepted` event,
  `ApplicationLifecycle`, `PartyDirectory`, `DocumentArchive`,
  `AuthorityDirectory`, `ChangeRequestGateway`, and a new Compliance
  contract `ComplianceGate` (KYC status, synchronous re-screen). It
  publishes `ConditionsGate` (`releaseBlockers`, `verifiedDestination`) for
  disbursement (M8).
- **Register** (FR-CPR-001): the offer's acceptance imports the approvers'
  conditions (each once) and moves Accepted → ConditionsPrecedent. Officers
  add further CPs or CSs with mandatory flag, owner and due date (overdue is
  flagged). Sign-off needs a note plus evidence: a malware-scanned upload
  filed under Documents and/or a reference. A database check also rejects a
  satisfied row without evidence.
- **Hard block and waivers** (FR-CPR-002): an outstanding mandatory CP means
  the case stays in ConditionsPrecedent, the gate blocks release, and the
  check fails. A waiver goes through maker-checker: maker
  `condition:manage`, checker `condition:waive_approve` with step-up. A
  rejected waiver leaves the item outstanding, and it can be re-requested.
  When the last mandatory CP is met or waived, the case moves to
  ReadyForDisbursement on its own.
- **Final pre-disbursement check** (FR-CPR-003/004/005): one command runs:
  - a fresh re-screen of every party (trigger `pre_disbursement`; any open,
    pending or confirmed alert fails it)
  - the CDD gate
  - executed agreement on file
  - mandatory CPs
  - approval validity
  - a destination name enquiry through the CBA port, with an LOS-side name
    match (order-, case- and suffix-insensitive; minimum 80%, configurable)

  Security and insurance report not-applicable until collateral
  (P1-COL-01). Pass or fail, the result is an immutable
  `pre_disbursement_checks` row, with the account number encrypted and
  masked. It is valid for 24 hours (configurable).
- **Gate:** release needs no outstanding mandatory CP and a latest check
  that passed and is still in date. `verifiedDestination()` gives
  disbursement the checked account.
- New permissions `condition:manage` (legal officer and disbursement maker
  templates) and `condition:waive_approve` (credit approver template).
- `conditions:open` backfills applications accepted before M13.
- API: `GET/POST /applications/{id}/conditions`,
  `POST /conditions/{id}/actions/satisfy|request-waiver`,
  `POST /applications/{id}/pre-disbursement-checks` (fragment
  `p7-conditions.php`).

| ID | Status | Evidence |
|---|---|---|
| FR-CPR-001 / 002 / 003 / 004 / 005 | Done (security and insurance items N/A until P1-COL-01) | `ConditionsTest`, `ConditionsDomainTest` |
| FR-CPR-006 | Not started: CS handover and escalation after booking (P3 / FR-HND-003) | — |

### Frontend (P1-FE-04 conditions slice)
- **Conditions tab:**
  - a readiness banner listing the blockers
  - the CP and CS lists, with mandatory, overdue and status pills, owner,
    due date and sign-off details
  - add-condition, sign-off (file and/or reference) and waiver-request
    dialogs
  - **Decide waiver** for holders of `condition:waive_approve`, which
    approves or rejects the change request (with step-up)
  - the pre-disbursement check panel (bank code, NUBAN, per-item results)
- The Approval tab's conditions no longer show a fulfilment status; they
  point to the Conditions tab.
- `features/conditions/domain.ts` has 4 unit tests.

### Demo seed
- femi gets `condition:manage`; segun gets `condition:waive_approve` and
  `change_request:read`.
- Simulator name enquiry is configured: 058/0123456789 → NWOSU AMAKA,
  058/0123456790 → a mismatch.
- `conditions:open` runs at the end.

### Gaps carried forward
- The Conditions tab was not checked in the browser (the pane was in use).
  The flow was exercised through the API and feature tests.
- Approval conditions keep their approval-time status column (`open`). The
  Conditions module is the system of record for fulfilment.
- No change-request inbox in the SPA yet, so document waivers still need
  the API.

## Milestone 8 — Disbursement, booking saga and reconciliation (2026-10-14)

**Gate results:** Pest **274 passed, 0 failed**; Larastan L8 0 errors; Pint
pass. Frontend `npm run verify`: 156 tests, contrast pass, build OK. Smoke
test on the seeded dev stack, DEMO-2026-000006 (Amaka Nwosu, NGN 3M):
- femi re-ran the check (passed)
- tolu made the instruction: net NGN 2,955,000.00 after MGMT and CRI fees,
  to 058/******6789 (NWOSU AMAKA)
- bayo released it with step-up
- the outbox worker ran ensure customer → loan account 9000000001 →
  disburse → fees → schedule check (variance 0) → book → advice
- the application is **Booked**
- reconciliation matched 3 of 3 postings, with 0 breaks

That completes demo steps 4–18 on one application, from capture to booked.

### Disbursement (M14, P1-DSB-01/02)
- New `Disbursement` module. It reaches other modules only through
  contracts: `ConditionsGate` (now with `revalidate()`), `OfferRegister`
  (now with the disclosed fees and net amount), `ApprovalRegister` (now with
  `voterIds`), `PartyDirectory`, `IdentityNumberResolver`,
  `DocumentArchive`, `ProductCatalogue`, `ChangeRequestGateway` and
  `ApplicationLifecycle`. It publishes `FacilityBooked` for handover
  (P1-HND-01).
- **Maker-checker release** (FR-DSB-009): the maker makes the instruction.
  Upfront fees are deducted, the destination is the verified account, and
  one live instruction is allowed per application. Release is a
  `disbursement.release` change request:
  - the checker holds `disbursement:check`
  - the checker is not the maker, and not anyone who voted on the approval
    (policy flag `fundly.disbursement.checker_not_approver`, default on)
  - the change-request route requires a step-up

  Release re-runs the pre-disbursement check, re-checks approval validity,
  and confirms the account did not change (D-053). The application moves
  ReadyForDisbursement → Disbursing.
- **Booking saga** (FR-DSB-001/004..007, FR-CBA-009, LOS-FR-313): an outbox
  job with these steps:
  1. ensure customer (find by LOS reference, else create, new-to-bank)
  2. create the loan account with the accepted terms; the instruction id is
     the LOS facility reference
  3. disburse the net amount
  4. post the upfront fees
  5. fetch the CBA schedule and compare it with the offer's
  6. Booked, then the advice

  How it stays safe:
  - **Stable keys:** every step uses one idempotency key and records its
    result, so re-runs skip completed steps.
  - **Unknown outcomes:** a timeout after sending is looked up by reference
    before anything is resent.
  - **Transient failures** park the instruction as `failed_retryable`, and
    the outbox retries it with backoff.
  - **Interventions** (variance, lapsed approval, anything unexpected) park
    it as `failed_intervention`.
  - **Rejections:** a definite rejection of the disbursement closes the
    account (compensation), marks the instruction `compensated`, and allows
    a fresh instruction (D-040b). Past the disbursement nothing is
    compensated automatically.
  - **Retry** (`disbursement:retry`) reuses the same instruction and keys.
  - **Schedule variance:** each instalment's principal, interest and total
    are compared within a tolerance (default 1.00). The CBA schedule is
    stored only when it passes.
- **Advice** (FR-DSB-011): a PDF with amounts, fees deducted, destination,
  and the schedule as booked, filed under Documents. Delivery comes with
  P1-NTF-01.
- **Exception queue** (PR-08): `GET /disbursement-exceptions`.
- **Reconciliation** (FR-DSB-008, FR-CBA-017): `reconciliation:run` runs
  daily at 02:00 and on demand. It matches LOS disbursement and fee postings
  against CBA postings by posting reference. Breaks are `missing_in_los`,
  `missing_in_cba` or `amount_mismatch`, and are resolved with a note.
- **CBA simulator:**
  - loan accounts keep their terms, so `getSchedule` is real
  - fee charges are recorded as postings
  - binding config `orphan_postings` injects CBA-only postings for the
    reconciliation demo
- Permissions `disbursement:retry` and `reconciliation:run`; the
  disbursement checker template also gets `change_request:read`. The
  change-request exclusion message is now neutral ("your own access or a
  decision you took part in").
- Tests share a new `tests/Support/Journey.php`, which builds the
  journey-to-ready fixture through the real API.

| ID | Status | Evidence |
|---|---|---|
| FR-DSB-001 / 004 / 005 / 006 / 007 / 009 / 011, FR-CBA-009, LOS-FR-313 | Done | `DisbursementTest`, `DisbursementDomainTest` |
| FR-DSB-002 | Partial: single full disbursement; tranches, revolving and third-party payout P3 | `DisbursementTest` |
| FR-DSB-008, FR-CBA-017 | Done | `DisbursementTest` (demo step 18) |
| FR-DSB-010 / 012 | Not started: mandates P4; reversal P3 | — |

### Frontend (P1-FE-04 disbursement slice)
- **Disbursement tab:**
  - a make-instruction panel listing the blockers
  - the instruction: amounts, destination, maker and checker
  - **Release / Reject** for the checker, with a client mirror of the
    four-eyes rule; the server decides
  - cancel, retry, and the error banner
  - booking progress, which polls every 5 seconds while the saga runs and
    notes "confirmed by reference lookup, not re-sent"
  - the booked schedule with its variance, and the advice download
- **Disbursement ops page** (`/operations/disbursements`): the exception
  queue, and reconciliation (run now, open breaks with resolve, recent
  runs).
- `features/disbursement/domain.ts` has 4 unit tests.

### Demo seed
tolu (Disbursement Maker) and bayo (Disbursement Checker, with retry,
reconciliation and change-request read).

### Gaps carried forward
- Not checked in the browser: the pane could not be displayed in this
  session. Verified through the API smoke test and feature tests.
- Handover (P1-HND-01: `facility.created` webhook, handover package) should
  listen to `FacilityBooked`.
- CBA product codes are derived from the product key until P4 adapter
  mappings exist.

## Milestone 9 — Handover and outbound webhooks (2026-10-14)

**Gate results:** Pest **278 passed, 0 failed**; Larastan L8 0 errors; Pint
pass. Frontend `npm run verify`: 158 tests, contrast pass, build OK. Dev
smoke test: `handover:create` packaged the booked DEMO-2026-000006. The
package holds 5 document references (statements, ID, offer letter, executed
agreement, advice) and the CRI insurance item, with retention until
2031-10-13. chidi acknowledged it.

### Outbound webhooks (Integration, TRD §10.1; FR-NTF-001 webhook channel)
- `webhook_endpoints`: the signing secret is generated server-side, shown
  once at registration, and stored encrypted. Endpoints must be https;
  plain http is allowed only to a local receiver outside production.
  Endpoints subscribe to event types or `*`.
- `WebhookPublisher::publish()` runs inside the caller's command, so the
  event, its deliveries and their outbox messages commit with the business
  change.
- Delivery uses outbox topic `webhook.deliver` and sends these headers:
  - `Fundly-Signature: t=<unix>,v1=<HMAC-SHA256(secret, "<t>.<body>")>`
  - `Fundly-Event-Id`, `Fundly-Event-Type`, `Fundly-Delivery-Id`

  A 2xx marks the delivery delivered. Anything else is logged (attempts,
  HTTP status, error) and retried with backoff. A disabled endpoint is
  skipped. Redelivery reuses the same event id.
- API: `GET/POST /webhook-endpoints`,
  `POST /webhook-endpoints/{id}/actions/disable`, `GET /webhook-deliveries`
  (filterable by subject), `POST /webhook-deliveries/{id}/actions/redeliver`.
  Permissions: `webhook:manage` (tenant administrator template) and
  `integration:read`.

### Handover (M15, P1-HND-01)
- New `Handover` module. On `FacilityBooked` it creates a package for
  servicing. The package is stored as the exact JSON that was hashed, with
  its SHA-256, and contains:
  - application and product
  - borrower and parties, with the CBA customer id
  - facility: loan account, principal, net disbursed, rate, tenor, value
    date, maturity, posting reference, fees deducted, booked schedule
  - agreement: hash and template version
  - **document references**: id, version, type, hash and API href
    (FR-HND-002 references; records/DMS transfer is P4-INT-09)
  - **open conditions subsequent** (FR-HND-003)
  - **insurance items**: fees whose code is in
    `fundly.handover.insurance_fee_codes`, with cover until maturity
  - collateral: empty until P1-COL-01
  - the servicing org unit and the retention summary
- It publishes `facility.created` with identifiers, terms and a link to the
  package only, and **no personal data**: receivers fetch the package
  through the API (FR-HND-001).
- Servicing acknowledges the package once (`handover:acknowledge`, on the
  branch manager template).
- **Retention** (FR-HND-004): a `retention_records` row is created on
  booking. Category `origination_record`, trigger `fixed_from_booking`,
  `fundly.retention.origination_years` (default 5) [verify, G-29], with a
  legal-hold flag. Origination evidence is append-only, so nothing is
  purged by handover.
- New contracts: `DocumentArchive::references()`,
  `ConditionsGate::openSubsequent()` and `Disbursement/DisbursementRegister`.
  `handover:create` backfills booked applications without a package.

| ID | Status | Evidence |
|---|---|---|
| FR-HND-001 / 003 / 004 | Done | `HandoverTest` (demo step 17) |
| FR-HND-002 | Partial: durable references; DMS transfer P4-INT-09 | `HandoverTest` |
| FR-NTF-001 | Partial: webhook channel; email / in-app with P1-NTF-01 | `HandoverTest` |

### Frontend
- **Handover tab:**
  - package hash and status, with acknowledge
  - conditions subsequent, insurance and collateral handed over
  - document references with download
  - facility.created delivery status per endpoint
  - retention
- **Webhooks page** under Administration: endpoints (add, with the secret
  shown once and a copy button; disable) and the delivery log (redeliver).
- `features/handover/domain.ts` has 2 unit tests.

### Gaps carried forward
- Not checked in the browser this session; verified through feature tests
  and the API smoke test.
- The dev tenant's administrator role predates `webhook:manage`. New
  tenants get it from the template. For the dev tenant, grant it through a
  role change request by a second administrator who does not hold the role.
- Facility closure from the LMS (to restart retention at
  relationship end) is P4.

## Milestone 10: Notifications (P1-NTF-01)

### Backend
- **Notification module** (`src/Modules/Notification`). It listens to domain
  events from other modules' Contracts and never calls those modules
  directly:
  - application submitted
  - awaiting approval
  - offer issued
  - ready for disbursement
  - disbursement awaiting release or failed
  - screening hits
  - facility booked
- **Channels:**
  - Customer **email** goes through the outbox (topic `notification.email`)
    with retry and backoff.
  - Staff get **in-app** messages (`GET /me/notifications` with an unread
    count, plus read and read-all).
  - The webhook channel shipped in M9.
- **Templates** (FR-NTF-002): config type `notification.template`, keyed by
  event and activated through maker-checker.
  - Content is per language (fallback `en`), per channel (email
    subject/body, in-app title/body), with placeholders from a fixed
    allow-list.
  - Validation rejects unknown events, channels and variables.
  - Built-in defaults apply until a tenant activates its own version.
- **Recipients:**
  - customers by email
  - the originator in-app
  - holders of a permission (approvers, disbursement checkers, compliance)
    through `AuthorityDirectory`, excluding the maker where it applies
- **Communication log** (FR-NTF-005): every message is a `communications`
  row, recording:
  - the event
  - the channel
  - the masked recipient
  - the rendered subject
  - the template source and version
  - the status (queued, sent, failed, delivered or read)
  - any attachments

  A missing email address is logged as `failed`. The log is available at
  `GET /applications/{id}/communications`.
- **Duplicate suppression** (FR-NTF-006, partial): a unique dedupe key per
  event, application, discriminator, channel and recipient.
- **Offer expiry reminders** (FR-OFR-007): `notifications:reminders` runs
  daily at 08:00 for offers expiring within
  `fundly.notifications.offer_reminder_days` (default 3), and sends each
  reminder only once.
- **Disbursement advice** emailed on booking, attached from the archive
  (FR-DSB-011).
- **New contracts:** `DisbursementAwaitingRelease` event and
  `OfferRegister::expiring()`.

| ID | Status | Evidence |
|---|---|---|
| FR-NTF-001 | Partial: email, in-app and webhook; SMS, WhatsApp and push are P4-NTF-01 | `NotificationTest` |
| FR-NTF-002 / 005 | Done | `NotificationTest`, `NotificationTemplateTest` |
| FR-NTF-006 | Partial: dedupe; preferences and quiet hours are P4 | `NotificationTest` |
| FR-OFR-007 | Done (reminders) | `NotificationTest` |

### Frontend
- **Inbox page** (`/inbox`): unread filter, mark read and read all, links to
  the case.
- The title-bar bell and nav badge show the real unread count.
- **Communications tab** on the case shows the per-application log with
  status, masked recipient and template source.
- `features/notifications/domain.ts` has unit tests.

### Verification
- Local Pest: 286 passed, with Pint and PHPStan clean.
- Frontend `npm run verify`: 160 tests.
- Dev smoke test: submitting a draft sent the `application.received` email
  (status `sent`, recipient masked).

### Gaps carried forward
- Not checked in the browser this session.
- Not built yet: SMS, WhatsApp, push, communication preferences and
  opt-out, quiet hours (P4-NTF-01).
- Templates are plain text (no HTML layout yet).

## Milestone 11: Collateral (P1-COL-01)

### Backend
- **Collateral module** (`src/Modules/Collateral`). Other modules see it only
  through the `CollateralRegister` contract:
  - `coverage()`
  - `security()`
  - `securedItems()`
  - `attentionDue()`
- **Types** (FR-COL-001): built-in defaults cover every BRD type:
  - real estate
  - vehicle
  - plant and equipment
  - inventory
  - receivables
  - cash / deposit lien
  - quoted securities
  - personal, corporate and institutional guarantees
  - assignment of insurance

  A tenant overrides or adds types with `collateral.type` configuration
  (maker-checker). Each type sets:
  - category
  - haircut
  - whether valuation is required, and how long it stays valid
  - whether insurance is required
  - the perfection checklist, which can differ by state (e.g. the FCT uses
    Minister's consent)

  The checklists and haircuts are indicative [verify, G-34].
- **Cover** (FR-COL-002):
  - Forced-sale value = market value less the haircut, computed in decimals.
  - Allocations are made in lending value.
  - Cover = lending value ÷ exposure. LTV = exposure ÷ allocated market
    value.
  - Both are compared with the product's new `collateral` section:
    `required`, `min_cover_percent`, `max_ltv_percent` and
    `perfection_timing`.
- **Valuations** (FR-COL-003) record:
  - valuer
  - date (never in the future)
  - method
  - market and forced-sale value
  - validity (defaults to the type's period)
  - the report, archived as a document

  Valuations are append-only. The latest becomes the value basis.
- **Re-valuation and insurance-expiry prompts:** the daily
  `notifications:reminders` job sends them in-app to collateral officers
  within `fundly.collateral.notice_days` (default 30), once each.
- **Perfection** (FR-COL-004):
  - Steps are created from the type for the asset's state.
  - The product can move a step between before disbursement (precedent) and
    after booking (subsequent), per G-34.
  - Each step carries a responsible user and a due date.
  - Completing a step needs evidence: a file and/or a reference.
- **Allocation** (FR-COL-005):
  - One item can secure several facilities.
  - Over-allocation is refused under a row lock on the item.
  - Setting an allocation to 0 releases it.
  - After a downward re-valuation, allocations are scaled pro rata and the
    item is flagged as over-allocated.
  - Cross-currency allocation is refused until FX haircuts exist.
- **Insurance** (FR-COL-006) records:
  - policy number
  - insurer
  - sum insured (with an under-insured flag)
  - start and expiry dates
  - whether the bank's interest is noted

  Policies are append-only. The policy that counts is one in force that
  notes the bank's interest.
- **Disbursement block** (FR-COL-008): the pre-disbursement check's
  `security_perfected` and `insurance_in_force` items now come from
  `CollateralRegister`. Release re-runs the check, so the block also applies
  at release. The exception route is a `collateral.waive` change request
  (maker `collateral:manage`, checker `condition:waive_approve`, step-up)
  for a perfection step or an item's insurance.
- **Approvals:** the matrix fact `collateral.coverage_percent` is now filled
  in, and the decision packet gains a collateral section (FR-APV-010).
- **Handover:** the package (version 2) now lists the collateral with its
  open perfection steps and current insurance (FR-HND-003).
- **Demo:** seed-demo adds **dapo@fundly.test** (Collateral Officer).

| ID | Status | Evidence |
|---|---|---|
| FR-COL-001 / 002 / 004 / 005 / 006 / 008 | Done | `CollateralTest`, `CoverTest` |
| FR-COL-003 | Done (prompts in-app) | `CollateralTest` |
| FR-COL-007 | P4-INT-08 (registry adapters) | — |

### Frontend
- **Collateral tab** (SCR-APP-07):
  - A "Security & cover" strip: cover, LTV, lending value, and perfection
    and insurance, each with pass/fail against the product.
  - A blockers banner.
  - A collateral table.
- **Item drawer:** valuation, perfection steps split into before
  disbursement and after booking, insurance, and allocation.
- **Dialogs:**
  - add collateral
  - record valuation
  - record insurance
  - change allocation
  - complete step
  - request waiver
  - decide waiver
- `features/collateral/domain.ts` has 5 unit tests.

### Verification
- Local Pest: 292 passed, with Pint and PHPStan clean.
- Frontend `npm run verify`: 165 tests.
- Dev smoke test as dapo: captured a Lagos property on DEMO-2026-000002
  (cover 157.50%, LTV 44.44%, 4 blockers).
- Checked in the browser:
  - the tab and the drawer render
  - completing a step with a reference cleared one blocker

### Gaps carried forward
- No collateral registry or valuer/insurer integrations (P4-INT-08,
  FR-COL-007).
- No FX for cross-currency security.
- Exposure is the requested amount, as for approval authority (G-15).
- Haircuts and perfection steps need confirming with Legal and Credit Risk
  (G-34).
- Collateral release on facility closure comes from the LMS (P4).
