import { describe, expect, it } from 'vitest';
import type { Offer, OfferSignatory } from '@/api/offers';
import { acceptReason, currentOffer, daysLeft, declineErrors, issueReason, reissueReason, signedAtError, workReason } from './domain';

const perms = new Set(['offer:prepare']);
const sig = (name: string, signed: boolean): OfferSignatory => ({ name, signed }) as OfferSignatory;
const offer = (over: Partial<Offer>): Offer => ({ status: 'issued', is_expired: false, signatories: [], ...over }) as Offer;

describe('what the officer can do', () => {
  it('issues only on approved or counter-offered cases, with the permission, and without a live offer', () => {
    expect(issueReason('approval', perms, false)).toMatch(/approved or counter-offered/);
    expect(issueReason('approved', new Set(), false)).toMatch(/offer:prepare/);
    expect(issueReason('counter_offered', perms, true)).toMatch(/re-issue/);
    expect(issueReason('approved', perms, false)).toBeNull();
  });

  it('works on live offers only, and accepts once everyone has signed', () => {
    expect(workReason(offer({ status: 'accepted' }), perms)).toMatch(/closed/);
    expect(workReason(offer({ is_expired: true }), perms)).toMatch(/expired/);
    expect(acceptReason(offer({ signatories: [sig('Ade Ltd', true), sig('Funke', false)] }), perms)).toBe('Still to sign: Funke.');
    expect(acceptReason(offer({ signatories: [sig('Ade Ltd', true)] }), perms)).toBeNull();
    expect(reissueReason(offer({ signatories: [sig('Ade Ltd', true)] }), perms)).toMatch(/Signatures are recorded/);
  });
});

describe('forms', () => {
  it('checks the signing time against the offer date and now', () => {
    const now = new Date('2026-10-13T12:00:00Z');
    expect(signedAtError('', '2026-10-13T10:00:00Z', now)).toMatch(/Enter/);
    expect(signedAtError('2026-10-13T13:00:00Z', '2026-10-13T10:00:00Z', now)).toMatch(/future/);
    expect(signedAtError('2026-10-13T09:59:00Z', '2026-10-13T10:00:00Z', now)).toMatch(/before/);
    expect(signedAtError('2026-10-13T10:00:00Z', '2026-10-13T10:00:00.500Z', now)).toBeNull();
  });

  it('needs a reason code and a note to decline', () => {
    const short = declineErrors('rate', 'short');
    expect(short.reasonCode).toBeUndefined();
    expect(short.note).toMatch(/10 characters/);
    const badCode = declineErrors('9', 'Customer wants a lower rate');
    expect(badCode.note).toBeUndefined();
    expect(badCode.reasonCode).toMatch(/UPPER_SNAKE/);
  });
});

describe('presentation', () => {
  it('picks the live offer, else the newest', () => {
    expect(currentOffer([offer({ id: 'b', status: 'superseded' }), offer({ id: 'a' })])?.id).toBe('a');
    expect(currentOffer([offer({ id: 'b', status: 'expired' })])?.id).toBe('b');
    expect(currentOffer([])).toBeNull();
  });

  it('counts days to expiry', () => {
    expect(daysLeft('2026-11-12T12:00:00Z', new Date('2026-10-13T12:00:00Z'))).toBe(30);
  });
});
