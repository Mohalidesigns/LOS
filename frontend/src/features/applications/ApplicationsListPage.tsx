import { useMemo } from 'react';
import { Link, useSearchParams } from 'react-router';
import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { Archive, CirclePlus, FileSearch, FileStack, FolderCheck, ScanSearch, ShieldCheck, Workflow } from 'lucide-react';
import {
  Button,
  Card,
  CardHeader,
  Checkbox,
  DataTable,
  DateTimeText,
  EmptyState,
  FilterChips,
  MoneyText,
  SearchField,
  SectionError,
  Skeleton,
  StatCard,
  StatusBadge,
  type Column,
} from '@/components';
import type { ApplicationSummary } from '@/api/lending';
import { formatNumber } from '@/lib/format';
import { RequirePermission } from '@/features/auth/RequirePermission';
import { usePermissions } from '@/features/auth/session';
import { PERM } from './domain/actions';
import { STATUS_GROUPS, countFor, groupStatuses, isStatusGroupId, type StatusGroupId } from './domain/groups';
import { applicationsInfinite, statsQuery } from './queries';

export type ListVariant = 'applications' | 'pipeline';

const columns: readonly Column<ApplicationSummary>[] = [
  { id: 'reference', header: 'Reference', rowHeader: true, cell: (r) => <span className="ref font-medium">{r.reference}</span>, sub: (r) => r.product.name, sortValue: (r) => r.reference },
  { id: 'applicant', header: 'Applicant', cell: (r) => r.primary_applicant.display_name, sub: (r) => r.segment.toUpperCase(), sortValue: (r) => r.primary_applicant.display_name },
  {
    id: 'amount',
    header: 'Amount',
    align: 'right',
    cell: (r) => (r.requested_amount ? <MoneyText amount={r.requested_amount.amount} currency={r.requested_amount.currency} compact /> : <span className="text-tertiary">—</span>),
    sub: (r) => (r.tenor_months ? `${r.tenor_months} months` : null),
    sortValue: (r) => Number(r.requested_amount?.amount ?? 0),
  },
  { id: 'status', header: 'Stage', cell: (r) => <StatusBadge status={r.status} />, sortValue: (r) => r.status },
  {
    id: 'submitted',
    header: 'Submitted',
    cell: (r) => (r.submitted_at ? <DateTimeText value={r.submitted_at} style="date" /> : <span className="text-tertiary">Not yet</span>),
    sub: (r) => (
      <>
        Updated <DateTimeText value={r.status_changed_at} style="relative-day" />
      </>
    ),
    sortValue: (r) => r.submitted_at ?? '',
  },
];

function parseGroups(raw: string | null): StatusGroupId[] {
  return (raw ?? '').split(',').filter(isStatusGroupId);
}

export function ApplicationsListPage({ variant }: { variant: ListVariant }) {
  return (
    <RequirePermission anyOf={[PERM.view]}>
      <ApplicationsList variant={variant} />
    </RequirePermission>
  );
}

function ApplicationsList({ variant }: { variant: ListVariant }) {
  const permissions = usePermissions();
  const [params, setParams] = useSearchParams();
  const groups = parseGroups(params.get('groups'));
  const q = params.get('q') ?? '';
  const mine = params.get('mine') === '1';

  const filters = useMemo(() => ({ status: groupStatuses(groups), q: q || undefined, mine, open: variant === 'pipeline' && groups.length === 0 }), [groups, q, mine, variant]);
  const list = useInfiniteQuery(applicationsInfinite(filters));
  const stats = useQuery(statsQuery);
  const rows = list.data?.pages.flatMap((p) => p.data);
  const byStatus = stats.data?.by_status ?? {};
  const filtered = groups.length > 0 || q !== '' || mine;

  const update = (next: { groups?: StatusGroupId[]; q?: string; mine?: boolean }) => {
    const p = new URLSearchParams(params);
    const g = next.groups ?? groups;
    if (g.length) p.set('groups', g.join(','));
    else p.delete('groups');
    const nq = next.q ?? q;
    if (nq) p.set('q', nq);
    else p.delete('q');
    const nm = next.mine ?? mine;
    if (nm) p.set('mine', '1');
    else p.delete('mine');
    setParams(p, { replace: true });
  };

  const toggleGroup = (id: string) => {
    if (!isStatusGroupId(id)) return;
    update({ groups: groups.includes(id) ? groups.filter((g) => g !== id) : [...groups, id] });
  };

  const chipOptions = STATUS_GROUPS.filter((g) => variant === 'applications' || g.id !== 'closed').map((g) => ({
    id: g.id,
    label: g.label,
    count: stats.data ? countFor(byStatus, g.statuses) : undefined,
  }));

  const tiles =
    variant === 'pipeline'
      ? [
          { label: 'Applications in flight', value: stats.data?.open, icon: <Workflow className="h-icon-lg w-icon-lg" /> },
          { label: 'In capture', value: countFor(byStatus, groupStatuses(['capture'])), icon: <FileSearch className="h-icon-lg w-icon-lg" /> },
          { label: 'KYC & screening', value: countFor(byStatus, groupStatuses(['kyc'])), icon: <ShieldCheck className="h-icon-lg w-icon-lg" /> },
          { label: 'Documentation', value: countFor(byStatus, groupStatuses(['documentation'])), icon: <FolderCheck className="h-icon-lg w-icon-lg" /> },
        ]
      : [
          { label: 'All applications', value: stats.data?.total, icon: <FileStack className="h-icon-lg w-icon-lg" /> },
          { label: 'Open', value: stats.data?.open, icon: <Workflow className="h-icon-lg w-icon-lg" /> },
          { label: 'Drafts', value: byStatus.draft ?? 0, icon: <ScanSearch className="h-icon-lg w-icon-lg" /> },
          { label: 'Closed', value: countFor(byStatus, groupStatuses(['closed'])), icon: <Archive className="h-icon-lg w-icon-lg" /> },
        ];

  const canOriginate = permissions.has(PERM.originate);
  const newButton = canOriginate ? (
    <Link to="/applications/new" className="inline-flex min-h-target items-center gap-2 rounded-pill bg-accent px-5 py-2 font-semibold text-on-accent shadow-button hover:bg-accent-hover">
      <CirclePlus aria-hidden="true" className="h-icon w-icon" />
      New application
    </Link>
  ) : null;

  return (
    <div className="space-y-6">
      <section aria-label="Key figures" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {tiles.map((t) =>
          stats.isPending ? (
            <Skeleton key={t.label} className="h-[11rem] rounded-card" />
          ) : (
            <StatCard key={t.label} label={t.label} value={formatNumber(t.value ?? 0)} icon={t.icon} />
          ),
        )}
      </section>

      <Card aria-labelledby="list-title">
        <CardHeader
          title={variant === 'pipeline' ? 'Live pipeline' : 'All applications'}
          titleId="list-title"
          subtitle={variant === 'pipeline' ? 'Applications in flight within your scope' : 'Every application in your scope, including closed ones'}
          actions={newButton}
        />
        <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <FilterChips label="Filter by stage" options={chipOptions} selected={groups} onToggle={toggleGroup} />
          <div className="flex flex-wrap items-center gap-4">
            <Checkbox name="mine" label="Mine only" checked={mine} onChange={(e) => update({ mine: e.target.checked })} />
            <SearchField key={q} id={`${variant}-q`} label="Search by reference or applicant" placeholder="Reference or applicant" value={q} onSearch={(v) => update({ q: v })} />
          </div>
        </div>

        {list.isError ? (
          <SectionError error={list.error} title="Applications couldn't load" onRetry={() => void list.refetch()} />
        ) : (
          <DataTable
            caption={variant === 'pipeline' ? 'Pipeline applications' : 'Applications'}
            columns={columns}
            rows={rows}
            getRowId={(r) => r.id}
            rowHref={(r) => `/applications/${r.id}`}
            loading={list.isPending}
            skeletonRows={8}
            empty={
              filtered ? (
                <EmptyState title="No applications match these filters" headingLevel={3} action={<Button variant="secondary" onClick={() => update({ groups: [], q: '', mine: false })}>Clear filters</Button>} />
              ) : (
                <EmptyState title="No applications in your scope yet" headingLevel={3} action={newButton}>
                  New applications you or your team create appear here.
                </EmptyState>
              )
            }
          />
        )}
        <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
          <p className="text-meta text-tertiary" aria-live="polite">
            {rows ? `Showing ${formatNumber(rows.length)}${list.hasNextPage ? '+' : ''} application${rows.length === 1 ? '' : 's'}` : ''}
          </p>
          {list.hasNextPage && (
            <Button variant="secondary" loading={list.isFetchingNextPage} onClick={() => void list.fetchNextPage()}>
              Load more
            </Button>
          )}
        </div>
      </Card>
    </div>
  );
}

export function ApplicationsPage() {
  return <ApplicationsListPage variant="applications" />;
}

export function PipelinePage() {
  return <ApplicationsListPage variant="pipeline" />;
}
