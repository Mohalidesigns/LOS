import { infiniteQueryOptions, queryOptions, useMutation, useQueryClient, type QueryClient } from '@tanstack/react-query';
import { applicationsApi, documentsApi, productsApi, type Application, type ApplicationFilters, type WithEtag } from '@/api/lending';
import { isApiProblem, type ApiProblem } from '@/api/problem';

export const appKeys = {
  all: ['applications'] as const,
  lists: ['applications', 'list'] as const,
  list: (f: ApplicationFilters) => ['applications', 'list', f] as const,
  stats: ['applications', 'stats'] as const,
  case: (id: string) => ['applications', 'case', id] as const,
  detail: (id: string) => ['applications', 'case', id, 'detail'] as const,
  kyc: (id: string) => ['applications', 'case', id, 'kyc'] as const,
  timeline: (id: string) => ['applications', 'case', id, 'timeline'] as const,
  checklist: (id: string) => ['applications', 'case', id, 'checklist'] as const,
  documents: (id: string) => ['applications', 'case', id, 'documents'] as const,
};

export const statsQuery = queryOptions({ queryKey: appKeys.stats, queryFn: () => applicationsApi.stats(), staleTime: 30_000 });

export const productsQuery = queryOptions({ queryKey: ['products'], queryFn: () => productsApi.list(), staleTime: 5 * 60_000 });

export function applicationsInfinite(filters: ApplicationFilters) {
  return infiniteQueryOptions({
    queryKey: appKeys.list(filters),
    queryFn: ({ pageParam }) => applicationsApi.list({ ...filters, after: pageParam }),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => (last.meta.page.has_more ? last.meta.page.next_cursor : null),
  });
}

export const applicationQuery = (id: string) => queryOptions({ queryKey: appKeys.detail(id), queryFn: () => applicationsApi.get(id) });
export const kycQuery = (id: string) => queryOptions({ queryKey: appKeys.kyc(id), queryFn: () => applicationsApi.kyc(id) });
export const timelineQuery = (id: string) => queryOptions({ queryKey: appKeys.timeline(id), queryFn: () => applicationsApi.timeline(id) });
export const checklistQuery = (id: string) => queryOptions({ queryKey: appKeys.checklist(id), queryFn: () => documentsApi.checklist(id) });
export const documentsQuery = (id: string) => queryOptions({ queryKey: appKeys.documents(id), queryFn: () => documentsApi.list(id) });

/** A 412 on If-Match: someone else changed the application since it was read. */
export function isStaleProblem(e: unknown): e is ApiProblem {
  return isApiProblem(e) && (e.status === 412 || e.is('stale-resource') || e.is('precondition-failed'));
}

/** After any case mutation: the server may auto-advance the status, so refetch the whole case + lists. */
export async function refreshCase(qc: QueryClient, id: string): Promise<void> {
  await Promise.all([
    qc.invalidateQueries({ queryKey: appKeys.case(id) }),
    qc.invalidateQueries({ queryKey: appKeys.lists }),
    qc.invalidateQueries({ queryKey: appKeys.stats }),
  ]);
}

export type IfMatchRunner<V> = (etag: string, vars: V) => Promise<WithEtag<Application>>;

/**
 * Mutation that sends If-Match with the ETag from the cached GET. On success
 * the response (and its new ETag) is cached, then the case is refetched. On
 * 412 it calls `onStale` (the case shows "This application changed — reload")
 * and keeps whatever the user typed: nothing is reset here.
 */
export function useIfMatchMutation<V>(applicationId: string, run: IfMatchRunner<V>, opts: { onStale?: () => void; onSuccess?: (app: Application) => void } = {}) {
  const qc = useQueryClient();
  return useMutation<WithEtag<Application>, Error, V>({
    mutationFn: async (vars) => {
      const current = qc.getQueryData<WithEtag<Application>>(appKeys.detail(applicationId));
      if (!current) throw new Error('The application is not loaded yet.');
      return run(current.etag, vars);
    },
    onSuccess: async (res) => {
      qc.setQueryData(appKeys.detail(applicationId), res);
      opts.onSuccess?.(res.data);
      await refreshCase(qc, applicationId);
    },
    onError: (e) => {
      if (isStaleProblem(e)) opts.onStale?.();
    },
  });
}
