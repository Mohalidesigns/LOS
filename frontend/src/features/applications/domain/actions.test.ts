import { describe, expect, it } from 'vitest';
import { APPLICATION_STATUSES } from '@/components/StatusBadge';
import { canAmend, canClose, menuActions, primaryAction, returnTargets } from './actions';

const originator = new Set(['application:view', 'application:originate']);
const recommender = new Set(['application:view', 'application:recommend']);
const viewer = new Set(['application:view']);

describe('primaryAction', () => {
  it('chooses by status: submit in draft, resubmit when returned, resume on hold, recommend in assessment', () => {
    expect(primaryAction('draft', originator)).toEqual({ action: 'submit', label: 'Submit application', disabledReason: null });
    expect(primaryAction('returned_for_rework', originator)?.action).toBe('resubmit');
    expect(primaryAction('on_hold', originator)?.action).toBe('resume');
    expect(primaryAction('assessment', recommender)).toEqual({ action: 'recommend', label: 'Recommend', disabledReason: null });
  });

  it('has no primary action in stages driven by other modules or closed', () => {
    for (const s of ['submitted', 'pre_qualified', 'kyc_screening', 'documentation', 'approval', 'booked', 'declined', 'withdrawn']) {
      expect(primaryAction(s, originator)).toBeNull();
    }
  });

  it('returns the action disabled with the missing permission (deny by default)', () => {
    expect(primaryAction('draft', viewer)?.disabledReason).toBe('Requires the application:originate permission.');
    expect(primaryAction('assessment', originator)?.disabledReason).toBe('Requires the application:recommend permission.');
  });

  it('never throws for any canonical status', () => {
    for (const s of APPLICATION_STATUSES) expect(() => primaryAction(s, originator)).not.toThrow();
  });
});

describe('menuActions', () => {
  it('offers hold / withdraw / cancel to an originator in documentation, return to a recommender', () => {
    const o = menuActions('documentation', originator);
    expect(o.map((a) => a.action)).toEqual(['hold', 'return', 'withdraw', 'cancel']);
    expect(o.find((a) => a.action === 'hold')?.disabledReason).toBeNull();
    expect(o.find((a) => a.action === 'return')?.disabledReason).toMatch(/application:recommend/);
    const r = menuActions('documentation', recommender);
    expect(r.find((a) => a.action === 'return')?.disabledReason).toBeNull();
    expect(r.find((a) => a.action === 'withdraw')?.disabledReason).toMatch(/application:originate/);
  });

  it('only allows withdraw / cancel while on hold or returned, and nothing once closed', () => {
    expect(menuActions('on_hold', originator).map((a) => a.action)).toEqual(['withdraw', 'cancel']);
    expect(menuActions('returned_for_rework', originator).map((a) => a.action)).toEqual(['withdraw', 'cancel']);
    expect(menuActions('booked', originator)).toEqual([]);
    expect(menuActions('withdrawn', originator)).toEqual([]);
    expect(menuActions('disbursing', originator)).toEqual([]);
  });

  it('does not offer rework from draft', () => {
    expect(menuActions('draft', recommender).some((a) => a.action === 'return')).toBe(false);
  });
});

describe('rules', () => {
  it('returns only to earlier stages', () => {
    expect(returnTargets('draft')).toEqual([]);
    expect(returnTargets('documentation')).toEqual(['draft', 'kyc_screening']);
    expect(returnTargets('approval')).toEqual(['draft', 'kyc_screening', 'documentation', 'assessment']);
    expect(returnTargets('on_hold')).toEqual([]);
  });

  it('amends before approval only', () => {
    expect(canAmend('draft')).toBe(true);
    expect(canAmend('recommended')).toBe(true);
    expect(canAmend('returned_for_rework')).toBe(true);
    expect(canAmend('approval')).toBe(false);
    expect(canAmend('on_hold')).toBe(false);
  });

  it('closes up to ready-for-disbursement plus hold / rework', () => {
    expect(canClose('ready_for_disbursement')).toBe(true);
    expect(canClose('disbursing')).toBe(false);
    expect(canClose('on_hold')).toBe(true);
  });
});
