# 02 — Gap, Ambiguity and Assumptions Register

| | |
|---|---|
| **Project** | Loan Origination System (working name "Fundly", see G-47) |
| **Source baseline** | `loan-origination-system-brd-v0.1.docx` (BRD v0.1, 27 Aug 2026, "Working draft") |
| **Step** | 1 of 5: BRD study |
| **Status** | Awaiting Product Owner decisions |
| **Date** | 7 October 2026 |

## How to read this register

- **Fact** is what the BRD says, cited by section or ID. **Recommendation** is my proposal. They are kept separate on purpose.
- **Severity** levels:
  - **Blocking**: must be decided before the TRD (Step 2) can be written without guessing.
  - **High**: changes scope, cost or the compliance position materially.
  - **Medium**: changes design detail.
  - **Low**: editorial.
- **Decision needed:** where it says **Yes**, the item is mirrored in `decision-log.md` with a decision ID. Where it says **No**, I'll go with the recommendation unless you object.
- Regulatory statements marked **[verify]** come from my own knowledge, not from the BRD. Each will be checked against the primary source (CBN, NDPC, FCCPC or NFIU circular) in TRD §9 before being designed in. The BRD itself cites no regulation by section (see A-15).

---

## Summary

| ID | Title | Category | Severity | Decision needed |
|---|---|---|---|---|
| G-01 | BRD is an unbaselined v0.1 draft | Baseline | Blocking | Yes (D-001) |
| G-02 | Lending segments in v1 (BRD Q1) | Scope | Blocking | Yes (D-002) |
| G-03 | Jurisdictions beyond Nigeria (BRD Q2) | Scope | High | Yes (D-003) |
| G-04 | CBAs at launch and sandbox access (BRD Q3) | Scope / integration | Blocking | Yes (D-004) |
| G-05 | SaaS-first vs on-prem together (BRD Q4) | Scope / deployment | Blocking | Yes (D-005) |
| G-06 | Document extraction: build, buy or hybrid (BRD Q5) | Scope / architecture | Blocking | Yes (D-006) |
| G-07 | Repayment schedule ownership (BRD Q6) | Architecture | Blocking | Yes (D-007) |
| G-08 | No design-partner institution (BRD Q7) | Delivery | High | Yes (D-008) |
| G-09 | Self-service in v1: Q8 contradicts FR-CHN-001 (M) | Contradiction | Blocking | Yes (D-009) |
| G-10 | Mobile approval (BRD Q9) | Scope | Medium | Yes (D-010) |
| G-11 | Credit policy ownership (BRD Q10) | Scope / UX | Medium | Yes (D-011) |
| G-12 | Priority conflicts between dependent requirements | Contradiction | High | Yes (D-012) |
| G-13 | Fixed canonical states (§18) vs configurable workflow (FR-WFL-001) | Contradiction | High | No |
| G-14 | Gaps and anomalies in the §18 lifecycle | Ambiguity | High | Yes (D-013) |
| G-15 | Applications with multiple facilities and partial outcomes | Ambiguity | High | Yes (D-014) |
| G-16 | API-first (PR-01) vs Inertia | Architecture vs constraint | Blocking | Yes (D-015) |
| G-17 | Laravel 11 is out of security support | Architecture vs constraint | Blocking | Yes (D-016) |
| G-18 | MySQL vs PostgreSQL | Architecture | Blocking | Yes (D-017) |
| G-19 | "Linux VPS" hosting cannot meet NFR-005/007/008/010 | Architecture vs constraint | Blocking | Yes (D-018) |
| G-20 | Adapter runtime: hot-swap (FR-CBA-018) and third-party SDK (FR-CBA-014) | Architecture | High | Yes (D-019) |
| G-21 | Rules and workflow engines: build vs embed | Architecture | High | Yes (D-020) |
| G-22 | Audit immutability against DBAs (FR-AUD-003) and WORM (FR-AUD-013) | Testability / architecture | High | Yes (D-021) |
| G-23 | Jurisdiction pack is keyed by country, but Nigerian rules vary by licence type | Regulatory | High | No |
| G-24 | Nigerian regulatory instruments the BRD does not address | Regulatory | High | Yes (D-022) |
| G-25 | CRMS and bureau reporting: LOS vs CBA/servicing boundary | Regulatory / scope | High | Yes (D-023) |
| G-26 | E-signature not enforceable for some security instruments | Regulatory | High | No |
| G-27 | GSI and payroll mandates under-prioritised | Regulatory / scope | High | Yes (D-024) |
| G-28 | Fair-lending monitoring attributes vs NDPA sensitive data | Regulatory | Medium | No |
| G-29 | Retention clock trigger unknown to the LOS | Regulatory | Medium | No |
| G-30 | Data residency of third-party processors | Regulatory | High | No |
| G-31 | Platform Operator capabilities have no FRs | Under-specified | High | No |
| G-32 | Authentication for tenants without an IdP, and for applicants | Under-specified | High | No |
| G-33 | Integration list in your brief vs BRD §16 | Under-specified | Medium | Yes (D-025) |
| G-34 | Mortgage collateral perfection and CP vs CS | Under-specified | High | No |
| G-35 | Prudential parameters and insider register not specified | Under-specified | High | No |
| G-36 | Fees, taxes, day-count and rounding | Under-specified | High | No |
| G-37 | Behavioural scorecards and simulation history | Under-specified | Medium | No |
| G-38 | Bulk upload, scanner and email ingestion formats | Under-specified | Medium | No |
| G-39 | Offline agent capture and device security | Under-specified / security | Medium | No |
| G-40 | Partner/DSA commission | Under-specified | Low | No |
| G-41 | FX rate source | Under-specified | Low | No |
| G-42 | NFRs not testable as written (load profile undefined) | Testability | High | Yes (D-026) |
| G-43 | OB-01 needs field-level data provenance | Testability | Medium | No |
| G-44 | Editorial defects in the BRD | Editorial | Low | No |
| G-45 | Three conflicting design systems | Design | Blocking for Step 5 | Yes (D-027) |
| G-46 | Accessibility target and contrast failures | Design / NFR | High | No |
| G-47 | Product name | Branding | Low | Yes (D-028) |

---

## A. Baseline and scope (BRD §20 open questions)

### G-01 BRD is an unbaselined v0.1 draft (Blocking)
- **Fact.** The cover page says "0.1 — Draft for review"; document owner and approvers are "TBD". §20 says its ten open questions "should be resolved before build". The objectives table skips OB-03 (G-44).
- **Why it matters.** "Build the entire system" against a moving draft makes traceability meaningless. Every later edit to the BRD silently changes what "done" means.
- **Recommendation.** Treat your Step 1 sign-off as **BRD baseline v1.0**: this inventory plus your decisions in `decision-log.md`. From then on, any change to scope goes through a change request: an entry in the decision log, new or retired requirement IDs, and an impact note. Requirement IDs are never reused.

### G-02 Lending segments in v1 (BRD Q1) (Blocking)
- **Fact.** FR-PRD-002 (M) lists every category including corporate term loans. FR-CRD-012 (financial spreading) and FR-APV-004 (committee workflow) are only **S**. §20 Q1 says retail-only is "roughly half the v1 surface".
- **Context from your brief.** The target market is commercial banks, PMBs and licensed fintech lenders.
- **Recommendation.** v1 = **retail + SME + mortgage**. Corporate term loans are configurable as a product but get no corporate-only depth: no syndication (already deferred), no multi-entity group structures beyond FR-CUS-006/010. **Promote FR-APV-004 (committee) and FR-CRD-012 (spreading) to M.** Every Nigerian bank and PMB I'd expect to sell to routes SME and mortgage credits above branch limits through a management or board credit committee, and SME assessment needs spreading. Without these two, the SME claim is hollow.

### G-03 Jurisdictions beyond Nigeria (BRD Q2) (High)
- **Fact.** AS-05 makes Nigeria the reference pack. FR-CMP-001 (M) requires the pack framework to be pluggable. FR-CMP-002 (multiple packs per deployment) is S.
- **Recommendation.** v1 ships the **Nigeria pack only**. The framework stays genuinely pluggable (FR-CMP-001), which is proven by a second "test jurisdiction" pack used only in automated tests. FR-CMP-002 moves to v2 unless you name a cross-border tenant.

### G-04 CBAs at launch and sandbox access (BRD Q3) (Blocking)
- **Fact.** FR-CBA-006 (generic file/manual adapter), FR-CBA-013 (certification kit) and FR-CBA-015 (simulator) are all M. §20 Q3 calls adapter effort "the single largest integration risk". No CBA is named in the BRD.
- **Context from your brief.** Finacle, Flexcube, T24, BankOne and Mifos-class cores.
- **Recommendation.**
  - v1 ships: the canonical contract, the simulator, the generic file + manual-queue adapter, and the certification kit.
  - **Live adapters only where we hold a sandbox.** Every other core stays at "Contract defined / Stub ready" in the Integration Register.
  - Finacle is the obvious first live adapter if sandbox access through your Finacle work is realistic. A Mifos/Fineract-class adapter is the natural second, because fintech tenants commonly run it and its API is open.
- **I need from you:** the first one or two cores, and whether sandboxes exist.

### G-05 SaaS-first vs on-prem together (BRD Q4) (Blocking)
- **Fact.** PR-10, NFR-009 and the §5 constraints require one artefact for SaaS, private cloud and on-prem. §5 adds air-gapped and restricted-egress tenants, where "core paths" cannot rely on external SaaS.
- **Recommendation.**
  - **One artefact from day one**: OCI container images plus a Helm chart and a docker-compose profile. This costs little early and is very expensive to retrofit.
  - The first *certified* deployment is SaaS/private cloud. Signed offline install bundles for on-prem and air-gapped tenants come in the hardening phase.
  - Consequence: every adapter on a core path (IDP, screening lists, notifications) needs an on-prem-capable option. This feeds G-06 and G-30.

### G-06 Document extraction: build, buy or hybrid (BRD Q5) (Blocking)
- **Fact.** FR-DOC-020 to FR-DOC-054 hold 23 requirements, 19 of them M. They include classification without the user declaring the type, splitting of bundled PDFs, extraction for 15 document types, per-field confidence, tamper detection, handwriting (S) and a fully on-prem option (FR-DOC-054). NFR-003 sets 60s at p95 for 20 pages.
- **My position.** This is an ML product in its own right, not a Laravel module. Building production-grade classification and extraction for 15 Nigerian document types in-house would be the longest pole in the plan.
- **Recommendation: hybrid behind one IDP port.**
  1. **Vendor cloud IDP adapter** for tenants allowed to process offshore. The specific vendor is chosen in the TRD; data residency is enforced per G-30.
  2. **Self-hosted pipeline adapter** for on-prem and restricted tenants: open-source OCR and layout models in a separate **Python service**. This is a justified deviation from the PHP stack.
  3. **Bank-statement analysis built in-house** in all cases (FR-DOC-040 to FR-DOC-046). Nigerian bank statement layouts are a finite, template-able set; this is the highest-value case; and the analytics (affordability, tampering arithmetic) are deterministic rules, not ML.
- **Consequences.** NFR-003 on-prem needs GPU or high-CPU sizing guidance. Vendor models also need entries in the model inventory (FR-CMP-040), even though their versions are only partly visible to us.

### G-07 Repayment schedule ownership (BRD Q6) (Blocking)
- **Fact.** FR-OFR-003 (M) requires the offered schedule to match "the schedule that will be booked". The canonical capability "Schedule: generate or retrieve" (§9.2) may be unsupported by some cores (FR-CBA-003/004). The BRD specifies no day-count convention, rounding rule or holiday treatment (G-36).
- **Recommendation.**
  - Where the adapter supports `Schedule.generate` natively, **the CBA is authoritative**: the LOS calls the CBA to produce the offer schedule.
  - Otherwise the LOS computes it with a schedule engine parameterised to the tenant's CBA conventions.
  - **In both cases, booking compares the CBA-returned schedule with the accepted offer schedule.** Any variance beyond a configured tolerance blocks booking and raises an exception (proposed requirement LOS-FR-313, INFERRED). This turns a silent dispute risk into a visible control.

### G-08 No design-partner institution (BRD Q7) (High)
- **Fact.** None is named.
- **Recommendation.** Secure one PMB or commercial-bank design partner before Phase 3 (core workflow). Until then I'll configure a reference "Nigerian commercial bank" and a reference "PMB" tenant from public practice, labelled as such.

### G-09 Self-service in v1: Q8 contradicts FR-CHN-001 (Blocking)
- **Fact.**
  - §20 Q8 asks whether self-service is in v1.
  - FR-CHN-001 (**M**) already requires "customer self-service web, mobile, agent/DSA app".
  - FR-NTF-004 (M) requires an applicant tracking portal, and FR-OFR-005 (M) requires electronic acceptance.
  - §4.1 lists self-service in scope.
- **Recommendation.** **Include self-service in v1** as a responsive **web/PWA applicant portal**: apply, upload, track, accept the offer, e-sign. **No native iOS/Android apps in v1.** NFR-015 permits a PWA for field agents, and I read "mobile" in FR-CHN-001 as satisfied by a mobile-optimised PWA. The portal brings its own fraud and abuse surface (FR-CRD-017 device/IP signals, rate limiting, bot protection), which the TRD will cover.

### G-10 Mobile approval (BRD Q9) (Medium)
- **Fact.** FR-APV-011 is S.
- **Recommendation.** Make approval screens responsive. Step-up authentication (WebAuthn/passkey or TOTP) is required for every approval regardless of device (FR-SEC-013). No native app. This delivers FR-APV-011 with little extra cost.

### G-11 Credit policy ownership (BRD Q10) (Medium)
- **Fact.** FR-CRD-001 (M) requires rules that business users can maintain.
- **Recommendation.** The tenant's risk team owns the policy, and the vendor supplies baseline templates per institution type (FR-CFG-003). The rules editor therefore has to be usable by a credit risk analyst, not a developer. Authoring is maker-checker controlled (FR-TEN-009) and simulated before activation (FR-CRD-002).

---

## B. Contradictions and priority conflicts

### G-12 Priority conflicts between dependent requirements (High)
Each row below is a **Should** or **Could** item that one or more **Must** items depend on. Leaving them as S would make a Must unbuildable or untestable.

| Requirement (BRD priority) | Conflicts with | Recommendation |
|---|---|---|
| FR-CUS-010 related-party linkage (**S**) | FR-CRD-007 connected-group exposure (M), FR-CMP-023 insider/related-party limits (M), FR-CMP-024 insider flagging (M) | **Promote to M** |
| FR-CHN-008 partner API (**S**) | FR-CHN-001 includes "partner/embedded API" (M); FR-SEC-014 partner credentials (M) | **Split**: core partner API with scoped credentials = M; per-partner catalogue and rate-limit tiers = S |
| FR-CHN-005 lead management (**S**) | §4.1 "Lead capture and pre-qualification" in scope; FR-CHN-006 pre-qualification (M) | **Split**: lead capture and convert-to-application = M; assignment, loss-reason analytics = S |
| FR-CPR-006 conditions subsequent tracking (**S**) | FR-CPR-001 tracks CS (M); FR-HND-003 hands open CS to servicing (M) | **Promote to M** |
| FR-APV-004 committee workflow (**S**) | FR-APV-003 quorum approval (M); §6 persona "e-voting for committees" | **Promote to M** (see G-02) |
| FR-DSB-010 mandate setup (**S**) | FR-PRD-002 salary-backed/payroll products (M) | **Promote to M for salary-backed products** (see G-27) |
| FR-CHN-004 offline capture (**S**) | FR-CHN-001 "agent/DSA app" (M) | Keep S. The agent PWA is M online-only; offline is S (see G-39) |

### G-13 Fixed canonical states (§18) vs configurable workflow (FR-WFL-001) (High)
- **Fact.** §18 says tenants may choose and relabel states "but not create states outside this set". FR-WFL-001 (M) says stages, transitions and conditions are "defined per product without code".
- **Recommendation: two layers.**
  1. **Canonical status.** The fixed enumeration from §18, owned by the platform. Regulatory logic, reporting, SLA classes and the CBA saga key off this.
  2. **Workflow stage.** Configured per tenant and product. Each stage maps to exactly one canonical status, and transitions are validated so they can't jump canonical states illegally (for example, Documentation straight to Disbursing).
- Both requirements are then satisfied. The configuration validator (FR-CFG-002) rejects any workflow that breaks the canonical graph.

### G-14 Gaps and anomalies in the §18 lifecycle (High)
§18: Draft → Submitted → Pre-qualified → KYC/Screening → Documentation → Assessment → Recommended → Approval (n) → Approved/Declined/Counter-offered → Offer Issued → Accepted/Rejected/Expired → Conditions Precedent → Ready for Disbursement → Disbursing → Booked. The cross-cutting states are On Hold, Returned for Rework, Withdrawn, Cancelled, Expired and Failed (CBA).

| # | Issue | Recommendation |
|---|---|---|
| a | "Pre-qualified" comes *after* "Submitted", but FR-CHN-006 pre-qualification happens *without* an application | Two separate things. A **PreQualification** record (lead-level, indicative offer, no hard bureau pull) sits outside the state machine. The "Pre-qualified" state means automated eligibility screening of a submitted application. |
| b | "Expired" appears both as an offer outcome and as a cross-cutting state | One terminal **Expired** state with a mandatory `expiry_reason` (draft, approval validity, offer validity, hold timeout). |
| c | Tranched disbursement (FR-DSB-002/003) vs **Booked** as terminal | **Booked** = loan account created and first release posted. Later tranches run as a facility-level DisbursementInstruction sub-lifecycle on a Booked application, with their own CP checks, approval and maker-checker. |
| d | No path from customer rejection to renegotiation, although FR-OFR-008 routes back to credit | Add the transition Offer Issued/Rejected → Assessment (renegotiation), with the reason code mandatory. |
| e | **Declined** is terminal (FR-APP-006), but FR-CMP-044 grants a right to human review of automated declines | An automated decline enters **Declined (review window)**. A review request re-opens it to Assessment with a human decision-maker. Once the window lapses it becomes final. A human decline stays terminal. **Needs your confirmation (D-013).** |
| f | "Failed (CBA)" vs FR-DSB-006's "definite pending state" | Disbursing gets sub-states **Pending**, **Failed – retryable** and **Failed – intervention**. Failed (CBA) is never terminal; it always lands in the exception queue (PR-08). |
| g | "Approved with conditions" (FR-APV-009) is not a state | Approved + generated CP/CS items. There is no separate state. |
| h | Counter-offer (FR-CRD-011) vs offer flow | A counter-offer is an Offer with revised terms. Acceptance uses the same Offer flow (FR-OFR-005 to 009). |
| i | The cancellation/reversal window (FR-DSB-012) acts on a Booked application | Reversal produces a **Reversed** outcome on the facility. The application state stays Booked, with a reversal event. The alternative is a new canonical state "Reversed", which is a product change under §18. **Your call (D-013).** |

### G-15 Applications with multiple facilities and partial outcomes (High)
- **Fact.** §15 says "an application may carry multiple facilities". FR-APV-001 keys the approval matrix on amount and product, which are facility attributes. §18 has one state per application.
- **Gap.** Nothing covers one facility approved and another declined, or offer, acceptance and disbursement per facility.
- **Recommendation.**
  - Decisions, approval and offer terms are recorded **per facility**. The application's canonical state is the *least-advanced* of its live facilities.
  - The approval authority needed is driven by **total application exposure**, so splitting a credit into several small facilities can't drop it into a lower approval band. This is also an anti-circumvention control.
  - **Decision D-014.**

---

## C. Architecture vs your stated constraints

### G-16 API-first (PR-01) vs Inertia (Blocking)
- **Fact.** PR-01 says "Every capability available in the UI is available via API. The UI is a client of the same contracts." FR-SEC-014 requires the same permission model for API access, with no bypass. Your brief fixes Laravel + Inertia + React.
- **Conflict.** Inertia controllers return page props, not a public API. A literal reading of PR-01 would forbid Inertia.
- **Options.**
  - **(A)** Keep Inertia for the staff console. Both Inertia controllers and the versioned REST API become thin adapters over one **application service layer** (commands and queries) with one policy layer. A CI test enumerates every Inertia write action and fails if no REST equivalent exists. The applicant portal and partner API are REST-only.
  - **(B)** Drop Inertia and build a React SPA that consumes the REST API directly.
- **Recommendation: (A).** PR-01's intent is capability parity and one authorisation path, and (A) delivers that with team continuity and faster screen delivery. The parity test makes it enforceable rather than aspirational. PR-01 becomes "UI and API share the same application contracts", which I'd record as a documented interpretation, not a waiver.

### G-17 Laravel 11 is out of security support (Blocking)
- **Fact.** Your brief specifies Laravel 11. **[verify]** Laravel's published support policy gives each major 18 months of bug fixes and 2 years of security fixes. Laravel 11 (released March 2024) therefore lost security support around **March 2026**.
- **Why it matters.** Starting a regulated banking product on an unsupported framework is a finding any CBN examiner or bank IT risk team would raise, and one you'd raise yourself as an auditor.
- **Recommendation.** Use the **current supported Laravel major** (I expect 13; to be confirmed against the release schedule in TRD §3) on a supported PHP 8.x. Your existing CI/CD and patterns from ThirdLine carry over; the upgrade delta between 11 and current is small.

### G-18 MySQL vs PostgreSQL (Blocking)
- **Fact.** Your brief allows either. FR-TEN-001 forbids any cross-tenant query path, FR-TEN-002 requires dedicated schema or database per tenant, FR-APP-003 requires custom fields without migration, FR-AUD-003/004 require an append-only, tamper-evident audit trail, and FR-AUD-011 requires temporal reconstruction.
- **Recommendation: PostgreSQL.**
  - **Row-Level Security** gives a database-enforced second line behind application tenant scoping for FR-TEN-001. MySQL has no equivalent.
  - **Schema-per-tenant** is native, so the FR-TEN-002 option is simple.
  - **JSONB with GIN indexes** handles extension attributes that rules and reports can query (FR-APP-003).
  - Append-only enforcement by revoking UPDATE/DELETE on audit tables, plus triggers.
  - Declarative partitioning for the event and audit tables.
  - Transactional DDL makes migrations safer.
- **Cost.** If your existing CI/CD is MySQL-tuned, expect a small pipeline change.

### G-19 "Linux VPS" hosting cannot meet NFR-005/007/008/010 (Blocking)
- **Fact.** These NFRs require:
  - NFR-005: 99.9% monthly availability.
  - NFR-007: RPO ≤ 15 min, RTO ≤ 4 h.
  - NFR-008: no committed data loss on any single-node failure.
  - NFR-010: containerised, IaC-provisioned, zero-downtime rolling upgrades.
  - NFR-004: horizontal scaling.
- **Conflict.** A single VPS is a single point of failure. **NFR-008 specifically needs synchronous database replication to a second node.**
- **Minimum reference topology.**
  - At least 2 application nodes behind a load balancer.
  - PostgreSQL primary plus a synchronous standby with automated failover.
  - Redis with replication.
  - Replicated S3-compatible object storage with object lock.
  - A worker pool for queues.
  - Everything containerised, provisioned by IaC (Terraform/Ansible), and deployable to VPS hosts, a private cloud or a bank's VMware estate.
- **Recommendation.** "Linux VPS" stays valid as the *unit of hosting*, but the topology is a minimum three-node cluster. The TRD will give small, medium and large sizing.

### G-20 Adapter runtime: hot-swap (FR-CBA-018) and third-party SDK (FR-CBA-014) (High)
- **Fact.** FR-CBA-018 (S) requires adapter upgrades without core redeployment. FR-CBA-014 (S) wants an SDK so a bank's own IT team can build an adapter. Banks' integration teams in Nigeria are frequently Java or .NET shops.
- **Conflict.** In-process PHP packages can't be hot-swapped without a deployment.
- **Recommendation.**
  - The canonical contract is defined once in **OpenAPI/JSON Schema**.
  - Adapters may run **in-process** (PHP, the default for the vendor's own adapters) or **out-of-process** as separate containers speaking the canonical contract over HTTPS with mTLS. Out-of-process adapters satisfy FR-CBA-018 and let a bank write an adapter in any language (FR-CBA-014).
  - The certification kit (FR-CBA-013) tests both modes identically.

### G-21 Rules and workflow engines: build vs embed (High)
- **Fact.** FR-CRD-001/002 require business-authored decision tables, trees and expressions, simulated against history. FR-CRD-014 requires decisions to be reproducible *years later*. FR-WFL-001/002 require a configurable workflow with parallel branches and joins.
- **Options.**
  - Embed a BPMN/DMN engine such as Camunda, Flowable or Drools. That is a JVM component and a stack deviation.
  - Build constrained engines in PHP.
- **Recommendation: build both, deliberately constrained.**
  - **Rules:** a JSON decision model (tables, trees, expressions) evaluated by a sandboxed, side-effect-free expression evaluator. **The evaluator version is recorded on every decision**, so a decision can be replayed with the exact engine that made it. Replaying a years-old decision on an upgraded third-party engine is the usual way FR-CRD-014 fails.
  - **Workflow:** the two-layer state model from G-13, plus parallel task tracks with join conditions. It is *not* general BPMN. General BPMN is more power than the product needs and harder for an examiner to reason about.

### G-22 Audit immutability against DBAs (FR-AUD-003) and WORM (FR-AUD-013) (High)
- **Fact.** FR-AUD-003 says audit records are "non-editable and non-deletable by any user, including platform administrators and DBAs".
- **Position.** No system can *prevent* a database superuser from altering storage they administer. What can be guaranteed:
  1. **Prevention** for every application, API and ordinary database role: UPDATE/DELETE revoked, append-only triggers.
  2. **Detection** for privileged actors: hash-chaining (FR-AUD-004), with the chain head periodically anchored **outside the DBA's control**, in object-locked WORM storage and/or the tenant's SIEM (FR-AUD-014).
  3. **A verification routine** that proves integrity on demand and alerts on any break.
- **Recommendation.** Record this as the documented interpretation of FR-AUD-003. Its test case becomes "an alteration by a superuser is detected and alerted", which is what an examiner can actually rely on. For FR-AUD-013 on-prem, the tenant must provide an S3 Object Lock–compatible store; this becomes a deployment prerequisite.

---

## D. Regulatory (Nigeria reference pack)

### G-23 Jurisdiction pack is keyed by country, but Nigerian rules vary by licence type (High)
- **Fact.** FR-CMP-001 and FR-CMP-002 resolve the pack by jurisdiction or booking entity.
- **Gap.** Within Nigeria, prudential and conduct rules differ by **licence category**: commercial/merchant banks (CBN), PMBs (CBN), MFBs (CBN), finance companies (CBN), and digital/consumer lenders registered with the FCCPC. Single-obligor limits, insider-lending rules, reporting returns and disclosure rules all differ. **[verify specific limits]**
- **Recommendation.** Pack = **jurisdiction × licence category**, both resolved from the booking legal entity (FR-TEN-003). This is a small change to the data model and expensive to retrofit. Adopted unless you object.

### G-24 Nigerian regulatory instruments the BRD does not address (High)
The BRD names only NDPA 2023 (FR-CMP-030) and CRMS (FR-CMP-022). Each item below would be captured as a pack rule with a citation (FR-CMP-003). All are **[verify]** at TRD §9.

| Instrument | Why it matters to this LOS |
|---|---|
| **NDPC General Application and Implementation Directive (GAID) 2025** | Puts NDPA into operation: DPIA for high-risk processing (credit scoring qualifies), DPO, breach notification timelines, compliance audit returns. FR-CMP-030 as written ("comply with law") is not testable without it. |
| **FCCPC Digital, Electronic, Online or Non-Traditional Consumer Lending Regulations 2025** | Applies directly to your **fintech lender** segment: registration, mandated disclosures, limits on use of device and contact data, and conduct rules for recovery. The BRD is silent. |
| **CBN AML/CFT/CPF Regulations (2022)** | The BRD says AML/CFT only; proliferation financing (CPF) screening and record-keeping are also required. |
| **NFIU goAML** | FR-CMP-016 STR extracts should target the goAML reporting schema, not a generic extract. |
| **CBN Consumer Protection framework** | Disclosure, key-facts and cooling-off content for FR-CMP-025 and FR-OFR-002. |
| **Credit Reporting Act 2017 and CBN credit bureau guidelines** | Consent basis for bureau enquiry (FR-CMP-021) and who must report to which bureaus. |
| **CBN Risk-Based Cybersecurity Framework** | Security control baseline for bank tenants (log retention, privileged access, VAPT cadence). Feeds TRD §8. |
| **CBN Global Standing Instruction (GSI)** | See G-27. |
| **NDPA 2023, automated decision-making provision** | The legal basis for FR-CMP-044. |

**Decision D-022:** confirm these go into the Nigeria pack scope. FCCPC applies only if fintech lenders remain a target segment.

### G-25 CRMS and bureau reporting: LOS vs CBA/servicing boundary (High)
- **Fact.** FR-CMP-022 (M) asks the LOS to generate CRMS and bureau reporting extracts "in the prescribed format and cadence". §4.2 puts servicing out of scope.
- **Conflict.** Periodic CRMS and bureau returns report **performance of booked facilities** (balances, arrears, classification). That data lives in the CBA or servicing system, not the LOS.
- **Recommendation.** The LOS owns the **origination-time** obligations:
  - CRMS and bureau *enquiry* before approval (FR-CMP-020).
  - New-facility registration data captured complete and BVN-linked at booking.
  - Handover of a CRMS/bureau-ready facility record.
  
  Ongoing performance returns stay with the CBA/servicing side. FR-CMP-022 would be reworded accordingly. **Decision D-023.**

### G-26 E-signature not enforceable for some security instruments (High)
- **Fact.** AS-03 assumes e-signatures are enforceable. FR-OFR-005 already provides a wet-signature path.
- **[verify]** Nigerian law (Evidence Act 2011, Cybercrimes Act 2015) excludes certain instruments from electronic execution, notably dealings in land and powers of attorney. Mortgage deeds also need stamping and Governor's consent.
- **Recommendation.** The execution method is configured **per document template**:
  - Offer letters and facility agreements can be e-signed.
  - Legal mortgages, deeds of assignment and powers of attorney default to wet signature + upload + verification.
  - The pack ships these defaults.

### G-27 GSI and payroll mandates under-prioritised (High)
- **Fact.** FR-DSB-010 (mandate setup) is S. §16 lists payroll "employer verification, deduction mandate" (Medium) and payments "direct debit mandate" (High). FR-PRD-002 (M) includes salary-backed/payroll lending.
- **Gap.** Salary-backed lending in Nigeria depends on the deduction mechanism, such as Remita for government payroll or employer check-off. **[verify]** The CBN GSI framework also expects GSI consent to be captured in the loan agreement at origination. The BRD mentions neither.
- **Recommendation.**
  - Promote FR-DSB-010 to M for salary-backed products.
  - Add "employer and payroll verification" as a pre-decision check for those products (§16 payroll is stated; it has no FR).
  - Add GSI consent capture to the consent model (FR-CUS-008) and to the offer template.
  - **Decision D-024.**

### G-28 Fair-lending monitoring attributes vs NDPA sensitive data (Medium)
- **Fact.** FR-CMP-045 (S) monitors outcome distributions across "configured attributes" that are not used as decision inputs.
- **Risk.** Attributes such as religion, ethnicity or health are **sensitive personal data** under NDPA and need a specific lawful basis to collect at all.
- **Recommendation.** Monitoring defaults to non-sensitive proxies already collected for other reasons (gender, age band, state, channel). Any sensitive attribute requires an explicit DPIA record before the tenant can enable it.

### G-29 Retention clock trigger unknown to the LOS (Medium)
- **Fact.** FR-HND-004, FR-CMP-034 and FR-AUD-012 retain records "for the configured period".
- **[verify]** Nigerian AML record-keeping typically runs from the **end of the business relationship or transaction**, which is an event that happens in the CBA, not the LOS.
- **Recommendation.**
  - Retention is driven by a configurable trigger: application closed (declined, withdrawn or expired), or facility closed (an event received from the CBA/LMS adapter).
  - Where no closure event is available, a conservative fixed period applies from booking.
  - Legal hold overrides everything (FR-CMP-034).

### G-30 Data residency of third-party processors (High)
- **Fact.** FR-CMP-035 restricts residency "including backups, logs, and any third-party processing". FR-CMP-037 (S) keeps a register of processors.
- **Gap.** Vendor IDP, screening, e-signature and messaging adapters may process PII offshore.
- **Recommendation.**
  - Every adapter declares its **processing location(s)** in its capability manifest (FR-CBA-003).
  - Tenant configuration refuses to activate an adapter whose location breaks the tenant's residency rule.
  - Promote FR-CMP-037 to M, because the enforcement depends on the register.

---

## E. Under-specified functional areas

### G-31 Platform Operator capabilities have no FRs (High)
- **Fact.** §6 (persona: "tenant management, adapter registry, observability") and §12.3 ("no access to tenant business data without tenant-granted, logged, time-bound authorization") describe the Platform Operator. No FR covers them.
- **Recommendation.** Inventory them as stated-but-unnumbered requirements (LOS-FR-274 to 277) and build a **separate operator console** that holds no tenant business data by default.

### G-32 Authentication for tenants without an IdP, and for applicants (High)
- **Fact.** FR-SEC-012 assumes a tenant IdP (SAML/OIDC/SCIM). Many PMBs and fintechs run none. Applicant authentication for self-service is not specified at all.
- **Recommendation.**
  - A built-in identity store with MFA, password policy and lockout for tenants without an IdP (LOS-FR-302, INFERRED).
  - Applicant authentication by phone or email OTP, with optional BVN-linked phone match (LOS-FR-303, INFERRED).

### G-33 Integration list in your brief vs BRD §16 (Medium)
- **In your brief, not in the BRD:**
  - **WhatsApp** notifications.
  - **LDAP**, which is relevant to on-prem banks running Active Directory without ADFS.
- **In the BRD, missing from your brief:**
  - Company registry (CAC) and tax authority (FIRS).
  - Payroll and valuation (valuer instruction and report retrieval).
  - Collateral registry (National Collateral Registry for movables **[verify]**).
  - Insurance (quote, issue, verify).
  - Data platform and SIEM.
  - Push notifications.
- **Recommendation.** Add WhatsApp as a notification channel adapter and LDAP as an identity adapter (LOS-FR-304/305, INFERRED). Both are cheap under the adapter pattern. All §16 integrations go into the Integration Register at Step 2. **Decision D-025.**

### G-34 Mortgage collateral perfection and CP vs CS (High)
- **Fact.** FR-COL-004 lists perfection steps as "search, stamping, registration, charge filing". FR-COL-008 blocks disbursement until perfection is complete, unless waived.
- **Gap.** Nigerian real-estate perfection (search, Governor's consent, stamping, registration at the state Lands Registry) can take months. **[verify]** Common PMB practice is to disburse against an executed deed and make consent or registration a **condition subsequent**. As written, FR-COL-008 would generate a waiver on almost every mortgage. That inflates the exception portfolio (FR-CRD-015) and makes the waiver report meaningless.
- **Recommendation.**
  - Perfection steps are a configurable checklist per collateral type and per state.
  - Each product declares which steps are CP and which are CS.
  - A CS still blocks *nothing* at disbursement but is tracked and escalated (FR-CPR-006, promoted to M in G-12).

### G-35 Prudential parameters and insider register not specified (High)
- **Fact.** FR-CMP-023 enforces single-obligor and insider limits, and FR-CMP-024 flags insiders automatically.
- **Gap.** Neither says where the limit base comes from (shareholders' funds or capital base, effective-dated), nor where the **insider/related-party register** (directors, significant shareholders, staff and their related parties) comes from.
- **Recommendation.**
  - Effective-dated prudential parameters per legal entity, maintained under maker-checker (LOS-FR-307).
  - An insider register, maintained manually or fed from HR/CBA (LOS-FR-306).
  - Both INFERRED and needed for the Musts to be testable.

### G-36 Fees, taxes, day-count and rounding (High)
- **Fact.** FR-PRD-003 and FR-DSB-004 specify fees and deductions only generically.
- **Gap.** Nothing covers:
  - VAT on fees.
  - Stamp duty on loan instruments.
  - Credit-life insurance premiums (partly FR-PRD-010, which is C).
  - Day-count convention (Actual/365 vs 30/360).
  - Rounding.
  - Holiday roll rules.
- These determine whether FR-OFR-003 ("schedule matches booked") can pass.
- **Recommendation.** Product configuration gets explicit day-count, rounding and holiday-roll settings that default from the CBA adapter's declared conventions. The pack supplies tax components. Tied to G-07.

### G-37 Behavioural scorecards and simulation history (Medium)
- **Fact.** FR-CRD-005 requires behavioural scorecards. FR-CRD-002 requires simulation "against historical applications".
- **Gaps.**
  - Behavioural scoring needs account behaviour from the CBA. The §9.2 statement and arrears operations can supply it, but only through capable adapters.
  - A new tenant has no LOS history.
- **Recommendation.**
  - Behavioural features are derived through the same statement-analysis engine (FR-DOC-040).
  - Add a **historical application import** in a canonical CSV format so simulation works from day one (LOS-FR-308, INFERRED).

### G-38 Bulk upload, scanner and email ingestion formats (Medium)
- **Fact.** FR-CHN-001 and FR-DOC-001 require these channels and define no formats.
- **Recommendation.**
  - **Bulk upload:** a canonical CSV/XLSX template per product (LOS-FR-312, INFERRED). Typical use is employer-batch salary loans.
  - **Scanners:** scan-to-email or a watched SFTP drop. No browser TWAIN plug-in.
  - **Email ingestion:** an IMAP/Graph adapter with sender allow-listing and matching of attachments to applications.

### G-39 Offline agent capture and device security (Medium)
- **Fact.** FR-CHN-004 (S) offline capture.
- **Risk.** PII stored on field devices.
- **Recommendation.**
  - The PWA stores offline drafts encrypted (WebCrypto, key released on login).
  - Offline mode is limited to capture: no decision data and no customer 360.
  - Drafts expire locally after a configurable time.
  - Sync is conflict-safe, with server-side deduplication (FR-CHN-007).

### G-40 Partner/DSA commission (Low)
- **Fact.** FR-CHN-002 records attribution "for commission". No FR covers commission calculation or payout.
- **Recommendation.** v1 captures attribution and reports on it. Calculation and payout are out of scope.

### G-41 FX rate source (Low)
- **Fact.** FR-TEN-006/007 require FX rates; no source is named.
- **Recommendation.** Manual maintenance under maker-checker, plus an optional rate-feed adapter.

---

## F. Testability

### G-42 NFRs not testable as written (High)
- **Fact.**
  - NFR-001 refers to "expected peak load" and NFR-004 says volumes are "set per tenant at discovery". Neither is defined.
  - OB-06's target is TBD.
  - FR-CMP-030 says "comply with applicable data protection law".
  - FR-TEN-001 says "no query path may return cross-tenant data".
- **Recommendation.** Adopt a **reference load profile** for certification, per tenant: 300 concurrent staff users, 2,000 applications submitted per day with 20% arriving in the peak hour, 30 documents uploaded per minute at peak, and 20 tenants on a SaaS cluster. These numbers are INFERRED and need your confirmation. Then:
  - FR-CMP-030 is decomposed into FR-CMP-031 to 037 plus the GAID controls (G-24).
  - FR-TEN-001 is tested through RLS, an architectural test, and an automated cross-tenant penetration suite.
  - **Decision D-026.**

### G-43 OB-01 needs field-level data provenance (Medium)
- **Fact.** OB-01 targets ≥90% of data captured without manual keying.
- **Gap.** That can't be measured unless every field records **how** it was populated: CBA/API, extraction, applicant self-service, or staff keying.
- **Recommendation.** Record provenance per field on every application (LOS-FR-301, INFERRED). It also strengthens FR-DOC-053 lineage.

### G-44 Editorial defects in the BRD (Low)
- The objectives table skips **OB-03**.
- §17's layers are numbered 5–11, but the text refers to "layers 1–6", and FR-CFG-001 to "layers 1–4".
- **I assume** the layers are 1 to 7 in the listed order: 1 Reference data, 2 Declarative config, 3 Rules, 4 Extension attributes, 5 Adapters, 6 Extension points, 7 Code fork (prohibited). FR-CFG-001 therefore covers reference data, declarative config, rules and extension attributes.
- The document owner and approvers are TBD.

---

## G. Design

### G-45 Three conflicting design systems (Blocking for Step 5)
There are three design sources in the folder, plus a fourth underneath one of them:

| Source | What it is | Fit |
|---|---|---|
| `design_handoff_fundly_los/` **v3** | A high-fidelity design for *this* LOS. Notion-like surface over the Modernist system, Archivo type, accent `#2383e2`, hairline rules, 5–6px radii. Covers 8 screens: pipeline, workspace, document review, credit, approval packet, disbursement, products/rules, and audit (stub). | **Domain fit is excellent.** It is fixed at 1440px and explicitly "no responsive behaviour designed", which conflicts with NFR-015. It also has contrast failures (G-46). |
| `AuditPro_GRC_Design_System.md` | The Atheris house system for Laravel + Inertia + React + Tailwind. Navy `#1A365D`, gold `#D4AF37`, Inter, a named React component library (PageHeader, DataTable, FilterBar, StatusBadge…). | **Stack and component fit is excellent**, and it's consistent with your other products. It is GRC-styled, not designed for LOS workflows. |
| `design-system.md` | A 20-line palette stub of AuditPro for an "IT Risk Mgt" product, with a hard-coded user "Adaeze Kunle Usman – Auditor". | Belongs to another product. **I recommend ignoring it.** |
| Modernist (`_ds/`) | Sits underneath v3: zero radius, red accent. v3 explicitly overrides it. | Reference only. |

**Options.**
- **(A)** Fundly v3 visual language, built on the AuditPro component architecture and naming. Tokens are remapped, contrast fixed, and responsive rules added.
- **(B)** AuditPro as it is, giving one look across the Atheris suite.
- **(C)** Fundly v3 as it is.

**Recommendation: (A).** v3 has already solved the hard LOS-specific patterns: side-peek document review with linked source regions, the decision packet, and the disbursement posting states. AuditPro gives the frontend agent a real component contract. **Decision D-027.**

### G-46 Accessibility target and contrast failures (High)
- **Fact.** NFR-014 sets **WCAG 2.2 AA**. Your brief says 2.1 AA. 2.2 is a superset, so **the BRD governs** (2.2 adds focus-not-obscured, target size, accessible authentication and redundant entry).
- **Measured contrast** (normal text needs 4.5:1):

| Token | Pair | Ratio | Result |
|---|---|---|---|
| Fundly v3 accent `#2383e2` | White text on primary button | 3.88:1 | Fail |
| Fundly v3 muted `#9b9a97` | Eyebrow labels on white | 2.81:1 | Fail |
| Fundly v3 tertiary `#787774` | On white | 4.48:1 | Fail, marginally |
| Fundly v3 faint `#b3b1ad` | On white | 2.14:1 | Fail |
| AuditPro secondary text `#718096` | On white | 4.02:1 | Fail |
| AuditPro secondary text `#718096` | On page background | 3.83:1 | Fail |
| AuditPro gold `#D4AF37` | On white | 2.1:1 | Fail (fine as a decorative indicator, never as text) |

- **Recommendation.** The Step 5 brief will specify corrected token values. Contrast checks become an automated CI gate (axe) on every screen.

### G-47 Product name (Low)
- **Fact.** The BRD has no product name. The design handoff calls it "Fundly", for "Institution A".
- **Recommendation.** Confirm "Fundly" as the working name. Also confirm whether it sits under the Atheris brand alongside ThirdLine and NexusRisk.

---

## H. Assumptions

These are working assumptions. Each one holds unless you overturn it at sign-off.

| ID | Assumption | Basis | Impact if wrong |
|---|---|---|---|
| A-01 | BRD v0.1 plus this register plus your decisions = scope baseline v1.0 | G-01 | Re-baseline and re-trace |
| A-02 | Target tenant types: commercial banks, PMBs and FCCPC-registered fintech lenders. MFBs and finance companies are not targeted in v1 but not precluded. | Your brief | More licence-category packs (G-23) |
| A-03 | All NFR-001 to NFR-020 are **Must**; the BRD gives NFRs no priority | §14 has no priority column | Re-prioritise in the plan |
| A-04 | Statements in §6, §9.2, §12.3, §16, §17 and §18 are binding requirements even without FR IDs. They are given LOS IDs with source "BRD-§x". | §7: "Principles … are binding" | Removed from the inventory |
| A-05 | §4.2 (out of scope) and §4.3 (deferred) are excluded. The design will not *preclude* them (for example, the product model leaves room for Islamic structures). | §4 | — |
| A-06 | Stack: Laravel (current supported major, not 11; G-17), Inertia + React for staff, REST for applicants and partners, PostgreSQL (G-18). A Python service is added for IDP (G-06). | Your brief + G-06/16/17/18 | TRD rework |
| A-07 | One codebase and one artefact for SaaS, private cloud and on-prem | PR-10, NFR-009 | — |
| A-08 | Applicant and agent channels are a responsive web PWA. No native apps in v1. | G-09, NFR-015 | Native app workstream added |
| A-09 | English at launch. The i18n framework is built in (FR-TEN-008), with other languages added as resource packs. | FR-TEN-008 is S | — |
| A-10 | Base currency NGN; multi-currency supported per FR-TEN-007 | FR-TEN-007 | — |
| A-11 | Tenant = one contracting institution. A group with several licences = several legal entities inside one tenant (FR-TEN-003). | FR-TEN-003, G-23 | Tenancy model change |
| A-12 | Reference load profile in G-42 | G-42 | NFR test targets change |
| A-13 | The Fundly v3 handoff is the intended UI direction, subject to D-027 | Handoff README "build this one" | Design brief rework |
| A-14 | "Fundly" is the working product name | G-47 | Cosmetic |
| A-15 | The BRD cites no regulation by section. Every regulatory rule in the Nigeria pack will carry a primary-source citation, verified in TRD §9 (FR-CMP-003). | FR-CMP-003 | — |
| A-16 | Requirement IDs: **LOS-xx IDs are the traceability keys**, and each one carries its BRD ID unchanged. BRD IDs are never renumbered. | Your brief + §21 | — |
| A-17 | The single requirement "comply with OWASP Top 10" (FR-SEC-019) is raised to **OWASP ASVS 4.x Level 2**, with Level 3 for authentication, session and cryptography, as your brief asks. This is a recommendation, not a BRD fact. | Your brief | Lower security baseline |

---

## I. Explicit exclusions (from BRD §4.2 and §4.3; not built in v1)

- Loan servicing, repayment processing and ledger.
- Collections and recovery workflow (integration point only).
- GL and accounting entries.
- Customer internet and mobile banking.
- Treasury, funds pricing and ALM.
- Credit bureau *provision* of data.
- Debt restructuring and workout.
- Syndicated and participation lending.
- Trade finance.
- Islamic finance structures.
- Dealer and floor-plan financing.
- Embedded-finance white-label journeys.
- Product bundles and cross-sell (FR-PRD-010 is C).
- Champion/challenger (FR-CRD-016 is C).
- Multi-language document extraction (FR-DOC-030 is C).

---

## J. Re-baseline addendum (2026-10-08, after PO decisions D-030 to D-036)

**Closed or changed by PO direction:**
- **G-02** is resolved by D-032: commercial banks; retail, SME and corporate.
- **G-05** is resolved by D-030: on-prem buy-and-deploy.
- **G-16** is resolved by D-031: pure API-first SPA.
- **G-17** is resolved: Laravel 13.
- **G-18** is resolved: PostgreSQL.
- **G-19** is reframed by D-030: client-hosted. See G-50.
- **G-23** is reduced to Nigeria × commercial bank.
- **G-24**: FCCPC is removed.
- **G-34** is closed: no PMB mortgage depth.

| ID | Title | Severity | Status |
|---|---|---|---|
| G-48 | LicensingServer API contract unknown | High | Open: need the contract or repo |
| G-49 | Per-client branch practice (ThirdLine) conflicts with BRD §17 / PR-03 | High | Recommendation D-035 |
| G-50 | NFR ownership split on-prem | Medium | Open |
| G-51 | SPA session security model | Medium | Resolved in TRD §8 |
| G-52 | Air-gapped update channel | Medium | Resolved in TRD §2.5 |

### G-48 LicensingServer API contract unknown (High)
- **Fact.** D-030 requires the installation to be managed through a licence server, and Atheris already runs a LicensingServer for ThirdLine.
- **Gap.** I don't have its API (activation, check-in, revocation, licence payload format). I also know there were activation failures on ThirdLine.
- **Recommendation.** The LOS depends only on a `LicensingPort`. The first adapter verifies an offline Ed25519-signed licence file, which needs no server. The LicensingServer adapter is built once you share the contract.

### G-49 Per-client branch practice conflicts with BRD §17 / PR-03 (High)
- **Fact.** BRD §17 layer 7 says "Code fork — not permitted". PR-03 says tenant differences are data.
- **Conflict.** Running a branch per client is a code fork.
- **Recommendation.** D-035: one mainline, signed extension modules for genuine client-specific logic, and semver releases pinned per installation.

### G-50 NFR ownership split on-prem (Medium)
- **Fact.** NFR-005 (99.9%), NFR-007 (RPO/RTO) and NFR-008 (no data loss) depend on infrastructure the bank now owns.
- **Recommendation.**
  - The vendor certifies the **HA reference profile** (≥2 app nodes, PostgreSQL with synchronous standby, replicated object storage) and the DR runbook.
  - The client contract and installation qualification record whether the bank's deployment meets that profile.
  - A single-node profile is supported for UAT and pilot only, and is labelled as not meeting NFR-005/008.

### G-51 SPA session security model (Medium)
- **Fact.** D-031 moves the staff UI to a pure SPA. The SPA must not hold bearer tokens in browser storage.
- **Recommendation.** Laravel Sanctum stateful SPA authentication: same-origin, HttpOnly + Secure + SameSite=Strict session cookie, CSRF token, idle and absolute timeouts server-side (FR-SEC-015), and step-up via WebAuthn/TOTP. Bearer tokens are only for partners and service accounts.

### G-52 Air-gapped update channel (Medium)
- **Fact.** BRD §5 mentions air-gapped tenants, and on-prem makes them likely.
- **Recommendation.** Signed offline bundles for: container images and migrations (release), licence files, sanctions/PEP list snapshots, and jurisdiction pack updates. Each is verified against the vendor's public key before import, and every import is audit-logged.
