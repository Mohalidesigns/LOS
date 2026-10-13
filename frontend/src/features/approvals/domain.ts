/**
 * Pure approval logic (APV-01/02): decision and level presentation, the vote
 * form rules (mirroring the API: rationale always, reason codes unless a plain
 * approval, conditions for a conditional approval, counter terms below the
 * exposure) and authority-basis wording. No React; unit-tested.
 */
import type { ApprovalLevel, ApprovalRequest, AuthorityBasis, VoteBody, VoteDecision } from '@/api/approvals';
import type { Tone } from '@/components/StatusBadge';

export const APPROVE_PERM = 'application:approve';

export const DECISION_META: Record<VoteDecision, { label: string; tone: Tone; verb: string }> = {
  approve: { label: 'Approve', tone: 'success', verb: 'approved' },
  approve_with_conditions: { label: 'Approve with conditions', tone: 'success', verb: 'approved with conditions' },
  decline: { label: 'Decline', tone: 'danger', verb: 'declined' },
  counter_offer: { label: 'Counter-offer', tone: 'info', verb: 'counter-offered' },
};

export const REQUEST_STATUS_META: Record<ApprovalRequest['status'], { label: string; tone: Tone }> = {
  pending: { label: 'Awaiting votes', tone: 'warning' },
  approved: { label: 'Approved', tone: 'success' },
  declined: { label: 'Declined', tone: 'danger' },
  counter_offered: { label: 'Counter-offered', tone: 'info' },
  expired: { label: 'Expired', tone: 'neutral' },
  superseded: { label: 'Superseded', tone: 'neutral' },
};

export function levelState(level: Pick<ApprovalLevel, 'state'>): { label: string; tone: Tone } {
  switch (level.state) {
    case 'awaiting_vote':
      return { label: 'Awaiting vote', tone: 'warning' };
    case 'queued':
      return { label: 'Queued', tone: 'neutral' };
    case 'not_reached':
      return { label: 'Not reached', tone: 'neutral' };
    default:
      return { label: DECISION_META[level.state].label, tone: DECISION_META[level.state].tone };
  }
}

export const ADDED_BY_LABEL: Record<ApprovalLevel['added_by'], string> = {
  matrix: 'Required by the matrix',
  exception: 'Escalated: policy exception',
  no_eligible_approver: 'Escalated: no eligible approver below',
};

export const LIMIT_SOURCE_LABEL: Record<AuthorityBasis['limit_source'], string> = {
  assignment_scope: 'role assignment scope',
  user: 'personal limit',
  role: 'role limit',
  org_unit: 'org-unit limit',
};

/** "Head of Credit · role limit NGN 100,000,000 · delegated" */
export function basisParts(b: AuthorityBasis): { role: string; limitSource: string; delegated: boolean } {
  return { role: b.role_name || b.role_code, limitSource: LIMIT_SOURCE_LABEL[b.limit_source], delegated: b.delegation_id !== null };
}

// ------------------------------------------------------------------ vote form
export type ConditionDraft = { kind: 'precedent' | 'subsequent'; description: string };

export type VoteForm = {
  decision: VoteDecision;
  rationale: string;
  /** comma / space separated UPPER_SNAKE codes */
  reasonCodes: string;
  conditions: ConditionDraft[];
  counterAmount: string;
  counterTenor: string;
};

export const EMPTY_VOTE: VoteForm = { decision: 'approve', rationale: '', reasonCodes: '', conditions: [], counterAmount: '', counterTenor: '' };

const CODE = /^[A-Z][A-Z0-9_]{1,47}$/;
const AMOUNT = /^\d{1,16}(\.\d{1,4})?$/;

export function parseCodes(raw: string): string[] {
  return [...new Set(raw.split(/[\s,;]+/).map((c) => c.trim().toUpperCase()).filter(Boolean))];
}

export function needsReasonCodes(d: VoteDecision): boolean {
  return d !== 'approve';
}

export type VoteErrors = Partial<Record<'rationale' | 'reasonCodes' | 'conditions' | 'counterAmount' | 'counterTenor', string>> & { conditionRows?: Record<number, string> };

export function validateVote(f: VoteForm, exposureAmount: string): VoteErrors {
  const e: VoteErrors = {};
  if (f.rationale.trim().length < 10) e.rationale = 'Record your rationale (10 characters or more).';
  const codes = parseCodes(f.reasonCodes);
  if (needsReasonCodes(f.decision) && codes.length === 0) e.reasonCodes = `${DECISION_META[f.decision].label} needs at least one reason code.`;
  else if (codes.some((c) => !CODE.test(c))) e.reasonCodes = 'Reason codes are UPPER_SNAKE_CASE, e.g. COND_GUARANTEE.';
  if (f.decision === 'approve_with_conditions') {
    if (f.conditions.length === 0) e.conditions = 'Add at least one condition.';
    const rows: Record<number, string> = {};
    f.conditions.forEach((c, i) => {
      if (c.description.trim().length < 5) rows[i] = 'Describe the condition (5 characters or more).';
    });
    if (Object.keys(rows).length > 0) e.conditionRows = rows;
  }
  if (f.decision === 'counter_offer') {
    const amount = f.counterAmount.replace(/,/g, '').trim();
    if (!AMOUNT.test(amount)) e.counterAmount = 'Enter the counter-offer amount.';
    else if (Number(amount) <= 0 || Number(amount) >= Number(exposureAmount)) e.counterAmount = 'A counter-offer must be for less than the requested amount.';
    const tenor = Number(f.counterTenor);
    if (!Number.isInteger(tenor) || tenor < 1 || tenor > 600) e.counterTenor = 'Enter a tenor in months.';
  }
  return e;
}

export function hasErrors(e: VoteErrors): boolean {
  return Object.keys(e).length > 0;
}

export function voteBody(f: VoteForm): VoteBody {
  return {
    decision: f.decision,
    rationale: f.rationale.trim(),
    reason_codes: parseCodes(f.reasonCodes),
    conditions: f.decision === 'approve_with_conditions' ? f.conditions.map((c) => ({ kind: c.kind, description: c.description.trim() })) : [],
    counter_terms: f.decision === 'counter_offer' ? { amount: f.counterAmount.replace(/,/g, '').trim(), tenor_months: Number(f.counterTenor) } : null,
  };
}

/** The request that matters on the case: the open one, else the latest. */
export function currentRequest(list: readonly ApprovalRequest[]): ApprovalRequest | null {
  return list.find((r) => r.status === 'pending') ?? list[0] ?? null;
}

/** Days left until an approval lapses (negative once past); null without validity. */
export function daysLeft(validUntil: string | null, now: Date = new Date()): number | null {
  if (!validUntil) return null;
  return Math.ceil((new Date(validUntil).getTime() - now.getTime()) / 86_400_000);
}
