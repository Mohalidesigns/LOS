/**
 * Typed adapters for the P1 conditions endpoints (CP/CS register, sign-off,
 * waivers via change requests, final pre-disbursement check).
 */
import { api, formBody, idempotencyKey, unwrap } from './client';
import type { components, operations } from './schema';

type S = components['schemas'];
type Body<O extends keyof operations> = operations[O]['requestBody'] extends { content: { 'application/json': infer B } } | undefined ? B : never;

export type Condition = S['Condition'];
export type ConditionStatus = Condition['status'];
export type PreDisbursementCheck = S['PreDisbursementCheck'];
export type CheckItem = PreDisbursementCheck['items'][number];
export type ConditionRegister = S['ConditionRegister'];
export type AddConditionBody = Body<'addCondition'>;

const idem = (key: string) => ({ 'Idempotency-Key': key });

export const conditionsApi = {
  async register(applicationId: string): Promise<ConditionRegister> {
    return unwrap(await api.GET('/api/v1/applications/{id}/conditions', { params: { path: { id: applicationId } } })).data;
  },
  async add(applicationId: string, body: AddConditionBody, key: string = idempotencyKey()): Promise<Condition> {
    return unwrap(await api.POST('/api/v1/applications/{id}/conditions', { params: { path: { id: applicationId }, header: idem(key) }, body })).data;
  },
  async satisfy(id: string, input: { note: string; reference: string | null; file: File | null }, key: string = idempotencyKey()): Promise<Condition> {
    return unwrap(
      await api.POST('/api/v1/conditions/{id}/actions/satisfy', {
        params: { path: { id }, header: idem(key) },
        body: { note: input.note, evidence_reference: input.reference ?? '' },
        bodySerializer: formBody({ note: input.note, evidence_reference: input.reference, file: input.file }),
      }),
    ).data;
  },
  async requestWaiver(id: string, reason: string, key: string = idempotencyKey()): Promise<{ id: string }> {
    return unwrap(await api.POST('/api/v1/conditions/{id}/actions/request-waiver', { params: { path: { id }, header: idem(key) }, body: { reason } })).data;
  },
  async decideWaiver(changeRequestId: string, decision: 'approve' | 'reject', reason: string, key: string = idempotencyKey()): Promise<void> {
    const params = { path: { id: changeRequestId }, header: idem(key) };
    const r = decision === 'approve'
      ? await api.POST('/api/v1/change-requests/{id}/actions/approve', { params, body: { reason } })
      : await api.POST('/api/v1/change-requests/{id}/actions/reject', { params, body: { reason } });
    unwrap(r);
  },
  async check(applicationId: string, bankCode: string, accountNo: string, key: string = idempotencyKey()): Promise<PreDisbursementCheck> {
    return unwrap(await api.POST('/api/v1/applications/{id}/pre-disbursement-checks', { params: { path: { id: applicationId }, header: idem(key) }, body: { bank_code: bankCode, account_no: accountNo } })).data;
  },
};
