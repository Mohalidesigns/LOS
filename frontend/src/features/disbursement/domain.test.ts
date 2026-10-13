import { describe, expect, it } from 'vitest';
import type { Disbursement } from '@/api/disbursement';
import { currentInstruction, isRunning, makeReason, releaseReason, retryReason, stepLabel } from './domain';

const d = (over: Partial<Disbursement>): Disbursement => ({ status: 'pending_release', made_by: { id: 'maker', name: 'M' }, ...over }) as Disbursement;

describe('instructions', () => {
  it('picks the live instruction, else the newest', () => {
    expect(currentInstruction([d({ id: 'b', status: 'compensated' }), d({ id: 'a', status: 'booked' })])?.id).toBe('a');
    expect(currentInstruction([d({ id: 'b', status: 'cancelled' })])?.id).toBe('b');
    expect(currentInstruction([])).toBeNull();
  });

  it('lets the maker make one live instruction once the case is ready', () => {
    const make = new Set(['disbursement:make']);
    expect(makeReason('conditions_precedent', make, null)).toMatch(/ready for disbursement/);
    expect(makeReason('ready_for_disbursement', make, d({}))).toMatch(/already a live/);
    expect(makeReason('disbursing', make, d({ status: 'compensated' }))).toBeNull();
    expect(makeReason('ready_for_disbursement', new Set(), null)).toMatch(/disbursement:make/);
  });

  it('mirrors four-eyes for release and gates retry', () => {
    const check = new Set(['disbursement:check']);
    expect(releaseReason(d({}), check, 'maker')).toMatch(/different person/);
    expect(releaseReason(d({}), check, 'someone')).toBeNull();
    expect(releaseReason(d({ status: 'booked' }), check, 'someone')).toMatch(/awaiting release/);
    expect(retryReason(d({ status: 'failed_intervention' }), new Set(['disbursement:retry']))).toBeNull();
    expect(retryReason(d({ status: 'compensated' }), new Set(['disbursement:retry']))).toMatch(/failed/);
  });

  it('labels steps and knows when the saga is still running', () => {
    expect(stepLabel({ step: 'schedule_check' })).toBe('Schedule matches the offer');
    expect(stepLabel({ step: 'mystery_step' })).toBe('mystery step');
    expect(isRunning(d({ status: 'pending_cba' }))).toBe(true);
    expect(isRunning(d({ status: 'booked' }))).toBe(false);
  });
});
