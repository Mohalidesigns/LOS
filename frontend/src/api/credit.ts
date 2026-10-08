/**
 * Typed adapters for the P1 credit endpoints (bureau, decisions, exceptions,
 * credit memo). Types come only from the generated contract (schema.d.ts).
 */
import { api, idempotencyKey, unwrap } from './client';
import type { components, operations } from './schema';

type S = components['schemas'];
type Json<O extends keyof operations, C extends number> = operations[O]['responses'] extends Record<C, { content: { 'application/json': infer B } }> ? B : never;
type Body<O extends keyof operations> = operations[O]['requestBody'] extends { content: { 'application/json': infer B } } | undefined ? B : never;

export type BureauReport = S['BureauReport'];
export type BureauProfile = BureauReport['profile'];
export type BureauFacility = BureauProfile['facilities'][number];
export type Decision = S['Decision'];
export type DecisionOutcome = Decision['outcome'];
export type ReasonCode = Decision['reason_codes'][number];
export type DecisionStage = ReasonCode['stage'];
export type Terms = Decision['requested_terms'];
export type Affordability = Decision['affordability'];
export type PolicyException = S['PolicyException'];
export type ExceptionSeverity = PolicyException['severity'];
export type MemoSection = S['MemoSection'];
export type CreditMemo = S['CreditMemo'];
export type MemoRecommendation = CreditMemo['recommendation'];
export type CreditMemoBundle = Json<'getCreditMemo', 200>['data'];
export type ReplayResult = Json<'replayDecision', 200>['data'];
export type RaiseExceptionBody = Body<'raiseException'>;
export type SaveMemoBody = Body<'saveCreditMemo'>;

export const creditApi = {
  async bureauReports(applicationId: string): Promise<BureauReport[]> {
    return unwrap(await api.GET('/api/v1/applications/{id}/bureau-reports', { params: { path: { id: applicationId } } })).data;
  },
  async pullBureau(applicationId: string, partyId: string | null, key: string = idempotencyKey()): Promise<BureauReport> {
    return unwrap(
      await api.POST('/api/v1/applications/{id}/bureau-reports/actions/pull', {
        params: { path: { id: applicationId }, header: { 'Idempotency-Key': key } },
        body: { party_id: partyId },
      }),
    ).data;
  },
  async decisions(applicationId: string): Promise<Decision[]> {
    return unwrap(await api.GET('/api/v1/applications/{id}/decisions', { params: { path: { id: applicationId } } })).data;
  },
  async runDecision(applicationId: string, key: string = idempotencyKey()): Promise<Decision> {
    return unwrap(await api.POST('/api/v1/applications/{id}/decisions/actions/run', { params: { path: { id: applicationId }, header: { 'Idempotency-Key': key } } })).data;
  },
  async decision(id: string): Promise<Decision> {
    return unwrap(await api.GET('/api/v1/decisions/{id}', { params: { path: { id } } })).data;
  },
  async replay(id: string, key: string = idempotencyKey()): Promise<ReplayResult> {
    return unwrap(await api.POST('/api/v1/decisions/{id}/actions/replay', { params: { path: { id }, header: { 'Idempotency-Key': key } } })).data;
  },
  async raiseException(decisionId: string, body: RaiseExceptionBody, key: string = idempotencyKey()): Promise<PolicyException> {
    return unwrap(await api.POST('/api/v1/decisions/{id}/exceptions', { params: { path: { id: decisionId }, header: { 'Idempotency-Key': key } }, body })).data;
  },
  async exceptions(applicationId: string): Promise<PolicyException[]> {
    return unwrap(await api.GET('/api/v1/applications/{id}/exceptions', { params: { path: { id: applicationId } } })).data;
  },
  async memo(applicationId: string): Promise<CreditMemoBundle> {
    return unwrap(await api.GET('/api/v1/applications/{id}/credit-memo', { params: { path: { id: applicationId } } })).data;
  },
  async saveMemo(applicationId: string, body: SaveMemoBody, key: string = idempotencyKey()): Promise<CreditMemo> {
    return unwrap(await api.POST('/api/v1/applications/{id}/credit-memo', { params: { path: { id: applicationId }, header: { 'Idempotency-Key': key } }, body })).data;
  },
};
