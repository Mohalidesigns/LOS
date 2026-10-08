# 01 — Requirements Inventory

| | |
|---|---|
| **Source** | `loan-origination-system-brd-v0.1.docx` (BRD v0.1, 27 Aug 2026), read in full |
| **Step** | 1 of 5: BRD study |
| **Status** | Awaiting Product Owner sign-off |
| **Companion** | `02-gap-register.md` (G-xx gaps, A-xx assumptions), `decision-log.md` (D-xxx) |
| **Machine-readable copy** | `01-requirements-inventory.csv` (same content; feeds the traceability matrix) |

## ID scheme

- **LOS-FR-001 to LOS-FR-273**: every numbered functional requirement in the BRD, in document order. The BRD ID is carried unchanged in the *BRD ID* column. LOS IDs are the traceability keys (A-16).
- **LOS-FR-274 to LOS-FR-287**: binding requirements the BRD states in prose or tables without an FR ID (§6, §12.3, §16, §17, §18). Type **Stated (unnumbered)**.
- **LOS-FR-301 onward**: **INFERRED**. These are not in the BRD; I propose them because a stated Must cannot be built or tested without them. They were **approved into scope by the PO on 2026-10-08 (D-029)**.
- **LOS-NFR-001 to LOS-NFR-020** = BRD NFR-001 to NFR-020. The BRD gives NFRs no priority, so I've assumed Must (shown as M*, A-03).
- **LOS-CON-001 to LOS-CON-013**: binding architectural principles (PR-01 to PR-10) and constraints (§5, §21).
- **Priority** is the BRD's own: **M** must (v1), **S** should (v1 if capacity), **C** could (v2). The *Proposed* column holds my recommended changes, which are not effective until you approve them (D-012).
- **Scope sensitivity** `[Qn]`: the requirement's scope depends on the answer to BRD §20 open question *n*.
- Requirement text is **verbatim from the BRD**. My commentary is confined to the *Notes* column.

## Summary

| Category | Count | M | S | C |
|---|---:|---:|---:|---:|
| Functional (numbered, BRD) | 273 | 232 | 38 | 3 |
| Functional (stated, unnumbered) | 14 | 14 | — | — |
| Non-functional | 20 | 20* | — | — |
| Principles and constraints | 13 | 13 | — | — |
| **Total stated** | **320** | | | |
| INFERRED (pending approval) | 15 | — | — | — |

\* Assumed Must (A-03).

### Functional requirements by module

| Module | BRD section | Total | M | S | C |
|---|---|---:|---:|---:|---:|
| TEN | §8.1 Tenant, Organization and Reference Data | 11 | 8 | 3 | 0 |
| PRD | §8.2 Product Factory | 10 | 8 | 1 | 1 |
| CHN | §8.3 Origination Channels and Lead Management | 8 | 5 | 3 | 0 |
| CUS | §8.4 Customer, Identity and KYC | 11 | 9 | 2 | 0 |
| APP | §8.5 Application Capture and Data Management | 9 | 8 | 1 | 0 |
| DOC | §8.6 Document Management and Intelligent Document Processing | 33 | 28 | 4 | 1 |
| CRD | §8.7 Credit Assessment and Decisioning | 17 | 12 | 4 | 1 |
| COL | §8.8 Collateral and Security | 8 | 7 | 1 | 0 |
| WFL | §8.9 Workflow, Queues and Case Management | 12 | 12 | 0 | 0 |
| APV | §8.10 Approval and Delegated Lending Authority | 12 | 10 | 2 | 0 |
| OFR | §8.11 Offer, Acceptance and Execution | 10 | 10 | 0 | 0 |
| CPR | §8.12 Conditions Precedent and Pre-Disbursement Control | 6 | 5 | 1 | 0 |
| DSB | §8.13 Disbursement and Booking | 12 | 11 | 1 | 0 |
| HND | §8.14 Handover to Servicing | 5 | 5 | 0 | 0 |
| NTF | §8.15 Notifications and Communications | 6 | 5 | 1 | 0 |
| CBA | §9 Core Banking Abstraction Layer | 20 | 18 | 2 | 0 |
| CMP | §10.1 Framework | 3 | 2 | 1 | 0 |
| CMP | §10.2 AML / CFT and Financial Crime | 8 | 8 | 0 | 0 |
| CMP | §10.3 Credit Regulation and Reporting | 8 | 8 | 0 | 0 |
| CMP | §10.4 Data Protection and Privacy | 8 | 7 | 1 | 0 |
| CMP | §10.5 Model and Decision Governance | 6 | 4 | 2 | 0 |
| AUD | §11 Audit and Traceability | 16 | 14 | 2 | 0 |
| SEC | §12 Security and Access Control | 20 | 18 | 2 | 0 |
| RPT | §13 Reporting and Analytics | 10 | 7 | 3 | 0 |
| CFG | §17 Configuration and Extensibility Model | 4 | 3 | 1 | 0 |

**Proposed priority changes:** 9 (see G-12, G-02, G-03, G-27, G-30). **Requirements that depend on an open BRD question:** 19.

## Business drivers and objectives (traced, not built directly)

These are **outcomes**, not buildable requirements. Each requirement will be traced to at least one driver in the traceability matrix (deliverable 08).

| ID | Driver / objective | Target / measure | Note |
|---|---|---|---|
| BD-01 | Reduce turnaround time (TAT) | Measurable reduction against each tenant's baseline; per-stage SLA visibility | |
| BD-02 | Reduce cost-to-originate | Straight-through processing for qualifying applications | |
| BD-03 | Consistent credit decisioning | Policy applied uniformly; exceptions explicit and approved, never silent | |
| BD-04 | Examination readiness | Complete evidence pack retrievable per application on demand | |
| BD-05 | Product agility | New product variant live via configuration, without a release | |
| BD-06 | Market reach for the platform | Deployable against any CBA via adapter; no vendor lock-in for buyers | |
| BD-07 | Risk reduction | Fraud, duplicate, and document-tampering detection at intake | |
| OB-01 | Digitize intake | ≥90% of application data captured without manual keying (API, document extraction, or self-service) | Measurable only with per-field provenance (LOS-FR-301, G-43) |
| OB-02 | Straight-through processing | Configurable STP rate target per product; baseline reported from month one |  |
| OB-04 | Decision explainability | 100% of decisions reproducible from stored inputs, policy version, and model version |  |
| OB-05 | Audit completeness | Zero state transitions without an attributable actor and timestamp |  |
| OB-06 | Tenant onboarding | New tenant configured and CBA-certified within a defined window (target to be set after first two implementations) | Target TBD in BRD |
| OB-07 | Availability | See NFR §14 | = NFR-005 |
| OB-03 | *(missing in BRD)* | — | Numbering gap (G-44) |

## BRD assumptions (§5), for reference

| ID | Assumption | Impact if false (BRD) |
|---|---|---|
| AS-01 | Each tenant's CBA exposes, or can be fronted by, an API for customer, account, and posting operations | Manual/batch fallback adapter required — increases TAT and reduces STP |
| AS-02 | Tenants hold valid customer consent for bureau enquiry and data processing | Consent capture must be built into intake (it is — see §8.4) |
| AS-03 | Electronic signatures are legally enforceable in target jurisdictions | Wet-signature fallback workflow required per tenant |
| AS-04 | Tenants will accept a canonical product model with configurable extensions | Higher per-tenant customization cost |
| AS-05 | Nigeria is the reference jurisdiction for v1 regulatory packs; other jurisdictions added as packs | Regulatory pack framework must be genuinely pluggable from day one |

## Principles and constraints (binding)

| LOS ID | Source | Principle / constraint | Notes |
|---|---|---|---|
| LOS-CON-001 | PR-01 | API-first: Every capability available in the UI is available via API. The UI is a client of the same contracts. | Interpretation with Inertia (G-16). |
| LOS-CON-002 | PR-02 | CBA-agnostic core: No CBA-specific field, code, or behaviour appears outside an adapter. Enforced by architectural test. |  |
| LOS-CON-003 | PR-03 | Configuration over code: Tenant differences are data. Tenant-specific code is a last resort and must be isolated in extension points. |  |
| LOS-CON-004 | PR-04 | Event-sourced state transitions: Application state changes are recorded as immutable events; current state is a projection. Underpins audit and replay. | Application aggregate event-sourced (G-21, FR-AUD-011). |
| LOS-CON-005 | PR-05 | Idempotency everywhere: Every external-effect operation carries an idempotency key. Retries are safe by design. |  |
| LOS-CON-006 | PR-06 | Explainable decisioning: Any automated decision stores its inputs, policy version, model version, and reason codes. |  |
| LOS-CON-007 | PR-07 | Least privilege by default: New roles start with zero permissions. Access is granted, never assumed. |  |
| LOS-CON-008 | PR-08 | Fail visible, not silent: Integration failures create actionable exceptions in a queue; they never silently degrade a decision. |  |
| LOS-CON-009 | PR-09 | Data minimization: Collect what policy requires. PII access is logged and purpose-bound. |  |
| LOS-CON-010 | PR-10 | Deployment neutrality: One artefact runs SaaS, private cloud, and on-premise. No hosting-model-specific code paths. | (G-05, G-19). |
| LOS-CON-011 | (unnumbered) | Deployable on-premise, in private cloud and as multi-tenant SaaS from one codebase (data residency driver). |  |
| LOS-CON-012 | (unnumbered) | Some tenants require air-gapped or restricted-egress deployment; core paths must not depend on external SaaS. |  |
| LOS-CON-013 | (unnumbered) | Maintain a traceability matrix: driver → epic/story → acceptance criteria → test → regulatory citation; no requirement reaches done without a mapped test. |  |

## Functional requirements (numbered in BRD)


### §8.1 Tenant, Organization and Reference Data

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-001 | FR-TEN-001 | Support multiple tenants (banks) on one deployment with complete logical data isolation; no query path may return cross-tenant data | M |  |  | Defence in depth: app tenant scoping + PostgreSQL RLS (G-18). Test: architectural + cross-tenant pen suite (G-42). |
| LOS-FR-002 | FR-TEN-002 | Support per-tenant deployment isolation (dedicated database/schema) as a configuration option for tenants with regulatory or contractual requirements | M |  |  | Schema- or DB-per-tenant option (G-18). |
| LOS-FR-003 | FR-TEN-003 | Model the tenant's organizational hierarchy: legal entity → region/zone → area → branch → team → user, with configurable depth and labels | M |  |  | Legal entity also resolves jurisdiction × licence pack (G-23). |
| LOS-FR-004 | FR-TEN-004 | Allow tenant-specific branding: logo, colours, document letterhead, email/SMS templates, and outbound domain | M |  |  | Outbound domain implies per-tenant SPF/DKIM setup. |
| LOS-FR-005 | FR-TEN-005 | Allow tenant-specific terminology overrides (e.g. "Loan Officer" vs "Relationship Officer") applied across UI and generated documents | S |  |  |  |
| LOS-FR-006 | FR-TEN-006 | Maintain reference data per tenant: currencies, FX rates, calendars/holidays, industry/sector codes, occupation codes, geography, document types, reason codes | M |  |  | Prudential parameters (limit base) not listed (G-35). FX source (G-41). |
| LOS-FR-007 | FR-TEN-007 | Support multiple currencies per tenant, with the application currency independent of the base reporting currency | M |  |  |  |
| LOS-FR-008 | FR-TEN-008 | Support multi-language UI and customer-facing document generation, with per-tenant enabled language set | S |  |  | English at launch; framework built in (A-09). |
| LOS-FR-009 | FR-TEN-009 | Version all configuration; support draft → review → approved → active lifecycle with maker-checker before activation | M |  |  |  |
| LOS-FR-010 | FR-TEN-010 | Support configuration export/import between environments (dev → UAT → prod) as a promotable artefact | M |  |  |  |
| LOS-FR-011 | FR-TEN-011 | Provide configuration diff and rollback to any prior active version | S |  |  |  |

### §8.2 Product Factory

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-012 | FR-PRD-001 | Define loan products through configuration without code deployment | M |  |  |  |
| LOS-FR-013 | FR-PRD-002 | Support product categories: personal/consumer, salary-backed/payroll, asset finance, mortgage, overdraft, credit line/revolving, SME term loan, corporate term loan, agricultural, device/BNPL | M |  |  | Mortgage perfection specifics (G-34). Salary-backed depends on mandates (G-27). |
| LOS-FR-014 | FR-PRD-003 | Configure per product: eligibility criteria, amount range, tenor range, interest basis (flat, reducing balance, floating with index and spread), repayment frequency, moratorium/grace, fees and charges (upfront, amortized, contingent), penalty structure, prepayment rules | M |  |  | Day-count, rounding, holiday roll and taxes unspecified (G-36, G-07). |
| LOS-FR-015 | FR-PRD-004 | Configure required document checklists per product, further conditional on applicant segment, amount band, and channel | M |  |  |  |
| LOS-FR-016 | FR-PRD-005 | Configure the workflow variant, credit policy set, and approval matrix bound to each product | M |  |  |  |
| LOS-FR-017 | FR-PRD-006 | Support product versioning with effective-dated activation; in-flight applications continue on the product version under which they were submitted | M |  |  |  |
| LOS-FR-018 | FR-PRD-007 | Support product availability rules by channel, branch/region, customer segment, and date window (campaign products) | M |  |  |  |
| LOS-FR-019 | FR-PRD-008 | Provide a simulation/sandbox mode allowing a product manager to run test applications against a draft product version before activation | S |  |  |  |
| LOS-FR-020 | FR-PRD-009 | Map each LOS product to the tenant's CBA product code(s) via the adapter configuration, including GL/branch mapping where required | M |  | Q3 | [Q3] Depends on CBA selection (G-04). |
| LOS-FR-021 | FR-PRD-010 | Support product bundles and cross-sell attachment (e.g. credit life insurance, device insurance) with their own eligibility and pricing | C |  |  |  |

### §8.3 Origination Channels and Lead Management

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-022 | FR-CHN-001 | Capture applications from: staff-assisted UI, customer self-service web, mobile, agent/DSA app, partner/embedded API, bulk file upload, and inbound email/document drop | M |  | Q8 | [Q8] Self-service (G-09); mobile/agent as PWA (A-08); partner API priority conflict (G-12); bulk/scanner/email formats (G-38). |
| LOS-FR-023 | FR-CHN-002 | Record channel and originating user/partner on every application for attribution, commission, and analytics | M |  |  | Commission calculation out of scope v1 (G-40). |
| LOS-FR-024 | FR-CHN-003 | Support save-and-resume for partially completed applications, with configurable expiry and reminder nudges | M |  |  |  |
| LOS-FR-025 | FR-CHN-004 | Support offline capture on the agent app with conflict-safe synchronization when connectivity returns | S |  |  | Device security controls (G-39). |
| LOS-FR-026 | FR-CHN-005 | Manage leads: capture, assign, track status, convert to application, and record loss reason | S | M/S split |  | Proposed split: capture/convert = M, rest = S (G-12). |
| LOS-FR-027 | FR-CHN-006 | Provide a pre-qualification/eligibility check that returns an indicative offer without a full application and without a hard bureau enquiry where policy allows | M |  |  | Position in lifecycle ambiguous (G-14a). |
| LOS-FR-028 | FR-CHN-007 | Deduplicate: detect existing applications and existing customers at intake using identity, phone, email, and fuzzy name matching; block or flag per policy | M |  |  |  |
| LOS-FR-029 | FR-CHN-008 | Provide partner API for embedded origination, including partner-scoped credentials, rate limits, and per-partner product catalogue | S | M/S split |  | Proposed split: core partner API = M (G-12). |

### §8.4 Customer, Identity and KYC

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-030 | FR-CUS-001 | Support individual, sole proprietor, partnership, limited company, and group/cooperative applicant types, each with a distinct data schema | M |  |  |  |
| LOS-FR-031 | FR-CUS-002 | Retrieve existing customer data from the CBA via the abstraction layer and pre-populate the application; never re-key what the bank already holds | M |  |  |  |
| LOS-FR-032 | FR-CUS-003 | Verify identity against national identity providers via pluggable adapters (e.g. BVN/NIN in Nigeria, or the tenant's configured equivalent) and record the verification result, provider, and timestamp | M |  |  | Nigeria: NIBSS BVN, NIMC NIN; access/consent model [verify] (G-24). |
| LOS-FR-033 | FR-CUS-004 | Perform liveness/selfie match against the identity photo where the provider supports it, with configurable match threshold | S |  |  |  |
| LOS-FR-034 | FR-CUS-005 | Screen every applicant, guarantor, director, and beneficial owner against sanctions, PEP, and adverse media lists at intake and re-screen at defined checkpoints and on list updates | M |  |  | Watchlist sourcing for air-gapped tenants (G-05, G-30). |
| LOS-FR-035 | FR-CUS-006 | Capture and store beneficial ownership for non-individual applicants to the configured threshold, including layered ownership | M |  |  |  |
| LOS-FR-036 | FR-CUS-007 | Apply risk-based CDD: standard, simplified, or enhanced due diligence driven by configurable rules over customer type, geography, product, and screening outcome | M |  |  |  |
| LOS-FR-037 | FR-CUS-008 | Capture explicit, granular, timestamped consent for: bureau enquiry, data processing, marketing, and third-party data sharing — each independently recorded and withdrawable | M |  |  | Add GSI consent (G-27); FCCPC data-use limits (G-24). |
| LOS-FR-038 | FR-CUS-009 | Present a consolidated customer 360 view: existing facilities, exposure, repayment history, deposits, and prior applications, sourced live from the CBA where available | M |  | Q3 | [Q3] Capability-dependent per adapter manifest (FR-CBA-003/004). |
| LOS-FR-039 | FR-CUS-010 | Support related-party and connected-exposure linkage (group companies, directors, spouses) for aggregated exposure limits | S | M |  | Proposed M: required by FR-CRD-007, FR-CMP-023/024 (G-12). |
| LOS-FR-040 | FR-CUS-011 | Push new-to-bank customer creation to the CBA at the appropriate stage and store the returned customer identifier | M |  |  |  |

### §8.5 Application Capture and Data Management

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-041 | FR-APP-001 | Assign a unique, human-readable application reference on creation, with configurable format per tenant | M |  |  |  |
| LOS-FR-042 | FR-APP-002 | Render dynamic forms driven by product and applicant-type schema, with conditional visibility, cross-field validation, and inline error messaging | M |  |  |  |
| LOS-FR-043 | FR-APP-003 | Support tenant-defined custom fields (extension attributes) on core entities without schema migration, including their inclusion in rules, documents, and reports | M |  |  | JSONB extension attributes (G-18). |
| LOS-FR-044 | FR-APP-004 | Support multiple applicants per application: primary, joint, guarantors, and co-signers, each with independent KYC and credit assessment | M |  |  |  |
| LOS-FR-045 | FR-APP-005 | Support application amendment before approval, with full field-level change history and re-triggering of affected checks | M |  |  |  |
| LOS-FR-046 | FR-APP-006 | Support withdrawal, cancellation, decline, and expiry as terminal states with mandatory reason codes | M |  |  | Declined terminal vs human review (G-14e). |
| LOS-FR-047 | FR-APP-007 | Support cloning an application (re-application, or new facility for the same customer) with configurable field carry-over | S |  |  |  |
| LOS-FR-048 | FR-APP-008 | Compute and display a completeness indicator showing outstanding data and documents against the product checklist | M |  |  |  |
| LOS-FR-049 | FR-APP-009 | Maintain application-level and stage-level SLA clocks with configurable business calendars, pause conditions (e.g. awaiting customer), and breach escalation | M |  |  |  |

### §8.6 Document Management and Intelligent Document Processing

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-050 | FR-DOC-001 | Accept documents via web upload, mobile camera capture, email ingestion to a monitored address, scanner integration, API, and partner submission | M |  |  | Scanner / email ingestion method (G-38). |
| LOS-FR-051 | FR-DOC-002 | Support PDF (native and scanned), JPEG, PNG, TIFF, HEIC, and common office formats, with configurable size limits per tenant | M |  |  |  |
| LOS-FR-052 | FR-DOC-003 | Apply image pre-processing: de-skew, de-noise, auto-crop, rotation correction, contrast normalization, and multi-page assembly | M |  | Q5 | [Q5] On-prem pipeline (G-06). |
| LOS-FR-053 | FR-DOC-004 | Scan every upload for malware before it becomes accessible to any user | M |  |  |  |
| LOS-FR-054 | FR-DOC-005 | Store documents in a content store with encryption at rest, immutable versioning, and a cryptographic hash recorded per version | M |  |  |  |
| LOS-FR-055 | FR-DOC-006 | Detect duplicate documents by hash and by content similarity, including the same document submitted across different applications | M |  |  |  |
| LOS-FR-056 | FR-DOC-007 | Maintain a per-application document checklist derived from product configuration, with statuses: not received, received, extracted, under review, verified, rejected, waived, expired | M |  |  |  |
| LOS-FR-057 | FR-DOC-008 | Support document waivers with mandatory justification and approval at the authority level configured for that document type | M |  |  |  |
| LOS-FR-058 | FR-DOC-009 | Track document expiry (e.g. ID validity, valuation age, financial statement recency) and flag stale documents against configurable thresholds | M |  |  |  |
| LOS-FR-059 | FR-DOC-010 | Provide a document viewer supporting annotation, redaction, side-by-side comparison, and page-level accept/reject | S |  |  |  |
| LOS-FR-060 | FR-DOC-020 | Automatically classify uploaded documents by type without requiring the user to declare what they uploaded; route unclassifiable items to a review queue | M |  | Q5 | [Q5] Build/buy/hybrid (G-06). |
| LOS-FR-061 | FR-DOC-021 | Support a bundled upload (single PDF containing multiple document types) with automatic page-range splitting into separate classified documents | M |  | Q5 | [Q5] (G-06). |
| LOS-FR-062 | FR-DOC-022 | Extract structured data from at minimum: government ID, passport, driver's licence, utility bill, payslip, employment letter, bank statement, audited/management financial statements, tax certificate, company registration documents, board resolution, title deed, valuation report, invoice/proforma, insurance certificate | M |  | Q5 | [Q5] 15 document types; Nigerian formats (G-06). |
| LOS-FR-063 | FR-DOC-023 | Extract tabular data (statement lines, financial statement line items) preserving row/column structure and page provenance | M |  | Q5 | [Q5] (G-06). |
| LOS-FR-064 | FR-DOC-024 | Return a confidence score per extracted field, with per-field, per-document-type, per-tenant thresholds governing auto-accept versus human review | M |  | Q5 | [Q5] (G-06). |
| LOS-FR-065 | FR-DOC-025 | Route any field below threshold to a human-in-the-loop verification queue showing the extracted value alongside the source document region that produced it | M |  |  |  |
| LOS-FR-066 | FR-DOC-026 | Record every human correction as training feedback, retaining the original machine value, the corrected value, the corrector, and the timestamp | M |  |  |  |
| LOS-FR-067 | FR-DOC-027 | Support handwritten field extraction where present on forms, at reduced confidence with mandatory review | S |  | Q5 | [Q5] Handwriting on-prem is weakest option (G-06). |
| LOS-FR-068 | FR-DOC-028 | Support tenant-specific document templates (e.g. a particular employer's payslip format) as additional extraction configurations without model retraining | S |  | Q5 | [Q5] (G-06). |
| LOS-FR-069 | FR-DOC-029 | Extract and normalize to a canonical schema per document type, so that downstream rules are written against canonical fields regardless of source layout | M |  |  | Canonical schema per doc type is LOS-owned regardless of engine. |
| LOS-FR-070 | FR-DOC-030 | Support multi-language document extraction for the tenant's enabled languages | C |  |  |  |
| LOS-FR-071 | FR-DOC-040 | Parse bank statements from any format (PDF, CSV, scanned, or API-sourced from the CBA/open banking) into a normalized transaction ledger | M |  |  | Built in-house in all options (G-06). Open-banking source is an extra integration. |
| LOS-FR-072 | FR-DOC-041 | Categorize transactions (salary, other income, loan repayments, utilities, transfers, cash, charges) using configurable rules plus classification models | M |  |  | Classification models fall under model inventory (FR-CMP-040). |
| LOS-FR-073 | FR-DOC-042 | Identify recurring salary credits, compute average and median net monthly income, and assess income regularity and volatility | M |  |  |  |
| LOS-FR-074 | FR-DOC-043 | Detect existing debt service obligations, returned/bounced items, unpaid cheques, overdraft excesses, and days-in-debit | M |  |  |  |
| LOS-FR-075 | FR-DOC-044 | Compute affordability metrics — disposable income, debt service ratio, debt-to-income — using per-tenant configurable formulas | M |  |  |  |
| LOS-FR-076 | FR-DOC-045 | Detect statement tampering: font/metadata inconsistency, balance arithmetic that does not reconcile, missing transaction sequence, edited-PDF indicators | M |  |  |  |
| LOS-FR-077 | FR-DOC-046 | Present analysis output with drill-through from every derived metric to the underlying transactions | M |  |  |  |
| LOS-FR-078 | FR-DOC-050 | Cross-validate extracted values against application-entered data and against other documents (e.g. name on ID vs payslip vs bank statement) and raise discrepancies as review items | M |  |  |  |
| LOS-FR-079 | FR-DOC-051 | Verify documents against issuing-authority APIs where available (identity, company registry, tax authority) rather than relying on extraction alone | M |  |  | Nigeria: NIMC/NIBSS, CAC, FIRS — API availability [verify]. |
| LOS-FR-080 | FR-DOC-052 | Flag document forgery indicators: metadata anomalies, cloned regions, inconsistent DPI, digital-signature failures on signed PDFs | S |  |  |  |
| LOS-FR-081 | FR-DOC-053 | Maintain full document lineage: source, uploader, all extraction runs with model/version, all human edits, and the final accepted values | M |  |  |  |
| LOS-FR-082 | FR-DOC-054 | Allow the extraction engine to be replaced per tenant (vendor OCR/IDP service, self-hosted model, or hybrid) behind a common interface, including a fully on-premise option for restricted deployments | M |  | Q5,Q4 | [Q5][Q4] Drives hybrid recommendation (G-06, G-05). |

### §8.7 Credit Assessment and Decisioning

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-083 | FR-CRD-001 | Provide a business-user-maintainable rules engine for eligibility, policy, and knock-out criteria, authored without code and version-controlled | M |  | Q10 | [Q10] (G-11). Engine build (G-21). |
| LOS-FR-084 | FR-CRD-002 | Support decision tables, decision trees, and expression-based rules, with test/simulation against historical applications before activation | M |  |  | Needs historical import for new tenants (G-37). |
| LOS-FR-085 | FR-CRD-003 | Retrieve credit bureau reports from multiple bureaus via adapters, with configurable bureau selection, fallback order, and caching/re-pull rules | M |  |  | Nigeria: CRC, FirstCentral, CreditRegistry. Consent basis [verify] (G-24). |
| LOS-FR-086 | FR-CRD-004 | Parse bureau reports into a canonical credit profile: existing facilities, balances, delinquency history, enquiry count, judgments, guarantees | M |  |  |  |
| LOS-FR-087 | FR-CRD-005 | Support internal application and behavioural scorecards, with the scorecard as a versioned, configurable artefact | M |  |  | Behavioural data source (G-37). |
| LOS-FR-088 | FR-CRD-006 | Support external scoring services and locally hosted models behind a common scoring interface | S |  |  |  |
| LOS-FR-089 | FR-CRD-007 | Aggregate total customer and connected-group exposure across existing CBA facilities plus in-flight applications, and evaluate against single-obligor and portfolio limits | M |  |  | Depends on FR-CUS-010 (G-12) and limit base (G-35). |
| LOS-FR-090 | FR-CRD-008 | Compute affordability and serviceability using per-product, per-tenant configurable formulas with all inputs traceable to source | M |  |  | Overlaps FR-DOC-044; one affordability engine. |
| LOS-FR-091 | FR-CRD-009 | Support risk-based pricing: derive rate, fees, tenor, and amount from risk grade and configured pricing matrices | S |  |  |  |
| LOS-FR-092 | FR-CRD-010 | Generate a structured decision output: outcome (approve/refer/decline/counter-offer), risk grade, recommended terms, and ordered reason codes | M |  |  |  |
| LOS-FR-093 | FR-CRD-011 | Support counter-offers (reduced amount, altered tenor, additional security) as a first-class outcome with customer acceptance flow | M |  |  |  |
| LOS-FR-094 | FR-CRD-012 | Provide financial spreading for SME/corporate: capture financial statements (extracted or manual), compute ratios, trend analysis, and projections against configurable templates | S | M | Q1 | [Q1] Proposed M (G-02). |
| LOS-FR-095 | FR-CRD-013 | Support credit memo authoring with templated sections auto-populated from application data, plus free-text analyst narrative | M |  |  |  |
| LOS-FR-096 | FR-CRD-014 | Record every decision with a complete, replayable snapshot: input values, rule set version, scorecard version, bureau report reference, and output. A decision must be reproducible years later | M |  |  | Evaluator version recorded per decision (G-21). |
| LOS-FR-097 | FR-CRD-015 | Support policy exceptions/overrides with mandatory reason, evidence, and approval at the configured elevated authority; exceptions are reportable as a portfolio | M |  |  |  |
| LOS-FR-098 | FR-CRD-016 | Support champion/challenger execution of competing policy or scorecard versions, with outcome comparison reporting | C |  |  |  |
| LOS-FR-099 | FR-CRD-017 | Support fraud checks at decision time: velocity rules, device/IP signals for digital channels, internal blacklist, and shared negative-file lookup where available | S |  |  | Supports BD-07; needed for self-service channel (G-09). |

### §8.8 Collateral and Security

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-100 | FR-COL-001 | Capture collateral of configurable types: real estate, vehicles, equipment, inventory/stock, receivables, cash/deposit lien, securities, guarantees (personal/corporate/institutional), insurance assignment | M |  |  |  |
| LOS-FR-101 | FR-COL-002 | Apply per-type haircuts and compute forced-sale value, security cover ratio, and loan-to-value against product thresholds | M |  |  |  |
| LOS-FR-102 | FR-COL-003 | Track valuation: valuer, date, method, amount, and expiry, with re-valuation prompts | M |  |  |  |
| LOS-FR-103 | FR-COL-004 | Track perfection status through its lifecycle (search, stamping, registration, charge filing) with responsible party and due dates | M |  |  | Steps configurable per asset type and state (G-34). |
| LOS-FR-104 | FR-COL-005 | Support one collateral item securing multiple facilities, with allocation of value across them and prevention of over-allocation | M |  |  |  |
| LOS-FR-105 | FR-COL-006 | Track collateral insurance: policy, insurer, sum insured, expiry, and bank interest noted | M |  |  |  |
| LOS-FR-106 | FR-COL-007 | Register collateral in the CBA and/or external collateral registry via adapters where the tenant requires it | S |  |  | National Collateral Registry for movables [verify] (G-33). |
| LOS-FR-107 | FR-COL-008 | Block disbursement where perfection or insurance conditions are unmet, unless waived through the exception process | M |  |  | CP vs CS per product, else every mortgage needs a waiver (G-34). |

### §8.9 Workflow, Queues and Case Management

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-108 | FR-WFL-001 | Provide a configurable workflow engine where stages, transitions, entry/exit conditions, and assignment rules are defined per product without code | M |  |  | Two-layer state model (G-13). Engine build (G-21). |
| LOS-FR-109 | FR-WFL-002 | Support parallel branches (e.g. legal documentation running alongside credit assessment) with join conditions | M |  |  | (G-21). |
| LOS-FR-110 | FR-WFL-003 | Route work by role, skill, branch, product, amount band, language, and workload balancing | M |  |  |  |
| LOS-FR-111 | FR-WFL-004 | Support pull-based queues and push-based assignment, configurable per stage | M |  |  |  |
| LOS-FR-112 | FR-WFL-005 | Support reassignment, delegation, and out-of-office cover with full attribution of who acted for whom | M |  |  |  |
| LOS-FR-113 | FR-WFL-006 | Enforce stage SLAs with warning and breach thresholds, automatic escalation paths, and notification | M |  |  |  |
| LOS-FR-114 | FR-WFL-007 | Support return-to-previous-stage ("send back") with mandatory reason and targeted re-work items rather than restarting the journey | M |  |  |  |
| LOS-FR-115 | FR-WFL-008 | Support hold/suspend states (awaiting customer, awaiting third party) that pause the SLA clock, with auto-follow-up and auto-expiry | M |  |  |  |
| LOS-FR-116 | FR-WFL-009 | Provide a consolidated task inbox per user across all applications and stages, with filtering, sorting, bulk action, and saved views | M |  |  |  |
| LOS-FR-117 | FR-WFL-010 | Maintain a case notes/collaboration thread per application, with internal-only and customer-visible note types, and @mention notification | M |  |  |  |
| LOS-FR-118 | FR-WFL-011 | Support configurable auto-decisions at defined stages (auto-approve, auto-decline, auto-route) with the automated actor identified in the audit trail | M |  |  |  |
| LOS-FR-119 | FR-WFL-012 | Support workflow versioning; in-flight applications complete on the version they started, unless explicitly migrated by an administrator with audit | M |  |  |  |

### §8.10 Approval and Delegated Lending Authority

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-120 | FR-APV-001 | Configure an approval matrix keyed on product, amount, currency, risk grade, customer segment, exposure level, collateral coverage, and exception presence | M |  |  | Authority driven by total application exposure (G-15). |
| LOS-FR-121 | FR-APV-002 | Support individual approval limits per user, per role, and per organizational unit, with the effective limit being the most restrictive applicable | M |  |  |  |
| LOS-FR-122 | FR-APV-003 | Support sequential, parallel, and quorum-based (committee) approval, including configurable quorum size and majority rules | M |  |  |  |
| LOS-FR-123 | FR-APV-004 | Support committee workflow: agenda assembly, decision packet distribution, individual voting with recorded rationale, abstention, and minuted outcome | S | M | Q1 | [Q1] Proposed M (G-02, G-12). |
| LOS-FR-124 | FR-APV-005 | Enforce that an approver cannot approve an application they originated, recommended, or previously approved at a lower level | M |  |  |  |
| LOS-FR-125 | FR-APV-006 | Support temporary delegation of authority with mandatory start/end dates, an explicit delegator, and a hard cap not exceeding the delegator's own limit | M |  |  |  |
| LOS-FR-126 | FR-APV-007 | Automatically escalate to the next authority level when limits are exceeded or exceptions are present | M |  |  |  |
| LOS-FR-127 | FR-APV-008 | Require approvers to record a decision rationale; decline and conditional-approval outcomes require reason codes | M |  |  |  |
| LOS-FR-128 | FR-APV-009 | Support approval with conditions, which become tracked conditions precedent or subsequent | M |  |  |  |
| LOS-FR-129 | FR-APV-010 | Present approvers with a complete decision packet: application, credit analysis, bureau output, score, exposure, collateral, exceptions, documents, and full prior decision history | M |  |  |  |
| LOS-FR-130 | FR-APV-011 | Support mobile approval with the same authorization controls and step-up authentication | S |  | Q9 | [Q9] Responsive web + step-up (G-10). |
| LOS-FR-131 | FR-APV-012 | Time-bound approvals with configurable validity; expiry requires re-approval rather than silent lapse into disbursement | M |  |  |  |

### §8.11 Offer, Acceptance and Execution

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-132 | FR-OFR-001 | Generate offer letters and facility agreements from tenant-configurable templates merged with application data, in PDF | M |  |  |  |
| LOS-FR-133 | FR-OFR-002 | Include a complete key-facts/cost-of-credit summary as required by the applicable jurisdiction regulatory pack | M |  |  | Nigeria disclosure content [verify] (G-24). |
| LOS-FR-134 | FR-OFR-003 | Generate and display the full repayment schedule prior to acceptance, matching the schedule that will be booked | M |  | Q6 | [Q6] Schedule ownership + variance check (G-07, G-36). |
| LOS-FR-135 | FR-OFR-004 | Deliver offers to customers by email, SMS link, in-app, and printable branch copy | M |  |  |  |
| LOS-FR-136 | FR-OFR-005 | Capture acceptance electronically with e-signature, or record wet-signature acceptance with the executed document uploaded | M |  |  | Wet-sign mandatory for some instruments (G-26). |
| LOS-FR-137 | FR-OFR-006 | Integrate e-signature providers via a pluggable adapter, capturing the signature certificate, audit trail, and signing timestamps | M |  |  |  |
| LOS-FR-138 | FR-OFR-007 | Enforce offer validity periods with expiry, reminder notifications, and configurable re-issue rules | M |  |  |  |
| LOS-FR-139 | FR-OFR-008 | Support customer rejection or negotiation, routing back to credit for revised terms | M |  |  | Needs Offer → Assessment transition (G-14d). |
| LOS-FR-140 | FR-OFR-009 | Support multi-party signing (joint applicants, guarantors, witnesses, bank signatories) in configured order | M |  |  |  |
| LOS-FR-141 | FR-OFR-010 | Store the executed agreement immutably, hash-recorded, and linked to the exact template version used | M |  |  |  |

### §8.12 Conditions Precedent and Pre-Disbursement Control

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-142 | FR-CPR-001 | Maintain conditions precedent and conditions subsequent as tracked items with owner, due date, evidence attachment, and sign-off | M |  |  |  |
| LOS-FR-143 | FR-CPR-002 | Hard-block disbursement while any mandatory CP is outstanding, overridable only through the exception/waiver process at configured authority | M |  |  |  |
| LOS-FR-144 | FR-CPR-003 | Enforce a final pre-disbursement checklist: KYC current, screening clear, documentation executed, security perfected, insurance in force, account details verified, approval unexpired | M |  |  |  |
| LOS-FR-145 | FR-CPR-004 | Re-run sanctions/PEP screening immediately before disbursement and block on a hit | M |  |  |  |
| LOS-FR-146 | FR-CPR-005 | Verify the disbursement destination account: name match against the applicant, and confirm the account is active via the CBA or interbank name-enquiry service | M |  |  | Nigeria: NIBSS NIP name enquiry. |
| LOS-FR-147 | FR-CPR-006 | Track conditions subsequent past disbursement, with ownership handover to servicing and escalation on breach | S | M |  | Proposed M: FR-HND-003 depends on it (G-12). |

### §8.13 Disbursement and Booking

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-148 | FR-DSB-001 | Create the loan account in the CBA through the abstraction layer, passing the mapped product, terms, schedule basis, and customer identifier | M |  |  |  |
| LOS-FR-149 | FR-DSB-002 | Support disbursement modes: full single, tranched/milestone, revolving line activation, and third-party/vendor payment (e.g. direct to dealer or seller) | M |  |  | Tranches after Booked (G-14c). |
| LOS-FR-150 | FR-DSB-003 | Support tranche schedules with per-tranche conditions, approval, and independent release | M |  |  | (G-14c). |
| LOS-FR-151 | FR-DSB-004 | Deduct upfront fees, charges, and insurance premiums at disbursement per product configuration, itemized on the customer advice | M |  |  | VAT / stamp duty / premiums (G-36). |
| LOS-FR-152 | FR-DSB-005 | Guarantee exactly-once disbursement via idempotency keys; a retry or duplicate instruction must never result in a second posting | M |  |  |  |
| LOS-FR-153 | FR-DSB-006 | Handle asynchronous, deferred, and partial CBA responses, holding the application in a definite pending state rather than assuming success | M |  |  | Disbursing sub-states (G-14f). |
| LOS-FR-154 | FR-DSB-007 | Detect and surface CBA posting failures as actionable exceptions with retry, manual-intervention, and compensating-reversal paths | M |  |  |  |
| LOS-FR-155 | FR-DSB-008 | Reconcile daily between LOS disbursement records and CBA postings, reporting breaks in an exception dashboard | M |  |  |  |
| LOS-FR-156 | FR-DSB-009 | Enforce maker-checker on disbursement release, with the checker distinct from the maker and from the approver where policy requires | M |  |  |  |
| LOS-FR-157 | FR-DSB-010 | Support direct debit / standing instruction / payroll deduction mandate setup at booking via the appropriate adapter | S | M (salary-backed) |  | Proposed M for salary-backed products (G-27, G-12). |
| LOS-FR-158 | FR-DSB-011 | Generate and deliver a disbursement advice and the final amortization schedule to the customer | M |  |  |  |
| LOS-FR-159 | FR-DSB-012 | Support cancellation and reversal of a disbursement within a configurable window, with compensating CBA instruction and full audit | M |  |  | Reversal outcome modelling (G-14i). |

### §8.14 Handover to Servicing

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-160 | FR-HND-001 | On successful booking, transition the application to a terminal Booked state and publish a facility-created event consumable by downstream systems | M |  |  | LMS/servicing handoff via published event. |
| LOS-FR-161 | FR-HND-002 | Transfer the complete document set, or durable references to it, to the tenant's document/records system where one exists | M |  |  |  |
| LOS-FR-162 | FR-HND-003 | Hand over open conditions subsequent, collateral perfection items, and insurance expiries to the responsible servicing owner | M |  |  |  |
| LOS-FR-163 | FR-HND-004 | Retain the origination record and its audit trail in the LOS for the configured retention period regardless of downstream handover | M |  |  | Retention trigger (G-29). |
| LOS-FR-164 | FR-HND-005 | Support post-booking data corrections through a controlled, audited amendment process rather than direct edit | M |  |  |  |

### §8.15 Notifications and Communications

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-165 | FR-NTF-001 | Send notifications over email, SMS, push, in-app, and webhook, with per-tenant provider adapters | M |  |  | Push needs app/web-push (A-08); WhatsApp not listed (G-33). |
| LOS-FR-166 | FR-NTF-002 | Configure templates per event, channel, language, and tenant, with maker-checker on template changes | M |  |  |  |
| LOS-FR-167 | FR-NTF-003 | Respect customer communication preferences and consent, including opt-out for non-essential messages | M |  |  |  |
| LOS-FR-168 | FR-NTF-004 | Provide applicants with status visibility and outstanding-requirement prompts through a tracking link or portal | M |  | Q8 | [Q8] (G-09). |
| LOS-FR-169 | FR-NTF-005 | Retain a complete communication log per application: what was sent, to whom, when, through which channel, and its delivery status | M |  |  |  |
| LOS-FR-170 | FR-NTF-006 | Suppress duplicate notifications and apply configurable rate limits and quiet hours | S |  |  |  |

### §9 Core Banking Abstraction Layer

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-171 | FR-CBA-001 | Define a versioned canonical interface covering the operations in §9.2, with backward-compatible evolution rules | M |  |  |  |
| LOS-FR-172 | FR-CBA-002 | Ensure no CBA-specific identifier, field, code, or behaviour exists outside an adapter; enforce with automated architectural tests in CI | M |  |  |  |
| LOS-FR-173 | FR-CBA-003 | Provide a capability manifest per adapter declaring which operations it supports natively, emulates, or cannot support | M |  |  |  |
| LOS-FR-174 | FR-CBA-004 | Degrade gracefully against declared capability: where an operation is unsupported, substitute a configured alternative (manual task, batch file, or LOS-side computation) rather than failing the journey | M |  |  |  |
| LOS-FR-175 | FR-CBA-005 | Support synchronous, asynchronous/callback, batch-file, and message-queue interaction styles within one adapter model | M |  |  |  |
| LOS-FR-176 | FR-CBA-006 | Provide a fallback adapter for tenants whose CBA offers no API: structured file export/import plus a manual operations queue, preserving the same LOS journey | M |  |  |  |
| LOS-FR-177 | FR-CBA-007 | Attach an idempotency key to every state-changing operation and correctly handle duplicate submission, timeout-then-success, and partial failure | M |  |  |  |
| LOS-FR-178 | FR-CBA-008 | Implement the transactional outbox pattern so a committed LOS state change always eventually dispatches its CBA instruction | M |  |  |  |
| LOS-FR-179 | FR-CBA-009 | Implement saga-style compensation for multi-step operations (create account → disburse → post fees) with defined compensating actions per step | M |  |  |  |
| LOS-FR-180 | FR-CBA-010 | Apply per-adapter timeout, retry policy with backoff, and circuit breaking; open circuits raise operational alerts | M |  |  |  |
| LOS-FR-181 | FR-CBA-011 | Translate CBA-native error codes into a canonical error taxonomy (retryable / non-retryable / requires-intervention / business-rejection) | M |  |  |  |
| LOS-FR-182 | FR-CBA-012 | Configure field and code mapping (product codes, GL accounts, branch codes, currency codes, customer types) per tenant without code changes | M |  |  |  |
| LOS-FR-183 | FR-CBA-013 | Provide a conformance test suite ("certification kit") every new adapter must pass, covering happy paths, timeouts, duplicates, partial failures, and compensation | M |  |  |  |
| LOS-FR-184 | FR-CBA-014 | Provide an adapter SDK and documentation enabling a bank's own IT team or a third party to build an adapter without vendor involvement | S |  |  | Out-of-process adapters, language-neutral contract (G-20). |
| LOS-FR-185 | FR-CBA-015 | Provide a mock/simulator CBA for implementation, testing, and demonstration, configurable to inject failures and latency | M |  |  |  |
| LOS-FR-186 | FR-CBA-016 | Log every CBA interaction — request, response, latency, correlation ID, adapter version — with PII masking, retained per audit policy | M |  |  |  |
| LOS-FR-187 | FR-CBA-017 | Run scheduled reconciliation between LOS-believed state and CBA actual state, reporting all breaks | M |  |  |  |
| LOS-FR-188 | FR-CBA-018 | Support hot-swapping or upgrading an adapter without LOS core redeployment, with version pinning per tenant | S |  |  | Not achievable in-process; out-of-process runtime (G-20). |
| LOS-FR-189 | FR-CBA-019 | Cache read-heavy CBA reference data with configurable TTL and explicit invalidation; never cache balances or exposure used for a live limit decision beyond a short configured window | M |  |  |  |
| LOS-FR-190 | FR-CBA-020 | Extend the same adapter pattern to all external integrations — bureaus, KYC providers, screening, e-signature, payments, notifications — so no external vendor is hard-coded | M |  |  |  |

### §10.1 Framework

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-191 | FR-CMP-001 | Implement regulatory obligations as pluggable jurisdiction packs, versioned and effective-dated, so a rule change is a configuration release not a code release | M |  |  | Pack = jurisdiction × licence category (G-23). |
| LOS-FR-192 | FR-CMP-002 | Support multiple jurisdiction packs on one deployment for tenants operating across borders, resolved by the booking entity | S | C (v2) | Q2 | [Q2] Proposed defer to v2 (G-03). |
| LOS-FR-193 | FR-CMP-003 | Maintain an effective-dated register of regulatory rules with citation reference, so any historical decision can be evaluated against the rule in force at the time | M |  |  | BRD cites no regulation (A-15). |

### §10.2 AML / CFT and Financial Crime

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-194 | FR-CMP-010 | Enforce completion of KYC/CDD to the required standard before decisioning; block progression on incomplete CDD | M |  |  |  |
| LOS-FR-195 | FR-CMP-011 | Screen all parties against sanctions, PEP, and internal watchlists at intake, at approval, and immediately before disbursement | M |  |  | Includes CPF (proliferation financing) [verify] (G-24). |
| LOS-FR-196 | FR-CMP-012 | Re-screen the in-flight population automatically when watchlists are updated, raising alerts on new matches | M |  |  |  |
| LOS-FR-197 | FR-CMP-013 | Provide an alert management workspace: match scoring, false-positive disposition with reason, escalation, and four-eyes clearance for true matches | M |  |  |  |
| LOS-FR-198 | FR-CMP-014 | Prevent any user from clearing a screening alert on an application they originated | M |  |  |  |
| LOS-FR-199 | FR-CMP-015 | Capture source of funds and source of wealth where the risk rating requires it | M |  |  |  |
| LOS-FR-200 | FR-CMP-016 | Provide structured data extracts to support suspicious transaction/activity reporting to the relevant FIU | M |  |  | Target NFIU goAML schema [verify] (G-24). |
| LOS-FR-201 | FR-CMP-017 | Retain all screening evidence — list version, query, response, disposition — for the statutory retention period | M |  |  |  |

### §10.3 Credit Regulation and Reporting

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-202 | FR-CMP-020 | Enforce mandatory credit bureau enquiry before approval where the jurisdiction requires it, and block approval without a valid, in-date report | M |  |  |  |
| LOS-FR-203 | FR-CMP-021 | Record and enforce customer consent as a precondition to bureau enquiry | M |  |  |  |
| LOS-FR-204 | FR-CMP-022 | Generate regulatory credit reporting extracts (e.g. Nigeria's CRMS submission and bureau reporting) in the prescribed format and cadence, as a configurable pack artefact | M |  |  | Reword: origination-time obligations only (G-25). |
| LOS-FR-205 | FR-CMP-023 | Enforce regulatory limits: single-obligor limits, insider/related-party lending rules and their distinct approval paths, and sectoral caps where mandated | M |  |  | Limits differ by licence (G-23); limit base (G-35). |
| LOS-FR-206 | FR-CMP-024 | Flag insider and related-party applications automatically and route them through the elevated approval path with board-level reporting | M |  |  | Insider register source (G-35). |
| LOS-FR-207 | FR-CMP-025 | Generate mandated pre-contractual disclosures — total cost of credit, effective interest rate, all fees, cooling-off rights where applicable — using the jurisdiction pack's calculation method and template | M |  |  | (G-24). |
| LOS-FR-208 | FR-CMP-026 | Issue adverse action notices on decline where required, containing the principal reasons in customer-comprehensible language | M |  |  |  |
| LOS-FR-209 | FR-CMP-027 | Support responsible-lending affordability assessment as a mandatory, evidenced gate where the jurisdiction requires it | M |  |  |  |

### §10.4 Data Protection and Privacy

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-210 | FR-CMP-030 | Comply with applicable data protection law (Nigeria Data Protection Act 2023 for the reference pack; GDPR-equivalent controls for other jurisdictions) | M |  |  | Not testable as written; decomposed into CMP-031..037 + GAID (G-24, G-42). |
| LOS-FR-211 | FR-CMP-031 | Maintain a data inventory identifying every PII field, its lawful basis, purpose, and retention period | M |  |  |  |
| LOS-FR-212 | FR-CMP-032 | Capture, store, and honour granular consent, including withdrawal, with consent history retained | M |  |  |  |
| LOS-FR-213 | FR-CMP-033 | Support data subject rights: access, rectification, erasure, portability, and restriction — subject to statutory retention overrides, with the override reason recorded | M |  |  |  |
| LOS-FR-214 | FR-CMP-034 | Enforce configurable retention schedules with automated purge or anonymization at expiry, and legal hold to suspend purge | M |  |  | Retention trigger (G-29). |
| LOS-FR-215 | FR-CMP-035 | Restrict data residency to the configured jurisdiction, including backups, logs, and any third-party processing | M |  |  | Processor location enforcement (G-30). |
| LOS-FR-216 | FR-CMP-036 | Mask PII by default in the UI, logs, exports, and lower environments; unmasking is an explicit, permissioned, logged action | M |  |  |  |
| LOS-FR-217 | FR-CMP-037 | Maintain a record of third-party processors, the data shared, and the legal basis for each transfer | S | M |  | Proposed M; residency enforcement depends on it (G-30). |

### §10.5 Model and Decision Governance

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-218 | FR-CMP-040 | Maintain a model inventory covering every scorecard, extraction model, and classification model in use, with owner, version, and validation date | M |  |  |  |
| LOS-FR-219 | FR-CMP-041 | Version-control models and require documented validation and approval before any model is activated in production | M |  |  |  |
| LOS-FR-220 | FR-CMP-042 | Monitor model performance and input drift, alerting owners on degradation beyond configured thresholds | S |  |  |  |
| LOS-FR-221 | FR-CMP-043 | Store per-decision reason codes sufficient to explain any automated outcome to a customer or regulator | M |  |  |  |
| LOS-FR-222 | FR-CMP-044 | Support a right to human review of fully automated decisions where the jurisdiction requires it, with a defined route to a human decision-maker | M |  |  | NDPA automated-decision provision [verify] (G-24); state model (G-14e). |
| LOS-FR-223 | FR-CMP-045 | Support fair-lending and disparate-impact monitoring by producing outcome distributions across configured attributes, without those attributes being used as decision inputs | S |  |  | Sensitive-data attributes need DPIA (G-28). |

### §11 Audit and Traceability

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-224 | FR-AUD-001 | Record an immutable, append-only audit event for every state change, decision, configuration change, and privileged data access | M |  |  |  |
| LOS-FR-225 | FR-AUD-002 | Capture on every event: actor identity, actor role and effective permissions at the time, timestamp with timezone, source IP and device, action, entity, before and after values, correlation ID, and reason where the action requires one | M |  |  |  |
| LOS-FR-226 | FR-AUD-003 | Make audit records non-editable and non-deletable by any user, including platform administrators and DBAs | M |  |  | Interpretation: prevent for users, detect + anchor for DBAs (G-22). |
| LOS-FR-227 | FR-AUD-004 | Apply tamper evidence — hash chaining or equivalent — with a verification routine that detects any alteration | M |  |  | Chain head anchored to WORM / SIEM (G-22). |
| LOS-FR-228 | FR-AUD-005 | Attribute automated and system actions to a named system identity, never to a generic or ambiguous actor | M |  |  |  |
| LOS-FR-229 | FR-AUD-006 | Log all read access to PII and to application data, distinguishing the access purpose where the workflow provides it | M |  |  |  |
| LOS-FR-230 | FR-AUD-007 | Log every authentication event, permission change, role assignment, delegation, and failed authorization attempt | M |  |  |  |
| LOS-FR-231 | FR-AUD-008 | Log every external integration call and its response, with PII masked | M |  |  |  |
| LOS-FR-232 | FR-AUD-009 | Provide an audit explorer with search and filter by application, user, date range, action type, and entity, available to auditors without granting operational permissions | M |  |  |  |
| LOS-FR-233 | FR-AUD-010 | Generate an **evidence pack** for any application on demand: complete timeline, all documents with hashes, all decisions with their inputs and versions, all approvals with authority basis, all screening results, and all communications — exportable as a single signed archive | M |  |  |  |
| LOS-FR-234 | FR-AUD-011 | Reconstruct the exact state of any application as at any past point in time (temporal query) | M |  |  | Event-sourced application aggregate (PR-04). |
| LOS-FR-235 | FR-AUD-012 | Retain audit records for the configured statutory period independent of the operational data retention schedule | M |  |  |  |
| LOS-FR-236 | FR-AUD-013 | Support write-once storage or equivalent immutability guarantee for audit and document stores | M |  |  | On-prem needs Object-Lock-compatible store (G-22). |
| LOS-FR-237 | FR-AUD-014 | Support streaming audit events to the tenant's SIEM in a standard format | S |  |  |  |
| LOS-FR-238 | FR-AUD-015 | Alert on suspicious patterns: bulk export, out-of-hours privileged access, repeated failed authorization, unusual override frequency by user | S |  |  |  |
| LOS-FR-239 | FR-AUD-016 | Provide a report of all overrides, waivers, exceptions, and manual interventions across a period, by user and by type | M |  |  |  |

### §12 Security and Access Control

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-240 | FR-SEC-001 | Provide fine-grained permissions at the level of resource and action, including field-level control for sensitive attributes | M |  |  |  |
| LOS-FR-241 | FR-SEC-002 | Allow tenants to define custom roles and to clone and modify a library of standard roles | M |  |  |  |
| LOS-FR-242 | FR-SEC-003 | Deny by default: an unassigned permission is denied; a newly created role holds nothing | M |  |  |  |
| LOS-FR-243 | FR-SEC-004 | Support multiple concurrent role assignments per user, with effective access as the union of granted permissions constrained by each assignment's scope | M |  |  |  |
| LOS-FR-244 | FR-SEC-005 | Support all scope dimensions listed in §12.1, combinable within a single assignment | M |  |  |  |
| LOS-FR-245 | FR-SEC-006 | Enforce a configurable segregation-of-duties matrix defining mutually exclusive role pairs, blocking conflicting assignment and reporting existing conflicts | M |  |  |  |
| LOS-FR-246 | FR-SEC-007 | Enforce maker-checker on configurable actions: configuration changes, product activation, limit changes, role assignment, disbursement release, template changes | M |  |  |  |
| LOS-FR-247 | FR-SEC-008 | Support time-bound access: effective-dated assignments, temporary elevation with automatic expiry, and mandatory expiry on delegation | M |  |  |  |
| LOS-FR-248 | FR-SEC-009 | Provide break-glass emergency access requiring justification, triggering immediate alerts to security and management, auto-expiring, and generating a mandatory post-use review | M |  |  |  |
| LOS-FR-249 | FR-SEC-010 | Support periodic access recertification campaigns where managers attest to their team's entitlements, with revocation of unattested access | S |  |  |  |
| LOS-FR-250 | FR-SEC-011 | Provide an "effective access" view showing exactly what a given user can see and do, and why (which assignment grants it) | M |  |  |  |
| LOS-FR-251 | FR-SEC-012 | Integrate with tenant identity providers via SAML 2.0 and OIDC, supporting SSO and SCIM-based user lifecycle provisioning and deprovisioning | M |  |  | Built-in IdP for tenants without one (G-32); LDAP (G-33). |
| LOS-FR-252 | FR-SEC-013 | Enforce MFA, with step-up authentication for high-risk actions (approval above threshold, disbursement release, PII unmask, configuration change) | M |  |  | Applies on every device incl. mobile approval (G-10). |
| LOS-FR-253 | FR-SEC-014 | Apply the same permission model to API access; service accounts and partner credentials are scoped identically to human users, with no privileged bypass | M |  |  |  |
| LOS-FR-254 | FR-SEC-015 | Enforce session controls: idle timeout, absolute timeout, concurrent session limits, and forced re-authentication after privilege change | M |  |  |  |
| LOS-FR-255 | FR-SEC-016 | Encrypt data in transit (TLS 1.3) and at rest, with tenant-level key separation and support for customer-managed keys | M |  |  | Per-tenant keys via KMS/HSM abstraction. |
| LOS-FR-256 | FR-SEC-017 | Apply field-level encryption to the most sensitive attributes (identity numbers, account numbers) with access mediated by permission | M |  |  |  |
| LOS-FR-257 | FR-SEC-018 | Never expose production PII in non-production environments; provide deterministic anonymization for test data refresh | M |  |  |  |
| LOS-FR-258 | FR-SEC-019 | Enforce rate limiting, input validation, and protection against the OWASP Top 10 across all interfaces | M |  |  | Raised to OWASP ASVS L2/L3 (A-17). |
| LOS-FR-259 | FR-SEC-020 | Support IP allow-listing and device-binding per tenant where required | S |  |  |  |

### §13 Reporting and Analytics

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-260 | FR-RPT-001 | Provide role-scoped operational dashboards: pipeline by stage, TAT by stage, SLA breaches, queue volumes, aging | M |  |  |  |
| LOS-FR-261 | FR-RPT-002 | Provide management reporting: volumes and values by product, channel, branch, officer, and segment; approval and decline rates; STP rate | M |  |  |  |
| LOS-FR-262 | FR-RPT-003 | Provide risk reporting: score distribution, risk grade mix, exception and override volumes, exposure concentration, collateral coverage | M |  |  |  |
| LOS-FR-263 | FR-RPT-004 | Provide productivity reporting by user and team: throughput, cycle time, rework, and override rate | S |  |  |  |
| LOS-FR-264 | FR-RPT-005 | Provide funnel and drop-off analysis by channel and stage, including reasons for abandonment | S |  |  |  |
| LOS-FR-265 | FR-RPT-006 | Generate jurisdiction-pack regulatory reports in prescribed formats on the prescribed cadence, with a submission log | M |  |  |  |
| LOS-FR-266 | FR-RPT-007 | Provide user-definable reports over a governed semantic layer, respecting the requesting user's RBAC scope | S |  |  |  |
| LOS-FR-267 | FR-RPT-008 | Support scheduled report delivery and export in CSV, XLSX, and PDF, with every export logged | M |  |  |  |
| LOS-FR-268 | FR-RPT-009 | Publish a data extract or streaming feed to the tenant's data warehouse or lake without direct access to the operational database | M |  |  |  |
| LOS-FR-269 | FR-RPT-010 | Enforce RBAC scope on all reporting; no report may reveal data the user could not see in the application | M |  |  |  |

### §17 Configuration and Extensibility Model

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Proposed | Scope | Notes |
|---|---|---|:-:|:-:|:-:|---|
| LOS-FR-270 | FR-CFG-001 | Provide an administrative console covering all layers 1–4 without developer involvement | M |  |  |  |
| LOS-FR-271 | FR-CFG-002 | Validate configuration on save, with clear error reporting, and prevent activation of internally inconsistent configuration | M |  |  |  |
| LOS-FR-272 | FR-CFG-003 | Provide a tenant onboarding accelerator: baseline configuration templates by institution type, reducing greenfield setup | S |  |  |  |
| LOS-FR-273 | FR-CFG-004 | Ensure platform upgrades never overwrite tenant configuration; surface any configuration made obsolete by an upgrade | M |  |  |  |

## Functional requirements stated in the BRD without an FR ID

| LOS ID | BRD source | Requirement | Pri | Notes |
|---|---|---|:-:|---|
| LOS-FR-274 | BRD §6, §12.3 | Platform Operator: tenant lifecycle management (provision, configure isolation mode, suspend, decommission) from a vendor operator console. | M | G-31 |
| LOS-FR-275 | BRD §6 | Platform Operator: adapter registry, registering adapter versions and pinning them per tenant. | M | G-31; relates FR-CBA-018 |
| LOS-FR-276 | BRD §6 | Platform Operator: cross-tenant operational observability (health, queues, adapter circuit state) with no tenant business data. | M | G-31 |
| LOS-FR-277 | BRD §12.3 | Platform Operator has no access to tenant business data without tenant-granted, logged, time-bound authorisation. | M | G-31 |
| LOS-FR-278 | BRD §12.3 | Ship the standard role library (19 roles listed in §12.3) as tenant-modifiable templates. | M | Relates FR-SEC-002 |
| LOS-FR-279 | BRD §12.3 | Auditor role: read-only, unrestricted scope, no operational actions. | M | Relates FR-AUD-009 |
| LOS-FR-280 | BRD §12.3 | Regulator/Examiner role: read-only and time-bound. | M |  |
| LOS-FR-281 | BRD §12.3 | Partner/DSA role restricted to own submissions. | M | Relates FR-SEC-014 |
| LOS-FR-282 | BRD §18 | Canonical application state model as listed in §18; tenants may select and relabel states but not create new ones without a product change. | M | G-13, G-14 |
| LOS-FR-283 | BRD §18 | Cross-cutting states available from most stages: On Hold, Returned for Rework, Withdrawn, Cancelled, Expired, Failed (CBA). | M | G-14 |
| LOS-FR-284 | BRD §17 | Configuration layer model: reference data, declarative config, rules, extension attributes, adapters, extension points; tenant code forks prohibited. Extension points are defined, versioned and isolated. | M | G-44 |
| LOS-FR-285 | BRD §16 | Valuation integration: valuer instruction and report retrieval (bi-directional). | M* | Criticality Medium in §16; G-33 |
| LOS-FR-286 | BRD §16 | Insurance integration: quote, policy issue, verification (bi-directional). | M* | Criticality Medium in §16; G-33 |
| LOS-FR-287 | BRD §16 | Payroll integration: employer verification and deduction mandate (bi-directional). | M* | G-27, G-33 |

\* §16 gives integration *criticality*, not priority. I've assumed these are in scope as Must because §16 says "every integration in this catalogue is implemented behind the adapter pattern". Live adapters are subject to G-04.

## Non-functional requirements

| LOS ID | BRD ID | Requirement (verbatim) | Pri | Notes |
|---|---|---|:-:|---|
| LOS-NFR-001 | NFR-001 | [Performance] Interactive screens respond within 2s at the 95th percentile under expected peak load | M* | 'Expected peak load' undefined (G-42). |
| LOS-NFR-002 | NFR-002 | [Performance] Automated decisioning completes within 30s at p95, excluding external provider latency, which is separately measured and reported | M* |  |
| LOS-NFR-003 | NFR-003 | [Performance] Document classification and extraction completes within 60s at p95 for a document up to 20 pages | M* | On-prem hardware sizing (G-06). |
| LOS-NFR-004 | NFR-004 | [Scalability] Horizontally scalable; a tenant's growth is met by adding capacity, not by re-architecture. Target and peak volumes to be set per tenant at discovery | M* | Volumes undefined (G-42). |
| LOS-NFR-005 | NFR-005 | [Availability] 99.9% monthly availability for core origination services, excluding scheduled maintenance windows | M* | Not achievable on single VPS (G-19). |
| LOS-NFR-006 | NFR-006 | [Resilience] Degrade gracefully when an external provider is unavailable: queue, retry, and surface an exception; never lose a submitted application | M* |  |
| LOS-NFR-007 | NFR-007 | [Recovery] RPO ≤ 15 minutes, RTO ≤ 4 hours; documented and tested DR at least annually | M* | (G-19). |
| LOS-NFR-008 | NFR-008 | [Data integrity] No committed application data loss under any single-node failure | M* | Requires synchronous DB replication (G-19). |
| LOS-NFR-009 | NFR-009 | [Deployability] One artefact deployable as multi-tenant SaaS, single-tenant private cloud, or on-premise, without code divergence | M* |  |
| LOS-NFR-010 | NFR-010 | [Deployability] Containerized, infrastructure-as-code provisioned, with zero-downtime rolling upgrades | M* | (G-19). |
| LOS-NFR-011 | NFR-011 | [Observability] Distributed tracing with a correlation ID propagated across every service and adapter call; structured logging; business and technical metrics | M* |  |
| LOS-NFR-012 | NFR-012 | [Maintainability] Configuration changes take effect without restart; no tenant requires a code fork | M* |  |
| LOS-NFR-013 | NFR-013 | [Usability] Primary application capture completable by a trained officer without switching to another system for any routine task | M* |  |
| LOS-NFR-014 | NFR-014 | [Accessibility] WCAG 2.2 Level AA for all customer-facing and staff interfaces | M* | WCAG 2.2 AA governs over brief's 2.1 (G-46). |
| LOS-NFR-015 | NFR-015 | [Compatibility] Current and prior major versions of Chrome, Edge, Safari, and Firefox; responsive down to tablet; native or PWA for field agents | M* | Design handoff is fixed 1440px (G-45). |
| LOS-NFR-016 | NFR-016 | [Low bandwidth] Usable over constrained and intermittent connectivity, with graceful handling of upload interruption | M* | Resumable chunked uploads. |
| LOS-NFR-017 | NFR-017 | [Localization] Language, currency, number, date, address, and name-format handling configurable per tenant and per jurisdiction | M* |  |
| LOS-NFR-018 | NFR-018 | [Testability] Every tenant has a non-production environment with a CBA simulator and anonymized data | M* |  |
| LOS-NFR-019 | NFR-019 | [Interoperability] Public API documented to OpenAPI 3.x, versioned, with a published deprecation policy and sandbox | M* |  |
| LOS-NFR-020 | NFR-020 | [Portability] Tenants can export their complete data set in documented open formats on exit | M* |  |

## INFERRED requirements (not in BRD; approved 2026-10-08, D-029)

Each of these closes a gap without which a stated requirement cannot be built or tested. All approved by D-029.

| LOS ID | Proposed requirement | Derived from | Gap |
|---|---|---|---|
| LOS-FR-301 | Record per-field data provenance (CBA/API, extraction, applicant self-service, staff keying) to measure OB-01. | OB-01 | G-43 |
| LOS-FR-302 | Built-in identity store with MFA, password policy and lockout for tenants without a SAML/OIDC IdP. | FR-SEC-012 | G-32 |
| LOS-FR-303 | Applicant authentication for self-service via phone/email OTP with optional BVN-linked phone match. | FR-CHN-001, FR-NTF-004 | G-32 |
| LOS-FR-304 | WhatsApp notification channel adapter. | Your brief | G-33 |
| LOS-FR-305 | LDAP/Active Directory identity adapter. | Your brief | G-33 |
| LOS-FR-306 | Insider/related-party register (directors, significant shareholders, staff, their related parties), maintained under maker-checker or fed from HR/CBA. | FR-CMP-023/024 | G-35 |
| LOS-FR-307 | Effective-dated prudential parameters per legal entity (limit base, regulatory limits) under maker-checker. | FR-CMP-023, FR-CRD-007 | G-35 |
| LOS-FR-308 | Historical application import in a canonical format for rules simulation and scorecard calibration. | FR-CRD-002 | G-37 |
| LOS-FR-309 | Adapter capability manifest declares data-processing location; activation refused where it breaches tenant residency. | FR-CMP-035, FR-CBA-003 | G-30 |
| LOS-FR-310 | Jurisdiction pack resolved by jurisdiction × licence category of the booking legal entity. | FR-CMP-001 | G-23 |
| LOS-FR-311 | GSI consent capture in consent model and offer template [verify]. | FR-CUS-008 | G-27 |
| LOS-FR-312 | Canonical bulk-upload template (CSV/XLSX) per product, with row-level validation report. | FR-CHN-001 | G-38 |
| LOS-FR-313 | Booking-time comparison of CBA-returned schedule with accepted offer schedule; variance beyond tolerance blocks booking as an exception. | FR-OFR-003 | G-07 |
| LOS-FR-314 | Execution method (e-sign / wet-sign) configured per document template, with pack defaults for land and security instruments. | FR-OFR-005 | G-26 |
| LOS-FR-315 | Employer/payroll verification as a pre-decision check for salary-backed products. | §16 Payroll | G-27 |

## Coverage status

No requirement is marked *covered*. Coverage needs a module, a build task and a test, and those are assigned in the TRD (Step 2), the development plan (Step 4) and the traceability matrix (deliverable 08).

## Additions after baseline (change-controlled)

| LOS ID | Source | Requirement | Pri | Notes |
|---|---|---|:-:|---|
| LOS-FR-316 | PO direction 2026-10-08 (D-030, D-034) | The installation is licensed per bank and managed through the Atheris licence server: signed licence (modules, user caps, validity, installation binding), offline activation, fail-safe enforcement. | M | G-48 |

**Scope changes 2026-10-08:**
- D-032 limits the target segment to commercial banks.
- D-022 removes the FCCPC instruments.
- LOS-FR-310 is narrowed to Nigeria × commercial bank.

No requirement text has changed.
