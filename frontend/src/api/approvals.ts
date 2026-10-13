/**
 * Typed adapters for the P1 approval endpoints (matrix-routed requests,
 * decision packet, votes). Types come only from the generated contract.
 */
import { api, idempotencyKey, unwrap } from './client';
import type { components, operations } from './schema';

type S = components['schemas'];
type Json<O extends keyof operations, C extends number> = operations[O]['responses'] extends Record<C, { content: { 'application/json': infer B } }> ? B : never;
type Body<O extends keyof operations> = operations[O]['requestBody'] extends { content: { 'application/json': infer B } } | undefined ? B : never;

export type ApprovalRequest = S['ApprovalRequest'];
export type ApprovalRequestStatus = ApprovalRequest['status'];
export type ApprovalLevel = S['ApprovalLevel'];
export type ApprovalVote = S['ApprovalVote'];
export type VoteDecision = ApprovalVote['decision'];
export type AuthorityBasis = ApprovalVote['authority_basis'];
export type ApprovalCondition = S['ApprovalCondition'];
export type ApprovalPacket = S['ApprovalPacket'];
export type ApprovalRequestWithPacket = Json<'getApprovalRequest', 200>['data'];
export type VoteBody = Body<'voteOnApprovalRequest'>;

export const approvalsApi = {
  async forApplication(applicationId: string): Promise<ApprovalRequest[]> {
    return unwrap(await api.GET('/api/v1/applications/{id}/approval-requests', { params: { path: { id: applicationId } } })).data;
  },
  async inbox(): Promise<ApprovalRequest[]> {
    return unwrap(await api.GET('/api/v1/approval-requests', {})).data;
  },
  async get(id: string): Promise<ApprovalRequestWithPacket> {
    return unwrap(await api.GET('/api/v1/approval-requests/{id}', { params: { path: { id } } })).data;
  },
  async vote(id: string, body: VoteBody, key: string = idempotencyKey()): Promise<ApprovalRequest> {
    return unwrap(await api.POST('/api/v1/approval-requests/{id}/actions/vote', { params: { path: { id }, header: { 'Idempotency-Key': key } }, body })).data;
  },
};
