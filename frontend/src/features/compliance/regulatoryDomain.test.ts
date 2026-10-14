import { describe, expect, it } from 'vitest';
import type { RegulatoryRuleRow } from '@/api/regulatory';
import { citationLine, orderRules, parameterSummary } from './regulatoryDomain';

const row = (code: string, inForce: boolean, from = '2020-01-01', parameters: Record<string, unknown> | null = null): RegulatoryRuleRow => ({
  code,
  title: code,
  kind: parameters ? 'parameter' : 'obligation',
  parameters,
  citation: { instrument: 'Act' },
  effective_from: from,
  effective_to: null,
  verification_status: 'unverified',
  verified_by: null,
  verified_on: null,
  note: null,
  in_force: inForce,
});

describe('regulatory register presentation', () => {
  it('summarises parameters and citations', () => {
    expect(parameterSummary(row('single_obligor_limit', true, '2020-01-01', { limit_percent: '20' }))).toBe("20% of shareholders' funds unimpaired");
    expect(parameterSummary(row('origination_record_retention', true, '2020-01-01', { years: 5 }))).toBe('5 years after booking');
    expect(parameterSummary(row('cdd_aml', true))).toBeNull();
    expect(citationLine({ instrument: 'BOFIA 2020', section: 's.20' })).toBe('BOFIA 2020 · s.20');
  });

  it('puts rules in force first', () => {
    expect(orderRules([row('b', false), row('a', true), row('c', true)]).map((r) => r.code)).toEqual(['a', 'c', 'b']);
  });
});
