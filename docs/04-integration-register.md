# 04 — Integration Register

| | |
|---|---|
| **Version** | 1.0, 8 October 2026 |
| **Governing requirements** | FR-CBA-001..020 (pattern applies to *every* integration, FR-CBA-020), BRD §16, TRD §11 |
| **Rule** | No vendor is hard-wired. Each integration point has a **port** (interface + canonical DTOs), a **mock/stub adapter**, and zero or more **live adapters**. The active adapter is bound per tenant/legal entity by configuration under maker-checker. |

**Status values** (per adapter):
1. **Contract defined.** Port interface and canonical payloads are specified here.
2. **Stub ready.** A mock adapter is implemented and the system runs end to end against it.
3. **Live adapter pending.** A live adapter is planned, awaiting sandbox, credentials or contract.
4. **Live: certified.** The certification kit passes against the vendor sandbox.

As of this version, nothing is implemented. The **Target status at MVP** column shows what the MVP release will deliver.

---

## 1. Register

| # | Integration point | Port | Canonical operations | Live adapters (candidates) | Style | Criticality (BRD §16) | Status now | Target status at MVP | Phase for live |
|---|---|---|---|---|---|---|---|---|---|
| I-01 | Core banking | `CoreBankingPort` (10 sub-ports, §2) | See §2 | **Finacle** (first, D-004), **Fineract/Mifos-class** (second), Flexcube, T24 (Temenos Transact), BankOne; **Generic file + manual queue** (FR-CBA-006) | sync / async / batch / MQ | Critical | Contract defined | **Stub ready** (CBA simulator with failure injection) | P4 (Finacle), P6 (others, per client) |
| I-02 | Identity: BVN | `IdentityVerificationPort` | `verifyBvn(bvn, name, dob, phone?)`, `getBvnDetails` (incl. photo where entitled) | NIBSS BVN service (directly or through a licensed aggregator) | sync | Critical | Contract defined | Stub ready | P4 |
| I-03 | Identity: NIN | `IdentityVerificationPort` | `verifyNin(nin, name, dob)` | NIMC verification service / licensed aggregator | sync | Critical | Contract defined | Stub ready | P4 |
| I-04 | Liveness / selfie match | `BiometricMatchPort` | `matchFace(selfie, referencePhoto) → score` | Provider TBD; self-hosted option for on-prem | sync | Should (FR-CUS-004) | Contract defined | — | P4 |
| I-05 | Credit bureaus | `CreditBureauPort` | `pullConsumerReport`, `pullCommercialReport`, `submitAccounts` (file) → canonical credit profile (FR-CRD-004) | CRC Credit Bureau, FirstCentral, CreditRegistry; fallback order configurable | sync + outbound file | Critical | Contract defined | Stub ready (one bureau) | P4 |
| I-06 | CBN CRMS | `RegulatoryReportingPort` | `crmsEnquiry(bvn/rc)`, `buildFacilityRecord` (file per CBN format) | CBN CRMS (portal / file) | batch file + manual upload | Critical (FR-CMP-022 as reworded, D-023) | Contract defined | — | P5 |
| I-07 | Sanctions / PEP / adverse media | `ScreeningPort` | `screenParty(party, lists[]) → matches[]`, `listVersion()`, `rescreenBatch` | Provider TBD (licensed list vendor); self-hosted list engine with offline snapshots for air-gapped sites (G-52) | sync + batch | Critical | Contract defined | Stub ready | P4 |
| I-08 | Company registry | `CompanyRegistryPort` | `lookupCompany(rc)`, `getDirectors`, `getShareholders`, `getStatus` | CAC public search API / licensed aggregator | sync | High | Contract defined | Stub ready | P4 |
| I-09 | Tax authority | `TaxVerificationPort` | `verifyTcc(tin, tccNo)` | FIRS TCC verification | sync | Medium | Contract defined | — | P4 |
| I-10 | Payments: name enquiry | `PaymentsPort` | `nameEnquiry(bankCode, accountNo)` (FR-CPR-005) | NIBSS NIP (direct / via CBA) | sync | High | Contract defined | Stub ready | P4 |
| I-11 | Payments: transfer to third party | `PaymentsPort` | `transfer(...)` for vendor/dealer payouts not posted by the CBA (FR-DSB-002) | NIBSS NIP via CBA (preferred) | sync/async | High | Contract defined | Stub ready | P4 |
| I-12 | E-mandate / direct debit | `MandatePort` | `createMandate`, `cancelMandate`, `mandateStatus` | NIBSS e-mandate (NIBSS Direct Debit), Remita | async | High | Contract defined | — | P4 |
| I-13 | Payroll / deduction | `PayrollPort` | `verifyEmployer`, `verifySalary`, `createDeductionMandate` (LOS-FR-287, LOS-FR-315) | Remita (public-sector/IPPIS), employer check-off file | async + file | Medium (M for salary-backed, D-024) | Contract defined | — | P4 |
| I-14 | GSI | `MandatePort` (GSI subtype) | `registerGsiConsent` [verify operational interface] | Via NIBSS / bank process | batch / manual | M (LOS-FR-311) | Contract defined | — | P4 |
| I-15 | E-signature | `ESignaturePort` | `createEnvelope(docs, signers[ordered])`, `envelopeStatus`, `downloadSigned` (+certificate, audit trail) | Provider TBD; must support on-prem or in-country processing (FR-CMP-035) | async callback | High | Contract defined | Stub ready | P4 |
| I-16 | Document OCR / IDP | `DocumentIntelligencePort` | `classify`, `split`, `extract(docType)`, `analyseStatement` → canonical schemas with field confidence + source regions | **Self-hosted Fundly IDP service** (primary, D-006); optional vendor cloud IDP per client | async | M (FR-DOC-054) | Contract defined | **Manual-verification adapter** (human keys or confirms fields) | P2 |
| I-17 | Open banking / statement API | `StatementSourcePort` | `fetchStatement(account, period)` | CBA statement (via I-01), open-banking aggregators | sync | Should | Contract defined | — | P2 |
| I-18 | Valuation | `ValuationPort` | `instructValuer`, `getReport` (LOS-FR-285) | Bank's valuer panel portal / email + manual | async / manual | Medium | Contract defined | — | P4 |
| I-19 | Collateral registry | `CollateralRegistryPort` | `search`, `registerCharge`, `release` (FR-COL-007) | National Collateral Registry (movables) [verify]; Lands registries: manual queue | async / manual | Medium | Contract defined | — | P4 |
| I-20 | Insurance | `InsurancePort` | `quote`, `issuePolicy`, `verifyPolicy` (LOS-FR-286) | Bank's partner insurers | async | Medium | Contract defined | — | P4 |
| I-21 | Email | `EmailPort` | `send(message)`, delivery status | SMTP relay (bank), Microsoft Graph | async | High | Contract defined | **Stub ready + SMTP live** (SMTP is generic, no vendor lock) | MVP |
| I-22 | SMS | `SmsPort` | `send`, `status` | Bank SMS gateway / aggregators | async | High | Contract defined | Stub ready | P4 |
| I-23 | WhatsApp | `WhatsAppPort` | `sendTemplate`, `status` (LOS-FR-304) | WhatsApp Business Platform via BSP | async | Medium | Contract defined | — | P4 |
| I-24 | Push / in-app | `PushPort` | `notify(user, payload)` | Built-in in-app inbox [MVP]; Web Push (VAPID) for PWA | async | High | Contract defined | Live (in-app, built-in) | MVP / P4 |
| I-25 | Webhooks (outbound) | `WebhookPort` | `deliver(event)` signed HMAC | Built-in | async | High | Contract defined | Live (built-in) | MVP |
| I-26 | Identity provider (staff) | `DirectoryPort` / `SsoPort` | LDAP bind + group lookup; SAML 2.0; OIDC; SCIM 2.0 provisioning | Active Directory / LDAP (LOS-FR-305), ADFS/Entra ID/Keycloak (SAML/OIDC) | sync | High | Contract defined | **LDAP live** (generic protocol) + local IdP | P4 (SAML/OIDC/SCIM) |
| I-27 | SIEM | `AuditStreamPort` | `stream(auditEvent)` in a standard format (CEF / JSON over syslog-TLS) | Splunk, QRadar, Elastic (protocol-generic) | async | Medium | Contract defined | — | P5 |
| I-28 | Data platform / BI | `DataExportPort` | Incremental extracts (Parquet/CSV) or CDC event feed (FR-RPT-009) | Bank DWH / lake (generic) | batch / stream | Medium | Contract defined | — | P5 |
| I-29 | Records / DMS handover | `RecordsPort` | `transferDocuments(package)` or durable references (FR-HND-002) | Bank DMS (generic CMIS / file drop) | batch | M | Contract defined | — | P4 |
| I-30 | Servicing / LMS handover | `ServicingHandoverPort` | Publish `facility.created` + package (FR-HND-001/003) | Bank LMS / collections (webhook, MQ, file) | async | M | Contract defined | Live (webhook + event table) | MVP |
| I-31 | Licence server | `LicensingPort` | `currentLicence`, `checkIn`, `activate` (LOS-FR-316) | Offline signed file [MVP]; Atheris LicensingServer (G-48) | sync (optional) | M | Contract defined | **Live (offline signed file)** | P1 (server adapter once contract received) |
| I-32 | Key management | `KeyManagementPort` | `wrap`, `unwrap`, `rotate` | Local keyfile [MVP], HashiCorp Vault Transit, PKCS#11 HSM | sync | M (FR-SEC-016) | Contract defined | Live (local keyfile) | P6 |
| I-33 | Malware scanning | `MalwareScanPort` | `scan(stream) → clean/infected` | ClamAV (bundled) | sync | M | Contract defined | Live (ClamAV) | MVP |
| I-34 | PDF rendering | `PdfRenderPort` | `render(html, options)` | Gotenberg (bundled) | sync | M | Contract defined | Live (Gotenberg) | MVP |

---

## 2. Canonical Core Banking contract (`CoreBankingPort`, FR-CBA-001, BRD §9.2)

The contract is versioned as **CBI v1.0**. Evolution rules:
- **Additive changes** (new optional fields or operations) are minor versions.
- **Removing or changing semantics** requires a new major version, with both majors supported in parallel for ≥ 12 months.
- **Adapters declare the CBI version they implement** in their manifest.

### 2.1 Operations

| Sub-port | Operation | Request (canonical) | Response (canonical) | State-changing? | Default timeout | Retry |
|---|---|---|---|---|---|---|
| Customer | `searchCustomers` | `{bvn?, nin?, rcNumber?, accountNo?, phone?, name?}` | `CustomerSummary[]` | No | 10s | 2× on retryable |
| Customer | `getCustomer` | `{cbaCustomerId}` | `Customer` (identity, contacts, addresses, segment, KYC tier, relationships) | No | 10s | 2× |
| Customer | `createCustomer` | `CustomerCreate` + `idempotencyKey` + `losPartyId` | `{cbaCustomerId}` | **Yes** | 30s | Lookup-before-retry on `losPartyId` |
| Customer | `updateCustomer` | `{cbaCustomerId, changes}` + key | `{version}` | Yes | 30s | Lookup-before-retry |
| Customer | `getRelationships` | `{cbaCustomerId}` | `Relationship[]` | No | 10s | 2× |
| Accounts | `listAccounts` | `{cbaCustomerId}` | `Account[]` | No | 10s | 2× |
| Accounts | `getBalance` | `{accountNo}` | `{ledger, available, currency, asOf}` | No | 5s | 1× |
| Accounts | `verifyAccountStatus` | `{accountNo}` | `{status: active/dormant/closed/frozen, ownerName}` | No | 5s | 1× |
| Accounts | `nameEnquiry` | `{bankCode, accountNo}` | `{accountName, matchScore?}` | No | 10s | 1× |
| Exposure | `getExposure` | `{cbaCustomerId}` + connected ids | `Facility[]` (outstanding, limit, arrears, classification, DPD history) | No | 15s | 2× |
| Loan account | `createLoanAccount` | `LoanAccountCreate` (mapped product code, terms, schedule basis, branch, GL mapping, `losFacilityId`) + key | `{loanAccountNo, cbaReference}` | **Yes** | 60s | **Lookup-before-retry** on `losFacilityId` |
| Loan account | `getLoanAccount` | `{loanAccountNo}` | `LoanAccount` | No | 10s | 2× |
| Loan account | `amendTerms` / `closeAccount` | + key | `{status}` | Yes | 60s | Lookup-before-retry |
| Postings | `disburse` | `{loanAccountNo, amount, destination{internal account / NIP beneficiary}, narration, valueDate}` + key | `{postingRef, status: posted/pending/rejected}` | **Yes** | 60s | **Never blind-retry.** On timeout → `pending_cba` → `getPostingByReference(key)` |
| Postings | `postCharges` | `{loanAccountNo, items[{code, amount, taxCode}]}` + key | `{postingRefs[]}` | Yes | 60s | Lookup-before-retry |
| Postings | `reversePosting` | `{postingRef, reason}` + key | `{reversalRef}` | Yes | 60s | Lookup-before-retry |
| Postings | `getPostingByReference` | `{idempotencyKey or losReference}` | `Posting?` | No | 10s | 3× |
| Schedule | `generateSchedule` | `{productCode, principal, rate, tenor, frequency, moratorium, startDate, dayCount}` | `Instalment[]` | No | 15s | 2× |
| Schedule | `getSchedule` | `{loanAccountNo}` | `Instalment[]` | No | 15s | 2× |
| Collateral | `registerCollateral` / `updateCollateral` / `releaseCollateral` | `CollateralRecord` + key | `{cbaCollateralId}` | Yes | 30s | Lookup-before-retry |
| Mandates | `createStandingInstruction` / `cancelStandingInstruction` | `{fromAccount, toLoanAccount, schedule}` + key | `{siReference}` | Yes | 30s | Lookup-before-retry |
| Reference | `getProducts`, `getBranches`, `getGlCodes`, `getCurrencies`, `getRates` | — | lists | No | 30s | 2× (cached, TTL per type) |
| Reconciliation | `getPostings(period)`, `getAccountStatement(account, period)` | `{from, to, filters}` | `Posting[]` / `StatementLine[]` | No | 120s | 2× |

### 2.2 Error taxonomy (FR-CBA-011)

| Canonical class | Meaning | Runtime behaviour |
|---|---|---|
| `retryable` | Transient: network, 5xx, timeout *before send confirmed*, lock contention | Backoff + jitter up to max attempts; then `requires_intervention` |
| `non_retryable` | Malformed request or mapping error | Exception queue (configuration fix); no retry |
| `requires_intervention` | Unknown outcome (timeout after send), partial failure, or CBA period closed (e.g. `GL_PERIOD_CLOSED`) | Park in a definite pending/failed state; operator task with safe actions (retry same key / reconcile / compensate) |
| `business_rejection` | The CBA refused the business operation (insufficient funds in disbursement GL, account frozen, product inactive) | Surface to the business user with a canonical reason; workflow decides (return to stage, hold) |

Canonical error codes have the form `CBA.<DOMAIN>.<REASON>`, e.g. `CBA.POSTING.PERIOD_CLOSED` or `CBA.CUSTOMER.DUPLICATE`. Each adapter ships a mapping table from native codes and messages to canonical codes. **An unmapped native code is mapped to `requires_intervention`, never to success.**

### 2.3 Idempotency and exactly-once disbursement (FR-DSB-005, FR-CBA-007)
1. The key is generated when the disbursement instruction is created: `{tenant}:disburse:{facilityId}:{trancheNo}`. It is stored with the instruction and is **never regenerated on retry**.
2. If the CBA supports idempotency keys, the adapter passes the key through.
3. If not, the adapter writes the key into a CBA-visible reference field (narration / external reference). Before any re-send it calls `getPostingByReference`, and re-sends only if no posting exists.
4. A unique constraint on `(tenant_id, idempotency_key)` in `posting_attempts` plus the outbox's at-least-once delivery means a duplicate *instruction* cannot be created and a duplicate *send* finds the earlier result.
5. Daily reconciliation is the backstop: any posting without a matching instruction is a break (FR-DSB-008).

### 2.4 Capability manifest example

```json
{
  "adapter": "finacle", "version": "1.0.0", "cbi": "1.0",
  "processing_location": "on_prem:client_dc",
  "operations": {
    "customer.create": {"support": "native"},
    "schedule.generate": {"support": "native"},
    "postings.disburse": {"support": "native", "idempotency": "reference_lookup"},
    "mandates.createStandingInstruction": {"support": "unsupported", "substitute": "manual_task"},
    "collateral.register": {"support": "emulated", "substitute": "batch_file"}
  }
}
```

The Finacle manifest above is illustrative. The real one is completed against the Finacle sandbox and the bank's Finacle integration layer (FI/Connect24-style services) **[confirm with bank]**.

---

## 3. Mock / simulator behaviour (FR-CBA-015, NFR-018)

Every port has a deterministic mock adapter, configured by fixtures and **fault scripts**:

| Fault | Effect | Used to test |
|---|---|---|
| `latency(ms)` | Delays the response | NFR-002, timeouts |
| `timeout` | No response within the timeout | Retry and breaker |
| `timeout_then_success` | Times out, but the effect is applied | Lookup-before-retry, exactly-once |
| `duplicate` | Returns a "duplicate" business error on re-send | Idempotency mapping |
| `partial(step)` | Saga step N fails after N-1 succeed | Compensation |
| `reject(code)` | Business rejection | Workflow return paths |
| `error_rate(p)` | Random retryable errors | Breaker thresholds |

Fault scripts are selectable per binding in non-production environments only. The simulator **refuses to bind in an installation flagged `production`** without an explicit, audit-logged override.

---

## 4. Per-integration delivery checklist (Definition of Done for an adapter)
An adapter is done when all of the following hold:
1. The port contract and canonical DTOs are published in OpenAPI/JSON Schema.
2. The mock adapter has fault scripts.
3. The live adapter has:
   - its mapping tables (codes, products, branches, GL);
   - its error mapping;
   - its manifest, including processing location;
   - timeout, retry and breaker settings.
4. `integration_calls` logging is PII-masked.
5. The certification kit passes against the sandbox.
6. The runbook covers credentials rotation, sandbox→prod cutover and known failure modes.
7. The register status is updated.
