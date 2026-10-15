import { useState, type ReactNode, type SyntheticEvent } from 'react';
import { useInfiniteQuery } from '@tanstack/react-query';
import { Search } from 'lucide-react';
import { auditApi, type AuditEvent, type FilterQuery, type IntegrationCall, type Page, type ReadAccessRecord } from '@/api/audit';
import { Button, Card, CardHeader, DataTable, DateTimeText, Pill, SectionError, Select, Tabs, TextInput, type Column } from '@/components';
import { RequirePermission } from '@/features/auth/RequirePermission';
import { usePermissions } from '@/features/auth/session';
import { actionLabel, AUDIT_PERM, toFilterQuery } from './domain';

type Field = { name: string; label: string; options?: readonly { value: string; label: string }[]; type?: 'datetime-local' };

const PERIOD: Field[] = [
  { name: 'from', label: 'From (WAT)', type: 'datetime-local' },
  { name: 'to', label: 'To (WAT)', type: 'datetime-local' },
];

/** Audit explorer (FR-AUD-009): the hash-chained trail, the read-access log and integration calls, for auditors. */
export function AuditExplorerPage() {
  return (
    <RequirePermission anyOf={[AUDIT_PERM.read]}>
      <Explorer />
    </RequirePermission>
  );
}

function Explorer() {
  const permissions = usePermissions();
  const [tab, setTab] = useState('events');
  const items = [
    { id: 'events', label: 'Audit events', panel: <EventsPanel /> },
    { id: 'reads', label: 'Read access', panel: <ReadsPanel /> },
    ...(permissions.has(AUDIT_PERM.integrationRead) ? [{ id: 'calls', label: 'Integration calls', panel: <CallsPanel /> }] : []),
  ];

  return (
    <Card>
      <CardHeader title="Audit explorer" titleId="ax-h" level={2} subtitle="Read-only. Every search is scoped to this tenant; the trail is append-only and hash-chained." />
      <Tabs label="Audit sources" items={items} value={tab} onChange={setTab} />
    </Card>
  );
}

function FilterForm({ fields, onApply }: { fields: readonly Field[]; onApply: (q: FilterQuery) => void }) {
  const [values, setValues] = useState<Record<string, string>>({});
  const submit = (e: SyntheticEvent) => {
    e.preventDefault();
    onApply(toFilterQuery(values));
  };
  return (
    <form onSubmit={submit} className="my-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Filters">
      {fields.map((f) =>
        f.options ? (
          <Select key={f.name} label={f.label} value={values[f.name] ?? ''} onChange={(e) => setValues({ ...values, [f.name]: e.target.value })} options={f.options} />
        ) : (
          <TextInput key={f.name} label={f.label} type={f.type ?? 'text'} value={values[f.name] ?? ''} onChange={(e) => setValues({ ...values, [f.name]: e.target.value })} />
        ),
      )}
      <div className="flex items-end">
        <Button type="submit" leadingIcon={<Search aria-hidden="true" className="h-icon-sm w-icon-sm" />}>
          Search
        </Button>
      </div>
    </form>
  );
}

function PagedTable<T>({ queryKey, fetcher, columns, caption, getRowId, fields }: { queryKey: string; fetcher: (q: FilterQuery, after: string | null) => Promise<Page<T>>; columns: readonly Column<T>[]; caption: string; getRowId: (r: T) => string; fields: readonly Field[] }): ReactNode {
  const [query, setQuery] = useState<FilterQuery>({});
  const q = useInfiniteQuery({
    queryKey: ['audit', queryKey, query],
    queryFn: ({ pageParam }) => fetcher(query, pageParam),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.next,
  });
  const rows = q.data?.pages.flatMap((p) => p.data);
  return (
    <div>
      <FilterForm fields={fields} onApply={setQuery} />
      {q.isError ? (
        <SectionError error={q.error} onRetry={() => void q.refetch()} />
      ) : (
        <DataTable caption={caption} hideCaption columns={columns} rows={rows} getRowId={getRowId} loading={q.isPending} empty="Nothing matches these filters." />
      )}
      {q.hasNextPage && (
        <div className="mt-3 flex justify-center">
          <Button variant="secondary" loading={q.isFetchingNextPage} onClick={() => void q.fetchNextPage()}>
            Load more
          </Button>
        </div>
      )}
    </div>
  );
}

const outcomeTone = (o: string) => (o === 'success' ? 'success' : o === 'denied' || o === 'non_retryable' || o === 'business_rejection' ? 'danger' : 'warning');
const mono = (v: string | null | undefined) => (v ? <span className="ref break-all text-meta">{v}</span> : '—');

const EVENT_COLUMNS: Column<AuditEvent>[] = [
  { id: 'seq', header: '#', cell: (e) => String(e.seq), align: 'right' },
  { id: 'at', header: 'When', cell: (e) => <DateTimeText value={e.occurred_at} /> },
  { id: 'action', header: 'Action', cell: (e) => actionLabel(e.action), sub: (e) => e.reason_text ?? undefined, rowHeader: true },
  { id: 'actor', header: 'Actor', cell: (e) => mono(e.actor_id), sub: (e) => e.actor_type },
  { id: 'entity', header: 'Entity', cell: (e) => e.entity_type ?? '—', sub: (e) => (e.entity_id ? mono(e.entity_id) : undefined) },
  { id: 'outcome', header: 'Outcome', cell: (e) => <Pill tone={outcomeTone(e.outcome)}>{e.outcome}</Pill> },
];

function EventsPanel() {
  return (
    <PagedTable
      queryKey="events"
      fetcher={(q, after) => auditApi.events(q, after)}
      caption="Audit events"
      columns={EVENT_COLUMNS}
      getRowId={(e) => e.id}
      fields={[
        { name: 'application_id', label: 'Application id' },
        { name: 'actor_id', label: 'User id' },
        { name: 'action', label: 'Action (prefix with *)' },
        { name: 'entity_type', label: 'Entity type' },
        { name: 'outcome', label: 'Outcome', options: [{ value: '', label: 'Any' }, { value: 'success', label: 'Success' }, { value: 'denied', label: 'Denied' }, { value: 'failure', label: 'Failure' }] },
        ...PERIOD,
      ]}
    />
  );
}

const READ_COLUMNS: Column<ReadAccessRecord>[] = [
  { id: 'at', header: 'When', cell: (r) => <DateTimeText value={r.occurred_at} /> },
  { id: 'actor', header: 'Reader', cell: (r) => mono(r.actor_id), sub: (r) => r.actor_type, rowHeader: true },
  { id: 'resource', header: 'Resource', cell: (r) => r.resource_type, sub: (r) => (r.resource_id ? mono(r.resource_id) : 'list') },
  { id: 'route', header: 'Route', cell: (r) => r.route },
  { id: 'pii', header: 'PII', cell: (r) => (r.contains_pii ? <Pill tone="warning">PII</Pill> : '—') },
  { id: 'purpose', header: 'Purpose', cell: (r) => r.purpose ?? '—' },
];

function ReadsPanel() {
  return (
    <PagedTable
      queryKey="reads"
      fetcher={(q, after) => auditApi.readAccess(q, after)}
      caption="Read access log"
      columns={READ_COLUMNS}
      getRowId={(r) => r.id}
      fields={[
        { name: 'resource_id', label: 'Resource id' },
        { name: 'actor_id', label: 'User id' },
        { name: 'resource_type', label: 'Resource type', options: [{ value: '', label: 'Any' }, ...['application', 'party', 'document', 'decision'].map((v) => ({ value: v, label: v }))] },
        { name: 'pii', label: 'PII only', options: [{ value: '', label: 'All reads' }, { value: 'true', label: 'PII reads' }] },
        ...PERIOD,
      ]}
    />
  );
}

const CALL_COLUMNS: Column<IntegrationCall>[] = [
  { id: 'at', header: 'Started', cell: (c) => (c.started_at ? <DateTimeText value={c.started_at} /> : '—') },
  { id: 'port', header: 'Port · operation', cell: (c) => `${c.port} · ${c.operation}`, sub: (c) => `${c.adapter_key} ${c.adapter_version ?? ''}`, rowHeader: true },
  { id: 'outcome', header: 'Outcome', cell: (c) => <Pill tone={outcomeTone(c.outcome)}>{c.outcome.replace(/_/g, ' ')}</Pill>, sub: (c) => c.error_code ?? undefined },
  { id: 'latency', header: 'Latency', cell: (c) => `${c.latency_ms} ms`, align: 'right' },
  { id: 'attempt', header: 'Attempt', cell: (c) => String(c.attempt ?? 1), align: 'right' },
  { id: 'corr', header: 'Correlation', cell: (c) => mono(c.correlation_id) },
];

function CallsPanel() {
  return (
    <PagedTable
      queryKey="calls"
      fetcher={(q, after) => auditApi.integrationCalls(q, after)}
      caption="Integration calls (PII-masked)"
      columns={CALL_COLUMNS}
      getRowId={(c) => c.id}
      fields={[
        { name: 'operation', label: 'Operation' },
        { name: 'correlation_id', label: 'Correlation id' },
        { name: 'outcome', label: 'Outcome', options: [{ value: '', label: 'Any' }, ...['success', 'retryable', 'non_retryable', 'requires_intervention', 'business_rejection', 'short_circuited'].map((v) => ({ value: v, label: v.replace(/_/g, ' ') }))] },
      ]}
    />
  );
}
