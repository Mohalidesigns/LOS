import { describe, expect, it } from 'vitest';
import { endpointErrors, packageView, parseEvents } from './domain';

describe('package content', () => {
  it('reads the parts the screen shows and tolerates missing ones', () => {
    const v = packageView({
      facility: { loan_account_no: '9000000001', maturity_date: '2027-10-14' },
      documents: [{ document_id: 'd', version_id: 'v', type: 'OFFER_LETTER', title: 'Offer', filename: 'o.pdf', sha256: 'abc' }],
      conditions_subsequent: [{ condition_id: 'c', description: 'Stock report', due_date: null }],
      insurance: [{ fee_code: 'CRI', cover: 'Credit life', premium: { amount: '15000.00', currency: 'NGN' }, cover_until: '2027-10-14' }],
      notes: { collateral: 'none yet' },
    });
    expect(v.loanAccountNo).toBe('9000000001');
    expect(v.documents[0]?.type).toBe('OFFER_LETTER');
    expect(v.conditions[0]?.description).toBe('Stock report');
    expect(v.insurance[0]?.premium.amount).toBe('15000.00');
    expect(v.collateralNote).toBe('none yet');
    expect(packageView(null)).toEqual({ loanAccountNo: null, maturityDate: null, documents: [], conditions: [], insurance: [], collateralNote: null });
  });
});

describe('endpoint form', () => {
  it('requires https except for a local receiver, and valid event names', () => {
    expect(endpointErrors({ name: 'LMS', url: 'http://lms.example.com/h', events: 'facility.created' }).url).toMatch(/https/);
    expect(endpointErrors({ name: 'LMS', url: 'http://localhost:9000/h', events: 'facility.created' })).toEqual({});
    expect(endpointErrors({ name: '', url: 'nope', events: 'Facility Created' })).toEqual({ name: expect.any(String) as string, url: expect.any(String) as string, events: expect.any(String) as string });
    expect(parseEvents('facility.created, *  facility.created')).toEqual(['facility.created', '*']);
  });
});
