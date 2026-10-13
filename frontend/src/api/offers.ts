/**
 * Typed adapters for the P1 offer endpoints (issue, re-issue, delivery,
 * ordered signing, acceptance, decline). Types come only from the generated contract.
 */
import { api, formBody, idempotencyKey, unwrap } from './client';
import type { components, operations } from './schema';

type S = components['schemas'];
type Body<O extends keyof operations> = operations[O]['requestBody'] extends { content: { 'application/json': infer B } } | undefined ? B : never;

export type Offer = S['Offer'];
export type OfferStatus = Offer['status'];
export type OfferKeyFacts = S['OfferKeyFacts'];
export type OfferInstalment = S['OfferInstalment'];
export type OfferSignatory = S['OfferSignatory'];
export type OfferDelivery = S['OfferDelivery'];
export type DeliverBody = Body<'deliverOffer'>;
export type SignBody = Body<'signOffer'>;
export type DeclineBody = Body<'declineOffer'>;

const idem = (key: string) => ({ 'Idempotency-Key': key });

export const offersApi = {
  async forApplication(applicationId: string): Promise<Offer[]> {
    return unwrap(await api.GET('/api/v1/applications/{id}/offers', { params: { path: { id: applicationId } } })).data;
  },
  async issue(applicationId: string, key: string = idempotencyKey()): Promise<Offer> {
    return unwrap(await api.POST('/api/v1/applications/{id}/offers', { params: { path: { id: applicationId }, header: idem(key) } })).data;
  },
  async reissue(id: string, reason: string, key: string = idempotencyKey()): Promise<Offer> {
    return unwrap(await api.POST('/api/v1/offers/{id}/actions/reissue', { params: { path: { id }, header: idem(key) }, body: { reason } })).data;
  },
  async deliver(id: string, body: DeliverBody, key: string = idempotencyKey()): Promise<Offer> {
    return unwrap(await api.POST('/api/v1/offers/{id}/deliveries', { params: { path: { id }, header: idem(key) }, body })).data;
  },
  async sign(id: string, signatoryId: string, body: SignBody, key: string = idempotencyKey()): Promise<Offer> {
    return unwrap(await api.POST('/api/v1/offers/{id}/signatories/{signatoryId}/actions/sign', { params: { path: { id, signatoryId }, header: idem(key) }, body })).data;
  },
  async accept(id: string, file: File, key: string = idempotencyKey()): Promise<Offer> {
    return unwrap(
      await api.POST('/api/v1/offers/{id}/actions/accept', {
        params: { path: { id }, header: idem(key) },
        // The contract types `file` as a binary string; the serializer sends the File itself.
        body: { file: file.name },
        bodySerializer: formBody({ file }),
      }),
    ).data;
  },
  async decline(id: string, body: DeclineBody, key: string = idempotencyKey()): Promise<Offer> {
    return unwrap(await api.POST('/api/v1/offers/{id}/actions/decline', { params: { path: { id }, header: idem(key) }, body })).data;
  },
};
