/**
 * Typed adapters for the P1 disbursement endpoints: instructions, release
 * (a change request decided by a checker), retry, exception queue and
 * reconciliation.
 */
import { api, idempotencyKey, unwrap } from './client';
import type { components } from './schema';

type S = components['schemas'];

export type Disbursement = S['Disbursement'];
export type DisbursementStatus = Disbursement['status'];
export type SagaStep = Disbursement['steps'][number];
export type ReconciliationRun = S['ReconciliationRun'];
export type ReconciliationBreak = S['ReconciliationBreak'];

const idem = (key: string) => ({ 'Idempotency-Key': key });

export const disbursementApi = {
  async forApplication(applicationId: string): Promise<Disbursement[]> {
    return unwrap(await api.GET('/api/v1/applications/{id}/disbursements', { params: { path: { id: applicationId } } })).data;
  },
  async create(applicationId: string, valueDate: string | null, key: string = idempotencyKey()): Promise<Disbursement> {
    return unwrap(await api.POST('/api/v1/applications/{id}/disbursements', { params: { path: { id: applicationId }, header: idem(key) }, body: { value_date: valueDate } })).data;
  },
  async cancel(id: string, reason: string, key: string = idempotencyKey()): Promise<Disbursement> {
    return unwrap(await api.POST('/api/v1/disbursements/{id}/actions/cancel', { params: { path: { id }, header: idem(key) }, body: { reason } })).data;
  },
  async retry(id: string, key: string = idempotencyKey()): Promise<Disbursement> {
    return unwrap(await api.POST('/api/v1/disbursements/{id}/actions/retry', { params: { path: { id }, header: idem(key) } })).data;
  },
  /** The checker's decision on the release change request (step-up applies). */
  async decideRelease(changeRequestId: string, decision: 'approve' | 'reject', reason: string, key: string = idempotencyKey()): Promise<void> {
    const params = { path: { id: changeRequestId }, header: idem(key) };
    const r = decision === 'approve'
      ? await api.POST('/api/v1/change-requests/{id}/actions/approve', { params, body: { reason } })
      : await api.POST('/api/v1/change-requests/{id}/actions/reject', { params, body: { reason } });
    unwrap(r);
  },
  async exceptions(): Promise<Disbursement[]> {
    return unwrap(await api.GET('/api/v1/disbursement-exceptions', {})).data;
  },
  async runs(): Promise<ReconciliationRun[]> {
    return unwrap(await api.GET('/api/v1/reconciliation-runs', {})).data;
  },
  async reconcile(key: string = idempotencyKey()): Promise<ReconciliationRun> {
    return unwrap(await api.POST('/api/v1/reconciliation-runs', { params: { header: idem(key) }, body: {} })).data;
  },
  async openBreaks(): Promise<ReconciliationBreak[]> {
    return unwrap(await api.GET('/api/v1/reconciliation-breaks', {})).data;
  },
  async resolveBreak(id: string, note: string, key: string = idempotencyKey()): Promise<ReconciliationBreak> {
    return unwrap(await api.POST('/api/v1/reconciliation-breaks/{id}/actions/resolve', { params: { path: { id }, header: idem(key) }, body: { note } })).data;
  },
};
