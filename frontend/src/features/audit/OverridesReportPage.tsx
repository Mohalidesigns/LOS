import { useState, type SyntheticEvent } from 'react';
import { useInfiniteQuery } from '@tanstack/react-query';
import { Link } from 'react-router';
import { Search } from 'lucide-react';
import { auditApi, type FilterQuery, type OverrideRecord } from '@/api/audit';
import { Button, Card, CardHeader, DataTable, DateTimeText, Pill, SectionError, Select, TextInput, type Column } from '@/components';
import { RequirePermission } from '@/features/auth/RequirePermission';
import { actionLabel, AUDIT_PERM, OVERRIDE_TYPE, personLabel, toFilterQuery } from './domain';

const TYPES = Object.keys(OVERRIDE_TYPE) as OverrideRecord['type'][];

const COLUMNS: Column<OverrideRecord>[] = [
  { id: 'at', header: 'When', cell: (r) => <DateTimeText value={r.occurred_at} /> },
  { id: 'type', header: 'Type', cell: (r) => <Pill tone={OVERRIDE_TYPE[r.type].tone}>{OVERRIDE_TYPE[r.type].label}</Pill> },
  { id: 'action', header: 'What', cell: (r) => actionLabel(r.action), sub: (r) => r.reason ?? undefined, rowHeader: true },
  { id: 'by', header: 'Performed / approved by', cell: (r) => personLabel(r.performed_by), sub: (r) => (r.requested_by ? `Requested by ${personLabel(r.requested_by)}` : undefined) },
  {
    id: 'app',
    header: 'Application',
    cell: (r) =>
      r.application_id ? (
        <Link className="text-link underline-offset-2 hover:underline" to={`/applications/${r.application_id}/timeline`}>
          Open case
        </Link>
      ) : (
        <span className="text-tertiary">{r.entity_type ?? '—'}</span>
      ),
  },
];

/** Overrides, waivers, exceptions and manual interventions over a period, by user and by type (FR-AUD-016). */
export function OverridesReportPage() {
  return (
    <RequirePermission anyOf={[AUDIT_PERM.read]}>
      <Report />
    </RequirePermission>
  );
}

function Report() {
  const [values, setValues] = useState<Record<string, string>>({});
  const [query, setQuery] = useState<FilterQuery>({});
  const q = useInfiniteQuery({
    queryKey: ['audit', 'overrides', query],
    queryFn: ({ pageParam }) => auditApi.overrides(query, pageParam),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.next,
  });
  const summary = q.data?.pages[0]?.summary;
  const submit = (e: SyntheticEvent) => {
    e.preventDefault();
    setQuery(toFilterQuery(values));
  };

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader title="Overrides & waivers" titleId="ov-h" level={2} subtitle="Derived from the hash-chained audit trail. Maker-checker waivers show who asked and who approved." />
        <form onSubmit={submit} aria-label="Filters" className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <TextInput label="From (WAT)" type="datetime-local" value={values.from ?? ''} onChange={(e) => setValues({ ...values, from: e.target.value })} />
          <TextInput label="To (WAT)" type="datetime-local" value={values.to ?? ''} onChange={(e) => setValues({ ...values, to: e.target.value })} />
          <Select label="Type" value={values.type ?? ''} onChange={(e) => setValues({ ...values, type: e.target.value })} options={[{ value: '', label: 'All types' }, ...TYPES.map((t) => ({ value: t, label: OVERRIDE_TYPE[t].label }))]} />
          <div className="flex items-end">
            <Button type="submit" leadingIcon={<Search aria-hidden="true" className="h-icon-sm w-icon-sm" />}>
              Run report
            </Button>
          </div>
        </form>
      </Card>

      {summary && (
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
          <Card>
            <CardHeader title="By type" titleId="ov-type" level={3} />
            <dl className="grid grid-cols-3 gap-3">
              {TYPES.map((t) => (
                <div key={t} className="rounded-control bg-muted p-3">
                  <dt className="text-meta text-secondary">{OVERRIDE_TYPE[t].label}</dt>
                  <dd className="text-title text-emphasis">{summary.by_type[t]}</dd>
                </div>
              ))}
            </dl>
          </Card>
          <Card>
            <CardHeader title="By user" titleId="ov-user" level={3} />
            {summary.by_user.length === 0 ? (
              <p className="text-body-sm text-secondary">No one in this period.</p>
            ) : (
              <ul className="divide-y text-body-sm">
                {summary.by_user.slice(0, 8).map((u) => (
                  <li key={u.user?.id ?? 'unknown'} className="flex justify-between py-2">
                    <span>{personLabel(u.user)}</span>
                    <span className="font-semibold">{u.count}</span>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>
      )}

      <Card>
        {q.isError ? (
          <SectionError error={q.error} onRetry={() => void q.refetch()} />
        ) : (
          <DataTable caption="Overrides and waivers" columns={COLUMNS} rows={q.data?.pages.flatMap((p) => p.data)} getRowId={(r) => r.id} loading={q.isPending} empty="No overrides or waivers in this period." />
        )}
        {q.hasNextPage && (
          <div className="mt-3 flex justify-center">
            <Button variant="secondary" loading={q.isFetchingNextPage} onClick={() => void q.fetchNextPage()}>
              Load more
            </Button>
          </div>
        )}
      </Card>
    </div>
  );
}
