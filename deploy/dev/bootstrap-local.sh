#!/usr/bin/env bash
# Fundly LOS - local development bootstrap (NOT for any shared or production use).
#
# Makes backend/ runnable on this machine for frontend development:
#   .env -> KEK -> migrations -> dev tenant + 2 admins -> licence keypair ->
#   dev licence -> import (maker = admin 1) -> approve via API (checker = admin 2)
#
# Usage:
#   deploy/dev/bootstrap-local.sh [setup]     everything (default, idempotent)
#   deploy/dev/bootstrap-local.sh serve       php artisan serve on 127.0.0.1:8091 (SERVE_PORT=...)
#   deploy/dev/bootstrap-local.sh totp EMAIL  print the current TOTP code for an admin
#   deploy/dev/bootstrap-local.sh login EMAIL sign in via the API (smoke test, needs a running server)
#   deploy/dev/bootstrap-local.sh reset-mfa EMAIL  clear an admin's MFA so the next login re-enrols
#   deploy/dev/bootstrap-local.sh reset --yes drop ALL tables in the dev DB, forget licence + MFA secrets
#   deploy/dev/bootstrap-local.sh seed-demo  demo org, product, rule set, business users + roles, parties,
#                                            applications (two in Assessment for the Credit tab)
#                                            (needs a running API: run 'serve' in another terminal first)
#
# Prerequisites: PHP 8.4 + composer deps in backend/vendor, PostgreSQL with the
# `fundly` DB and fundly_owner / fundly_app roles (deploy/sql/00-provision-roles.sql),
# curl, jq.
#
# Everything below can be overridden from the environment.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
BACKEND_DIR="${BACKEND_DIR:-$REPO_DIR/backend}"
DEV_DIR="${FUNDLY_DEV_DIR:-$HOME/.fundly-dev}"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-fundly}"
DB_USERNAME="${DB_USERNAME:-fundly_app}"
DB_PASSWORD="${DB_PASSWORD:-app_dev_pw}"
DB_OWNER_USERNAME="${DB_OWNER_USERNAME:-fundly_owner}"
DB_OWNER_PASSWORD="${DB_OWNER_PASSWORD:-owner_dev_pw}"

SERVE_HOST="${SERVE_HOST:-127.0.0.1}"
# 8091 by default: port 8000 is often taken by another local Laravel app. Override
# with SERVE_PORT (or API_BASE for a server you run yourself).
SERVE_PORT="${SERVE_PORT:-${FUNDLY_API_PORT:-8091}}"
API_BASE="${API_BASE:-http://$SERVE_HOST:$SERVE_PORT}"
SPA_ORIGIN="${SPA_ORIGIN:-http://localhost:5173}"

TENANT_SLUG="${TENANT_SLUG:-fundly-dev}"
TENANT_NAME="${TENANT_NAME:-Fundly Dev Bank}"
ADMIN1_NAME="${ADMIN1_NAME:-Ada Admin}"
ADMIN1_EMAIL="${ADMIN1_EMAIL:-ada@fundly.test}"
ADMIN1_PASSWORD="${ADMIN1_PASSWORD:-Ada-Dev-Passw0rd!}"
ADMIN2_NAME="${ADMIN2_NAME:-Bo Checker}"
ADMIN2_EMAIL="${ADMIN2_EMAIL:-bo@fundly.test}"
ADMIN2_PASSWORD="${ADMIN2_PASSWORD:-Bo-Dev-Passw0rd!}"

# seed-demo business users (created through the API; MFA enrols on their first scripted login)
DEMO_PASSWORD="${DEMO_PASSWORD:-Demo-Dev-Passw0rd!}"
DEMO_LO_NAME="${DEMO_LO_NAME:-Lola Originator}"
DEMO_LO_EMAIL="${DEMO_LO_EMAIL:-lola@fundly.test}"
DEMO_CMP1_NAME="${DEMO_CMP1_NAME:-Chidi Compliance}"
DEMO_CMP1_EMAIL="${DEMO_CMP1_EMAIL:-chidi@fundly.test}"
DEMO_CMP2_NAME="${DEMO_CMP2_NAME:-Ngozi Compliance}"
DEMO_CMP2_EMAIL="${DEMO_CMP2_EMAIL:-ngozi@fundly.test}"
DEMO_CA_NAME="${DEMO_CA_NAME:-Tunde Analyst}"
DEMO_CA_EMAIL="${DEMO_CA_EMAIL:-tunde@fundly.test}"
DEMO_RULE_SET_KEY="${DEMO_RULE_SET_KEY:-sme-policy}"

LICENCE_MODULES="${LICENCE_MODULES:-*}"
LICENCE_MAX_USERS="${LICENCE_MAX_USERS:-200}"

KEK_FILE="${FUNDLY_KEK_FILE:-$DEV_DIR/kek.key}"
LICENCE_SK="$DEV_DIR/licence.sk"
LICENCE_FILE="$DEV_DIR/dev.lic"
ENV_FILE="$BACKEND_DIR/.env"

# ---------------------------------------------------------------- helpers
c_bold=$'\033[1m'; c_dim=$'\033[2m'; c_red=$'\033[31m'; c_grn=$'\033[32m'; c_off=$'\033[0m'
heading() { printf '\n%s==> %s%s\n' "$c_bold" "$*" "$c_off"; }
info() { printf '    %s\n' "$*"; }
ok() { printf '    %s%s%s\n' "$c_grn" "$*" "$c_off"; }
die() { printf '%sERROR:%s %s\n' "$c_red" "$c_off" "$*" >&2; exit 1; }

artisan() { (cd "$BACKEND_DIR" && php artisan "$@"); }

require_tools() {
  local t
  for t in php curl jq; do command -v "$t" >/dev/null || die "'$t' is required"; done
  [[ -f "$BACKEND_DIR/vendor/autoload.php" ]] || die "run 'composer install' in $BACKEND_DIR first"
}

secret_file_for() { printf '%s/mfa-%s.secret' "$DEV_DIR" "$1"; }
step_file_for() { printf '%s/mfa-%s.laststep' "$DEV_DIR" "$1"; }

password_for() {
  case "$1" in
    "$ADMIN1_EMAIL") printf '%s' "$ADMIN1_PASSWORD" ;;
    "$ADMIN2_EMAIL") printf '%s' "$ADMIN2_PASSWORD" ;;
    "$DEMO_LO_EMAIL"|"$DEMO_CMP1_EMAIL"|"$DEMO_CMP2_EMAIL"|"$DEMO_CA_EMAIL") printf '%s' "$DEMO_PASSWORD" ;;
    *) local v="${FUNDLY_DEV_PASSWORD:-}"; [[ -n "$v" ]] || die "no password known for $1 (set FUNDLY_DEV_PASSWORD)"; printf '%s' "$v" ;;
  esac
}

# Owner-connection SQL (single value / first column of first row). Sets the
# tenant GUC when a tenant id is given so forced RLS lets the row through.
sql_value() { # sql [tenant_id]
  php -r '
    $pdo = new PDO(sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE")), getenv("DB_OWNER_USERNAME"), getenv("DB_OWNER_PASSWORD"), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if (($argv[2] ?? "") !== "") { $pdo->prepare("select set_config(?, ?, false)")->execute(["app.tenant_id", $argv[2]]); }
    $v = $pdo->query($argv[1])->fetchColumn();
    echo $v === false || $v === null ? "" : $v;
  ' "$1" "${2:-}"
}
export DB_HOST DB_PORT DB_DATABASE DB_OWNER_USERNAME DB_OWNER_PASSWORD

# RFC 6238 TOTP (SHA1, 30 s, 6 digits). Prints "<code> <step>".
totp_code() { # base32_secret [unix_time]
  php -r '
    $s = strtoupper(preg_replace("/[^A-Za-z2-7]/", "", $argv[1])); $a = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
    $bits = ""; foreach (str_split($s) as $c) { $bits .= str_pad(decbin(strpos($a, $c)), 5, "0", STR_PAD_LEFT); }
    $key = ""; foreach (str_split($bits, 8) as $b) { if (strlen($b) === 8) { $key .= chr(bindec($b)); } }
    $t = intdiv((int) ($argv[2] ?? time()), 30);
    $h = hash_hmac("sha1", pack("J", $t), $key, true); $o = ord($h[19]) & 0xF;
    $n = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    printf("%06d %d\n", $n % 1000000, $t);
  ' "$1" "${2:-$(date +%s)}"
}

# Like totp_code but waits for a step not used yet (the server rejects replays).
fresh_totp_for() { # email -> prints code
  local email="$1" sf stf secret last now step code
  sf="$(secret_file_for "$email")"; stf="$(step_file_for "$email")"
  [[ -f "$sf" ]] || die "no MFA secret stored for $email ($sf); use 'reset-mfa $email' then log in again"
  secret="$(cat "$sf")"
  last="$(cat "$stf" 2>/dev/null || echo 0)"
  while :; do
    now="$(date +%s)"; step=$((now / 30))
    if (( step > last )); then break; fi
    printf '    %s(waiting %ss for a fresh TOTP step)%s\n' "$c_dim" $((30 - now % 30 + 1)) "$c_off" >&2
    sleep $((30 - now % 30 + 1))
  done
  read -r code step < <(totp_code "$secret" "$now")
  echo "$step" >"$stf"
  printf '%s' "$code"
}

set_env_key() { # key value  (in-place upsert in backend/.env)
  php -r '
    [$f, $k, $v] = [$argv[1], $argv[2], $argv[3]];
    $lines = file_exists($f) ? file($f, FILE_IGNORE_NEW_LINES) : [];
    $found = false;
    foreach ($lines as $i => $l) { if (preg_match("/^".preg_quote($k, "/")."=/", $l)) { $lines[$i] = "$k=$v"; $found = true; } }
    if (! $found) { $lines[] = "$k=$v"; }
    file_put_contents($f, implode("\n", $lines)."\n");
  ' "$ENV_FILE" "$1" "$2"
}
get_env_key() { grep -E "^$1=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

# ---------------------------------------------------------------- HTTP session helpers
JAR=""
new_session() { JAR="$(mktemp "${TMPDIR:-/tmp}/fundly-jar.XXXXXX")"; }
xsrf() { php -r 'echo urldecode($argv[1]);' "$(awk '$6=="XSRF-TOKEN"{v=$7} END{print v}' "$JAR")"; }

# api METHOD PATH [json-body] [extra curl args...] -> sets $HTTP_STATUS and $BODY (no subshell)
HTTP_STATUS=""; BODY=""
api() {
  local method="$1" path="$2" body="${3:-}"; shift 3 || shift $#
  local out; out="$(mktemp "${TMPDIR:-/tmp}/fundly-out.XXXXXX")"
  local args=(-sS -o "$out" -w '%{http_code}' -X "$method" -b "$JAR" -c "$JAR"
    -H 'Accept: application/json' -H "Origin: $SPA_ORIGIN" -H "Referer: $SPA_ORIGIN/")
  if [[ "$method" != GET ]]; then args+=(-H "X-XSRF-TOKEN: $(xsrf)"); fi
  if [[ -n "$body" ]]; then args+=(-H 'Content-Type: application/json' --data "$body"); fi
  HTTP_STATUS="$(curl "${args[@]}" "$@" "$API_BASE$path")"
  BODY="$(cat "$out")"; rm -f "$out"
}

# Fundly answers /health with 200; another app on the same port usually 404s.
server_up() { curl -fsS -o /dev/null "$API_BASE/health" 2>/dev/null; }

STARTED_PID=""
ensure_server() {
  if server_up; then info "API already running at $API_BASE"; return; fi
  info "starting temporary php artisan serve on $SERVE_HOST:$SERVE_PORT"
  (cd "$BACKEND_DIR" && exec php artisan serve --host="$SERVE_HOST" --port="$SERVE_PORT" >"$DEV_DIR/serve.log" 2>&1) &
  STARTED_PID=$!
  local i
  for i in $(seq 1 40); do server_up && return; sleep 0.25; done
  die "server did not come up; see $DEV_DIR/serve.log"
}
stop_server() {
  if [[ -n "$STARTED_PID" ]]; then
    pkill -P "$STARTED_PID" 2>/dev/null || true
    kill "$STARTED_PID" 2>/dev/null || true
    wait "$STARTED_PID" 2>/dev/null || true
    STARTED_PID=""
  fi
}

# Full SPA sign-in: csrf-cookie -> login -> (enrol) -> mfa/verify. Leaves $JAR authenticated.
login() { # email
  local email="$1" pw resp status secret code
  pw="$(password_for "$email")"
  new_session
  api GET /api/v1/auth/csrf-cookie ''
  [[ "$HTTP_STATUS" == 204 || "$HTTP_STATUS" == 200 ]] || die "csrf-cookie returned $HTTP_STATUS"
  api POST /api/v1/auth/login "$(jq -nc --arg e "$email" --arg p "$pw" '{email:$e,password:$p}')"; resp="$BODY"
  [[ "$HTTP_STATUS" == 200 ]] || die "login $email -> HTTP $HTTP_STATUS: $resp"
  status="$(jq -r '.data.status' <<<"$resp")"
  case "$status" in
    authenticated) ok "$email authenticated (no MFA)"; return ;;
    mfa_enrollment_required)
      secret="$(jq -r '.data.mfa_enrollment.secret' <<<"$resp")"
      [[ -n "$secret" && "$secret" != null ]] || die "enrolment response without a secret: $resp"
      umask 077
      printf '%s\n' "$secret" >"$(secret_file_for "$email")"
      jq -r '.data.mfa_enrollment.otpauth_uri // .data.mfa_enrollment.provisioning_uri // empty' <<<"$resp" >"$DEV_DIR/mfa-$email.otpauth"
      rm -f "$(step_file_for "$email")"
      info "MFA enrolled for $email; secret saved to $(secret_file_for "$email")" ;;
    mfa_required) : ;;
    *) die "unexpected login status '$status': $resp" ;;
  esac
  code="$(fresh_totp_for "$email")"
  api POST /api/v1/auth/mfa/verify "$(jq -nc --arg c "$code" '{code:$c}')"; resp="$BODY"
  if [[ "$HTTP_STATUS" == 401 ]]; then
    # The step may already have been used elsewhere (e.g. a code typed into the SPA); try the next one.
    info "code rejected (step already used?); retrying with the next TOTP step"
    date +%s | awk '{print int($1/30)}' >"$(step_file_for "$email")"
    code="$(fresh_totp_for "$email")"
    api POST /api/v1/auth/mfa/verify "$(jq -nc --arg c "$code" '{code:$c}')"; resp="$BODY"
  fi
  [[ "$HTTP_STATUS" == 200 && "$(jq -r '.data.status' <<<"$resp")" == authenticated ]] || die "mfa verify $email -> HTTP $HTTP_STATUS: $resp"
  ok "$email signed in"
}

# ---------------------------------------------------------------- steps
step_env() {
  heading "backend/.env"
  mkdir -p "$DEV_DIR"; chmod 700 "$DEV_DIR"
  if [[ -f "$ENV_FILE" ]]; then
    info "exists, keeping it (only KEK path / licence key are updated)"
  else
    cat >"$ENV_FILE" <<EOF
APP_NAME="Fundly LOS"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=$SPA_ORIGIN
APP_TIMEZONE=UTC
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_NG
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=single
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_DATABASE=$DB_DATABASE
DB_USERNAME=$DB_USERNAME
DB_PASSWORD=$DB_PASSWORD
DB_OWNER_USERNAME=$DB_OWNER_USERNAME
DB_OWNER_PASSWORD=$DB_OWNER_PASSWORD
DB_SSLMODE=prefer

# SPA via the Vite proxy (http://localhost:5173 -> $API_BASE). Plain http, so no
# __Host- prefix and no Secure flag.
SESSION_DRIVER=database
SESSION_LIFETIME=480
SESSION_EXPIRE_ON_CLOSE=true
SESSION_ENCRYPT=true
SESSION_COOKIE=fundly_session
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173

# No Redis locally
QUEUE_CONNECTION=database
CACHE_STORE=database
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
MAIL_MAILER=log

FUNDLY_INSTALLATION_ENV=non_production
FUNDLY_TENANCY_RESOLUTION=single
FUNDLY_MFA_REQUIRED=true
FUNDLY_SESSION_IDLE_MINUTES=120
FUNDLY_SESSION_ABSOLUTE_MINUTES=720
FUNDLY_SESSION_MAX_CONCURRENT=5
FUNDLY_STEP_UP_MINUTES=5
FUNDLY_LOGIN_RATE_IDENTITY=30
FUNDLY_LOGIN_RATE_IP=120
FUNDLY_BREAKER_STORE=database
FUNDLY_LICENCE_PUBLIC_KEY=
FUNDLY_KEK_PATH=$KEK_FILE
FUNDLY_KEK_ID=local-1
EOF
    chmod 600 "$ENV_FILE"
    ok "written"
  fi
  if [[ -z "$(get_env_key APP_KEY)" ]]; then artisan key:generate --force --no-interaction >/dev/null; ok "APP_KEY generated"; fi
}

step_kek() {
  heading "KEK (field encryption key)"
  if [[ -f "$KEK_FILE" ]]; then
    info "exists: $KEK_FILE"
  else
    (cd "$BACKEND_DIR" && php -r 'require "vendor/autoload.php"; Fundly\Integration\Adapters\LocalKeyfile\LocalKeyfileKms::generateKeyFile($argv[1]);' "$KEK_FILE")
    chmod 400 "$KEK_FILE"
    ok "generated $KEK_FILE"
  fi
  set_env_key FUNDLY_KEK_PATH "$KEK_FILE"
}

step_migrate() {
  heading "Migrations (as $DB_OWNER_USERNAME)"
  if [[ "${SKIP_MIGRATE:-0}" == 1 ]]; then info "skipped (SKIP_MIGRATE=1)"; return; fi
  artisan migrate --force --database=pgsql_owner --no-interaction
}

TENANT_ID=""
step_tenant() {
  heading "Tenant '$TENANT_SLUG'"
  TENANT_ID="$(sql_value "select id from tenants where slug = '$TENANT_SLUG'")"
  if [[ -n "$TENANT_ID" ]]; then
    info "already provisioned: $TENANT_ID"
  else
    FUNDLY_BOOTSTRAP_PASSWORD_1="$ADMIN1_PASSWORD" FUNDLY_BOOTSTRAP_PASSWORD_2="$ADMIN2_PASSWORD" \
      artisan tenant:provision "$TENANT_SLUG" "$TENANT_NAME" \
        --admin="$ADMIN1_NAME:$ADMIN1_EMAIL" --admin="$ADMIN2_NAME:$ADMIN2_EMAIL"
    TENANT_ID="$(sql_value "select id from tenants where slug = '$TENANT_SLUG'")"
  fi
  local active; active="$(sql_value "select count(*) from tenants where status = 'active'")"
  if [[ "$active" != 1 ]]; then
    info "${c_red}WARNING:${c_off} $active active tenants; FUNDLY_TENANCY_RESOLUTION=single only resolves a guest login when exactly one is active."
  fi
}

step_licence_key() {
  heading "Licence signing keypair (dev only)"
  if [[ ! -f "$LICENCE_SK" ]]; then
    artisan licence:keypair --secret-out="$LICENCE_SK" >/dev/null
    ok "secret key written to $LICENCE_SK"
  else
    info "exists: $LICENCE_SK"
  fi
  local pub
  pub="$(php -r '$sk = base64_decode(trim(file_get_contents($argv[1])), true); echo base64_encode(sodium_crypto_sign_publickey_from_secretkey($sk));' "$LICENCE_SK")"
  if [[ "$(get_env_key FUNDLY_LICENCE_PUBLIC_KEY)" != "$pub" ]]; then
    set_env_key FUNDLY_LICENCE_PUBLIC_KEY "$pub"
    artisan config:clear >/dev/null 2>&1 || true
    ok "FUNDLY_LICENCE_PUBLIC_KEY set in .env"
  else
    info "FUNDLY_LICENCE_PUBLIC_KEY already matches"
  fi
}

licence_status() { # "active:<id>:<state>" | "none" | "invalid:<msg>", via the app's own licensing port
  (cd "$BACKEND_DIR" && php artisan tinker --no-interaction --execute '
    try { $c = app(Fundly\Integration\Ports\Licensing\LicensingPort::class)->currentLicence();
      if ($c === null) { echo "none"; } else { $st = $c->state(new DateTimeImmutable()); echo "active:".$c->licenceId.":".($st->value ?? $st->name); }
    } catch (Throwable $e) { echo "invalid:".$e->getMessage(); }
  ' 2>/dev/null | tail -1) || echo "unknown"
}

step_licence() {
  heading "Licence"
  local row st
  row="$(sql_value "select licence_id from licences where status = 'active' order by imported_at desc limit 1" 2>/dev/null || true)"
  st="$(licence_status)"
  info "licence state: $st"
  if [[ -n "$row" && ( "$st" == *:valid || "$st" == *:grace ) ]]; then
    ok "active licence $row"
    return
  fi
  [[ -n "$row" ]] && info "licence $row is not usable with the current key/date; re-issuing"

  ensure_server
  # Reuse a pending import request from an interrupted run instead of piling up new ones.
  local cr reused=0
  cr="$(sql_value "select id from change_requests where action_type = 'licensing.licence.import' and status = 'pending' order by created_at desc limit 1" "$TENANT_ID")"
  if [[ -n "$cr" ]]; then reused=1; info "reusing pending licence-import change request $cr"; else cr="$(raise_licence_import)"; fi
  [[ -n "$cr" ]] || die "no licence-import change request to approve"

  heading "Approve licence import as $ADMIN2_EMAIL (maker-checker via API)"
  login "$ADMIN2_EMAIL"
  # A fresh primary sign-in counts as step-up (FUNDLY_STEP_UP_MINUTES), so no /auth/step-up call is needed.
  approve_cr "$cr"
  if [[ "$HTTP_STATUS" == 409 && "$reused" == 1 ]]; then
    info "stale request closed by the server; raising a new one"
    cr="$(raise_licence_import)"; [[ -n "$cr" ]] || die "licence import failed"
    approve_cr "$cr"
  fi
  [[ "$HTTP_STATUS" =~ ^20 ]] || die "approve -> HTTP $HTTP_STATUS: $BODY"
  ok "approved: $(jq -c '{id: .data.id, status: .data.status}' <<<"$BODY" 2>/dev/null || echo "$BODY")"
  rm -f "$JAR"
}

raise_licence_import() { # -> prints change request id
  artisan licence:issue --secret-key-file="$LICENCE_SK" --modules="$LICENCE_MODULES" \
    --max-users="$LICENCE_MAX_USERS" --client="$TENANT_NAME" --out="$LICENCE_FILE" >&2
  local out cr
  out="$(artisan licence:import "$LICENCE_FILE" --maker="$ADMIN1_EMAIL" --reason="Local dev bootstrap" 2>&1)" || die "licence:import failed: $out"
  info "$out" >&2
  cr="$(sed -nE 's/.*Change request ([0-9a-fA-F-]{36}).*/\1/p' <<<"$out" | head -1)"
  [[ -n "$cr" ]] || die "could not parse change request id from: $out"
  printf '%s' "$cr"
}

approve_cr() { # id  (uses the current $JAR session)
  api POST "/api/v1/change-requests/$1/actions/approve" '{"reason":"Local dev bootstrap"}' -H "Idempotency-Key: dev-bootstrap-approve-$1"
}

step_enrol_remaining() {
  heading "MFA enrolment for both admins (secrets in $DEV_DIR)"
  local email
  for email in "$ADMIN1_EMAIL" "$ADMIN2_EMAIL"; do
    local confirmed
    confirmed="$(sql_value "select coalesce(mfa_confirmed_at::text, '') from users where lower(email) = lower('$email')" "$TENANT_ID")"
    if [[ -n "$confirmed" ]]; then
      if [[ -f "$(secret_file_for "$email")" ]]; then info "$email: enrolled, secret on file"; else
        info "${c_red}$email is enrolled but no secret is on file${c_off}: run '$0 reset-mfa $email' and re-run setup"; fi
      continue
    fi
    ensure_server
    login "$email"
    rm -f "$JAR"
  done
}

step_verify() {
  heading "Smoke test as $ADMIN1_EMAIL"
  ensure_server
  login "$ADMIN1_EMAIL"
  local resp
  api GET /api/v1/me ''; resp="$BODY"; [[ "$HTTP_STATUS" == 200 ]] || die "/me -> $HTTP_STATUS: $resp"
  ok "GET /api/v1/me 200 ($(jq -r '.data.email // .data.user.email // "?"' <<<"$resp"))"
  api GET /api/v1/me/effective-access ''; resp="$BODY"; [[ "$HTTP_STATUS" == 200 ]] || die "/me/effective-access -> $HTTP_STATUS: $resp"
  ok "GET /api/v1/me/effective-access 200"
  api GET /api/v1/users ''; resp="$BODY"; [[ "$HTTP_STATUS" == 200 ]] || die "/users (licence-gated) -> $HTTP_STATUS: $resp"
  ok "GET /api/v1/users 200 (licence OK)"
  api GET /api/v1/licence ''; resp="$BODY"
  ok "GET /api/v1/licence $HTTP_STATUS $(jq -c '.data | {state, modules: .licence.modules, valid_to: .licence.valid_to}' <<<"$resp" 2>/dev/null || true)"
  rm -f "$JAR"
}

print_summary() {
  heading "Done"
  cat <<EOF
    Run the API:       $0 serve        (php artisan serve --host=$SERVE_HOST --port=$SERVE_PORT)
    Run the SPA:       cd "$REPO_DIR/frontend" && npm ci && npm run dev     -> $SPA_ORIGIN
                       (Vite proxies /api, /health, /ready to $API_BASE; if you run Vite on
                       another port, add it to SANCTUM_STATEFUL_DOMAINS in backend/.env)

    Tenant:            $TENANT_NAME ($TENANT_SLUG)
    Admin (maker):     $ADMIN1_EMAIL / $ADMIN1_PASSWORD
    Admin (checker):   $ADMIN2_EMAIL / $ADMIN2_PASSWORD
    MFA secrets:       $DEV_DIR/mfa-<email>.secret  (otpauth URI in mfa-<email>.otpauth)
    Current TOTP code: $0 totp $ADMIN1_EMAIL
    Dev secrets:       $DEV_DIR (KEK, licence signing key, dev.lic)
EOF
}

# ---------------------------------------------------------------- seed-demo
# Demo data for the staff SPA, created through the public API (maker-checker
# included) so it exercises the same rules as a real tenant. Idempotent-ish:
# every step looks for what an earlier run created before creating it again.
#
#   ada (maker) / bo (checker)  org DEMO + branches, users, roles, product
#   lola  Loan Officer + Documentation Officer      originates, uploads, verifies
#   chidi Compliance Officer + Branch Manager       proposes alert dispositions, recommends, approves waivers
#   ngozi Compliance Officer                        confirms dispositions (four-eyes)
# Admins (tenant_administrator) hold no application permissions on purpose:
# sign in to the SPA as lola / chidi / ngozi to work applications.

DEMO_PRODUCT_KEY="${DEMO_PRODUCT_KEY:-sme-term-loan}"

idem() { printf 'seed-%s' "$(uuidgen | tr 'A-Z' 'a-z')"; }
apost() { # path [json] [extra curl args...]
  local path="$1" body="${2:-}"; shift 2 || shift $#
  [[ -n "$body" ]] || body='{}'
  api POST "$path" "$body" -H "Idempotency-Key: $(idem)" "$@"
}
expect() { # "codes regex" what
  [[ "$HTTP_STATUS" =~ ^($1)$ ]] || die "$2 -> HTTP $HTTP_STATUS: $BODY"
}

seed_org() {
  heading "Organisation: legal entity DEMO with branches LAGOS and KANO (as $ADMIN1_EMAIL)"
  api GET '/api/v1/legal-entities?page%5Bsize%5D=100' ''; expect 200 "list legal entities"
  LE_ID="$(jq -r '.data[] | select(.code=="DEMO") | .id' <<<"$BODY" | head -1)"
  if [[ -z "$LE_ID" ]]; then
    apost /api/v1/legal-entities '{"code":"DEMO","name":"Demo Bank Plc","jurisdiction":"NG","licence_category":"commercial_bank","base_currency":"NGN","timezone":"Africa/Lagos","org_level_labels":["Branch"]}'
    expect 201 "create legal entity"; LE_ID="$(jq -r '.data.id' <<<"$BODY")"; ok "legal entity DEMO $LE_ID"
  else info "legal entity DEMO exists: $LE_ID"; fi
  local code name
  for pair in "LAGOS:Lagos Island" "KANO:Kano Main"; do
    code="${pair%%:*}"; name="${pair#*:}"
    api GET "/api/v1/org-units?filter%5Blegal_entity_id%5D=$LE_ID&page%5Bsize%5D=100" ''; expect 200 "list org units"
    local id; id="$(jq -r --arg c "$code" '.data[] | select(.code==$c) | .id' <<<"$BODY" | head -1)"
    if [[ -z "$id" ]]; then
      apost /api/v1/org-units "$(jq -nc --arg le "$LE_ID" --arg c "$code" --arg n "$name" '{legal_entity_id:$le,code:$c,name:$n}')"
      expect 201 "create org unit $code"; id="$(jq -r '.data.id' <<<"$BODY")"; ok "branch $code $id"
    else info "branch $code exists: $id"; fi
    [[ "$code" == LAGOS ]] && LAGOS_ID="$id" || KANO_ID="$id"
  done
}

# -> echoes user id (creates the user when missing)
seed_user() { # email name
  api GET '/api/v1/users?page%5Bsize%5D=100' ''; expect 200 "list users"
  local id; id="$(jq -r --arg e "$1" '.data[] | select((.email|ascii_downcase)==($e|ascii_downcase)) | .id' <<<"$BODY" | head -1)"
  if [[ -z "$id" ]]; then
    apost /api/v1/users "$(jq -nc --arg e "$1" --arg n "$2" --arg p "$DEMO_PASSWORD" --arg le "$LE_ID" --arg ou "$LAGOS_ID" \
      '{kind:"human",email:$e,name:$n,password:$p,home_legal_entity_id:$le,home_org_unit_id:$ou}')"
    expect 201 "create user $1"; id="$(jq -r '.data.id' <<<"$BODY")"; ok "user $1 $id" >&2
  else info "user $1 exists: $id" >&2; fi
  printf '%s' "$id"
}

# -> echoes role id: a tenant role cloned from a library template, plus optional
# extra permissions (role permission changes are maker-checker: ada requests, bo approves).
seed_role() { # code name template_key [extra_permission...]
  local code="$1" name="$2" tpl_key="$3"; shift 3
  JAR="$ADA_JAR"
  api GET '/api/v1/roles?page%5Bsize%5D=100' ''; expect 200 "list roles"
  local id tpl role
  id="$(jq -r --arg c "$code" '.data[] | select(.code==$c and (.is_template|not)) | .id' <<<"$BODY" | head -1)"
  if [[ -z "$id" ]]; then
    tpl="$(jq -r --arg t "$tpl_key" '.data[] | select(.is_template and (.template_key==$t or .code==$t)) | .id' <<<"$BODY" | head -1)"
    [[ -n "$tpl" ]] || die "role template '$tpl_key' not found"
    apost "/api/v1/roles/$tpl/actions/clone" "$(jq -nc --arg c "$code" --arg n "$name" '{code:$c,name:$n,description:"seed-demo"}')"
    expect 201 "clone role $tpl_key"; id="$(jq -r '.data.id' <<<"$BODY")"; ok "role $code cloned from $tpl_key" >&2
  else info "role $code exists" >&2; fi
  if (( $# > 0 )); then
    api GET "/api/v1/roles/$id" ''; expect 200 "get role $code"; role="$BODY"
    local missing; missing="$(jq -c --args '.data.permissions as $have | [$ARGS.positional[] | select(. as $p | ($have | index($p)) == null)]' "$@" <<<"$role")"
    if [[ "$missing" != "[]" ]]; then
      api PUT "/api/v1/roles/$id/permissions" "$(jq -c --argjson add "$missing" '{permissions: (.data.permissions + $add), reason: "seed-demo: originators pick legal entity and branch"}' <<<"$role")"
      expect 202 "request permissions for $code"
      local cr; cr="$(jq -r '.data.id' <<<"$BODY")"
      JAR="$BO_JAR"; approve_cr "$cr"; expect 200 "approve permissions for $code"; JAR="$ADA_JAR"
      ok "role $code += $(jq -r 'join(", ")' <<<"$missing") (change request $cr)" >&2
    fi
  fi
  printf '%s' "$id"
}

# Request (ada) and approve (bo) one assignment at a time: a second request for
# the same user goes stale once the first executes (the user record changed).
seed_assignment() { # user_id role_id label   (needs ADA_JAR + BO_JAR)
  JAR="$ADA_JAR"
  api GET "/api/v1/role-assignments?filter%5Buser_id%5D=$1&filter%5Bactive%5D=true&page%5Bsize%5D=100" ''; expect 200 "list assignments"
  if jq -e --arg r "$2" '.data[] | select(.role_id==$r and .revoked_at==null)' <<<"$BODY" >/dev/null; then info "$3: already assigned"; return; fi
  api GET '/api/v1/change-requests?filter%5Bstatus%5D=pending&page%5Bsize%5D=100' ''; expect 200 "list change requests"
  local cr attempt; cr="$(jq -r --arg u "$1" --arg r "$2" '.data[] | select(.payload.user_id==$u and .payload.role_id==$r) | .id' <<<"$BODY" | head -1)"
  for attempt in 1 2; do
    if [[ -z "$cr" ]]; then
      JAR="$ADA_JAR"
      apost /api/v1/role-assignments "$(jq -nc --arg u "$1" --arg r "$2" --arg f "$(date -u +%Y-%m-%dT%H:%M:%SZ)" '{user_id:$u,role_id:$r,scope:{},valid_from:$f,reason:"seed-demo"}')"
      expect 202 "request assignment $3"; cr="$(jq -r '.data.id' <<<"$BODY")"
    fi
    JAR="$BO_JAR"; approve_cr "$cr"
    if [[ "$HTTP_STATUS" == 200 ]]; then ok "$3: assigned (change request $cr approved by $ADMIN2_EMAIL)"; JAR="$ADA_JAR"; return; fi
    [[ "$HTTP_STATUS" == 409 && "$attempt" == 1 ]] || die "approve assignment $3 -> HTTP $HTTP_STATUS: $BODY"
    info "$3: request $cr was stale; raising a new one"; cr=""
  done
}

seed_product_author() { # as ada: artefact, version, submit; leaves PRODUCT_* set
  heading "Product '$DEMO_PRODUCT_KEY' (author: $ADMIN1_EMAIL)"
  local base=/api/v1/config-artifacts/product content
  api GET "$base?page%5Bsize%5D=100" ''; expect 200 "list product artefacts"
  PRODUCT_ARTIFACT="$(jq -r --arg k "$DEMO_PRODUCT_KEY" '.data[] | select(.key==$k) | .id' <<<"$BODY" | head -1)"
  PRODUCT_ACTIVE="$(jq -r --arg k "$DEMO_PRODUCT_KEY" '.data[] | select(.key==$k) | .active_version_id // empty' <<<"$BODY" | head -1)"
  if [[ -n "$PRODUCT_ACTIVE" ]]; then info "already active (version $PRODUCT_ACTIVE)"; PRODUCT_VERSION=""; return; fi
  if [[ -z "$PRODUCT_ARTIFACT" ]]; then
    apost "$base" "$(jq -nc --arg k "$DEMO_PRODUCT_KEY" '{key:$k,name:"SME Term Loan",description:"Amortising term loan for SMEs (seed-demo)"}')"
    expect 201 "create product artefact"; PRODUCT_ARTIFACT="$(jq -r '.data.id' <<<"$BODY")"
  fi
  # Reuse an in-flight version from an interrupted run.
  api GET "$base/$PRODUCT_ARTIFACT/versions?page%5Bsize%5D=100" ''; expect 200 "list versions"
  PRODUCT_VERSION="$(jq -r '[.data[] | select(.status=="draft" or .status=="in_review" or .status=="submitted" or .status=="approved")] | last | .id // empty' <<<"$BODY")"
  PRODUCT_VSTATUS="$(jq -r --arg v "$PRODUCT_VERSION" '.data[] | select(.id==$v) | .status' <<<"$BODY")"
  if [[ -z "$PRODUCT_VERSION" ]]; then
    content='{"category":"sme_term_loan","segment":"sme","currency":"NGN","applicant_types":["limited_company","individual"],
      "amount":{"min":"500000.00","max":"50000000.00"},"tenor_months":{"min":3,"max":36},
      "interest":{"basis":"reducing_balance","rate_percent":"24.5000"},"repayment_frequency":"monthly","moratorium_months":{"max":3},
      "fees":[{"code":"MGMT","name":"Management fee","type":"upfront","calc":"percent","value":"1.0000"},
              {"code":"CRI","name":"Credit life insurance","type":"upfront","calc":"percent","value":"0.5000"}],
      "penalty":{"rate_percent":"2.0000"},"prepayment":{"allowed":true,"fee_percent":"1.0000"},
      "eligibility":[{"code":"MIN_TRADING","description":"At least 12 months trading history"}],
      "checklist":[
        {"code":"CAC_CERT","name":"CAC certificate of incorporation","mandatory":true,"applies_to":{"applicant_types":["limited_company"]}},
        {"code":"STATEMENT_6M","name":"6 months bank statements","mandatory":true},
        {"code":"AUDITED_FS","name":"Audited financial statements","mandatory":true,"applies_to":{"amount_min":"10000000.00"}},
        {"code":"GOVT_ID","name":"Government ID","mandatory":true,"applies_to":{"applicant_types":["individual"]}},
        {"code":"BOARD_RES","name":"Board resolution to borrow","mandatory":false,"applies_to":{"applicant_types":["limited_company"]}}],
      "bindings":{"workflow":"sme-standard","rule_set":"sme-policy","approval_matrix":"sme-matrix"},
      "offer_validity_days":30,"approval_validity_days":60}'
    apost "$base/$PRODUCT_ARTIFACT/versions" "$(jq -c '{content: ., notes: "seed-demo"}' <<<"$content")"
    expect 201 "create product version"; PRODUCT_VERSION="$(jq -r '.data.id' <<<"$BODY")"; PRODUCT_VSTATUS=draft
    ok "version $PRODUCT_VERSION drafted"
  fi
  if [[ "$PRODUCT_VSTATUS" == draft ]]; then
    apost "$base/$PRODUCT_ARTIFACT/versions/$PRODUCT_VERSION/actions/submit" '{"reason":"seed-demo"}'; expect 200 "submit version"
    PRODUCT_VSTATUS=submitted; ok "submitted for review"
  fi
}

seed_product_review() { # as bo
  [[ -n "$PRODUCT_VERSION" ]] || return 0
  local base=/api/v1/config-artifacts/product
  if [[ "$PRODUCT_VSTATUS" != approved ]]; then
    apost "$base/$PRODUCT_ARTIFACT/versions/$PRODUCT_VERSION/actions/approve" '{"reason":"seed-demo review"}'; expect 200 "approve version"
    ok "version approved by $ADMIN2_EMAIL"
  fi
}

seed_product_activate_request() { # as ada -> PRODUCT_CR
  PRODUCT_CR=""
  [[ -n "$PRODUCT_VERSION" ]] || return 0
  api GET '/api/v1/change-requests?filter%5Bstatus%5D=pending&page%5Bsize%5D=100' ''; expect 200 "list change requests"
  PRODUCT_CR="$(jq -r --arg v "$PRODUCT_VERSION" '.data[] | select((.payload|tostring)|contains($v)) | .id' <<<"$BODY" | head -1)"
  if [[ -z "$PRODUCT_CR" ]]; then
    apost "/api/v1/config-artifacts/product/$PRODUCT_ARTIFACT/versions/$PRODUCT_VERSION/actions/activate" '{"reason":"seed-demo go-live"}'
    expect 202 "request activation"; PRODUCT_CR="$(jq -r '.data.id' <<<"$BODY")"
  fi
  ok "activation change request $PRODUCT_CR"
}

# -> echoes party id; finds by display name first
seed_party() { # display_name json
  api GET "/api/v1/parties?filter%5Bq%5D=$(jq -rn --arg q "$1" '$q|@uri')&page%5Bsize%5D=25" ''; expect 200 "search parties"
  local id; id="$(jq -r --arg n "$1" '.data[] | select(.display_name==$n) | .id' <<<"$BODY" | head -1)"
  if [[ -z "$id" ]]; then
    apost /api/v1/parties "$2"; expect 201 "create party $1"; id="$(jq -r '.data.id' <<<"$BODY")"; ok "party $1" >&2
    local purpose
    for purpose in data_processing credit_bureau; do
      apost "/api/v1/parties/$id/consents" "$(jq -nc --arg p "$purpose" '{purpose:$p,action:"grant",channel:"branch",terms_version:"T&C-2026.1",evidence_ref:"signed-form-001"}')"
      expect 201 "consent $purpose for $1"
    done
  else info "party $1 exists" >&2; fi
  printf '%s' "$id"
}

# -> echoes application id; one demo application per primary party
seed_application() { # party_id amount tenor purpose
  api GET "/api/v1/applications?filter%5Bparty_id%5D=$1&page%5Bsize%5D=5" ''; expect 200 "list applications"
  local id; id="$(jq -r '.data[0].id // empty' <<<"$BODY")"
  if [[ -z "$id" ]]; then
    apost /api/v1/applications "$(jq -nc --arg le "$LE_ID" --arg ou "$LAGOS_ID" --arg k "$DEMO_PRODUCT_KEY" --arg p "$1" --arg a "$2" --argjson t "$3" --arg pu "$4" \
      '{legal_entity_id:$le,org_unit_id:$ou,product_key:$k,primary_party_id:$p,channel:"staff",requested_amount:$a,tenor_months:$t,purpose:$pu,repayment_frequency:"monthly"}')"
    expect 201 "create application"; id="$(jq -r '.data.id' <<<"$BODY")"; ok "application $(jq -r '.data.reference' <<<"$BODY") (draft)" >&2
  else info "application for party exists: $(jq -r '.data[0].reference' <<<"$BODY") ($(jq -r '.data[0].status' <<<"$BODY"))" >&2; fi
  printf '%s' "$id"
}

seed_submit() { # application id
  api GET "/api/v1/applications/$1" '' -D "$DEV_DIR/seed-headers"; expect 200 "get application"
  [[ "$(jq -r '.data.status' <<<"$BODY")" == draft ]] || { info "already submitted"; return; }
  local etag; etag="$(awk 'tolower($1)=="etag:"{print $2}' "$DEV_DIR/seed-headers" | tr -d '\r')"
  apost "/api/v1/applications/$1/actions/submit" '{}' -H "If-Match: $etag"; expect 200 "submit application"
  ok "submitted $(jq -r '.data.reference' <<<"$BODY")"
}

# ---------------------------------------------------------------- credit demo (P1-FE-03)
# credit.rule_set 'sme-policy' (the product binds rule_set: sme-policy). Same maker-checker
# flow as the product: ada authors + submits, bo approves, ada requests activation, bo approves.
RULE_SET_CONTENT='{"evaluator_version":"1.0.0",
 "formulas":[{"name":"instalment","expression":"pmt(facility.rate_percent / 1200, facility.tenor_months, facility.amount)"},
             {"name":"dsr","expression":"percent(coalesce(bureau.monthly_obligations, 0) + formulas.instalment, applicant.monthly_income)"}],
 "knockouts":{"hit_policy":"COLLECT","rows":[{"when":"bureau.has_write_off","reason":{"code":"KO_WRITE_OFF"}},
   {"when":"applicant.type == '"'"'limited_company'"'"' and coalesce(applicant.years_trading, 0) < 1","reason":{"code":"KO_TRADING_HISTORY"}}]},
 "policy":{"hit_policy":"COLLECT","rows":[{"when":"bureau.max_dpd_12m > 30","reason":{"code":"POL_DPD_30"}},
   {"when":"bureau.enquiries_6m > 5","reason":{"code":"POL_ENQUIRIES"}},
   {"when":"bureau.score != null and bureau.score < 550","reason":{"code":"POL_LOW_SCORE"}}]},
 "grade":{"hit_policy":"FIRST","rows":[{"when":"bureau.score >= 720","outputs":{"risk_grade":"A"},"reason":{"code":"GRADE_A"}},
   {"when":"bureau.score >= 640","outputs":{"risk_grade":"B"},"reason":{"code":"GRADE_B"}},
   {"when":"bureau.score >= 550","outputs":{"risk_grade":"C"},"reason":{"code":"GRADE_C"}},
   {"when":"true","outputs":{"risk_grade":"D"},"reason":{"code":"GRADE_D"}}]},
 "affordability":{"max_dsr_percent":"40","income_fact":"applicant.monthly_income","dsr_formula":"dsr"},
 "pricing":{"hit_policy":"FIRST","rows":[{"when":"decision.risk_grade == '"'"'A'"'"'","outputs":{"rate_percent":"22.5"}},
   {"when":"true","outputs":{"rate_percent":"=facility.rate_percent"}}]}}'

seed_rule_set() { # needs ADA_JAR + BO_JAR
  local type=credit.rule_set key="$DEMO_RULE_SET_KEY"
  local base="/api/v1/config-artifacts/$type" art active ver vstatus cr
  heading "Rule set $type/$key (maker: $ADMIN1_EMAIL, checker: $ADMIN2_EMAIL)"
  JAR="$ADA_JAR"
  api GET "$base?page%5Bsize%5D=100" ''; expect 200 "list rule sets"
  art="$(jq -r --arg k "$key" '.data[] | select(.key==$k) | .id' <<<"$BODY" | head -1)"
  active="$(jq -r --arg k "$key" '.data[] | select(.key==$k) | .active_version_id // empty' <<<"$BODY" | head -1)"
  if [[ -n "$active" ]]; then info "already active (version $active)"; return; fi
  if [[ -z "$art" ]]; then
    apost "$base" "$(jq -nc --arg k "$key" '{key:$k,name:"SME credit policy",description:"Knock-outs, policy, grade, affordability (DSR <= 40%) and pricing (seed-demo)"}')"
    expect 201 "create rule set artefact"; art="$(jq -r '.data.id' <<<"$BODY")"
  fi
  api GET "$base/$art/versions?page%5Bsize%5D=100" ''; expect 200 "list rule set versions"
  ver="$(jq -r '[.data[] | select(.status=="draft" or .status=="in_review" or .status=="submitted" or .status=="approved")] | last | .id // empty' <<<"$BODY")"
  vstatus="$(jq -r --arg v "$ver" '.data[] | select(.id==$v) | .status' <<<"$BODY")"
  if [[ -z "$ver" ]]; then
    apost "$base/$art/versions" "$(jq -c '{content: ., notes: "seed-demo"}' <<<"$RULE_SET_CONTENT")"
    if [[ "$HTTP_STATUS" == 422 ]]; then
      printf '%s\n' "$BODY" | jq . >&2 || printf '%s\n' "$BODY" >&2
      die "the rule set content was rejected (validation errors above)"
    fi
    expect 201 "create rule set version"; ver="$(jq -r '.data.id' <<<"$BODY")"; vstatus=draft; ok "version $ver drafted"
  fi
  if [[ "$vstatus" == draft ]]; then
    apost "$base/$art/versions/$ver/actions/submit" '{"reason":"seed-demo"}'
    [[ "$HTTP_STATUS" == 422 ]] && { jq . <<<"$BODY" >&2; die "rule set submit rejected (errors above)"; }
    expect 200 "submit rule set version"; vstatus=submitted
  fi
  if [[ "$vstatus" != approved ]]; then
    JAR="$BO_JAR"; apost "$base/$art/versions/$ver/actions/approve" '{"reason":"seed-demo review"}'; expect 200 "approve rule set version"
  fi
  JAR="$ADA_JAR"
  api GET '/api/v1/change-requests?filter%5Bstatus%5D=pending&page%5Bsize%5D=100' ''; expect 200 "list change requests"
  cr="$(jq -r --arg v "$ver" '.data[] | select((.payload|tostring)|contains($v)) | .id' <<<"$BODY" | head -1)"
  if [[ -z "$cr" ]]; then
    apost "$base/$art/versions/$ver/actions/activate" '{"reason":"seed-demo go-live"}'
    [[ "$HTTP_STATUS" == 422 ]] && { jq . <<<"$BODY" >&2; die "rule set activation rejected (errors above)"; }
    expect 202 "request rule set activation"; cr="$(jq -r '.data.id' <<<"$BODY")"
  fi
  JAR="$BO_JAR"; approve_cr "$cr"; expect 200 "approve rule set activation"; JAR="$ADA_JAR"
  ok "rule set $key is live (change request $cr)"
}

seed_consent() { # party_id purpose   (current $JAR needs party:manage)
  api GET "/api/v1/parties/$1/consents" ''; expect 200 "list consents"
  if [[ "$(jq -r --arg p "$2" '.data.current[$p].status // empty' <<<"$BODY")" == granted ]]; then return; fi
  apost "/api/v1/parties/$1/consents" "$(jq -nc --arg p "$2" '{purpose:$p,action:"grant",channel:"branch",terms_version:"T&C-2026.1",evidence_ref:"signed-form-001"}')"
  expect 201 "consent $2"; ok "consent $2 granted for party $1"
}

APP_ETAG=""
app_get() { # application id -> $BODY + $APP_ETAG (no subshell, so both survive)
  api GET "/api/v1/applications/$1" '' -D "$DEV_DIR/seed-headers"; expect 200 "get application"
  APP_ETAG="$(awk 'tolower($1)=="etag:"{print $2}' "$DEV_DIR/seed-headers" | tr -d '\r')"
}

seed_app_data() { # application id, json object merged into data (as an originator)
  app_get "$1"; local etag="$APP_ETAG"
  if jq -e --argjson want "$2" '(.data.data // {}) as $d | [$want | to_entries[] | $d[.key] == .value] | all' <<<"$BODY" >/dev/null; then
    info "$(jq -r '.data.reference' <<<"$BODY"): data already set"; return
  fi
  local merged; merged="$(jq -c --argjson want "$2" '(.data.data // {}) + $want' <<<"$BODY")"
  api PATCH "/api/v1/applications/$1" "$(jq -nc --argjson d "$merged" '{data:$d,source:"staff"}')" -H "If-Match: $etag"
  expect 200 "set application data"; ok "$(jq -r '.data.reference' <<<"$BODY"): data $(jq -c . <<<"$2")"
}


# Upload (lola) + verify (ngozi: a different officer) every mandatory item that is not yet satisfied.
seed_complete_checklist() { # application id  (needs LOLA_JAR + NGOZI_JAR)
  local pdf="$DEV_DIR/seed-demo.pdf" item id code status
  [[ -f "$pdf" ]] || printf '%%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%%%EOF\n' >"$pdf"
  JAR="$LOLA_JAR"
  api GET "/api/v1/applications/$1/checklist" ''; expect 200 "get checklist"
  while IFS= read -r item; do
    [[ -n "$item" ]] || continue
    id="$(jq -r '.id' <<<"$item")"; code="$(jq -r '.code' <<<"$item")"; status="$(jq -r '.status' <<<"$item")"
    if [[ "$status" == not_received || "$status" == rejected || "$status" == expired ]]; then
      JAR="$LOLA_JAR"
      api POST "/api/v1/applications/$1/documents" '' -H "Idempotency-Key: $(idem)" \
        -F "file=@$pdf;type=application/pdf;filename=$code.pdf" -F "document_type=$code" -F "checklist_item_id=$id" -F "title=$code (seed-demo)"
      expect 201 "upload $code"; info "uploaded $code"
    fi
    JAR="$NGOZI_JAR"
    apost "/api/v1/checklist-items/$id/actions/verify" '{"reason":null}'; expect 200 "verify $code"; ok "$code verified by $DEMO_CMP2_EMAIL"
  done < <(jq -c '.data.items[] | select(.mandatory and .status != "verified" and .status != "waived")' <<<"$BODY")
}

seed_credit_demo() { # app ids...  (as lola + ngozi; needs LOLA_JAR + NGOZI_JAR)
  local app status pid try
  for app in "$@"; do
    JAR="$LOLA_JAR"; app_get "$app"; status="$(jq -r '.data.status' <<<"$BODY")"; pid="$(jq -r '.data.primary_applicant.party_id' <<<"$BODY")"
    seed_consent "$pid" credit_bureau
    app_get "$app"; status="$(jq -r '.data.status' <<<"$BODY")"
    if [[ "$status" == kyc_screening ]]; then
      # The KYC gate needs the primary's BVN verified (simulator); the gate then moves the case on.
      api GET "/api/v1/parties/$pid" ''; expect 200 "get party"
      if jq -e '.data.identities[] | select(.type=="bvn" and .verification_status != "verified")' <<<"$BODY" >/dev/null; then
        apost "/api/v1/parties/$pid/identities/bvn/actions/verify" '{}'; expect 200 "verify BVN"; ok "BVN verified for party $pid"
      fi
      for try in 1 2 3; do # the gate re-evaluates from the outbox; give the worker a few passes
        artisan schedule:run >/dev/null 2>&1 || true
        artisan queue:work --stop-when-empty --tries=3 >/dev/null 2>&1 || true
        app_get "$app"
        [[ "$(jq -r '.data.status' <<<"$BODY")" == kyc_screening ]] || break
        sleep 2
      done
    fi
    if [[ "$status" == documentation ]]; then
      seed_complete_checklist "$app"
      JAR="$LOLA_JAR"; app_get "$app"; status="$(jq -r '.data.status' <<<"$BODY")"
    fi
    ok "$(jq -r '.data.reference' <<<"$BODY") ($(jq -r '.data.primary_applicant.display_name' <<<"$BODY")): $status"
  done
}

cmd_seed_demo() {
  require_tools
  command -v uuidgen >/dev/null || die "'uuidgen' is required"
  server_up || die "API not running at $API_BASE (run '$0 serve' in another terminal)"
  local tid; tid="$(sql_value "select id from tenants where slug = '$TENANT_SLUG'")"
  [[ -n "$tid" ]] || die "tenant $TENANT_SLUG not found; run '$0 setup' first"

  heading "Simulator adapters (identity verification, screening, ...)"
  artisan integration:bind-simulators || die "integration:bind-simulators failed"

  login "$ADMIN2_EMAIL"; BO_JAR="$JAR"
  login "$ADMIN1_EMAIL"; ADA_JAR="$JAR"
  seed_org
  heading "Demo users and roles (maker: $ADMIN1_EMAIL, checker: $ADMIN2_EMAIL)"
  local lola chidi ngozi tunde r_lo r_doc r_cmp r_bm r_sca
  lola="$(seed_user "$DEMO_LO_EMAIL" "$DEMO_LO_NAME")"
  chidi="$(seed_user "$DEMO_CMP1_EMAIL" "$DEMO_CMP1_NAME")"
  ngozi="$(seed_user "$DEMO_CMP2_EMAIL" "$DEMO_CMP2_NAME")"
  tunde="$(seed_user "$DEMO_CA_EMAIL" "$DEMO_CA_NAME")"
  r_lo="$(seed_role demo_loan_officer 'Loan Officer (demo)' loan_officer legal_entity:read org_unit:read)"
  r_doc="$(seed_role demo_documentation_officer 'Documentation Officer (demo)' documentation_officer)"
  r_cmp="$(seed_role demo_compliance_officer 'Compliance Officer (demo)' compliance_officer)"
  r_bm="$(seed_role demo_branch_manager 'Branch Manager (demo)' branch_manager)"
  r_sca="$(seed_role demo_senior_credit_analyst 'Senior Credit Analyst (demo)' senior_credit_analyst)"
  seed_assignment "$lola" "$r_lo" "lola: loan officer"
  seed_assignment "$lola" "$r_doc" "lola: documentation officer"
  seed_assignment "$chidi" "$r_cmp" "chidi: compliance officer"
  seed_assignment "$chidi" "$r_bm" "chidi: branch manager"
  seed_assignment "$ngozi" "$r_cmp" "ngozi: compliance officer"
  # ngozi also verifies documents lola uploaded (a different officer must verify an upload).
  seed_assignment "$ngozi" "$r_doc" "ngozi: documentation officer"
  seed_assignment "$tunde" "$r_sca" "tunde: senior credit analyst"

  JAR="$ADA_JAR"; seed_product_author
  JAR="$BO_JAR"; seed_product_review
  JAR="$ADA_JAR"; seed_product_activate_request
  if [[ -n "$PRODUCT_CR" ]]; then
    JAR="$BO_JAR"; approve_cr "$PRODUCT_CR"; expect 200 "approve product activation"
    ok "product $DEMO_PRODUCT_KEY is live"
  fi
  seed_rule_set
  rm -f "$ADA_JAR" "$BO_JAR"

  heading "Parties and applications (as $DEMO_LO_EMAIL)"
  login "$DEMO_LO_EMAIL"
  local company director person app1 app2
  company="$(seed_party 'Adebayo Foods Limited' "$(jq -nc --arg ou "$LAGOS_ID" '{type:"limited_company",company_name:"Adebayo Foods Limited",registration_number:"RC1234567",incorporation_date:"2015-03-01",sector:"agro_processing",phone:"08031234567",email:"finance@adebayofoods.ng",tin:"12345678-0001",address:{line1:"14 Broad Street",city:"Lagos",state:"Lagos",country:"NG"},org_unit_id:$ou}')")"
  director="$(seed_party 'Funke Adebayo' "$(jq -nc --arg ou "$LAGOS_ID" '{type:"individual",first_name:"Funke",last_name:"Adebayo",date_of_birth:"1980-05-17",gender:"female",nationality:"NG",phone:"08021234567",email:"funke@adebayofoods.ng",identities:[{type:"bvn",value:"22212345678"}],org_unit_id:$ou}')")"
  local rel
  for rel in 'director:null' 'shareholder:"60.00"'; do
    api GET "/api/v1/parties/$company/relationships" ''; expect 200 "list relationships"
    if ! jq -e --arg d "$director" --arg r "${rel%%:*}" '.data.relationships[] | select(.party.id==$d and .role==$r)' <<<"$BODY" >/dev/null; then
      apost "/api/v1/parties/$company/relationships" "$(jq -nc --arg d "$director" --arg r "${rel%%:*}" --argjson pct "${rel#*:}" '{related_party_id:$d,role:$r,ownership_percent:$pct}')"
      expect 201 "add ${rel%%:*}"; ok "Funke Adebayo recorded as ${rel%%:*} of Adebayo Foods Limited"
    fi
  done
  person="$(seed_party 'Chinedu Eze' "$(jq -nc --arg ou "$KANO_ID" '{type:"individual",first_name:"Chinedu",last_name:"Eze",date_of_birth:"1988-11-02",gender:"male",nationality:"NG",phone:"08035550123",email:"chinedu.eze@example.ng",identities:[{type:"bvn",value:"22298765432"}],org_unit_id:$ou}')")"
  app1="$(seed_application "$company" '12500000.00' 24 'Purchase of a cassava processing line')"
  app2="$(seed_application "$person" '2000000.00' 12 'Working capital for retail shop')"
  seed_submit "$app2"
  # "Emeka Obi" is on the screening simulator's synthetic PEP register: this one raises an alert.
  local pep app3
  pep="$(seed_party 'Emeka Obi' "$(jq -nc --arg ou "$LAGOS_ID" '{type:"individual",first_name:"Emeka",last_name:"Obi",date_of_birth:"1970-01-15",gender:"male",nationality:"NG",phone:"08033330001",identities:[{type:"bvn",value:"22211122233"}],org_unit_id:$ou}')")"
  app3="$(seed_application "$pep" '5000000.00' 12 'Equipment purchase')"
  seed_submit "$app3"
  # Bureau simulator outcome by identifier suffix: BVN ending 13 = write-off + DPD (the decision refers).
  local bola app4
  bola="$(seed_party 'Bola Adeyemi' "$(jq -nc --arg ou "$LAGOS_ID" '{type:"individual",first_name:"Bola",last_name:"Adeyemi",date_of_birth:"1985-07-21",gender:"female",nationality:"NG",phone:"08037770013",email:"bola.adeyemi@example.ng",identities:[{type:"bvn",value:"22233344413"}],org_unit_id:$ou}')")"
  app4="$(seed_application "$bola" '3000000.00' 12 'Restocking a pharmacy')"
  seed_submit "$app4"
  heading "Credit facts on the demo applications (data.monthly_income, data.years_trading)"
  seed_app_data "$app1" '{"monthly_income":"3500000.00","years_trading":11}'
  seed_app_data "$app2" '{"monthly_income":"3500000.00","years_trading":6}'
  seed_app_data "$app4" '{"monthly_income":"2500000.00","years_trading":4}'

  heading "Running due outbox jobs (screening runs asynchronously)"
  # The scheduler's outbox.dispatch pushes a DispatchOutboxJob onto the database queue; a worker runs it.
  artisan schedule:run >/dev/null 2>&1 || info "schedule:run reported an error"
  artisan queue:work --stop-when-empty --tries=3 >/dev/null 2>&1 || info "queue:work reported an error (see storage/logs)"
  LOLA_JAR="$JAR"

  heading "Credit demo: credit_bureau consent + complete the checklist so cases reach Assessment"
  login "$DEMO_CMP2_EMAIL"; NGOZI_JAR="$JAR"
  seed_credit_demo "$app2" "$app4"
  artisan schedule:run >/dev/null 2>&1 || true
  artisan queue:work --stop-when-empty --tries=3 >/dev/null 2>&1 || true
  rm -f "$LOLA_JAR" "$NGOZI_JAR" "$DEV_DIR/seed-headers"

  heading "MFA enrolment for the compliance demo users"
  local who
  for who in "$DEMO_CMP1_EMAIL" "$DEMO_CMP2_EMAIL" "$DEMO_CA_EMAIL"; do
    if [[ -f "$(secret_file_for "$who")" && -n "$(sql_value "select coalesce(mfa_confirmed_at::text, '') from users where lower(email) = lower('$who')" "$tid")" ]]; then
      info "$who: enrolled, secret on file"
    else login "$who"; rm -f "$JAR"; fi
  done

  heading "Demo data ready"
  cat <<EOF
    Sign in to the SPA as (password $DEMO_PASSWORD, TOTP via '$0 totp <email>'):
      $DEMO_LO_EMAIL     Loan Officer + Documentation Officer (create, submit, upload, verify)
      $DEMO_CMP1_EMAIL    Compliance Officer + Branch Manager (propose alert dispositions, recommend, approve waivers)
      $DEMO_CMP2_EMAIL    Compliance Officer + Documentation Officer (confirm dispositions, verify lola's uploads)
      $DEMO_CA_EMAIL    Senior Credit Analyst (pull bureau, run decision, exceptions, credit memo, recommend)
    Draft application:      Adebayo Foods Limited, NGN 12.5M / 24 months  ($app1)
    Submitted application:  Chinedu Eze, NGN 2M / 12 months               ($app2)
    Screening alert:        Emeka Obi (synthetic PEP), NGN 5M             ($app3)
    Credit (Assessment):    Chinedu Eze NGN 2M (clean bureau -> approve; lower data.monthly_income to
                            e.g. 150000.00 for a counter-offer), Bola Adeyemi NGN 3M (BVN ..13: write-off -> refer)  ($app4)
    Rule set:               credit.rule_set/$DEMO_RULE_SET_KEY (evaluator 1.0.0)
    Screening runs asynchronously (outbox -> database queue). seed-demo drains it once; for live
    updates keep both running in backend/:  php artisan schedule:work   and   php artisan queue:work
EOF
}

# ---------------------------------------------------------------- commands
cmd_setup() {
  require_tools
  trap stop_server EXIT
  step_env
  step_kek
  step_migrate
  step_tenant
  step_licence_key
  step_licence
  step_enrol_remaining
  step_verify
  stop_server
  print_summary
}

cmd_serve() {
  [[ -f "$ENV_FILE" ]] || die "no backend/.env; run '$0 setup' first"
  if command -v lsof >/dev/null && lsof -nP -iTCP:"$SERVE_PORT" -sTCP:LISTEN >/dev/null 2>&1; then
    server_up && die "a Fundly API is already listening on $SERVE_HOST:$SERVE_PORT"
    die "port $SERVE_PORT is used by another program (not Fundly: $API_BASE/health is not 200). Pick a free one: SERVE_PORT=8092 $0 serve, and start Vite with FUNDLY_BACKEND_URL=http://127.0.0.1:8092"
  fi
  cd "$BACKEND_DIR" && exec php artisan serve --host="$SERVE_HOST" --port="$SERVE_PORT"
}

cmd_totp() {
  local arg="${1:-$ADMIN1_EMAIL}" secret
  if [[ -f "$arg" ]]; then secret="$(cat "$arg")"
  elif [[ -f "$(secret_file_for "$arg")" ]]; then secret="$(cat "$(secret_file_for "$arg")")"
  elif [[ "$arg" =~ ^[A-Za-z2-7=]{16,}$ ]]; then secret="$arg"
  else die "no MFA secret for '$arg' in $DEV_DIR"; fi
  local code step now; now="$(date +%s)"
  read -r code step < <(totp_code "$secret" "$now")
  echo "$code"
  printf '%s(valid ~%ss more; each code is accepted once)%s\n' "$c_dim" $((30 - now % 30)) "$c_off" >&2
}

cmd_login() {
  require_tools
  local email="${1:-$ADMIN1_EMAIL}"
  server_up || die "API not running at $API_BASE (run '$0 serve')"
  login "$email"
  api GET /api/v1/me ''; jq . <<<"$BODY"
  rm -f "$JAR"
}

cmd_reset_mfa() {
  local email="${1:?email required}" tid
  tid="$(sql_value "select id from tenants where slug = '$TENANT_SLUG'")"
  [[ -n "$tid" ]] || die "tenant $TENANT_SLUG not found"
  sql_value "update users set mfa_secret = null, mfa_confirmed_at = null, mfa_last_used_step = null where lower(email) = lower('$email') returning id" "$tid" >/dev/null
  rm -f "$(secret_file_for "$email")" "$(step_file_for "$email")" "$DEV_DIR/mfa-$email.otpauth"
  ok "MFA cleared for $email; the next login returns mfa_enrollment_required"
}

cmd_reset() {
  [[ "${1:-}" == --yes ]] || die "this DROPS ALL TABLES in $DB_DATABASE@$DB_HOST:$DB_PORT. Re-run as: $0 reset --yes"
  heading "Dropping and re-creating the schema"
  artisan migrate:fresh --force --database=pgsql_owner --no-interaction
  rm -f "$LICENCE_FILE" "$DEV_DIR"/mfa-*.secret "$DEV_DIR"/mfa-*.laststep "$DEV_DIR"/mfa-*.otpauth
  ok "schema rebuilt; KEK, licence signing key and .env kept. Run '$0 setup' next."
}

case "${1:-setup}" in
  setup) cmd_setup ;;
  serve) cmd_serve ;;
  totp) shift; cmd_totp "$@" ;;
  login) shift; cmd_login "$@" ;;
  reset-mfa) shift; cmd_reset_mfa "$@" ;;
  reset) shift; cmd_reset "$@" ;;
  seed-demo) cmd_seed_demo ;;
  -h|--help|help) sed -n '2,22p' "$0" ;;
  *) die "unknown command '$1' (setup|serve|totp|login|reset-mfa|reset|seed-demo)" ;;
esac
