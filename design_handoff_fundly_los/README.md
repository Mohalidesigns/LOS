# Handoff: Fundly — Loan Origination System (LOS)

## Overview
Fundly is a multi-tenant **loan origination system** for a bank ("Institution A"). The design covers the
originations workflow end to end: work queue → application workspace → document intelligence review →
credit assessment → approval decision packet → disbursement / core-banking posting, plus product & rule
configuration and an audit view.

The design is one single-page app with a **left sidebar of pages** (Notion-like), a persistent
**application context bar**, tabbed case detail, and inline property panels. Role context ("acting as")
changes with the screen — Credit Analyst on assessment, Credit Approver on approval, Disbursement Officer
on disbursement — and the UI is role-scoped.

## About the Design Files
The files in this bundle are **design references created in HTML** — prototypes showing intended look and
behaviour, **not production code to copy**. They use an internal HTML component runtime (`support.js` +
`<x-dc>` templates) that exists only for prototyping.

The task is to **recreate these designs in the target codebase's existing environment** (React, Vue,
SwiftUI, native, etc.) using its established patterns, component library and data layer. If no environment
exists yet, choose an appropriate stack and implement the designs there. Read the HTML for exact values —
every style is inline, so nothing is hidden in a stylesheet.

## Fidelity
**High-fidelity.** Final colours, typography, spacing, states, copy and interactions. Recreate the UI
closely, substituting the codebase's own primitives where they match. All content is realistic but
**fictional demo data** — do not ship it.

## Versions in this bundle
| File | What it is |
| --- | --- |
| `Fundly LOS v3.dc.html` | **CURRENT / build this one.** Notion-like surface: sidebar navigation, inline property panels, side-peek document review. |
| `Fundly LOS v2.dc.html` | Prior version — same flow, Modernist design system applied literally (top nav, rules, no sidebar). Reference only. |
| `Fundly LOS.dc.html` | v1, original "Industry" design system. Historical reference only. |

## Design language (v3)
A Notion-derived surface layered over the Modernist design system's Archivo type. Flat, dense, ruled with
hairlines rather than shadows; small radii (5–6px); one blue accent for action; semantic colours carry
status only.

### Design tokens (exact values used)
Colors
- Page background `#ffffff`; sidebar / subtle fill `#f7f7f5`; header & card-in-table fill `#fbfbfa`
- Text primary `#37352f`; secondary `#5f5e5b`; tertiary `#787774`; muted `#9b9a97`; faint `#b3b1ad`
- Hairline rules `rgba(55,53,47,0.09)`; light row rule `rgba(55,53,47,0.06)`; input border `rgba(55,53,47,0.14)`
- Row hover `rgba(55,53,47,0.035)`; nav hover `rgba(55,53,47,0.06)`; table row hover `rgba(55,53,47,0.025)`
- Accent (primary action, links, selection) `#2383e2`; hover/pressed `#1a6fc4`; tint fill `#f2f8fd`; focus ring `0 0 0 2px rgba(35,131,226,0.2)`
- Info tag: bg `#d3e5ef`, text `#1a4d80`
- Status tones (bg / text):
  - OK / pass: `#dbeddb` / `#1c6c47`
  - Warn / refer: `#fbf3db`-family amber / `#8a6a10` (see `WARN` in source)
  - Bad / fail / breach: `#ffe2dd` / `#b3261e`
  - Neutral: `#f1f0ef` / `#5f5e5b`
- Score band ramp: `#1c6c47`, `#4d7a5c`, `#8a6a10`, `#b07a1e`, `#b3261e`

Type — **Archivo** (`--font-body` / `--font-heading` from the Modernist stylesheet), base 14px.
- Section/panel title 16px / 600 / letter-spacing −0.01em
- Body 13–14px; table body 12.5px; meta 11–12px; micro 10.5px
- Big metric 30px / 600 / −0.03em; disbursement net figure 18px / 600
- Numeric columns use `font-variant-numeric: tabular-nums`
- Labels are sentence case; eyebrow labels 11px / 600 / uppercase / 0.03em in `#9b9a97`

Geometry & elevation
- Radius: 5px controls, 6px cards/tiles, 3px status chips, 4px brand mark
- Shadows: only `0 1px 2px rgba(15,15,15,0.04)` on default buttons and `0 1px 2px rgba(15,15,15,0.08)` on the selected segmented option. No other elevation.
- Spacing rhythm: 7/8/9/11/14/16/18/20px paddings; card padding 14px 16px; table cell 7px 10px

Layout
- Fixed design width **1440px**, full-viewport height, no page scroll — only the content column scrolls.
- Sidebar 216px fixed. Case detail grid: `186px | 1fr | 288px` (stage tracker | content | properties).
- Document review is a **side peek**, not a modal: document render pane + field list side by side, with click-linked highlight regions.

## Screens

### 1. Pipeline & work queue (`screen: "pipeline"`) — default
Four metric tiles (in flight 148 / awaiting my action 12 / SLA breached 5 / approved this week 23), a
filter bar of toggleable chips + search, then a dense table: Reference, Applicant (+branch), Product,
Amount (right), Stage (+flag note), Days (right), SLA chip with a 6px square dot, Assignee (+role),
"Open →". Rows are clickable; a breached row gets a `rgba(179,38,30,0.04)` wash. Footer: result count +
auto-refresh note.

### 2. Application workspace (`screen: "workspace"`)
Context bar (applicant, segment tag, reference · branch · submitted; Amount / Product / Term / Stage / SLA;
actions Back to queue, Reassign, primary action). Sticky tab row: Summary, Applicant & KYC, Documents,
Credit, Collateral, Approvals, Conditions, Audit (badges for counts). Left rail = 8-step stage tracker
(square dot + connector line, current step bold). Centre = panels: Requested terms (4-up property grid),
Applicant snapshot, Existing exposure (from core banking adapter), Case completeness (progress bar + parts).
Right rail = properties/requirements/notes panel.

### 3. Document intelligence review (`screen: "docs"`)
Rendered payslip on the left with click-selectable dashed field regions; on click the region turns
2px `#2383e2` with a `rgba(35,131,226,0.14)` fill. Right: 11 extracted fields ordered
below-threshold-first, each with value, confidence %, source region, reason code, and an "Accept" /
"Accept corrected value" action. Threshold default **75%** (a tweakable prop). Name-mismatch field carries a
`DOC_NAME_MISMATCH` warning explaining the discrepancy. "Accept all above threshold" bulk action.

### 4. Credit assessment (`screen: "credit"`)
Score band ramp + eight score drivers (signed contributions). Affordability table: net income, existing
debt service, proposed repayment, **DSR 46.2% against a ≤40.0% policy → Fail**, disposable income; each row
links back to the source field in Document review. Statement analytics (8 rows), bureau summary (8 rows),
and a policy rule list of 8 rules with Pass / Fail / Refer states, codes and explanations.

### 5. Approval decision packet (`screen: "approval"`, read-only for approver)
Requested vs recommended terms with deltas (counter-offer: ₦25.0m→₦21.5m, 48→36 months, 26.0%→28.5%).
Connected-party exposure, collateral with realisable values and perfection status, **two policy exceptions
requiring approval** (each with analyst justification), document status list, and the approval history/
routing chain across four authority tiers with an SLA breach flagged.

### 6. Disbursement & core banking (`screen: "disbursement"`)
Pre-disbursement checklist (8 checks, hard-block vs soft-check; insurance lapse = Fail). Disbursement
instruction breakdown (approved amount, four fee deductions, total deductions, net to customer ₦9,382,250).
Core-banking posting panel with three states — **failed → sending → acknowledged** — driven by
`state.posting` (initial state is a tweakable prop). Failed state explains `CBA-ERR-4412 GL_PERIOD_CLOSED`,
confirms funds have not left the bank, and offers a safe retry with the same idempotency key
(`IDMP-DI-01188-A7F3`). Retry transitions to "sending", then to "acknowledged" after **1600ms**, returning
loan account `8801-4471-02`. Attempt log grows per attempt. Sibling posting queue table.

### 7. Products & rules (`screen: "products"`)
Four configuration panels — Eligibility rules, Document checklist, Fees & pricing, Approval matrix — each a
table of item, code, condition/basis, and draft-vs-live column where amber text marks changes staged in the
next version (v8).

### 8. Audit & reports (`screen: "audit"`)
Stub / read-only.

## Interactions & behaviour
- Sidebar item → `go(screen)`, with per-screen default application ids (`approval`→LN-2026-04863, `disbursement`→LN-2026-04855, others→LN-2026-04871).
- Table row click → open workspace for that application (only for the three fully-modelled applications).
- Filter chips toggle in/out of a selected list; selected = solid accent, unselected = outlined.
- Field region ↔ field-list selection is two-way linked by field id.
- Accept adds a field id to an `accepted` list, flipping its chip to "Accepted" and the button to "Accepted ✓".
- Disbursement retry is the only timed transition (1600ms).
- Hover: rows tint, nav items tint, buttons tint. Focus-visible: 2px accent ring / accent border + 2px soft ring on inputs. Disabled: 0.5 opacity.
- No responsive behaviour designed — fixed 1440px desk-scale app. If the target needs responsive, the sidebar should collapse and the 3-column case grid should drop the right rail first.

## State model
```
screen: "pipeline" | "workspace" | "docs" | "credit" | "approval" | "disbursement" | "products" | "audit"
tab: "summary" | "kyc" | "documents" | "credit" | "collateral" | "approvals" | "conditions" | "audit"
appId: string                 // key into APPS
field: string                 // selected extraction field id
doc: string                   // selected document in review
filters: string[]             // active pipeline filter chips
accepted: string[]            // accepted extraction field ids
posting: "failed" | "sending" | "acknowledged"
```
Configurable props: `extractionThreshold` (default 75), `initialPostingState` (default "failed").

Data needed from services: application/case records, pipeline queue with SLA computation, KYC & screening
results, document set + extraction fields with confidence and source regions, bureau + statement analytics,
policy rule evaluation results, collateral & conditions, approval routing/authority matrix, disbursement fee
schedule, and core-banking adapter status (idempotency key, correlation id, attempt log).

## Assets
None. No images, no icon files — status uses coloured squares/dots and text chips. Icons, where the target
codebase wants them, should follow **Lucide** (the design system's icon choice).

## Design system
The prototypes load the **Modernist** design system: `_ds/modernist-.../styles.css` (tokens + components)
and `_ds_bundle.js`, both included in this bundle at their original relative paths. v3 overrides Modernist's
zero-radius / red-accent chrome with the Notion-like surface described above, but keeps its **Archivo**
type scale. In the target codebase, use its own design system for primitives and treat the token table above
as the intended visual result.

## Files in this bundle
- `Fundly LOS v3.dc.html` — current design (build this)
- `Fundly LOS v2.dc.html`, `Fundly LOS.dc.html` — earlier directions, reference only
- `support.js` — prototyping runtime (not for production)
- `_ds/modernist-00908af7-9af3-4154-8630-5de257cd0101/{styles.css,_ds_bundle.js,readme.md}` — design system
Open `Fundly LOS v3.dc.html` in a browser to interact with the prototype.
