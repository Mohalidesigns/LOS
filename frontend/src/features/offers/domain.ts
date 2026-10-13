/**
 * Pure offer logic (OFR-01/02): status and role presentation, which offer is
 * current, what the officer can do next and why not, and form rules that
 * mirror the API. No React; unit-tested.
 */
import type { Offer, OfferSignatory } from '@/api/offers';
import type { Tone } from '@/components/StatusBadge';

export const OFFER_PERM = 'offer:prepare';

export const OFFER_STATUS_META: Record<Offer['status'], { label: string; tone: Tone }> = {
  issued: { label: 'Issued', tone: 'info' },
  accepted: { label: 'Accepted', tone: 'success' },
  rejected: { label: 'Rejected by customer', tone: 'danger' },
  negotiating: { label: 'Back to credit (negotiation)', tone: 'warning' },
  expired: { label: 'Expired', tone: 'neutral' },
  superseded: { label: 'Superseded', tone: 'neutral' },
};

export const ROLE_LABEL: Record<OfferSignatory['role'], string> = {
  primary: 'Borrower',
  joint: 'Joint borrower',
  guarantor: 'Guarantor',
  co_signer: 'Co-signer',
  bank: 'Bank signatory',
};

/** The live offer if there is one, else the newest. */
export function currentOffer(list: readonly Offer[]): Offer | null {
  return list.find((o) => o.status === 'issued') ?? list[0] ?? null;
}

/** Why the officer cannot issue an offer now; null when they can. */
export function issueReason(status: string, permissions: ReadonlySet<string>, hasLive: boolean): string | null {
  if (status !== 'approved' && status !== 'counter_offered') return 'An offer is issued once the application is approved or counter-offered.';
  if (!permissions.has(OFFER_PERM)) return `Requires the ${OFFER_PERM} permission.`;
  if (hasLive) return 'There is already a live offer; re-issue it instead.';
  return null;
}

/** Why the officer cannot work on an offer (sign, deliver, accept, decline); null when they can. */
export function workReason(offer: Pick<Offer, 'status' | 'is_expired'>, permissions: ReadonlySet<string>): string | null {
  if (offer.status !== 'issued') return 'This offer is closed.';
  if (offer.is_expired) return 'The offer has expired; it can no longer be signed or accepted.';
  if (!permissions.has(OFFER_PERM)) return `Requires the ${OFFER_PERM} permission.`;
  return null;
}

export function unsigned(signatories: readonly OfferSignatory[]): OfferSignatory[] {
  return signatories.filter((s) => !s.signed);
}

export function acceptReason(offer: Pick<Offer, 'status' | 'is_expired' | 'signatories'>, permissions: ReadonlySet<string>): string | null {
  const base = workReason(offer, permissions);
  if (base) return base;
  const left = unsigned(offer.signatories);
  return left.length > 0 ? `Still to sign: ${left.map((s) => s.name).join(', ')}.` : null;
}

export function reissueReason(offer: Pick<Offer, 'status' | 'is_expired' | 'signatories'>, permissions: ReadonlySet<string>): string | null {
  const base = workReason(offer, permissions);
  if (base) return base;
  return offer.signatories.some((s) => s.signed) ? 'Signatures are recorded; decline and route back to credit instead.' : null;
}

/** Signing time must be between the offer date and now (the API checks the same). */
export function signedAtError(value: string, issuedAt: string, now: Date = new Date()): string | null {
  if (!value) return 'Enter when it was signed.';
  const t = new Date(value).getTime();
  if (Number.isNaN(t)) return 'Enter a valid date and time.';
  if (t > now.getTime()) return 'The signing time cannot be in the future.';
  if (t < Math.floor(new Date(issuedAt).getTime() / 1000) * 1000) return 'The signing time cannot be before the offer was issued.';
  return null;
}

const CODE = /^[A-Z][A-Z0-9_]{1,47}$/;

export function declineErrors(reasonCode: string, note: string): { reasonCode?: string; note?: string } {
  const e: { reasonCode?: string; note?: string } = {};
  if (!CODE.test(reasonCode.trim().toUpperCase())) e.reasonCode = 'Enter a reason code in UPPER_SNAKE_CASE, e.g. CUST_RATE.';
  if (note.trim().length < 10) e.note = 'Describe what the customer said (10 characters or more).';
  return e;
}

/** Days left until the offer lapses (negative once past). */
export function daysLeft(validUntil: string, now: Date = new Date()): number {
  return Math.ceil((new Date(validUntil).getTime() - now.getTime()) / 86_400_000);
}

/** `datetime-local` value for "now" in the browser's zone. */
export function localNow(now: Date = new Date()): string {
  const d = new Date(now.getTime() - now.getTimezoneOffset() * 60_000);
  return d.toISOString().slice(0, 16);
}
