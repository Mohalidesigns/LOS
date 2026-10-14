import { describe, expect, it } from 'vitest';
import type { Collateral, PerfectionStep } from '@/api/collateral';
import { allocatable, captureErrors, insuranceErrors, manageReason, perfectionProgress, stepGroups, thresholdTone, valuationErrors } from './domain';

const step = (code: string, timing: 'precedent' | 'subsequent', status: PerfectionStep['status']): PerfectionStep => ({
  id: code,
  sequence: 1,
  code,
  name: code,
  timing,
  responsible: null,
  due_date: null,
  is_overdue: false,
  status,
  evidence: null,
  completion: null,
  waiver: null,
});

describe('collateral domain', () => {
  it('explains why an officer cannot act', () => {
    expect(manageReason('approval', new Set(['collateral:manage']))).toBeNull();
    expect(manageReason('approval', new Set())).toContain('collateral:manage');
    expect(manageReason('declined', new Set(['collateral:manage']))).toContain('declined');
  });

  it('groups steps by timing and counts progress', () => {
    const steps = [step('a', 'precedent', 'completed'), step('b', 'precedent', 'waiver_pending'), step('c', 'subsequent', 'waived'), step('d', 'subsequent', 'pending')];
    expect(stepGroups(steps).precedent.map((s) => s.code)).toEqual(['a', 'b']);
    expect(perfectionProgress(steps)).toEqual({ done: 2, total: 4 });
  });

  it('maps thresholds to chip tones', () => {
    expect(thresholdTone(true)).toBe('success');
    expect(thresholdTone(false)).toBe('danger');
    expect(thresholdTone(null)).toBe('neutral');
  });

  it('validates capture, valuation and insurance like the API', () => {
    const e = captureErrors({
      typeCode: '',
      description: 'x',
      marketValue: '0',
      ownerName: '',
      ownerIsApplicant: false,
    });
    expect(Object.keys(e).sort()).toEqual(['description', 'marketValue', 'ownerName', 'typeCode']);
    expect(e.marketValue).toContain('greater than zero');
    expect(
      captureErrors({
        typeCode: 'vehicle',
        description: 'Hilux',
        marketValue: '2,000,000.00',
        ownerName: '',
        ownerIsApplicant: true,
      }),
    ).toEqual({});
    const v = valuationErrors(
      {
        valuer: 'KF',
        date: '2026-10-15',
        marketValue: '100',
        forcedSaleValue: '200',
      },
      '2026-10-14',
    );
    expect(Object.keys(v).sort()).toEqual(['date', 'forcedSaleValue']);
    expect(v.forcedSaleValue).toContain('exceed');
    expect(
      Object.keys(
        insuranceErrors({
          policyNo: 'P1',
          insurer: 'Leadway',
          sumInsured: '5000000',
          start: '2026-10-14',
          expiry: '2026-10-14',
        }),
      ),
    ).toEqual(['expiry']);
  });

  it('computes what can be allocated to a facility', () => {
    const c = {
      allocation: {
        total: { amount: '1200000.0000', currency: 'NGN' },
        available: { amount: '200000.0000', currency: 'NGN' },
        over_allocated: false,
        items: [
          {
            id: '1',
            application_id: 'A',
            application_reference: 'A',
            amount: { amount: '700000.0000', currency: 'NGN' },
            status: 'active',
          },
          {
            id: '2',
            application_id: 'B',
            application_reference: 'B',
            amount: { amount: '300000.0000', currency: 'NGN' },
            status: 'active',
          },
        ],
      },
    } as Pick<Collateral, 'allocation'>;
    expect(allocatable(c, 'A')).toBe(900000);
    expect(allocatable(c, 'C')).toBe(200000);
  });
});
