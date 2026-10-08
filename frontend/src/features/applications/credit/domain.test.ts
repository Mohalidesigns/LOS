import { describe, expect, it } from 'vitest';
import type { BureauReport, CreditMemo, Decision } from '@/api/credit';
import { ApiProblem } from '@/api/problem';
import {
  consentBlock,
  creditWorkReason,
  dsrBar,
  firstReason,
  flatten,
  staleInputs,
  gradeBand,
  gradeTone,
  needPermission,
  outcomeMeta,
  problemLines,
  readiness,
  scoreBar,
} from './domain';
import { memoDefaults, memoSchema, toSaveBody } from './memoForm';

const money = (amount: string) => ({ amount, currency: 'NGN' });

function report(over: Partial<BureauReport> = {}): BureauReport {
  return {
    id: 'r1',
    application_id: 'a1',
    party_id: 'p1',
    party_name: 'Adebayo Foods Limited',
    bureau: 'simulator',
    report_reference: 'SIM-1',
    pulled_at: '2026-10-01T09:00:00Z',
    valid_until: '2026-10-31T09:00:00Z',
    is_valid: true,
    hit: true,
    profile: {
      score: 700,
      active_facilities: 1,
      total_outstanding: money('1000000.0000'),
      monthly_obligations: money('50000.0000'),
      max_dpd_12m: 0,
      delinquent_facilities: 0,
      enquiries_6m: 1,
      has_write_off: false,
      facilities: [],
    },
    pulled_by: 'tunde',
    ...over,
  };
}

function decision(over: Partial<Decision> = {}): Decision {
  const terms = { amount: money('12500000.0000'), tenor_months: 24, rate_percent: '24.5000', monthly_instalment: money('660000.0000') };
  return {
    id: 'd1',
    application_id: 'a1',
    sequence: 1,
    outcome: 'approve',
    risk_grade: 'B',
    requested_terms: terms,
    recommended_terms: terms,
    reason_codes: [{ code: 'GRADE_B', text_key: 'reason.grade_b', stage: 'grade' }],
    affordability: { dsr_percent: '20.0', max_dsr_percent: '40', passed: true },
    rule_set: { key: 'sme-policy', version_id: 'v1', version_no: 1 },
    evaluator_version: '1.0.0',
    bureau_report_id: 'r1',
    facts: {},
    trace: [],
    exceptions: [],
    decided_by: 'tunde',
    decided_at: '2026-10-01T10:00:00Z',
    is_latest: true,
    ...over,
  };
}

function memo(over: Partial<CreditMemo> = {}): CreditMemo {
  return {
    id: 'm1',
    application_id: 'a1',
    version_no: 1,
    decision_id: 'd1',
    sections: [],
    narrative: 'Strong cash flows from contracted offtake.',
    recommendation: 'approve',
    recommended_amount: money('12500000.0000'),
    recommended_tenor_months: 24,
    conditions: ['Insurance on the processing line'],
    authored_by: 'tunde',
    authored_at: '2026-10-01T11:00:00Z',
    ...over,
  };
}

describe('outcome and grade presentation', () => {
  it('maps every P1 outcome to a label and tone (P1 never auto-declines, decline still renders)', () => {
    expect(outcomeMeta('approve')).toMatchObject({ label: 'Approve', tone: 'success' });
    expect(outcomeMeta('refer')).toMatchObject({ label: 'Refer', tone: 'warning' });
    expect(outcomeMeta('counter_offer')).toMatchObject({ label: 'Counter-offer', tone: 'info' });
    expect(outcomeMeta('decline')).toMatchObject({ label: 'Decline', tone: 'danger' });
  });

  it('falls back to a readable neutral label for unknown outcomes', () => {
    expect(outcomeMeta('manual_review')).toMatchObject({ label: 'Manual review', tone: 'neutral' });
  });

  it('places grades on the five-band ramp', () => {
    expect(gradeBand('A')).toBe(1);
    expect(gradeBand('d')).toBe(4);
    expect(gradeBand('Z')).toBeNull();
    expect(gradeTone('B')).toBe('success');
    expect(gradeTone('C')).toBe('warning');
    expect(gradeTone('D')).toBe('danger');
    expect(gradeTone('?')).toBe('neutral');
  });

  it('bands bureau scores in line with the sme-policy grade thresholds', () => {
    expect(scoreBar(720)).toMatchObject({ band: 1, label: 'Very good' });
    expect(scoreBar(639).band).toBe(3);
    expect(scoreBar(300).fraction).toBe(0);
    expect(scoreBar(900).fraction).toBe(1);
    expect(scoreBar(550).fraction).toBeCloseTo(0.4);
    expect(scoreBar(680).fraction).toBeCloseTo(0.7);
    expect(scoreBar(null)).toMatchObject({ band: null, label: 'No score (thin file)' });
  });
});

describe('dsrBar', () => {
  it('fills relative to a 0–100 scale with a marker at the limit', () => {
    const b = dsrBar({ dsr_percent: '20.0000', max_dsr_percent: '40', passed: true });
    expect(b).toMatchObject({ dsr: 20, max: 40, scale: 100, fill: 0.2, marker: 0.4, passed: true, headroom: 20 });
    expect(b.text).toBe('DSR 20.0% vs ≤ 40.0% → Pass');
  });

  it('extends the scale when the DSR exceeds 100% and reports a fail', () => {
    const b = dsrBar({ dsr_percent: '134.2', max_dsr_percent: '40', passed: false });
    expect(b.scale).toBe(140);
    expect(b.fill).toBeCloseTo(134.2 / 140);
    expect(b.passed).toBe(false);
    expect(b.headroom).toBe(-94.2);
    expect(b.text).toContain('Fail');
  });

  it('derives pass/fail when the server sends null, and handles a missing income', () => {
    expect(dsrBar({ dsr_percent: '45', max_dsr_percent: '40', passed: null }).passed).toBe(false);
    const none = dsrBar({ dsr_percent: null, max_dsr_percent: '40', passed: null });
    expect(none).toMatchObject({ dsr: null, fill: 0, passed: null });
    expect(none.text).toMatch(/not available/);
  });
});

describe('readiness (ready to recommend)', () => {
  const base = { primaryPartyId: 'p1' };

  it('is ready with a valid primary report, a decision on it and a memo referencing it', () => {
    const r = readiness({ ...base, reports: [report()], decisions: [decision()], memo: memo() });
    expect(r.ready).toBe(true);
    expect(r.items.map((i) => i.met)).toEqual([true, true, true]);
  });

  it('needs a report for the primary applicant, not a co-applicant', () => {
    const r = readiness({ ...base, reports: [report({ party_id: 'p2' })], decisions: [], memo: null });
    expect(r.items[0]).toMatchObject({ key: 'bureau', met: false });
    expect(r.ready).toBe(false);
  });

  it('flags an expired report and a decision run on it', () => {
    const r = readiness({ ...base, reports: [report({ is_valid: false })], decisions: [decision()], memo: memo() });
    expect(r.items[0]?.detail).toMatch(/expired/);
    expect(r.items[1]?.met).toBe(false);
    expect(r.items[2]?.met).toBe(false);
  });

  it('treats a decision on an older report as stale once a newer report is pulled', () => {
    const newer = report({ id: 'r2', pulled_at: '2026-10-05T09:00:00Z' });
    const r = readiness({ ...base, reports: [report(), newer], decisions: [decision({ bureau_report_id: 'r1' })], memo: memo() });
    expect(r.items[1]?.met).toBe(false);
  });

  it('requires the memo to reference the latest decision', () => {
    const d2 = decision({ id: 'd2', sequence: 2 });
    const r = readiness({ ...base, reports: [report()], decisions: [decision({ is_latest: false }), d2], memo: memo({ decision_id: 'd1' }) });
    expect(r.items[1]?.met).toBe(true);
    expect(r.items[2]).toMatchObject({ met: false });
    expect(r.items[2]?.detail).toMatch(/earlier decision/);
  });
});

describe('memo form schema', () => {
  const ok = { narrative: 'Thirty plus characters of analyst narrative.', recommendation: 'approve' as const, recommended_amount: '', recommended_tenor_months: '', conditions: [] };

  it('accepts a minimal approve memo', () => {
    expect(memoSchema.safeParse(ok).success).toBe(true);
  });

  it('requires a real narrative', () => {
    const r = memoSchema.safeParse({ ...ok, narrative: 'too short' });
    expect(r.success).toBe(false);
  });

  it('requires amount and tenor for a counter-offer', () => {
    const r = memoSchema.safeParse({ ...ok, recommendation: 'counter_offer' });
    expect(r.success).toBe(false);
    if (!r.success) expect(r.error.issues.map((i) => i.path.join('.'))).toEqual(expect.arrayContaining(['recommended_amount', 'recommended_tenor_months']));
  });

  it('validates amount and tenor formats', () => {
    expect(memoSchema.safeParse({ ...ok, recommended_amount: '10,000,000.50', recommended_tenor_months: '36' }).success).toBe(true);
    expect(memoSchema.safeParse({ ...ok, recommended_amount: 'ten million' }).success).toBe(false);
    expect(memoSchema.safeParse({ ...ok, recommended_tenor_months: '0' }).success).toBe(false);
    expect(memoSchema.safeParse({ ...ok, recommended_tenor_months: '400' }).success).toBe(false);
  });

  it('maps form values to the save body (commas stripped, blanks to null, empty conditions dropped)', () => {
    expect(
      toSaveBody({ ...ok, recommendation: 'counter_offer', recommended_amount: '9,500,000', recommended_tenor_months: '18', conditions: [{ text: ' Personal guarantee ' }, { text: '  ' }] }),
    ).toEqual({ narrative: ok.narrative, recommendation: 'counter_offer', recommended_amount: '9500000', recommended_tenor_months: 18, conditions: ['Personal guarantee'] });
    expect(toSaveBody(ok)).toMatchObject({ recommended_amount: null, recommended_tenor_months: null });
  });

  it('defaults from the latest memo, else from the decision', () => {
    expect(memoDefaults(memo(), decision())).toMatchObject({ recommended_amount: '12500000.00', recommended_tenor_months: '24', conditions: [{ text: 'Insurance on the processing line' }] });
    const co = decision({ outcome: 'counter_offer', recommended_terms: { amount: money('9000000.0000'), tenor_months: 18, rate_percent: '26', monthly_instalment: null } });
    expect(memoDefaults(memo({ decision_id: 'd1' }), { ...co, id: 'd2' })).toMatchObject({ recommendation: 'counter_offer', recommended_amount: '9000000.00', narrative: memo().narrative });
    expect(memoDefaults(null, co)).toMatchObject({ recommendation: 'counter_offer', recommended_amount: '9000000.00', recommended_tenor_months: '18', narrative: '' });
  });
});

describe('problems and reasons', () => {
  const consentProblem = new ApiProblem({
    type: 'urn:fundly:problem:bureau-consent-missing',
    title: 'Consent missing',
    status: 422,
    detail: 'Adebayo Foods Limited has no active credit_bureau consent.',
    code: 'bureau-consent-missing',
    extensions: { party_id: 'p9' },
  });

  it('recognises the bureau consent problem and carries the party id', () => {
    expect(consentBlock(consentProblem)).toEqual({ partyId: 'p9', detail: 'Adebayo Foods Limited has no active credit_bureau consent.' });
  });

  it('ignores other problems and plain errors', () => {
    expect(consentBlock(new ApiProblem({ type: 'x', title: 'x', status: 422, detail: 'x', code: 'validation-failed' }))).toBeNull();
    expect(consentBlock(new Error('boom'))).toBeNull();
  });

  it('lists blockers (strings or objects) before the detail', () => {
    const p = new ApiProblem({ type: 'x', title: 'Not ready', status: 422, detail: 'Not ready', code: 'not-ready', extensions: { blockers: ['No current decision', { code: 'MEMO_MISSING', message: 'No memo references the decision' }] } });
    expect(problemLines(p)).toEqual(['No current decision', 'No memo references the decision']);
    expect(problemLines(new ApiProblem({ type: 'x', title: 'x', status: 422, detail: 'Status must be assessment', code: 'x' }))).toEqual(['Status must be assessment']);
  });

  it('picks the first applicable disabled reason', () => {
    expect(creditWorkReason('documentation')).toMatch(/Assessment/);
    expect(creditWorkReason('assessment')).toBeNull();
    expect(needPermission(new Set(['bureau:pull']), 'bureau:pull')).toBeNull();
    expect(firstReason(null, needPermission(new Set(), 'credit:analyse'), 'later')).toBe('Requires the credit:analyse permission.');
  });

  it('flattens facts to dotted paths with printable values', () => {
    expect(flatten({ bureau: { score: 700, has_write_off: false }, list: [1, null], empty: {} })).toEqual([
      { path: 'bureau.score', value: '700' },
      { path: 'bureau.has_write_off', value: 'No' },
      { path: 'list[0]', value: '1' },
      { path: 'list[1]', value: '—' },
      { path: 'empty', value: '{}' },
    ]);
  });
});

describe('staleInputs', () => {
  const d = { facts: { applicant: { monthly_income: '3500000.00', years_trading: 6 }, facility: { amount: '2000000.0000', tenor_months: 12 } } } as unknown as Decision;
  it('is empty when the application still matches the snapshot', () => {
    expect(staleInputs(d, { data: { monthly_income: '3500000', years_trading: 6 }, requested_amount: { amount: '2000000.00' }, tenor_months: 12 })).toEqual([]);
  });
  it('lists the inputs that changed', () => {
    expect(staleInputs(d, { data: { monthly_income: '1600000.00', years_trading: 6 }, requested_amount: { amount: '2500000.00' }, tenor_months: 12 })).toEqual(['monthly income', 'requested amount']);
  });
});
