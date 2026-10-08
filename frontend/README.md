# Fundly LOS — staff SPA (frontend)

React 19 + TypeScript 5 (strict) + Vite single-page app for bank staff. It
talks only to the Laravel API in `../backend` over a same-origin Sanctum
cookie session (TRD §2.2 / §3, D-031). Tasks P0-UX-01 + P0-FE-01.

## Run it locally

```bash
# 1. Backend (once): env, migrations, dev tenant, admins, licence, MFA
../deploy/dev/bootstrap-local.sh            # see the script header for subcommands
../deploy/dev/bootstrap-local.sh serve      # php artisan serve on 127.0.0.1:8000

# 2. Frontend
npm ci
npm run dev                                 # http://localhost:5173
```

Sign in with a dev admin that the bootstrap printed, for example `ada@fundly.test`.
Get the current authenticator code with `../deploy/dev/bootstrap-local.sh totp ada@fundly.test`.
Each code works once per 30-second window, so if the script just used one, wait for the next.

Vite proxies `/api`, `/sanctum`, `/health` and `/ready` to `FUNDLY_BACKEND_URL`
(default `http://127.0.0.1:8000`). The proxy uses `changeOrigin: false`, so
Laravel sees `Host: localhost:5173`. Sanctum then treats the browser as a
stateful first-party SPA, and the `fundly_session` and `XSRF-TOKEN` cookies
land on the Vite origin. If you run Vite on a different port
(`npm run dev -- --port 5174`), add that `host:port` to
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
    dashboard/    DashboardPage + api.ts adapter (MOCK until P1-RPT-01)
    placeholders/ routed EmptyState pages for P1+ modules
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

## Known gaps
- **Dashboard figures are mock data.** `features/dashboard/api.ts` is marked
  `TODO(P1-RPT-01): replace with /api/v1/reports/operational`. The page says
  it is sample data. Nav count badges come from the same adapter.
- Search and notifications are placeholders that show a toast. Placeholder
  routes show EmptyStates until their modules ship.
- Tailwind v3 is mandated. `npm audit` flags its build-time dependencies
  (`braces`, `micromatch` via chokidar/fast-glob). They are dev-only and
  never ship to the browser.
