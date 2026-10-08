import { describe, expect, it } from 'vitest';
import { alertActions } from './fourEyes';

const reviewer = new Set(['screening:review']);
const clearer = new Set(['screening:review', 'screening:clear']);

describe('alertActions (four-eyes)', () => {
  it('lets a reviewer propose on an open alert', () => {
    expect(alertActions({ status: 'open', proposed_by: null }, 'u1', reviewer)).toEqual({ propose: { visible: true, reason: null }, confirm: { visible: false, reason: null } });
  });

  it('blocks proposing without screening:review, and for the originator', () => {
    expect(alertActions({ status: 'open', proposed_by: null }, 'u1', new Set()).propose.reason).toMatch(/screening:review/);
    expect(alertActions({ status: 'open', proposed_by: null }, 'u1', reviewer, 'u1').propose.reason).toMatch(/originated/);
  });

  it('lets a DIFFERENT officer with screening:clear confirm', () => {
    expect(alertActions({ status: 'pending_confirmation', proposed_by: 'u1' }, 'u2', clearer).confirm).toEqual({ visible: true, reason: null });
  });

  it('blocks the proposer, officers without screening:clear and the originator from confirming', () => {
    expect(alertActions({ status: 'pending_confirmation', proposed_by: 'u1' }, 'u1', clearer).confirm.reason).toMatch(/different officer/);
    expect(alertActions({ status: 'pending_confirmation', proposed_by: 'u1' }, 'u2', reviewer).confirm.reason).toMatch(/screening:clear/);
    expect(alertActions({ status: 'pending_confirmation', proposed_by: 'u1' }, 'u2', clearer, 'u2').confirm.reason).toMatch(/originated/);
  });

  it('offers nothing on cleared or confirmed alerts', () => {
    for (const status of ['cleared', 'confirmed_match'] as const) {
      const a = alertActions({ status, proposed_by: 'u1' }, 'u2', clearer);
      expect(a.propose.visible || a.confirm.visible).toBe(false);
    }
  });
});
