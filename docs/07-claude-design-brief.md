# 07 — Claude Design Brief: Fundly LOS

| | |
|---|---|
| **Product** | Fundly LOS (working name, D-028): staff-facing loan origination for Nigerian commercial banks, installed on-prem per bank (D-030, D-032) |
| **Version** | 1.0, 8 October 2026 |
| **Governing decision** | **D-027**: Fundly v3 visual language on the AuditPro component architecture, contrast-corrected, responsive |
| **Audience** | Claude Design (screen generation); frontend engineers and the frontend agent (React 19 + TypeScript + Tailwind, D-031) |
| **Companion files** | `design-tokens.css` (token source of truth) · `design-tokens.tailwind.js` (Tailwind preset) · `contrast-check.py` (WCAG gate; parses the CSS) |
| **Inputs read** | Design handoff README + `Fundly LOS v3.dc.html` (inline styles and script constants) · Modernist `readme.md` + `styles.css` · `AuditPro_GRC_Design_System.md` · `01-requirements-inventory` · `02-gap-register` (G-45, G-46 and lifecycle gaps) · `03-TRD` (§1.3, §5.3, §6, §8, §10.2) · `phase_map.py` · `decision-log` (D-001…D-036). `design-system.md` ignored per G-45. |
| **Status** | Issued for design. Changes go through the decision log. |

## Contents

0. [How to use this brief in Claude Design](#0-how-to-use-this-brief-in-claude-design) (with the copy-paste prompt)
1. [Conventions](#1-conventions)
2. [Design principles](#2-design-principles)
3. [Design tokens](#3-design-tokens) (single source, contrast results)
4. [Responsive model](#4-responsive-model-nfr-015)
5. [App shell and navigation](#5-app-shell-and-navigation)
6. [Standard states and error mapping](#6-standard-states-and-error-mapping)
7. [Patterns](#7-patterns)
8. [Screen inventory](#8-screen-inventory) (summary, then every screen)
9. [User flows](#9-user-flows)
10. [Component usage guide](#10-component-usage-guide)
11. [Accessibility and quality gates](#11-accessibility-and-quality-gates)
12. [Conflicts, ambiguities and open questions](#12-conflicts-ambiguities-and-open-questions)

---

## 0. How to use this brief in Claude Design

**What Claude Design produces from this brief.** High-fidelity screens for the staff SPA, built only from the tokens in §3 and the components in §10, with every state in §6 drawn for each screen. Frontend engineers then implement those screens with the same component names.

**Order of work.**
1. Attach `design-tokens.css` and this brief to the Claude Design project. Tell it the CSS file is the only allowed source of colour, type, spacing, radius, shadow and motion values.
2. Paste the **MVP prompt** below. It generates the MVP screens in four batches (A to D). Review each batch before asking for the next.
3. For every screen, ask for these frames: desktop 1440, laptop 1180, tablet 820 and, only where §4 allows it, mobile 390. Then ask for the state frames listed in the screen's spec (§8). Each screen has an ID such as `SCR-APV-01`; keep that ID in the frame name so engineers can trace it.
4. Check every generated screen against the acceptance checklist in §11.3 before you accept it. Reject screens with colours, fonts, radii or components that are not in §3 and §10.
5. Generate later-phase screens (§8.1, Phase = Later) only when their phase starts. Their specs here are complete enough to design from, but their requirements may still move.

**What Claude Design must not do.**
- Invent colours, tints, gradients, fonts, icon sets or components. Icons are Lucide only.
- Use `#2383e2` for text or filled buttons; use `#9b9a97` or `#b3b1ad` for text. These fail WCAG (§3.2).
- Put meaning in colour alone. Every status has a text label.
- Design the fixed 1440 px v3 layout only. Every screen must be drawn responsive per §4.
- Show real customer data. Use the fictional v3 demo data (Adaeze Okonkwo, Brightpath Logistics Ltd, Sunrise Pharmacy Ltd, Institution A).

### 0.1 Copy-paste prompt (MVP screens first)

Paste the block below into Claude Design as the first message, with `design-tokens.css` and this brief attached.

```text
You are designing Fundly LOS, a staff-facing loan origination system for a Nigerian commercial bank
("Institution A"). It is installed on-prem. Users are loan officers, documentation officers, credit
analysts, approvers, compliance officers, legal/collateral officers, disbursement makers and checkers,
product managers, tenant administrators and read-only auditors. Build HIGH-FIDELITY screens.

SOURCES (attached): design-tokens.css is the ONLY source of colour, type, spacing, radius, shadow,
focus and motion. The brief (07-claude-design-brief.md) defines screens (§8), states (§6),
patterns (§7) and components (§10). Follow screen IDs exactly (e.g. SCR-WRK-01) in frame names.

VISUAL LANGUAGE (Fundly v3, contrast-corrected):
- Notion-like, flat, dense, ruled with hairlines (--color-border), not shadows. White page, #f7f7f5
  sidebar, #fbfbfa table header and property panels. Archivo everywhere; tabular numerals for numbers;
  reference numbers (LN-2026-04871, IDMP-DI-01188-A7F3, account numbers) in --font-mono.
- One accent for action, links and selection: --color-accent #1a6fc4 (white text on it). #2383e2 is
  allowed only for non-text graphics (stage-tracker dots, progress fill, selected document region).
- Text: primary #37352f, secondary #5f5e5b, tertiary #6b6a66 (meta, eyebrows, table headers).
  Never use #9b9a97 or #b3b1ad for text.
- Status chips: 3px radius, 11–12px text, tone pairs success #1c6c47/#dbeddb, warning #7a5d0d/#fdecc8,
  danger #b3261e/#ffe2dd, info #1a4d80/#d3e5ef, neutral #5f5e5b/#f1f0ef. Every chip has a text label;
  SLA chips add a 6px square dot.
- Radii: 5px controls, 6px cards, 3px chips, 8px modals/drawers. Shadows only on buttons, the selected
  segmented option, the rendered document page, and overlays.
- Inputs, checkboxes and radios have a #8b8a86 border (3:1). Focus is a 2px #1a6fc4 outline with a 2px
  offset on every interactive element. Minimum pointer target 24x24px (44px on touch layouts).
- Sentence-case labels. Eyebrow labels 11px/600/uppercase/0.03em in tertiary.
- Money: ₦ with en-NG grouping, e.g. ₦4,500,000 in lists and ₦9,382,250.00 in financial detail.
  Dates: 27 Aug 2026, 09:42 WAT (Africa/Lagos).

LAYOUT: left sidebar 216px (groups per role), screen title bar 48px, content column scrolls.
Case workspace = context bar (applicant, ref, amount, product, term, stage, SLA, actions) + sticky tab
row + 3-column grid 186px stage tracker | content | 288px properties rail. Document review is a
side-peek (document pane + field list), not a modal.
RESPONSIVE: desktop >=1280 full grid. Laptop 1024–1279: properties rail becomes a right drawer.
Tablet 768–1023: sidebar becomes a 56px icon rail; case grid becomes one column with the stage tracker as
a horizontal stepper; tables become stacked cards. Mobile <768: only Task inbox and Approvals.

STATES: draw for every screen: loading (skeleton), empty, error (problem+json mapped per §6.2), success
toast, permission-denied, and where listed: masked PII, read-only auditor, maker-checker pending,
SoD-blocked, step-up dialog.

GENERATE IN BATCHES; stop after each batch for review.
Batch A (shell and work): SCR-GLB-01 Sign in, SCR-GLB-02 MFA, SCR-GLB-03 Step-up dialog,
  SCR-WRK-01 Task inbox, SCR-WRK-02 Pipeline, SCR-WRK-03 Applications, SCR-APP-04 Case summary.
Batch B (origination to credit): SCR-APP-01 New application, SCR-APP-02 Capture form, SCR-APP-03
  Review & submit, SCR-APP-05 Applicant & KYC, SCR-APP-06 Documents, SCR-DOC-01 Document review
  side-peek (manual-verification variant), SCR-DOC-02 Waiver, SCR-CMP-01 Screening alerts, SCR-CMP-02
  Alert disposition (four-eyes), SCR-CRD-01 Credit assessment, SCR-CRD-04 Credit memo & recommendation.
Batch C (approval to booking): SCR-APV-01 Decision packet, SCR-APV-02 Vote dialog, SCR-OFR-01 Offer,
  SCR-OFR-02 Acceptance & execution, SCR-APP-08 Conditions, SCR-CPR-01 Pre-disbursement check,
  SCR-DSB-01 Disbursement instruction, SCR-DSB-02 Release (checker), SCR-DSB-03 Posting status with
  failed -> sending -> acknowledged and failed-intervention states, SCR-DSB-06 Booked & handover.
Batch D (configuration, admin, assurance): SCR-PRD-01/02 Products and version editor, SCR-PRD-03 Rules
  editor, SCR-PRD-04 Approval matrix, SCR-ADM-05 Role assignments, SCR-ADM-06 SoD, SCR-ADM-07
  Change-request inbox, SCR-ADM-10 Adapter bindings, SCR-ADM-12 Licence, SCR-AUD-01 Audit explorer,
  SCR-AUD-02 Application reconstruction, SCR-AUD-03 Evidence pack export.
Use the fictional v3 demo data. Do not invent components, colours or fonts.
```

---

## 1. Conventions

| Marker | Meaning |
|---|---|
| **[F]** | **Fact** taken from an input. The source follows in brackets, e.g. [F v3], [F TRD §6.3], [F AuditPro §6.5], [F D-036]. [F commission] means the commissioning instructions for this deliverable (breakpoints, mobile scope, required flows and patterns). |
| **[R]** | **Recommendation** by the UI/UX lead. It is not in any input and is open to PO objection through the decision log. |
| `SCR-<AREA>-nn` | Screen ID. Areas: GLB global, WRK work, APP application/case, PTY party, DOC documents, CRD credit, APV approvals, OFR offer, CPR conditions, DSB disbursement, CMP compliance, AUD audit, RPT reporting, PRD product/configuration, ADM administration, OPR platform operator, POR applicant portal, LED leads. |
| `UF-nn` | User flow (§9). |
| Requirement IDs | BRD IDs (FR-APP-008); LOS IDs for unnumbered and inferred requirements (LOS-FR-301); NFR IDs (NFR-014). |
| **Phase** | **MVP** = phase P0 or P1 in `phase_map.py`, as scoped by D-036. **Later** = P2–P6 or V2, with the phase given. Where a screen ships in the MVP and gains features later, the later parts are marked inline. |
| Role codes | APL applicant · LO loan officer/RM · OPS branch operations/documentation officer (incl. branch manager) · CA credit analyst · APR approver/committee · CMP compliance/AML · LEG legal/collateral officer · DM disbursement maker · DC disbursement checker · PM product manager · ADM tenant administrator · AUD auditor/regulator (read-only) · OPR platform operator |
| Permissions | `resource:action` per TRD §8.1 [F]. Names used in the TRD are facts. Other names in this brief are proposals [R] for the M02 permission catalogue. |
| Endpoints | Paths are relative to `/api/v1` and taken from TRD §10.2 [F]. Paths marked [R] are not in the catalogue and are proposed additions. |

## 2. Design principles

1. **Status by text, colour as support.** Every state has a word. Colour reinforces it. [F Modernist/v3 "semantic colours carry status only"; R for the text-label rule, WCAG 1.4.1]
2. **One accent.** Blue means "you can act here". Status colours never appear on actions, except the danger button. [F v3]
3. **Dense where work is repetitive, calm where decisions are made.** Queues and dashboards use 12.5 px table rows at 32 px height. Decision screens (packet, vote, release) use 14 px body and generous spacing. [R]
4. **Show the control, not just the result.** Who can act, why an action is blocked (SoD, authority, maker-checker, licence), and what the system did (idempotency key, attempt log, decision snapshot) are always visible. [F v3 disbursement screen; R as a general rule]
5. **Nothing silent.** Integration failures appear as actionable exceptions with a next step (LOS-CON-008). The UI never shows success for a request whose outcome is unknown. [F PR-08]
6. **Keyboard-complete.** Every action reachable and operable by keyboard, with a visible focus ring. [F NFR-014]
7. **Least exposure.** PII is masked by default; revealing it is a deliberate, logged act. [F FR-CMP-036]

---
## 3. Design tokens

### 3.1 Source of truth and rules

- `design-tokens.css` is the **single source** [R, implements D-027]. It has two layers:
  - **Primitives** (`--p-*`) hold raw values. Components never use them.
  - **Semantic tokens** (`--color-*`, `--text-*`, `--space-*`, `--radius-*`, `--shadow-*`, `--duration-*`, `--z-*`, `--layout-*`) are what components use.
- `design-tokens.tailwind.js` maps every semantic token into Tailwind. It **replaces** Tailwind's default colour, spacing, radius, shadow, font, z-index and duration scales, so `bg-blue-500` or `text-gray-400` simply do not compile. (Verified: a test build with Tailwind 3.4.17 generated zero stock-palette classes.) Opacity modifiers on tokens (`bg-accent/50`) are not supported, deliberately: tints are tokens.
- `contrast-check.py` parses the CSS file and grades every allowed pair. It runs in CI next to axe (TRD §12.1, NFR-014) and fails the build on any failing pair.
- **No literal colour, font name, radius, shadow or px spacing in component code.** ESLint rule [R]: forbid hex/rgb literals and arbitrary Tailwind values (`[#…]`, `[13px]`) outside `design-tokens.*`.
- **Fonts are self-hosted** [R]. The Modernist stylesheet imports Archivo from Google Fonts [F Modernist styles.css]. An on-prem, possibly air-gapped installation with CSP `default-src 'self'` [F TRD §8.4, D-030] cannot load it. Use `@fontsource-variable/archivo` (variable weights cover v3's 450) and `@fontsource/roboto-mono` 400/500.
- **Light theme only for v1** [R]. v3 defines no dark theme. The two-layer token structure allows one later without touching components.
- **Tenant branding (FR-TEN-004, P5)** [R]: a tenant colour may replace the brand mark and document letterhead only. It may replace `--color-accent` only if `contrast-check.py` passes with it. The configuration validator (FR-CFG-002) runs that check before activation.

### 3.2 Colour

**What changed from v3 and why** (all ratios computed by `contrast-check.py`; full table in §3.3):

| v3 value | Role in v3 | Problem | Corrected token | New ratio |
|---|---|---|---|---|
| `#2383e2` accent | Primary button fill, selected filter chip, links, focus | White on it 3.88:1; as link text 3.88:1 (needs 4.5) | `--color-accent` = **`#1a6fc4`** (v3's own hover colour). `#2383e2` stays as `--color-accent-graphic`, for non-text graphics only (3.88:1 ≥ 3:1). | 5.10:1 |
| `#1a6fc4` accent hover | Hover | Now the base | `--color-accent-hover` = **`#155ea8`**, `--color-accent-pressed` = **`#124f8e`** | 6.57, 8.28 |
| `#787774` tertiary | Meta lines, inactive tabs, labels | 4.48:1 on white, 4.17:1 on sidebar | `--color-text-tertiary` = **`#6b6a66`** | 5.41 on white; ≥ 4.54 on every allowed surface |
| `#9b9a97` muted | Eyebrows, table headers, meta, timestamps (75 uses in v3) | 2.81:1 | Text uses `--color-text-tertiary`. `#9b9a97` survives only as `--color-text-disabled` (inside disabled controls, which WCAG 1.4.3 exempts). | 5.41 |
| `#b3b1ad` faint | Nav counts | 2.14:1; 2.00:1 on sidebar | Counts use tertiary (secondary inside the active nav item). `#b3b1ad` survives as `--color-decorative` (separators only). | 5.05 |
| `#8a6a10` warn text | Warn chips and flags on `#fdecc8` | 4.34:1 | `--color-warning-fg` = **`#7a5d0d`**. `#8a6a10` stays as score band 3 (non-text). | 5.30 |
| `0 0 0 2px rgba(35,131,226,0.2)` | Input focus ring | 1.28:1 against white: not a visible indicator | 2px solid `--focus-ring-color` (`#1a6fc4`), 2px offset; inputs use border + 1px inner ring in accent | 5.10 |
| `rgba(55,53,47,0.14)` | Input border | 1.28:1: input boundary invisible to low-vision users (WCAG 1.4.11) | `--color-border-control` = **`#8b8a86`** | 3.46 |
| `#d3d1cd` | Pending stage dot border | 1.52:1 | `--color-indicator` = **`#8b8a86`** | 3.46 |
| AuditPro `#718096`, gold `#D4AF37` | Secondary text; active nav indicator | 4.02:1; 2.10:1 | Not adopted. The Fundly v3 palette governs (D-027). | — |

**Unchanged v3 values that pass** [F v3]: text primary `#37352f` (12.26), secondary `#5f5e5b` (6.48), surfaces `#ffffff` / `#f7f7f5` / `#fbfbfa` / `#f1f0ef`, hover tints, OK `#1c6c47`/`#dbeddb` (5.22), bad `#b3261e`/`#ffe2dd` (5.35), info `#1a4d80`/`#d3e5ef` (6.71), neutral `#5f5e5b`/`#f1f0ef` (5.70), score ramp (all ≥ 3.72 as non-text).

**Consequence to accept** [R]: secondary (`#5f5e5b`) and tertiary (`#6b6a66`) are now close in value. Hierarchy between them comes from size and weight (meta is 11–12 px, eyebrows are uppercase 600), not from lightness. This is the price of AA on every surface.

**Semantic colour tokens.**

| Group | Token | Value | Use |
|---|---|---|---|
| Surface | `--color-bg` | `#ffffff` | Page, cards, tables |
| | `--color-bg-subtle` | `#f7f7f5` | Sidebar, segmented track, document pane |
| | `--color-bg-muted` | `#fbfbfa` | Table header, nested card, property panel |
| | `--color-bg-neutral` | `#f1f0ef` | Neutral chip |
| | `--color-bg-hover` | `rgba(55,53,47,.035)` | Row and list hover (v3 `.los-row`; v3's separate table hover `.025` is merged into it [R]) |
| | `--color-bg-hover-nav` | `rgba(55,53,47,.06)` | Nav hover |
| | `--color-bg-selected` | `rgba(55,53,47,.08)` | Active nav, selected row |
| | `--color-bg-pressed` | `rgba(55,53,47,.10)` | Pressed secondary button [R] |
| | `--color-bg-danger-wash` | `rgba(179,38,30,.04)` | Breached queue row, failed posting panel [F v3] |
| | `--color-bg-success-wash` | `rgba(44,96,71,.06)` | Acknowledged posting panel [F v3] |
| | `--color-bg-overlay` | `rgba(15,15,15,.40)` | Modal and drawer backdrop [R] |
| | `--color-bg-inverse` | `#37352f` | Brand mark, tooltip |
| Text | `--color-text-primary` | `#37352f` | Body, values, titles |
| | `--color-text-secondary` | `#5f5e5b` | Secondary copy, inactive nav |
| | `--color-text-tertiary` | `#6b6a66` | Meta, eyebrow, table header, inactive tab, nav count, placeholder |
| | `--color-text-disabled` | `#9b9a97` | Inside disabled controls only |
| | `--color-text-link` / `-hover` | `#1a6fc4` / `#155ea8` | Links and text buttons |
| | `--color-text-on-accent`, `-on-danger`, `-inverse` | `#ffffff` | On filled accent, danger fill, inverse |
| Accent | `--color-accent` / `-hover` / `-pressed` | `#1a6fc4` / `#155ea8` / `#124f8e` | Primary action, selected chip, checked control |
| | `--color-accent-subtle` | `#f2f8fd` | Info banner, "awaiting my action" tile |
| | `--color-accent-graphic` | `#2383e2` | Non-text: tracker dots, progress fill, region outline |
| | `--color-accent-border` | `#a9cdf0` | Decorative: tracker connector, info banner edge |
| | `--color-region-fill`, `--color-selection` | `rgba(35,131,226,.14)` / `.20` | Selected document region, text selection |
| Border | `--color-border` | `rgba(55,53,47,.09)` | Hairline rules (decorative) |
| | `--color-border-subtle` | `rgba(55,53,47,.06)` | Table row rules |
| | `--color-border-strong` | `rgba(55,53,47,.14)` | Button and card edges (the label identifies the button) |
| | `--color-border-control` / `-hover` | `#8b8a86` / `#6b6a66` | Inputs, selects, checkboxes, radios |
| | `--color-indicator` | `#8b8a86` | Pending markers, neutral meaningful icons |
| | `--color-decorative` | `#b3b1ad` | Separators only |
| Status | `--color-success-fg` / `-bg` / `-border` | `#1c6c47` / `#dbeddb` / `rgba(28,108,71,.30)` | Pass, OK, on track, booked |
| | `--color-warning-fg` / `-bg` / `-border` | `#7a5d0d` / `#fdecc8` / `rgba(122,93,13,.30)` | Refer, at risk, on hold, below 90 % confidence |
| | `--color-danger-fg` / `-bg` / `-border` | `#b3261e` / `#ffe2dd` / `#b3261e` | Fail, breach, declined, posting failed |
| | `--color-danger-text-strong` | `#6d2a24` | Body copy in danger callouts [F v3] |
| | `--color-danger-fill` / `-hover` | `#b3261e` / `#8f1e18` | Danger (destructive) button |
| | `--color-info-fg` / `-bg` / `-border` | `#1a4d80` / `#d3e5ef` / `#a9cdf0` | Info tag, segment tag, adapter source tag |
| | `--color-neutral-fg` / `-bg` | `#5f5e5b` / `#f1f0ef` | Draft, queued, not yet routed |
| Data | `--color-score-1…5` | `#1c6c47` `#4d7a5c` `#8a6a10` `#b07a1e` `#b3261e` | Risk/score band ramp, band 1 = lowest risk [F v3]. Non-text. |
| | `--color-chart-1…5` | `#1a6fc4` `#1a4d80` `#1c6c47` `#b07a1e` `#6b6a66` | Chart series [R]. All ≥ 3:1 on white. |

**Prohibited pair** (computed 4.38:1): tertiary text inside the active nav item. Use secondary there.

### 3.3 Contrast results (generated)

Generated by `python3 contrast-check.py --legacy` against `design-tokens.css`. "Need" is the WCAG 2.2 AA minimum: 4.5 for text, 3.0 for large text (≥ 24 px, or ≥ 18.66 px bold), and 3.0 for UI components, state indicators and focus rings (1.4.11). Exempt rows are listed for information only.

**All graded and informational pairs**

| # | Foreground | Background | Fg hex | Bg hex | Ratio | Need | Result | Usage |
|--:|---|---|---|---|--:|--:|---|---|
| 1 | `--color-text-primary` | `--color-bg` | #37352f | #ffffff | 12.26 | 4.5 | PASS | Body, values, titles |
| 2 | `--color-text-primary` | `--color-bg-subtle` | #37352f | #f7f7f5 | 11.43 | 4.5 | PASS | Sidebar items, side-peek |
| 3 | `--color-text-primary` | `--color-bg-muted` | #37352f | #fbfbfa | 11.84 | 4.5 | PASS | Table header cells, property panel |
| 4 | `--color-text-primary` | `--color-bg-neutral` | #37352f | #f1f0ef | 10.77 | 4.5 | PASS | Text on neutral fill |
| 5 | `--color-text-primary` | `--color-bg-selected@--color-bg-subtle` | #37352f | #e8e7e5 | 9.92 | 4.5 | PASS | Active nav item label |
| 6 | `--color-text-primary` | `--color-bg-danger-wash@--color-bg` | #37352f | #fcf6f6 | 11.47 | 4.5 | PASS | Breached queue row |
| 7 | `--color-text-primary` | `--color-accent-subtle` | #37352f | #f2f8fd | 11.46 | 4.5 | PASS | Info banner title |
| 8 | `--color-text-primary` | `--color-warning-bg` | #37352f | #fdecc8 | 10.51 | 4.5 | PASS | Warning banner title |
| 9 | `--color-text-primary` | `--color-danger-bg` | #37352f | #ffe2dd | 10.03 | 4.5 | PASS | Danger banner body |
| 10 | `--color-text-primary` | `--color-success-bg` | #37352f | #dbeddb | 10.01 | 4.5 | PASS | Success banner body |
| 11 | `--color-text-secondary` | `--color-bg` | #5f5e5b | #ffffff | 6.48 | 4.5 | PASS | Secondary copy, inactive nav |
| 12 | `--color-text-secondary` | `--color-bg-subtle` | #5f5e5b | #f7f7f5 | 6.04 | 4.5 | PASS | Inactive nav item |
| 13 | `--color-text-secondary` | `--color-bg-muted` | #5f5e5b | #fbfbfa | 6.26 | 4.5 | PASS | Secondary in property panel |
| 14 | `--color-text-secondary` | `--color-bg-neutral` | #5f5e5b | #f1f0ef | 5.70 | 4.5 | PASS | Neutral chip label |
| 15 | `--color-text-secondary` | `--color-bg-selected@--color-bg-subtle` | #5f5e5b | #e8e7e5 | 5.25 | 4.5 | PASS | Count inside active nav item |
| 16 | `--color-text-secondary` | `--color-bg-hover-nav@--color-bg-subtle` | #5f5e5b | #ebebe9 | 5.43 | 4.5 | PASS | Hovered nav item |
| 17 | `--color-text-secondary` | `--color-accent-subtle` | #5f5e5b | #f2f8fd | 6.06 | 4.5 | PASS | Banner body (info) |
| 18 | `--color-text-secondary` | `--color-warning-bg` | #5f5e5b | #fdecc8 | 5.56 | 4.5 | PASS | Banner body (warning) |
| 19 | `--color-text-secondary` | `--color-danger-bg` | #5f5e5b | #ffe2dd | 5.30 | 4.5 | PASS | Banner body (danger) |
| 20 | `--color-text-secondary` | `--color-success-bg` | #5f5e5b | #dbeddb | 5.29 | 4.5 | PASS | Banner body (success) |
| 21 | `--color-text-tertiary` | `--color-bg` | #6b6a66 | #ffffff | 5.41 | 4.5 | PASS | Meta, eyebrow, table header, inactive tab |
| 22 | `--color-text-tertiary` | `--color-bg-subtle` | #6b6a66 | #f7f7f5 | 5.05 | 4.5 | PASS | Sidebar meta, nav counts |
| 23 | `--color-text-tertiary` | `--color-bg-muted` | #6b6a66 | #fbfbfa | 5.23 | 4.5 | PASS | Table header on muted fill |
| 24 | `--color-text-tertiary` | `--color-bg-neutral` | #6b6a66 | #f1f0ef | 4.76 | 4.5 | PASS | Meta on neutral fill |
| 25 | `--color-text-tertiary` | `--color-bg-hover@--color-bg` | #6b6a66 | #f8f8f8 | 5.10 | 4.5 | PASS | Meta in hovered row |
| 26 | `--color-text-tertiary` | `--color-bg-hover-nav@--color-bg-subtle` | #6b6a66 | #ebebe9 | 4.54 | 4.5 | PASS | Count in hovered nav item |
| 27 | `--color-text-tertiary` | `--color-bg-danger-wash@--color-bg` | #6b6a66 | #fcf6f6 | 5.07 | 4.5 | PASS | Meta in breached row |
| 28 | `--color-text-tertiary` | `--color-bg-success-wash@--color-bg` | #6b6a66 | #f2f5f4 | 4.94 | 4.5 | PASS | Meta in acknowledged panel |
| 29 | `--color-text-tertiary` | `--color-accent-subtle` | #6b6a66 | #f2f8fd | 5.06 | 4.5 | PASS | Meta in info tile |
| 30 | `--color-text-placeholder` | `--color-bg` | #6b6a66 | #ffffff | 5.41 | 4.5 | PASS | Input placeholder |
| 31 | `--color-text-placeholder` | `--color-bg-muted` | #6b6a66 | #fbfbfa | 5.23 | 4.5 | PASS | Filter input placeholder |
| 32 | `--color-text-link` | `--color-bg` | #1a6fc4 | #ffffff | 5.10 | 4.5 | PASS | Links, text buttons |
| 33 | `--color-text-link` | `--color-bg-subtle` | #1a6fc4 | #f7f7f5 | 4.76 | 4.5 | PASS | Links in sidebar / side-peek |
| 34 | `--color-text-link` | `--color-bg-muted` | #1a6fc4 | #fbfbfa | 4.93 | 4.5 | PASS | Links in property panel |
| 35 | `--color-text-link` | `--color-accent-subtle` | #1a6fc4 | #f2f8fd | 4.77 | 4.5 | PASS | Links in info banner |
| 36 | `--color-text-link` | `--color-bg-hover@--color-bg` | #1a6fc4 | #f8f8f8 | 4.81 | 4.5 | PASS | Link in hovered row |
| 37 | `--color-text-link-hover` | `--color-bg` | #155ea8 | #ffffff | 6.57 | 4.5 | PASS | Link hover |
| 38 | `--color-text-on-accent` | `--color-accent` | #ffffff | #1a6fc4 | 5.10 | 4.5 | PASS | Primary button, selected filter chip |
| 39 | `--color-text-on-accent` | `--color-accent-hover` | #ffffff | #155ea8 | 6.57 | 4.5 | PASS | Primary button hover |
| 40 | `--color-text-on-accent` | `--color-accent-pressed` | #ffffff | #124f8e | 8.28 | 4.5 | PASS | Primary button pressed |
| 41 | `--color-text-on-danger` | `--color-danger-fill` | #ffffff | #b3261e | 6.54 | 4.5 | PASS | Danger button |
| 42 | `--color-text-on-danger` | `--color-danger-fill-hover` | #ffffff | #8f1e18 | 8.87 | 4.5 | PASS | Danger button hover |
| 43 | `--color-text-inverse` | `--color-bg-inverse` | #ffffff | #37352f | 12.26 | 4.5 | PASS | Tooltip, brand mark |
| 44 | `--color-success-fg` | `--color-success-bg` | #1c6c47 | #dbeddb | 5.22 | 4.5 | PASS | Success chip |
| 45 | `--color-success-fg` | `--color-bg` | #1c6c47 | #ffffff | 6.39 | 4.5 | PASS | Success inline text |
| 46 | `--color-success-fg` | `--color-bg-success-wash@--color-bg` | #1c6c47 | #f2f5f4 | 5.83 | 4.5 | PASS | Acknowledged panel text |
| 47 | `--color-warning-fg` | `--color-warning-bg` | #7a5d0d | #fdecc8 | 5.30 | 4.5 | PASS | Warning chip (was failing) |
| 48 | `--color-warning-fg` | `--color-bg` | #7a5d0d | #ffffff | 6.18 | 4.5 | PASS | Warning inline text, queue flag |
| 49 | `--color-warning-fg` | `--color-bg-muted` | #7a5d0d | #fbfbfa | 5.97 | 4.5 | PASS | Warning text in property panel |
| 50 | `--color-danger-fg` | `--color-danger-bg` | #b3261e | #ffe2dd | 5.35 | 4.5 | PASS | Danger chip |
| 51 | `--color-danger-fg` | `--color-bg` | #b3261e | #ffffff | 6.54 | 4.5 | PASS | Danger inline text, outline danger button |
| 52 | `--color-danger-fg` | `--color-bg-danger-wash@--color-bg` | #b3261e | #fcf6f6 | 6.12 | 4.5 | PASS | Danger text in breached row |
| 53 | `--color-danger-fg` | `--color-bg-muted` | #b3261e | #fbfbfa | 6.31 | 4.5 | PASS | Danger text in property panel |
| 54 | `--color-danger-text-strong` | `--color-danger-bg` | #6d2a24 | #ffe2dd | 8.56 | 4.5 | PASS | Danger callout body |
| 55 | `--color-danger-text-strong` | `--color-bg-danger-wash@--color-bg` | #6d2a24 | #fcf6f6 | 9.79 | 4.5 | PASS | Cross-validation warning body |
| 56 | `--color-info-fg` | `--color-info-bg` | #1a4d80 | #d3e5ef | 6.71 | 4.5 | PASS | Info / segment tag |
| 57 | `--color-info-fg` | `--color-accent-subtle` | #1a4d80 | #f2f8fd | 8.12 | 4.5 | PASS | Accent tile value and label |
| 58 | `--color-info-fg` | `--color-bg` | #1a4d80 | #ffffff | 8.69 | 4.5 | PASS | Info inline text |
| 59 | `--color-neutral-fg` | `--color-neutral-bg` | #5f5e5b | #f1f0ef | 5.70 | 4.5 | PASS | Neutral chip |
| 60 | `--color-text-primary` | `--color-bg` | #37352f | #ffffff | 12.26 | 3.0 | PASS | Stat tile value 30px |
| 61 | `--color-info-fg` | `--color-accent-subtle` | #1a4d80 | #f2f8fd | 8.12 | 3.0 | PASS | Awaiting-my-action tile value |
| 62 | `--color-danger-fg` | `--color-danger-bg` | #b3261e | #ffe2dd | 5.35 | 3.0 | PASS | SLA-breached tile value |
| 63 | `--color-border-control` | `--color-bg` | #8b8a86 | #ffffff | 3.46 | 3.0 | PASS | Input / checkbox / radio boundary |
| 64 | `--color-border-control` | `--color-bg-subtle` | #8b8a86 | #f7f7f5 | 3.22 | 3.0 | PASS | Input boundary on subtle |
| 65 | `--color-border-control` | `--color-bg-muted` | #8b8a86 | #fbfbfa | 3.34 | 3.0 | PASS | Filter input boundary |
| 66 | `--color-border-control-hover` | `--color-bg` | #6b6a66 | #ffffff | 5.41 | 3.0 | PASS | Input boundary hover |
| 67 | `--focus-ring-color` | `--color-bg` | #1a6fc4 | #ffffff | 5.10 | 3.0 | PASS | Focus ring on page |
| 68 | `--focus-ring-color` | `--color-bg-subtle` | #1a6fc4 | #f7f7f5 | 4.76 | 3.0 | PASS | Focus ring in sidebar / side-peek |
| 69 | `--focus-ring-color` | `--color-bg-muted` | #1a6fc4 | #fbfbfa | 4.93 | 3.0 | PASS | Focus ring in property panel |
| 70 | `--focus-ring-color` | `--color-bg-neutral` | #1a6fc4 | #f1f0ef | 4.48 | 3.0 | PASS | Focus ring on neutral fill |
| 71 | `--focus-ring-color` | `--color-bg-selected@--color-bg-subtle` | #1a6fc4 | #e8e7e5 | 4.13 | 3.0 | PASS | Focus ring on active nav |
| 72 | `--focus-ring-color` | `--color-accent-subtle` | #1a6fc4 | #f2f8fd | 4.77 | 3.0 | PASS | Focus ring in info banner |
| 73 | `--focus-ring-color` | `--color-bg-danger-wash@--color-bg` | #1a6fc4 | #fcf6f6 | 4.78 | 3.0 | PASS | Focus ring on breached row |
| 74 | `--focus-ring-color` | `--color-warning-bg` | #1a6fc4 | #fdecc8 | 4.38 | 3.0 | PASS | Focus ring in warning banner |
| 75 | `--focus-ring-color` | `--color-danger-bg` | #1a6fc4 | #ffe2dd | 4.17 | 3.0 | PASS | Focus ring in danger banner |
| 76 | `--color-accent` | `--color-bg` | #1a6fc4 | #ffffff | 5.10 | 3.0 | PASS | Selected chip / checked box vs page |
| 77 | `--color-accent-graphic` | `--color-bg` | #2383e2 | #ffffff | 3.88 | 3.0 | PASS | Stage tracker dot, progress fill, region outline |
| 78 | `--color-indicator` | `--color-bg` | #8b8a86 | #ffffff | 3.46 | 3.0 | PASS | Pending stage marker, neutral icon |
| 79 | `--color-indicator` | `--color-bg-subtle` | #8b8a86 | #f7f7f5 | 3.22 | 3.0 | PASS | Neutral icon in sidebar |
| 80 | `--color-text-on-accent` | `--color-accent` | #ffffff | #1a6fc4 | 5.10 | 3.0 | PASS | Checkmark on checked box |
| 81 | `--color-score-1` | `--color-bg` | #1c6c47 | #ffffff | 6.39 | 3.0 | PASS | Score band 1 |
| 82 | `--color-score-2` | `--color-bg` | #4d7a5c | #ffffff | 4.94 | 3.0 | PASS | Score band 2 |
| 83 | `--color-score-3` | `--color-bg` | #8a6a10 | #ffffff | 5.06 | 3.0 | PASS | Score band 3 |
| 84 | `--color-score-4` | `--color-bg` | #b07a1e | #ffffff | 3.72 | 3.0 | PASS | Score band 4 |
| 85 | `--color-score-5` | `--color-bg` | #b3261e | #ffffff | 6.54 | 3.0 | PASS | Score band 5 |
| 86 | `--color-chart-1` | `--color-bg` | #1a6fc4 | #ffffff | 5.10 | 3.0 | PASS | Chart series 1 |
| 87 | `--color-chart-2` | `--color-bg` | #1a4d80 | #ffffff | 8.69 | 3.0 | PASS | Chart series 2 |
| 88 | `--color-chart-3` | `--color-bg` | #1c6c47 | #ffffff | 6.39 | 3.0 | PASS | Chart series 3 |
| 89 | `--color-chart-4` | `--color-bg` | #b07a1e | #ffffff | 3.72 | 3.0 | PASS | Chart series 4 |
| 90 | `--color-chart-5` | `--color-bg` | #6b6a66 | #ffffff | 5.41 | 3.0 | PASS | Chart series 5 |
| 91 | `--color-text-disabled` | `--color-bg` | #9b9a97 | #ffffff | 2.81 | — | info (exempt) | Disabled control label (WCAG 1.4.3 exception) |
| 92 | `--color-decorative` | `--color-bg` | #b3b1ad | #ffffff | 2.14 | — | info (exempt) | Decorative separators only |
| 93 | `--color-border` | `--color-bg` | #ededec | #ffffff | 1.17 | — | info (exempt) | Hairline rule (decorative) |
| 94 | `--color-border-strong` | `--color-bg` | #e3e3e2 | #ffffff | 1.28 | — | info (exempt) | Button / card edge (label identifies the control) |
| 95 | `--color-accent-border` | `--color-bg` | #a9cdf0 | #ffffff | 1.66 | — | info (exempt) | Tracker connector (decorative) |
| 96 | `--color-text-tertiary` | `--color-bg-selected@--color-bg-subtle` | #6b6a66 | #e8e7e5 | 4.38 | — | info (exempt) | PROHIBITED pair: use text-secondary inside the active nav item |

90 graded pairs, 0 failing, 6 informational.

**Originals (v3 / AuditPro) vs corrected**

| Pair | Original | Ratio | Corrected | Ratio | Need |
|---|---|--:|---|--:|--:|
| White on v3 accent (primary button, selected chip) | #ffffff on #2383e2 | 3.88 | #ffffff on #1a6fc4 | 5.10 | 4.5 |
| v3 accent as link text | #2383e2 on #ffffff | 3.88 | #1a6fc4 on #ffffff | 5.10 | 4.5 |
| v3 accent link on tint | #2383e2 on #f2f8fd | 3.62 | #1a6fc4 on #f2f8fd | 4.77 | 4.5 |
| v3 tertiary on white | #787774 on #ffffff | 4.48 | #6b6a66 on #ffffff | 5.41 | 4.5 |
| v3 tertiary on sidebar | #787774 on #f7f7f5 | 4.17 | #6b6a66 on #f7f7f5 | 5.05 | 4.5 |
| v3 muted (eyebrow, table header, meta) on white | #9b9a97 on #ffffff | 2.81 | #6b6a66 on #ffffff | 5.41 | 4.5 |
| v3 muted on header fill | #9b9a97 on #fbfbfa | 2.72 | #6b6a66 on #fbfbfa | 5.23 | 4.5 |
| v3 faint nav count on sidebar | #b3b1ad on #f7f7f5 | 2.00 | #6b6a66 on #f7f7f5 | 5.05 | 4.5 |
| v3 faint on white | #b3b1ad on #ffffff | 2.14 | #6b6a66 on #ffffff | 5.41 | 4.5 |
| v3 warn text on amber | #8a6a10 on #fdecc8 | 4.34 | #7a5d0d on #fdecc8 | 5.30 | 4.5 |
| v3 focus ring (2px soft ring) | rgba(35,131,226,0.2) on #ffffff | 1.28 | #1a6fc4 on #ffffff | 5.10 | 3.0 |
| v3 input border | rgba(55,53,47,0.14) on #ffffff | 1.28 | #8b8a86 on #ffffff | 3.46 | 3.0 |
| v3 pending stage dot border | #d3d1cd on #ffffff | 1.52 | #8b8a86 on #ffffff | 3.46 | 3.0 |
| AuditPro secondary text on white | #718096 on #ffffff | 4.02 | #6b6a66 on #ffffff | 5.41 | 4.5 |
| AuditPro gold nav indicator | #d4af37 on #ffffff | 2.10 | #1a6fc4 on #ffffff | 5.10 | 3.0 |

### 3.4 Typography

Family: **Archivo** for everything [F v3 / Modernist]; **Roboto Mono** for references, keys, hashes and account numbers [R; Roboto Mono is the AuditPro house mono, F AuditPro §3.1]. Numeric columns, amounts, percentages, dates and counts use `font-variant-numeric: tabular-nums` [F v3]. **[verify in build]** that the shipped Archivo build exposes `tnum`; if it does not, numeric table columns fall back to `--font-mono`.

| Token (Tailwind) | Size / line | Weight · tracking | Use | Source |
|---|---|---|---|---|
| `micro` | 11 / 16 | 400–600 | Eyebrow (600, uppercase, 0.03em, tertiary), count badges, chip meta, row sub-lines | [F v3 11px; R raises v3's 10 and 10.5 px text to 11 px] |
| `meta` | 12 / 16 | 400–450 | Meta lines, table header, chips, filter chips | [F v3] |
| `table` | 12.5 / 18 | 400 | Table body | [F v3] |
| `body-sm` | 13 / 18 | 400 | Side-peek field list, compact buttons, helper text | [F v3 body 13–14] |
| `nav` | 13.5 / 20 | 400; 600 active | Sidebar items | [F v3] |
| `body` | 14 / 20 | 400 | Base body, inputs, buttons (450) | [F v3 base 14] |
| `title` | 16 / 22 | 600 · −0.01em | Screen title, panel and section titles | [F v3] |
| `title-lg` | 18 / 24 | 600 · −0.01em | Net disbursement figure, mobile page title | [F v3 18px figure] |
| `heading` | 22 / 28 | 600 · −0.02em | Portal page heading, sign-in, empty-state headline | [R] |
| `metric` | 30 / 32 | 600 · −0.03em | Stat tile value (counts as large text) | [F v3] |

Rules: sentence case everywhere, including buttons [F v3]. AuditPro's uppercase, tracked button text is **not** adopted [R, D-027]. Line length for prose (memo, explanations) is capped at 68ch. Weight 700 is not used.

### 3.5 Spacing, sizing and density

- Scale: 2, 4, 6, 8, 10, 12, 14, 16, 20, 24, 32, 40, 48, 64 px (`0.5` … `16`) [R]. It keeps v3's half-steps (6, 10, 14) and drops its one-off values (7, 9, 11, 18 px), which round to the nearest step. The exceptions, kept as component tokens, are: card padding 14 × 16 px and table cell padding 7 × 10 px [F v3].
- Page gutter 20 px at ≥ 1024, 16 px below [R].
- Control heights: 28 (compact, in tables and side-peek), 32 (default), 40 (dialog primary and touch) [R]. Table row minimum is 32 px [R; v3 rows measure about 32 px].
- **Target size** [F NFR-014, WCAG 2.2 2.5.8]: every pointer target ≥ 24 × 24 px, or has 24 px spacing to the next target. v3's small table buttons (`padding 3px 9px`, about 22 px tall) are raised to 28 px [R]. Below 1024 px and in the applicant portal, targets are 44 × 44 px [R].
- Icons: Lucide [F v3/Modernist] at 14, 16 or 20 px, stroke 1.5, `currentColor`.

### 3.6 Radii

| Token | Value | Use | Source |
|---|---|---|---|
| `rounded-chip` | 3 px | Status chips, count badges, reason-code chips | [F v3] |
| `rounded-mark` | 4 px | Brand mark, avatar square | [F v3] |
| `rounded` / `rounded-control` | 5 px | Buttons, inputs, selects, segmented control, filter chips | [F v3; R adds filter chips, which v3 left square] |
| `rounded-card` | 6 px | Cards, stat tiles, panels, banners | [F v3] |
| `rounded-overlay` | 8 px | Modal, popover, drawer leading edge, toast | [R] |
| `rounded-full` | 9999 px | Toggle track and avatar circle only | [R] |

Status dots are **square** (6 px, radius 0) [F v3].

### 3.7 Elevation

v3 is flat: hairlines separate surfaces [F v3]. Shadows:

| Token | Value | Use |
|---|---|---|
| `shadow-button` | `0 1px 2px rgba(15,15,15,.04)` | Default/secondary buttons [F v3] |
| `shadow-seg` | `0 1px 2px rgba(15,15,15,.08)` | Selected segmented option [F v3] |
| `shadow-document` | `0 3px 10px rgba(45,43,43,.16)` | Rendered document page in side-peek [F v3, Modernist `--shadow-md`] |
| `shadow-popover` | 1 px edge + `0 4px 12px rgba(15,15,15,.10)` | Dropdown, popover, tooltip, command palette [R] |
| `shadow-drawer` | 1 px edge + `-8px 0 24px rgba(15,15,15,.10)` | Right-rail drawer, side-peek [R] |
| `shadow-modal` | 1 px edge + `0 12px 32px rgba(15,15,15,.18)` | Modal, confirm dialog, step-up dialog [R] |

No hover lift on cards. AuditPro's `translateY(-2px)` card hover is not adopted [R].

### 3.8 Focus

- **Default:** `outline: 2px solid var(--focus-ring-color); outline-offset: 2px` on `:focus-visible` [R; Modernist pattern, F Modernist readme]. The ring colour `#1a6fc4` measures ≥ 4.13:1 against every surface in §3.3.
- **Inputs:** border becomes `--color-accent`, plus a `0 0 0 1px` inner shadow in accent, giving a 2 px accent edge. No soft halo.
- **Rows and items inside `overflow:hidden`:** `--focus-ring-inset` (2 px inset).
- **Primary button:** the outline sits outside the 2 px offset, on the page background, so it reads against white rather than against the blue fill.
- **Never** remove focus styles. **Never** let sticky headers cover the focused element [F WCAG 2.2 2.4.11]. `[data-scroll-container]` sets `scroll-padding-top` for this.

### 3.9 Motion

| Token | Value | Use |
|---|---|---|
| `duration-fast` | 120 ms | Hover, press, chip toggle |
| `duration-base` | 160 ms | Popover, tooltip, tab indicator |
| `duration-slow` | 220 ms | Drawer, side-peek, modal |
| `ease-standard` / `enter` / `exit` | `cubic-bezier(.2,0,0,1)` / `(0,0,.2,1)` / `(.4,0,1,1)` | Moves, entrances, exits |

All [R]. The only timed behaviour in v3 is the 1600 ms demo posting transition, which is prototype behaviour, not a motion spec [F v3]. `prefers-reduced-motion: reduce` sets every duration to 0, removes transforms and stops skeleton shimmer (skeletons stay static grey) [R]. No autoplaying motion anywhere.

### 3.10 Z-index and layout constants

Z-index: sticky 10 · header 20 · rail flyout 30 · drawer/side-peek 40 · modal 50 · toast 60 · tooltip 70.
Layout: sidebar 216 px [F v3] · icon rail 56 px [R] · title bar 48 px [R] · context bar 64 px [R] · tab row 40 px [R] · case grid `186px | 1fr | 288px` [F v3] · properties drawer 320 px [R] · side-peek `min(1080px, 92vw)` [R] · form max 880 px [R] · portal column 560 px [R].

---

## 4. Responsive model (NFR-015)

**Fact.** v3 is a fixed 1440 px layout with "no responsive behaviour designed". It advises: "the sidebar should collapse and the 3-column case grid should drop the right rail first" [F v3 README]. NFR-015 requires support "down to tablet" [F]. D-010 approves responsive mobile approval with step-up [F]. FR-APV-011 (mobile approval) is phase P3 [F phase_map].

### 4.1 Breakpoints

| Name | Width | Tailwind | Shell | Case workspace | Tables |
|---|---|---|---|---|---|
| **Desktop** | ≥ 1280 | `xl:` | Sidebar 216 px, expanded | `186 | 1fr | 288` grid: tracker, content, properties rail | Full columns |
| **Laptop** | 1024–1279 | `lg:` | Sidebar 216 px | Tracker + content. The properties rail becomes a **right drawer** (320 px), opened by a "Properties" button in the context bar, with a count badge for open requirements. | Low-priority columns (Assignee role, Branch) fold into a sub-line |
| **Tablet** | 768–1023 | `md:` | **Icon rail 56 px**: Lucide icon with label tooltip, count dot. The full sidebar opens as an overlay drawer from a menu button. | **Single column.** The stage tracker becomes a horizontal stepper under the context bar (current stage named, others as dots, "3 of 8"). The tab row scrolls horizontally with visible overflow arrows. The properties rail becomes a "Requirements" tab. | Rows become **stacked cards**: reference + status on line 1, applicant + amount on line 2, SLA chip + stage on line 3 |
| **Mobile** | < 768 | base | Top app bar + bottom tab bar (Inbox, Approvals, Notifications, Me). **Only SCR-WRK-01 Task inbox, SCR-APV-04 Mobile approval and SCR-GLB screens are available.** Any other route shows "Open this on a larger screen" with a link copier. | n/a (approval packet is a single-column summary) | Cards |

Sources: the four breakpoints, their behaviour and the mobile scope are specified in the commissioning instructions for this deliverable [F commission]; the pixel details (rail width, stepper, card stacking) are [R]. Mobile approval content is FR-APV-011 (P3); until P3, mobile shows the inbox only, and approval tasks say "Approve on a tablet or desktop" [R].

### 4.2 Rules

- **Applicant portal (P4) is mobile-first** [F commission]: single column at 390 px, 560 px max column on wider screens, 44 px targets, camera capture for uploads (FR-DOC-001).
- **Reflow** (WCAG 1.4.10): at 320 CSS px wide (400 % zoom) no screen scrolls in two dimensions, except data tables, which scroll horizontally inside their own container with a sticky first column. [R]
- **Order of collapse** follows v3's advice [F]: 1) right rail → drawer; 2) sidebar → icon rail; 3) grid → single column.
- **Side-peek** (document review): ≥ 1280 it overlays 1080 px from the right, document and field list side by side. 1024–1279: 92 vw. < 1024: full screen, with document and fields as two tabs ("Document" / "Fields (3 flagged)"). [R]
- **Stat tiles:** 4-up ≥ 1280, 2-up 768–1279, 1-up below [R; AuditPro grid pattern F §4.4].
- **Context bar** wraps to two lines below 1280: identity on line 1, terms and actions on line 2. The primary action never collapses into an overflow menu; secondary actions do. [R]
- **Text resize**: layouts hold at 200 % browser zoom without loss of content (WCAG 1.4.4) [R].

---
## 5. App shell and navigation

### 5.1 Shell anatomy (desktop)

```
┌───────────────┬───────────────────────────────────────────────────────────────┐
│ F  Fundly     │ Screen title · subtitle        [⌘K Search] [?] [🔔 3] [Avatar] │ title bar 48
│ INSTITUTION A │───────────────────────────────────────────────────────────────│
│ [Legal entity]│ (banner zone: environment · licence · session · maker-checker) │
│               │───────────────────────────────────────────────────────────────│
│ WORK          │                                                               │
│  Inbox     12 │  content column (only this scrolls)                           │
│  Pipeline 148 │                                                               │
│  …            │                                                               │
│ QUEUES …      │                                                               │
│ CONFIGURATION │                                                               │
│ ADMINISTRATION│                                                               │
│ ASSURANCE     │                                                               │
│───────────────│                                                               │
│ ACTING AS     │                                                               │
│ Chidi Nwosu   │                                                               │
│ Credit Analyst│                                                               │
│ Scope · Limit │                                                               │
└───────────────┴───────────────────────────────────────────────────────────────┘
```

| Element | Spec | Source |
|---|---|---|
| Brand block | 20 px inverse square "F" (4 px radius), "Fundly" 17 px/600, eyebrow "Institution A · origination" | [F v3] |
| Legal entity switcher | Only when the user's assignments span more than one legal entity (FR-TEN-003). Changes list scope; never mixes entities in one list without an Entity column. | [R] |
| Nav item | 13.5 px, padding 5 × 9 px, radius 5 px; hover `bg-hover-nav`; active `bg-selected` + text primary + weight 600; count right-aligned 11 px tertiary (secondary when active). Height ≥ 28 px. `aria-current="page"` on the active item. | [F v3; R for height, weight and ARIA] |
| Group label | Eyebrow style, 11 px/600/uppercase, tertiary | [F v3] |
| Acting-as footer | Name; **all active role assignments** (not one per screen); scope summary ("3 branches · Retail, SME"); approval limit if any ("Limit ₦20,000,000"); active delegation ("Acting for Yusuf Lawal until 30 Sep"). Links to SCR-GLB-06. | [F v3 shows role, scope and limit; R for multi-role and delegation] |
| Title bar | Screen title 16/600 + subtitle (meta, tertiary, left hairline) [F v3]. Right side: global search (SCR-GLB-07), help (same position on every screen, WCAG 3.2.6), notifications (SCR-GLB-05), user menu. v3's "Tenant · date · time" text moves into the user menu. | [F v3; R for the right side] |
| Banner zone | Stacks at most two banners; more collapse into "2 more notices". Order: environment → licence → session → screen-specific. | [R] |
| Environment banner | Non-production installations: persistent warning-tone strip "UAT · anonymised data · not for customer use" (NFR-018, FR-SEC-018). | [R] |

**Role context.** v3 changes the "acting as" role with the screen (Credit Analyst on assessment, Approver on approval) [F v3]. The access model is a **union of all active assignments, each constrained by its scope** [F FR-SEC-004, TRD §8.1]. So the shell shows every role the user holds, and each screen shows what the user can do *here* through enabled or blocked actions. There is no role switcher [R].

### 5.2 Sidebar groups and items

An item is shown only if `/me/effective-access` grants its list permission in at least one scope [R; deny by default, F FR-SEC-003]. Counts come from saved-view counts on `/tasks` or list totals, and refresh every 60 s and on focus [R; v3 shows an "auto-refresh" note].

| Group | Item | Route | Screen | Count | Phase |
|---|---|---|---|---|---|
| **Work** | Inbox | `/inbox` | SCR-WRK-01 | My open tasks | MVP |
| | Checks | `/inbox?view=checks` | SCR-ADM-07 (filtered to items I can check) | Change requests awaiting me | MVP |
| | Pipeline | `/pipeline` | SCR-WRK-02 | In flight, in scope | MVP |
| | Applications | `/applications` | SCR-WRK-03 | — | MVP |
| | Customers | `/parties` | SCR-PTY-01 | — | MVP |
| | Leads | `/leads` | SCR-LED-01 | Open leads | Later (P4) |
| **Queues** (saved inbox views per stage, as in v3) | Document review | `/inbox?view=documents` | SCR-WRK-01 | | MVP |
| | Credit assessment | `/inbox?view=assessment` | SCR-WRK-01 | | MVP |
| | Approvals | `/inbox?view=approvals` | SCR-WRK-01 | | MVP |
| | Offers & execution | `/inbox?view=offers` | SCR-WRK-01 | | MVP |
| | Conditions | `/inbox?view=conditions` | SCR-WRK-01 | | MVP |
| | Disbursement | `/inbox?view=disbursement` | SCR-WRK-01 | | MVP |
| **Compliance** | Screening alerts | `/compliance/alerts` | SCR-CMP-01 | Open alerts | MVP |
| | Regulatory packs | `/compliance/packs` | SCR-CMP-03 | | MVP |
| | Insider register | `/compliance/insiders` | SCR-CMP-07 | | Later (P3) |
| | Model inventory | `/compliance/models` | SCR-CMP-06 | | Later (P3) |
| | Data subject requests | `/compliance/dsr` | SCR-CMP-04 | Open DSRs | Later (P5) |
| | Retention & legal holds | `/compliance/retention` | SCR-CMP-05 | | Later (P5) |
| | PII & processors | `/compliance/pii-register` | SCR-CMP-08 | | Later (P5) |
| **Operations** | Disbursement exceptions | `/operations/exceptions` | SCR-DSB-04 | Open exceptions | MVP |
| | Reconciliation | `/operations/reconciliation` | SCR-DSB-05 | Open breaks | MVP |
| | Integration monitor | `/operations/integrations` | SCR-ADM-11 | Open circuits | MVP |
| **Configuration** | Products | `/config/products` | SCR-PRD-01 | Drafts | MVP |
| | Rules | `/config/rules` | SCR-PRD-03 | Drafts | MVP |
| | Approval matrix | `/config/approval-matrix` | SCR-PRD-04 | | MVP |
| | Workflows | `/config/workflows` | SCR-PRD-07 | | MVP |
| | Templates | `/config/templates` | SCR-PRD-06 | | MVP |
| | Simulation | `/config/simulation` | SCR-PRD-05 | | Later (P3) |
| **Administration** | Users | `/admin/users` | SCR-ADM-02 | | MVP |
| | Roles | `/admin/roles` | SCR-ADM-04 | | MVP |
| | Assignments | `/admin/assignments` | SCR-ADM-05 | Expiring ≤ 7 d | MVP |
| | Segregation of duties | `/admin/sod` | SCR-ADM-06 | Conflicts | MVP |
| | Delegations | `/admin/delegations` | SCR-ADM-14 | Active | MVP |
| | Organisation | `/admin/organisation` | SCR-ADM-01 | | MVP |
| | Reference data | `/admin/reference-data` | SCR-ADM-08 | | MVP |
| | Prudential parameters | `/admin/prudential` | SCR-ADM-09 | | MVP |
| | Adapter bindings | `/admin/adapters` | SCR-ADM-10 | | MVP |
| | Licence | `/admin/licence` | SCR-ADM-12 | Days to expiry when ≤ 60 | MVP |
| | Import centre | `/admin/imports` | SCR-ADM-13 | | Later (P4–P5) |
| | Support access | `/admin/support-access` | SCR-ADM-15 | Pending requests | Later (P5) |
| | Branding & terminology | `/admin/branding` | SCR-ADM-16 | | Later (P3/P5) |
| | Break-glass & recertification | `/admin/access-reviews` | SCR-ADM-17 | | Later (P5) |
| **Assurance** | Dashboard | `/reports/operational` | SCR-RPT-01 | | MVP |
| | Audit explorer | `/audit/events` | SCR-AUD-01 | | MVP |
| | Evidence packs | `/audit/evidence-packs` | SCR-AUD-03 | Ready to download | MVP |
| | Overrides & exceptions | `/audit/overrides` | SCR-AUD-04 | | MVP |
| | Audit integrity | `/audit/integrity` | SCR-AUD-05 | Breaks | MVP |
| | PII access log | `/audit/pii-access` | SCR-AUD-06 | | MVP |
| | Reports | `/reports` | SCR-RPT-02 | | Later (P5) |
| **Operator** (separate console, §8 OPR) | Installation health · Adapter registry · Support sessions | `/operator/*` | SCR-OPR-01…03 | | Later (P5–P6) |

### 5.3 Default visibility by role (standard role templates, LOS-FR-278)

● full · ◐ read-only · ○ own items only · – hidden. Tenants can change the role templates; this is the shipped default [R].

| Item | LO | OPS | CA | APR | CMP | LEG | DM | DC | PM | ADM | AUD |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Inbox, Pipeline, Applications | ● | ● | ● | ● | ● | ● | ● | ● | – | – | ◐ |
| Checks | – | ● | ● | ● | ● | ● | – | ● | ● | ● | – |
| Customers | ● | ● | ● | ◐ | ● | ◐ | ◐ | ◐ | – | – | ◐ |
| Queue: Documents | ○ | ● | ◐ | – | – | ● | – | – | – | – | ◐ |
| Queue: Assessment | ○ | – | ● | ◐ | – | – | – | – | – | – | ◐ |
| Queue: Approvals | ○ | – | ○ | ● | – | – | – | – | – | – | ◐ |
| Queue: Offers, Conditions | ● | ● | ◐ | – | – | ● | ◐ | ◐ | – | – | ◐ |
| Queue: Disbursement | – | ◐ | – | – | – | – | ● | ● | – | – | ◐ |
| Screening alerts | – | – | – | – | ● | – | – | – | – | – | ◐ |
| Regulatory packs | – | – | – | – | ● | – | – | – | ◐ | ● | ◐ |
| Disbursement exceptions, Reconciliation | – | ◐ | – | – | – | – | ● | ● | – | ◐ | ◐ |
| Integration monitor | – | – | – | – | – | – | ◐ | ◐ | – | ● | ◐ |
| Products, Rules, Approval matrix, Workflows, Templates | – | – | ◐ | – | ◐ | ◐ | – | – | ● | ◐ | ◐ |
| Users, Roles, Assignments, SoD, Delegations, Organisation | – | – | – | – | – | – | – | – | – | ● | ◐ |
| Reference data, Prudential parameters, Adapter bindings, Licence | – | – | – | – | ◐ | – | – | – | ◐ | ● | ◐ |
| Dashboard | ◐ | ● | ● | ● | ● | ● | ● | ● | ● | ● | ◐ |
| Audit explorer, Overrides, PII access log | – | – | – | – | ● | – | – | – | – | ◐ | ● |
| Evidence packs, Audit integrity | – | – | – | – | ◐ | – | – | – | – | ◐ | ● |

**Auditor** [F LOS-FR-279, FR-AUD-009]: read-only, unrestricted scope, no operational actions. Every screen the auditor sees renders in the read-only variant (§6.1). A **Regulator/Examiner** gets the same view, time-bound (LOS-FR-280, P5); the shell then shows "Examiner access until 14 Nov 2026" in the acting-as footer [R].
**Applicant** uses the separate portal (§8, SCR-POR). **Platform operator** uses a separate console with no tenant business data (LOS-FR-276/277) [F G-31].

### 5.4 Case workspace tabs

v3 tabs [F]: Summary, Applicant & KYC, Documents, Credit, Collateral, Approvals, Conditions, Audit. Added [R]: **Offer** (between Approvals and Conditions), **Disbursement** (after Conditions) and **Notes** (before Audit). The extra three keep every stage reachable from the case.

- Tabs never disappear because of stage. A tab for a stage not yet reached shows a "Not started" empty state that says what unlocks it [R].
- A tab is hidden only when the user lacks permission to read its data (e.g. pricing) [R].
- Badges: count of open items, warning tone; "OK" badge in success tone when complete [F v3].
- Keyboard: `role="tablist"`; arrow keys move between tabs; each tab is its own route, so browser back works [R].

### 5.5 Keyboard shortcuts [R]

`Ctrl/⌘ K` search · `g` then `i` inbox · `g` then `p` pipeline · `[` / `]` previous/next case tab · `j` / `k` next/previous row · `Enter` open row · `x` select row · `Esc` close overlay · `?` shortcut sheet. Single-key shortcuts can be turned off in SCR-GLB-06 (WCAG 2.1.4). Shortcuts never fire inside text fields.

---

## 6. Standard states and error mapping

Every screen in §8 implements these contracts. The screen specs list only what is specific to that screen.

### 6.1 State contracts

| State | Contract |
|---|---|
| **Loading** | Skeleton after 150 ms (no flash on fast loads). Blocks in `--color-bg-neutral`, 3 px radius, heights equal to the line-heights they replace. Tables: header row real, 8 skeleton rows at 32 px with column widths matching the real columns. Panels: title real, body skeleton. Region gets `aria-busy="true"`, and a polite live region announces "Loading <screen>". Static under reduced motion. Spinners only inside buttons (14 px), never for page content. [R] |
| **Empty** | Three kinds, each with its own copy: **first use** ("No products yet" + primary action if permitted); **filtered empty** ("No applications match these filters" + "Clear filters"); **not applicable** ("Collateral is not required for this product"). Component: `EmptyState` with a 32 px Lucide icon in tertiary. [F AuditPro §6.10, restyled; R for the three kinds] |
| **Error** | Mapped from `application/problem+json` (§6.2). Field errors inline under the field plus an `ErrorSummary` at the top of the form that links to each field and receives focus on submit. Section errors replace the section body with an inline `Banner` (danger) and a Retry button. Page errors use the full-page error with the `correlation_id` in mono and a copy button. [R; problem+json F TRD §10.1] |
| **Success** | Commands that change state show a `FlashNotification` toast (polite live region, 6 s, pauses on hover/focus, dismissible) **and** the screen re-renders from the server response. Toasts never carry the only copy of important information such as a new reference or a loan account number; those also appear on the page. [R] |
| **Permission denied** | **Page:** "You don't have access to this page" + which permission is missing (from `/me/effective-access`) + "Request access" (mailto/admin contact). **Section:** the panel shows a lock icon and "Hidden: requires `application:view_pricing`". **Action:** the button is shown disabled (`aria-disabled`, focusable) with a reason line under it or in its tooltip. Hide actions only where the user can never hold the permission in their role template. [R; deny by default F FR-SEC-003] |
| **Out of scope / not found** | One message for both, so existence is not leaked: "Application not found or outside your scope." [R; TRD scope filter F §8.1] |
| **Read-only (auditor, examiner, closed case)** | Every action control is removed, not disabled. A neutral `Banner`: "Read-only · Auditor access. Actions are not available." Masked PII stays masked; unmask is available only if the role holds `pii:unmask` (§7.3). Export and evidence-pack actions remain (D-034: never blocked by licence state). [R; F LOS-FR-279] |
| **Masked PII** | Default for every PII field (§7.3). [F FR-CMP-036] |
| **Maker-checker pending** | Banner + locked fields (§7.4). [F FR-SEC-007] |
| **SoD-blocked** | Action disabled with the reason (§7.5). [F FR-SEC-006, FR-APV-005, FR-CMP-014] |
| **Stale** | `412` on `If-Match`: an inline warning banner, "This application changed since you opened it (by Chidi Nwosu, 09:41). Review changes · Reload". The user's unsaved input is kept in the form. [R; ETag F TRD §10.1] |
| **Slow or offline network** | NFR-016. A top banner, "Connection lost. Your draft is saved on this device and will sync when you're back online", for capture forms with save-and-resume. Uploads show per-file progress and resume after interruption (tus). Commands are never auto-retried with a new idempotency key (§7.9). [R; F NFR-016, TRD §12.1] |
| **Licence-restricted** | Licence states from TRD §2.6 (§7.15). [F D-034] |

### 6.2 problem+json → UI mapping

Format and fields are fact [F TRD §10.1: RFC 9457, `type`, `code`, `correlation_id`, `errors[]`]. The `code` values below are proposed for the error catalogue [R].

| HTTP | `code` | UI treatment | Focus / announcement |
|---|---|---|---|
| 400 | `bad_request` | Page error "Something in this request was not valid" + correlation ID (developer fault; never expected) | Focus the error heading |
| 401 | `unauthenticated`, `session_expired` | SCR-GLB-04 session dialog; after re-auth, return to the same route and keep form state | Dialog takes focus |
| 401/403 | `mfa_required` | Route to SCR-GLB-02 | — |
| 403 | `step_up_required` | SCR-GLB-03 step-up dialog; on success **retry the same request with the same `Idempotency-Key`** | Dialog takes focus; return focus to the action |
| 403 | `forbidden` | Permission-denied state (§6.1) at page, section or action level | Assertive only when it follows a user action |
| 403 | `sod_conflict` | SoD notice (§7.5) naming the conflicting fact | Polite |
| 403 | `authority_insufficient` | "Your limit ₦20,000,000 is below the required ₦25,000,000. You can recommend; final approval routes to Board Credit Committee." [F v3 copy] | Polite |
| 403 | `licence_expired`, `licence_module_not_entitled`, `licence_user_cap` | Licence banner or page (§7.15) | — |
| 404 | `not_found` | "Not found or outside your scope" | — |
| 409 | `state_conflict` | "This action is no longer available: the application moved to Approval at 09:44." + Reload | Polite |
| 409 | `idempotency_key_reused` | Page error with correlation ID (client defect) | — |
| 409 | `change_request_stale` | Checker view: "The record changed after this request was made. It cannot be applied. Ask the maker to resubmit." [F TRD §6.4 "fails stale"] | Polite |
| 409 | `duplicate_detected` | Dedupe panel on SCR-APP-01 with matches (FR-CHN-007) | Focus the panel |
| 412 | `stale_resource` | Stale banner (§6.1) | Polite |
| 413 / 415 | `file_too_large`, `unsupported_type` | Inline on the upload row with the tenant limit (FR-DOC-002) | Polite |
| 422 | `validation_failed` | `errors[]` → inline field errors + `ErrorSummary` | Focus the summary |
| 422 | `business_rule` | Inline `Banner` (warning) with the reason code(s) (§7.7), e.g. `CDD_INCOMPLETE`, `BUREAU_REPORT_EXPIRED`, `CONSENT_MISSING` | Polite |
| 423 | `locked_pending_checker` | Maker-checker banner (§7.4) | Polite |
| 429 | `rate_limited` | Inline: "Too many requests. Try again in 20 s" with a countdown from `Retry-After`; the action re-enables itself | Polite, once |
| 500 | `server_error` | Section or page error with correlation ID and Retry | Focus the error |
| 502/503/504 | `integration_unavailable` (+ taxonomy `retryable` · `requires_intervention`) | "Core banking (Finacle adapter) is not responding. Your request is queued and will be retried automatically" (`retryable`), or "Needs manual intervention: an exception has been raised in Disbursement exceptions" (`requires_intervention`). Never shown as plain failure. [F PR-08, FR-CBA-011] | Polite |
| 503 | `maintenance` | Full-page maintenance with expected end time (Africa/Lagos) | — |
| `202` (not an error) | `change_request_created` | Success path for maker-checker actions: toast "Submitted for checker approval · CR-2026-00418" + maker-checker banner on the record (§7.4) | Polite |

---

## 7. Patterns

### 7.1 Canonical status chips

**Fact** [F TRD §6.2, LOS-FR-282/283]: the canonical status is a platform enumeration. Tenants configure **workflow stages** that map to exactly one canonical status, and they may relabel statuses but not create them. **Rule** [R]: the chip shows the tenant label; its **tone and icon come from the canonical status**, so a relabel cannot change meaning. The stage tracker (§10) shows workflow stages; chips show canonical status.

| Canonical status | Default label | Tone | Lucide icon |
|---|---|---|---|
| Draft | Draft | neutral | `pencil` |
| Submitted | Submitted | info | `send` |
| PreQualified | Pre-qualified | info | `filter` |
| KycScreening | KYC & screening | info | `user-check` |
| Documentation | Documentation | info | `files` |
| Assessment | Credit assessment | info | `calculator` |
| Recommended | Recommended | info | `thumbs-up` |
| Approval | In approval | info | `stamp` |
| Approved | Approved | success | `check` |
| CounterOffered | Counter-offered | warning | `arrow-left-right` |
| Declined | Declined | danger | `x` |
| Declined (review window, P5) | Declined · review available | danger | `x` + `clock` |
| OfferIssued | Offer issued | info | `file-signature` |
| Accepted | Offer accepted | success | `check-check` |
| ConditionsPrecedent | Conditions precedent | info | `list-checks` |
| ReadyForDisbursement | Ready to disburse | success | `circle-check` |
| Disbursing · `pending_cba` | Posting to core | info | `loader` (static under reduced motion) |
| Disbursing · `failed_retryable` | Posting failed · retry available | danger | `alert-triangle` |
| Disbursing · `failed_intervention` | Posting failed · needs intervention | danger | `octagon-alert` |
| Disbursing · `compensating` | Reversing posting | warning | `undo-2` |
| Booked | Booked | success | `landmark` |
| OnHold | On hold | warning | `pause` |
| ReturnedForRework | Returned for rework | warning | `corner-up-left` |
| Withdrawn | Withdrawn | neutral | `log-out` |
| Cancelled | Cancelled | neutral | `ban` |
| Expired | Expired · <reason> | neutral | `timer-off` |
| Facility: Reversed (P3) | Reversed | neutral | `undo-2` |

Spec: `StatusBadge` 3 px radius, 11–12 px text, padding 1 × 8 px, icon 12 px optional (icon on in tables, off in dense sub-lines). `Expired` always carries its `expiry_reason` (draft, approval validity, offer validity, hold timeout) [F G-14b].

**Other status families** use the same component with their own maps [F v3 unless noted]:
- Document checklist (FR-DOC-007): Not received (neutral) · Received (neutral) · Extracted (info) · Under review (warning) · Verified (success) · Rejected (danger) · Waived (neutral, `badge-check`) · Expired (danger). v3 uses "Missing" for not received; the FR-DOC-007 label governs [R].
- Policy rule result: Pass (success) · Refer (warning) · Fail (danger) [F v3].
- Pre-disbursement check: Pass · Fail (hard block) · Fail (soft) · Waived [F v3 hard-block vs soft-check].
- Condition: Open · Evidence submitted · Satisfied · Waived · Overdue [R].
- Change request: Pending checker · Approved · Rejected · Failed stale · Executed [R, TRD §5.2 statuses].
- Screening alert: New · Under review · Proposed false positive · Proposed true match · Cleared · Confirmed match · Escalated [R].

### 7.2 SLA chips

**Fact** [F v3, FR-APP-009, FR-WFL-006/008, TRD §6.5]: clocks run per application and per stage in business hours on the tenant calendar, pause on hold, and have warning and breach thresholds.

| State | Tone | Text | Dot |
|---|---|---|---|
| On track | success | "On track · 2d 4h left" | 6 px square, success |
| At risk (past warning threshold) | warning | "At risk · 6h 12m left" | warning |
| Breached | danger | "Breached · 31h overdue" | danger; whole row gets `bg-danger-wash` [F v3] |
| Paused | neutral | "Paused · awaiting customer" | `pause` icon instead of dot |
| Not started / complete | none | omitted | — |

Times are **business hours** and say so on hover ("Business hours, Institution A calendar; excludes 1 Oct public holiday") [R]. Accessible name: "SLA at risk, 6 hours 12 minutes left in stage Credit assessment" [R]. Durations: `<1h` → `42m`; `<24h` → `6h 12m`; else `2d 4h` [R].

### 7.3 Masked PII and unmask

**Facts** [F TRD §5.3, FR-CMP-036, FR-SEC-013, FR-AUD-006]: the API serialises PII masked by default. `POST /pii/unmask` with a purpose code returns clear values for named fields, requires `pii:unmask`, triggers step-up, and writes a `pii_access_log` row and an audit event. Field-encrypted PII: BVN, NIN, passport and driver's licence numbers, account numbers, TIN, date of birth, phone, email, residential address.

**Display** [R]:

| Field | Masked form |
|---|---|
| BVN, NIN, TIN, ID numbers | `2234*****91` (first 4, last 2) — as served by the API |
| Account number | `3081****47` |
| Phone | `+234 803 *** **17` |
| Email | `ad*****@g****.com` |
| Date of birth | `** *** 1988` (year only) |
| Address | `Ikeja, Lagos` (street line hidden) |

**Unmask interaction** [R]:
1. `MaskedValue` shows the masked text in mono plus an icon button "Reveal" (`eye`, 24 px target, accessible name "Reveal BVN").
2. Activating it opens `UnmaskDialog`: the field list (pre-ticked with the clicked field; other masked fields in the same panel are offered as checkboxes), a required **Purpose** select from the tenant reference list `pii_purpose` (e.g. KYC verification, Customer contact, Disbursement account check, Audit review, Regulatory request, Complaint handling), and an optional note. Copy: "This is logged with your name, purpose and time."
3. If step-up is needed, SCR-GLB-03 runs first.
4. On success the values show in place for **60 s**, with a countdown ring and a "Hide" button. Values re-mask on timeout, route change, tab hide (`visibilitychange`) or sign-out. Clear values live only in component memory: never in the TanStack Query cache, local storage or logs.
5. The panel shows "Revealed by you at 09:42 for KYC verification" until navigation.
- Screen readers: masked state reads "BVN, masked, ending 91"; revealed reads the value; the 10 s-left warning is announced politely once.
- Without `pii:unmask` the Reveal button is absent and the value reads "Masked".
- Clipboard: a "Copy" button appears only while revealed, and copying is itself logged [R].

### 7.4 Maker-checker banner

**Facts** [F FR-SEC-007, TRD §6.4]: covered actions are config activation, product activation, limit changes, role assignment, template changes, disbursement release, waiver approval, pack activation, licence import and adapter binding. The checker must hold the checker permission in scope and differ from the maker; where policy requires, also from the approver (FR-DSB-009). Execution re-validates and **fails stale** if state changed.

**On the record (maker's and everyone's view)** [R]: an info `Banner` under the title bar or context bar:
> **Pending checker approval** · Product SBL-036 v8 activation requested by Tunde Bakare, 27 Aug 2026, 09:42 · CR-2026-00418. The live version (v7) stays in force until a checker approves.
> Actions: *View request* · *Withdraw request* (maker only)

- Fields affected by the pending request are read-only, with a lock icon and tooltip "Locked by pending change request CR-2026-00418".
- The maker never sees an Approve button on their own request. Where it would be, a line says "You made this request, so another user must check it."

**Checker view** (SCR-ADM-07 detail): maker, time, reason; a **diff of the change** (field, current, proposed; additions in success tone, removals struck through in danger tone, both with "+"/"−" text markers); validation results; Approve (step-up where configured) and Reject (reason required). States: `change_request_stale` (§6.2), approved → "Executed" with result, rejected → returned to maker with reason.

### 7.5 SoD-blocked action messaging

**Facts** [F FR-SEC-006, FR-APV-005, FR-CMP-014, FR-DSB-009, TRD §8.1]: SoD is checked at assignment time and at action time.

**Pattern** [R]: the action stays visible and is disabled (`aria-disabled="true"`, still focusable), with a reason line beneath it in tertiary text and a `shield-alert` icon:

| Situation | Copy |
|---|---|
| Approver originated / recommended / approved lower | "You can't approve this application because you recommended it on 26 Aug 2026 (segregation of duties)." |
| Clear own screening alert | "You can't clear this alert because you originated the application." |
| Disbursement checker = maker | "You prepared this instruction. A different disbursement checker must release it." |
| Checker = approver (policy on) | "You approved this facility. Bank policy requires a checker who was not an approver." |
| Role assignment conflict (admin) | Inline error on the role field: "Disbursement Maker conflicts with Disbursement Checker (SoD rule SOD-003). Remove one or request an exception." |

Never just hide an SoD-blocked action from someone who otherwise holds the permission: they need to know who can act instead. Where known, add "Eligible: 3 approvers in Ikeja region" with a link to reassign.

### 7.6 Step-up authentication

**Facts** [F FR-SEC-013, TRD §8.2]: re-authentication within the last 5 minutes (configurable) for approval above threshold, disbursement release, PII unmask, configuration and role changes, licence import. The step-up reference is stored on the audit event. TOTP in MVP; WebAuthn in P4.

**Pattern** [R]: actions that need step-up show a small `shield` icon after the label and the hint "Requires verification". On activation, the step-up dialog (SCR-GLB-03) opens, the original request is held, and after success it is sent with the **same idempotency key**. The OTP field accepts paste and autofill (`autocomplete="one-time-code"`) and has no cognitive test or puzzle (WCAG 2.2 3.3.8). A 5-minute "verified" window is shown in the user menu ("Verified · 4 min left") so users know when they will be asked again.

### 7.7 Reason codes

**Facts** [F FR-CRD-010, FR-APV-008, FR-APP-006, FR-CMP-043, TRD §7.2; examples F v3]: decisions carry **ordered** reason codes, each with a customer-facing text key; declines and conditional approvals need reason codes; terminal states need mandatory reason codes.

**Display** [R]:
- `ReasonCode` = code chip in mono 11 px on neutral fill (`DSR_ABOVE_THRESHOLD`) followed by the staff explanation in body text ("46.2% exceeds 40.0%. Requires counter-offer or approver exception."). Tone comes from the outcome, not the code.
- Ordered lists keep their order with a visible rank (1, 2, 3); rank 1 is labelled "Principal reason".
- In customer-facing previews (adverse action notice, P5; offer), show the customer text and hide the code.
- Reason-code pickers (decline, withdraw, waive, send back) are searchable selects from the tenant reference list, showing code + label; free text is an additional field, never a replacement.

### 7.8 Confidence chips (extraction, P2)

**Facts** [F v3, FR-DOC-024/025]: each extracted field has a confidence %. Threshold default 75 %. v3 tones: below threshold → bad, below 90 → warn, else OK. Fields below threshold sort first.

| Band | Tone | Text |
|---|---|---|
| < threshold | danger | "58% · below 75%" |
| threshold – 89 % | warning | "84%" |
| ≥ 90 % | success | "96%" |
| Accepted by user | success + `check` | "Accepted" (original confidence in tooltip) |
| Corrected | info + `pencil` | "Corrected · was 58%" |
| No extraction (MVP manual) | neutral | "Manual entry" |

The chip always includes the number; colour is secondary. The threshold is shown in the field-list header ("11 fields · threshold 75% · 2 flagged") [F v3].

### 7.9 Idempotent retry UI

**Facts** [F PR-05, FR-DSB-005/006/007, FR-CBA-007/011, TRD §10.1, §11; v3 posting panel]: every external-effect POST carries an `Idempotency-Key`; a replay returns the original response; postings have states pending, failed-retryable, failed-intervention and compensating; the saga never auto-compensates a posting the CBA may have applied without looking it up first.

**Client rules** [R]:
1. **Two keys, two layers** [F TRD §10.1, §11]. The HTTP `Idempotency-Key` (SPA → LOS API) is generated by the client **once per user intent** (when the confirm dialog opens), not per click; double-clicks and network retries of that request reuse it. The **CBA idempotency key** (LOS → core, `{tenant}:{operation}:{business_key}:{attempt_group}`) is generated by the server; the UI only displays it. Each "Retry posting" press is a new intent, so it gets a new HTTP key, while the server decides which CBA key to replay.
2. If the response does not arrive (timeout, network drop), the UI enters **"Outcome unknown"**: "We didn't get a reply. Checking whether the instruction reached the core…" It then reads the resource (`GET /disbursements/{id}`) before offering anything. It never offers "Submit again" with a new key for the same intent.
3. The **posting panel** (SCR-DSB-03) shows: status chip, idempotency reference (mono, copyable), CBA correlation ID, adapter name and version, attempts "2 of 5", and an attempt log (newest first: time, outcome, canonical error code, plain explanation). [F v3]
4. Failed-retryable: danger banner "Core banking posting failed. Funds have NOT left the bank." + **Retry posting** (primary) [F v3]. The second sentence depends on the failure [R; see §12 C-07]:
   - after a **timeout or indeterminate** result: "Safe to retry: the same idempotency key will be replayed." [F v3 copy]
   - after a **definitive business rejection** (e.g. `GL_PERIOD_CLOSED`): "Safe to retry once the cause is fixed: the core confirmed no posting was made, so the retry starts a new attempt group." The new key is shown in the panel.
   Retrying sets "Sending…" (button disabled, polite live region "Posting sent to core"), then the acknowledged or failed state from the server.
5. Failed-intervention: no Retry button. Instead: "Needs manual intervention. The adapter could not confirm whether the core applied this posting." + *Open exception* (SCR-DSB-04) + *Run balance enquiry* (lookup-before-retry). [R from TRD §11]
6. Acknowledged: success banner with the loan account number in mono, and the case advances [F v3].
7. Business rejections show the canonical error code and the adapter's native code (`CBA-ERR-4412 GL_PERIOD_CLOSED`) and name the fix and its owner ("Correct the GL mapping in Adapter bindings, then retry") [F v3; R for the screen named].

### 7.10 Audit timeline

**Facts** [F FR-AUD-001/002/005, TRD §5.4]: events carry actor, role snapshot, on-behalf-of, timestamp with timezone, source IP/device, action, entity, before/after (PII-masked), reason, correlation ID and hash chain. System actors are named (`system:rules-engine`).

**Component** `AuditTimeline` [R]: a vertical list grouped by day (Africa/Lagos). Each item: time `09:42:05 WAT` (tabular) · actor (name, role snapshot, "on behalf of Yusuf Lawal" when delegated; system actors in mono with a `cpu` icon) · action sentence ("Recommended counter-offer") · entity link · reason chip · expand control. Expanded: before/after diff table (masked), correlation ID, source IP, device, step-up reference, change-request ID. Filters: action type, actor, date range, entity. In AUD screens each event also shows its sequence number and a hash-verified tick from the last integrity run. `role="feed"`, each item an `article` with `aria-posinset`/`aria-setsize`; "Load older" button, no infinite scroll [R].

### 7.11 Money

**Facts** [F TRD §4.1: `Money` = numeric(20,4) + ISO 4217; floats banned; FR-TEN-007 multi-currency; NFR-017 en-NG]. v3 formats with `en-US` [F v3 source]; this brief sets **en-NG** [R].

| Context | Format | Example (Intl output verified) |
|---|---|---|
| Lists, queues, tiles, terms | NGN, no decimals when kobo = 0 | `₦4,500,000` |
| Any amount with non-zero kobo | Always 2 dp, never rounded away | `₦9,382,250.50` |
| Financial detail (fees, deductions, schedule, disbursement advice, offer) | Always 2 dp | `₦9,382,250.00` |
| Deductions | Minus sign, 2 dp, right-aligned | `−₦148,300.00` |
| Other currencies | ISO code, not symbol (en-NG renders USD as "US$") | `USD 12,000.00` with base equivalent below in tertiary: `≈ ₦18,420,000 at 1,535.00 (27 Aug)` |
| Compact (stat tile deltas only) | One decimal, full value in tooltip and accessible name | `₦25.0M` (Intl gives capital M; v3 used "m") |
| Exports, CSV, audit diffs | ISO code, 2–4 dp as stored | `NGN 4500000.00` |

All amounts: `tabular-nums`, right-aligned in tables, never wrapped. Inputs: `MoneyInput` with a fixed ₦ (or code) prefix, grouping as you type, decimal pad on touch, value held as a decimal string, never a JS float [R].

### 7.12 Date and time

**Facts** [F TRD §4.1: stored as UTC `timestamptz`, rendered in tenant timezone Africa/Lagos; business-day maths on the tenant calendar].

| Context | Format | Example |
|---|---|---|
| Date | `d MMM yyyy` | `27 Aug 2026` [F v3] |
| Date-time | `d MMM yyyy, HH:mm` (24 h) | `27 Aug 2026, 09:42` [F v3 "27 Aug 2026 · 09:42"] |
| Audit, evidence, integration logs | + seconds + zone | `27 Aug 2026, 09:42:05 WAT`; ISO 8601 with offset in tooltip and exports (`2026-08-27T09:42:05+01:00`) |
| Relative | Notifications and "updated 3 min ago" only; absolute time in the tooltip | `3 min ago` |
| Durations | SLA rules (§7.2) | `6h 12m` |
| Date inputs | Typed `dd/mm/yyyy` with a calendar popover; non-business days marked in the picker for business-date fields | — |

All times render in Africa/Lagos whatever the browser's zone; when the browser zone differs, the user menu shows "Times shown in WAT" [R].

### 7.13 Reference numbers and long identifiers

Application references (`LN-2026-04871`), CR IDs, idempotency keys (`IDMP-DI-01188-A7F3`), correlation IDs (`CBA-TX-99241-3`), account numbers (`8801-4471-02`), document hashes (SHA-256) [F v3 examples]:
- `RefText` in `--font-mono` at 0.92 em, `tabular-nums slashed-zero`, `letter-spacing: 0.01em`, `white-space: nowrap` [R].
- Over 24 characters (hashes, UUIDs): middle truncation `9f2c…a71e`, full value in tooltip and accessible name, and a copy button (24 px) that announces "Copied" [R].
- References are links when the user can open the target.
- Human application references follow the tenant format, e.g. `{LE}-{YYYY}-{SEQ:6}` [F TRD §4.1]; never truncate them.

### 7.14 Authority and limits

Wherever an approval or release depends on authority, show it [F v3 approval packet; FR-APV-002/006, TRD §6.3]: "Your effective limit ₦20,000,000 (lowest of: user ₦20m, role ₦50m, Ikeja region ₦25m)". When delegated: "Delegated by Yusuf Lawal until 30 Sep 2026, cap ₦10,000,000". Required authority comes from the matrix row: "Required: tier 3 · ₦20m–₦50m · matrix v8 row 6". [R for the exact layout]

### 7.15 Licence states

**Facts** [F D-034, TRD §2.6]: warnings at T-60, T-30, T-7, entering grace, breach. Expiry blocks new applications and logins beyond grace. Never blocked: in-flight sagas and reconciliation, auditor and regulator read and evidence export, data subject requests.

| State | UI [R] |
|---|---|
| ≤ 60 / 30 / 7 days | Admins only: warning banner "Licence expires 30 Nov 2026 (60 days)". At 7 days, all users see a neutral line in the user menu. |
| Grace | All users: warning banner "Licence in grace period until 7 Dec 2026. New applications will be blocked after that." |
| Expired beyond grace | "New application" disabled with the reason. Sign-in blocked for operational roles with an explanation page. Disbursements in flight, reconciliation, auditor access and evidence export keep working, and the banner says so. |
| Module not entitled | Nav item hidden; deep link shows "This module is not included in your licence." |
| User cap reached | Sign-in page: "Your bank has reached its licensed user limit. Contact your administrator." |

### 7.16 Forms

- Labels above fields, always visible; required marked with "Required" text, not only an asterisk [R].
- Validation on blur for format, on submit for cross-field rules (FR-APP-002); errors inline (danger text + `alert-circle` icon) and in the `ErrorSummary` [R].
- **Redundant entry** (WCAG 2.2 3.3.7): data the bank already holds is pre-filled from the CBA and labelled with its source (FR-CUS-002, "never re-key"). Data entered earlier in the same journey is never asked again [F FR-CUS-002; R for the label].
- **Field provenance** (LOS-FR-301): a small tertiary tag after the label: `CBA`, `Document`, `Staff`, `Applicant`, `Partner`. Hover gives source reference and time [R].
- Autosave of drafts every 10 s and on blur; "Saved 09:42" indicator (FR-CHN-003) [R].
- Destructive or irreversible actions use `ConfirmDialog` with the consequence stated and a mandatory reason where the API requires one [R].

---
## 8. Screen inventory

### 8.1 Summary

**Counts by primary role and phase** (each screen counted once, under its first-listed role; shell screens under "All staff"):

| Primary role | MVP | Later | Total |
|---|--:|--:|--:|
| All staff (shell) | 8 | 0 | 8 |
| Loan officer / RM | 12 | 1 | 13 |
| Branch ops / documentation | 7 | 3 | 10 |
| Credit analyst | 4 | 3 | 7 |
| Approver / committee | 2 | 2 | 4 |
| Compliance / AML | 4 | 5 | 9 |
| Legal / collateral | 2 | 0 | 2 |
| Disbursement maker | 5 | 2 | 7 |
| Disbursement checker | 1 | 0 | 1 |
| Product manager | 5 | 1 | 6 |
| Tenant administrator | 14 | 4 | 18 |
| Auditor / regulator | 7 | 0 | 7 |
| Platform operator | 0 | 3 | 3 |
| Applicant | 0 | 8 | 8 |
| **Total** | **71** | **32** | **103** |

**Screens each role can open** (any listing, including read-only ◐; shell screens included for staff roles):

| Role | MVP | Later |
|---|--:|--:|
| Loan officer / RM | 28 | 1 |
| Branch ops / documentation | 34 | 3 |
| Credit analyst | 28 | 4 |
| Approver / committee | 25 | 3 |
| Compliance / AML | 31 | 8 |
| Legal / collateral | 21 | 0 |
| Disbursement maker | 22 | 2 |
| Disbursement checker | 21 | 2 |
| Product manager | 17 | 3 |
| Tenant administrator | 31 | 7 |
| Auditor / regulator | 62 | 1 |
| Platform operator | 0 | 3 |
| Applicant | 0 | 8 |

Later screens by phase: P2: 2 · P3: 10 · P4: 10 · P5: 10.

**Inventory**

| ID | Screen | Roles (primary first) | Phase | Requirements |
|---|---|---|---|---|
| [SCR-GLB-01](#scr-glb-01--sign-in) | Sign in | LO, OPS, CA, APR, CMP, LEG, DM, DC, PM, ADM, AUD | MVP (P0) | LOS-FR-302, LOS-FR-305, FR-SEC-015, FR-SEC-012, LOS-FR-316 |
| [SCR-GLB-02](#scr-glb-02--mfa-verification-and-enrolment) | MFA verification and enrolment | LO, OPS, CA, APR, CMP, LEG, DM, DC, PM, ADM, AUD | MVP (P0) | FR-SEC-013, LOS-FR-302 |
| [SCR-GLB-03](#scr-glb-03--step-up-verification-dialog) | Step-up verification dialog | APR, DC, ADM, PM, CMP, AUD, LO | MVP (P0) | FR-SEC-013, FR-APV-011 |
| [SCR-GLB-04](#scr-glb-04--session-timeout-and-expiry) | Session timeout and expiry | LO, OPS, CA, APR, CMP, LEG, DM, DC, PM, ADM, AUD | MVP (P0) | FR-SEC-015 |
| [SCR-GLB-05](#scr-glb-05--notifications-centre) | Notifications centre | LO, OPS, CA, APR, CMP, LEG, DM, DC, PM, ADM | MVP (P1) | FR-NTF-001, FR-WFL-010, FR-WFL-006 |
| [SCR-GLB-06](#scr-glb-06--my-profile-sessions-and-cover) | My profile, sessions and cover | LO, OPS, CA, APR, CMP, LEG, DM, DC, PM, ADM, AUD | MVP (P1) | FR-SEC-011, FR-SEC-015, FR-WFL-005, FR-APV-006 |
| [SCR-GLB-07](#scr-glb-07--global-search) | Global search | LO, OPS, CA, APR, CMP, LEG, DM, DC, AUD | MVP (P1) | FR-APP-001, FR-RPT-010, FR-SEC-001 |
| [SCR-GLB-08](#scr-glb-08--system-pages) | System pages | LO, OPS, CA, APR, CMP, LEG, DM, DC, PM, ADM, AUD | MVP (P0) | FR-SEC-003, LOS-FR-316, NFR-006 |
| [SCR-WRK-01](#scr-wrk-01--task-inbox) | Task inbox | LO, OPS, CA, APR, CMP, LEG, DM, DC, AUD | MVP (P1) | FR-WFL-009, FR-WFL-004, FR-WFL-005, FR-WFL-006, FR-WFL-003, FR-APP-009 |
| [SCR-WRK-02](#scr-wrk-02--pipeline) | Pipeline | OPS, LO, CA, APR, CMP, LEG, DM, DC, AUD | MVP (P1) | FR-RPT-001, FR-RPT-010, FR-APP-009, FR-WFL-006 |
| [SCR-WRK-03](#scr-wrk-03--applications) | Applications | LO, OPS, CA, APR, CMP, LEG, DM, DC, AUD | MVP (P1) | FR-APP-001, FR-RPT-010, FR-APP-006 |
| [SCR-LED-01](#scr-led-01--leads-and-pre-qualification) | Leads and pre-qualification | LO | Later (P4) | FR-CHN-005, FR-CHN-006 |
| [SCR-APP-01](#scr-app-01--new-application-product-and-applicant) | New application: product and applicant | LO, OPS | MVP (P1) | FR-CHN-001, FR-CHN-002, FR-CHN-007, FR-CUS-002, FR-PRD-006, FR-APP-001, FR-CUS-001 |
| [SCR-APP-02](#scr-app-02--application-capture-form) | Application capture form | LO, OPS | MVP (P1) | FR-APP-002, FR-APP-004, FR-CHN-003, FR-CUS-001, FR-CUS-006, FR-CUS-008, FR-APP-008, FR-APP-005, LOS-FR-301, NFR-013 |
| [SCR-APP-03](#scr-app-03--review-and-submit) | Review and submit | LO, OPS | MVP (P1) | FR-APP-008, FR-CMP-010, FR-CMP-021, FR-CUS-008, FR-APP-001 |
| [SCR-APP-04](#scr-app-04--case-workspace-summary) | Case workspace: Summary | LO, OPS, CA, APR, CMP, LEG, DM, DC, AUD | MVP (P1) | FR-APP-008, FR-APP-009, FR-CUS-009, LOS-FR-282, FR-WFL-001, FR-APP-004 |
| [SCR-APP-05](#scr-app-05--case-applicant-and-kyc) | Case: Applicant and KYC | CMP, LO, CA, OPS, AUD | MVP (P1) | FR-CUS-003, FR-CUS-005, FR-CUS-006, FR-CUS-007, FR-CUS-008, FR-CMP-010, FR-CMP-011, FR-APP-004, FR-CMP-036, FR-SEC-017 |
| [SCR-APP-06](#scr-app-06--case-documents-checklist) | Case: Documents (checklist) | OPS, LO, CA, LEG, AUD | MVP (P1) | FR-DOC-001, FR-DOC-002, FR-DOC-004, FR-DOC-005, FR-DOC-006, FR-DOC-007, FR-DOC-008, FR-DOC-009, NFR-016 |
| [SCR-APP-07](#scr-app-07--case-collateral) | Case: Collateral | LEG, CA, OPS, AUD | MVP (P1) | FR-COL-001, FR-COL-002, FR-COL-003, FR-COL-004, FR-COL-005, FR-COL-006, FR-COL-008 |
| [SCR-APP-08](#scr-app-08--case-conditions) | Case: Conditions | LEG, OPS, CA, LO, AUD | MVP (P1) | FR-CPR-001, FR-CPR-002, FR-APV-009, FR-CPR-006 |
| [SCR-APP-09](#scr-app-09--case-notes-and-communications) | Case: Notes and communications | LO, OPS, CA, APR, CMP, LEG, DM, DC, AUD | MVP (P1) | FR-WFL-010, FR-NTF-005, FR-NTF-001 |
| [SCR-APP-10](#scr-app-10--case-audit) | Case: Audit | AUD, CMP, LO, OPS, CA, APR, LEG, DM, DC | MVP (P1) | FR-AUD-001, FR-AUD-002, FR-AUD-005, FR-AUD-009, FR-APP-005 |
| [SCR-APP-11](#scr-app-11--case-action-dialogs) | Case action dialogs | LO, OPS, CA, APR | MVP (P1) | FR-WFL-005, FR-WFL-007, FR-WFL-008, FR-APP-006, LOS-FR-283 |
| [SCR-APP-12](#scr-app-12--bulk-application-upload) | Bulk application upload | OPS | Later (P4) | LOS-FR-312, FR-CHN-001 |
| [SCR-PTY-01](#scr-pty-01--customer-360) | Customer 360 | LO, CA, CMP, OPS, APR, LEG, AUD | MVP (P1) | FR-CUS-009, FR-CUS-002, FR-CUS-006, FR-CUS-001, FR-CMP-036, FR-CUS-010, FR-SEC-017 |
| [SCR-PTY-02](#scr-pty-02--consent-record) | Consent record | LO, CMP, AUD | MVP (P1) | FR-CUS-008, FR-CMP-021, FR-CMP-032 |
| [SCR-DOC-01](#scr-doc-01--document-review-side-peek) | Document review side-peek | OPS, CA, LEG, AUD | MVP (P1) | FR-DOC-007, FR-DOC-005, FR-DOC-009, FR-DOC-010, FR-DOC-024, FR-DOC-025, FR-DOC-026, FR-DOC-050, FR-DOC-053 |
| [SCR-DOC-02](#scr-doc-02--waiver-request-and-approval) | Waiver request and approval | OPS, LEG, CA, APR | MVP (P1) | FR-DOC-008, FR-SEC-007, FR-CRD-015, FR-AUD-016 |
| [SCR-DOC-03](#scr-doc-03--upload-panel) | Upload panel | OPS, LO, LEG | MVP (P1) | FR-DOC-001, FR-DOC-002, FR-DOC-004, FR-DOC-005, NFR-016 |
| [SCR-DOC-04](#scr-doc-04--classification-and-extraction-review-queue) | Classification and extraction review queue | OPS | Later (P2) | FR-DOC-020, FR-DOC-021, FR-DOC-025, FR-DOC-027 |
| [SCR-DOC-05](#scr-doc-05--bank-statement-analysis) | Bank statement analysis | CA | Later (P2) | FR-DOC-040, FR-DOC-041, FR-DOC-042, FR-DOC-043, FR-DOC-044, FR-DOC-045, FR-DOC-046 |
| [SCR-CRD-01](#scr-crd-01--credit-assessment) | Credit assessment | CA, APR, AUD | MVP (P1) | FR-CRD-001, FR-CRD-003, FR-CRD-004, FR-CRD-008, FR-CRD-010, FR-CRD-014, FR-CMP-020, FR-CMP-027, FR-CMP-023 |
| [SCR-CRD-02](#scr-crd-02--bureau-report-viewer) | Bureau report viewer | CA, APR, AUD | MVP (P1) | FR-CRD-003, FR-CRD-004, FR-CMP-021 |
| [SCR-CRD-03](#scr-crd-03--decision-snapshot) | Decision snapshot | CA, AUD, APR, CMP | MVP (P1) | FR-CRD-010, FR-CRD-014, FR-CMP-043, FR-CRD-002 |
| [SCR-CRD-04](#scr-crd-04--credit-memo-and-recommendation) | Credit memo and recommendation | CA, APR, AUD | MVP (P1) | FR-CRD-013, FR-CRD-011, FR-CRD-015, FR-CRD-010, FR-APV-009 |
| [SCR-CRD-05](#scr-crd-05--financial-spreading) | Financial spreading | CA | Later (P3) | FR-CRD-012 |
| [SCR-CRD-06](#scr-crd-06--connected-exposure) | Connected exposure | CA, APR | Later (P3) | FR-CRD-007, FR-CUS-010, FR-CMP-024 |
| [SCR-APV-01](#scr-apv-01--decision-packet) | Decision packet | APR, CA, LO, AUD | MVP (P1) | FR-APV-010, FR-APV-005, FR-APV-007, FR-APV-008, FR-APV-009, FR-APV-012, FR-APV-002, FR-APV-006, FR-CRD-015 |
| [SCR-APV-02](#scr-apv-02--vote-dialog) | Vote dialog | APR | MVP (P1) | FR-APV-008, FR-APV-009, FR-CRD-011, FR-SEC-013 |
| [SCR-APV-03](#scr-apv-03--committee-meeting-and-e-voting) | Committee meeting and e-voting | APR | Later (P3) | FR-APV-004, FR-APV-003 |
| [SCR-APV-04](#scr-apv-04--mobile-approval) | Mobile approval | APR | Later (P3) | FR-APV-011, FR-SEC-013 |
| [SCR-OFR-01](#scr-ofr-01--offer-preparation-and-issue) | Offer preparation and issue | LO, OPS, AUD | MVP (P1) | FR-OFR-001, FR-OFR-002, FR-OFR-003, FR-OFR-004, FR-OFR-007, FR-CMP-025, FR-CRD-011 |
| [SCR-OFR-02](#scr-ofr-02--acceptance-and-execution) | Acceptance and execution | OPS, LEG, LO, AUD | MVP (P1) | FR-OFR-005, FR-OFR-006, FR-OFR-008, FR-OFR-009, FR-OFR-010, LOS-FR-314, FR-OFR-007 |
| [SCR-CPR-01](#scr-cpr-01--pre-disbursement-check) | Pre-disbursement check | DM, DC, OPS, AUD | MVP (P1) | FR-CPR-003, FR-CPR-004, FR-CPR-005, FR-CPR-002, FR-COL-008, FR-APV-012 |
| [SCR-DSB-01](#scr-dsb-01--disbursement-instruction-maker) | Disbursement instruction (maker) | DM, AUD | MVP (P1) | FR-DSB-001, FR-DSB-002, FR-DSB-004, FR-DSB-009, FR-DSB-011, FR-CBA-012 |
| [SCR-DSB-02](#scr-dsb-02--release-review-checker) | Release review (checker) | DC | MVP (P1) | FR-DSB-009, FR-SEC-007, FR-SEC-013, FR-APV-012, FR-DSB-005 |
| [SCR-DSB-03](#scr-dsb-03--posting-status-and-exception-handling) | Posting status and exception handling | DM, DC, OPS, AUD | MVP (P1) | FR-DSB-005, FR-DSB-006, FR-DSB-007, FR-CBA-007, FR-CBA-009, FR-CBA-011, FR-CBA-016, LOS-FR-313 |
| [SCR-DSB-04](#scr-dsb-04--disbursement-exception-queue) | Disbursement exception queue | DM, DC, OPS, ADM, AUD | MVP (P1) | FR-DSB-007, FR-CBA-011, LOS-CON-008, FR-CBA-010 |
| [SCR-DSB-05](#scr-dsb-05--reconciliation) | Reconciliation | DM, DC, OPS, ADM, AUD | MVP (P1) | FR-DSB-008, FR-CBA-017 |
| [SCR-DSB-06](#scr-dsb-06--booked-and-handover) | Booked and handover | LO, DM, OPS, AUD | MVP (P1) | FR-HND-001, FR-HND-003, FR-HND-004, FR-DSB-011 |
| [SCR-DSB-07](#scr-dsb-07--disbursement-reversal) | Disbursement reversal | DM, DC | Later (P3) | FR-DSB-012, FR-CBA-009 |
| [SCR-DSB-08](#scr-dsb-08--tranche-schedule) | Tranche schedule | DM, DC, CA | Later (P3) | FR-DSB-003, FR-DSB-002 |
| [SCR-CMP-01](#scr-cmp-01--screening-alert-queue) | Screening alert queue | CMP, AUD | MVP (P1) | FR-CMP-013, FR-CMP-011, FR-CUS-005, FR-CMP-014 |
| [SCR-CMP-02](#scr-cmp-02--alert-review-and-disposition-four-eyes) | Alert review and disposition (four-eyes) | CMP, AUD | MVP (P1) | FR-CMP-013, FR-CMP-014, FR-CMP-017, FR-CPR-004 |
| [SCR-CMP-03](#scr-cmp-03--regulatory-packs-and-rule-register) | Regulatory packs and rule register | CMP, ADM, PM, AUD | MVP (P1) | FR-CMP-001, FR-CMP-003, LOS-FR-310 |
| [SCR-CMP-04](#scr-cmp-04--data-subject-requests) | Data subject requests | CMP | Later (P5) | FR-CMP-033 |
| [SCR-CMP-05](#scr-cmp-05--retention-policies-and-legal-holds) | Retention policies and legal holds | CMP, ADM | Later (P5) | FR-CMP-034, FR-AUD-012 |
| [SCR-CMP-06](#scr-cmp-06--model-inventory) | Model inventory | CMP, PM | Later (P3) | FR-CMP-040, FR-CMP-041, FR-CMP-042 |
| [SCR-CMP-07](#scr-cmp-07--insider-and-related-party-register) | Insider and related-party register | CMP | Later (P3) | LOS-FR-306, FR-CMP-024 |
| [SCR-CMP-08](#scr-cmp-08--pii-inventory-and-processor-register) | PII inventory and processor register | CMP | Later (P5) | FR-CMP-031, FR-CMP-037, FR-CMP-035 |
| [SCR-AUD-01](#scr-aud-01--audit-explorer) | Audit explorer | AUD, CMP, ADM | MVP (P1) | FR-AUD-009, FR-AUD-001, FR-AUD-002, FR-AUD-007, FR-AUD-008, FR-AUD-005 |
| [SCR-AUD-02](#scr-aud-02--application-reconstruction-as-at) | Application reconstruction (as at) | AUD, CMP | MVP (P1) | FR-AUD-011, FR-CRD-014, FR-AUD-010 |
| [SCR-AUD-03](#scr-aud-03--evidence-pack-export) | Evidence pack export | AUD, CMP | MVP (P1) | FR-AUD-010, FR-RPT-008 |
| [SCR-AUD-04](#scr-aud-04--overrides-waivers-and-exceptions-report) | Overrides, waivers and exceptions report | AUD, CMP | MVP (P1) | FR-AUD-016, FR-CRD-015 |
| [SCR-AUD-05](#scr-aud-05--audit-integrity) | Audit integrity | AUD, ADM | MVP (P1) | FR-AUD-004, FR-AUD-003, FR-AUD-013 |
| [SCR-AUD-06](#scr-aud-06--pii-access-log) | PII access log | AUD, CMP | MVP (P1) | FR-AUD-006, FR-CMP-036 |
| [SCR-RPT-01](#scr-rpt-01--operational-dashboard) | Operational dashboard | OPS, CA, APR, CMP, DM, PM, ADM, AUD | MVP (P1) | FR-RPT-001, FR-RPT-010 |
| [SCR-RPT-02](#scr-rpt-02--reports-and-exports) | Reports and exports | OPS, CMP, PM, ADM, AUD | Later (P5) | FR-RPT-002, FR-RPT-003, FR-RPT-004, FR-RPT-005, FR-RPT-006, FR-RPT-007, FR-RPT-008, FR-RPT-009 |
| [SCR-PRD-01](#scr-prd-01--product-catalogue) | Product catalogue | PM, ADM, AUD | MVP (P1) | FR-PRD-001, FR-PRD-002, FR-PRD-006, FR-CFG-001 |
| [SCR-PRD-02](#scr-prd-02--product-version-editor) | Product version editor | PM, ADM, AUD | MVP (P1) | FR-PRD-003, FR-PRD-004, FR-PRD-005, FR-PRD-006, FR-PRD-009, FR-CFG-002, FR-TEN-009, FR-SEC-007, LOS-FR-284 |
| [SCR-PRD-03](#scr-prd-03--rules-editor) | Rules editor | PM, CMP, CA, AUD | MVP (P1) | FR-CRD-001, FR-CRD-002, FR-TEN-009, FR-CFG-002 |
| [SCR-PRD-04](#scr-prd-04--approval-matrix-and-authority-limits) | Approval matrix and authority limits | PM, ADM, AUD | MVP (P1) | FR-APV-001, FR-APV-002, FR-APV-007, FR-SEC-007 |
| [SCR-PRD-05](#scr-prd-05--simulation) | Simulation | PM, CMP | Later (P3) | FR-PRD-008, FR-CRD-002, LOS-FR-308 |
| [SCR-PRD-06](#scr-prd-06--document-and-notification-templates) | Document and notification templates | PM, ADM, AUD | MVP (P1) | FR-OFR-001, FR-OFR-002, FR-NTF-002, FR-OFR-010, LOS-FR-314 |
| [SCR-PRD-07](#scr-prd-07--workflow-configuration) | Workflow configuration | ADM, PM, AUD | MVP (P1) | FR-WFL-001, FR-WFL-003, FR-WFL-004, FR-WFL-006, FR-WFL-011, FR-WFL-012, LOS-FR-282 |
| [SCR-ADM-01](#scr-adm-01--organisation-and-legal-entities) | Organisation and legal entities | ADM, AUD | MVP (P0) | FR-TEN-003, FR-TEN-001, FR-TEN-009 |
| [SCR-ADM-02](#scr-adm-02--users) | Users | ADM, AUD | MVP (P0) | FR-SEC-004, LOS-FR-302, LOS-FR-305, FR-SEC-015 |
| [SCR-ADM-03](#scr-adm-03--user-detail-and-effective-access) | User detail and effective access | ADM, AUD | MVP (P0) | FR-SEC-011, FR-SEC-008, FR-SEC-004 |
| [SCR-ADM-04](#scr-adm-04--roles-and-permissions) | Roles and permissions | ADM, AUD | MVP (P0) | FR-SEC-001, FR-SEC-002, FR-SEC-003, LOS-FR-278 |
| [SCR-ADM-05](#scr-adm-05--role-assignments) | Role assignments | ADM, AUD | MVP (P0) | FR-SEC-004, FR-SEC-005, FR-SEC-006, FR-SEC-007, FR-SEC-008 |
| [SCR-ADM-06](#scr-adm-06--segregation-of-duties) | Segregation of duties | ADM, CMP, AUD | MVP (P0) | FR-SEC-006, FR-APV-005, FR-CMP-014 |
| [SCR-ADM-07](#scr-adm-07--change-request-inbox-maker-checker) | Change-request inbox (maker-checker) | ADM, PM, CMP, DC, APR, OPS, AUD | MVP (P0) | FR-SEC-007, FR-TEN-009, FR-DOC-008, FR-NTF-002, FR-DSB-009 |
| [SCR-ADM-08](#scr-adm-08--reference-data-calendars-and-fx) | Reference data, calendars and FX | ADM, PM, AUD | MVP (P1) | FR-TEN-006, FR-TEN-007, FR-TEN-009, FR-CFG-001 |
| [SCR-ADM-09](#scr-adm-09--prudential-parameters) | Prudential parameters | ADM, CMP, AUD | MVP (P1) | LOS-FR-307, FR-CMP-023 |
| [SCR-ADM-10](#scr-adm-10--adapter-bindings-and-mappings) | Adapter bindings and mappings | ADM, DM, AUD | MVP (P1) | FR-CBA-003, FR-CBA-012, FR-PRD-009, FR-CBA-020, FR-CBA-019, LOS-FR-309, FR-CBA-015, FR-CBA-004 |
| [SCR-ADM-11](#scr-adm-11--integration-monitor) | Integration monitor | ADM, DM, DC, AUD | MVP (P1) | FR-CBA-010, FR-CBA-016, FR-AUD-008, FR-CBA-008, NFR-011 |
| [SCR-ADM-12](#scr-adm-12--licence) | Licence | ADM, AUD | MVP (P0) | LOS-FR-316, FR-SEC-013, FR-SEC-007 |
| [SCR-ADM-13](#scr-adm-13--import-centre-signed-bundles) | Import centre (signed bundles) | ADM | Later (P5) | FR-TEN-010, FR-CMP-012, FR-CFG-004 |
| [SCR-ADM-14](#scr-adm-14--delegations-and-out-of-office-admin) | Delegations and out-of-office (admin) | ADM, AUD | MVP (P1) | FR-WFL-005, FR-APV-006, FR-SEC-008 |
| [SCR-ADM-15](#scr-adm-15--support-access-grants) | Support access grants | ADM | Later (P5) | LOS-FR-277 |
| [SCR-ADM-16](#scr-adm-16--branding-and-terminology) | Branding and terminology | ADM | Later (P3) | FR-TEN-005, FR-TEN-004, FR-TEN-008 |
| [SCR-ADM-17](#scr-adm-17--break-glass-and-access-recertification) | Break-glass and access recertification | ADM, CMP | Later (P5) | FR-SEC-009, FR-SEC-010 |
| [SCR-OPR-01](#scr-opr-01--installation-health) | Installation health | OPR | Later (P5) | LOS-FR-276, NFR-011 |
| [SCR-OPR-02](#scr-opr-02--adapter-registry-and-version-pinning) | Adapter registry and version pinning | OPR, ADM | Later (P5) | LOS-FR-275, FR-CBA-018, FR-CBA-013 |
| [SCR-OPR-03](#scr-opr-03--support-sessions) | Support sessions | OPR | Later (P5) | LOS-FR-277, LOS-FR-274 |
| [SCR-POR-01](#scr-por-01--sign-in-with-one-time-code) | Sign in with one-time code | APL | Later (P4) | LOS-FR-303 |
| [SCR-POR-02](#scr-por-02--products-and-eligibility-check) | Products and eligibility check | APL | Later (P4) | FR-CHN-006, FR-PRD-007 |
| [SCR-POR-03](#scr-por-03--apply) | Apply | APL | Later (P4) | FR-CHN-001, FR-CHN-003, FR-APP-002, FR-CUS-008, LOS-FR-311 |
| [SCR-POR-04](#scr-por-04--upload-documents) | Upload documents | APL | Later (P4) | FR-DOC-001, FR-DOC-002, NFR-016 |
| [SCR-POR-05](#scr-por-05--application-status-tracker) | Application status tracker | APL | Later (P4) | FR-NTF-004 |
| [SCR-POR-06](#scr-por-06--offer-review-and-acceptance) | Offer review and acceptance | APL | Later (P4) | FR-OFR-003, FR-OFR-004, FR-OFR-005, FR-OFR-006, FR-CMP-025, FR-OFR-008 |
| [SCR-POR-07](#scr-por-07--consents-and-communication-preferences) | Consents and communication preferences | APL | Later (P4) | FR-CUS-008, FR-NTF-003, FR-CMP-032 |
| [SCR-POR-08](#scr-por-08--messages) | Messages | APL | Later (P4) | FR-WFL-010, FR-NTF-004 |

### 8.2 Spec format

Each screen below gives: roles (first = primary), phase, requirement IDs, purpose, layout (with the v3 source where one exists), key data, actions (permission, step-up "SU", maker-checker "MC", endpoint), states that differ from §6, and API. Unless a screen says otherwise, the §6.1 contracts apply in full. Endpoint paths are relative to `/api/v1`.

### 8.3 Global and shell

#### SCR-GLB-01 · Sign in
**Roles:** LO, OPS, CA, APR, CMP, LEG, DM, DC, PM, ADM, AUD · **Phase:** MVP (P0) · **Reqs:** LOS-FR-302, LOS-FR-305, FR-SEC-015, FR-SEC-012, LOS-FR-316
**Purpose.** Authenticate staff with the local identity store or LDAP/AD bind (MVP); SSO button appears in P4.
**Layout.** Centred 400 px card on `bg-subtle`; brand block; "Institution A" name; username, password; "Sign in"; environment banner if non-prod. [R]
**Data.** Username, password; identity source (local / directory) chosen automatically by username domain, or a select when both exist [R].
**Actions.** Sign in (`POST /auth/login`) · Forgot password (local store only; shows "Contact your administrator" for LDAP users) [R] · Sign in with SSO (P4).
**States.** Error: generic "Username or password is incorrect" (no account enumeration); lockout "Too many attempts. Try again in 5 min" from `429` with `Retry-After`; licence user cap / expired (§7.15); maintenance. Loading: button pending only. Password field allows paste and password managers (WCAG 3.3.8).
**API.** `POST /auth/login`.

#### SCR-GLB-02 · MFA verification and enrolment
**Roles:** LO, OPS, CA, APR, CMP, LEG, DM, DC, PM, ADM, AUD · **Phase:** MVP (P0) · **Reqs:** FR-SEC-013, LOS-FR-302
**Purpose.** Verify the TOTP second factor; enrol a factor on first sign-in (MFA mandatory for all staff, TRD §8.2).
**Layout.** Same card. Verify: 6-digit single input (not six boxes) with `autocomplete="one-time-code"`, "Verify". Enrol: QR code + manual key (mono, copyable) + confirm code + 8 recovery codes (download/print, "I've stored these" checkbox). [R]
**Actions.** Verify (`POST /auth/mfa/verify`) · Use a recovery code · Back to sign-in.
**States.** Error inline "That code didn't work. Codes change every 30 seconds." After 5 failures: lockout message. Success: route to the originally requested page.
**API.** `POST /auth/mfa/verify`; enrolment endpoints under `/auth/mfa/*` [R].

#### SCR-GLB-03 · Step-up verification dialog
**Roles:** APR, DC, ADM, PM, CMP, AUD, LO · **Phase:** MVP (P0) · **Reqs:** FR-SEC-013, FR-APV-011
**Purpose.** Re-verify identity before a high-risk action (approval above threshold, disbursement release, PII unmask, configuration/role change, licence import) [F TRD §8.2].
**Layout.** `Modal` sm (400 px): title "Verify it's you"; what is being authorised ("Approve LN-2026-04863 · ₦21,500,000"); code input; "Verify and continue"; "Cancel". [R]
**Actions.** Verify (`POST /auth/step-up`) → the held request is sent with its original `Idempotency-Key` (§7.6).
**States.** Error inline; 3 failures close the dialog and cancel the action with a toast; session expiry inside the dialog → SCR-GLB-04. Focus trapped; Esc cancels; focus returns to the triggering button.
**API.** `POST /auth/step-up`.

#### SCR-GLB-04 · Session timeout and expiry
**Roles:** LO, OPS, CA, APR, CMP, LEG, DM, DC, PM, ADM, AUD · **Phase:** MVP (P0) · **Reqs:** FR-SEC-015
**Purpose.** Warn before idle timeout (15 min) and absolute timeout (8 h); recover after expiry without losing work [F TRD §8.2].
**Layout.** Warning `Modal` at 2 min before idle expiry: "You'll be signed out in 1:59 due to inactivity" + "Stay signed in" (primary) + "Sign out". Absolute timeout: warning 5 min before, no extend. Expired: "Your session ended" dialog with inline re-sign-in (password + code); on success returns to the same route. Forced re-auth after a role change: "Your access changed. Sign in again to continue." [R]
**States.** The countdown is announced at 2:00 and 0:30 only (polite), not every second (WCAG 2.2.1). Unsaved drafts are autosaved before sign-out.
**API.** `GET /me` (heartbeat on activity), `POST /auth/login`, `POST /auth/logout`.

#### SCR-GLB-05 · Notifications centre
**Roles:** LO, OPS, CA, APR, CMP, LEG, DM, DC, PM, ADM · **Phase:** MVP (P1) · **Reqs:** FR-NTF-001, FR-WFL-010, FR-WFL-006
**Purpose.** In-app notifications: assignments, @mentions, SLA warnings and breaches, checker requests, decisions on my requests.
**Layout.** Popover from the bell (360 px, `shadow-popover`), tabs "Unread · All"; each item: icon, sentence, application ref, relative time; "Mark all read"; "View all" opens a full page list with filters. [R]
**Actions.** Open item (navigates; marks read) · Mark read/unread · Mark all read.
**States.** Empty: "You're all caught up." Loading: 5 skeleton items. New items announce politely ("2 new notifications") at most once a minute.
**API.** `GET /notifications`, `POST /notifications/{id}/actions/read` [R: not in TRD §10.2; needed for in-app channel FR-NTF-001].

#### SCR-GLB-06 · My profile, sessions and cover
**Roles:** LO, OPS, CA, APR, CMP, LEG, DM, DC, PM, ADM, AUD · **Phase:** MVP (P1) · **Reqs:** FR-SEC-011, FR-SEC-015, FR-WFL-005, FR-APV-006
**Purpose.** See my roles, scopes and limits; manage MFA; see and end sessions; set out-of-office cover and delegate authority.
**Layout.** Sections: Profile · My access (effective access, read-only, same component as SCR-ADM-03) · Security (MFA factors, recovery codes) · Sessions (device, IP, last active; "Sign out other sessions") · Out of office (dates, cover user) · Delegation of authority (delegate, cap ≤ my limit, start/end) · Preferences (single-key shortcuts on/off). [R]
**Actions.** Revoke session · Set cover (`POST /delegations`) · Delegate authority (`POST /delegations-of-authority`; SU; MC if the tenant registers delegation as a checker action) · Toggle shortcuts.
**States.** Delegation cap above own limit: inline error "Cap can't exceed your limit of ₦20,000,000" [F FR-APV-006]. SoD: cannot delegate to a user who would then approve their own originations; the warning names the conflict [R].
**API.** `GET /me`, `GET /me/effective-access`, `/delegations`, `/delegations-of-authority`, `/me/sessions` [R].

#### SCR-GLB-07 · Global search
**Roles:** LO, OPS, CA, APR, CMP, LEG, DM, DC, AUD · **Phase:** MVP (P1) · **Reqs:** FR-APP-001, FR-RPT-010, FR-SEC-001
**Purpose.** Jump to an application, customer or task by reference, name or masked identifier.
**Layout.** Command palette (`Ctrl/⌘ K`), 640 px, `shadow-popover`: input; grouped results (Applications, Customers, Pages); each result shows ref (mono), name, status chip; keyboard navigable. [R]
**States.** Empty query: recent items. No results: "Nothing in your scope matches 'okon'". Results are scope-filtered by the API, so nothing out of scope appears [F TRD §8.1]. Identifier search uses exact match against blind indexes, so a full BVN finds a party without showing it [F TRD §5.3].
**API.** `GET /applications?filter[q]=…`, `GET /parties?filter[q]=…` [R: `q` filter].

#### SCR-GLB-08 · System pages
**Roles:** LO, OPS, CA, APR, CMP, LEG, DM, DC, PM, ADM, AUD · **Phase:** MVP (P0) · **Reqs:** FR-SEC-003, LOS-FR-316, NFR-006
**Purpose.** Full-page 403, 404, 500, maintenance, licence-blocked and "open on a larger screen" (mobile scope, §4).
**Layout.** Content column, 22 px heading, one sentence of explanation, correlation ID where relevant, one primary way out ("Go to inbox"). [R]
**API.** None (driven by the failing response).

### 8.4 Work

#### SCR-WRK-01 · Task inbox
**Roles:** LO, OPS, CA, APR, CMP, LEG, DM, DC; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-WFL-009, FR-WFL-004, FR-WFL-005, FR-WFL-006, FR-WFL-003, FR-APP-009
**Purpose.** One consolidated list of my work across all applications and stages, with filters, sorting, saved views and bulk actions [F FR-WFL-009]. Stage queues in the sidebar are saved views of this screen.
**Layout.** `PageHeader` (title "Inbox", view selector) → saved-view chips (My tasks · Team queue (pull) · Checks · per-stage views) → `FilterBar` (stage, product, branch, SLA, age, assignee) → `DataTable`. Row: checkbox · task type icon · application ref (mono) + applicant · task ("Verify payslip net pay") · stage · SLA chip · age · assigned to/claimed by · action ("Open →"). Bulk bar appears on selection: Claim · Reassign · Release. Mobile: cards, no bulk actions. [R; table styling F v3 pipeline]
**Data.** Task id, type, application ref, applicant (party display name), product, amount, stage, SLA state and remaining time, created, assignee, claimed-by, on-behalf-of.
**Actions.**

| Action | Permission | SU | MC | Endpoint |
|---|---|---|---|---|
| Open task | `task:view` | – | – | navigate to the task's screen |
| Claim (pull queue) | `task:claim` | – | – | `POST /tasks/{id}/actions/claim` |
| Reassign / bulk reassign | `task:reassign` | – | – | `POST /tasks/{id}/actions/reassign` |
| Save view | `task:view` | – | – | `/tasks` saved views |

**States.** Empty (first use): "No tasks assigned to you." Filtered empty: "No tasks match" + Clear. Claim race: `409 state_conflict` → "Ngozi Eke claimed this task a moment ago" and the row updates. SoD: tasks the user cannot act on (e.g. approving own origination) are excluded by the API; if one appears through a stale cache, its action shows the §7.5 message. Delegated work shows "For Yusuf Lawal" [F FR-WFL-005]. Auditor: read-only list of all tasks, no claim or reassign.
**API.** `GET /tasks`, `/tasks/{id}/actions/{claim,complete,reassign}`.

#### SCR-WRK-02 · Pipeline
**Roles:** OPS, LO, CA, APR, CMP, LEG, DM, DC; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-RPT-001, FR-RPT-010, FR-APP-009, FR-WFL-006
**Purpose.** In-scope applications in flight, with SLA visibility. This is v3 screen 1 [F v3].
**Layout** [F v3]. Four `StatCard`s: Applications in flight (148, "+11 today") · Awaiting my action (12, accent-subtle tile, info text) · SLA breached (5, danger tile) · Approved this week (23, "₦218,400,000"). `FilterBar` of toggle chips + search (selected = accent fill with white text; unselected = outline). Dense `DataTable`: Reference · Applicant (+ branch) · Product · Amount (right) · Stage (+ flag note in its tone) · Days (right) · SLA chip · Assignee (+ role) · "Open →". Breached rows get `bg-danger-wash`. Footer: result count + "Updated 09:42 · refreshes every 60 s".
**Data.** As columns; tile values from `/dashboards/pipeline` [R key].
**Actions.** Open application (row click and the "Open →" link; whole row is clickable but the link is the keyboard target) · Toggle filters · Export CSV (P5, FR-RPT-008).
**States.** Loading: 4 tile skeletons + 8 row skeletons. Empty: "No applications in your scope." Tile click applies the matching filter [R]. Auditor: same, read-only. Tablet: tiles 2-up, rows as cards.
**API.** `GET /applications?filter[status]=in_flight…`, `GET /dashboards/pipeline`.

#### SCR-WRK-03 · Applications
**Roles:** LO, OPS, CA, APR, CMP, LEG, DM, DC; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-APP-001, FR-RPT-010, FR-APP-006
**Purpose.** Search all applications in scope, including terminal states (declined, withdrawn, expired, booked).
**Layout.** `PageHeader` + "New application" (primary, LO) → `FilterBar` (status incl. terminal, product, branch, channel, date range, officer) → `DataTable` with columns as SCR-WRK-02 plus canonical status chip and submitted date; cursor pagination ("Show 50 more"). [R]
**Actions.** New application (`application:create`) · Open · Clone (P3, FR-APP-007).
**States.** As §6.1. Licence expired: "New application" disabled with reason (§7.15).
**API.** `GET /applications`, `POST /applications`.

#### SCR-LED-01 · Leads and pre-qualification
**Roles:** LO · **Phase:** Later (P4) · **Reqs:** FR-CHN-005, FR-CHN-006
**Purpose.** Capture leads, run an indicative eligibility check without a hard bureau pull, convert to an application, record loss reasons [F; G-14a: pre-qualification is lead-level, outside the state machine].
**Layout.** List of leads (name, product interest, status, owner, age) + a lead detail drawer with pre-qualification result (indicative amount range, reasons) and "Convert to application". [R]
**Actions.** Create lead · Run pre-qualification · Convert (`POST /applications` with lead id) · Mark lost (reason code).
**States.** Indicative result labelled "Indicative only · not an offer". Consent missing for bureau soft check → blocked with `CONSENT_MISSING`.
**API.** Leads endpoints are not in TRD §10.2 [R: `/leads`, `/leads/{id}/prequalifications`].

### 8.5 Application and case workspace

#### SCR-APP-01 · New application: product and applicant
**Roles:** LO, OPS · **Phase:** MVP (P1) · **Reqs:** FR-CHN-001, FR-CHN-002, FR-CHN-007, FR-CUS-002, FR-PRD-006, FR-APP-001, FR-CUS-001
**Purpose.** Start an application: choose product and applicant type, find the customer in the CBA, catch duplicates.
**Layout.** Three-step `Stepper` page (max 880 px): 1) Product (cards per available product with amount/tenor range and version "v7 · live since 1 Aug"); 2) Applicant type (Individual / Limited company in MVP; sole proprietor, partnership, group in P3) [F phase_map COMPLETES]; 3) Find customer: search by account number, BVN (masked entry field), RC number or name → CBA results with "Use this customer" or "New to bank". Dedupe panel shows existing applications and parties that match (fuzzy name, identity, phone, email). [R]
**Data.** Product + live version, channel (staff-assisted, preset), originating user and branch (preset from assignment), CBA customer id, matched applications.
**Actions.** Create draft (`application:create`, `POST /applications`, `Idempotency-Key`) · Open existing match · Override duplicate flag with reason (where policy flags rather than blocks).
**States.** CBA lookup unavailable → `integration_unavailable`: "Core banking lookup is unavailable. You can continue as new-to-bank and the record will be matched later", with the exception queued (PR-08) [R]. Duplicate blocked by policy: danger banner listing the match with link. Product version pinned: "This application will stay on v7 even if v8 goes live" [F FR-PRD-006].
**API.** `GET /products`, `GET /parties?filter[q]=…`, `GET /parties/{id}/customer-360`, `POST /applications`.

#### SCR-APP-02 · Application capture form
**Roles:** LO, OPS · **Phase:** MVP (P1) · **Reqs:** FR-APP-002, FR-APP-004, FR-CHN-003, FR-CUS-001, FR-CUS-006, FR-CUS-008, FR-APP-008, FR-APP-005, LOS-FR-301, NFR-013
**Purpose.** Capture the application with dynamic, product- and applicant-type-driven forms; also the **amend** mode before approval (FR-APP-005).
**Layout.** Left: section list with completion ticks (Facility request · Applicant · Employment/Business · Related parties (joint, guarantors, directors, UBOs) · Consents · Declarations). Centre: one section at a time, max 880 px, two-column fields ≥ 1024. Right (≥ 1280): completeness meter + "Outstanding" list (FR-APP-008). Footer bar: "Saved 09:42" · Back · Next / Save and exit. [R; completeness bar F v3 "Case completeness"]
**Data.** Product-schema fields; party fields (PII entered in clear, displayed masked after save); related parties each with role; beneficial owners with ownership % and layers (threshold from config) [F FR-CUS-006]; granular consents (bureau, processing, marketing, third-party sharing; GSI in P4) each with timestamp [F FR-CUS-008]; provenance tags (§7.16).
**Actions.** Save draft (autosave, `PATCH /applications/{id}` with `If-Match`) · Add related party · Record consent · Amend after submit (amend mode: changed fields highlighted, "This will re-run: KYC, decision" notice) [F FR-APP-005 "re-triggering of affected checks"].
**States.** Validation: inline + summary. Save conflict `412`: stale banner, local edits kept. Offline: draft kept locally and synced (NFR-016). Draft expiry warning "Draft expires in 3 days" [F FR-CHN-003]. Masked PII after save, with Reveal (§7.3). Amend mode on a case in Approval: banner "Amending sends this application back to Assessment".
**API.** `PATCH /applications/{id}`, `GET /applications/{id}/completeness`, `/parties/{id}/consents`, `/parties/{id}/relationships`.

#### SCR-APP-03 · Review and submit
**Roles:** LO, OPS · **Phase:** MVP (P1) · **Reqs:** FR-APP-008, FR-CMP-010, FR-CMP-021, FR-CUS-008, FR-APP-001
**Purpose.** Final review before submission, with blockers listed.
**Layout.** Read-only summary of every section with "Edit" links; blockers panel (missing mandatory fields, missing consent, mandatory documents not yet uploaded where the product requires them at submit); declaration checkbox; "Submit application". [R]
**Actions.** Submit (`application:submit`, `POST /applications/{id}/actions/submit`, `Idempotency-Key`, `If-Match`).
**States.** Blocked: Submit disabled, with the blocker count and links. Success: page shows the reference `LN-2026-04910` (mono, large) and next stage "KYC & screening · assigned to Compliance" and toast. `422 business_rule` (e.g. knock-out at Submitted → Declined) shows the decline with reason codes and the human-review route where applicable (P5).
**API.** `GET /applications/{id}/completeness`, `POST /applications/{id}/actions/submit`.

#### SCR-APP-04 · Case workspace: Summary
**Roles:** LO, OPS, CA, APR, CMP, LEG, DM, DC; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-APP-008, FR-APP-009, FR-CUS-009, LOS-FR-282, FR-WFL-001, FR-APP-004
**Purpose.** The case home. v3 screen 2 [F v3].
**Layout** [F v3]. **Context bar:** applicant name (16/600) + segment tag (info) · "LN-2026-04871 · Victoria Island Branch · Submitted 21 Aug 2026" · Amount / Product / Term / Stage / SLA · actions: Back to queue, Reassign, **primary action for the current stage** (e.g. "Open credit assessment"). **Tab row** (§5.4). **Left rail:** `StageTracker`, 8 workflow stages, square dot + connector, current bold, meta per stage ("In progress · Chidi Nwosu"). **Centre panels:** Requested terms (4-up `PropertyGrid`: amount vs cap, tenor vs max, rate + basis, repayment) · Facilities (when > 1, per-facility status, D-014) [R] · Applicant snapshot · Existing exposure "From CBA · Finacle adapter" tag with pull time and correlation ID [F v3] · Case completeness (progress bar + parts). **Right rail:** Requirements (open items with owner) · Notes preview · Properties.
**Data.** Application header, facilities, applicants, CBA exposure (cached ≤ 60 s for limit use, FR-CBA-019), completeness, SLA clocks, open tasks.
**Actions.** Stage primary action (permission-dependent) · Reassign (`task:reassign`) · Case actions menu → SCR-APP-11 (Hold, Return, Withdraw, Cancel).
**States.** CBA exposure unavailable: panel shows "Core banking not reachable · last good data 27 Aug 09:38" in warning tone, never zeros [F PR-08]. Terminal case: context bar shows the terminal chip and reason; actions removed. Auditor: read-only. Laptop: right rail → drawer. Tablet: stepper + tabs.
**API.** `GET /applications/{id}`, `/applications/{id}/completeness`, `/parties/{id}/customer-360`, `/tasks?filter[application]=…`, `/applications/{id}/notes`.

#### SCR-APP-05 · Case: Applicant and KYC
**Roles:** CMP, LO, CA, OPS; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-CUS-003, FR-CUS-005, FR-CUS-006, FR-CUS-007, FR-CUS-008, FR-CMP-010, FR-CMP-011, FR-APP-004, FR-CMP-036, FR-SEC-017
**Purpose.** Identity verification, screening results, CDD level and consents for every party (applicant, joint, guarantor, director, UBO). v3 "Applicant & KYC" tab [F v3].
**Layout.** Party switcher (chips per party with role and KYC state) → panels: Identity & verification (BVN/NIN masked, result, provider, timestamp, name/DOB match) · Screening results (lists, list version, hits, disposition, "Compliance sign-off required before disbursement · held by Ngozi Eke" [F v3]) · CDD level (Simplified / Standard / Enhanced, rule that set it; EDD items incl. source of funds/wealth in P5) · Beneficial ownership tree (non-individuals) · Consents (purpose, status, timestamp, withdraw). [R for the party switcher]
**Actions.** Verify identity (`POST /parties/{id}/identities/actions/verify`, `Idempotency-Key`) · Re-screen (`/parties/{id}/screenings`) · Open alert (→ SCR-CMP-02) · Reveal PII (§7.3, SU) · Record/withdraw consent.
**States.** Identity provider stub in MVP: result tagged "Simulated provider" in non-prod and "Stub adapter" in pilot [R; F phase_map FR-CUS-003 "port + stub adapter"]. CDD incomplete: warning banner "Progression blocked until CDD is complete (FR-CMP-010)" with the missing items. Screening hit: danger chip "Potential match · 2 hits" linking to the alert. Masked PII default; auditor sees masked unless `pii:unmask`.
**API.** `/parties/{id}`, `/parties/{id}/identities/actions/verify`, `/parties/{id}/screenings`, `/parties/{id}/consents`, `/parties/{id}/relationships`, `POST /pii/unmask`.

#### SCR-APP-06 · Case: Documents (checklist)
**Roles:** OPS, LO, CA, LEG; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-DOC-001, FR-DOC-002, FR-DOC-004, FR-DOC-005, FR-DOC-006, FR-DOC-007, FR-DOC-008, FR-DOC-009, NFR-016
**Purpose.** The per-application checklist derived from the product, with upload, status, expiry and waiver. v3 "Document checklist" [F v3].
**Layout** [F v3]. Header "6 required · 5 received · 1 flagged for review". `DataTable`: Document (+ file name and pages) · Status chip (FR-DOC-007 statuses) · Confidence ("Manual entry" in MVP; % in P2) · Received date · Expiry (with "Expires in 12 days" warning) · Action (Review / View / Request / Upload). Upload drop zone + "Upload files" button (SCR-DOC-03).
**Actions.**

| Action | Permission | SU | MC | Endpoint |
|---|---|---|---|---|
| Upload | `document:upload` | – | – | `POST /applications/{id}/documents` (resumable) |
| Review / verify / reject | `document:verify` | – | – | `POST /checklist-items/{id}/actions/{verify,reject}` |
| Request waiver | `document:waive_request` | – | MC (waiver approval) | `POST /checklist-items/{id}/actions/waive` → change request |
| Download | `document:view` | – | – | `GET /documents/{id}/content` (stream, logged) |

**States.** AV scan pending: row "Scanning…" (neutral) and the file cannot be opened [F FR-DOC-004]; scan failed: danger "Blocked: malware detected · file quarantined". Duplicate document detected: warning chip "Same file as LN-2026-04844 payslip" [F FR-DOC-006]. Expired: danger chip and "Request new". Waiver pending checker: maker-checker banner on the row. Empty (not yet derived): "Checklist appears when the product is selected."
**API.** `/applications/{id}/documents`, `/documents/{id}/versions`, `/documents/{id}/content`, `/checklist-items/{id}/actions/{verify,reject,waive}`.

#### SCR-APP-07 · Case: Collateral
**Roles:** LEG, CA, OPS; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-COL-001, FR-COL-002, FR-COL-003, FR-COL-004, FR-COL-005, FR-COL-006, FR-COL-008
**Purpose.** Capture collateral, valuation, perfection and insurance, and compute cover. v3 "Security & cover" panel [F v3].
**Layout.** Summary strip: security cover ratio, LTV, forced-sale value vs product thresholds (Pass/Fail chips). Collateral `DataTable` (type, description, owner, market value, haircut, FSV, allocated to facilities, perfection status, insurance status). Detail drawer per item: Valuation (valuer, date, method, amount, expiry, "Re-valuation due in 20 days") · Perfection steps (configurable list: search, stamping, registration, charge filing; each with responsible party, due date, status, evidence) · Insurance (policy, insurer, sum insured, expiry, bank interest noted) · Allocation (value split across facilities, over-allocation blocked). [R]
**Actions.** Add collateral · Add valuation · Update perfection step (evidence upload) · Record insurance · Allocate (`/allocations`; over-allocation → inline error) [F FR-COL-005].
**States.** Unmet perfection/insurance: warning "Blocks disbursement unless waived" [F FR-COL-008]. Not required for product: not-applicable empty state.
**API.** `/collaterals`, `/collaterals/{id}/valuations`, `/perfection-steps`, `/allocations`, `/insurance-policies`.

#### SCR-APP-08 · Case: Conditions
**Roles:** LEG, OPS, CA, LO; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-CPR-001, FR-CPR-002, FR-APV-009, FR-CPR-006
**Purpose.** Track conditions precedent and subsequent created by approval or policy, with owner, due date, evidence and sign-off. v3 "Conditions precedent & subsequent" [F v3].
**Layout** [F v3]. Table: Condition (+ code) · Type (CP/CS) · Owner · Due · Status chip · Evidence · Action. Filter CP / CS / Overdue. Header counts "2 CPs outstanding · disbursement blocked".
**Actions.** Submit evidence · Satisfy (`POST /facilities/{id}/conditions/…/actions/satisfy`; sign-off by a different user than the evidence submitter [R]) · Request waiver (MC) · Add condition (CA/APR only).
**States.** Any mandatory CP open: danger banner "Disbursement blocked: 2 mandatory conditions precedent outstanding" [F FR-CPR-002]. CS tracking past disbursement and handover is P3 (FR-CPR-006): until then CS rows show "Tracked after booking from P3" [R].
**API.** `/facilities/{id}/conditions` (+ `actions/{satisfy,waive}`).

#### SCR-APP-09 · Case: Notes and communications
**Roles:** LO, OPS, CA, APR, CMP, LEG, DM, DC; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-WFL-010, FR-NTF-005, FR-NTF-001
**Purpose.** Collaboration thread with internal and customer-visible notes and @mentions; the communication log of everything sent to the customer.
**Layout.** Two sub-tabs. Notes: composer (type toggle Internal / Customer-visible, with a warning-tone label on customer-visible), @mention autocomplete, thread list (author, role, time) [F v3 notes]. Communications: table (time, channel, template, recipient (masked), delivery status, view message). [R]
**Actions.** Post note (`POST /applications/{id}/notes`) · Mention user · Resend communication (P4).
**States.** Customer-visible notes need a confirm step "The customer will see this note" [R]. Delivery failed: danger chip with provider reason.
**API.** `/applications/{id}/notes`, `/applications/{id}/communications` [R: comm log endpoint not in §10.2].

#### SCR-APP-10 · Case: Audit
**Roles:** AUD, CMP, LO, OPS, CA, APR, LEG, DM, DC · **Phase:** MVP (P1) · **Reqs:** FR-AUD-001, FR-AUD-002, FR-AUD-005, FR-AUD-009, FR-APP-005
**Purpose.** The application's own immutable timeline: "Immutable · exportable · 214 events" [F v3].
**Layout.** `AuditTimeline` (§7.10) with filters; header actions "Reconstruct as at…" (→ SCR-AUD-02) and "Evidence pack" (→ SCR-AUD-03), both for users with audit permissions. [R]
**States.** Field-level change history for amendments shows before/after per field [F FR-APP-005].
**API.** `GET /applications/{id}/timeline`.

#### SCR-APP-11 · Case action dialogs
**Roles:** LO, OPS, CA, APR · **Phase:** MVP (P1) · **Reqs:** FR-WFL-005, FR-WFL-007, FR-WFL-008, FR-APP-006, LOS-FR-283
**Purpose.** Cross-cutting transitions available from every non-terminal state up to Ready for disbursement [F TRD §6.2].
**Layout.** One `Modal` per action, each with consequence text and the fields the API requires [R]:
- **Put on hold:** reason code (awaiting customer / awaiting third party / other), follow-up date, auto-expiry date; "The SLA clock pauses" [F FR-WFL-008].
- **Resume.**
- **Return for rework:** target stage (earlier stages only), reason code, one or more rework items with owner [F FR-WFL-007].
- **Withdraw** (customer request) / **Cancel** (bank): mandatory reason code + note; red danger button; "This ends the application. It can't be reopened."
- **Reassign:** user picker filtered to eligible users in scope, with workload count; reason.

**Actions.** `POST /applications/{id}/actions/{hold,resume,return,withdraw,cancel}`; `POST /tasks/{id}/actions/reassign`. All carry `Idempotency-Key` and `If-Match`.
**States.** Disbursing case: Withdraw/Cancel replaced by "Use disbursement reversal" (P3) [F TRD §6.2]. `409 state_conflict` if the case moved.
**API.** As above.

#### SCR-APP-12 · Bulk application upload
**Roles:** OPS · **Phase:** Later (P4) · **Reqs:** LOS-FR-312, FR-CHN-001
**Purpose.** Upload a canonical CSV/XLSX per product and get a row-level validation report [F LOS-FR-312].
**Layout.** Download template · upload · validation report table (row, field, error) · "Create 46 valid applications" · error file download. [R]
**API.** Not in §10.2 [R: `POST /applications/bulk-imports`].

### 8.6 Party

#### SCR-PTY-01 · Customer 360
**Roles:** LO, CA, CMP, OPS, APR, LEG; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-CUS-009, FR-CUS-002, FR-CUS-006, FR-CUS-001, FR-CMP-036, FR-CUS-010, FR-SEC-017
**Purpose.** Consolidated customer view: existing facilities, exposure, repayment history, deposits and prior applications, live from the CBA where the adapter supports it [F FR-CUS-009].
**Layout.** Header (name, type, CBA customer id (mono), segment, KYC state, risk rating) → tabs: Overview (exposure summary, facilities table with balance and DPD, deposits) · Applications (all, in scope) · Identity & KYC (as SCR-APP-05 for this party) · Relationships (directors, guarantees, connected parties; graph view P3) · Consents (SCR-PTY-02). "From CBA · <adapter>" tag with pull time on every CBA-sourced panel [F v3 pattern].
**States.** Capability not supported by the adapter manifest: panel "Repayment history isn't available from this core banking system" (not an error) [F FR-CBA-003/004]. Masked PII default.
**API.** `GET /parties/{id}`, `/parties/{id}/customer-360`, `/parties/{id}/relationships`, `/applications?filter[party]=…`.

#### SCR-PTY-02 · Consent record
**Roles:** LO, CMP; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-CUS-008, FR-CMP-021, FR-CMP-032
**Purpose.** Show and change granular consent per purpose with full history [F FR-CMP-032].
**Layout.** Table per purpose: status (Given / Withdrawn / Not asked), given on, channel, evidence (signed form or e-consent), withdrawn on; history drawer. [R]
**Actions.** Record consent (with evidence) · Withdraw (reason; warning "Bureau enquiries will be blocked for in-flight applications") [F FR-CMP-021].
**API.** `/parties/{id}/consents`.

### 8.7 Documents

#### SCR-DOC-01 · Document review side-peek
**Roles:** OPS, CA, LEG; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-DOC-007, FR-DOC-005, FR-DOC-009, FR-DOC-010, FR-DOC-024, FR-DOC-025, FR-DOC-026, FR-DOC-050, FR-DOC-053
**Purpose.** Verify a document against the application. v3 screen 3 [F v3]. **MVP is the manual-verification variant**: real extraction is out of the MVP (D-036), so the IDP port uses a manual adapter [F TRD §4 M07]. Extraction, confidence and region linking arrive in **P2**.
**Layout** [F v3]. Side-peek from the right (`shadow-drawer`, 1080 px). Header: file name, "1 of 1 · uploaded 24 Aug 2026", engine tag ("Manual verification" in MVP; "OCR + layout model v4.2" in P2), close. **Left:** rendered document on `bg-subtle` with `shadow-document`; toolbar Rotate · Zoom · Download (28 px buttons). **Right:** field list.
- **MVP:** canonical fields for the document type (FR-DOC-029 schema) entered or confirmed by the reviewer, each with the application value beside it and a match indicator; document-level actions Verify / Reject (reason) / Request new.
- **P2 adds** [F v3]: dashed regions on the document for every read field; click a region ↔ select a field (two-way link by field id); selected region = 2 px `accent-graphic` outline + `region-fill`; fields ordered below-threshold first; each field shows value, confidence chip (§7.8), source region ("Region 9 · p.1"), reason code, and Accept / Accept corrected value; cross-validation warning block (e.g. `DOC_NAME_MISMATCH`) with "Use application value" / "Keep document value"; bulk "Accept all above threshold".
**Data.** Document version (hash shown in mono on hover), type, pages, fields (canonical name, value, application value, confidence, region, reason code, accepted by/at, original machine value, corrected value) [F FR-DOC-026/053].
**Actions.**

| Action | Permission | SU | MC | Endpoint |
|---|---|---|---|---|
| Verify / reject document | `document:verify` | – | – | `POST /checklist-items/{id}/actions/{verify,reject}` |
| Accept field (P2) | `document:verify` | – | – | `POST /extractions/{id}/fields/{f}/actions/accept` |
| Correct field (P2) | `document:verify` | – | – | `POST /extractions/{id}/fields/{f}/actions/correct` |
| Accept all above threshold (P2) | `document:verify` | – | – | repeated `accept` (one request per field, or batch [R]) |
| Download | `document:view` | – | – | `GET /documents/{id}/content` |

**States.** Loading: document pane skeleton page + 8 field rows. Viewer can't render (format): "Preview not available · Download to view", hash still shown. Correction recorded: chip "Corrected · was ₦742,000 (58%)" [F FR-DOC-026]. Region click on keyboard: every region is also reachable as a list item; the image is never the only way to select a field (WCAG 2.1.1). Side-peek: `role="dialog"`, `aria-modal="false"` on desktop (the case stays readable behind it), focus moves into the peek on open, Esc closes and focus returns to the row's Review button. Tablet/mobile: full screen with Document / Fields tabs. Auditor: read-only, shows who accepted what.
**API.** `GET /documents/{id}/versions`, `GET /documents/{id}/content`, `/checklist-items/{id}/actions/*`, `/extractions/{id}/fields/{f}/actions/*`.

#### SCR-DOC-02 · Waiver request and approval
**Roles:** OPS, LEG, CA, APR · **Phase:** MVP (P1) · **Reqs:** FR-DOC-008, FR-SEC-007, FR-CRD-015, FR-AUD-016
**Purpose.** Waive a checklist document with mandatory justification, approved at the authority level configured for that document type [F FR-DOC-008]; maker-checker on waiver approval [F TRD §6.4].
**Layout.** Maker `Modal`: document, reason code, justification (required, min 20 characters), supporting evidence (optional), required authority ("Branch manager or above") [R]. Checker view in SCR-ADM-07 with the case context link.
**Actions.** Request waiver (`POST /checklist-items/{id}/actions/waive` → `202` change request) · Approve / reject in SCR-ADM-07 (`POST /change-requests/{id}/actions/{approve,reject}`, SU where configured).
**States.** Pending: maker-checker banner on the checklist row. Checker lacks authority for this document type: Approve disabled with "Requires Credit Approver tier 2 or above".
**API.** As above.

#### SCR-DOC-03 · Upload panel
**Roles:** OPS, LO, LEG · **Phase:** MVP (P1) · **Reqs:** FR-DOC-001, FR-DOC-002, FR-DOC-004, FR-DOC-005, NFR-016
**Purpose.** Resumable upload with type and size validation and malware scanning before availability.
**Layout.** Drop zone + "Choose files" button (drag is never the only way, WCAG 2.5.7); per-file row: name, size, checklist item (select; auto-classification in P2), progress bar, status (Uploading 46% · Paused · Scanning · Ready · Blocked), cancel/retry. Tenant limits stated: "PDF, JPG, PNG, TIFF, HEIC, DOCX · up to 20 MB". [R; formats F FR-DOC-002]
**States.** Interrupted: "Paused · will resume when connection returns" (tus). Wrong type / too large: inline at the row. Malware: blocked, quarantined, never previewable [F FR-DOC-004].
**API.** `POST /applications/{id}/documents` (multipart + resumable).

#### SCR-DOC-04 · Classification and extraction review queue
**Roles:** OPS · **Phase:** Later (P2) · **Reqs:** FR-DOC-020, FR-DOC-021, FR-DOC-025, FR-DOC-027
**Purpose.** Work queue for unclassifiable uploads, bundle splits to confirm, and fields below threshold (HITL) across applications [F].
**Layout.** Inbox view with three tabs (Unclassified · Split to confirm · Fields to verify); split confirmation shows page thumbnails with proposed type per page range. [R]
**API.** `/extractions/{id}/fields/{f}/actions/*`; classification endpoints [R].

#### SCR-DOC-05 · Bank statement analysis
**Roles:** CA · **Phase:** Later (P2) · **Reqs:** FR-DOC-040, FR-DOC-041, FR-DOC-042, FR-DOC-043, FR-DOC-044, FR-DOC-045, FR-DOC-046
**Purpose.** Normalised transaction ledger, categorisation, recurring salary detection, obligations, affordability metrics and tamper indicators, with drill-through from every metric to its transactions [F FR-DOC-046]. v3 "Statement analytics (8 rows)" [F v3].
**Layout.** Metric rows (average and median net income, regularity, existing debt service, bounced items, days in debit, DSR) each linking to a filtered transaction table; tamper panel (pass/flag per check with explanation). [R]
**API.** Statement endpoints [R].

### 8.8 Credit

#### SCR-CRD-01 · Credit assessment
**Roles:** CA; APR ◐, AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-CRD-001, FR-CRD-003, FR-CRD-004, FR-CRD-008, FR-CRD-010, FR-CRD-014, FR-CMP-020, FR-CMP-027, FR-CMP-023
**Purpose.** Run and review the automated decision: bureau, policy rules, affordability, risk grade. v3 screen 4 [F v3].
**Layout** [F v3]. **Risk grade** band ramp (5 bands, `score-1…5`, band labels printed under each segment; the current band carries a marker and the text "Grade C · band 3 of 5"). **Score drivers** (eight signed contributions) appear only when a scorecard is active: **P3** (FR-CRD-005) [F phase_map]. **Affordability table:** net income, existing debt service, proposed repayment, DSR "46.2% vs ≤ 40.0% → Fail", disposable income; each row shows its source ("Bank statement") and links to the source document/field [F v3]. **Bureau summary** (8 rows) with report date and validity. **Policy rules:** list of rule, code, Pass/Refer/Fail chip, explanation [F v3]. **Single-obligor check** (MVP) [F TRD §9.2]. Header: decision outcome chip (Approve / Refer / Decline / Counter-offer), rule-set version (mono), evaluator version, "Run 27 Aug 2026, 09:31".
**Actions.**

| Action | Permission | SU | MC | Endpoint |
|---|---|---|---|---|
| Pull bureau report | `bureau:pull` | – | – | `POST /applications/{id}/bureau-reports/actions/pull` (Idempotency-Key) |
| Run decision | `decision:run` | – | – | `POST /applications/{id}/decisions/actions/run` |
| View snapshot | `decision:view` | – | – | → SCR-CRD-03 |
| Go to memo & recommendation | `application:recommend` | – | – | → SCR-CRD-04 |

**States.** Bureau consent missing: "Bureau enquiry blocked: no valid bureau consent" with link to consent [F FR-CMP-021]. Bureau report expired: "Report older than 30 days; pull again before approval" [F FR-CMP-020; R for copy]. Bureau provider down: `integration_unavailable` retryable. Decision stale (inputs changed since last run): warning banner "Inputs changed after this decision (net pay corrected). Run again." [R]. Not yet run: empty state with "Run decision". Auditor/approver: read-only.
**API.** `/applications/{id}/bureau-reports/actions/pull`, `/applications/{id}/decisions/actions/run`, `GET /decisions/{id}`.

#### SCR-CRD-02 · Bureau report viewer
**Roles:** CA; APR ◐, AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-CRD-003, FR-CRD-004, FR-CMP-021
**Purpose.** The canonical credit profile from the bureau: facilities, balances, delinquency history, enquiries, judgments, guarantees [F FR-CRD-004].
**Layout.** Header (bureau, report reference (mono), pulled at, valid until, consent reference) → summary tiles → facilities table (lender, type, limit, balance, DPD history as a 12-month strip with text values) → enquiries → judgments → guarantees → "View original report" (PDF). [R]
**States.** Single bureau via stub in MVP; multiple bureaus with fallback order in P4 [F phase_map]. Fallback used: info banner "CRC unavailable; FirstCentral used per fallback order".
**API.** `GET /applications/{id}/bureau-reports` [R list], report content via `GET /documents/{id}/content` [R].

#### SCR-CRD-03 · Decision snapshot
**Roles:** CA, AUD, APR, CMP · **Phase:** MVP (P1) · **Reqs:** FR-CRD-010, FR-CRD-014, FR-CMP-043, FR-CRD-002
**Purpose.** Show exactly what a decision used and produced, and replay it with the evaluator version that made it [F TRD §7.2].
**Layout.** Summary (outcome, grade, recommended terms, ordered reason codes) · Inputs (fact table: canonical field, value, source, provenance) · Versions (rule set, scorecard, evaluator, pack, bureau report ref) · Trace (collapsible tree: decision flow → table → row hit → output) · Replay panel. [R]
**Actions.** Replay (`decision:replay`, `POST /decisions/{id}/actions/replay`) → result "Replay matches original" (success) or a diff (danger) [R].
**States.** Large traces: collapsed by default, lazy-loaded per node.
**API.** `GET /decisions/{id}`, `POST /decisions/{id}/actions/replay`.

#### SCR-CRD-04 · Credit memo and recommendation
**Roles:** CA; APR ◐, AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-CRD-013, FR-CRD-011, FR-CRD-015, FR-CRD-010, FR-APV-009
**Purpose.** Author the credit memo (templated sections pre-filled, analyst narrative), propose terms or a counter-offer, raise policy exceptions, propose conditions, and recommend.
**Layout.** Left: memo sections (auto sections read-only with "Refresh from application"; narrative sections rich text: headings, lists, bold only). Right: **Recommendation** panel: outcome (Approve / Approve with conditions / Counter-offer / Decline), terms (requested vs recommended with deltas, e.g. ₦25.0m → ₦21.5m, 48 → 36 months, 26.0% → 28.5%) [F v3 counter-offer], proposed conditions (CP/CS), **Exceptions** (each: rule, reason, evidence, severity; "Raising an exception escalates the required approval authority" [F TRD §7.3]). Footer: "Recommend" (primary). [R]
**Actions.** Save memo (`PATCH`, autosave) · Raise exception (`POST /exceptions`) · Recommend (`application:recommend`, `POST /applications/{id}/actions/recommend`, Idempotency-Key, If-Match).
**States.** Recommend disabled until: decision run and current, affordability evidenced [F FR-CMP-027], bureau in date [F FR-CMP-020]; each missing item is listed. Decline needs reason codes. Success: routes to approval; toast with first approver tier.
**API.** `/applications/{id}/credit-memo` [R], `/exceptions`, `/applications/{id}/actions/recommend`.

#### SCR-CRD-05 · Financial spreading
**Roles:** CA · **Phase:** Later (P3) · **Reqs:** FR-CRD-012
**Purpose.** SME/corporate financial statements (extracted or manual), ratios, trends and projections against templates [F; promoted to M by D-012].
**Layout.** Spreadsheet-like grid (years as columns, template lines as rows, right-aligned tabular numbers), ratio panel, trend sparklines with values printed. Keyboard grid navigation (`role="grid"`). [R]
**API.** Spreading endpoints [R].

#### SCR-CRD-06 · Connected exposure
**Roles:** CA, APR · **Phase:** Later (P3) · **Reqs:** FR-CRD-007, FR-CUS-010, FR-CMP-024
**Purpose.** Aggregate customer and connected-group exposure across CBA facilities and in-flight applications against single-obligor and portfolio limits; flag insider/related parties. v3 approval packet "Connected-party exposure" [F v3].
**Layout.** Group list (party, relationship, existing exposure, in-flight, total) with total vs limit bar (value printed); insider flag chip "Insider · director" (danger) routing to the elevated path. [R]
**API.** `/parties/{id}/relationships`, `/parties/{id}/customer-360`.

### 8.9 Approvals

#### SCR-APV-01 · Decision packet
**Roles:** APR; CA ◐, LO ◐, AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-APV-010, FR-APV-005, FR-APV-007, FR-APV-008, FR-APV-009, FR-APV-012, FR-APV-002, FR-APV-006, FR-CRD-015
**Purpose.** Everything an approver needs in one read-only packet, plus the routing chain. v3 screen 5 [F v3]. Also the case "Approvals" tab for non-approvers (chain and history only).
**Layout** [F v3]. **Authority banner** (when relevant): "Requested ₦25,000,000 against your approval limit of ₦20,000,000. You may recommend; final sanction routes to Board Credit Committee (tier 4)." in danger-strong text on danger wash [F v3]. **Sections:** Requested vs recommended terms with deltas · Credit analysis summary + memo link · Bureau summary · Risk grade · Exposure (customer; connected from P3) · Collateral with realisable values and perfection status · **Policy exceptions requiring approval** (each with analyst justification) · Document status list · Conditions proposed · **Approval chain** (tiers with actor, role, action, time, note; SLA breach flagged) · prior decision history. Sticky action bar (bottom on tablet): **Approve** · Approve with conditions · Counter-offer · Decline (danger outline) · Send back. Authority line (§7.14).
**Actions.**

| Action | Permission | SU | MC | Endpoint |
|---|---|---|---|---|
| Vote (any outcome) | `application:approve` within scope and limit | SU above threshold [F FR-SEC-013] | – (the vote is the control; SoD enforced) | `POST /approval-requests/{id}/actions/vote` |
| Send back to analyst | `application:approve` | – | – | vote with outcome `refer_back` [R] |
| Escalate | automatic (FR-APV-007) | – | – | — |

**States.** SoD-blocked (originator, recommender, lower-level approver) (§7.5) [F FR-APV-005]. Authority insufficient: Approve hidden behind "Recommend to next tier" [F v3 copy]. Approval expired: "Approval validity lapsed on 12 Sep 2026; re-approval required" [F FR-APV-012]. Bureau out of date: Approve disabled [F FR-CMP-020]. Already decided by a parallel approver: `409` → chain refreshes. Committee tier in MVP: see §12 (C-09). Read-only for CA, LO, AUD.
**API.** `GET /applications/{id}/approval-requests`, `GET /decisions/{id}`, `POST /approval-requests/{id}/actions/vote`.

#### SCR-APV-02 · Vote dialog
**Roles:** APR · **Phase:** MVP (P1) · **Reqs:** FR-APV-008, FR-APV-009, FR-CRD-011, FR-SEC-013
**Purpose.** Record the decision with mandatory rationale, reason codes, conditions or counter-offer terms.
**Layout.** `Modal` lg: outcome (segmented: Approve · Approve with conditions · Counter-offer · Decline); rationale (required textarea); reason codes (required for decline and conditional; searchable multi-select, ordered); conditions editor (type CP/CS, description, owner role, due offset) for conditional; counter-offer terms (amount, tenor, rate, extra security) with deltas; authority basis line (read-only: "Limit ₦50,000,000 · matrix v8 row 6"); "Submit decision" (+ shield icon when step-up applies). [R]
**States.** Validation inline + summary. After step-up, the vote is sent with the same idempotency key. Success: dialog closes, chain updates, toast "Approved · routed to Offer". Error `409` (someone else decided / case moved).
**API.** `POST /approval-requests/{id}/actions/vote`, `POST /auth/step-up`.

#### SCR-APV-03 · Committee meeting and e-voting
**Roles:** APR · **Phase:** Later (P3) · **Reqs:** FR-APV-004, FR-APV-003
**Purpose.** Agenda assembly, packet distribution, individual votes with rationale, abstention, quorum and minuted outcome [F FR-APV-004].
**Layout.** Meeting list → meeting page: agenda (applications with amount and recommendation), attendance and quorum meter ("4 of 5 present · quorum 3"), per item: packet link, members' votes (hidden until the member votes or the chair reveals, per tenant rule [R]), minute editor, outcome. [R]
**API.** Committee endpoints [R], `/approval-requests/{id}/actions/vote`.

#### SCR-APV-04 · Mobile approval
**Roles:** APR · **Phase:** Later (P3) · **Reqs:** FR-APV-011, FR-SEC-013
**Purpose.** Approve on a phone with the same controls and step-up [F FR-APV-011, D-010].
**Layout.** < 768: single column: header (applicant, amount, tier, SLA) → collapsible packet sections (terms, exceptions, risk, collateral, conditions) → sticky bottom bar (Decline · Approve…) opening SCR-APV-02 as a full-screen sheet. 44 px targets. [R]
**API.** As SCR-APV-01/02.

### 8.10 Offer and execution

#### SCR-OFR-01 · Offer preparation and issue
**Roles:** LO, OPS; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-OFR-001, FR-OFR-002, FR-OFR-003, FR-OFR-004, FR-OFR-007, FR-CMP-025, FR-CRD-011
**Purpose.** Generate the offer letter and facility agreement from templates, with the key-facts statement and the full repayment schedule, then issue it with a validity period.
**Layout.** Left: approved terms (read-only; counter-offer terms where applicable), template (with version), delivery channels (email + printable in MVP; SMS link/in-app in P4), validity (days, expiry date). Right: PDF preview with page thumbnails; **Key facts** panel (total cost of credit, effective rate, all fees) [F FR-CMP-025]; **Repayment schedule** table (no., due date, principal, interest, fees, total, balance; totals row). [R]
**Actions.** Generate preview · Issue (`offer:issue`, `POST /facilities/{id}/offers` then `actions/issue`, Idempotency-Key) · Re-issue (after expiry, per rules) [F FR-OFR-007].
**States.** Template merge error: inline list of missing merge fields. Schedule source: "Schedule computed by LOS; CBA schedule will be compared at booking" (variance check, LOS-FR-313) [F TRD §11]. Expired: chip "Expired · offer validity" + Re-issue.
**API.** `/facilities/{id}/offers` (+ `actions/{issue,reissue}`), `/offers/{id}/document`.

#### SCR-OFR-02 · Acceptance and execution
**Roles:** OPS, LEG, LO; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-OFR-005, FR-OFR-006, FR-OFR-008, FR-OFR-009, FR-OFR-010, LOS-FR-314, FR-OFR-007
**Purpose.** Capture acceptance by e-signature or wet signature, in the configured signing order for every party; or record rejection/negotiation.
**Layout.** **Signing order** list (joint applicants, guarantors, witnesses, bank signatories; each: party, role, method (e-sign / wet-sign per template, LOS-FR-314), status, signed at). Per document: method chip; e-sign shows provider status and certificate link (MVP: stub provider); wet-sign shows "Upload executed copy" + verification checklist (all pages, signatures, witness, date) [R]. Footer: "Record acceptance" (enabled when all required signatures are verified) · "Customer rejected / wants changes". Executed agreement: hash (mono) + template version [F FR-OFR-010].
**Actions.** Send for e-sign (`POST /offers/{id}/signatures`) · Upload wet-signed copy (document upload) · Verify signature (OPS/LEG) · Accept (`POST /facilities/{id}/offers/{oid}/actions/accept`) · Reject / negotiate (`actions/reject`, reason code; routes back to Assessment) [F G-14d].
**States.** Instrument requires wet signature (e.g. legal mortgage): e-sign option absent with "Must be wet-signed under the regulatory pack default" [F G-26]. Offer expired during signing: blocked, Re-issue. Out-of-order signature: blocked with the required order.
**API.** `/offers/{id}/signatures`, `/facilities/{id}/offers` (+ `actions/{accept,reject}`), `/applications/{id}/documents`.

### 8.11 Conditions, disbursement and booking

#### SCR-CPR-01 · Pre-disbursement check
**Roles:** DM, DC, OPS; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-CPR-003, FR-CPR-004, FR-CPR-005, FR-CPR-002, FR-COL-008, FR-APV-012
**Purpose.** The final checklist before an instruction can be made. v3 "Pre-disbursement checklist (8 checks, hard-block vs soft-check; insurance lapse = Fail)" [F v3].
**Layout** [F v3]. Checklist: KYC current · Screening clear (re-run now) · Documentation executed · Security perfected · Insurance in force · Account verified (name enquiry: name match score, account active) · Approval unexpired · Mandatory CPs met. Each: Pass/Fail chip, "Hard block" or "Soft check" tag, evidence and run time, action (Re-run / Open / Request waiver). Summary: "2 hard blocks · disbursement not allowed".
**Actions.** Run checks (`POST /facilities/{id}/pre-disbursement-check`, Idempotency-Key) · Re-screen (blocks on hit, FR-CPR-004) · Name enquiry (via simulator in MVP) · Request waiver for soft checks or waivable CPs (MC).
**States.** Screening hit at re-screen: danger "Blocked: potential sanctions match. Compliance alert ALR-2026-0091 opened" [F FR-CPR-004]. Name mismatch: warning with both names shown (masked account) and score. All pass: success banner "Ready to disburse" + "Prepare instruction".
**API.** `GET/POST /facilities/{id}/pre-disbursement-check`, `/parties/{id}/screenings`.

#### SCR-DSB-01 · Disbursement instruction (maker)
**Roles:** DM; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-DSB-001, FR-DSB-002, FR-DSB-004, FR-DSB-009, FR-DSB-011, FR-CBA-012
**Purpose.** Prepare the instruction: amount, destination, fee/charge/insurance deductions, net to customer. v3 "Disbursement instruction breakdown" [F v3].
**Layout** [F v3]. Breakdown table: approved amount · four fee deductions (each named, basis, amount, `−₦` format) · total deductions · **Net to customer ₦9,382,250.00** (18 px/600). Destination account (masked, name, bank, name-enquiry result). Mode: Full single (MVP); tranche, revolving activation and third-party payment later [F phase_map FR-DSB-002 COMPLETES P3]. CBA mapping summary (product code, GL, branch) from the adapter binding. "Submit for release" (primary).
**Actions.** Create instruction (`disbursement:make`, `POST /facilities/{id}/disbursements`, Idempotency-Key) → creates the release change request (MC) [F TRD §6.4].
**States.** Pre-check not passed: page shows the blocking checks instead of the form. Pending release: maker-checker banner "Awaiting disbursement checker" with the CR id; fields locked; maker can withdraw.
**API.** `POST /facilities/{id}/disbursements`, `GET /disbursements/{id}`.

#### SCR-DSB-02 · Release review (checker)
**Roles:** DC · **Phase:** MVP (P1) · **Reqs:** FR-DSB-009, FR-SEC-007, FR-SEC-013, FR-APV-012, FR-DSB-005
**Purpose.** Second person checks and releases the instruction to the core.
**Layout.** Instruction summary (as SCR-DSB-01, read-only) · Pre-disbursement check results with time · Approval validity · Maker identity and time · "Release to core banking" (primary, shield icon) · "Reject" (reason). [R]
**Actions.** Release (`disbursement:check`, `POST /disbursements/{id}/actions/release`, SU, Idempotency-Key) · Reject (`POST /change-requests/{id}/actions/reject`).
**States.** SoD: checker = maker or (policy) = approver → §7.5 copy [F FR-DSB-009]. Approval expired since maker: `409` → "Approval validity lapsed; release blocked" [F TRD §6.3 step 5]. Stale change request → `change_request_stale`. Success → SCR-DSB-03 in "Posting to core".
**API.** `GET /disbursements/{id}`, `POST /disbursements/{id}/actions/release`, `/change-requests/{id}/actions/reject`.

#### SCR-DSB-03 · Posting status and exception handling
**Roles:** DM, DC, OPS; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-DSB-005, FR-DSB-006, FR-DSB-007, FR-CBA-007, FR-CBA-009, FR-CBA-011, FR-CBA-016, LOS-FR-313
**Purpose.** Show the core-banking posting saga and recover safely from failure. v3 "Core-banking posting panel" [F v3].
**Layout** [F v3]. Full-width banner by state (failed: danger bg + danger border; sending: accent-subtle + accent-border; acknowledged: success bg). Panel (`bg-danger-wash` when failed, `bg-success-wash` when acknowledged): headline ("Rejected by core · CBA-ERR-4412"), explanation, property list (Adapter "Finacle 11.x · REST v2", Environment, Instruction status, **Idempotency reference** `IDMP-DI-01188-A7F3`, CBA correlation ID, Attempts "2 of 5"), **attempt log** (newest first). Saga steps list [R]: Ensure customer → Create loan account → Disburse → Post fees → Retrieve schedule + variance check (each: status, time, compensation available). Sibling posting queue table [F v3].
**Actions.**

| Action | Permission | SU | MC | Endpoint |
|---|---|---|---|---|
| Retry posting (failed-retryable) | `disbursement:retry` [R] | – [R] | – [R] | `POST /disbursements/{id}/actions/retry` (new HTTP key per retry intent; the server chooses the CBA key, §7.9) |
| Open exception (failed-intervention) | `disbursement:view` | – | – | → SCR-DSB-04 |
| Reverse (P3) | `disbursement:reverse` | SU | MC | `POST /disbursements/{id}/actions/reverse` |

**States.** All of §7.9: pending (`pending_cba`), outcome unknown, failed-retryable, failed-intervention, compensating, acknowledged (loan account number in mono, "Case advances to handover"). **Schedule variance** beyond tolerance: booking blocked as an exception "CBA schedule differs from accepted offer: instalment ₦412,880.00 vs ₦412,860.00 (tolerance ₦10.00)" [F LOS-FR-313]. Licence expiry never interrupts this screen [F D-034].
**API.** `GET /disbursements/{id}`, `/disbursements/{id}/actions/retry`, `/integration-calls?filter[correlation]=…`.

#### SCR-DSB-04 · Disbursement exception queue
**Roles:** DM, DC, OPS, ADM; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-DSB-007, FR-CBA-011, LOS-CON-008, FR-CBA-010
**Purpose.** Every posting that needs a person, with the canonical error class and next step (PR-08).
**Layout.** `DataTable`: application ref · instruction · error class chip (retryable / requires intervention / business rejection) · native code · age · owner · next action. Detail opens SCR-DSB-03. [R]
**Actions.** Assign owner · Retry (retryable only) · Mark resolved after manual posting (requires evidence: CBA reference + note; MC [R]) · Start compensation (P3).
**States.** Circuit open on the adapter: banner "Finacle adapter circuit is open since 09:10. New postings are queued." [F FR-CBA-010].
**API.** `GET /disbursement-exceptions`, `/disbursements/{id}/actions/*`.

#### SCR-DSB-05 · Reconciliation
**Roles:** DM, DC, OPS, ADM; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-DSB-008, FR-CBA-017
**Purpose.** Daily LOS-vs-CBA reconciliation runs and their breaks [F].
**Layout.** Runs table (date, period, records compared, breaks, status) → run detail: breaks table (type: missing in CBA / missing in LOS / amount mismatch / status mismatch; LOS value; CBA value; age; owner; resolution). [R]
**Actions.** Run now (`POST /reconciliation-runs`) · Assign · Resolve break (reason + evidence).
**States.** No breaks: success "Reconciled · 214 postings, 0 breaks". Run failed (adapter): `integration_unavailable`.
**API.** `/reconciliation-runs`, `/reconciliation-breaks`.

#### SCR-DSB-06 · Booked and handover
**Roles:** LO, DM, OPS; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-HND-001, FR-HND-003, FR-HND-004, FR-DSB-011
**Purpose.** Confirm booking, the facility-created event, the disbursement advice and final schedule sent to the customer, and the handover of open items to servicing.
**Layout.** Success header "Booked · loan account 8801-4471-02" · Facility-created event (time, consumers, delivery status) · Customer documents sent (advice, schedule; channel, status) · Handover list (open CS, perfection items, insurance expiries; owner in servicing; due) · Retention line "Origination record retained until 2033 (policy)". [R]
**API.** `GET /applications/{id}`, `/applications/{id}/communications` [R], `/facilities/{id}/conditions`.

#### SCR-DSB-07 · Disbursement reversal
**Roles:** DM, DC · **Phase:** Later (P3) · **Reqs:** FR-DSB-012, FR-CBA-009
**Purpose.** Cancel or reverse a disbursement within the configured window with a compensating instruction [F]. Outcome "Reversed" on the facility; application stays Booked with a reversal event [F G-14i, pending D-013 confirmation].
**Layout.** Window countdown ("Reversal window closes 28 Aug 2026, 17:00"), reason code, compensating steps preview, maker → checker. [R]
**API.** `POST /disbursements/{id}/actions/reverse`.

#### SCR-DSB-08 · Tranche schedule
**Roles:** DM, DC, CA · **Phase:** Later (P3) · **Reqs:** FR-DSB-003, FR-DSB-002
**Purpose.** Per-tranche conditions, approval and independent release on a Booked facility [F G-14c].
**Layout.** Tranche table (no., amount, conditions, approval, status, released on) with each tranche opening its own CPR-01 / DSB-01..03 sequence. [R]
**API.** `/facilities/{id}/disbursements`.

### 8.12 Compliance

#### SCR-CMP-01 · Screening alert queue
**Roles:** CMP; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-CMP-013, FR-CMP-011, FR-CUS-005, FR-CMP-014
**Purpose.** All open screening alerts with match scores, owner, stage and SLA [F FR-CMP-013].
**Layout.** `FilterBar` (status, list type: sanctions / PEP / adverse media / internal, checkpoint: intake / approval / pre-disbursement, score band, assignee) → `DataTable`: alert id (mono) · party (name, role, application ref) · list + entry · score (number + bar, value printed) · checkpoint · status chip · first reviewer · age/SLA · "Review →". [R]
**Actions.** Claim · Assign · Open.
**States.** Alerts on applications the user originated are listed but their actions carry the SoD notice [F FR-CMP-014]. Empty: "No open alerts."
**API.** `GET /screening-alerts`.

#### SCR-CMP-02 · Alert review and disposition (four-eyes)
**Roles:** CMP; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-CMP-013, FR-CMP-014, FR-CMP-017, FR-CPR-004
**Purpose.** Compare the party with the list entry, record a disposition with reason, and clear true matches only with a second reviewer [F FR-CMP-013].
**Layout.** Two-column comparison: party data (name, DOB masked, nationality, ID numbers masked, addresses) vs list entry (names and aliases, DOB, nationality, programme, source, list version and date); matched attributes highlighted with a text marker ("match", "partial", "no match"), not colour alone. Evidence panel: query, response, list version [F FR-CMP-017]. Disposition panel: **first review** (False positive / Potential true match / Escalate; reason code; note) → **second review** (Confirm / Return; reason). Timeline of the alert. [R]
**Actions.**

| Action | Permission | SU | MC | Endpoint |
|---|---|---|---|---|
| First disposition | `screening:review` | – | – | `POST /screening-alerts/{id}/dispositions` |
| Second review (clear / confirm match) | `screening:clear` | – | four-eyes: reviewer ≠ first reviewer | `POST /screening-alerts/{id}/dispositions` |
| Escalate | `screening:review` | – | – | same, outcome `escalate` |
| Reveal PII | `pii:unmask` | SU | – | `POST /pii/unmask` |

**States.** SoD: originator cannot dispose [F FR-CMP-014]; first reviewer cannot be the second (§7.5). Confirmed true match: application blocked; banner on the case "Blocked by confirmed sanctions match" (danger). Masked PII default.
**API.** `GET /screening-alerts/{id}`, `POST /screening-alerts/{id}/dispositions`.

#### SCR-CMP-03 · Regulatory packs and rule register
**Roles:** CMP, ADM; PM ◐, AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-CMP-001, FR-CMP-003, LOS-FR-310
**Purpose.** See the active jurisdiction pack (Nigeria × commercial bank), its versions and the effective-dated rule register with citations; activate a new pack version under maker-checker.
**Layout.** Pack header (jurisdiction, licence category, version, effective from, signature status) → versions table → rule register (rule, citation, effective from/to, **verification status** chip: Verified / Unverified) [F TRD §9.4]. [R]
**Actions.** Activate pack version (`pack:activate`, SU, MC) · View rule history.
**States.** Activation blocked while any rule is unverified: "3 rules are not verified against a primary source. The pack can't be activated." [F TRD §9.4].
**API.** `GET /jurisdiction-packs`, `/config-artifacts/jurisdiction_pack/{id}/versions` (+ actions) [R type mapping].

#### SCR-CMP-04 · Data subject requests
**Roles:** CMP · **Phase:** Later (P5) · **Reqs:** FR-CMP-033
**Purpose.** Access, rectification, erasure, portability and restriction requests, with statutory override reasons [F].
**Layout.** Request list (type, subject (masked), received, due, status) → detail with tasks per system and override reasons; fulfilment export. Never blocked by licence state [F D-034]. [R]
**API.** `/dsr-requests`.

#### SCR-CMP-05 · Retention policies and legal holds
**Roles:** CMP, ADM · **Phase:** Later (P5) · **Reqs:** FR-CMP-034, FR-AUD-012
**Purpose.** Retention schedules per data category (trigger, period, action) and legal holds that suspend purge [F TRD §5.5].
**Layout.** Policies table (MC on change) and holds table (scope, reason, placed by, until). [R]
**API.** `/retention-policies`, `/legal-holds`.

#### SCR-CMP-06 · Model inventory
**Roles:** CMP, PM · **Phase:** Later (P3) · **Reqs:** FR-CMP-040, FR-CMP-041, FR-CMP-042
**Purpose.** Scorecards, extraction and classification models with owner, version, validation date; activation needs a validation record and maker-checker [F].
**Layout.** Inventory table + model detail (versions, validation records, drift metrics P3). [R]
**API.** [R] `/models`.

#### SCR-CMP-07 · Insider and related-party register
**Roles:** CMP · **Phase:** Later (P3) · **Reqs:** LOS-FR-306, FR-CMP-024
**Purpose.** Maintain the insider register under maker-checker or from HR/CBA feeds [F LOS-FR-306].
**Layout.** Register table (person, relationship, source, effective dates) + import from feed. [R]
**API.** [R] `/insider-register`.

#### SCR-CMP-08 · PII inventory and processor register
**Roles:** CMP · **Phase:** Later (P5) · **Reqs:** FR-CMP-031, FR-CMP-037, FR-CMP-035
**Purpose.** Generated PII field register (lawful basis, purpose, retention) and third-party processor register with processing location [F].
**Layout.** Two tables; residency breaches flagged in danger tone with the adapter binding link. [R]
**API.** [R] `/pii-register`, `/processors`.

### 8.13 Audit and assurance

#### SCR-AUD-01 · Audit explorer
**Roles:** AUD, CMP; ADM ◐ · **Phase:** MVP (P1) · **Reqs:** FR-AUD-009, FR-AUD-001, FR-AUD-002, FR-AUD-007, FR-AUD-008, FR-AUD-005
**Purpose.** Search and filter the audit trail by application, user, date range, action type and entity, without operational permissions [F FR-AUD-009].
**Layout.** `FilterBar` (application ref, actor, role, action type, entity type, date-time range in WAT, correlation ID) → results table (seq, time, actor (+ role snapshot, on-behalf-of), action, entity, reason, correlation ID) → row opens a side panel with the full event (before/after masked diff, IP, device, step-up ref, CR id, hash, prev hash). Export (CSV, logged). [R]
**States.** Large result sets: cursor pagination, count "≥ 10,000 events; narrow the filter". Read-only by nature. Licence state never blocks this screen [F D-034].
**API.** `GET /audit-events`.

#### SCR-AUD-02 · Application reconstruction (as at)
**Roles:** AUD, CMP · **Phase:** MVP (P1) · **Reqs:** FR-AUD-011, FR-CRD-014, FR-AUD-010
**Purpose.** Show the exact state of an application at any past moment [F FR-AUD-011].
**Layout.** Header with application ref and an **as-at control**: date-time input (WAT) + timeline scrubber of event markers (each marker a focusable button with its event label) + "Step to previous / next event". Body: the Summary, KYC, documents (versions valid at T), decision (snapshot valid at T), approvals and conditions **as they were**, each panel labelled "As at 26 Aug 2026, 17:52:10 WAT". A persistent info banner: "Historical view. Nothing here can be changed." Side panel: events up to T. [R]
**Actions.** Set time · Step events · Compare with now (diff) · Export evidence pack (→ SCR-AUD-03).
**States.** Time before creation: "The application did not exist at this time." Loading per panel (replay can be slow): panel skeletons with "Rebuilding state…".
**API.** `GET /applications/{id}/as-at?t=…`, `GET /applications/{id}/timeline`, `GET /decisions/{id}`.

#### SCR-AUD-03 · Evidence pack export
**Roles:** AUD, CMP · **Phase:** MVP (P1) · **Reqs:** FR-AUD-010, FR-RPT-008
**Purpose.** Build and download the signed evidence pack for an application [F FR-AUD-010, TRD §5.4].
**Layout.** Request form (application ref; contents preview: timeline, N documents with hashes, decisions, approvals with authority basis, screening evidence, communications; purpose code) → jobs table (requested by, at, status Queued / Building 40% / Ready / Failed, size, manifest SHA-256 (mono, truncated), download, expires). Verification help: "Verify the manifest signature with the installation public key" + copyable key fingerprint. [R]
**Actions.** Request (`POST /applications/{id}/evidence-pack`, Idempotency-Key) · Download (logged) · Retry failed.
**States.** Building: progress announced politely at 25 % steps. Ready: toast + item highlighted. Never blocked by licence state [F D-034].
**API.** `POST/GET /applications/{id}/evidence-pack`.

#### SCR-AUD-04 · Overrides, waivers and exceptions report
**Roles:** AUD, CMP · **Phase:** MVP (P1) · **Reqs:** FR-AUD-016, FR-CRD-015
**Purpose.** All overrides, waivers, exceptions and manual interventions over a period, by user and type [F].
**Layout.** Period + type + user filters → summary by type and by user (counts) → detail table (date, application, type, rule/document, reason, requested by, approved by, authority). Export. [R]
**API.** `GET /reports/overrides`, `GET /exceptions`.

#### SCR-AUD-05 · Audit integrity
**Roles:** AUD, ADM · **Phase:** MVP (P1) · **Reqs:** FR-AUD-004, FR-AUD-003, FR-AUD-013
**Purpose.** Show hash-chain verification results and checkpoints; run a verification for a range [F TRD §5.4].
**Layout.** Last nightly run (range, events verified, result chip "Chain intact" / "Break detected at seq 1,204,331"), checkpoints table (time, head hash, anchored to WORM/SIEM), "Verify range". [R]
**States.** Break: danger banner with the first broken sequence and "P1 alert raised to security". WORM anchoring is P6 [F phase_map FR-AUD-013]; until then checkpoints say "Stored in object store (Object Lock pending P6)".
**API.** `POST /audit/verify`, `GET /audit-events`.

#### SCR-AUD-06 · PII access log
**Roles:** AUD, CMP · **Phase:** MVP (P1) · **Reqs:** FR-AUD-006, FR-CMP-036
**Purpose.** Who read or revealed which PII, when and for what purpose.
**Layout.** Filters (subject party, user, purpose, date) → table (time, user, role, subject (masked), fields, action: read / reveal / copy, purpose, application). [R]
**API.** `GET /audit-events?filter[action]=pii.*` [R].

### 8.14 Reporting

#### SCR-RPT-01 · Operational dashboard
**Roles:** OPS, CA, APR, CMP, DM, PM, ADM; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-RPT-001, FR-RPT-010
**Purpose.** Role-scoped pipeline by stage, TAT by stage, SLA breaches, queue volumes and ageing [F FR-RPT-001].
**Layout.** Stat tiles (in flight, breached, average TAT, approvals this week) → bar chart "Applications by stage" (single series `chart-1`, values printed on bars) → "Median TAT by stage (business hours)" bar chart → ageing buckets table → breaches table. Every chart has a "View as table" toggle and a text summary [R]. Scope line: "Showing Victoria Island, Ikeja, Lekki branches".
**States.** Empty data: "No applications in this period." Charts never rely on colour: single-series bars, labels printed.
**API.** `GET /dashboards/operational` [R key].

#### SCR-RPT-02 · Reports and exports
**Roles:** OPS, CMP, PM, ADM; AUD ◐ · **Phase:** Later (P5) · **Reqs:** FR-RPT-002, FR-RPT-003, FR-RPT-004, FR-RPT-005, FR-RPT-006, FR-RPT-007, FR-RPT-008, FR-RPT-009
**Purpose.** Management, risk, productivity, funnel and regulatory reports; scheduled delivery; CSV/XLSX/PDF exports with an export log [F].
**Layout.** Catalogue → report page (parameters, run, results table/chart, schedule) → runs and exports log. [R]
**API.** `/reports`, `/reports/{id}/runs`, `/exports`.

### 8.15 Configuration

#### SCR-PRD-01 · Product catalogue
**Roles:** PM; ADM ◐, AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-PRD-001, FR-PRD-002, FR-PRD-006, FR-CFG-001
**Purpose.** List products with their live and draft versions.
**Layout.** `DataTable`: product (name, code mono), category, live version + since, draft version + state (Draft / In review / Approved / Scheduled), in-flight applications on old versions, owner. "New product". [R]
**Actions.** New product (`product:create`) · Open version editor · Clone version.
**API.** `GET /products`, `POST /products`.

#### SCR-PRD-02 · Product version editor
**Roles:** PM; ADM ◐, AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-PRD-003, FR-PRD-004, FR-PRD-005, FR-PRD-006, FR-PRD-009, FR-CFG-002, FR-TEN-009, FR-SEC-007, LOS-FR-284
**Purpose.** Edit a draft version: terms, pricing and fees, document checklist, bindings, CBA mapping; submit for approval and schedule activation. v3 screen 7 "Products & rules" [F v3: panels with item, code, condition/basis and a draft-vs-live column where amber text marks staged changes].
**Layout.** Version header (v8 Draft · based on v7 live · effective from) + lifecycle stepper (Draft → In review → Approved → Active). Sections (left nav): Terms (amount and tenor ranges, interest basis, repayment frequency, moratorium, prepayment, penalties) · Fees & charges (upfront, amortised, contingent; basis; VAT flag [verify G-36]) · Document checklist (conditional rules by segment, amount band, channel) · Eligibility (link to rule set) · Bindings (workflow version, rule-set version, approval-matrix version) · CBA mapping (product code, GL, branch mapping; read from the adapter binding, edited there) · Availability (P3). Each table has a **Draft vs live** column [F v3]: changed values in warning-fg text **plus** a "Changed" text tag; "New in v8"/"Removed" tags [R adds the text tags]. Validation panel (FR-CFG-002): errors block submission; warnings don't. [R for section list]
**Actions.**

| Action | Permission | SU | MC | Endpoint |
|---|---|---|---|---|
| Save draft | `product:edit` | – | – | `PATCH /products/{id}/versions/{v}` |
| Submit for review | `product:edit` | – | – | `/products/{id}/versions/{v}` lifecycle `submit` |
| Approve and schedule activation (checker) | `product:approve` | SU | MC: this is the check; checker ≠ maker | lifecycle `approve`; activation at the effective date by `system:scheduler`, or `activate` immediately when the date is today [R, §12 C-10] |
| Simulate (P3) | `product:simulate` | – | – | `POST /products/{id}/versions/{v}/simulate` |

**States.** Live version: read-only, "Create draft from v7". Pending checker: banner (§7.4). Invalid configuration: activation disabled with the error list [F FR-CFG-002]. In-flight note: "38 applications stay on v7" [F FR-PRD-006].
**API.** `/products`, `/products/{id}/versions` (+ lifecycle actions), `/adapter-bindings`.

#### SCR-PRD-03 · Rules editor
**Roles:** PM, CMP; CA ◐, AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-CRD-001, FR-CRD-002, FR-TEN-009, FR-CFG-002
**Purpose.** Author eligibility, policy and knock-out rules as decision tables and expressions, without code, version-controlled [F FR-CRD-001; usable by a credit-risk analyst, G-11].
**Layout.** Rule-set list → rule-set version page: decision flow outline (knock-out → policy → affordability → pricing → outcome) [F TRD §7.1] · **decision table editor**: columns = input expressions (fact picker with autocomplete from the fact schema), rows = conditions, outputs (outcome, reason code); hit policy select (UNIQUE / FIRST / PRIORITY / COLLECT); row reorder with **Move up / Move down buttons** (no drag-only, WCAG 2.5.7) · expression rule editor with inline validation · formula editor with named intermediates · test panel: pick a stored application snapshot, run, see the trace. Draft vs live column as SCR-PRD-02. [R]
**Actions.** Save draft · Validate · Test against snapshot (MVP) · Simulate population (P3) · Submit · Approve (MC) · Activate (SU, MC).
**States.** Expression error: inline under the cell with position ("Unknown fact `applicant.netpay` — did you mean `applicant.net_pay`?"). Hit-policy conflicts (UNIQUE with overlapping rows): validation error listing the rows.
**API.** `/rule-sets`…, `/config-artifacts/rule_set/{id}/versions` (+ `actions/submit`, `/approve`, `/activate`, `/rollback`).

#### SCR-PRD-04 · Approval matrix and authority limits
**Roles:** PM, ADM; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-APV-001, FR-APV-002, FR-APV-007, FR-SEC-007
**Purpose.** Configure who must approve what, and individual, role and org-unit limits. v3 "Approval matrix · 4 tiers · dual control above tier 2" [F v3].
**Layout.** Matrix as a decision table (inputs: product, amount band in base currency, currency, risk grade, segment, total connected exposure, collateral coverage, exception presence, insider flag → output: ordered authority levels with mode single / sequential / parallel / quorum) [F TRD §6.3]. Limits tab: users, roles and org units with limits and currency; effective-limit preview for a chosen user ("Lowest of user, role, org unit"). Draft vs live column [F v3]. [R]
**Actions.** Edit draft · Submit · Approve and activate (SU, MC; limit changes are MC [F TRD §6.4]).
**States.** Gap or overlap in amount bands: validation error. Parallel/quorum modes shown as "Available from P3" in MVP [F phase_map FR-APV-003 COMPLETES P3].
**API.** `/approval-matrices`, `/authority-limits`.

#### SCR-PRD-05 · Simulation
**Roles:** PM, CMP · **Phase:** Later (P3) · **Reqs:** FR-PRD-008, FR-CRD-002, LOS-FR-308
**Purpose.** Run a draft product or rule-set version against a historical population and compare outcomes before activation [F TRD §7.3].
**Layout.** Population picker (date range, product, count; imported history) → run → side-by-side outcome diff (approve/decline flips, grade migration matrix with numbers printed, sample cases). [R]
**API.** `/products/{id}/versions/{v}/simulate`.

#### SCR-PRD-06 · Document and notification templates
**Roles:** PM, ADM; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-OFR-001, FR-OFR-002, FR-NTF-002, FR-OFR-010, LOS-FR-314
**Purpose.** Maintain offer, agreement, advice and notification templates per event, channel and language, with maker-checker [F FR-NTF-002].
**Layout.** Template list (type, channel, language, version, execution method for documents: e-sign / wet-sign [F LOS-FR-314]) → editor (merge-field picker from the canonical schema, preview with a sample application, PDF preview via the renderer). Draft vs live. [R]
**Actions.** Edit · Preview · Submit · Approve/activate (MC, SU).
**API.** `/config-artifacts/document_template/{id}/versions`, `/config-artifacts/notification_template/{id}/versions` [R type keys].

#### SCR-PRD-07 · Workflow configuration
**Roles:** ADM, PM; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-WFL-001, FR-WFL-003, FR-WFL-004, FR-WFL-006, FR-WFL-011, FR-WFL-012, LOS-FR-282
**Purpose.** Define workflow stages per product: canonical status mapping, entry/exit guards, assignment rule, SLA, permitted actions, auto-decisions [F TRD §6.1].
**Layout.** Stage list in order (each row: stage label, canonical status chip, assignment (role + scope + pull/push/least-loaded), SLA warn/breach in business hours, auto-decision flag) → stage detail form. A read-only **canonical graph** view shows allowed transitions; invalid jumps are flagged by the validator ("Documentation → Disbursing is not allowed") [F G-13]. Version pinning note: "In-flight applications finish on v4 unless migrated" [F FR-WFL-012]. [R]
**Actions.** Edit draft · Validate · Submit · Activate (MC, SU) · Migrate in-flight applications (ADM, audited, MC) [F FR-WFL-012].
**API.** `/config-artifacts/workflow/{id}/versions` (+ actions) [R type key].

### 8.16 Administration

#### SCR-ADM-01 · Organisation and legal entities
**Roles:** ADM; AUD ◐ · **Phase:** MVP (P0) · **Reqs:** FR-TEN-003, FR-TEN-001, FR-TEN-009
**Purpose.** Legal entity → region/zone → area → branch → team hierarchy with configurable depth and labels [F FR-TEN-003].
**Layout.** Tree (keyboard: `role="tree"`, arrow keys) + detail panel (name, code, type label, parent, legal entity, jurisdiction pack, base currency). [R]
**Actions.** Add / edit / move unit (MC as configuration [F FR-TEN-009]).
**API.** `/legal-entities`, `/org-units`.

#### SCR-ADM-02 · Users
**Roles:** ADM; AUD ◐ · **Phase:** MVP (P0) · **Reqs:** FR-SEC-004, LOS-FR-302, LOS-FR-305, FR-SEC-015
**Purpose.** Create, find, deactivate users; local or directory-sourced.
**Layout.** `DataTable`: name, username, source (Local / LDAP; SSO P4), status, roles count, last sign-in, MFA state. "Invite user". [R]
**Actions.** Create (local) · Deactivate (revokes sessions) · Reset MFA (SU) · Open detail.
**API.** `/users`.

#### SCR-ADM-03 · User detail and effective access
**Roles:** ADM; AUD ◐ · **Phase:** MVP (P0) · **Reqs:** FR-SEC-011, FR-SEC-008, FR-SEC-004
**Purpose.** For one user, every permission × scope and the assignment that grants it [F FR-SEC-011].
**Layout.** Tabs: Profile · Assignments (role, scope summary, valid from/to, granted by, CR) · **Effective access** (table: permission, scopes, "granted by" assignment link; search) · Delegations · Sessions · Audit (this user's events). [R]
**API.** `/users/{id}`, `/role-assignments?filter[user]=…`, `/me/effective-access` equivalent for a user [R: `/users/{id}/effective-access`].

#### SCR-ADM-04 · Roles and permissions
**Roles:** ADM; AUD ◐ · **Phase:** MVP (P0) · **Reqs:** FR-SEC-001, FR-SEC-002, FR-SEC-003, LOS-FR-278
**Purpose.** Clone standard role templates, define custom roles; new roles start empty [F].
**Layout.** Role list (standard template tag, users count) → role editor: permission matrix grouped by resource (rows) × actions (columns) with checkboxes, field-level permissions section (e.g. `party:view_identity_numbers`), SoD warnings inline. [R]
**Actions.** Clone template · Create role (empty) · Edit (MC) · Retire.
**States.** New role: "This role has no permissions" [F FR-SEC-003].
**API.** `/roles`, `/permissions`.

#### SCR-ADM-05 · Role assignments
**Roles:** ADM; AUD ◐ · **Phase:** MVP (P0) · **Reqs:** FR-SEC-004, FR-SEC-005, FR-SEC-006, FR-SEC-007, FR-SEC-008
**Purpose.** Grant roles with scope and validity, under maker-checker and SoD.
**Layout.** Assignment list (user, role, scope chips, valid from/to, status, CR) → "New assignment" form: user, role, **scope builder** (legal entity, org-unit subtree, product set, currency set, max amount band, segments, portfolio tag; all combinable) [F TRD §8.1], valid from/to (temporary elevation with auto-expiry), justification. Live SoD check result above the submit button. [R]
**Actions.** Request assignment (MC, SU) · Revoke (MC) · Extend.
**States.** SoD conflict: blocked with the rule (§7.5). Pending checker: banner. Expiring soon: warning chip "Expires in 5 days".
**API.** `/role-assignments`, `/sod-conflicts`, `/change-requests`.

#### SCR-ADM-06 · Segregation of duties
**Roles:** ADM, CMP; AUD ◐ · **Phase:** MVP (P0) · **Reqs:** FR-SEC-006, FR-APV-005, FR-CMP-014
**Purpose.** Maintain mutually exclusive permission and role pairs and report existing conflicts [F].
**Layout.** Rules table (id, left, right, type: permission pair / role pair, enforcement: block / report, rationale) → Conflicts tab (user, rule, assignments involved, since, action). [R]
**Actions.** Add/edit rule (MC, SU) · Resolve conflict (opens assignment revoke).
**API.** `/sod-rules`, `/sod-conflicts`.

#### SCR-ADM-07 · Change-request inbox (maker-checker)
**Roles:** ADM, PM, CMP, DC, APR, OPS; AUD ◐ · **Phase:** MVP (P0) · **Reqs:** FR-SEC-007, FR-TEN-009, FR-DOC-008, FR-NTF-002, FR-DSB-009
**Purpose.** One place for every change awaiting a checker, and every request I made.
**Layout.** Tabs: **To check** (only requests I am eligible to check) · **My requests** · All (ADM, AUD). Table: CR id (mono), action type, target, maker, requested at, age, status. Detail (§7.4 checker view): summary, **diff**, validation results, maker's reason, related record link, Approve / Reject. [R]
**Actions.**

| Action | Permission | SU | MC | Endpoint |
|---|---|---|---|---|
| Approve | checker permission for the action type, in scope | per action type | is the check | `POST /change-requests/{id}/actions/approve` |
| Reject | same | – | – | `POST /change-requests/{id}/actions/reject` |
| Withdraw (maker) | maker | – | – | [R] `actions/withdraw` |

**States.** Self-check: Approve absent; "You made this request" (§7.4). Stale: `change_request_stale`. Execution failed after approval: danger "Approved but not executed: <reason>". Empty: "Nothing to check."
**API.** `/change-requests` (+ `actions/approve`, `/reject`).

#### SCR-ADM-08 · Reference data, calendars and FX
**Roles:** ADM; PM ◐, AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-TEN-006, FR-TEN-007, FR-TEN-009, FR-CFG-001
**Purpose.** Tenant reference lists (currencies, FX rates, calendars/holidays, sector, occupation, geography, document types, reason codes, PII purposes) [F FR-TEN-006].
**Layout.** List of reference types → type page: entries table (code mono, label, active, effective dates) with draft/live; calendar view for holidays (with a list alternative); FX rates table (pair, rate, date, source). [R]
**Actions.** Edit draft · Import CSV · Submit · Activate (MC).
**API.** `/reference-lists/{type}`, `/calendars`, `/fx-rates`.

#### SCR-ADM-09 · Prudential parameters
**Roles:** ADM, CMP; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** LOS-FR-307, FR-CMP-023
**Purpose.** Effective-dated limit base and regulatory limits per legal entity, under maker-checker [F LOS-FR-307].
**Layout.** Per legal entity: parameter table (name, value, unit, effective from, source/citation, verification status [verify TRD §9.3 item 1]). [R]
**Actions.** Propose change (MC, SU).
**API.** `/prudential-parameters`.

#### SCR-ADM-10 · Adapter bindings and mappings
**Roles:** ADM; DM ◐, AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-CBA-003, FR-CBA-012, FR-PRD-009, FR-CBA-020, FR-CBA-019, LOS-FR-309, FR-CBA-015, FR-CBA-004
**Purpose.** Bind each port (core banking, bureau, identity, screening, e-signature, notifications) to an adapter version with configuration and code mappings [F TRD §11].
**Layout.** Ports table (port, adapter, version, status: Certified / Simulator / Stub, circuit state) → binding detail: endpoints, credentials reference (never the secret), timeouts and retry policy, **capability manifest** (operation × native / emulated / unsupported + substitution) [F FR-CBA-003], **mappings** (product codes, GL, branch, currency, customer type) [F FR-CBA-012], processing location (P4, LOS-FR-309), cache TTLs. [R]
**Actions.** Edit binding (MC, SU) · Test connection · View manifest.
**States.** Simulator binding in production: warning banner "This binding uses the CBA simulator" [R]. Residency breach (P4): activation refused with explanation [F LOS-FR-309].
**API.** `/adapter-bindings`, `/adapters/{key}/manifest`.

#### SCR-ADM-11 · Integration monitor
**Roles:** ADM, DM, DC; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-CBA-010, FR-CBA-016, FR-AUD-008, FR-CBA-008, NFR-011
**Purpose.** Health of integrations: circuit breakers, outbox lag, recent calls with masked payloads [F].
**Layout.** Tiles (open circuits, outbox lag, error rate 1 h) → circuits table (binding, state Closed / Open / Half-open, since, failure count) → outbox table (message, operation, attempts, next attempt, status) → calls table (time, port, operation, latency, outcome class, correlation ID) with a detail drawer showing masked request/response. [R]
**API.** `/integration-calls`, `/outbox`, `/circuit-breakers`.

#### SCR-ADM-12 · Licence
**Roles:** ADM; AUD ◐ · **Phase:** MVP (P0) · **Reqs:** LOS-FR-316, FR-SEC-013, FR-SEC-007
**Purpose.** Show the installed licence and its usage; import a new signed licence file; produce an offline activation request [F TRD §2.6].
**Layout.** Licence card: client, licence id (mono), edition, modules (chips), named users "212 of 300", legal entities, adapter entitlements, valid from/to, grace days, support tier, signature status "Verified · Atheris key 7F3A…" · warnings timeline (T-60/30/7) · "Import licence file" · "Create activation request" (downloads a request file for air-gapped sites). [R]
**Actions.** Import (`POST /licence/import`, SU, MC) · Activation request (`POST /licence/activation-request`).
**States.** Invalid signature or fingerprint mismatch: "This licence is not valid for this installation." Grace and expiry (§7.15).
**API.** `GET /licence`, `POST /licence/import`, `POST /licence/activation-request`.

#### SCR-ADM-13 · Import centre (signed bundles)
**Roles:** ADM · **Phase:** Later (P5) · **Reqs:** FR-TEN-010, FR-CMP-012, FR-CFG-004
**Purpose.** Import signed offline artefacts (release bundle, watchlist snapshot, jurisdiction pack version, extension module; licence stays on SCR-ADM-12) and promote configuration between environments [F TRD §2.5].
**Layout.** Upload → signature verification result → contents and impact ("Obsolete configuration after upgrade: 2 items" [F FR-CFG-004]) → submit for checker. [R]
**API.** `/config/export`, `/config/import`; bundle endpoints [R].

#### SCR-ADM-14 · Delegations and out-of-office (admin)
**Roles:** ADM; AUD ◐ · **Phase:** MVP (P1) · **Reqs:** FR-WFL-005, FR-APV-006, FR-SEC-008
**Purpose.** View and manage all active and scheduled delegations of work and authority, with mandatory dates and caps [F].
**Layout.** Table (delegator, delegate, type: work cover / authority, cap, from, to, status) + create on behalf (MC). [R]
**API.** `/delegations`, `/delegations-of-authority`.

#### SCR-ADM-15 · Support access grants
**Roles:** ADM · **Phase:** Later (P5) · **Reqs:** LOS-FR-277
**Purpose.** Grant the platform operator time-bound, logged access to tenant data on request [F LOS-FR-277].
**Layout.** Requests list (requester, reason, scope, duration) → Grant / Deny (SU, MC) → active grants with countdown and "Revoke now". [R]
**API.** [R] `/operator-access-grants`.

#### SCR-ADM-16 · Branding and terminology
**Roles:** ADM · **Phase:** Later (P3) · **Reqs:** FR-TEN-005, FR-TEN-004, FR-TEN-008
**Purpose.** Terminology overrides (P3), branding: logo, letterhead, outbound domain (P5), languages (P5) [F phase_map].
**Layout.** Terminology table (term, default, override, preview). Branding: logo upload, letterhead preview, accent colour field that runs the contrast check and refuses failing colours (§3.1). [R]
**API.** `/config-artifacts/branding/{id}/versions` [R].

#### SCR-ADM-17 · Break-glass and access recertification
**Roles:** ADM, CMP · **Phase:** Later (P5) · **Reqs:** FR-SEC-009, FR-SEC-010
**Purpose.** Emergency access with justification, alerts, auto-expiry and post-use review; periodic manager attestation campaigns [F].
**Layout.** Break-glass: request form → active session banner shown to the user in danger tone "Emergency access · expires 11:42" → review tasks. Recertification: campaign list → manager view (team members × entitlements, Keep / Revoke per row, bulk). [R]
**API.** [R] `/break-glass`, `/recertification-campaigns`.

### 8.17 Platform operator console (separate application)

The operator has no access to tenant business data by default [F LOS-FR-277]. Under D-030 the operator console is a **licence and support** console, not a SaaS control plane [F]. These screens run in a separate route tree with their own sign-in and never render application data [R].

#### SCR-OPR-01 · Installation health
**Roles:** OPR · **Phase:** Later (P5) · **Reqs:** LOS-FR-276, NFR-011
**Purpose.** Health, queues and adapter circuit state with no tenant business data [F].
**Layout.** Service status tiles, queue depths, outbox lag, circuits, version, licence summary, last audit verification result. [R]
**API.** Health/readiness and metrics endpoints [R].

#### SCR-OPR-02 · Adapter registry and version pinning
**Roles:** OPR, ADM · **Phase:** Later (P5) · **Reqs:** LOS-FR-275, FR-CBA-018, FR-CBA-013
**Purpose.** Register adapter versions and pin them per tenant; show certification status [F].
**Layout.** Registry table (adapter, versions, certification kit result per version, pinned version) → pin/unpin (MC with tenant admin as checker [R]). [R]
**API.** `/adapter-bindings`, `/adapters/{key}/manifest`.

#### SCR-OPR-03 · Support sessions
**Roles:** OPR · **Phase:** Later (P5) · **Reqs:** LOS-FR-277, LOS-FR-274
**Purpose.** Request tenant-granted, time-bound access and work within it; every action logged [F].
**Layout.** Request form (reason, scope, duration) → status → active session banner with countdown and scope. [R]
**API.** [R] `/operator-access-grants`.

### 8.18 Applicant portal (PWA, mobile-first)

D-009 puts the portal in v1 but not in the MVP (D-036); phase_map places its channel, tracking and e-sign completion in P4 [F]. Mobile-first at 390 px, 44 px targets, 16 px base body [R].

#### SCR-POR-01 · Sign in with one-time code
**Roles:** APL · **Phase:** Later (P4) · **Reqs:** LOS-FR-303
**Purpose.** Applicant authentication by phone or email OTP, optional BVN-linked phone match [F LOS-FR-303].
**Layout.** Phone or email → code (`autocomplete="one-time-code"`, paste allowed) → continue. [R]
**API.** `/portal/v1/auth/*` [R].

#### SCR-POR-02 · Products and eligibility check
**Roles:** APL · **Phase:** Later (P4) · **Reqs:** FR-CHN-006, FR-PRD-007
**Purpose.** Browse available products and get an indicative result without a hard bureau enquiry [F FR-CHN-006].
**Layout.** Product cards → short eligibility form → indicative range, "Indicative only · not an offer". [R]
**API.** `/portal/v1/...`.

#### SCR-POR-03 · Apply
**Roles:** APL · **Phase:** Later (P4) · **Reqs:** FR-CHN-001, FR-CHN-003, FR-APP-002, FR-CUS-008, LOS-FR-311
**Purpose.** Self-service application with save-and-resume and granular consent (incl. GSI where applicable) [F].
**Layout.** One question group per screen, progress "Step 3 of 7", Save and continue later, consent screen with each purpose separately. [R]
**API.** `/portal/v1/...`.

#### SCR-POR-04 · Upload documents
**Roles:** APL · **Phase:** Later (P4) · **Reqs:** FR-DOC-001, FR-DOC-002, NFR-016
**Purpose.** Camera capture and file upload, resumable on poor connections.
**Layout.** Checklist of required documents; each opens camera (with edge guide) or file picker; per-file progress; "Uploaded · being checked". [R]
**API.** `/portal/v1/...`.

#### SCR-POR-05 · Application status tracker
**Roles:** APL · **Phase:** Later (P4) · **Reqs:** FR-NTF-004
**Purpose.** Status visibility and outstanding-requirement prompts [F].
**Layout.** Vertical customer-language stepper (Received · Checking your details · Credit review · Decision · Offer · Signing · Paid out), outstanding items with actions, messages link. [R]
**API.** `/portal/v1/...`.

#### SCR-POR-06 · Offer review and acceptance
**Roles:** APL · **Phase:** Later (P4) · **Reqs:** FR-OFR-003, FR-OFR-004, FR-OFR-005, FR-OFR-006, FR-CMP-025, FR-OFR-008
**Purpose.** Read key facts and the repayment schedule, then accept by e-signature, decline or ask for changes [F].
**Layout.** Key facts first (amount, total cost of credit, effective rate, fees, instalment, tenor), schedule (stacked rows on mobile), full document, Accept & sign · Ask for changes · Decline. Validity countdown. [R]
**API.** `/portal/v1/...`.

#### SCR-POR-07 · Consents and communication preferences
**Roles:** APL · **Phase:** Later (P4) · **Reqs:** FR-CUS-008, FR-NTF-003, FR-CMP-032
**Purpose.** See and withdraw consents; choose channels; opt out of non-essential messages [F].
**API.** `/portal/v1/...`.

#### SCR-POR-08 · Messages
**Roles:** APL · **Phase:** Later (P4) · **Reqs:** FR-WFL-010, FR-NTF-004
**Purpose.** Customer-visible notes and requests from the bank, with replies and attachments [F FR-WFL-010 customer-visible notes].
**API.** `/portal/v1/...`.

---
## 9. User flows

Each flow is a numbered step list: **actor · screen · action → system result**. Canonical status changes are shown as `Status`. "SU" = step-up (SCR-GLB-03); "CR" = change request (maker-checker). Alternate and failure paths follow each flow. Demo data is the fictional v3 data. These flows are also the Playwright end-to-end journeys (TRD §13, "Main journeys per role (§07 brief flows)").

### UF-01 · Capture and submit a retail loan (MVP)

Adaeze Okonkwo, Salary-Backed Loan, ₦4,500,000, 36 months.

1. **LO** · SCR-WRK-03 · "New application" → SCR-APP-01.
2. **LO** · SCR-APP-01 step 1 · picks "Salary-Backed Loan · v7 live" → step 2 · Individual → step 3 · searches by account number → CBA match "Adaeze Okonkwo · CIF 00418827" → dedupe panel: no open applications → "Create draft" (`POST /applications`, Idempotency-Key) → `Draft`, reference `LN-2026-04910`.
3. **LO** · SCR-APP-02 · Applicant section pre-filled from the CBA with `CBA` tags (no re-keying, FR-CUS-002) → enters employment details (`Staff` tags) → Consents: bureau enquiry ✓, data processing ✓, marketing ✗ (each timestamped) → autosave (`PATCH`, If-Match).
4. **LO** · SCR-APP-06 → SCR-DOC-03 · uploads payslip, 6-month statement, NIN slip → rows show "Scanning…" then "Received" (AV clean).
5. **LO** · SCR-APP-03 · no blockers → ticks declaration → "Submit application" (`POST …/actions/submit`, Idempotency-Key, If-Match) → `Submitted` → automated eligibility by `system:rules-engine` → `PreQualified` → `KycScreening`; screening task to Compliance; page shows the reference and "Next: KYC & screening".

**Alternates.**
- 2a. Duplicate found (`409 duplicate_detected`): the dedupe panel lists `LN-2026-04888 · Document review`; LO opens it instead, or overrides with a reason where policy flags but does not block.
- 2b. CBA lookup unavailable: "Continue as new to bank"; the customer is matched or created at booking (FR-CUS-011), and an integration exception is raised.
- 5a. Network drop on submit: "Outcome unknown, checking…" → `GET /applications/{id}` → if `Submitted`, show success; if still `Draft`, re-enable Submit with the **same** Idempotency-Key.
- 5b. Knock-out at eligibility: `Declined` with ordered reason codes shown on SCR-APP-03; the adverse action notice is P5.
- 5c. Licence expired beyond grace: "New application" disabled at step 1 (§7.15).

### UF-02 · KYC screening alert clearance, four-eyes (MVP)

Halima Yusuf, `LN-2026-04888`, potential PEP match at intake.

1. **System** (`system:screening`) · screens all parties at intake → 1 hit (score 82) → alert `ALR-2026-0091` `New`; task in the Compliance queue; the case's KYC tab shows "Potential match · 1 hit".
2. **CMP 1** (Ngozi Eke) · SCR-CMP-01 · claims the alert → SCR-CMP-02.
3. **CMP 1** · compares attributes (name: partial; DOB: masked) → "Reveal" DOB → UnmaskDialog: purpose "KYC verification" → SU → DOB shown for 60 s → DOB: no match.
4. **CMP 1** · first disposition "False positive", reason `FP_DOB_MISMATCH`, note → `POST …/dispositions` → `Proposed false positive`; second-review task created.
5. **CMP 2** (Musa Danladi) · SCR-CMP-02 · reviews the evidence and first disposition → "Confirm false positive" → `Cleared`. Screening evidence (list version, query, response, dispositions) retained (FR-CMP-017).
6. **System** · CDD level "Standard" complete + screening clear → `Documentation`.

**Alternates.**
- 2a. CMP 1 originated the application: actions disabled, "You can't clear this alert because you originated the application" (FR-CMP-014).
- 5a. CMP 1 attempts the second review: "Confirm" disabled, "A second reviewer is required. You made the first disposition."
- 5b. True match: CMP 1 proposes "Potential true match" → CMP 2 "Confirm match" → `Confirmed match`; the case is blocked with a danger banner; escalation task to the MLRO role.
- Re-screening repeats at approval and immediately before disbursement (FR-CMP-011, FR-CPR-004); a new hit at pre-disbursement blocks the release (UF-07 step 3a).

### UF-03 · Document checklist verification and waiver (MVP; P2 extraction variant)

1. **OPS** · SCR-WRK-01 "Document review" view · opens `LN-2026-04871` → SCR-APP-06: "6 required · 5 received · 1 flagged".
2. **OPS** · Payslip → "Review" → SCR-DOC-01 (manual variant) · enters canonical fields (net pay ₦742,000, employer Sterling Foods Limited, pay period July 2026); each shows the application value and a match marker → "Verify" → `Verified`.
3. **OPS** · Utility bill shows `Expired` → "Request new" → customer-visible note sent (SCR-APP-09).
4. **OPS** · Employer undertaking `Not received` → "Request waiver" → SCR-DOC-02: reason `WAIVER_TIER1_EMPLOYER`, justification → `202` CR-2026-00431 → row shows "Pending checker approval".
5. **Checker** (Branch manager, authority for this document type) · Checks inbox → SCR-ADM-07 · reviews the request, the case link and the authority line → "Approve" (SU if configured) → row `Waived` (FR-DOC-008).
6. **System** · checklist complete or waived → `Assessment`; task to the credit analyst queue.

**P2 variant (extraction).**
- 2′. SCR-DOC-01 shows 11 extracted fields, below-threshold first. Net pay at **58 %** ("below 75 %"): the reviewer clicks Region 9, corrects the value, then "Accept corrected value" → chip "Corrected · was 58 %"; the correction is stored as training feedback (FR-DOC-026).
- 2″. Employee name raises `DOC_NAME_MISMATCH` ("Adaeze C. Okonkwo" vs "Adaeze Okonkwo") → "Use application value" with a note.
- 2‴. "Accept all above threshold" accepts the remaining nine.

**Alternates.**
- 5a. Checker = maker: Approve absent, "You made this request…".
- 5b. Checker lacks authority for the document type: Approve disabled, with the required authority shown.
- 5c. The checklist changed after the request (document arrived): `change_request_stale` → maker withdraws.

### UF-04 · Credit assessment to recommendation (MVP)

1. **CA** (Chidi Nwosu) · inbox "Credit assessment" → SCR-CRD-01.
2. **CA** · "Pull bureau report" → consent check passes → report stored, valid 30 days (pack) → SCR-CRD-02 for detail.
3. **CA** · "Run decision" → outcome `Refer`, grade C (band 3 of 5); rules: `DSR_ABOVE_THRESHOLD` Fail (46.2 % vs ≤ 40.0 %), `DOC_NAME_MISMATCH` Refer, `CP_INSURANCE_INFORCE` Refer, others Pass.
4. **CA** · SCR-CRD-03 · checks the snapshot: inputs, rule-set v12, evaluator 1.4.0.
5. **CA** · SCR-CRD-04 · writes the memo narrative; **Recommendation**: Counter-offer ₦3,600,000 over 36 months (DSR 37.1 %) with condition precedent "Credit life insurance in force"; no exception needed after the counter-offer.
6. **CA** · "Recommend" (`…/actions/recommend`, Idempotency-Key, If-Match) → `Recommended` → matrix evaluated → `Approval`; approval requests created (tier 1: Branch manager). Toast: "Recommended · routed to Approval (tier 1)".

**Alternates.**
- 2a. Bureau consent missing: blocked with link to SCR-PTY-02.
- 2b. Bureau provider down: retryable integration notice; the pull is queued.
- 3a. Inputs changed after the run (net pay corrected): "Run again" banner; Recommend disabled until the decision is current.
- 5a. CA keeps the requested terms and raises a **policy exception** for DSR with justification and evidence → the exception escalates the required authority (TRD §7.3).

### UF-05 · Multi-level approval with SoD and step-up (MVP)

Brightpath Logistics Ltd, SME Term Loan, recommended ₦18,000,000, one policy exception. Matrix v8 row 6: sequential tier 2 → tier 3; the exception adds one level (FR-APV-007).

1. **System** · matrix evaluation at `Recommended` → approval requests: tier 2 (Credit Approver), tier 3 (Senior Credit Approver) → `Approval`.
2. **APR A** (Yusuf Lawal, tier 2, limit ₦20,000,000) · inbox "Approvals" → SCR-APV-01 · reads the packet: terms, exception with analyst justification, collateral, documents, chain.
3. **APR A** · "Approve" → SCR-APV-02 · rationale (required) → "Submit decision" (shield icon: amount above step-up threshold) → SU → vote sent with the same Idempotency-Key → tier 2 `Approved`; routes to tier 3. Audit event stores the authority basis ("Limit ₦20m · matrix v8 row 6") and the step-up reference.
4. **APR B** (Chidi Nwosu also holds an approver role in another scope) · opens the packet → Approve disabled: "You can't approve this application because you recommended it on 26 Aug 2026 (segregation of duties)." · "Eligible: 2 approvers in Ikeja region" → reassigns.
5. **APR C** (Funmilayo Adebayo, tier 3) · SCR-APV-02 · "Approve with conditions" → reason code `APPROVED_WITH_CP` + condition "Debenture over receivables perfected (CP)" → SU → `Approved`; conditions created (FR-APV-009); approval validity set (e.g. 60 days, FR-APV-012).

**Alternates.**
- 3a. Tier 2 limit below the amount: banner "Requested ₦25,000,000 against your approval limit of ₦20,000,000. You may recommend; final sanction routes to…" [F v3]; Approve becomes "Recommend to next tier".
- 3b. Step-up fails 3 times: the dialog closes, nothing is sent, and a toast explains.
- 5a. Decline: reason codes required → `Declined` (terminal for a human decline).
- 5b. Counter-offer: revised terms → `CounterOffered` → UF-06.
- 5c. Send back: outcome `refer_back` with rework items → `ReturnedForRework` → Assessment.
- 5d. Approval validity lapses before disbursement: `Expired (approval validity)` → re-approval (SCR-DSB-02 blocks release).
- 5e. A committee tier is required: see §12 C-09 (MVP behaviour pending decision).

### UF-06 · Offer issue and acceptance, e-sign and wet sign (MVP; e-sign via stub until P4)

Brightpath Logistics Ltd: facility agreement (e-sign) + debenture (wet-sign by pack default).

1. **LO** · SCR-OFR-01 · approved terms pre-filled; template "SME facility offer v5"; key facts (total cost of credit, effective rate, fees); full repayment schedule; validity 14 days → "Issue" (`offers … actions/issue`, Idempotency-Key) → `OfferIssued`; email + printable branch copy.
2. **LO** · SCR-OFR-02 · signing order: Director 1 → Director 2 → Guarantor → Bank signatory 1 → Bank signatory 2.
3. **System** · e-sign envelope for the facility agreement (stub provider in MVP) → statuses update per signer; certificates stored.
4. **OPS** · the debenture shows "Must be wet-signed under the regulatory pack default" → after branch signing, "Upload executed copy" → **LEG** verifies (all pages, signatures, witness, date) → "Verified".
5. **OPS** · all required signatures verified → "Record acceptance" (`…/actions/accept`) → `Accepted` → `ConditionsPrecedent`; executed agreement stored immutably with hash and template version (FR-OFR-010).

**Alternates.**
- 2a. Customer wants changes: "Customer rejected / wants changes" → reason code → back to `Assessment` (G-14d).
- 3a. A signer tries out of order: blocked with the required order.
- 5a. Offer validity lapses: `Expired (offer validity)`; "Re-issue" per rules (FR-OFR-007).

### UF-07 · CP clearance → pre-disbursement check → maker-checker release → CBA failure → safe retry → booked (MVP)

Sunrise Pharmacy Ltd, `LN-2026-04855`, Asset Finance ₦9,750,000.

1. **LEG** · SCR-APP-08 · CP "Credit life insurance in force": uploads certificate → **OPS** (different user) "Satisfy" → CP "Asset insurance with bank interest noted" likewise → no mandatory CP open → `ReadyForDisbursement`.
2. **DM** (Ibrahim Sanni) · SCR-CPR-01 · "Run checks" → KYC current ✓, screening re-run ✓, documentation executed ✓, security perfected ✓, insurance in force ✓, account verified (name enquiry match 96 %) ✓, approval unexpired ✓ → "Ready to disburse".
3. **DM** · SCR-DSB-01 · approved ₦9,750,000; four deductions; net to customer **₦9,382,250.00** → "Submit for release" (`POST /facilities/{id}/disbursements`) → CR; banner "Awaiting disbursement checker".
4. **DC** · Checks inbox → SCR-DSB-02 · reviews instruction, checks, approval validity, maker → "Release to core banking" → SU → `POST /disbursements/{id}/actions/release` (Idempotency-Key) → `Disbursing · pending_cba`.
5. **System** · attempt 1 times out (90 s) → adapter balance enquiry confirms no posting → `failed_retryable` · attempt 2 rejected `HTTP 422 CBA-ERR-4412 GL_PERIOD_CLOSED` (business rejection) → exception in SCR-DSB-04.
6. **DM** · SCR-DSB-03 · banner "Core banking posting failed. Funds have NOT left the bank." + explanation "GL 1042-03 closed for period 2026-08 … correct the GL mapping, then retry" + attempt log.
7. **ADM** · SCR-ADM-10 · updates the GL mapping in the Finacle binding → CR → **second ADM** approves (SU) → mapping active.
8. **DM** · SCR-DSB-03 · "Retry posting" → "Sending…" → attempt 3 acknowledged → loan account `8801-4471-02` → schedule retrieved, variance within tolerance (LOS-FR-313) → `Booked`.
9. **DM** · SCR-DSB-06 · facility-created event published; disbursement advice and final schedule sent; open CS and insurance expiries handed to servicing.

**Alternates.**
- 3a. Re-screen hit at step 2: "Blocked: potential sanctions match. Compliance alert ALR-2026-0102 opened"; no instruction possible.
- 4a. DC is the maker, or the approver where policy requires: SoD message (§7.5).
- 4b. Approval expired between steps 3 and 4: `409` "Approval validity lapsed; release blocked".
- 4c. The browser loses its connection after "Release": "Outcome unknown, checking…" → GET → shows the actual saga state; no second release is possible.
- 5a. Indeterminate result the adapter cannot resolve: `failed_intervention` → no Retry; "Open exception" → manual lookup and resolution with evidence (SCR-DSB-04).
- 8a. Schedule variance beyond tolerance: booking blocked as an exception (LOS-FR-313).
- Next day: SCR-DSB-05 reconciliation shows 0 breaks for the posting.

### UF-08 · Product configuration draft → maker-checker activation (MVP)

Salary-Backed Loan `SBL-036`, v7 live → v8.

1. **PM 1** (Tunde Bakare) · SCR-PRD-01 → "Create draft from v7" → v8 `Draft`.
2. **PM 1** · SCR-PRD-03 · rule set "Retail eligibility" draft: adds `MIN_EMPLOYMENT_MONTHS ≥ 6` → "Test against snapshot" (LN-2026-04871) → Pass; trace shown.
3. **PM 1** · SCR-PRD-02 · binds the draft rule set; adds checklist item `DOC_EMPLOYER_UNDERTAKING` (conditional: tier 2 employers); changes the management fee 1.0 % → 1.25 %; each change shows a "Changed" / "New in v8" tag in the draft-vs-live column.
4. **PM 1** · "Validate" → error "Bound rule set v13 is not approved" → submits the rule set first (CR-2026-00440), then the product with effective date 1 Oct 2026 (CR-2026-00441). Banners: "Pending checker approval".
5. **PM 2 / Head of Credit Risk** · SCR-ADM-07 · CR-00440: diff of rule rows → Approve (SU) → rule set v13 `Approved`. CR-00441: diff of product fields → Approve (SU) → v8 `Approved · scheduled 1 Oct 2026`.
6. **System** (`system:scheduler`) · 1 Oct 2026 00:00 WAT → v8 `Active`; 38 in-flight applications remain on v7 (FR-PRD-006).

**Alternates.**
- 5a. PM 1 opens their own CR: no Approve; "You made this request…".
- 5b. Someone edits v8 after submission: the CR fails stale on approval → PM 1 resubmits.
- 4a. P3: "Simulate" against the last 6 months before submitting (SCR-PRD-05).

### UF-09 · Auditor reconstructs an application and exports the evidence pack (MVP)

Internal auditor, `LN-2026-04863` (Brightpath, tier 3 SLA breach).

1. **AUD** · SCR-GLB-01/02 · signs in with MFA → read-only shell; "Read-only · Auditor access" banner on every operational screen.
2. **AUD** · SCR-AUD-01 · filter application = `LN-2026-04863` → 214 events; opens "Escalated · Yusuf Lawal · 26 Aug 2026, 17:52:10 WAT" → full event with authority basis and step-up ref.
3. **AUD** · SCR-APP-04 and SCR-APP-10 · reads the case read-only (no actions rendered).
4. **AUD** · SCR-AUD-02 · as-at **26 Aug 2026, 17:52 WAT** → sees the packet, decision snapshot, conditions and chain exactly as they were → "Compare with now" highlights the later tier 3 decision.
5. **AUD** · SCR-CRD-03 · "Replay" → "Replay matches original".
6. **AUD** · needs the director's BVN for a sample check → "Reveal" → purpose "Audit review" → SU → shown for 60 s; logged in SCR-AUD-06.
7. **AUD** · SCR-AUD-03 · "Request evidence pack" (purpose "Internal audit 2026-Q3") → `Building 40 %` → `Ready` → download ZIP; verifies the manifest signature against the shown key fingerprint.
8. **AUD** · SCR-AUD-05 · verifies the chain for August → "Chain intact".

**Alternates.**
- Licence expired: every step still works (D-034).
- Examiner (P5) with time-bound access: at expiry the session ends with "Your examiner access ended on 14 Nov 2026".
- 7a. Build fails: "Retry"; the failure and retry are audit events.

---
## 10. Component usage guide

**Principle (D-027).** The component **inventory and names** come from AuditPro (`PageHeader`, `DataTable`, `FilterBar`, `StatusBadge`, `StatCard`, `Modal`, `ConfirmDialog`, `EmptyState`, `FlashNotification`, `Dropdown`, `PrimaryButton`…) [F AuditPro §6]. The **look** comes from Fundly v3 via the tokens [F v3]. New components needed by LOS patterns are marked [R]. Components live in `frontend/src/components` as TypeScript (TRD §2.2: "AuditPro components ported to TS") [F].

**Not adopted from AuditPro** [R]: `AiAssistantPanel`, `AiReportGenerator`, `GenerateExternalLinkModal`, `RegulatoryReferenceSuggester` (GRC-specific); `DonutChart` and `ProgressRing` (use bars and `ProgressBar`, which compare better and read more accurately); the bell-ring animation; card hover lift; uppercase tracked button text; Heroicons (Lucide instead); Inter and the navy/gold palette. `DuplicateFindingWarning`'s pattern is reused as the dedupe panel on SCR-APP-01.

**Shared rules for every component** [R]:
- Props take semantic intents (`tone="danger"`), never colours or class strings.
- Every interactive element: `:focus-visible` ring (§3.8), ≥ 24 px target (44 px under `md` touch layouts), disabled via `aria-disabled` when a reason must stay discoverable (focusable, with the reason), native `disabled` only when no explanation is needed.
- Icons from `lucide-react`, 14/16/20 px, `aria-hidden="true"` unless the icon is the only label (then `aria-label` on the button).
- Respect `prefers-reduced-motion` through the duration tokens.

### 10.1 Layout and navigation

**`AuthenticatedLayout`** (AppShell) [F AuditPro §11; v3 shell]
- Purpose: shell with sidebar, title bar, banner zone and the single scrolling content column.
- Props: `title`, `subtitle?`, `actions?`, `banners?: Banner[]`, `scrollContainerRef`.
- Behaviour: switches sidebar → icon rail at `< lg` and → top bar + bottom tabs at `< md` (§4.1). Content region is `<main id="main">`; a "Skip to main content" link is the first focusable element.
- A11y: landmarks `nav` (sidebar, `aria-label="Main"`), `header`, `main`; the scroll container carries `data-scroll-container` (focus-not-obscured padding).

**`Sidebar` / `NavItem` / `NavGroup`** [F AuditPro §6.12; v3 nav]
- Props: `NavItem { to, icon, label, count?, countTone? }`; `NavGroup { label, items }`.
- States: default, hover (`bg-hover-nav`), active (`bg-selected`, 600, `aria-current="page"`), focus.
- Icon rail variant: icon + tooltip label + count dot; the label is still the accessible name.
- Don't: use AuditPro's dark navy sidebar or gold active border.

**`TitleBar`** (AuditPro `TopBar`) [F AuditPro §6.12; v3 header]
- Props: `title`, `subtitle?`. Right slot fixed: search, help, notifications, user menu (§5.1).
- Height 48 px, sticky, hairline bottom border.

**`PageHeader` + `Breadcrumb`** [F AuditPro §6.9]
- Props: `title`, `subtitle?`, `breadcrumb?`, `actions?`, `meta?`.
- Use on list/config/admin pages. Case screens use `ContextBar` instead.
- Title uses `text-title` (16 px), not AuditPro's 28 px bold [F v3].

**`ContextBar`** [R; F v3 application context bar]
- Props: `application`, `primaryAction`, `secondaryActions[]`, `onOpenProperties?` (laptop drawer).
- Content: applicant + segment tag; ref · branch · submitted (ref in `RefText`); Amount / Product / Term / Stage / SLA; actions.
- Responsive: wraps to two lines below `xl`; secondary actions collapse into a `Dropdown`; the primary action never does.

**`Tabs`** [F v3 tab row; AuditPro `NavLink`]
- Props: `items: { id, label, badge?, badgeTone?, to }[]`, `variant: 'route' | 'local'`.
- Row on `bg-muted`, sticky [F v3]. 12.5 px labels, padding 9 × 13; inactive in tertiary; badge chip with tone [F v3]. **Active tab: primary text, weight 600 and a 2 px `accent` underline** [R]. In v3 the underline is transparent for every tab, so the active tab differs by text colour alone (WCAG 1.4.1).
- A11y: `role="tablist"`/`tab`/`tabpanel` for local tabs; route tabs use `nav` + `aria-current`. Arrow keys move; Home/End jump. Overflow: horizontal scroll with visible arrow buttons.

**`StageTracker`** [F v3]
- Props: `stages: { label, meta, state: 'done' | 'current' | 'pending' | 'skipped' }[]`, `orientation: 'vertical' | 'horizontal'`.
- Visual [F v3]: 11 px square dot in a 14 px column (done: `accent-graphic` fill and border; current: white fill + `accent-graphic` border; pending: `indicator` border, replacing v3's 1.52:1 `#d3d1cd`); 1 px connector `accent-border` (done) or `border-strong`; label 12.5 px, current 600; meta 11 px tertiary.
- A11y: an ordered list; each item's accessible name includes its state ("Credit assessment, current step, in progress, Chidi Nwosu"). Shows workflow stages, not canonical statuses (§7.1).

**`Drawer`** [R]
- Props: `side: 'right' | 'left'`, `width` (token), `title`, `open`, `onClose`, `modal?: boolean`.
- Uses: properties rail at laptop width (non-modal); mobile nav (modal).
- A11y: modal → focus trap, `aria-modal="true"`, Esc closes, focus returns to the trigger. Non-modal → `role="complementary"`, no trap, Esc closes.

**`SidePeek`** [F v3 document review]
- Props: `title`, `meta`, `open`, `onClose`, `children` (two panes).
- `shadow-drawer`, width `--layout-peek-w`, opens from the right in 220 ms (0 under reduced motion).
- A11y: `role="dialog"` with `aria-modal="false"` at `xl` (the case stays visible), `true` below `lg` (full screen). Focus moves to the heading on open; Esc closes; focus returns to the trigger row.

**`CommandPalette`** [R]
- Combobox pattern (`role="combobox"` + `listbox`), grouped options, `aria-activedescendant`, Esc closes.

### 10.2 Actions and inputs

**Buttons: `PrimaryButton`, `SecondaryButton`, `DangerButton`, `GhostButton`, `IconButton`** [F AuditPro §6.1 names; F v3 styles]
- Shared props: `size: 'sm' (28) | 'md' (32) | 'lg' (40)`, `icon?`, `iconPosition`, `loading?`, `disabledReason?`, `requiresStepUp?` (adds `shield` icon + "Requires verification" description).
- Primary: `bg-accent text-on-accent`, hover `accent-hover`, pressed `accent-pressed`. Secondary: white, `border-strong`, `shadow-button`, hover `bg-hover`. Danger: `bg-danger-fill`. Danger outline (v3 Decline): white, `text-danger`, `border-danger-soft`. Ghost: text-link colour, no border. Icon: square, `aria-label` required.
- 5 px radius, 450 weight, sentence case [F v3].
- States: hover, pressed, focus, loading (14 px spinner + "Saving…" label, `aria-busy`), disabled.
- Do: one primary per view region. Don't: use status colours for non-destructive actions; use AuditPro uppercase text.

**`TextInput`, `Textarea`, `InputLabel`, `InputError`, `HelperText`** [F AuditPro §6.2]
- Props: `label` (required), `hint?`, `error?`, `required?`, `provenance?` (§7.16), `prefix?`/`suffix?`.
- Visual: white, `border-control` (3.46:1), radius 5, height 32; hover `border-control-hover`; focus accent border + 1 px accent inner shadow; error `border-danger` + `InputError` (danger text + icon).
- A11y: label `for`/`id`; `aria-describedby` → hint + error; `aria-invalid` on error; required stated in text.

**`Select` / `Combobox`** [F AuditPro `.form-select`; R combobox]
- Native `<select>` for ≤ 10 static options; `Combobox` (searchable, async) for parties, users, reason codes, facts. Multi-select shows chips with remove buttons (24 px).

**`Checkbox`, `Radio`, `Toggle`** [F AuditPro §6.2; F Modernist radio]
- 16 px box/circle with `border-control`; checked = accent fill with white mark; the label is the click target (≥ 24 px row). Toggle only for immediate settings, never inside forms that need Save.

**`SegmentedControl`** [F v3 `.seg`]
- Track `bg-subtle`, selected option white + `shadow-seg`; radio-group semantics; arrow keys.

**`MoneyInput`, `PercentInput`, `DateInput`** [R]
- Money: fixed currency prefix, live grouping, decimal string value, `inputmode="decimal"`. Date: typed `dd/mm/yyyy` + calendar popover (grid with arrow keys; non-business days marked with text "Holiday" in the accessible name).

**`FileUpload`** [R]
- Drop zone + button; per-file rows with progress (`role="progressbar"`, `aria-valuenow`), pause/resume/cancel; tus resumable; states from SCR-DOC-03.

**`FilterBar` + `FilterChip`** [F AuditPro §6.7 structure; F v3 chips]
- Props: `chips: { id, label, selected }[]`, `search?`, `advanced?` (popover with fields), `onReset`.
- Chip: 12 px, radius 5 [R], selected = accent fill + white text; unselected = outline `border-strong` + secondary text. `aria-pressed` on each chip. "Reset" appears only when filters are active [F AuditPro].
- Don't: use AuditPro's grey filter tray with uppercase micro labels; v3's inline chip row governs.

**`Dropdown`** (menu) [F AuditPro §6.13]
- Menu-button pattern (`aria-haspopup="menu"`, `role="menu"`/`menuitem`); arrow keys, type-ahead, Esc returns focus. `shadow-popover`, radius 8.

**`ErrorSummary`** [R]
- Danger `Banner` at the top of a form listing each error as a link to its field; receives focus after a failed submit; `role="alert"` only on that transition.

**`Stepper`** [R]
- Multi-step forms (SCR-APP-01/02, SCR-POR-03): ordered list with step state; "Step 2 of 3" text; never blocks backward navigation.

### 10.3 Data display

**`DataTable`** [F AuditPro §6.5; F v3 table styling]
- Props: `columns: { id, header, cell, align?, numeric?, priority: 1|2|3, sortable? }[]`, `rows`, `getRowHref?`, `selectable?`, `bulkActions?`, `rowTone?(row)`, `loading`, `empty`, `stickyHeader`, `density: 'compact' | 'default'`.
- Visual [F v3]: header 12 px tertiary on white (or `bg-muted`), `border` bottom; body 12.5 px, cell padding 7 × 10, row rule `border-subtle`, hover `bg-hover`, row min height 32; numeric columns right-aligned `tabular-nums`; breach rows `bg-danger-wash`.
- Responsive: `priority 3` columns fold into a sub-line at `lg`; at `< md` rows render as cards (§4.1); tables that must stay tabular (reconciliation, spreading) scroll horizontally with a sticky first column.
- Row navigation: the primary cell holds a real link; the whole row is clickable for pointer users; keyboard users tab to the link (no `onClick`-only rows).
- Sorting: header buttons with `aria-sort`. Selection: checkbox column with "Select all on this page".
- Pagination: **cursor** "Show 50 more" plus count where known [F TRD §10.1 cursor pagination]; AuditPro numbered page links are not used [R].
- A11y: real `<table>` with `<th scope>`; the caption (visually hidden if needed) names the table.

**`StatCard`** [F AuditPro §6.4; F v3 metric tiles]
- Props: `label`, `value`, `delta?`, `note?`, `tone?: 'default' | 'accent' | 'danger'`, `href?`.
- 30 px/600 value (`text-metric`), 11 px delta and note; 6 px radius, hairline border; accent tile `bg-accent-subtle` + info text; danger tile `bg-danger` + danger text [F v3]. No icon box (AuditPro's coloured icon squares are dropped) [R].
- When `href` is set, the whole card is a link with an accessible name that includes label and value.

**`Card` / `Panel`** [F AuditPro `.card`; v3 panels]
- Props: `title?`, `meta?`, `actions?`, `tone?`, `padding: 'default' | 'none'`.
- White, hairline border, radius 6, padding 14 × 16, title 16/600 [F v3]. No shadow, no hover lift.

**`PropertyGrid` / `KeyValueList`** [F v3 requested-terms grid and properties rail]
- Props: `items: { label, value, note?, tone? }[]`, `columns: 1 | 2 | 4`.
- Label 11.5–12 px tertiary, value 14 px primary, note 11 px tertiary. Use `<dl>`.

**`StatusBadge`** [F AuditPro §6.3 name; F v3 chip style]
- Props: `status` (typed union of canonical statuses and the other families in §7.1), `label?` (tenant override), `icon?: boolean`, `size: 'sm' | 'md'`.
- Tone and icon resolved from the status map, never passed in. 3 px radius, 11–12 px, padding 1 × 8.
- Don't: use AuditPro `rounded-full` pills; use colour without the label.

**`SlaChip`** [F v3]
- Props: `state: 'on_track' | 'at_risk' | 'breached' | 'paused'`, `remaining` (ISO duration), `stage`, `calendarNote?`. Rendering and accessible name per §7.2.

**`ConfidenceChip`** [F v3]
- Props: `confidence: number`, `threshold: number`, `state?: 'accepted' | 'corrected' | 'manual'`, `original?`. Rules per §7.8.

**`ScoreBand`** (replaces AuditPro `RatingBadge`) [F v3 band ramp]
- Props: `bands: { label, range }[]` (5), `current: number`, `grade`.
- Five segments in `score-1…5`, each labelled in text under the segment; current band has a 2 px primary-ink marker and text "Grade C · band 3 of 5". `role="img"` with an `aria-label` stating the grade and band.

**`ReasonCode`** [F v3 codes]
- Props: `code`, `text`, `rank?`, `audience: 'staff' | 'customer'`. Rendering per §7.7. Lists use `<ol>` to keep order.

**`MoneyText`, `DateTimeText`, `RefText`** [R]
- `MoneyText { amount: string; currency: string; precision?: 'auto' | 2; sign?: 'deduction' }` → §7.11.
- `DateTimeText { value: ISO; format: 'date' | 'datetime' | 'audit' | 'relative' }` → §7.12; wraps a `<time dateTime>`.
- `RefText { value; copy?: boolean; truncate?: 'middle' }` → §7.13.

**`MaskedValue` + `UnmaskDialog`** [R; F TRD §5.3]
- `MaskedValue { field, masked, entityRef, canUnmask }` → §7.3. The dialog collects purpose and runs step-up.
- Don't: cache revealed values outside component state; place revealed values into editable inputs automatically.

**`ProgressBar`** [F AuditPro §6.14; v3 completeness]
- 6 px track `bg-neutral`, fill `accent-graphic`, value printed beside ("82 %"); `role="progressbar"` with `aria-valuenow` and label.

**`AuditTimeline`** [R] → §7.10.

**`PostingStatusPanel` + `AttemptLog`** [F v3 disbursement panel]
- Props: `state` (`pending_cba` | `outcome_unknown` | `failed_retryable` | `failed_intervention` | `compensating` | `acknowledged`), `properties`, `attempts[]`, `sagaSteps[]`, `onRetry?`, `failureKind: 'timeout' | 'business_rejection' | 'technical'`.
- Banner copy per §7.9; the retry button exists only in `failed_retryable`. State changes are announced in a polite live region.

**`DiffView`** [F v3 draft-vs-live column; R checker diff]
- Props: `rows: { path, label, before, after, change: 'added' | 'removed' | 'changed' | 'unchanged' }[]`, `showUnchanged?`.
- Changed values: warning-fg text + "Changed" tag [F v3 amber; R tag]; added: success "+ New"; removed: danger, struck through, "− Removed". Text markers always present.

**`DecisionTableEditor`** [R]
- Grid with `role="grid"`, cell editing on Enter/F2, Esc cancels; row Move up/Move down buttons; inline validation per cell; hit-policy select; output reason-code column with `Combobox`.

**`DocumentViewer` + `RegionOverlay` + `FieldReviewList`** [F v3]
- Viewer: page render on `bg-subtle` with `shadow-document`; toolbar (rotate, zoom, download).
- RegionOverlay (P2): regions as focusable buttons in reading order, dashed `indicator` outline; selected: 2 px `accent-graphic` + `region-fill`; accessible name "Net pay, region 9, page 1, 58 %".
- FieldReviewList: listbox-like list; selection syncs both ways with the overlay (`aria-controls`).

**`BarChart`** [R; replaces AuditPro `DonutChart`]
- Single series by default (`chart-1`), values printed at bar ends, gridlines `border-subtle`, axis labels tertiary; "View as table" toggle renders the same data in `DataTable`; a one-sentence text summary above the chart.

**`Skeleton`** [R] → §6.1 loading contract. Variants: `text`, `row`, `tile`, `panel`, `document`.

### 10.4 Feedback and overlays

**`Modal`** [F AuditPro §6.8]
- Props: `size: 'sm' (400) | 'md' (480) | 'lg' (640) | 'xl' (800)`, `title`, `description?`, `initialFocus?`, `onClose`, `dismissible?`.
- `bg-overlay` backdrop, white panel, radius 8, `shadow-modal`; enter 220 ms opacity + 8 px rise (opacity only under reduced motion).
- A11y: `role="dialog"`, `aria-modal="true"`, `aria-labelledby`; focus trap; Esc closes when dismissible; focus returns to the trigger; background `inert`.

**`ConfirmDialog`** [F AuditPro §6.8]
- Props: `variant: 'danger' | 'warning' | 'info'`, `title`, `consequence` (required text), `reasonCodes?`, `requireNote?`, `confirmLabel` (a verb, e.g. "Cancel application"), `onConfirm`.
- Icon circle replaced by a 20 px Lucide icon in tone colour beside the title [R]. Initial focus on the least destructive button for danger variants.

**`StepUpDialog`** [R] → SCR-GLB-03, §7.6.

**`FlashNotification`** (toast) [F AuditPro §6.11]
- Props: `tone`, `title`, `message?`, `action?`, `duration` (default 6 s; 0 = sticky for errors).
- Position top-right below the title bar, 360 px, radius 8, `shadow-popover`, tone border-left 3 px + icon. AuditPro's countdown progress bar is removed [R].
- A11y: container `role="status"` (polite); error toasts `role="alert"`; pause on hover/focus; dismiss button; never the only place important information appears.

**`Banner`** (inline alert) [R; F v3 posting banner]
- Props: `tone`, `title`, `body?`, `actions?`, `dismissible?`.
- Variants built on it: `MakerCheckerBanner` (§7.4), `LicenceBanner` (§7.15), `EnvironmentBanner`, `StaleBanner`, `ReadOnlyBanner`, `PostingBanner` (§7.9).
- Visual: tone bg, 1 px tone border, radius 6, 13–14 px text; title 600.
- A11y: static banners have no live role; banners appearing after an action use `role="status"`.

**`EmptyState`** [F AuditPro §6.10]
- Props: `kind: 'first_use' | 'filtered' | 'not_applicable' | 'not_started'`, `title`, `description`, `action?`.
- 32 px Lucide icon in tertiary (no grey circle), 14 px title 600, 13 px description, centred in tables, left-aligned in panels.

**`BlockedReason`** (SoD, authority, licence, permission) [R]
- Props: `kind: 'sod' | 'authority' | 'permission' | 'licence' | 'state'`, `message`, `eligible?`.
- Renders under a disabled action, linked by `aria-describedby`; icon by kind (`shield-alert`, `scale`, `lock`, `key-round`, `info`).

**`Tooltip`** [R]
- Hover and focus; Esc dismisses; content hoverable (WCAG 1.4.13); never holds essential information that is not available elsewhere.

**`SessionTimeoutDialog`** [R] → SCR-GLB-04.

**`NoteComposer` / `CommentThread`** [R]
- Type toggle Internal / Customer-visible (with visible label and confirm for customer-visible), @mention combobox, Ctrl/⌘ Enter to post.

### 10.5 Component → screen map (MVP)

| Component | Main screens |
|---|---|
| `ContextBar`, `Tabs`, `StageTracker`, `PropertyGrid`, `Drawer` | SCR-APP-04…10, SCR-CRD-01/04, SCR-APV-01, SCR-OFR-01/02, SCR-CPR-01, SCR-DSB-01…03 |
| `DataTable`, `FilterBar`, `StatCard` | SCR-WRK-01…03, SCR-CMP-01, SCR-DSB-04/05, SCR-AUD-01/04/06, SCR-ADM-* |
| `SidePeek`, `DocumentViewer`, `FieldReviewList`, `ConfidenceChip` (P2) | SCR-DOC-01 |
| `ScoreBand`, `ReasonCode` | SCR-CRD-01/03/04, SCR-APV-01 |
| `PostingStatusPanel`, `AttemptLog`, `RefText` | SCR-DSB-03/04 |
| `DiffView`, `MakerCheckerBanner` | SCR-ADM-07, SCR-PRD-02…07, SCR-ADM-05/08/10 |
| `MaskedValue`, `UnmaskDialog`, `StepUpDialog` | SCR-APP-05, SCR-PTY-01, SCR-CMP-02, SCR-AUD-* |
| `AuditTimeline` | SCR-APP-10, SCR-AUD-01/02 |
| `DecisionTableEditor` | SCR-PRD-03/04 |

---
## 11. Accessibility and quality gates

### 11.1 WCAG 2.2 AA: how this brief meets the criteria most at risk

| Criterion | Where it is handled |
|---|---|
| 1.3.1 Info and relationships | Real tables, `<dl>` property grids, labelled form controls, landmarks (§10) |
| 1.4.1 Use of colour | Every status chip, SLA chip, confidence chip, diff and chart has text; active tab gets an underline (§7, §10) |
| 1.4.3 Contrast (minimum) | §3.3: all 59 text pairs ≥ 4.5:1; `contrast-check.py` in CI |
| 1.4.4 / 1.4.10 / 1.4.12 Resize, reflow, text spacing | §4.2; no fixed-height text containers; tables scroll inside their own container |
| 1.4.11 Non-text contrast | Control borders 3.46:1, focus ring ≥ 4.13:1, indicators, score ramp and chart series ≥ 3:1 (§3.3) |
| 1.4.13 Content on hover or focus | `Tooltip` is dismissible, hoverable and persistent (§10.4) |
| 2.1.1 / 2.1.2 Keyboard, no trap | Every component's keyboard model in §10; modal traps release on Esc |
| 2.1.4 Character key shortcuts | Single-key shortcuts can be turned off (§5.5) |
| 2.2.1 Timing adjustable | Session warning with extend (SCR-GLB-04); unmask timer is a security control with re-reveal available (§7.3) |
| 2.4.3 / 2.4.7 Focus order and visible | Focus returns to triggers; 2 px ring everywhere (§3.8) |
| **2.4.11 Focus not obscured (new in 2.2)** | `scroll-padding-top` under sticky title bar, context bar and tab row; toasts never cover the focused element (top-right, below the title bar) |
| **2.5.7 Dragging movements (new)** | Rule rows, uploads and region selection all have non-drag alternatives (§10) |
| **2.5.8 Target size (new)** | 24 px minimum; v3's ~22 px table buttons raised to 28 px; 44 px on touch layouts (§3.5) |
| **3.2.6 Consistent help (new)** | Help in the same title-bar position on every screen (§5.1) |
| 3.3.1 / 3.3.3 Error identification and suggestion | Inline errors + `ErrorSummary`; problem+json mapping with corrective copy (§6.2) |
| **3.3.7 Redundant entry (new)** | CBA pre-fill, no re-asking within a journey (§7.16) |
| **3.3.8 Accessible authentication (new)** | Paste and autofill allowed in password and OTP fields; no cognitive tests (SCR-GLB-01/02/03) |
| 4.1.3 Status messages | Polite live regions for toasts, posting state, upload progress milestones, unmask countdown (§10.4, §7) |

### 11.2 Automated gates [R; extends TRD §12.1 "axe in Playwright for every screen"]

1. `python3 docs/contrast-check.py` exits non-zero on any failing token pair.
2. ESLint: no hex/rgb/hsl literals and no arbitrary Tailwind values outside `design-tokens.*`; `jsx-a11y` recommended rules.
3. Playwright + axe on every screen **in every state frame listed in §8** (loading, empty, error, denied, read-only, masked), at 1440, 1180, 820 and (where allowed) 390 px.
4. Keyboard-only Playwright script for each UF-01…UF-09.
5. Manual screen-reader pass per release (NVDA + Chrome, VoiceOver + Safari) on UF-05 and UF-07.

### 11.3 Acceptance checklist for every Claude Design screen

- [ ] Frame named with its screen ID; desktop, laptop and tablet frames (mobile only where §4 allows).
- [ ] Only tokens from `design-tokens.css`; no `#2383e2` text or fills behind text; no `#9b9a97`/`#b3b1ad` text.
- [ ] Components from §10 only, with AuditPro names.
- [ ] All state frames from the spec drawn (loading skeleton, empty, error, success, denied, plus masked/read-only/maker-checker/SoD where listed).
- [ ] Every status has a text label; colour is never the only signal.
- [ ] Focus ring visible on the focused element in at least one frame per screen.
- [ ] Targets ≥ 24 px (≥ 44 px on tablet/mobile frames).
- [ ] Money, dates and references formatted per §7.11–7.13 (₦, en-NG, WAT, mono refs).
- [ ] Actions that need step-up show the shield marker; maker-checker actions show the pending banner state; SoD-blocked actions show the reason.
- [ ] Fictional v3 demo data only.

### 11.4 Requirement coverage note

Every MVP (P0/P1) requirement with a UI surface is referenced by at least one screen in §8.1. MVP requirements with **no direct UI** (backend or architectural, verified by tests rather than screens): FR-CBA-001, FR-CBA-002, FR-CBA-005, FR-SEC-014, FR-SEC-016, FR-SEC-019. Requirement-to-screen links are also expected in `08-traceability-matrix` [R].

---

## 12. Conflicts, ambiguities and open questions

Each item states the conflict, what this brief does, and whether a PO decision is needed.

| ID | Conflict or ambiguity | Sources | What this brief does | PO decision? |
|---|---|---|---|---|
| C-01 | Three design systems: AuditPro (Inter, navy/gold, `rounded-full` pills, uppercase buttons, 260/72 px sidebar, Heroicons, numbered pagination, card hover lift) vs Fundly v3 (Archivo, one blue accent, 3–6 px radii, sentence case, 216 px sidebar, Lucide) vs Modernist (zero radius, red accent) | G-45, D-027 | v3 look + AuditPro inventory/names. Lucide, Archivo, v3 radii, cursor pagination (TRD §10.1). Icon rail 56 px. | No (D-027) |
| C-02 | Contrast failures, including two G-46 does not list: the v3 input focus ring (1.28:1) and the input border (1.28:1), plus warn text on amber (4.34:1) | G-46, v3 source | Corrected tokens (§3.2); 90 graded pairs pass | No |
| C-03 | v3 accessibility defects beyond contrast: nav items and tabs use `all: unset`, which removes focus indication; the active tab differs by text colour only; 10–10.5 px text; table buttons ~22 px tall; queue rows navigate by `onClick` only | v3 source | Focus ring restored on every control; active-tab underline; 11 px floor; 28 px small buttons; real links in rows | No |
| C-04 | v3 changes the "acting as" role per screen; the access model is the union of all active assignments with scope | v3 README; FR-SEC-004, TRD §8.1 | Shell shows all roles; screens show enabled/blocked actions; no role switcher | No |
| C-05 | v3 is fixed 1440 px; NFR-015 requires down to tablet. The mobile staff scope (inbox + approvals) depends on FR-APV-011, which is **P3**. `phase_map.NFR_PHASE` has no entry for NFR-015 or NFR-016 (or NFR-001…005, 007, 020). | v3 README; NFR-015; phase_map | Desktop/laptop/tablet are MVP; mobile approval is P3, mobile inbox MVP. Treat NFR-015/016 as P1. | **Yes**: confirm NFR-015/016 = P1 and add them to `NFR_PHASE` |
| C-06 | v3's headline document-intelligence screen depends on extraction, which D-036 excludes from the MVP (manual adapter) | v3; D-036; TRD §4 M07 | SCR-DOC-01 ships as a manual-verification variant; extraction layer in P2 | No |
| C-07 | v3 failed-posting copy says "Safe to retry — the same idempotency key will be replayed" for a **business rejection** (`GL_PERIOD_CLOSED`). If the CBA stores rejected keys, replaying the same key returns the same rejection. TRD's key includes an `attempt_group`. | v3 disbVals; TRD §11 | Copy split by failure kind (§7.9): timeout → same key; definitive rejection → new attempt group. The UI only displays the server's key. | **Yes**: integration lead to confirm attempt-group rules per adapter |
| C-08 | v3 tells the user to "Correct the product GL mapping in Products & rules"; TRD puts code/GL mappings in adapter bindings (FR-CBA-012), with FR-PRD-009 mapping "via the adapter configuration" | v3; FR-PRD-009, FR-CBA-012 | Product editor shows the mapping read-only; edits happen in SCR-ADM-10 under maker-checker | No |
| C-09 | v3's approval chain includes tier 4 Board Credit Committee; committee e-voting (FR-APV-004) is P3 and excluded from the MVP; quorum/parallel modes (FR-APV-003) complete in P3 | v3; D-036; phase_map | Not resolved. Options: (a) MVP matrices may not route to a committee; the validator blocks committee levels until P3; (b) interim: a committee secretary records the minuted committee outcome as one vote with minutes attached | **Yes**: recommend (a) unless the pilot bank's limits need a committee |
| C-10 | TRD lists separate `approve` and `activate` lifecycle actions, and also lists "config activation" and "product activation" as maker-checker actions, which could mean two checker steps | TRD §6.4, §10.2 | One checker step: the checker approves the version **and** its effective date; `system:scheduler` activates on that date | **Yes**: confirm single checker step |
| C-11 | v3's credit screen shows a score ramp and eight score drivers; scorecards (FR-CRD-005) are P3 | v3; phase_map | MVP shows the risk grade from the rule set; drivers appear in P3 | No |
| C-12 | v3's approval packet shows connected-party exposure; FR-CRD-007 and FR-CUS-010 are P3 | v3; phase_map | MVP shows customer exposure from the CBA; connected exposure in P3 | No |
| C-13 | v3's credit screen shows statement analytics and links affordability to extracted fields; statement analysis (FR-DOC-040…046) is P2 | v3; phase_map | MVP affordability uses manually verified document values, still linked to their source document | No |
| C-14 | v3 document status "Missing" vs FR-DOC-007 "not received" | v3; FR-DOC-007 | FR-DOC-007 labels govern | No |
| C-15 | v3 formats money with `en-US` and compacts as "₦25.0m"; Intl `en-NG` gives "₦25.0M" and renders USD as "US$" | v3 source; NFR-017 | en-NG; ISO codes for non-NGN; capital M accepted | No |
| C-16 | Retry of a failed posting has no stated control. Release is maker-checker + step-up; retry is not mentioned. | TRD §6.4, §8.2 | Proposed: `disbursement:retry` held by DM and DC; no new checker (same released instruction, same amount and destination); no step-up | **Yes** |
| C-17 | UI needs endpoints not in TRD §10.2: notifications, `/me/sessions`, communication log, credit memo, bureau report list, `users/{id}/effective-access`, change-request withdraw, leads, bulk import, models, insider register, PII/processor registers, operator access grants, break-glass, recertification, search `q` filter. TRD defines the problem+json format but no `code` catalogue. | TRD §10.1–10.2 | Marked [R] on each screen; codes proposed in §6.2 | **Yes**: API owner to add to OpenAPI |
| C-18 | Only example permission names exist (`application:approve`, `pii:unmask`, …) | TRD §8.1 | Proposed names on each screen [R] | API/M02 owner to confirm |
| C-19 | Operator console: D-030 makes it a licence and support console, but LOS-FR-274 still describes tenant lifecycle, and the LicensingServer contract is unknown (G-48). Where the operator console runs (inside the installation or Atheris-side) is not stated. | D-030, D-033, G-48, LOS-FR-274…277 | MVP: offline licence import and activation request in SCR-ADM-12. Operator screens P5, separate route tree, no business data. | **Yes**: location of the operator console |
| C-20 | Modernist loads Archivo from Google Fonts; on-prem CSP `default-src 'self'` and air-gapped sites cannot. Archivo `tnum` support is unverified. | Modernist styles.css; TRD §8.4; D-030 | Self-host via `@fontsource`; mono fallback for numeric columns if `tnum` is missing | No (verify in build) |
| C-21 | Auditors have "no operational actions" (LOS-FR-279). Is PII unmask an operational action? | LOS-FR-279, FR-CMP-036 | Proposed: auditors may hold `pii:unmask` (a logged read with purpose + step-up), off by default in the template | **Yes** |
| C-22 | Licence expiry "blocks logins beyond grace", yet auditor and regulator read access must never be blocked | D-034, TRD §2.6 | Sign-in stays open for auditor/examiner roles and for users completing in-flight disbursements; blocked for others | **Yes**: confirm the login exception |
| C-23 | Declined review window (G-14e) and Reversed facility outcome (G-14i) await D-013 confirmation | G-14, D-013 | Chips defined and marked P5/P3; may change | Pending D-013 |
| C-24 | FR-DSB-009 "checker distinct from approver where policy requires": where the policy switch lives is not defined | FR-DSB-009 | SoD copy supports both settings; assumed a tenant SoD rule | API/M02 owner |
| C-25 | `docs/README.md` still describes the target market as "commercial banks, PMBs and licensed fintech lenders" and shows 03 as "Not started"; D-032 limits scope to commercial banks | README; D-032 | Brief follows D-032 | No (README housekeeping) |
| C-26 | `phase_map.py`'s docstring refers to a `DEFAULT_BY_MODULE` fallback that is not defined. No impact today: all 273 BRD FR IDs are mapped explicitly. | phase_map | None | No (housekeeping) |
| C-27 | The secondary and tertiary text colours are now close (6.48 vs 5.41). v3's three-step grey hierarchy cannot survive AA on all surfaces. | §3.2 | Hierarchy carried by size/weight/case | No (accepted trade-off) |

**Decisions requested from the PO:** C-05, C-07, C-09, C-10, C-16, C-17, C-19, C-21, C-22. Once decided, record them in `decision-log.md` and this brief is updated to v1.1.
