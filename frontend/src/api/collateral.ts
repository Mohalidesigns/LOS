/**
 * Typed adapters for the P1 collateral endpoints: types, the security file
 * of an application, capture, valuations, insurance, allocations,
 * perfection steps and waivers (FR-COL-001..006, 008).
 */
import { api, formBody, idempotencyKey, unwrap } from './client';
import type { components, operations } from './schema';

type S = components['schemas'];
type Body<O extends keyof operations> = operations[O]['requestBody'] extends { content: { 'application/json': infer B } } | undefined ? B : never;

export type Collateral = S['Collateral'];
export type CollateralType = S['CollateralType'];
export type SecurityFile = S['SecurityFile'];
export type SecuredCollateral = SecurityFile['items'][number];
export type PerfectionStep = Collateral['perfection']['steps'][number];
export type InsurancePolicy = Collateral['insurance']['policies'][number];
export type CreateCollateralBody = Body<'createCollateral'>;
export type ValuationInput = Body<'recordCollateralValuation'> & {
  file: File | null;
};
export type InsuranceInput = Body<'recordCollateralInsurance'> & {
  file: File | null;
};

const idem = (key: string) => ({ 'Idempotency-Key': key });

export const collateralApi = {
  async types(): Promise<CollateralType[]> {
    return unwrap(await api.GET('/api/v1/collateral-types', {})).data;
  },
  async forApplication(applicationId: string): Promise<SecurityFile> {
    return unwrap(
      await api.GET('/api/v1/applications/{id}/collateral', {
        params: { path: { id: applicationId } },
      }),
    ).data;
  },
  async create(body: CreateCollateralBody, key: string = idempotencyKey()): Promise<Collateral> {
    return unwrap(
      await api.POST('/api/v1/collaterals', {
        params: { header: idem(key) },
        body,
      }),
    ).data;
  },
  async valuation(id: string, input: ValuationInput, key: string = idempotencyKey()): Promise<Collateral> {
    const { file, ...body } = input;
    return unwrap(
      await api.POST('/api/v1/collaterals/{id}/valuations', {
        params: { path: { id }, header: idem(key) },
        body,
        bodySerializer: formBody({ ...body, file }),
      }),
    ).data;
  },
  async insurance(id: string, input: InsuranceInput, key: string = idempotencyKey()): Promise<Collateral> {
    const { file, ...body } = input;
    return unwrap(
      await api.POST('/api/v1/collaterals/{id}/insurance-policies', {
        params: { path: { id }, header: idem(key) },
        body,
        bodySerializer: formBody({
          ...body,
          bank_interest_noted: body.bank_interest_noted ? '1' : '0',
          file,
        }),
      }),
    ).data;
  },
  async allocate(id: string, applicationId: string, amount: string, key: string = idempotencyKey()): Promise<Collateral> {
    return unwrap(
      await api.POST('/api/v1/collaterals/{id}/allocations', {
        params: { path: { id }, header: idem(key) },
        body: { application_id: applicationId, amount },
      }),
    ).data;
  },
  async updateStep(
    id: string,
    body: {
      responsible_user_id?: string | null;
      due_date?: string | null;
      start?: boolean;
    },
  ): Promise<Collateral> {
    return unwrap(
      await api.PATCH('/api/v1/perfection-steps/{id}', {
        params: { path: { id } },
        body,
      }),
    ).data;
  },
  async completeStep(id: string, input: { note: string; reference: string | null; file: File | null }, key: string = idempotencyKey()): Promise<Collateral> {
    return unwrap(
      await api.POST('/api/v1/perfection-steps/{id}/actions/complete', {
        params: { path: { id }, header: idem(key) },
        body: { note: input.note, evidence_reference: input.reference ?? '' },
        bodySerializer: formBody({
          note: input.note,
          evidence_reference: input.reference,
          file: input.file,
        }),
      }),
    ).data;
  },
  async requestWaiver(target: { kind: 'perfection_step' | 'insurance'; id: string }, reason: string, key: string = idempotencyKey()): Promise<{ id: string }> {
    const params = { path: { id: target.id }, header: idem(key) };
    const r =
      target.kind === 'insurance'
        ? await api.POST('/api/v1/collaterals/{id}/actions/request-insurance-waiver', { params, body: { reason } })
        : await api.POST('/api/v1/perfection-steps/{id}/actions/request-waiver', { params, body: { reason } });
    return unwrap(r).data;
  },
};
