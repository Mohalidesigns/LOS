# Fundly LOS — Delivery Documentation Index

These documents take the Loan Origination System from its business requirements to a built, tested system for Nigerian commercial banks, PMBs and licensed fintech lenders. Each step stops at a gate for Product Owner sign-off before the next step begins.

**Scope source of truth:** `../loan-origination-system-brd-v0.1.docx` (BRD v0.1, 27 Aug 2026), together with the approved entries in `decision-log.md`.

## Deliverables

| # | File | Step | Status |
|---|---|---|---|
| 01 | `01-requirements-inventory.md` (+ `.csv`) | 1: BRD study | **Awaiting sign-off** |
| 02 | `02-gap-register.md` (gaps, ambiguities, assumptions, exclusions) | 1: BRD study | **Awaiting sign-off** |
| 03 | `03-TRD.md` | 2: Technical requirements | Not started; gated on Step 1 |
| 04 | `04-integration-register.md` | 2: Technical requirements | Not started |
| 05 | `05-agent-roster.md` | 3: Agent team | Not started |
| 06 | `06-development-plan.md` (+ progress tracker, delivery risk register) | 4: Development plan | Not started |
| 07 | `07-claude-design-brief.md` | 5: Claude Design brief | Not started; needs D-027 |
| 08 | `08-traceability-matrix.md` (+ `.csv`) | 2 onward, maintained continuously | Not started |
| — | `decision-log.md` | All steps | 29 decisions proposed |

## Conventions

- **Requirement IDs.** `LOS-FR-nnn` (functional), `LOS-NFR-nnn` (non-functional) and `LOS-CON-nnn` (principles and constraints). Each carries its BRD ID unchanged. IDs are never reused or renumbered.
- **Labels.** INFERRED means the requirement is not in the BRD and stays out of scope until the PO approves it.
- **Other ID families.** Gaps are `G-nn`, assumptions `A-nn`, decisions `D-nnn`.
- **Facts vs recommendations.** BRD facts and my recommendations are always kept separate.
- **Regulatory claims.** Any claim marked **[verify]** is checked against its primary source before it is designed in.
- **Coverage.** No requirement is marked "covered" unless it is mapped to a module, a task and a test.

## Source material in the parent folder

| File | Role |
|---|---|
| `loan-origination-system-brd-v0.1.docx` | Scope source of truth |
| `design_handoff_fundly_los/` (v3 current; v1 and v2 historical) | LOS-specific high-fidelity UI design (see G-45) |
| `AuditPro_GRC_Design_System.md` | Atheris house design system for Laravel/Inertia/React/Tailwind (see G-45) |
| `design-system.md` | Palette stub from another product; recommended to ignore (G-45) |
| `Fundly loan system setup.zip` | Byte-identical copy of `design_handoff_fundly_los/` |
