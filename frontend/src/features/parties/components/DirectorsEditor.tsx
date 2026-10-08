import { useState } from 'react';
import { Link } from 'react-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { UserPlus } from 'lucide-react';
import { partiesApi, type RelationshipRole } from '@/api/lending';
import { isApiProblem } from '@/api/problem';
import { Button, CardHeader, ErrorSummary, Pill, SectionError, Select, Skeleton } from '@/components';
import { formatPercent } from '@/lib/format';
import { PartyCreateForm } from './PartyCreateForm';
import { PartySearch } from './PartySearch';

export const partyKeys = {
  detail: (id: string) => ['parties', id, 'detail'] as const,
  relationships: (id: string) => ['parties', id, 'relationships'] as const,
  consents: (id: string) => ['parties', id, 'consents'] as const,
};

const ROLE_LABEL: Record<string, string> = {
  director: 'Director',
  shareholder: 'Shareholder',
  beneficial_owner: 'Beneficial owner',
  signatory: 'Signatory',
  company_secretary: 'Company secretary',
};

/** Directors, shareholders and look-through beneficial owners of a company, with inline "Add director". */
export function DirectorsEditor({ companyId, canManage, headingLevel = 3 }: { companyId: string; canManage: boolean; headingLevel?: 2 | 3 }) {
  const qc = useQueryClient();
  const rel = useQuery({ queryKey: partyKeys.relationships(companyId), queryFn: () => partiesApi.relationships(companyId) });
  const [adding, setAdding] = useState<null | 'search' | 'create'>(null);
  const [role, setRole] = useState<RelationshipRole>('director');
  const add = useMutation({
    mutationFn: (relatedId: string) => partiesApi.addRelationship(companyId, { related_party_id: relatedId, role }),
    onSuccess: async () => {
      setAdding(null);
      await qc.invalidateQueries({ queryKey: partyKeys.relationships(companyId) });
    },
  });

  return (
    <section aria-labelledby={`rel-${companyId}`} className="rounded-card border p-4">
      <CardHeader
        title="Directors and owners"
        titleId={`rel-${companyId}`}
        level={headingLevel}
        className="mb-3"
        actions={
          canManage && !adding ? (
            <Button size="sm" variant="secondary" leadingIcon={<UserPlus aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={() => setAdding('search')}>
              Add person
            </Button>
          ) : undefined
        }
      />
      {rel.isPending && <Skeleton className="h-16 w-full" />}
      {rel.isError && <SectionError error={rel.error} onRetry={() => void rel.refetch()} />}
      {rel.data && (
        <>
          {rel.data.relationships.length === 0 ? (
            <p className="text-body-sm text-secondary">No directors recorded yet. CDD for a company needs its directors and beneficial owners.</p>
          ) : (
            <ul className="divide-y divide-subtle">
              {rel.data.relationships.map((r) => (
                <li key={r.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                  <Link to={`/parties/${r.party.id}`} className="font-semibold text-link hover:underline">
                    {r.party.display_name}
                  </Link>
                  <span className="flex items-center gap-2 text-body-sm text-secondary">
                    <Pill tone="neutral">{ROLE_LABEL[r.role] ?? r.role}</Pill>
                    {r.ownership_percent && <span className="tabular">{formatPercent(Number(r.ownership_percent) / 100, 0)}</span>}
                  </span>
                </li>
              ))}
            </ul>
          )}
          {rel.data.beneficial_owners.length > 0 && (
            <div className="mt-3">
              <h4 className="text-body-sm font-semibold text-primary">Beneficial owners (look-through)</h4>
              <ul className="mt-1 space-y-1">
                {rel.data.beneficial_owners.map((b) => (
                  <li key={b.party_id} className="flex justify-between text-body-sm">
                    <span>{b.display_name}</span>
                    <span className="tabular text-secondary">
                      {formatPercent(Number(b.effective_percent) / 100, 1)} · {b.source === 'look_through' ? 'look-through' : 'declared'}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </>
      )}
      {adding && (
        <div className="mt-4 space-y-3 rounded-card bg-muted p-4">
          <Select
            label="Role"
            value={role}
            onChange={(e) => setRole(e.target.value as RelationshipRole)}
            options={[
              { value: 'director', label: 'Director' },
              { value: 'signatory', label: 'Signatory' },
              { value: 'company_secretary', label: 'Company secretary' },
            ]}
            className="max-w-[16rem]"
          />
          {add.error && <ErrorSummary problem={isApiProblem(add.error) ? add.error : null} messages={isApiProblem(add.error) ? undefined : [add.error.message]} />}
          {adding === 'search' ? (
            <>
              <PartySearch typeFilter="individual" selectLabel="Add" excludeIds={rel.data?.relationships.map((r) => r.party.id) ?? []} onSelect={(p) => add.mutate(p.id)} />
              <div className="flex flex-wrap justify-between gap-2">
                <Button variant="ghost" size="sm" onClick={() => setAdding(null)}>
                  Cancel
                </Button>
                <Button variant="secondary" size="sm" onClick={() => setAdding('create')}>
                  Not found? Create the person
                </Button>
              </div>
            </>
          ) : (
            <PartyCreateForm kind="individual" submitLabel={`Create and add as ${ROLE_LABEL[role]?.toLowerCase() ?? role}`} onCreated={(p) => add.mutate(p.id)} onUseExisting={(m) => add.mutate(m.party_id)} onCancel={() => setAdding('search')} />
          )}
        </div>
      )}
    </section>
  );
}
