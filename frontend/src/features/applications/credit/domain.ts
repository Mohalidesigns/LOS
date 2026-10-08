/**
 * Pure credit-tab logic (CRD-01..04): outcome/grade presentation, the DSR bar,
 * the bureau score bar, the "ready to recommend" checklist, the memo form
 * schema and problem handling. No React here; everything is unit-tested.
 */
import type { Affordability, BureauReport, CreditMemo, Decision, DecisionStage } from '@/api/credit';
import { isApiProblem } from '@/api/problem';
import type { Tone } from '@/components/StatusBadge';

// ------------------------------------------------------------------ permissions + codes
export const CREDIT_PERM = {
  bureauPull: 'bureau:pull',
  analyse: 'credit:analyse',
  exceptionRaise: 'exception:raise',
  recommend: 'application:recommend',
} as const;

export const CONSENT_MISSING = 'bureau-consent-missing';

export function needPermission(permissions: ReadonlySet<string>, perm: string): string | null {
  return permissions.has(perm) ? null : `Requires the ${perm} permission.`;
}

/** Credit work (pull, run, memo) is open only while the case is in assessment. */
export function creditWorkReason(status: string): string | null {
  return status === 'assessment' ? null : 'Credit work opens when the application reaches Assessment.';
}

/** First non-null reason wins (state before permission, so the user learns the bigger blocker). */
export function firstReason(...reasons: (string | null | undefined)[]): string | null {
  for (const r of reasons) if (r) return r;
  return null;
}

// ------------------------------------------------------------------ outcome / grade / stage
export type OutcomeMeta = { label: string; tone: Tone; summary: string };

export const OUTCOME_META: Record<string, OutcomeMeta> = {
  approve: { label: 'Approve', tone: 'success', summary: 'The policy supports approval on the recommended terms.' },
  refer: { label: 'Refer', tone: 'warning', summary: 'A policy rule needs an analyst’s judgement. Review the reason codes before you recommend.' },
  counter_offer: { label: 'Counter-offer', tone: 'info', summary: 'The requested terms do not pass; the engine recommends different terms.' },
  decline: { label: 'Decline', tone: 'danger', summary: 'The policy does not support this application.' },
};

export function outcomeMeta(outcome: string): OutcomeMeta {
  return OUTCOME_META[outcome] ?? { label: outcome.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase()) || 'Unknown', tone: 'neutral', summary: '' };
}

const GRADES = ['A', 'B', 'C', 'D', 'E'] as const;
export type Band = 1 | 2 | 3 | 4 | 5;

/** Risk grade A..E → band 1 (best) … 5 on the score ramp; an unknown grade returns null (no marker). */
export function gradeBand(grade: string): Band | null {
  const i = GRADES.indexOf(grade.trim().toUpperCase() as (typeof GRADES)[number]);
  return i < 0 ? null : ((i + 1) as Band);
}

export function gradeTone(grade: string): Tone {
  const b = gradeBand(grade);
  if (b === null) return 'neutral';
  if (b <= 2) return 'success';
  if (b === 3) return 'warning';
  return 'danger';
}

export const STAGE_LABEL: Record<DecisionStage, string> = {
  knockout: 'Knock-out',
  policy: 'Policy',
  grade: 'Grade',
  affordability: 'Affordability',
  pricing: 'Pricing',
  outcome: 'Outcome',
};

export function stageLabel(stage: string): string {
  return (STAGE_LABEL as Record<string, string | undefined>)[stage] ?? humanKey(stage);
}

// ------------------------------------------------------------------ numbers
export function num(v: string | number | null | undefined): number | null {
  if (v === null || v === undefined || v === '') return null;
  const n = typeof v === 'number' ? v : Number(v);
  return Number.isFinite(n) ? n : null;
}

export function pct(v: number, digits = 1): string {
  return `${v.toFixed(digits)}%`;
}

// ------------------------------------------------------------------ affordability bar
export type DsrBar = {
  dsr: number | null;
  max: number | null;
  /** Upper end of the bar scale (percent). */
  scale: number;
  /** 0..1 fill width. */
  fill: number;
  /** 0..1 position of the max-DSR marker (null when there is no limit). */
  marker: number | null;
  passed: boolean | null;
  /** Percentage points left before the limit (negative = over). */
  headroom: number | null;
  text: string;
};

/**
 * Debt-service ratio vs the policy maximum. The scale is 0–100 % unless the
 * DSR is higher, then rounded up to the next 10 so the fill never overflows.
 * `passed` trusts the server, falling back to dsr <= max.
 */
export function dsrBar(a: Pick<Affordability, 'dsr_percent' | 'max_dsr_percent' | 'passed'>): DsrBar {
  const dsr = num(a.dsr_percent);
  const max = num(a.max_dsr_percent);
  const scale = Math.max(100, dsr !== null ? Math.ceil(dsr / 10) * 10 : 0, max !== null ? Math.ceil(max / 10) * 10 : 0);
  const fill = dsr === null ? 0 : Math.min(1, Math.max(0, dsr / scale));
  const marker = max === null ? null : Math.min(1, Math.max(0, max / scale));
  const passed = a.passed ?? (dsr !== null && max !== null ? dsr <= max : null);
  const headroom = dsr !== null && max !== null ? Math.round((max - dsr) * 10) / 10 : null;
  let text: string;
  if (dsr === null) text = 'DSR not available (no income on file)';
  else if (max === null) text = `DSR ${pct(dsr)}`;
  else text = `DSR ${pct(dsr)} vs ≤ ${pct(max)} → ${passed ? 'Pass' : 'Fail'}`;
  return { dsr, max, scale, fill, marker, passed, headroom, text };
}

// ------------------------------------------------------------------ bureau score bar
export const SCORE_MIN = 300;
export const SCORE_MAX = 850;
/** Lower bounds of bands 5..1, aligned with the sme-policy grade table (550 / 640 / 720). */
const SCORE_BANDS: readonly { from: number; band: Band; label: string }[] = [
  { from: 720, band: 1, label: 'Very good' },
  { from: 640, band: 2, label: 'Good' },
  { from: 550, band: 3, label: 'Fair' },
  { from: 450, band: 4, label: 'Weak' },
  { from: -Infinity, band: 5, label: 'Poor' },
];

export function scoreBar(score: number | null): { fraction: number; band: Band | null; label: string } {
  if (score === null) return { fraction: 0, band: null, label: 'No score (thin file)' };
  // Piecewise: each of the five equal-width ramp segments spans one band (300–450–550–640–720–850).
  const edges = [SCORE_MIN, 450, 550, 640, 720, SCORE_MAX];
  let fraction = score >= SCORE_MAX ? 1 : 0;
  for (let i = 0; i < 5; i++) {
    const lo = edges[i] ?? SCORE_MIN;
    const hi = edges[i + 1] ?? SCORE_MAX;
    if (score >= lo && score < hi) fraction = (i + (score - lo) / (hi - lo)) / 5;
  }
  const b = SCORE_BANDS.find((x) => score >= x.from) ?? { band: 5 as Band, label: 'Poor' };
  return { fraction, band: b.band, label: b.label };
}

// ------------------------------------------------------------------ bureau selection
/** Newest valid report for a party (reports arrive newest first, but do not rely on it). */
export function latestValidReport(reports: readonly BureauReport[], partyId: string): BureauReport | null {
  return (
    reports
      .filter((r) => r.party_id === partyId && r.is_valid)
      .sort((a, b) => b.pulled_at.localeCompare(a.pulled_at))[0] ?? null
  );
}

export function latestDecision(decisions: readonly Decision[]): Decision | null {
  return decisions.find((d) => d.is_latest) ?? [...decisions].sort((a, b) => b.sequence - a.sequence)[0] ?? null;
}

// ------------------------------------------------------------------ ready to recommend
export type ReadinessItem = { key: 'bureau' | 'decision' | 'memo'; label: string; met: boolean; detail: string };

/**
 * Mirrors the server's recommend guard so the analyst sees what is missing
 * before trying: a valid bureau report for the primary applicant, a current
 * decision (run on that report), and a memo version that references it.
 * The server stays authoritative (its 422 `blockers` are shown verbatim).
 */
export function readiness(input: { primaryPartyId: string; reports: readonly BureauReport[]; decisions: readonly Decision[]; memo: CreditMemo | null }): { items: ReadinessItem[]; ready: boolean } {
  const report = latestValidReport(input.reports, input.primaryPartyId);
  const decision = latestDecision(input.decisions);
  const anyPrimary = input.reports.some((r) => r.party_id === input.primaryPartyId);

  const bureau: ReadinessItem = {
    key: 'bureau',
    label: 'Bureau report',
    met: report !== null,
    detail: report ? `Valid until ${report.valid_until.slice(0, 10)}` : anyPrimary ? 'The last report has expired. Pull a new one.' : 'Pull a bureau report for the primary applicant.',
  };

  const decisionOnReport = decision !== null && report !== null && (decision.bureau_report_id === null || decision.bureau_report_id === report.id);
  const decisionItem: ReadinessItem = {
    key: 'decision',
    label: 'Decision',
    met: decision !== null && decisionOnReport,
    detail:
      decision === null
        ? 'Run the decision once the bureau report is in.'
        : !decisionOnReport
          ? 'A newer bureau report exists or the report expired. Run the decision again.'
          : `Decision #${decision.sequence}: ${outcomeMeta(decision.outcome).label}`,
  };

  const memoCurrent = input.memo !== null && decision !== null && input.memo.decision_id === decision.id;
  const memo: ReadinessItem = {
    key: 'memo',
    label: 'Credit memo',
    met: memoCurrent && decisionItem.met,
    detail:
      input.memo === null
        ? 'Save a credit memo version.'
        : !memoCurrent
          ? `Memo v${input.memo.version_no} refers to an earlier decision. Save a new version.`
          : `Memo v${input.memo.version_no} references decision #${decision.sequence}`,
  };

  const items = [bureau, decisionItem, memo];
  return { items, ready: items.every((i) => i.met) };
}

// ------------------------------------------------------------------ problems
export type ConsentBlock = { partyId: string | null; detail: string };

/** A 422 `bureau-consent-missing` → the blocked state (with a link to the consents panel). */
export function consentBlock(e: unknown): ConsentBlock | null {
  if (!isApiProblem(e) || !e.is(CONSENT_MISSING)) return null;
  const partyId = typeof e.extensions.party_id === 'string' ? e.extensions.party_id : null;
  return { partyId, detail: e.detail };
}

/** Messages for a business-rule refusal: `blockers` first, then field errors, then the detail. */
export function problemLines(e: unknown): string[] {
  if (!isApiProblem(e)) return e instanceof Error ? [e.message] : ['Something went wrong.'];
  const b = e.extensions.blockers;
  if (Array.isArray(b)) {
    const list = b.map((x) => (typeof x === 'string' ? x : isRecord(x) && typeof x.message === 'string' ? x.message : isRecord(x) && typeof x.code === 'string' ? x.code : null)).filter((x): x is string => x !== null);
    if (list.length > 0) return list;
  }
  return e.messages;
}

function isRecord(v: unknown): v is Record<string, unknown> {
  return typeof v === 'object' && v !== null && !Array.isArray(v);
}

// ------------------------------------------------------------------ facts / content viewer
export type FlatEntry = { path: string; value: string };

/** Flatten a JSON object into dotted paths with printable values (no HTML, ever). */
export function flatten(value: unknown, prefix = '', out: FlatEntry[] = [], depth = 0): FlatEntry[] {
  if (depth > 8) {
    out.push({ path: prefix || '(root)', value: '…' });
    return out;
  }
  if (Array.isArray(value)) {
    if (value.length === 0) out.push({ path: prefix || '(root)', value: '[]' });
    value.forEach((v, i) => flatten(v, `${prefix}[${i}]`, out, depth + 1));
    return out;
  }
  if (isRecord(value)) {
    const keys = Object.keys(value);
    if (keys.length === 0) out.push({ path: prefix || '(root)', value: '{}' });
    for (const k of keys) flatten(value[k], prefix ? `${prefix}.${k}` : k, out, depth + 1);
    return out;
  }
  out.push({ path: prefix || '(root)', value: printable(value) });
  return out;
}

export function printable(v: unknown): string {
  if (v === null || v === undefined) return '—';
  if (typeof v === 'boolean') return v ? 'Yes' : 'No';
  if (typeof v === 'string') return v;
  if (typeof v === 'number') return String(v);
  return JSON.stringify(v);
}

export function humanKey(k: string): string {
  return k.replace(/[_-]+/g, ' ').replace(/^\w/, (c) => c.toUpperCase());
}

// ------------------------------------------------------------------ stale decision inputs
type Facts = { applicant?: { monthly_income?: unknown; years_trading?: unknown }; facility?: { amount?: unknown; tenor_months?: unknown } };

/**
 * Inputs captured on the application that changed after the decision ran
 * (brief CRD-01 "Inputs changed after this decision … Run again"). Compares the
 * snapshot's facts with the current application; numbers are compared numerically.
 */
export function staleInputs(decision: Pick<Decision, 'facts'>, app: { data: Record<string, unknown>; requested_amount?: { amount: string } | null; tenor_months?: number | null }): string[] {
  const f = decision.facts as Facts;
  const changed: string[] = [];
  const differs = (a: unknown, b: unknown) => {
    const x = num(typeof a === 'number' || typeof a === 'string' ? a : null);
    const y = num(typeof b === 'number' || typeof b === 'string' ? b : null);
    return x !== y;
  };
  if (f.applicant && differs(f.applicant.monthly_income, app.data.monthly_income ?? app.data.monthly_turnover)) changed.push('monthly income');
  if (f.applicant && differs(f.applicant.years_trading, app.data.years_trading)) changed.push('years trading');
  if (f.facility && differs(f.facility.amount, app.requested_amount?.amount)) changed.push('requested amount');
  if (f.facility && differs(f.facility.tenor_months, app.tenor_months)) changed.push('tenor');
  return changed;
}

export function staffLabel(id: string, myId: string): string {
  if (id === myId) return 'You';
  if (/^[0-9a-f-]{20,}$/i.test(id)) return `Staff user ${id.slice(-6)}`;
  return id;
}
