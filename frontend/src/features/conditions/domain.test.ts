import { describe, expect, it } from 'vitest';
import type { Condition } from '@/api/conditions';
import { accountErrors, addErrors, canDecideWaiver, grouped, outstandingMandatory, satisfyErrors, workReason } from './domain';

const c = (over: Partial<Condition>): Condition => ({ kind: 'precedent', mandatory: true, status: 'open', waiver: null, ...over }) as Condition;
const manage = new Set(['condition:manage']);

describe('register', () => {
  it('splits CPs from CSs and finds the mandatory CPs still outstanding', () => {
    const list = [c({ id: 'a' }), c({ id: 'b', kind: 'subsequent' }), c({ id: 'd', status: 'waiver_pending' }), c({ id: 'e', status: 'satisfied' }), c({ id: 'f', mandatory: false })];
    expect(grouped(list).subsequent.map((x) => x.id)).toEqual(['b']);
    expect(outstandingMandatory(list).map((x) => x.id)).toEqual(['a', 'd']);
  });
});

describe('what you can do', () => {
  it('works conditions between acceptance and booking with the permission; CSs after booking too', () => {
    expect(workReason('approval', manage)).toMatch(/between acceptance and booking/);
    expect(workReason('conditions_precedent', new Set())).toMatch(/condition:manage/);
    expect(workReason('conditions_precedent', manage, c({ status: 'satisfied' }))).toMatch(/satisfied/);
    expect(workReason('booked', manage, c({ kind: 'subsequent' }))).toBeNull();
    expect(workReason('ready_for_disbursement', manage, c({ status: 'waiver_pending' }))).toBeNull();
  });

  it('lets only a waiver authority decide a pending waiver', () => {
    const pending = c({ status: 'waiver_pending', waiver: { change_request_id: 'cr', reason: 'r' } });
    expect(canDecideWaiver(pending, manage)).toBe(false);
    expect(canDecideWaiver(pending, new Set(['condition:waive_approve']))).toBe(true);
  });
});

describe('forms', () => {
  it('validates new conditions, sign-off evidence and the destination account', () => {
    expect(addErrors({ description: 'PG', dueDate: '2026-01-01' }, '2026-10-13')).toEqual({ description: expect.any(String) as string, dueDate: expect.any(String) as string });
    expect(satisfyErrors({ note: 'Deed received', reference: '', file: null }).evidence).toMatch(/evidence/);
    expect(satisfyErrors({ note: 'Deed received', reference: 'Lands registry 123', file: null })).toEqual({});
    expect(accountErrors('58', '123')).toEqual({ bankCode: expect.any(String) as string, accountNo: expect.any(String) as string });
    expect(accountErrors('058', '0123456789')).toEqual({});
  });
});
