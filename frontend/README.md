# Fundly LOS — staff SPA (frontend)

React 19 + TypeScript 5 (strict) + Vite single-page app for bank staff. It
talks only to the Laravel API in `../backend` over a same-origin Sanctum
cookie session (TRD §2.2 / §3, D-031). Tasks P0-UX-01 + P0-FE-01.

## Run it locally

```bash
# 1. Backend (once): env, migrations, dev tenant, admins, licence, MFA
../deploy/dev/bootstrap-local.sh            # see the script header for subcommands
../deploy/dev/bootstrap-local.sh serve      # php artisan serve on 127.0.0.1:8091 (SERVE_PORT=… to change)

# 2. Demo data (needs the API running): org, live product, business users, parties, applications
../deploy/dev/bootstrap-local.sh seed-demo

# 3. Async work (screening runs from the outbox via the database queue) — keep both running
(cd ../backend && php artisan schedule:work)
(cd ../backend && php artisan queue:work)

# 4. Frontend
npm ci
FUNDLY_BACKEND_URL=http://127.0.0.1:8091 npm run dev -- --port 5180
```

The backend port defaults to **8091** because 8000/8010 are often taken by other
local Laravel apps (the script refuses to start on a port another app holds, and
`/health` must answer 200 for it to count as Fundly). The Claude launch config
`fundly-frontend` (`Loanoriginator/.claude/launch.json`) starts Vite on 5180 with
`FUNDLY_BACKEND_URL=http://127.0.0.1:8091`. `localhost:5180` is already in
`SANCTUM_STATEFUL_DOMAINS`.

Sign in with a dev admin that the bootstrap printed, for example `ada@fundly.test`.
Get the current authenticator code with `../deploy/dev/bootstrap-local.sh totp ada@fundly.test`.
Each code works once per 30-second window, so if the script just used one, wait for the next.

### Demo data (`seed-demo`)

`seed-demo` drives the public API with curl (maker-checker included), so it
exercises the same rules as a real tenant. It is idempotent: re-running it finds
what earlier runs created. It:

1. binds the simulator adapters (`php artisan integration:bind-simulators`);
2. as **ada**, creates legal entity `DEMO` with branches `LAGOS` and `KANO`;
3. creates three business users (password `Demo-Dev-Passw0rd!`, override with
   `DEMO_PASSWORD`), clones library role templates into tenant roles and
   assigns them; every assignment and the extra role permissions are requested
   by **ada** and approved by **bo** (a request for the same user goes stale once
   the previous one executes, so they are done one at a time);
4. authors the `sme-term-loan` product (content as in
   `backend/tests/Support/LendingFixtures.php::smeTermLoan()` plus an optional
   board-resolution item), **bo** reviews, **ada** requests activation (202) and
   **bo** approves the change request;
5. as **lola**, creates Adebayo Foods Limited (+ director/shareholder Funke
   Adebayo), Chinedu Eze and Emeka Obi with consents, one draft and two
   submitted applications, then runs the scheduler + queue once so screening
   happens (Emeka Obi is on the simulator's synthetic PEP list → an open alert);
6. activates the `credit.rule_set` artefact `sme-policy` (the product binds
   `rule_set: sme-policy`) with the same maker-checker flow as the product;
7. sets `data.monthly_income` / `data.years_trading` on the demo applications
   (PATCH with If-Match), makes sure every primary applicant has the
   `credit_bureau` consent, and moves Chinedu Eze and Bola Adeyemi into
   **Assessment** (BVN verified, mandatory checklist uploaded by lola and
   verified by ngozi);
8. signs chidi, ngozi and tunde in once so their MFA secrets are on file.

Bureau simulator outcomes follow the identifier suffix: `…00` no hit (thin file),
`…13` write-off + DPD (refer), `…77` many enquiries (refer), anything else a clean
score of 600–799. Bola Adeyemi's BVN ends in 13. For a counter-offer, lower
Chinedu Eze's `data.monthly_income` (e.g. `1600000.00`) and run the decision
again: the DSR passes 40 % and the engine recommends a smaller amount.

| User | Roles (cloned templates) | Use it for |
|---|---|---|
| `lola@fundly.test` | Loan Officer (+ `legal_entity:read`, `org_unit:read`), Documentation Officer | new application wizard, submit, KYC verify/consents, upload, verify, waiver request |
| `chidi@fundly.test` | Compliance Officer, Branch Manager | propose alert dispositions, recommend, approve waivers |
| `ngozi@fundly.test` | Compliance Officer, Documentation Officer | confirm dispositions chidi proposed (four-eyes), verify lola's uploads |
| `tunde@fundly.test` | Senior Credit Analyst | Credit tab: pull bureau, run decision, replay, exceptions, credit memo, recommend |

The admins (tenant administrator) deliberately hold no application permissions.
TOTP for any of them: `../deploy/dev/bootstrap-local.sh totp <email>`.

Vite proxies `/api`, `/sanctum`, `/health` and `/ready` to `FUNDLY_BACKEND_URL`
(default `http://127.0.0.1:8091`, the bootstrap default). The proxy uses `changeOrigin: false`, so
Laravel sees `Host: localhost:5173`. Sanctum then treats the browser as a
stateful first-party SPA, and the `fundly_session` and `XSRF-TOKEN` cookies
land on the Vite origin. If you run Vite on a different port
(`npm run dev -- --port 5180`), add that `host:port` to
`SANCTUM_STATEFUL_DOMAINS` in `backend/.env`. Sanctum only treats a request as
stateful when its `Origin`/`Referer` is in that list, so never serve the SPA
with `Referrer-Policy: no-referrer`.

| Script | What it does |
|---|---|
| `npm run dev` | Vite dev server on port 5173 with the API proxy |
| `npm run build` | Runs `tsc -b`, then a production build into `dist/` with code-split routes |
| `npm run typecheck` | Runs `tsc --noEmit` in strict mode, with `noUncheckedIndexedAccess` |
| `npm run lint` | ESLint flat config, zero warnings allowed (rules listed below) |
| `npm run test` | Vitest + Testing Library in jsdom |
| `npm run contrast` | WCAG 2.2 AA check of every token pair (`scripts/contrast-check.py`) |
| `npm run gen:api` | Regenerates `src/api/schema.d.ts` from `../api/openapi/openapi.yaml` |
| `npm run verify` | Runs all of the above in CI order |

## Architecture

```
src/
  app/            router (data router, lazy routes), query client, shell
                  (AuthenticatedLayout, TitleBar), session bridge, route errors
  api/            THE network boundary: generated schema.d.ts, client.ts
                  (openapi-fetch + policy), problem.ts (RFC 9457 ApiProblem), auth.ts
  components/     design-system components (token-only styling, accessible)
  features/
    auth/         LoginPage + login state machine, session queries,
                  StepUpProvider / useStepUp, safe post-login redirect
    navigation/   nav config (permission-driven) + Sidebar
    applications/ queues (/applications, /pipeline), wizard/ (/applications/new),
                  case/ (/applications/:id + tabs), domain/ (pure, tested: stage
                  tracker mapping, action availability, status groups), queries.ts
                  (TanStack keys + useIfMatchMutation)
    parties/      /parties, /parties/:id, shared PartyCreateForm (live dedupe),
                  PartySearch, IdentitiesPanel, ConsentsPanel, DirectorsEditor
    compliance/   /compliance/alerts queue + drawer, fourEyes.ts (tested)
    dashboard/    DashboardPage + api.ts adapter (recent applications and the
                  stage donut are live; the rest MOCK until P1-RPT-01)
    placeholders/ routed EmptyState pages for modules not built yet
  design-tokens/  tokens.css (single source of truth) + tailwind-preset.js
  lib/            formatting (en-NG, ₦, Africa/Lagos), cn()
```

### API client (`src/api/client.ts`)
- Types come from the OpenAPI contract: `openapi-typescript` generates
  `schema.d.ts`, and `openapi-fetch` calls it (`api.GET('/api/v1/me')`).
  Do not hand-write request or response types. Rerun `npm run gen:api` when
  the contract changes.
- Every request gets `credentials: 'include'`, `Accept: application/json`,
  and `X-XSRF-TOKEN`, which is re-read from the `XSRF-TOKEN` cookie on every
  attempt because the session regenerates at login.
- Every POST gets an `Idempotency-Key` (`crypto.randomUUID()`). Retries reuse
  the same key.
- Any non-2xx response throws a typed `ApiProblem` (`type`, `code`, `status`,
  `detail`, `correlationId`, field `errors`, extensions).
- On 419 (CSRF mismatch) the client fetches `/api/v1/auth/csrf-cookie` and
  retries once.
- On 401 `step-up-required` it calls the handler that `<StepUpProvider>`
  registered. That opens `StepUpDialog` (password + TOTP →
  `POST /api/v1/auth/step-up`), and the original request is retried once on
  success. Concurrent demands share one dialog.
- On 401 `unauthenticated`, `session-expired` or `session-revoked` while
  signed in, the session bridge clears the query cache and routes to
  `/login?reason=session-ended`, which shows the "Your session ended" banner.
  A 401 from login, MFA or step-up means bad credentials and is shown inline.
- Multipart uploads go through the same client: pass the typed `body` plus
  `bodySerializer: formBody({...fields, file})`; openapi-fetch leaves
  Content-Type to the browser (boundary) and the policy (XSRF, Idempotency-Key,
  419/step-up retries) applies unchanged. Binary downloads use `parseAs: 'blob'`
  and `saveBlob()` (`lib/download.ts`, short-lived object URL).
- `src/api/lending.ts` names the generated types and wraps the P1 endpoints;
  application reads/mutations return `{ data, etag }` so the next mutation can
  send `If-Match`.
- Lint forbids `fetch`, `XMLHttpRequest` and `axios` everywhere else.

### Auth and session
Sign-in calls `GET /auth/csrf-cookie`, then `POST /auth/login`. The response
is `authenticated`, `mfa_required` or `mfa_enrollment_required`. For
enrolment, the setup key and `otpauth://` URI are shown as text; there is no
external QR service. `POST /auth/mfa/verify` completes sign-in.

The flow is a pure reducer (`loginMachine.ts`, unit-tested). After sign-in,
`/me` and `/me/effective-access` are cached in TanStack Query, in memory only.
The `/` route loader guards every authenticated route. Navigation is **deny
by default**: an item renders only if the server listed one of its
permissions (`features/navigation/nav.ts`). The placeholder pages enforce the
same rule and show "You don't have access" otherwise.

Nothing is ever written to `localStorage`, `sessionStorage` or IndexedDB;
lint enforces this. There is no `dangerouslySetInnerHTML` (also enforced) and
no CDN asset: fonts are self-hosted with `@fontsource`, and the build sets
`assetsInlineLimit: 0`, so a `default-src 'self'` CSP works.

### Code splitting
Login, shell, dashboard and placeholder pages are lazy routes. React,
TanStack Query and the forms stack are separate long-lived vendor chunks.
The forms stack (`react-hook-form` + `zod`) is not in the entry; it loads
only with a route that renders a form. Initial JS for `/login` is about
120 kB gzip for entry + React + Query, plus about 38 kB gzip for the login
route, forms and components.

## Design tokens: rules

Visual values are re-themed per PO decision **D-042** (`loan-ui.jpg`). The
token architecture follows **D-027**.

1. **`src/design-tokens/tokens.css` is the only place colours, sizes and
   fonts are defined.** Primitives (`--p-*`) feed semantic tokens
   (`--color-*`, `--radius-*`, `--space-*` …). Components use semantic
   tokens only.
2. **Tailwind's theme is replaced, not extended** (`tailwind-preset.js`).
   Stock classes like `bg-blue-500` and `p-7` do not exist, so an ad-hoc
   value produces no style and gets caught in review. Use `bg-surface`,
   `text-primary`, `text-emphasis` (forest headings), `bg-accent` (forest
   primary), `bg-accent-fill` / `bg-selected` (lime), `rounded-card`,
   `rounded-pill`, and so on. The cheat-sheet is at the top of the preset.
   `text-heading` is the page-title **font size**; the heading **colour** is
   `text-emphasis`.
3. **No hex literals in `.ts`/`.tsx`** outside `src/design-tokens`; ESLint
   fails the build. SVG charts colour themselves with
   `fill-[var(--color-chart-1)]` style classes.
4. **Lime is a fill, never text.** It goes behind dark-green ink only:
   active nav pill, accent buttons, progress track, the positive trend chip
   and chart series 2. Lime graphics on white always get the
   `--color-accent-fill-edge` stroke so their boundary meets 3:1.
5. **Contrast is a gate.** `npm run contrast` checks 116 graded text and UI
   pairs against WCAG 2.2 AA (4.5:1 text, 3:1 UI and large text), including
   every text token on every surface. To add a token pair a component uses,
   add it to `PAIRS` in `scripts/contrast-check.py`.
6. Colour never carries meaning alone. Status pills, SLA chips, trend chips
   and chart legends always print a text label, and every chart has a table
   or text alternative.
7. Accessibility baseline:
   - visible `:focus-visible` rings (lime on brand surfaces)
   - targets of at least 24px
   - `prefers-reduced-motion` respected
   - native `<dialog>` for modals and the mobile drawer
   - polite live region for toasts
   - `aria-current="page"` on the active nav item
   - `aria-sort` on sortable columns
   - a skip link

### Responsive shell
| Width | Layout |
|---|---|
| ≥ 1024 px | full sidebar (260 px) |
| 768–1023 px | 76 px icon rail (labels stay available to assistive tech) |
| < 768 px | the title bar's menu button opens a `<dialog>` drawer |

The dashboard has 3 columns at ≥ 1280 px, 2 at ≥ 1024 px and 1 below that.

## Origination slice (P1-FE-02)

| Route | Screen | Notes |
|---|---|---|
| `/applications`, `/pipeline` | WRK-03 / WRK-02 | stat tiles from `applicationStats`, status-group chips, search, "Mine only", cursor "Load more"; filters live in the URL |
| `/applications/new` | APP-01..03 | product cards → find/create applicant (Zod mirrors backend rules, live `matchParties` dedupe, `possible_duplicates` card, inline directors for companies) → legal entity/branch + terms validated against the product range → review → `createApplication` (one Idempotency-Key per review) |
| `/applications/:id` (+ `/kyc`, `/documents`, `/credit`, `/timeline`) | APP-04..10 | context bar with the status/permission-chosen primary action, Actions menu (hold, return, withdraw, cancel, recommend) with reason codes, 12-step stage tracker, route tabs (arrow keys). Every mutation sends `If-Match`; a 412 shows "This application changed — reload" and keeps typed input. KYC gate and the case header poll every 10 s while screening is pending |
| `/compliance/alerts` | CMP-01/02 | status chips, drawer via `?alert=` (deep-linkable from the KYC tab), propose / confirm with the four-eyes rules and the server's refusal shown verbatim |
| `/parties`, `/parties/:id` | PTY-01 lite | search, masked identifiers + Verify, consents grant/withdraw + history, directors/owners, the customer's applications |

### Credit tab (P1-FE-03, SCR-CRD-01..04)

`/applications/:id/credit` (between Documents and Timeline), code in
`features/applications/credit/` (pure logic in `domain.ts`, tested) and
`api/credit.ts`:

- **Credit bureau**: party chooser (primary first), Pull / Pull again
  (`bureau:pull`), latest report with validity and hit/no-hit pills, score on a
  five-band ramp (bands align with the sme-policy grade table), summary tiles
  (flagged tiles for DPD > 30, delinquency, enquiries > 5, write-off),
  facilities table, earlier reports collapsed. A 422 `bureau-consent-missing`
  shows "Bureau enquiry blocked" with a link to
  `/applications/:id/kyc?party=…#consents` (the KYC tab preselects the party and
  scrolls to the consents panel).
- **Decision**: Run decision (`credit:analyse`, needs Assessment and a valid
  primary report; the server's problem detail is shown verbatim), a forest hero
  card (outcome chip, grade badge, recommended amount and terms), ordered
  reason codes with stage, requested → recommended terms, affordability with a
  DSR meter (lime allowed zone up to the max, forest fill, danger fill when over),
  versions + Replay ("Replayed with evaluator 1.0.0 and rule set v1: identical"
  or the differences), "How this was decided" trace (decision-table rows with
  Hit/No), facts as a dotted key/value table, earlier decisions collapsed, and
  an "Inputs changed after this decision" warning when income/amount/tenor
  changed since the snapshot.
- **Exceptions** (`exception:raise`): reason code from the decision's codes,
  justification (≥ 20 chars), evidence ref, severity; the list notes that each
  one escalates approval authority.
- **Credit memo** (`credit:analyse`): auto-populated sections as read-only
  cards, recommendation / amount / tenor / narrative (≥ 30 chars) / conditions
  editor (react-hook-form + `memoSchema`), "Save as vN", version history.
- **Ready to recommend**: Bureau report ✓ · Decision ✓ (on the current report) ·
  Memo ✓ (references the latest decision). The Recommend dialog shows the same
  checklist and the server's 422 `blockers` (strings or `{code, message}`).
- Outside Assessment the tab is read-only with an info banner.

Permission-aware actions stay visible but inert (`aria-disabled`, reason as
tooltip and screen-reader text) via `Button disabledReason`.

## Known gaps
- **Dashboard figures are mock data.** `features/dashboard/api.ts` is marked
  `TODO(P1-RPT-01): replace with /api/v1/reports/operational`. The page says
  it is sample data. Nav count badges come from the same adapter.
- Notifications are a placeholder toast; the title-bar search opens
  `/applications?q=…`. Inbox, Products, Users and Audit are still placeholders.
- Upload progress is phase-based (uploading → scanned clean / quarantined), not
  per-byte: `fetch` has no upload progress and XHR is outside the client policy.
- Timeline actors are shown as "You", "Workflow engine" or a short staff id:
  the timeline API returns actor ids only.
- PII "Reveal" (`POST /pii/unmask`) is not in the P1 contract; values stay masked.
- Tailwind v3 is mandated. `npm audit` flags its build-time dependencies
  (`braces`, `micromatch` via chokidar/fast-glob). They are dev-only and
  never ship to the browser.
