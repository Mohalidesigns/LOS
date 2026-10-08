# Fundly LOS — backend (Phase 0 foundation)

Fundly LOS is a loan origination system for Nigerian commercial banks, sold
buy-and-deploy: one on-prem installation per bank, licensed by a signed licence
file. This directory is the Laravel 13 API.

The application is **API-only**. It serves JSON under `/api/v1` plus the
`/health` and `/ready` probes, and has no Blade or Inertia views.

Phase 0 builds the platform foundation:
- tenancy with PostgreSQL row-level security
- identity and access: RBAC with scopes, segregation of duties, maker-checker, MFA and sessions
- a hash-chained audit trail
- the command bus
- the integration runtime with the core-banking (CBA) port and its simulator
- licensing
- API conventions

Business modules (applications, products and so on) start in P1.

- Contract: [`../api/openapi/openapi.yaml`](../api/openapi/openapi.yaml) (OpenAPI 3.1).
- Requirement-by-requirement build record: [`../docs/build-log-P0.md`](../docs/build-log-P0.md).

---

## 1. Requirements

| Tool | Version |
|---|---|
| PHP | 8.3+ with `pdo_pgsql`, `sodium`, `intl`, `mbstring`, `bcmath` (and `redis` for production cache/queue/breaker) |
| Composer | 2.x |
| PostgreSQL | 16+ (**only** supported database, for the app *and* the tests) |
| Redis | 7+ (production; tests use the database/array drivers) |

---

## 2. Database: the two-role model

The app never connects as a superuser or as the schema owner. Row-level
security therefore applies to every query the application makes, including raw
SQL.

| Role | Used by | Properties |
|---|---|---|
| `fundly_owner` | migrations only (`pgsql_owner` connection) | owns the schema; `NOSUPERUSER NOBYPASSRLS` |
| `fundly_app` | api, worker, scheduler, **tests** (`pgsql` connection) | non-owner, `NOSUPERUSER NOBYPASSRLS NOINHERIT`; table grants per migration |

Provision the roles once, as a superuser. Passwords are passed as psql
variables and are never committed:

```bash
psql -h <host> -U postgres -d postgres \
  -v owner_password='…' -v app_password='…' -v db_name=fundly \
  -f ../deploy/sql/00-provision-roles.sql
```

Every tenant table has `ENABLE` + `FORCE ROW LEVEL SECURITY` with this policy:

```sql
tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid
```

`TenantContext` sets `app.tenant_id` per request or job. Without it, queries
return nothing. `audit_events` is INSERT/SELECT-only for the app role, and a
trigger rejects UPDATE, DELETE and TRUNCATE, even for the owner.

`php artisan migrate` switches to the owner connection automatically.
`--database=pgsql_owner` is the explicit form.

---

## 3. Local setup

```bash
composer install
cp .env.example .env            # fill DB_PASSWORD / DB_OWNER_PASSWORD, APP_URL
php artisan key:generate
# KEK for field encryption (32 random bytes, base64), mode 0400, outside the repo:
php -r 'require "vendor/autoload.php"; Fundly\Integration\Adapters\LocalKeyfile\LocalKeyfileKms::generateKeyFile($argv[1]);' /secure/path/kek.key
#   then set FUNDLY_KEK_PATH=/secure/path/kek.key
php artisan migrate --database=pgsql_owner

# First tenant (installation = tenant). Passwords come from the environment, never argv:
FUNDLY_BOOTSTRAP_PASSWORD_1='…' FUNDLY_BOOTSTRAP_PASSWORD_2='…' \
  php artisan tenant:provision acme "Acme Bank" --admin="Ada Admin:ada@acme.test" --admin="Bo Checker:bo@acme.test"
```

### Licence (development)

```bash
php artisan licence:keypair --secret-out=/secure/path/licence.sk   # dev only; prints FUNDLY_LICENCE_PUBLIC_KEY
# put the public key in .env (FUNDLY_LICENCE_PUBLIC_KEY=…), then:
php artisan licence:issue --secret-key-file=/secure/path/licence.sk --modules=core --out=dev.lic   # dev only
php artisan licence:import dev.lic --maker=ada@acme.test           # raises a maker-checker request
# a second administrator approves: POST /api/v1/change-requests/{id}/actions/approve
```

`licence:keypair` and `licence:issue` refuse to run when `APP_ENV=production`
or `FUNDLY_INSTALLATION_ENV=production`. While a licence is missing or expired
beyond grace, these still work:
- read-only auditor access
- licence status and import
- approving the licence-import change request
- in-flight outbox work

Holders of `licence:import_approve` can still sign in for that recovery (D-034).

### Demo seed (non-production only)

`FUNDLY_DEMO_PASSWORD='…' php artisan db:seed` creates a demo tenant with two administrators.

---

## 4. Tests and quality gates

The suite runs against **PostgreSQL**, connecting as `fundly_app`. `phpunit.xml`
targets `127.0.0.1:5433/fundly_test` with the development passwords
`owner_dev_pw` and `app_dev_pw`. Provision that database with the SQL script
above, or set the `DB_*` environment variables to override.

```bash
vendor/bin/pest                       # all suites: Unit, Feature, Database, Architecture
vendor/bin/pest --testsuite=Database  # RLS + audit tamper tests
vendor/bin/pest --group=FR-SEC-007    # every test for one requirement ID
vendor/bin/pint --test                # code style
vendor/bin/phpstan analyse --memory-limit=2G   # Larastan level 8, no baseline
composer audit
```

How the test harness works:
- The schema is rebuilt once per run as the owner. Each test then runs in a
  transaction on the runtime connection.
- Feature tests use a stateful SPA client. It keeps a cookie jar, sends the
  Referer and an Idempotency-Key, and resets scoped services, guards and
  sessions between requests, the way Octane does.
- Every `/api/v1` response is validated against the OpenAPI document by
  `tests/Support/OpenApiValidator.php`. The validator uses opis/json-schema
  draft 2020-12, because league/openapi-psr7-validator only supports 3.0.
- A test KEK is generated under `storage/framework/testing/`, which is
  git-ignored.

CI (`../.github/workflows/ci.yml`) runs these steps:
1. Provision the two roles on a PostgreSQL 16 service.
2. Assert that `fundly_app` cannot bypass RLS.
3. Migrate as the owner.
4. Run Pint, Larastan, Pest and `composer audit`.

---

## 5. Architecture

```
src/
  Shared/                 kernel: Money (brick/math), UuidV7, Clock, CanonicalJson, PiiMasker,
                          TenantContext, CommandBus, Audit (hash chain), Security (Principal,
                          AuthorizationGate, ListScopeFilter), Http (problem+json, cursor
                          pagination, ETag, correlation id, Idempotency-Key), Crypto (per-tenant DEKs)
  Modules/<Module>/{Contracts,Domain,Application,Infrastructure,Http}
    Access                users, roles, permissions, scoped assignments, SoD, delegations,
                          maker-checker engine, authentication (Argon2id, TOTP, step-up, sessions)
    Platform              tenants, legal entities, org units (closure table), config artefacts/versions
    Audit                 search, verification, checkpoints
    Licensing             licence guard, import (maker-checker), enforcement middleware
  Integration/
    Ports/                CoreBanking (10 sub-ports, operation catalogue, DTOs), Licensing, KeyManagement
    Runtime/              gateway, adapter registry, bindings, capability manifest, retry/backoff,
                          circuit breaker (DB or Redis), error taxonomy, call log, outbox dispatcher
    Adapters/             OfflineLicence (Ed25519), LocalKeyfile (KEK)
    Simulators/           CoreBanking simulator with fault scripts
```

### Rules, each enforced by an architecture test in `tests/Architecture`

- **Command bus.** Every state change goes through the `CommandBus`. It runs
  authorize, then validate, then handle, then writes the audit record and the
  outbox message, all in **one** transaction. Domain events are dispatched
  after commit. Handlers never manage transactions, and controllers never
  write.
- **Module boundaries.** Modules talk to each other only through `Contracts`.
  Domain layers do not use the framework.
- **Vendor isolation.** No core-banking vendor identifier appears outside
  `src/Integration/Adapters`. Modules never import adapters or simulators.
- **Route authorisation.** Every route has an authorization middleware
  (`authz:<permission>`, `authz:authenticated` or `authz:public`). Every
  effectful POST requires an `Idempotency-Key`. The OpenAPI document lists
  exactly the implemented routes.
- **Code hygiene.** Every PHP file declares `strict_types`. Application code
  contains no floats; money and rates are `BigDecimal` / `Money`.

### Request pipeline

The `api` group runs this middleware, in order:
1. correlation id
2. tenant resolution from the credential
3. Sanctum (SPA cookie or personal access token)
4. principal binding
5. session policy (idle/absolute timeouts, concurrent cap, revocation on privilege change)
6. licence enforcement
7. throttle
8. `authz`
9. `stepup:<minutes>`
10. `idempotent`

Errors are RFC 9457 `application/problem+json`, with a stable `code` and the
`correlation_id`.

### Access model (TRD §8)

```
allow = ∃ active assignment whose role grants the permission ∧ the resource ⊨ the assignment scope
```

- A scope combines any of: legal entity, org-unit subtree, products,
  currencies, maximum amount, segments and portfolio tags.
- `ListScopeFilter` compiles the same rules into SQL, so lists cannot include
  records the user could not open.
- Personal access tokens can only *narrow* their user's access.
- SoD is checked when an assignment is requested, when it is executed, and
  when an action is performed, including history-based SoD on the same record.

### Integration runtime

`IntegrationGateway` handles each call as follows:
- resolves the adapter binding (legal-entity specific first, then the tenant default)
- checks the capability manifest, sending unsupported operations to their declared substitute
- applies the circuit breaker
- retries reads inline with exponential backoff and full jitter; **never** retries writes inline
- maps native error codes to the canonical taxonomy (unknown codes become `requires_intervention`, never success)
- logs every call with PII masked

Writes go through the transactional outbox. The dispatcher claims messages
with `SKIP LOCKED` and a lease, and does a lookup before any retry. The CBA
simulator supports these fault scripts: `latency`, `timeout`,
`timeout_then_success`, `duplicate`, `partial`, `reject` and `error_rate`.

---

## 6. Operations

| Command | Purpose |
|---|---|
| `tenant:provision` | create the installation tenant, its legal entity and bootstrap administrators |
| `audit:verify [--tenant] [--from] [--to] [--json]` | verify hash chains and checkpoints; non-zero exit on any break |
| `audit:checkpoint` | anchor each tenant's chain head (DB + write-once file sink) |
| `licence:import <file> --maker=<email>` | raise a licence-import change request |
| `licence:check` | licence expiry warnings (T-60/30/7, grace, breach) into the audit trail |
| `licence:keypair`, `licence:issue` | **development only** |

The scheduler (`php artisan schedule:work`) runs:
- outbox dispatch, every minute
- audit checkpoints, every 5 minutes
- nightly `audit:verify`
- daily `licence:check`

Container deployment is described in [`../deploy/docker`](../deploy/docker) and
[`../deploy/compose/docker-compose.yml`](../deploy/compose/docker-compose.yml).

To run it:
1. Put the secrets in `deploy/compose/secrets/`: `app_key`,
   `db_super_password`, `db_owner_password`, `db_app_password`,
   `redis_password`, `fundly_kek`, `minio_root_user` and
   `minio_root_password`. The files must be readable by uid 33.
2. Put the TLS certificate and key in `deploy/compose/tls/` as `tls.crt` and
   `tls.key`.
3. Copy `env/app.env.example` to `env/app.env`.

None of these are committed.
