import { describe, expect, it } from 'vitest';
import type { ApprovalRequest } from '@/api/approvals';
import { currentRequest, daysLeft, EMPTY_VOTE, hasErrors, levelState, parseCodes, validateVote, voteBody, type VoteForm } from './domain';

const form = (over: Partial<VoteForm>): VoteForm => ({ ...EMPTY_VOTE, rationale: 'Within appetite and affordable.', ...over });

describe('vote form rules (mirror the API)', () => {
  it('needs a rationale for every decision', () => {
    expect(validateVote(form({ rationale: 'ok' }), '8000000').rationale).toMatch(/rationale/);
    expect(hasErrors(validateVote(form({}), '8000000'))).toBe(false);
  });

  it('needs reason codes for anything but a plain approval, in UPPER_SNAKE_CASE', () => {
    expect(validateVote(form({ decision: 'decline' }), '8000000').reasonCodes).toMatch(/Decline needs/);
    expect(validateVote(form({ decision: 'decline', reasonCodes: '9X' }), '8000000').reasonCodes).toMatch(/UPPER_SNAKE/);
    expect(parseCodes(' cond_guarantee, DEC_X;cond_guarantee ')).toEqual(['COND_GUARANTEE', 'DEC_X']);
  });

  it('needs conditions with descriptions for a conditional approval', () => {
    expect(validateVote(form({ decision: 'approve_with_conditions', reasonCodes: 'COND_X' }), '1').conditions).toMatch(/at least one/);
    const e = validateVote(form({ decision: 'approve_with_conditions', reasonCodes: 'COND_X', conditions: [{ kind: 'precedent', description: 'PG' }] }), '1');
    expect(e.conditionRows?.[0]).toMatch(/Describe/);
  });

  it('only counter-offers below the exposure', () => {
    const base = { decision: 'counter_offer' as const, reasonCodes: 'CTR_AFF', counterTenor: '18' };
    expect(validateVote(form({ ...base, counterAmount: '9,000,000' }), '8000000.0000').counterAmount).toMatch(/less than/);
    expect(validateVote(form({ ...base, counterAmount: '6000000', counterTenor: '0' }), '8000000.0000').counterTenor).toMatch(/months/);
    expect(hasErrors(validateVote(form({ ...base, counterAmount: '6,000,000' }), '8000000.0000'))).toBe(false);
  });

  it('builds the request body, dropping fields that do not apply to the decision', () => {
    expect(voteBody(form({ decision: 'approve', reasonCodes: 'x_ok', conditions: [{ kind: 'precedent', description: 'stale' }], counterAmount: '5' }))).toEqual({
      decision: 'approve',
      rationale: 'Within appetite and affordable.',
      reason_codes: ['X_OK'],
      conditions: [],
      counter_terms: null,
    });
    expect(voteBody(form({ decision: 'counter_offer', reasonCodes: 'CTR', counterAmount: '6,000,000.50', counterTenor: '18' })).counter_terms).toEqual({ amount: '6000000.50', tenor_months: 18 });
  });
});

describe('presentation', () => {
  it('labels level states', () => {
    expect(levelState({ state: 'awaiting_vote' })).toEqual({ label: 'Awaiting vote', tone: 'warning' });
    expect(levelState({ state: 'approve_with_conditions' }).label).toBe('Approve with conditions');
  });

  it('picks the open request, else the latest', () => {
    const r = (id: string, status: ApprovalRequest['status']) => ({ id, status }) as ApprovalRequest;
    expect(currentRequest([r('b', 'approved'), r('a', 'pending')])?.id).toBe('a');
    expect(currentRequest([r('b', 'expired'), r('a', 'superseded')])?.id).toBe('b');
    expect(currentRequest([])).toBeNull();
  });

  it('counts validity days left', () => {
    const now = new Date('2026-10-13T12:00:00Z');
    expect(daysLeft('2026-12-12T12:00:00Z', now)).toBe(60);
    expect(daysLeft('2026-12-12T11:00:00Z', now)).toBe(60);
    expect(daysLeft('2026-10-12T12:00:00Z', now)).toBe(-1);
    expect(daysLeft(null, now)).toBeNull();
  });
});
