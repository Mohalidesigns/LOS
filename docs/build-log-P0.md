# Build log — Phase 0 (Foundation)

**Scope.** All P0 requirement IDs from `phase_map.py`, built against
`03-TRD.md` (§2–§8, §10, §11, §13), `04-integration-register.md` §2 and
`decision-log.md`.

**Code.** `backend/`, OpenAPI contract `api/openapi/openapi.yaml`, CI
`.github/workflows/ci.yml`, deployment `deploy/`.

**Proof.** Every test is tagged with its requirement ID, so
`vendor/bin/pest --group=<ID>` runs the evidence for one requirement.

## Summary

| Gate | Result |
|---|---|
| Pest (Unit + Feature + Database + Architecture) on PostgreSQL 16 as `fundly_app` | **176 passed, 0 failed, 0 skipped** (5,296 assertions, ~33 s) |
| Larastan / PHPStan 2.3.0 | **level 8, 0 errors, no baseline, no ignores** |
| Pint | pass |
| `composer audit` | no advisories |
| Container smoke test (the built php-fpm image behind the nginx config) | pass; details in "Deployment verification" below |

### Status per requirement

**Status key**
- **Done:** the requirement as written for P0 is met and tested.
- **Partial:** a tested foundation exists, but parts of the requirement depend on later phases or on infrastructure. Each Partial row says which parts.

| ID | Status | Tests |
|---|---|---|
| FR-TEN-001 | Done | 7 |
| FR-TEN-003 | Done | 3 |
| FR-TEN-009 | Done | 6 |
| FR-SEC-001 | Partial | 3 |
| FR-SEC-002 | Done | 2 |
| FR-SEC-003 | Done | 3 |
| FR-SEC-004 | Done | 3 |
| FR-SEC-005 | Done (one list-filter gap, noted) | 4 |
| FR-SEC-006 | Done | 8 |
| FR-SEC-007 | Partial (P0 actions wired) | 13 |
| FR-SEC-008 | Done | 4 |
| FR-SEC-011 | Done | 2 |
| FR-SEC-013 | Partial (P0 high-risk actions wired) | 18 |
| FR-SEC-014 | Done | 6 |
| FR-SEC-015 | Done | 5 |
| FR-SEC-016 | Partial | 2 + smoke |
| FR-SEC-017 | Partial | 4 |
| FR-SEC-019 | Done for P0 surface | 6 |
| FR-AUD-001 | Done for P0 surface | 4 |
| FR-AUD-002 | Done | 3 |
| FR-AUD-003 | Done | 4 |
| FR-AUD-004 | Done (anchor sink is a stand-in) | 8 |
| FR-AUD-005 | Done | 2 |
| FR-AUD-007 | Done | 7 |
| FR-AUD-012 | Partial | 2 |
| FR-CBA-001 | Done | 5 |
| FR-CBA-002 | Done | 3 |
| FR-CBA-007 | Done | 12 |
| FR-CBA-008 | Done | 8 |
| FR-CBA-010 | Done (Redis breaker store untested) | 6 |
| FR-CBA-011 | Done | 8 |
| FR-CBA-016 | Done | 5 |
| FR-CBA-020 | Partial (pattern in place, only CBA/licensing/KMS ports exist) | 5 |
| FR-CMP-036 | Partial (no UI or exports yet) | 4 |
| LOS-FR-278 | Done | 1 |
| LOS-FR-284 | Partial (layers 2 and 5 only) | 5 |
| LOS-FR-302 | Done (local identity store; LDAP not in P0) | 13 |
| LOS-FR-316 | Done (offline adapter; Atheris server adapter pending G-48) | 12 |

---

## Tenancy and platform (M01)

### FR-TEN-001: multi-tenant isolation; no query path returns cross-tenant data

**Implemented: two layers of isolation.**

*Database layer.*
- Every tenant table has `ENABLE` and `FORCE ROW LEVEL SECURITY`, with this
  policy for both `USING` and `WITH CHECK`:
  `tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid`.
- The app connects as `fundly_app` (`NOSUPERUSER NOBYPASSRLS NOINHERIT`, not
  the owner). Migrations run as `fundly_owner`, and a `CommandStarting`
  listener forces `migrate*` onto the owner connection.
- `TenantContext` uses `set_config('app.tenant_id', …)`. `run()` restores the
  previous context.

*Application layer.*
- A global `TenantScope` applies to every tenant model.
- The tenant is resolved from the credential (the session user or the token
  owner), never from the request body.
- Composite foreign keys `(tenant_id, id)` stop cross-tenant references.

**Tests.** `Database/RowLevelSecurityTest.php`:
- connects as a non-owner, non-superuser role that cannot bypass RLS
- lets tenant A read only its own rows, even via raw SQL on the app connection
- returns nothing without a tenant context
- refuses writes into another tenant (WITH CHECK) and updates/deletes of its rows
- cannot switch off RLS or escalate from the runtime role

`Feature/Platform/TenancyAndOrgTest.php`:
- isolates tenants at the application layer: B cannot see or reach A data, whatever the request says
- keeps each tenant audit chain separate

**Deviation.** These tables have no RLS, by design:
- `tenants`, `sessions` and `personal_access_tokens` are read *before* the
  tenant is known; they are how the tenant gets resolved.
- `installation`, `licences` and `licence_events` are installation-scoped
  (D-033: installation = tenant).
- `permissions` is the code-defined catalogue.
- `migrations`, `cache*` and `jobs*` are framework tables.

None of these hold tenant business data.

### FR-TEN-003: org hierarchy, legal entity → configurable levels, with labels and depth

**Implemented.**
- `legal_entities` stores jurisdiction, licence category, base currency and
  timezone.
- `org_units` has `level` and a closure table `org_unit_closure`. Moving a
  subtree is maintained transactionally.
- Each legal entity stores its level labels (`org_level_labels`). The
  number of labels sets the maximum depth, which org-unit creation enforces.

**Tests.** `TenancyAndOrgTest.php`:
- models legal entity → configurable levels with labels and enforces the configured depth
- maintains the closure table when a subtree moves, and scope follows the move
- stores jurisdiction, licence category, base currency and timezone on the legal entity

### FR-TEN-009: versioned configuration, draft → review → approved → active, with maker-checker

**Implemented.**
- `config_artifacts` and `config_versions` follow the lifecycle
  `draft → in_review → approved → active → superseded`.
- `ConfigTypeRegistry` validates content per type. P0 registers
  `security.session_policy` and `platform.reference_list`.
- A database trigger makes approved and active content immutable.
- The reviewer must differ from the author. Activation and rollback go
  through the maker-checker engine (`ActivateConfigVersionAction`), with a
  stale check on the currently active version.
- The active configuration is read live: `security.session_policy` changes
  session timeouts without a restart.

**Tests.** `Feature/Platform/ConfigLifecycleTest.php`, all 6 tests:
- runs draft → in_review → approved → active with maker-checker activation
- validates content per type and only lets drafts be edited (with If-Match)
- makes approved content immutable at the database, even for direct SQL
- requires a reviewer other than the author, and an activation checker other than the author
- activates a new version, supersedes the old one, and rolls back via maker-checker
- applies the active session policy at runtime

### LOS-FR-284: configuration layer model (§17 layers 1–6); no code forks

**Status: Partial.**

**Implemented.**
- **Layer 2, declarative configuration:** versioned artefacts with a typed
  registry and maker-checker (see FR-TEN-009).
- **Layer 5, adapters:** an adapter registry with capability manifests and
  per-legal-entity bindings.
- **No forks:** the vendor-neutrality architecture test.

**Tests:**
- the 3 lifecycle and runtime tests in `ConfigLifecycleTest.php`
- `CoreBankingRuntimeTest.php`: resolves legal-entity specific bindings before the tenant default
- `VendorNeutralityTest.php`: has no core banking vendor identifiers outside src/Integration/Adapters

**Not done.** These belong to P1+ and depend on the business modules that use
them:
- Layer 1: reference-data tables, beyond the `platform.reference_list` artefact type.
- Layer 3: rules.
- Layer 4: extension attributes.
- Layer 6: signed extension modules and extension points.

---

## Identity and access (M02)

### FR-SEC-001: fine-grained resource:action permissions, including field-level control

**Status: Partial.** The model is complete; the sensitive fields it protects
arrive with P1 entities.

**Implemented.**
- The `Permission` enum is the code-defined catalogue, using `resource:action`
  codes with module, description and a sensitivity flag. It is synced into
  `permissions` after each migration.
- Field-level control: `UserPresenter` masks email unless the reader holds
  `pii:unmask`.
- P1 permissions (application, offer, disbursement…) are already in the
  catalogue, so the role library is complete, but nothing in P0 uses them.

**Tests.** `AuthorizationTest.php`:
- lists the code-defined permission catalogue
- masks sensitive fields for readers without the field permission
- clones a template into an assignable role and lets the tenant edit it via maker-checker

### FR-SEC-002 and LOS-FR-278: custom roles; clone and modify the 19-role standard library

**Implemented.**
- `RoleLibrary` holds 19 templates, seeded per tenant as **non-assignable**
  templates.
- `POST /roles/{id}/actions/clone` creates an editable, assignable role.
- Changing a role's permissions goes through maker-checker
  (`SetRolePermissionsAction`). Holders' sessions are revoked when it executes.

**Tests.** `AuthorizationTest.php`:
- ships the 19-role standard library as tenant-modifiable, non-assignable templates
- clones a template into an assignable role…

### FR-SEC-003: deny by default

**Implemented.**
- `AccessPolicy::decide` denies unless an active assignment grants the
  permission *and* the resource satisfies the assignment's scope.
- New roles hold nothing.
- Every route carries `authz:…`; the architecture test enforces this.

**Tests:**
- `AccessPolicyTest`: denies by default when nothing grants the permission
- `AuthorizationTest`: creates new roles with no permissions and denies by default
- `CommandBusTest`: authorizes before validating and never reaches the handler when denied; the denial is audited

### FR-SEC-004: multiple concurrent assignments; effective access = union constrained by each scope

**Tests:**
- `AuthorizationTest`: grants the union of all assignments
- `AccessPolicyTest`: allows through the union of assignments and names the granting assignment
- `MakerCheckerTest`: only creates a role assignment once a different user approves it

### FR-SEC-005: all §12.1 scope dimensions, combinable in one assignment

**Implemented.**
- `Scope` covers legal entities, an org-unit subtree (through the closure
  table), products, currencies, a maximum amount (`Money`), segments and
  portfolio tags.
- `ScopeFilter` compiles the scope into SQL for lists, and `Authorizer` uses
  the same rules for single reads.

**Tests:**
- `AccessPolicyTest`: applies every scope dimension, combined within one assignment
- `AuthorizationTest`: combines legal-entity and org scope in one assignment
- `AuthorizationTest`: filters lists by scope and refuses out-of-scope single reads
- `TenancyAndOrgTest`: scope follows a subtree move

**Limitation.**
- The SQL list filter does not compile `portfolio tags`. Single-resource
  checks do apply them. No P0 table has a portfolio-tag column; this will be
  added with the first P1 entity that has one.
- The product, currency, amount and segment dimensions are compiled into SQL
  but are only unit-tested at the decision level. No P0 list has those
  columns.

### FR-SEC-006: configurable SoD matrix, enforced at assignment and at action time, with conflict reporting

**Implemented.**
- `sod_rules` holds role pairs and permission pairs. Default rules are seeded
  per tenant, and administrators can manage them through the API.
- `SodChecker` runs when an assignment is requested, again when it is
  executed, and when a role bundle is changed.
- `Authorizer` blocks at action time if the user holds a standing conflict.
  It also blocks a user from exercising the counterpart permission on the same
  record (history-based SoD).
- `GET /sod-conflicts` reports existing violations.

**Tests.** `SegregationOfDutiesTest.php` (all 6), plus:
- `MakerCheckerTest`: does not let the subject of a change approve it…
- `AccessPolicyTest`: detects SoD violations across combined holdings

### FR-SEC-007: maker-checker on configurable actions

**Status: Partial.** The engine is complete. It is wired to every P0 action
that needs it:
- role assignment grant and revoke
- role permission changes
- config activation and rollback
- licence import

Product activation, limits, disbursement and templates are P1+ actions and
will register `ChangeAction`s with the same engine.

**Implemented.**
- `change_requests` records the maker, the checker and the required checker
  permission.
- A database check requires checker ≠ maker.
- Before executing, the engine:
  - excludes the subject of the change from approving it
  - re-validates the request
  - compares the fingerprint taken at request time against current state, and
    fails the request as stale if they differ
- Reject requires a reason. Only the maker may cancel.
- Approval requires a step-up.

**Tests:**
- `MakerCheckerTest.php` (all 9)
- `ConfigLifecycleTest` (2)
- `LicensingTest`: imports a signed licence only through maker-checker
- `SegregationOfDutiesTest`: execution-time conflict

### FR-SEC-008: effective-dated assignments, temporary elevation that expires, delegation with mandatory expiry

**Tests:**
- `AccessPolicyTest`: honours valid_from and valid_to
- `MakerCheckerTest`: temporary elevation expires
- `DelegationTest`: delegates an assignment for a bounded period with mandatory expiry (maximum from config)
- `DelegationTest`: only lets you delegate your own assignments, and lets the delegator revoke

### FR-SEC-011: effective-access view (what and why)

**Implemented.** `GET /me/effective-access` and
`GET /users/{id}/effective-access` (the latter requires `user:read`). Each
response lists every permission with the scope and the granting assignment or
delegation.

**Tests:**
- `AuthorizationTest`: shows effective access with the granting assignment, for self and (with user:read) others

### FR-SEC-013 and LOS-FR-302: MFA, step-up, built-in identity store, password policy, lockout

**Implemented.**
- Users are a local identity store with Argon2id passwords.
- Password policy: at least 12 characters, mixed case, a number and a symbol.
- TOTP is RFC 6238 and implemented in-house. The secret is encrypted with the
  tenant's data key. Replay is rejected using the last used time step.
- MFA is enrolled on first sign-in and required on every later sign-in.
- Lockout uses exponential backoff, and login is rate-limited per identity and
  per IP.
- Unknown users, bad passwords and locked accounts all return the same
  generic error.
- `stepup:<minutes>` middleware requires a re-entered password plus TOTP. A
  successful sign-in counts as a step-up for the window.

Routes that require step-up in P0:
- change-request approve and reject
- role assignment and revocation
- role permission changes
- SoD rule changes
- delegations
- user update
- token issue
- config transitions
- licence import

**Tests.** `AuthenticationTest.php`:
- enrols TOTP on first sign-in and stores the secret encrypted, never in clear
- requires MFA on subsequent sign-ins and rejects a replayed code
- requires a recent step-up for high-risk actions
- returns the same generic error for unknown users, bad passwords and locked accounts
- locks the account with backoff after repeated failures and audits it
- rate-limits login attempts per identity

`TotpTest.php`: RFC 4226 vectors, RFC 6238 vectors, ±1 step and replay.

`MakerCheckerTest`: requires step-up for approval.

**Partial.** Step-up for approval above a threshold, disbursement release and
PII unmask will attach to P1 routes; the middleware is generic. LDAP/AD (D-025)
and SAML/OIDC are not in P0.

### FR-SEC-014: same permission model for API access; service and partner principals with no bypass

**Implemented.**
- Sanctum personal access tokens belong to a `service` or `partner` user that
  holds assignments like any other user.
- Token abilities can only *narrow* that user's access.
- Issuing a token requires `user:manage_tokens` and a step-up.

Architecture tests check that:
- every route has an `authz` middleware
- every non-public route is authenticated
- only the auth bootstrap and the probes are public
- the OpenAPI document lists exactly the implemented routes, each with a
  permission and a security scheme

**Tests:**
- `RoutesTest` (4)
- `AuthenticationTest`: applies the same permission model to service tokens, which can only narrow access
- `AuthenticationTest`: issues scoped service tokens through the API with step-up
- `AccessPolicyTest`: lets a token only narrow access, never widen it

### FR-SEC-015: idle and absolute timeouts, concurrent-session cap, re-authentication after privilege change

**Implemented.**
- Sessions use the database driver.
- `EnforceSessionPolicy` enforces the idle and absolute timeouts and the
  concurrent-session cap; when the cap is exceeded, the oldest session is
  evicted.
- The values come from the active `security.session_policy` artefact, falling
  back to config.
- A privilege change increments `users.auth_version` and deletes the user's
  sessions, which forces re-authentication.

**Tests:**
- `AuthenticationTest`: ends idle and absolute-timeout sessions
- `AuthenticationTest`: caps concurrent sessions per user
- `AuthenticationTest`: forces re-authentication after a privilege change
- `MakerCheckerTest`: revokes an assignment through maker-checker and forces the user to re-authenticate
- `ConfigLifecycleTest`: applies the active session policy at runtime

### FR-SEC-016: TLS 1.3 in transit; at rest with tenant-level key separation; customer-managed keys

**Status: Partial.**

**Implemented.**
- *In transit:* `deploy/docker/nginx/fundly.conf` allows `ssl_protocols TLSv1.3`
  only. It sets HSTS and HTTP/2, and redirects plain HTTP to HTTPS except
  `/health`.
- *Tenant key separation:* `TenantKeyRing` gives each tenant its own data key.
  Data keys are stored only wrapped by the key-encryption key (KEK) through
  `KeyManagementPort`, and they rotate while old ciphertext stays readable.
- *Customer-managed keys:* the port exists, but the only adapter is
  `LocalKeyfileKms`, a KEK file mounted read-only.

**Tests:**
- `FieldEncryptionTest`: encrypts fields under per-tenant data keys that are stored only wrapped by the KEK
- `FieldEncryptionTest`: rotates data keys while old ciphertext stays readable, and refuses tampered wrapped keys
- Container smoke test: TLS 1.3 accepted; a TLS 1.2-only client is refused (curl exit 35)

**Not done.**
- Full-disk and database-volume encryption are installation infrastructure,
  not application code.
- HSM and Vault KMS adapters are not built.
- No automated test runs against nginx; the TLS checks are from the manual
  smoke run.

### FR-SEC-017: field-level encryption of the most sensitive attributes, access mediated by permission

**Status: Partial.**

**Implemented.**
- `FieldEncryptor` uses XChaCha20-Poly1305 with the tenant's data key.
- The field name is bound as associated data, so values cannot be swapped
  between columns.
- A deterministic per-tenant blind index supports exact-match search.

**Tests:**
- `FieldEncryptionTest` (4)
- `AuthenticationTest`: the TOTP secret is stored encrypted

**Not done.** The identity numbers and account numbers this requirement
targets belong to P1 entities (party, KYC). In P0 the only encrypted field is
the TOTP secret. Permission-mediated reveal exists as `pii:unmask`, and is
exercised on user email.

### FR-SEC-019: rate limiting, input validation, OWASP Top 10

**Implemented.**
- Rate limits for login (per identity and per IP) and for the API, with
  matching nginx `limit_req` zones.
- Form-request validation on every write.
- RFC 9457 problem documents that never leak internals: `APP_DEBUG` is off and
  every error carries a correlation id.
- Sanctum CSRF protection for the SPA, and strict `__Host-` cookies.
- Generic authentication errors.
- A deny-all CSP plus nosniff, frame and referrer headers at nginx.
- Idempotency keys on effectful POSTs.
- The JSON-only renderer never redirects.

**Tests:**
- `ApiConventionsTest`: renders all errors as RFC 9457 problem documents
- `UnauthenticatedClientsTest`: answers unauthenticated non-JSON clients with a 401 problem document, not a login redirect (regression found by the smoke test)
- `AuthenticationTest` (3)
- `AccessPolicyTest` lockout

**Not done.** No DAST/ZAP scan is part of CI.

---

## Audit (M03)

### FR-AUD-001: an immutable audit event for every state change, decision, config change and privileged access

**Implemented.**
- The `CommandBus` writes the audit event in the same transaction as the
  state change and the outbox message.
- Authentication, maker-checker, licence, integration and authorisation-denial
  events are written by their services.
- `GET /audit-events` supports filters and cursor pagination.

**Tests:**
- `AuditTrailTest`: captures actor, roles snapshot, permissions hash, IP, user agent, correlation id, before/after and reason
- `AuditTrailTest`: searches with filters and cursor
- `CommandBusTest`: runs authorize → validate → handle → audit → outbox in one transaction
- `MakerCheckerTest`: audits the full maker-checker lifecycle

### FR-AUD-002: required fields on every event

**Implemented.** Each event records:
- actor type and id
- `actor_roles` (a snapshot)
- `effective_permissions_hash`
- `on_behalf_of` (delegation)
- `source_ip`, `user_agent` and `device_id` (from `X-Device-Id`)
- `occurred_at` (timestamptz, microseconds)
- action, permission and outcome
- entity type and id
- `before` and `after` (PII-masked)
- reason code and text
- `correlation_id` and `step_up_ref`

**Tests:**
- `AuditTrailTest`: captures actor, roles snapshot…
- `AuditTrailTest`: masks PII in before/after values
- `PiiMaskerTest`

### FR-AUD-003: audit records non-editable and non-deletable by anyone, including DBAs

**Implemented.**
- The app role has INSERT and SELECT only.
- A `BEFORE UPDATE OR DELETE` row trigger and a `BEFORE TRUNCATE` statement
  trigger reject changes, **including from the owner**.
- An owner or superuser can still disable the trigger. That is covered by
  FR-AUD-004 detection.

**Tests:**
- `AuditTamperDetectionTest`: rejects UPDATE, DELETE and TRUNCATE on audit events even for the schema owner
- `AuditTrailTest`: gives the runtime role INSERT and SELECT only

### FR-AUD-004: tamper evidence and a verification routine

**Implemented.**
- Each event's hash is `SHA-256(prev_hash ‖ CanonicalJson(row without hash))`.
  The chain is per tenant, and `seq` has no gaps.
- Appends are serialised with `pg_advisory_xact_lock(41300, hashtext(tenant))`.
- `audit:checkpoint` anchors each chain head into `audit_checkpoints` and into
  a write-once file sink.
- `AuditVerifier`, run as `audit:verify` or `GET /audit/verify`, detects:
  - content edits
  - deleted events (sequence gaps)
  - broken links
  - a chain rewritten and fully re-hashed, caught by checkpoint mismatch
- `audit:verify` runs nightly from the scheduler.

**Tests.** `AuditTamperDetectionTest`:
- detects an edited event after the trigger is disabled by the owner
- detects a deleted event as a sequence gap
- detects a fully re-hashed chain through the anchored checkpoint

`AuditTrailTest`:
- hash-chains events per tenant with a gap-free sequence
- verifies the chain from the CLI and the API, and writes checkpoints

Also `CanonicalJsonAndHashChainTest` (2) and `TenancyAndOrgTest` (separate
chains).

**Limitation.** The file sink stands in for the Object-Lock bucket or SIEM
that TRD §5.4 specifies. An attacker with both database-owner and
filesystem-root access could rewrite both. The Object-Lock/SIEM adapter is
still to be built.

### FR-AUD-005: system actions attributed to named system identities

**Implemented.** The `SystemIdentity` enum names each automated actor:
`system:outbox`, `system:scheduler`, `system:licensing`, `system:installer` and
the others. An `ActorType::System` actor is required for automated actions.

**Tests:**
- `AuditTrailTest`: attributes automated actions to named system identities
- `OutboxDispatchTest`: delivers a committed outbox message and records the result

### FR-AUD-007: log every authentication event, permission change, role assignment, delegation and failed authorisation

**Implemented.**
- `Authorizer` records `authz.denied` for every denial, including out-of-scope
  single reads.
- The bus records denials after rollback, so each denial is recorded once.
- Login, MFA, step-up, lockout, logout, licence-denied login and session
  revocation are each audited.

**Tests:**
- `AuditTrailTest`: logs permission changes, role assignments and failed authorisation attempts
- `AuthenticationTest` (2)
- `AuthorizationTest`: out-of-scope reads
- `CommandBusTest`
- `MakerCheckerTest`
- `LicensingTest`

### FR-AUD-012: audit retention independent of operational retention

**Status: Partial.**

**Implemented.**
- Audit rows have no foreign keys to business tables, so deleting business
  data leaves its audit history intact.
- The app role cannot delete audit rows.
- `fundly.audit.retention_years` (default 10) is configured.

**Tests:**
- `AuditTrailTest`: keeps audit retention independent of operational data
- `AuditTrailTest`: INSERT/SELECT only

**Not done.**
- `audit_events` is not partitioned by month (TRD §5.4).
- There is no archive or export job for records past retention.
- `retention_years` is configuration only; nothing reads it yet.

---

## Command bus and API conventions (Shared)

These are cross-cutting rather than requirement IDs, but several requirements
depend on them.

**Command bus.** `CommandBus` runs these steps:
1. Set the current principal.
2. Authorize through `AuthorizationGate`.
3. Validate (`ValidatesInput`).
4. Run the handler (`HandledBy`).
5. Write the audit entry and outbox intents, in one transaction with step 4.
6. Dispatch domain events after commit.

**Bus tests.** `CommandBusTest` (6). `LayeringTest` checks that:
- commands have handlers
- handlers never manage transactions
- controllers never write or touch the DB facade
- modules cross only through Contracts
- domains are free of the framework
- every file declares strict_types
- no floats are used

**API conventions:**
- `X-Correlation-Id` is accepted or generated and echoed.
- `Idempotency-Key` is required on effectful POSTs. Replays get the stored
  response; reusing a key with a different body gets 409; 5xx responses are
  not stored.
- Errors are `application/problem+json`.
- Lists use opaque cursor pagination (`page[size]`, `page[after]`) with a
  maximum page size.
- Mutable resources use `ETag` / `If-Match`. A missing `If-Match` gets 428 and
  a stale one gets 412.

**Convention tests.** `ApiConventionsTest` (7).

---

## Integration runtime and CBA (M17)

### FR-CBA-001: versioned canonical interface covering the §9.2 operations

**Implemented.**
- `CoreBankingPort` declares `CONTRACT_VERSION` and is split into 10
  sub-ports: customers, exposure, accounts, loans, postings, schedules,
  name-enquiry, collateral, mandates and calendar.
- 24 immutable DTOs carry the data, with `Money` for amounts.
- The `Operations` catalogue classifies every operation as read or write and
  gives its retry policy.
- Adapters declare a `CapabilityManifest`. An operation marked unsupported
  must name a substitute such as `manual_task`.

**Tests:**
- `CoreBankingRuntimeTest`: serves customer search/get/create, exposure, loan account, disbursement, posting lookup, name enquiry and schedule through the canonical port
- `CoreBankingRuntimeTest`: routes operations the manifest marks unsupported to their substitute
- `RuntimePrimitivesTest` (2)
- `OutboxDispatchTest`: exposes bindings, manifests and the masked call log over the API

### FR-CBA-002: no CBA-specific identifier outside an adapter; architecture test in CI

**Tests.** `VendorNeutralityTest`:
- scans for vendor identifiers outside `src/Integration/Adapters`
- checks itself, so a pattern that stops catching identifiers fails
- checks that modules never import adapters or simulators

**Deviation.** Pest architecture and token-scan tests are used instead of
Deptrac, to avoid another tool. The rules are the same.

### FR-CBA-007: idempotency key on every state-changing operation; duplicate, timeout-then-success and partial failure

**Implemented.**
- Write DTOs carry an idempotency key.
- The gateway **never retries writes inline**. A write timeout is an *unknown
  outcome*.
- `LookupBeforeRetry` looks up whether the effect was applied before
  re-sending with the same key.
- The simulator implements native idempotency.
- HTTP `Idempotency-Key` is handled by `IdempotencyStore`.

**Tests (12):**
- `OutboxDispatchTest`: books exactly once when the provider times out after applying the effect
- `OutboxDispatchTest`: re-delivers after a crash (lease expiry)…
- `OutboxDispatchTest`: never blind-retries a disbursement…
- `CoreBankingRuntimeTest`: natively idempotent; duplicate fault; partial(step) fault; no inline write retry
- `ApiConventionsTest` (2)
- `RoutesTest`
- `RuntimePrimitivesTest` (2)

### FR-CBA-008: transactional outbox

**Implemented.**
- `outbox_messages` rows are written in the command's transaction.
- `OutboxDispatcher` claims messages with `FOR UPDATE SKIP LOCKED` and a
  lease, runs the handler outside a transaction, and records the outcome:
  dispatched, retried, parked, failed or rejected.
- `DispatchOutboxJob` runs every minute for every tenant. It is licence
  fail-safe.

**Tests:**
- `OutboxDispatchTest` (6)
- `CommandBusTest`: one transaction
- `CommandBusTest`: rolls back state, audit and outbox together

### FR-CBA-010: per-adapter timeout, retry with backoff, circuit breaking with alerts

**Implemented.**
- `OperationPolicy` sets the timeout per operation.
- `RetryPolicy` uses exponential backoff with full jitter, in integer
  milliseconds, with a cap.
- `CircuitBreaker` keeps its state per binding and operation, in a database
  store (default) or a Redis store. Opening the circuit writes a
  `circuit_events` row and an audit alert, after which calls short-circuit.

**Tests:**
- `CoreBankingRuntimeTest` (4: latency, timeout retry, breaker trip and alert, seeded error_rate)
- `OutboxDispatchTest`: backoff then park
- `RuntimePrimitivesTest`: backoff

**Limitation.** `RedisBreakerStore` is implemented but **not tested**. Tests run
without Redis; CI could add a Redis service.

### FR-CBA-011: canonical error taxonomy

**Implemented.**
- Four error classes: `Retryable`, `NonRetryable`, `RequiresIntervention` and
  `BusinessRejection`.
- Canonical codes such as `CBA.POSTING.INSUFFICIENT_FUNDS`.
- Adapters supply an error map. **An unmapped native code becomes
  `requires_intervention`, never success.**

**Tests:**
- `RuntimePrimitivesTest` (2)
- `CoreBankingRuntimeTest` (3)
- `OutboxDispatchTest` (3)

### FR-CBA-016: log every CBA interaction with PII masking

**Implemented.** `integration_calls` records each call's:
- operation, binding and adapter version
- correlation id and idempotency key
- latency, outcome and error class
- request and response, masked by `PiiMasker`

The log is exposed at `GET /integration-calls` and is append-only for the app
role.

**Tests:**
- `CoreBankingRuntimeTest`: logs every call with PII masked, latency, correlation id and adapter version
- `OutboxDispatchTest`: call log over the API
- `ApiConventionsTest`: correlation id

### FR-CBA-020: the same adapter pattern for all external integrations

**Status: Partial.** The pattern is generic:
- `AdapterRegistry`
- `adapter_bindings` keyed by `port`
- manifests
- the gateway
- the simulator guard, which refuses simulators in production

It is already used by the CBA, Licensing and KeyManagement ports.

**Tests:**
- `VendorNeutralityTest`: ports only
- `CoreBankingRuntimeTest` (3)
- `RuntimePrimitivesTest`: manifest

**Not done.** The bureau, KYC, screening, e-signature, payment and
notification ports belong to P1+.

### Adapter bindings API

`GET /adapter-bindings` and `GET /adapter-bindings/{id}` are read-only. Writes
are not exposed, per the brief. Bindings are created by provisioning and the
seeder.

---

## Compliance

### FR-CMP-036: PII masked by default in UI, logs and exports; unmasking is permissioned and logged

**Status: Partial.**

**Implemented.**
- `PiiMasker` works recursively from a configurable key list. It masks PII and
  redacts secrets.
- It is applied to:
  - audit before/after values
  - integration call logs
  - API representations (`pii:unmask` reveals email)
- Logs use JSON to stderr, and nothing logs raw request bodies.

**Tests:**
- `PiiMaskerTest`
- `AuditTrailTest`: masks PII in before/after
- `CoreBankingRuntimeTest`: call log masking
- `AuthorizationTest`: masks sensitive fields without the field permission

**Not done.**
- There is no UI and no exports in P0.
- An unmask is not yet recorded as a separate `pii.unmasked` audit event.
  That will come with the P1 party API, where unmasking is an explicit action.
- Masking for lower environments (data copies) is out of scope.

---

## Licensing (M18)

### LOS-FR-316: signed licence, modules, user caps, validity, installation binding, offline activation, fail-safe enforcement

**Implemented.**

*Port and adapter.*
- `LicensingPort` with the `OfflineSignedFileLicensing` adapter.
- The licence is a JSON document signed with Ed25519 (libsodium), checked
  against a pinned public key.
- The installation fingerprint is
  `sha256(installation_uuid:pg system_identifier)`.
- The stored licence is re-verified on read, so editing its row voids it.
- `POST /licence/activation-request` produces the offline activation request.

*Enforcement.*
- `EnforceLicence` middleware applies validity and grace and sends a
  `Fundly-Licence-State` header. Module entitlement is enforced per route
  group (`licence:<module>`).
- The named-user cap is enforced at user creation and at login.
- `EnsureJobLicensed` blocks ordinary queued jobs.

*Fail-safe (D-034).* These keep working whatever the licence state:
- read-only auditor sign-in and audit routes
- licence status and import
- the licence-import change-request routes
- outbox and saga jobs
- sign-in for holders of `licence:import_approve`, so an expired or
  unlicensed installation can be recovered (fixed in this phase)

*Events and commands.*
- Licence events are audited, including T-60/30/7 warnings, grace and breach
  (`licence:check`).
- `licence:keypair` and `licence:issue` are development only and refuse to
  run in production.
- `licence:import` raises a maker-checker request.

**Tests.** `LicensingTest.php` (11), plus `OutboxDispatchTest`: fail-safe job.

**Not done.** The Atheris LicensingServer adapter and online check-in are
pending G-48. `checkIn()` returns "not used" for the offline adapter.

---

## Deployment verification (container smoke test)

**Environment.** Docker was available in the build sandbox. The smoke test
used:
- the runtime stage of `deploy/docker/Dockerfile`
- the local `vendor/`, because composer could not download GitHub zipballs
  through the sandbox proxy (a sandbox limitation, not an image defect)
- a read-only root filesystem with tmpfs, as in compose
- nginx running `deploy/docker/nginx/*.conf` with a self-signed certificate

**Results:**
- `/health` returned 200 over HTTP/2 with TLS 1.3 and every security header.
- A TLS 1.2-only client was refused.
- HTTP redirected to HTTPS with 308.
- Paths outside the API returned 404.
- `/ready` reported RLS enforcement, applied migrations and the cache.
- `X-Correlation-Id` passed through.

**Full licence lifecycle, run in the container:**
1. Migrate as the owner.
2. Run `licence:keypair`, which refused in production mode and worked in dev.
3. Provision a tenant with 2 administrators.
4. Run `licence:issue`.
5. Run `licence:import --maker`.
6. The checker signed in over HTTPS with no licence installed (recovery).
7. Routes outside recovery returned 403 `licence-not-installed`.
8. The checker approved the change request; it executed.
9. `GET /roles` returned 200 with `Fundly-Licence-State: valid`.
10. `audit:verify` reported OK, with 31 events.
11. No server errors were logged.

**Defects found and fixed. Both now have regression tests.**
1. **Guest redirect returned 500.** An unauthenticated client without
   `Accept: application/json` got a 500. The framework's default guest
   redirect targets a `login` route that this API-only app does not have.
   Guests now get a 401 problem document.
2. **Licence recovery deadlock.** Beyond grace, or before the first licence,
   nobody could sign in to approve the licence-import request. The old test
   had logged the checker in *before* expiry.

**Not verified.** The full `docker compose up` was not run, for three reasons:
- registry rate limits in the sandbox
- the size of the minio, clamav and gotenberg images
- these services have no P0 consumer

`docker compose config` validates the file. The CI workflow has not run on
GitHub, because there is no remote; its steps mirror commands that pass
locally.

---

## Decisions taken where the TRD was silent or ambiguous

1. **Tables without RLS:** tenants, sessions, personal access tokens,
   installation, licences, licence events, permissions and the framework
   tables. The reasons are under FR-TEN-001.
2. **`NULLIF` in the RLS predicate.** An unset or empty `app.tenant_id`
   matches nothing; it does not raise a cast error. Session-level `set_config`
   (not `SET LOCAL`) is used because the tenant must hold across the request's
   several transactions. `ResolveTenantFromCredential` restores the previous
   context in a `finally` block at the end of each request, and
   `TenantContext::run()` does the same for jobs. Nothing leaks across
   requests on a persistent connection.
3. **Tenant resolution for unauthenticated requests** such as login:
   `FUNDLY_TENANCY_RESOLUTION=single` uses the sole active tenant (D-033).
   `hostname` mode resolves the tenant from the request host. After sign-in,
   the tenant always comes from the credential.
4. **ETag** is a SHA-256 over the full representation, not a version counter.
   Version counters collided under frozen test clocks, and a representation
   hash also catches changes made outside the bus.
5. **TOTP is implemented in-house.** It is about 100 lines, pinned to the
   RFC 4226 and RFC 6238 vectors, and avoids a dependency on a
   security-critical path.
6. **OpenAPI validation uses opis/json-schema (draft 2020-12).**
   `league/openapi-psr7-validator` does not support OpenAPI 3.1. The contract
   is the source of truth, and an architecture test checks it against the
   routes.
7. **Sanctum `AuthenticateSession` is disabled.** It resolved the user before
   the tenant context existed. `EnforceSessionPolicy` does the same job
   through `auth_version`, and adds timeouts and the cap.
8. **Bootstrap administrators** are created by `tenant:provision` as
   `system:installer`, without maker-checker, because no checker exists yet.
   The action is audited, and the command warns if there are fewer than 2
   administrators.
9. **Denied authorisations write an audit record** (`authz.denied`), including
   out-of-scope reads that return 404.
10. **`role_assignments.granted_by` is `varchar(128)`.** It holds a user UUID
    or a system identity such as `system:installer`.
11. **All timestamps are `timestamptz(6)`.** Microsecond precision keeps
    audit and ETag ordering stable.
12. **Licence fail-safe at login:**
    - Read-only principals can always sign in.
    - Users with `user:manage` are exempt from the user cap, so they can
      deactivate users to get back under it.
    - Users with `licence:import_approve` can sign in when the licence is not
      operational, so the installation can be recovered.
    - In every case the middleware still restricts the routes they can reach.
13. **A sign-in counts as a step-up** for the step-up window (default 5
    minutes), because it is a fresh password plus TOTP.
14. **The P1 permission catalogue is defined in P0**, so the 19-role library
    is complete. Nothing in P0 enforces those permissions.
15. **Field encryption** uses libsodium XChaCha20-Poly1305 with per-tenant
    data keys wrapped by a local KEK. The field name is bound as associated
    data.
16. **Money** uses `brick/math` `BigDecimal` at storage scale 4, with
    `RoundingMode::HalfEven` (banker's rounding) and integer minor units per
    ISO 4217.

## Quality-gate notes

- **PHPStan:** level 8 across `src`, `app`, `routes`, `database` and `config`,
  with no baseline and no ignore comments. `tests/` is not analysed.
- **`composer.json`:** `larastan/larastan` is constrained to `*` (resolved
  3.13.0). Pin it before release.
