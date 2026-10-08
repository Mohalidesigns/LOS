import { describe, expect, it } from 'vitest';
import { companySchema, companyToCreate, compareDecimal, individualSchema, individualToCreate, matchQuery, termsSchema, todayLagos } from './schemas';

const person = { first_name: 'Funke', last_name: 'Adebayo', date_of_birth: '1980-05-17', nationality: 'NG', phone: '0803 123 4567', bvn: '22212345678' };

function errorsOf(r: { success: boolean; error?: { issues: { path: (string | number)[]; message: string }[] } }) {
  return Object.fromEntries((r.error?.issues ?? []).map((i) => [i.path.join('.'), i.message]));
}

describe('individualSchema', () => {
  it('accepts a valid individual', () => {
    expect(individualSchema.safeParse(person).success).toBe(true);
  });

  it('requires names and a date of birth before today (Lagos)', () => {
    const r = individualSchema.safeParse({ ...person, first_name: '', date_of_birth: todayLagos() });
    expect(errorsOf(r)).toMatchObject({ first_name: 'Enter the first name.', date_of_birth: 'The date of birth must be before today.' });
  });

  it('checks BVN = 11 digits and the phone format like the backend', () => {
    const r = individualSchema.safeParse({ ...person, bvn: '1234', phone: '12ab' });
    expect(errorsOf(r).bvn).toBe('A BVN must be 11 digits.');
    expect(errorsOf(r).phone).toMatch(/10 to 15 digits/);
    expect(individualSchema.safeParse({ ...person, phone: '+2348031234567' }).success).toBe(true);
  });

  it('maps to PartyCreate with compacted phone and identities', () => {
    const body = individualToCreate(individualSchema.parse(person), 'ou-1');
    expect(body).toMatchObject({ type: 'individual', org_unit_id: 'ou-1', phone: '08031234567', identities: [{ type: 'bvn', value: '22212345678' }], middle_name: null, email: null });
  });
});

describe('companySchema', () => {
  const company = { company_name: 'Adebayo Foods Limited', registration_number: 'RC1234567', tin: '12345678-0001' };

  it('accepts RC / BN / IT numbers and rejects others', () => {
    expect(companySchema.safeParse(company).success).toBe(true);
    expect(companySchema.safeParse({ ...company, registration_number: 'BN 123' }).success).toBe(true);
    expect(errorsOf(companySchema.safeParse({ ...company, registration_number: 'XX99' }))).toHaveProperty('registration_number');
  });

  it('rejects a future incorporation date and a malformed TIN', () => {
    const r = companySchema.safeParse({ ...company, incorporation_date: '2999-01-01', tin: 'abc' });
    expect(Object.keys(errorsOf(r)).sort()).toEqual(['incorporation_date', 'tin']);
  });

  it('normalises the RC number and builds the address only when given', () => {
    expect(companyToCreate(companySchema.parse({ ...company, registration_number: 'rc 1234567' }))).toMatchObject({ registration_number: 'RC1234567', address: null });
    expect(companyToCreate(companySchema.parse({ ...company, city: 'Lagos' })).address).toEqual({ line1: '', city: 'Lagos', state: '', country: 'NG' });
  });
});

describe('matchQuery (live dedupe)', () => {
  it('stays silent until something identifying is typed', () => {
    expect(matchQuery('individual', { first_name: 'Fu' })).toBeNull();
  });
  it('sends name, phone and BVN once well-formed', () => {
    expect(matchQuery('individual', { first_name: 'Funke', last_name: 'Adebayo', phone: '0803 123 4567', bvn: '22212345678' })).toEqual({
      name: 'Funke Adebayo',
      phone: '08031234567',
      identities: [{ type: 'bvn', value: '22212345678' }],
    });
    expect(matchQuery('limited_company', { company_name: 'Ade', registration_number: 'rc 99' })).toEqual({ name: 'Ade', registration_number: 'RC99' });
  });
});

describe('termsSchema', () => {
  const product = { currency: 'NGN', amount: { min: { amount: '500000.0000', currency: 'NGN' }, max: { amount: '50000000.0000', currency: 'NGN' } }, tenor_months: { min: 3, max: 36 } };
  const base = { legal_entity_id: 'le', org_unit_id: 'ou', requested_amount: '5,000,000', tenor_months: '12', purpose: 'Working capital', repayment_frequency: 'monthly' };

  it('accepts terms inside the product range and strips thousands separators', () => {
    const r = termsSchema(product).parse(base);
    expect(r.requested_amount).toBe('5000000');
    expect(r.tenor_months).toBe(12);
  });

  it('rejects amounts and tenors outside the range', () => {
    const r = termsSchema(product).safeParse({ ...base, requested_amount: '50000000.01', tenor_months: '48' });
    expect(Object.keys(errorsOf(r)).sort()).toEqual(['requested_amount', 'tenor_months']);
    expect(termsSchema(product).safeParse({ ...base, requested_amount: '499999.99' }).success).toBe(false);
    expect(termsSchema(product).safeParse({ ...base, requested_amount: '500000' }).success).toBe(true);
  });

  it('requires branch, legal entity and purpose', () => {
    const r = termsSchema(product).safeParse({ ...base, legal_entity_id: '', org_unit_id: '', purpose: '' });
    expect(Object.keys(errorsOf(r)).sort()).toEqual(['legal_entity_id', 'org_unit_id', 'purpose']);
  });

  it('compares decimals without floats', () => {
    expect(compareDecimal('10000000000000001', '10000000000000000')).toBe(1);
    expect(compareDecimal('500000', '500000.0000')).toBe(0);
    expect(compareDecimal('0.5', '0.4999')).toBe(1);
  });
});
