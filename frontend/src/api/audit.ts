/** Typed adapters for the audit explorer, read-access log, overrides report and evidence pack (FR-AUD-006/008..011/016). */
import { api, unwrap } from './client';
import type { components } from './schema';

type S = components['schemas'];

export type AuditEvent = S['AuditEvent'];
export type ReadAccessRecord = S['ReadAccessRecord'];
export type OverrideRecord = S['OverrideRecord'];
export type IntegrationCall = S['IntegrationCall'];
export type Page<T> = { data: T[]; next: string | null };
export type OverridesPage = Page<OverrideRecord> & {
  summary: { by_type: Record<'policy_exception' | 'waiver' | 'manual_intervention', number>; by_user: { user: { id: string; name: string | null } | null; count: number }[] };
};
/** `filter[...]` query keys, already stripped of blanks (see features/audit/domain). */
export type FilterQuery = Record<string, string>;

const page = <T>(r: { data: T[]; meta: { page?: { next_cursor?: string | null } } }): Page<T> => ({ data: r.data, next: r.meta.page?.next_cursor ?? null });
const withCursor = (query: FilterQuery, after?: string | null): FilterQuery => (after ? { ...query, 'page[after]': after } : query);

export const auditApi = {
  async events(query: FilterQuery, after?: string | null): Promise<Page<AuditEvent>> {
    return page(unwrap(await api.GET('/api/v1/audit-events', { params: { query: withCursor(query, after) } })));
  },
  async readAccess(query: FilterQuery, after?: string | null): Promise<Page<ReadAccessRecord>> {
    return page(unwrap(await api.GET('/api/v1/audit/read-access', { params: { query: withCursor(query, after) } })));
  },
  async integrationCalls(query: FilterQuery, after?: string | null): Promise<Page<IntegrationCall>> {
    return page(unwrap(await api.GET('/api/v1/integration-calls', { params: { query: withCursor(query, after) } })));
  },
  async overrides(query: FilterQuery, after?: string | null): Promise<OverridesPage> {
    const r = unwrap(await api.GET('/api/v1/audit/overrides', { params: { query: withCursor(query, after) } }));
    return { data: r.data, next: (r.meta.page as { next_cursor?: string | null }).next_cursor ?? null, summary: r.meta.summary };
  },
  /** The ZIP and the manifest hash the server computed (header X-Manifest-SHA256). */
  async evidencePack(applicationId: string): Promise<{ blob: Blob; manifestSha256: string | null }> {
    const r = await api.GET('/api/v1/applications/{id}/evidence-pack', {
      params: { path: { id: applicationId }, header: { 'X-Access-Purpose': 'evidence_export' } },
      parseAs: 'blob',
    });
    return { blob: unwrap<Blob>(r), manifestSha256: r.response.headers.get('X-Manifest-SHA256') };
  },
};
