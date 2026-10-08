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
