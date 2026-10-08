import { queryOptions, useQuery, type QueryClient } from '@tanstack/react-query';
import { authApi, type EffectiveAccess, type Me, type Scope } from '@/api/auth';

/** Server state for "who am I and what may I do". Held in memory only (TanStack Query cache). */
export const sessionKeys = {
  me: ['session', 'me'] as const,
  access: ['session', 'effective-access'] as const,
};

export const meQuery = queryOptions({
  queryKey: sessionKeys.me,
  queryFn: () => authApi.me(),
  staleTime: 5 * 60_000,
  retry: false,
});

export const accessQuery = queryOptions({
  queryKey: sessionKeys.access,
  queryFn: () => authApi.effectiveAccess(),
  staleTime: 5 * 60_000,
  retry: false,
});

export type Session = { me: Me; access: EffectiveAccess; permissions: ReadonlySet<string> };

/** Deny by default: only permissions the server lists are granted. */
export function permissionSet(access: EffectiveAccess | undefined): ReadonlySet<string> {
  return new Set((access?.permissions ?? []).map((p) => p.permission));
}

export function hasAny(permissions: ReadonlySet<string>, required: readonly string[]): boolean {
  return required.some((p) => permissions.has(p));
}

/** Loader helper: resolves the session or throws the ApiProblem (401 → caller redirects). */
export async function loadSession(qc: QueryClient): Promise<Session> {
  // staleTime 'static' = serve from cache when present (ensureQueryData semantics).
  const [me, access] = await Promise.all([qc.query({ ...meQuery, staleTime: 'static' }), qc.query({ ...accessQuery, staleTime: 'static' })]);
  return { me, access, permissions: permissionSet(access) };
}

export function useSession(): Session {
  const me = useQuery(meQuery);
  const access = useQuery(accessQuery);
  if (!me.data || !access.data) {
    // The authenticated layout's loader guarantees both are cached before render.
    throw new Error('useSession used outside an authenticated route');
  }
  return { me: me.data, access: access.data, permissions: permissionSet(access.data) };
}

export function usePermissions(): ReadonlySet<string> {
  const access = useQuery(accessQuery);
  return permissionSet(access.data);
}

/** Human summary of the scope of a user's assignments ("All legal entities", "2 products" …). */
export function describeScope(access: EffectiveAccess | undefined): string {
  const assignments = access?.assignments ?? [];
  if (assignments.length === 0) return 'No active assignments';
  const parts = new Set<string>();
  for (const a of assignments) {
    const s: Scope = a.scope ?? {};
    const restricted: string[] = [];
    if (s.legal_entity_ids?.length) restricted.push(`${s.legal_entity_ids.length} legal entit${s.legal_entity_ids.length === 1 ? 'y' : 'ies'}`);
    if (s.org_unit_id) restricted.push('1 org unit');
    if (s.product_ids?.length) restricted.push(`${s.product_ids.length} product${s.product_ids.length === 1 ? '' : 's'}`);
    if (s.currencies?.length) restricted.push(s.currencies.join(', '));
    if (s.segments?.length) restricted.push(s.segments.join(', '));
    parts.add(restricted.length ? restricted.join(' · ') : 'Whole institution');
  }
  return [...parts].join('; ');
}

export function roleLabel(code: string): string {
  return code.replace(/[_-]+/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
