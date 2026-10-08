#!/usr/bin/env bash
# Fundly LOS - local development bootstrap (NOT for any shared or production use).
#
# Makes backend/ runnable on this machine for frontend development:
#   .env -> KEK -> migrations -> dev tenant + 2 admins -> licence keypair ->
#   dev licence -> import (maker = admin 1) -> approve via API (checker = admin 2)
#
# Usage:
#   deploy/dev/bootstrap-local.sh [setup]     everything (default, idempotent)
#   deploy/dev/bootstrap-local.sh serve       php artisan serve on 127.0.0.1:8000
#   deploy/dev/bootstrap-local.sh totp EMAIL  print the current TOTP code for an admin
#   deploy/dev/bootstrap-local.sh login EMAIL sign in via the API (smoke test, needs a running server)
#   deploy/dev/bootstrap-local.sh reset-mfa EMAIL  clear an admin's MFA so the next login re-enrols
#   deploy/dev/bootstrap-local.sh reset --yes drop ALL tables in the dev DB, forget licence + MFA secrets
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
SERVE_PORT="${SERVE_PORT:-8000}"
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
  -h|--help|help) sed -n '2,20p' "$0" ;;
  *) die "unknown command '$1' (setup|serve|totp|login|reset-mfa|reset)" ;;
esac
