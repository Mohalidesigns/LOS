import { describe, expect, it } from 'vitest';
import { APPLICATION_STATUSES } from '@/components/StatusBadge';
import { STAGES, isTerminal, rankOf, stageModel } from './stages';

const EXPECTED: Record<string, { current: string | null; interruption: 'on_hold' | 'returned' | 'closed' | null }> = {
  draft: { current: 'draft', interruption: null },
  submitted: { current: 'submitted', interruption: null },
  pre_qualified: { current: 'pre_qualified', interruption: null },
  kyc_screening: { current: 'kyc_screening', interruption: null },
  documentation: { current: 'documentation', interruption: null },
  assessment: { current: 'assessment', interruption: null },
  recommended: { current: 'recommended', interruption: null },
  approval: { current: 'approval', interruption: null },
  approved: { current: 'offer', interruption: null },
  counter_offered: { current: 'offer', interruption: null },
  offer_issued: { current: 'offer', interruption: null },
  accepted: { current: 'conditions', interruption: null },
  conditions_precedent: { current: 'conditions', interruption: null },
  ready_for_disbursement: { current: 'disbursement', interruption: null },
  disbursing: { current: 'disbursement', interruption: null },
  booked: { current: null, interruption: null }, // every step complete
  declined: { current: 'approval', interruption: 'closed' },
  expired: { current: null, interruption: 'closed' },
  on_hold: { current: null, interruption: 'on_hold' },
  returned_for_rework: { current: null, interruption: 'returned' },
  withdrawn: { current: null, interruption: 'closed' },
  cancelled: { current: null, interruption: 'closed' },
};

describe('stageModel', () => {
  it('has a mapping for all 22 canonical statuses', () => {
    expect(APPLICATION_STATUSES).toHaveLength(22);
    expect(Object.keys(EXPECTED).sort()).toEqual([...APPLICATION_STATUSES].sort());
  });

  it.each(APPLICATION_STATUSES)('places %s on the 12-step tracker', (status) => {
    const m = stageModel(status);
    const exp = EXPECTED[status]!;
    expect(m.steps).toHaveLength(STAGES.length);
    expect(m.interruption?.kind ?? null).toBe(exp.interruption);
    const current = m.steps.find((s) => s.state === 'current');
    expect(current?.key ?? null).toBe(exp.current);
    if (current) {
      const i = m.steps.indexOf(current);
      expect(m.steps.slice(0, i).every((s) => s.state === 'complete')).toBe(true);
      expect(m.steps.slice(i + 1).every((s) => s.state === 'upcoming')).toBe(true);
    }
  });

  it('marks every step complete once booked', () => {
    const m = stageModel('booked');
    expect(m.done).toBe(true);
    expect(m.steps.every((s) => s.state === 'complete')).toBe(true);
  });

  it('anchors on-hold and returned cases at resume_to / return_to', () => {
    const hold = stageModel('on_hold', { resumeTo: 'documentation' });
    expect(hold.interruption).toEqual({ kind: 'on_hold', status: 'on_hold', label: 'On hold' });
    expect(hold.steps[hold.currentIndex]?.key).toBe('documentation');
    const back = stageModel('returned_for_rework', { returnTo: 'kyc_screening' });
    expect(back.steps[back.currentIndex]?.key).toBe('kyc_screening');
    expect(back.steps.filter((s) => s.state === 'complete').map((s) => s.key)).toEqual(['draft', 'submitted', 'pre_qualified']);
  });

  it('places a closed case at its last active stage when history is known', () => {
    const m = stageModel('withdrawn', { lastActive: 'assessment' });
    expect(m.interruption?.label).toBe('Withdrawn');
    expect(m.steps[m.currentIndex]?.key).toBe('assessment');
  });

  it('degrades gracefully for unknown statuses', () => {
    const m = stageModel('something_new');
    expect(m.currentIndex).toBe(-1);
    expect(m.interruption).toBeNull();
  });

  it('ranks like the backend and knows terminal statuses', () => {
    expect(rankOf('draft')).toBe(0);
    expect(rankOf('booked')).toBe(15);
    expect(rankOf('on_hold')).toBeNull();
    expect(['booked', 'declined', 'expired', 'withdrawn', 'cancelled'].every(isTerminal)).toBe(true);
    expect(isTerminal('on_hold')).toBe(false);
  });
});
