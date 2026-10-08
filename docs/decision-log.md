# Decision Log

This log records every decision that changes scope, architecture or the compliance position. Entries are append-only: a decision is superseded by a new entry, never edited. The Approved column records the PO's decision.

**Status values:** Proposed · Approved · Approved with change · Rejected · Superseded (by D-xxx).

## Round 1: raised 2026-10-07, decided by PO 2026-10-08

On 2026-10-08 the PO approved the decision log and changed the approach in five ways: API-first instead of Inertia; PostgreSQL; latest Laravel; commercial banks only, no mortgage; and buy-and-deploy on-prem managed through a licence server. Where a Round 1 recommendation conflicts with those changes, it is **Superseded** by the Round 2 entry named.

| ID | Gap | Decision | Status | Effective outcome |
|---|---|---|---|---|
| D-001 | G-01 | Scope baseline | **Approved** | BRD v0.1 + Step 1 outputs + this log = **baseline v1.0**, under change control |
| D-002 | G-02 | v1 lending segments | **Superseded by D-032** | Commercial bank segments; no mortgage/PMB depth |
| D-003 | G-03 | Jurisdictions | **Approved** | Nigeria only; FR-CMP-002 to v2 |
| D-004 | G-04 | First CBAs | **Approved** | Finacle first live adapter (sandbox to be confirmed), Mifos/Fineract-class second. Others "Contract defined / Stub ready". |
| D-005 | G-05 | Deployment sequence | **Superseded by D-030** | On-prem first |
| D-006 | G-06 | Document extraction | **Approved with change (D-030)** | Hybrid IDP port. Because deployment is on-prem, the **self-hosted pipeline is primary** and the vendor-cloud adapter is optional per client. Bank-statement analysis built in-house. |
| D-007 | G-07 | Schedule authority | **Approved** | CBA authoritative where supported. Booking-time variance check always. |
| D-008 | G-08 | Design partner | **Approved** | One commercial bank to be secured before MVP pilot |
| D-009 | G-09 | Self-service in v1 | **Approved** | Applicant PWA portal in v1, **not in MVP** (D-036) |
| D-010 | G-10 | Mobile approval | **Approved** | Responsive + step-up |
| D-011 | G-11 | Policy ownership | **Approved** | Bank risk team owns policy |
| D-012 | G-12 | Priority changes | **Approved** | As proposed. FR-CRD-012 and FR-APV-004 become M (corporate/SME at commercial banks). FR-DSB-010 M for salary-backed. |
| D-013 | G-14 | Lifecycle amendments | **Approved** | G-14 a–i adopted |
| D-014 | G-15 | Multi-facility | **Approved** | Per-facility decision; authority on total exposure |
| D-015 | G-16 | Inertia vs API-first | **Superseded by D-031** | Pure API-first; no Inertia |
| D-016 | G-17 | Laravel version | **Approved** | **Laravel 13** (13.35.0 current at 2026-10-08), PHP ≥ 8.3 |
| D-017 | G-18 | Database | **Approved** | **PostgreSQL 16+** |
| D-018 | G-19 | Hosting topology | **Superseded by D-030** | Client-hosted; vendor ships single-node and HA profiles |
| D-019 | G-20 | Adapter runtime | **Approved** | In-process + out-of-process adapters, one certification kit |
| D-020 | G-21 | Rules/workflow engines | **Approved** | Build, constrained; evaluator version per decision |
| D-021 | G-22 | FR-AUD-003 interpretation | **Approved** | Prevent for app/DB roles; detect and alert for superusers |
| D-022 | G-24 | Nigeria pack scope | **Approved with change (D-032)** | Commercial-bank pack. **FCCPC digital lending regulations removed** from scope (FCCPC-registered lenders are no longer a target segment) [verify any residual applicability to banks' digital channels]. |
| D-023 | G-25 | CRMS/bureau boundary | **Approved** | LOS owns origination-time obligations only |
| D-024 | G-27 | Payroll/GSI mandates | **Approved** | |
| D-025 | G-33 | WhatsApp + LDAP adapters | **Approved** | LDAP/AD is especially relevant on-prem |
| D-026 | G-42 | Reference load profile | **Approved with change (D-030)** | Applied **per installation**: 300 concurrent staff, 2,000 applications/day. The 20-tenants-per-cluster figure is dropped. |
| D-027 | G-45 | Design system | **Approved** | Fundly v3 visual language on AuditPro component architecture, contrast-corrected, responsive |
| D-028 | G-47 | Product name | **Approved** | "Fundly" working name |
| D-029 | 01 | INFERRED LOS-FR-301..315 | **Approved** | In scope. LOS-FR-310 (licence-category pack) is reduced to commercial-bank only by D-032. |

## Round 2: raised and decided 2026-10-08 (PO direction)

| ID | Date | Decision | Source | Status | Consequences |
|---|---|---|---|---|---|
| D-030 | 2026-10-08 | **Deployment model: buy-and-deploy, on-prem, one installation per bank, managed through a licence server.** | PO | **Approved** | <ul><li>The bank hosts the installation; NFR-005/007/008 become *jointly owned* (vendor supplies the HA reference architecture and certified profiles; the bank supplies infrastructure). See G-50.</li><li>No vendor cloud service on any core path.</li><li>Air-gapped update channel for releases, licences and watchlists (G-52).</li><li>The vendor operator console (LOS-FR-274..277) becomes a licence and support console, not a SaaS control plane.</li></ul> |
| D-031 | 2026-10-08 | **Pure API-first: React SPA (TypeScript, Vite) consuming only `/api/v1`. No Inertia.** | PO | **Approved** | <ul><li>PR-01 is satisfied literally.</li><li>Staff SPA uses Laravel Sanctum SPA (cookie session, CSRF-protected, same-origin).</li><li>Partners and service accounts use scoped tokens.</li><li>The OpenAPI 3.1 spec is the contract; the SPA's typed client is generated from it.</li><li>AuditPro React components are reused as SPA components.</li></ul> |
| D-032 | 2026-10-08 | **Target segment: Nigerian commercial banks only.** PMBs and fintech lenders out. | PO | **Approved** | <ul><li>Product categories (FR-PRD-002) stay configurable; mortgage remains a product a commercial bank *may* configure, but no PMB-specific depth.</li><li>G-34 (PMB mortgage perfection) is closed; perfection remains configurable per collateral type.</li><li>Jurisdiction pack = Nigeria × commercial bank (merchant bank is a minor variant if needed).</li><li>v1 covers retail, SME and corporate; corporate needs spreading and committee (already M via D-012).</li></ul> |
| D-033 | 2026-10-08 | **Tenancy model: installation = tenant.** The data model keeps `tenant_id`, legal entities and PostgreSQL RLS, so FR-TEN-001/002 hold by design and a future SaaS edition needs no re-architecture. Multi-tenant *operations* (tenant provisioning UI, cross-tenant observability) are deferred. | Recommendation (follows D-030) | **Adopted. Object if you disagree.** | <ul><li>Cheap now (one column + RLS policy per table), very expensive to add later.</li><li>A bank group with subsidiaries runs as one installation with several legal entities (FR-TEN-003).</li></ul> |
| D-034 | 2026-10-08 | **Licensing: `LicensingPort` with an Atheris LicensingServer adapter, plus an offline signed licence file.** | Follows D-030 | **Adopted. Needs input (G-48).** | <ul><li>The licence is an Ed25519-signed document: client, installation fingerprint, modules, named-user and branch caps, adapter entitlements, validity, grace period.</li><li>It is verified locally on every boot and cached for every request; online check-in to the LicensingServer is optional, with offline activation for air-gapped sites.</li><li>**Fail-safe rules:**<ul><li>Licence expiry blocks *new* applications and logins beyond grace.</li><li>It **never** interrupts an in-flight disbursement saga or reconciliation.</li><li>It never blocks auditor read-only access or evidence-pack export, since a regulator must always be able to reconstruct a decision.</li></ul></li><li>**Needed from you:** the LicensingServer API contract or repo (the one ThirdLine uses).</li></ul> |
| D-035 | 2026-10-08 | **One mainline codebase; no per-client branches.** | Recommendation | **Adopted. Please confirm.** | <ul><li>BRD §17 and PR-03 forbid code forks. The per-client branch model used on ThirdLine would breach that and would make every regulatory pack update an N-way merge.</li><li>Client differences live in configuration, adapters and versioned extension modules (§17 layers 1–6), shipped as signed packages.</li><li>Releases are tagged semver; each installation pins a version.</li></ul> |
| D-036 | 2026-10-08 | **MVP scope** (see `06-development-plan.md` §2): staff-assisted retail and SME term-loan origination for one commercial bank, end to end from capture to booked in the CBA simulator, with production-grade controls (RBAC + scope, maker-checker, SoD, hash-chained audit, idempotent CBA saga, licence enforcement). | Recommendation (PO asked for MVP ASAP) | **Adopted. Please confirm.** | <ul><li>The full v1 continues after the MVP in the planned phases.</li><li>MVP excludes the applicant portal, partner API, real IDP extraction (manual verification behind the IDP port), committee e-voting, spreading, SSO/SCIM (local auth + MFA + LDAP in), and advanced reporting.</li></ul> |

## Round 3: raised 2026-10-08 during P0 build and planning (Tech Lead decisions, PO may overturn)

| ID | Date | Decision | Source | Status | Consequences |
|---|---|---|---|---|---|
| D-037 | 2026-10-08 | **The MVP (P0 + P1) is a UAT and demonstration release, not a production pilot.** Bureau, BVN/NIN, screening and the CBA run on stubs and the simulator until P4. A production pilot needs the P4 live adapters (Finacle, one bureau, NIBSS BVN, a screening provider) plus D-038 resolved. | Plan issue 8 | **Needs PO confirmation** | Sets bank expectations for the MVP demo. The fastest route to a pilot is to pull the four adapters P4-INT forward as soon as sandboxes exist. |
| D-038 | 2026-10-08 | **MVP control posture.** <ul><li>(a) FR-CMP-015 (source of funds/wealth) moves P5 → **P1**, closing the AML gap in the CDD gate.</li><li>(b) Automated outcomes in P1 may approve or refer but **never auto-decline**, until human review (FR-CMP-044) and adverse-action notices (FR-CMP-026) land in P5. Enforced by the configuration validator.</li><li>(c) Salary-backed products **cannot be activated** until mandates (FR-DSB-010, P4).</li><li>(d) MVP approval matrices **may not route to a committee** (committee voting is P3).</li><li>(e) The MVP single-obligor check is per customer; connected-group aggregation arrives in P3 [verify CBN definition of "single obligor"].</li></ul> | Plan issues 2, 4, 5, 6; design issue 1 | **Adopted** | Every gap that would let the MVP make a decision without its BRD safeguard is closed by a configuration rule, not by trust. |
| D-039 | 2026-10-08 | **Non-production data is synthetic only** until the deterministic anonymiser (FR-SEC-018) ships in P5. Restoring production data into UAT is blocked by procedure and by the restore script. | Plan issue 3 | **Adopted** | NFR-018 is partially met until P5 |
| D-040 | 2026-10-08 | **UI and control details:** <ul><li>(a) Product activation is **one** checker step approving both version and effective date.</li><li>(b) Retrying a failed posting with the *same* instruction and key needs `disbursement:retry`, with no new checker. A *definite* business rejection (e.g. GL period closed) cannot be blind-retried: it needs a corrected instruction and a fresh maker-checker release.</li><li>(c) Auditors may unmask PII **with a purpose code + step-up**, fully logged. This is read access, not an operational action. **PO to confirm** against internal audit charter norms.</li><li>(d) Licence enforcement exempts auditor, examiner and in-flight-disbursement users (D-034).</li><li>(e) The operator console inside an installation is limited to licence and support functions; the Atheris-side console is part of the LicensingServer (G-48).</li><li>(f) Archivo is **self-hosted** (no Google Fonts on-prem).</li><li>(g) Non-NGN currencies display as ISO codes.</li></ul> | Design brief §12 | **Adopted; (c) needs PO confirmation** | |
| D-041 | 2026-10-08 | **Licence recovery path.** While unlicensed or past grace, holders of `licence:import_approve` may sign in, restricted to the licence-recovery routes. Without this, an expired installation could never approve its own renewal. | P0 build defect fix | **Adopted** | Covered by tests in the licensing suite |

## Round 4: raised 2026-10-08 at the start of P1

| ID | Date | Decision | Source | Status | Consequences |
|---|---|---|---|---|---|
| D-042 | 2026-10-08 | **Visual language: adopt the `loan-ui.jpg` dashboard look** (light sage canvas, white large-radius cards, deep forest-green primary, lime accent fills, pill navigation, stat cards with trend chips, chart and activity-timeline widgets) for the LOS staff SPA. Supersedes the Fundly v3 grey/blue *visual values* of D-027. | PO instruction | **Approved (PO)** | <ul><li>Kept from D-027 / the 07 brief: the semantic token architecture (`design-tokens.css` + Tailwind preset, no literals in components), AuditPro component names, information architecture, state contracts, self-hosted fonts and every WCAG 2.2 AA rule.</li><li>Lime is a fill/graphic colour behind dark-green text only; it is never text on white.</li><li>Tokens live in `frontend/src/design-tokens/`; `docs/design-tokens.css` remains the v3 reference until the brief is revised.</li></ul> |
| D-043 | 2026-10-08 | **Domain events are dispatched synchronously after commit** through Laravel's event dispatcher (`CommandBus` already does this). A listener that needs a durable or external effect writes an outbox message instead of doing the work inline. | TRD gap ("domain-event transport not specified") | **Adopted (Tech Lead)** | Keeps modules decoupled without a broker in the single-node MVP; the outbox gives at-least-once delivery where it matters. |
