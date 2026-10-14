/** Typed adapters for jurisdiction packs: rule register, draft packs, regulatory limit checks (FR-CMP-001/003/023). */
import { api, unwrap } from './client';
import type { components } from './schema';

type S = components['schemas'];

export type RegulatoryRegister = S['RegulatoryRegister'];
export type RegulatoryRuleRow = RegulatoryRegister['rules'][number];
export type RegulatoryLimitCheck = S['RegulatoryLimitCheck'];

export const regulatoryApi = {
  async register(legalEntityId: string): Promise<RegulatoryRegister> {
    return unwrap(await api.GET('/api/v1/legal-entities/{id}/regulatory-rules', { params: { path: { id: legalEntityId } } })).data;
  },
  async limitChecks(applicationId: string): Promise<RegulatoryLimitCheck[]> {
    return unwrap(await api.GET('/api/v1/applications/{id}/regulatory-limit-checks', { params: { path: { id: applicationId } } })).data;
  },
};
