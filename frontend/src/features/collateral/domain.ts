/**
 * Pure collateral logic (COL-01): status presentation, what the officer and
 * the waiver checker can do, and form rules that mirror the API.
 */
import type { Collateral, PerfectionStep } from '@/api/collateral';
import type { Tone } from '@/components/StatusBadge';

export const COLLATERAL_PERM = {
  manage: 'collateral:manage',
  waiveApprove: 'condition:waive_approve',
} as const;

export const STEP_META: Record<PerfectionStep['status'], { label: string; tone: Tone }> = {
  pending: { label: 'Pending', tone: 'warning' },
  in_progress: { label: 'In progress', tone: 'info' },
  completed: { label: 'Completed', tone: 'success' },
  waiver_pending: { label: 'Waiver awaiting approval', tone: 'info' },
  waived: { label: 'Waived', tone: 'neutral' },
};

export const INSURANCE_META: Record<Collateral['insurance']['status'], { label: string; tone: Tone }> = {
  not_required: { label: 'Not required', tone: 'neutral' },
  waived: { label: 'Waived', tone: 'neutral' },
  in_force: { label: 'In force', tone: 'success' },
  expiring: { label: 'Expiring soon', tone: 'warning' },
  missing: { label: 'No policy', tone: 'danger' },
  expired: { label: 'Expired', tone: 'danger' },
  not_started: { label: 'Not started', tone: 'warning' },
  interest_not_noted: { label: 'Bank interest not noted', tone: 'danger' },
};

export const VALUATION_META: Record<Collateral['valuation']['status'], { label: string; tone: Tone }> = {
  not_required: { label: 'Face value', tone: 'neutral' },
  none: { label: 'Declared, not valued', tone: 'warning' },
  current: { label: 'Valued', tone: 'success' },
  due_soon: { label: 'Re-valuation due', tone: 'warning' },
  expired: { label: 'Valuation expired', tone: 'danger' },
};

export const METHODS = [
  { value: 'market_comparison', label: 'Market comparison' },
  { value: 'income', label: 'Income capitalisation' },
  { value: 'cost', label: 'Cost / depreciated replacement' },
  { value: 'residual', label: 'Residual' },
  { value: 'desktop', label: 'Desktop' },
  { value: 'mark_to_market', label: 'Mark to market' },
] as const;

/** Nigerian state codes used by perfection checklists (FC = FCT Abuja). */
export const STATES: readonly { value: string; label: string }[] = [
  { value: 'LA', label: 'Lagos' },
  { value: 'FC', label: 'FCT Abuja' },
  { value: 'OG', label: 'Ogun' },
  { value: 'OY', label: 'Oyo' },
  { value: 'RI', label: 'Rivers' },
  { value: 'KN', label: 'Kano' },
  { value: 'KD', label: 'Kaduna' },
  { value: 'EN', label: 'Enugu' },
  { value: 'AN', label: 'Anambra' },
  { value: 'DE', label: 'Delta' },
  { value: 'ED', label: 'Edo' },
  { value: 'AK', label: 'Akwa Ibom' },
];

const CLOSED = ['declined', 'expired', 'withdrawn', 'cancelled'];

export function manageReason(appStatus: string, permissions: ReadonlySet<string>): string | null {
  if (CLOSED.includes(appStatus)) return `Security is not worked on a ${appStatus} application.`;
  if (!permissions.has(COLLATERAL_PERM.manage)) return `Requires the ${COLLATERAL_PERM.manage} permission.`;
  return null;
}

export const isOpenStep = (s: Pick<PerfectionStep, 'status'>): boolean => s.status === 'pending' || s.status === 'in_progress' || s.status === 'waiver_pending';

export function stepGroups(steps: readonly PerfectionStep[]): {
  precedent: PerfectionStep[];
  subsequent: PerfectionStep[];
} {
  return {
    precedent: steps.filter((s) => s.timing === 'precedent'),
    subsequent: steps.filter((s) => s.timing === 'subsequent'),
  };
}

/** "3 of 5" style progress across every step. */
export function perfectionProgress(steps: readonly PerfectionStep[]): {
  done: number;
  total: number;
} {
  return {
    done: steps.filter((s) => !isOpenStep(s)).length,
    total: steps.length,
  };
}

export type ThresholdTone = 'success' | 'danger' | 'neutral';

/** Pass / fail chip tone against a product threshold (null when the product sets none). */
export function thresholdTone(meets: boolean | null): ThresholdTone {
  return meets === null ? 'neutral' : meets ? 'success' : 'danger';
}

const MONEY = /^\d{1,16}(\.\d{1,4})?$/;

export function moneyError(value: string, label: string, { allowZero = false } = {}): string | undefined {
  const v = value.trim().replace(/,/g, '');
  if (!MONEY.test(v)) return `Enter ${label} as an amount, e.g. 2500000.00.`;
  if (!allowZero && Number(v) === 0) return `${label.charAt(0).toUpperCase()}${label.slice(1)} must be greater than zero.`;
  return undefined;
}

export const normaliseMoney = (value: string): string => value.trim().replace(/,/g, '');

export function captureErrors(f: {
  typeCode: string;
  description: string;
  marketValue: string;
  ownerName: string;
  ownerIsApplicant: boolean;
}): Partial<Record<'typeCode' | 'description' | 'marketValue' | 'ownerName', string>> {
  const e: Partial<Record<'typeCode' | 'description' | 'marketValue' | 'ownerName', string>> = {};
  if (!f.typeCode) e.typeCode = 'Choose the collateral type.';
  if (f.description.trim().length < 3) e.description = 'Describe the asset (address, registration, serial number).';
  const m = moneyError(f.marketValue, 'the value');
  if (m) e.marketValue = m;
  if (!f.ownerIsApplicant && f.ownerName.trim().length < 2) e.ownerName = 'Give the owner of third-party security.';
  return e;
}

export function valuationErrors(
  f: {
    valuer: string;
    date: string;
    marketValue: string;
    forcedSaleValue: string;
  },
  today: string,
): Partial<Record<'valuer' | 'date' | 'marketValue' | 'forcedSaleValue', string>> {
  const e: Partial<Record<'valuer' | 'date' | 'marketValue' | 'forcedSaleValue', string>> = {};
  if (f.valuer.trim().length < 2) e.valuer = 'Name the valuer.';
  if (!f.date) e.date = 'Give the valuation date.';
  else if (f.date > today) e.date = 'A valuation cannot be dated in the future.';
  const m = moneyError(f.marketValue, 'the market value', { allowZero: true });
  if (m) e.marketValue = m;
  if (f.forcedSaleValue.trim() !== '') {
    const fe = moneyError(f.forcedSaleValue, 'the forced-sale value', {
      allowZero: true,
    });
    if (fe) e.forcedSaleValue = fe;
    else if (!m && Number(normaliseMoney(f.forcedSaleValue)) > Number(normaliseMoney(f.marketValue))) e.forcedSaleValue = 'The forced-sale value cannot exceed the market value.';
  }
  return e;
}

export function insuranceErrors(f: { policyNo: string; insurer: string; sumInsured: string; start: string; expiry: string }): Partial<Record<'policyNo' | 'insurer' | 'sumInsured' | 'expiry', string>> {
  const e: Partial<Record<'policyNo' | 'insurer' | 'sumInsured' | 'expiry', string>> = {};
  if (!f.policyNo.trim()) e.policyNo = 'Enter the policy number.';
  if (f.insurer.trim().length < 2) e.insurer = 'Name the insurer.';
  const m = moneyError(f.sumInsured, 'the sum insured');
  if (m) e.sumInsured = m;
  if (!f.start || !f.expiry || f.expiry <= f.start) e.expiry = 'The expiry date must be after the start date.';
  return e;
}

/** Lending value still free on the item for this facility (its current allocation can be raised into it). */
export function allocatable(c: Pick<Collateral, 'allocation'>, applicationId: string): number {
  const mine = c.allocation.items.find((a) => a.application_id === applicationId && a.status === 'active');
  return Number(c.allocation.available.amount) + (mine ? Number(mine.amount.amount) : 0);
}
