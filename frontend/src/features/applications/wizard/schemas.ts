/**
 * Zod schemas for the new-application wizard, mirroring the backend rules
 * (CreateParty / CreateApplication validation, IdentityType formats) so most
 * mistakes are caught before the round trip. The server stays authoritative:
 * its 422 field errors are mapped back onto these fields.
 */
import { z } from 'zod';
import type { PartyCreate, ProductSummary } from '@/api/lending';
import { lagosDayKey } from '@/lib/format';

const trimmed = z.string().trim();
const optional = <T extends z.ZodTypeAny>(s: T) => z.union([s, z.literal('')]).optional();

/** Nigerian mobile/landline as the backend accepts it: optional +, 10–15 digits (spaces/dashes ignored). */
export const PHONE_RE = /^\+?[0-9]{10,15}$/;
export const BVN_RE = /^\d{11}$/;
export const RC_RE = /^(RC|BN|IT)?\s?\d{1,9}$/i;
export const TIN_RE = /^[0-9-]{8,15}$/;

function compact(v: string): string {
  return v.replace(/[\s-]/g, '');
}

const phone = optional(trimmed.refine((v) => PHONE_RE.test(compact(v)), 'Enter a phone number of 10 to 15 digits, for example 08031234567.'));
const email = optional(trimmed.email('Enter a valid email address.').max(254));

/** Today in Africa/Lagos, YYYY-MM-DD (the backend compares dates, not instants). */
export function todayLagos(now: Date = new Date()): string {
  return lagosDayKey(now);
}

export const individualSchema = z
  .object({
    first_name: trimmed.min(1, 'Enter the first name.').max(100),
    middle_name: optional(trimmed.max(100)),
    last_name: trimmed.min(1, 'Enter the last name.').max(100),
    gender: z.enum(['', 'female', 'male', 'other', 'undisclosed']).optional(),
    date_of_birth: trimmed.regex(/^\d{4}-\d{2}-\d{2}$/, 'Enter the date of birth.'),
    nationality: trimmed.regex(/^[A-Z]{2}$/, 'Use a two-letter country code, for example NG.'),
    phone,
    email,
    bvn: optional(trimmed.refine((v) => BVN_RE.test(compact(v)), 'A BVN must be 11 digits.')),
    nin: optional(trimmed.refine((v) => BVN_RE.test(compact(v)), 'A NIN must be 11 digits.')),
  })
  .superRefine((v, ctx) => {
    if (v.date_of_birth && v.date_of_birth >= todayLagos()) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['date_of_birth'], message: 'The date of birth must be before today.' });
    }
  });

export const companySchema = z
  .object({
    company_name: trimmed.min(1, 'Enter the registered company name.').max(300),
    registration_number: trimmed.min(1, 'Enter the CAC registration number.').regex(RC_RE, 'Use the CAC number, for example RC1234567.'),
    incorporation_date: optional(trimmed.regex(/^\d{4}-\d{2}-\d{2}$/, 'Enter a valid date.')),
    sector: optional(trimmed.max(64)),
    phone,
    email,
    tin: optional(trimmed.regex(TIN_RE, 'A TIN is 8 to 15 digits (dashes allowed).')),
    address_line1: optional(trimmed.max(300)),
    city: optional(trimmed.max(100)),
    state: optional(trimmed.max(100)),
  })
  .superRefine((v, ctx) => {
    if (v.incorporation_date && v.incorporation_date > todayLagos()) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['incorporation_date'], message: 'The incorporation date cannot be in the future.' });
    }
  });

export type IndividualValues = z.infer<typeof individualSchema>;
export type CompanyValues = z.infer<typeof companySchema>;

const blank = (v: string | undefined): string | null => (v === undefined || v.trim() === '' ? null : v.trim());

export function individualToCreate(v: IndividualValues, orgUnitId: string | null = null): PartyCreate {
  const identities: NonNullable<PartyCreate['identities']> = [];
  const bvn = blank(v.bvn);
  const nin = blank(v.nin);
  if (bvn) identities.push({ type: 'bvn', value: compact(bvn) });
  if (nin) identities.push({ type: 'nin', value: compact(nin) });
  return {
    type: 'individual',
    org_unit_id: orgUnitId,
    first_name: v.first_name.trim(),
    middle_name: blank(v.middle_name),
    last_name: v.last_name.trim(),
    gender: blank(v.gender),
    date_of_birth: v.date_of_birth,
    nationality: v.nationality,
    phone: blank(v.phone) === null ? null : compact(v.phone ?? ''),
    email: blank(v.email),
    identities,
  };
}

export function companyToCreate(v: CompanyValues, orgUnitId: string | null = null): PartyCreate {
  const line1 = blank(v.address_line1);
  const city = blank(v.city);
  const state = blank(v.state);
  return {
    type: 'limited_company',
    org_unit_id: orgUnitId,
    company_name: v.company_name.trim(),
    registration_number: v.registration_number.replace(/\s+/g, '').toUpperCase(),
    incorporation_date: blank(v.incorporation_date),
    sector: blank(v.sector),
    phone: blank(v.phone) === null ? null : compact(v.phone ?? ''),
    email: blank(v.email),
    tin: blank(v.tin),
    address: line1 || city || state ? { line1: line1 ?? '', city: city ?? '', state: state ?? '', country: 'NG' } : null,
  };
}

/** Body for the live dedupe check (POST /parties/actions/match) from whatever is typed so far. */
export function matchQuery(kind: 'individual' | 'limited_company', v: Partial<IndividualValues & CompanyValues>) {
  const name = kind === 'individual' ? [v.first_name, v.last_name].filter(Boolean).join(' ').trim() : (v.company_name ?? '').trim();
  const out: { name?: string; phone?: string; email?: string; registration_number?: string; tin?: string; identities?: { type: string; value: string }[] } = {};
  if (name.length >= 3) out.name = name;
  if (v.phone && PHONE_RE.test(compact(v.phone))) out.phone = compact(v.phone);
  if (v.email && z.string().email().safeParse(v.email).success) out.email = v.email.trim();
  if (kind === 'limited_company' && v.registration_number && RC_RE.test(v.registration_number)) out.registration_number = v.registration_number.replace(/\s+/g, '').toUpperCase();
  if (kind === 'limited_company' && v.tin && TIN_RE.test(v.tin)) out.tin = v.tin;
  if (kind === 'individual' && v.bvn && BVN_RE.test(compact(v.bvn))) out.identities = [{ type: 'bvn', value: compact(v.bvn) }];
  return Object.keys(out).length > 0 ? out : null;
}

export const REPAYMENT_FREQUENCIES = ['monthly', 'quarterly', 'weekly', 'bullet'] as const;

/** Decimal string compare without floats (both non-negative, up to 4 dp). */
export function compareDecimal(a: string, b: string): number {
  const norm = (s: string) => {
    const [i = '0', f = ''] = s.trim().split('.');
    return { i: i.replace(/^0+(?=\d)/, ''), f: f.padEnd(4, '0').slice(0, 4) };
  };
  const x = norm(a);
  const y = norm(b);
  if (x.i.length !== y.i.length) return x.i.length < y.i.length ? -1 : 1;
  if (x.i !== y.i) return x.i < y.i ? -1 : 1;
  if (x.f !== y.f) return x.f < y.f ? -1 : 1;
  return 0;
}

/** Terms validated against the chosen product's amount and tenor ranges. */
export function termsSchema(product: Pick<ProductSummary, 'amount' | 'tenor_months' | 'currency'>) {
  const min = product.amount.min.amount;
  const max = product.amount.max.amount;
  return z.object({
    legal_entity_id: z.string().min(1, 'Choose the legal entity.'),
    org_unit_id: z.string().min(1, 'Choose the branch.'),
    requested_amount: trimmed
      .transform((v) => v.replace(/,/g, ''))
      .refine((v) => /^\d{1,16}(\.\d{1,2})?$/.test(v), 'Enter an amount in naira, for example 5000000 or 5000000.50.')
      .refine((v) => !/^\d{1,16}(\.\d{1,2})?$/.test(v) || (compareDecimal(v, min) >= 0 && compareDecimal(v, max) <= 0), `The amount must be between the product minimum and maximum.`),
    tenor_months: z.coerce
      .number({ invalid_type_error: 'Enter the tenor in months.' })
      .int('Enter whole months.')
      .min(product.tenor_months.min, `The tenor must be at least ${product.tenor_months.min} months.`)
      .max(product.tenor_months.max, `The tenor must be at most ${product.tenor_months.max} months.`),
    purpose: trimmed.min(3, 'Describe the purpose of the loan.').max(500),
    repayment_frequency: z.enum(REPAYMENT_FREQUENCIES),
  });
}

export type TermsValues = z.infer<ReturnType<typeof termsSchema>>;
export type TermsInput = z.input<ReturnType<typeof termsSchema>>;
