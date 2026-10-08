import { useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router';
import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { ArrowLeft, Building2, User, UserPlus } from 'lucide-react';
import { applicationsApi, partiesApi, type ApplicationSummary, type PartySummary } from '@/api/lending';
import {
  Button,
  Card,
  CardHeader,
  DataTable,
  DateTimeText,
  EmptyState,
  FilterChips,
  KeyValueGrid,
  Modal,
  MoneyText,
  Pill,
  SearchField,
  SectionError,
  Skeleton,
  StatusBadge,
  useToast,
  type Column,
} from '@/components';
import { RequirePermission } from '@/features/auth/RequirePermission';
import { usePermissions } from '@/features/auth/session';
import { DirectorsEditor } from './components/DirectorsEditor';
import { PartyCreateForm } from './components/PartyCreateForm';
import { ConsentsPanel, IdentitiesPanel, usePartyQuery } from './components/PartyPanels';

const TYPE_OPTIONS = [
  { id: 'individual', label: 'Individuals' },
  { id: 'limited_company', label: 'Companies' },
];

const columns: readonly Column<PartySummary>[] = [
  { id: 'name', header: 'Customer', rowHeader: true, cell: (p) => p.display_name, sub: (p) => (p.type === 'limited_company' ? 'Limited company' : 'Individual'), sortValue: (p) => p.display_name },
  { id: 'rc', header: 'RC number', cell: (p) => (p.registration_number ? <span className="ref">{p.registration_number}</span> : <span className="text-tertiary">—</span>) },
  { id: 'status', header: 'Status', cell: (p) => <Pill tone={p.status === 'active' ? 'success' : 'neutral'}>{p.status.replace(/_/g, ' ')}</Pill>, sortValue: (p) => p.status },
  { id: 'created', header: 'Created', cell: (p) => <DateTimeText value={p.created_at} style="date" />, sortValue: (p) => p.created_at },
];

export function PartiesPage() {
  return (
    <RequirePermission anyOf={['party:manage', 'application:view']}>
      <PartiesList />
    </RequirePermission>
  );
}

function PartiesList() {
  const permissions = usePermissions();
  const navigate = useNavigate();
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const q = params.get('q') ?? '';
  const type = params.get('type') ?? '';
  const [creating, setCreating] = useState(false);
  const list = useInfiniteQuery({
    queryKey: ['parties', 'list', q, type],
    queryFn: ({ pageParam }) => partiesApi.list({ q: q || undefined, type: type || undefined, after: pageParam }),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => (last.meta.page.has_more ? last.meta.page.next_cursor : null),
  });
  const rows = list.data?.pages.flatMap((p) => p.data);
  const set = (k: string, v: string) => {
    const p = new URLSearchParams(params);
    if (v) p.set(k, v);
    else p.delete(k);
    setParams(p, { replace: true });
  };
  const canCreate = permissions.has('party:manage');

  return (
    <Card aria-labelledby="parties-title">
      <CardHeader
        title="Customers"
        titleId="parties-title"
        subtitle="Individuals and companies in your scope. Identity numbers are always shown masked."
        actions={
          canCreate ? (
            <Button leadingIcon={<UserPlus aria-hidden="true" className="h-icon w-icon" />} onClick={() => setCreating(true)}>
              New customer
            </Button>
          ) : undefined
        }
      />
      <div className="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <FilterChips label="Customer type" options={TYPE_OPTIONS} selected={type ? [type] : []} onToggle={(id) => set('type', type === id ? '' : id)} />
        <SearchField key={q} id="parties-q" label="Search by name or RC number" placeholder="Name or RC number" value={q} onSearch={(v) => set('q', v)} />
      </div>
      {list.isError ? (
        <SectionError error={list.error} title="Customers couldn't load" onRetry={() => void list.refetch()} />
      ) : (
        <DataTable
          caption="Customers"
          columns={columns}
          rows={rows}
          getRowId={(p) => p.id}
          rowHref={(p) => `/parties/${p.id}`}
          loading={list.isPending}
          skeletonRows={8}
          empty={
            q || type ? (
              <EmptyState title="No customers match these filters" headingLevel={3} action={<Button variant="secondary" onClick={() => setParams({}, { replace: true })}>Clear filters</Button>} />
            ) : (
              <EmptyState title="No customers yet" headingLevel={3}>
                Customers are created when you start an application or with “New customer”.
              </EmptyState>
            )
          }
        />
      )}
      {list.hasNextPage && (
        <div className="mt-4 flex justify-end">
          <Button variant="secondary" loading={list.isFetchingNextPage} onClick={() => void list.fetchNextPage()}>
            Load more
          </Button>
        </div>
      )}
      {creating && (
        <Modal open onClose={() => setCreating(false)} title="New customer" className="!max-w-[min(760px,calc(100%-2rem))]">
          <PartyCreateForm
            onCreated={(p) => {
              toast.show(`${p.display_name} created`, 'success');
              void navigate(`/parties/${p.id}`);
            }}
            onUseExisting={(m) => void navigate(`/parties/${m.party_id}`)}
            onCancel={() => setCreating(false)}
          />
        </Modal>
      )}
    </Card>
  );
}

const appColumns: readonly Column<ApplicationSummary>[] = [
  { id: 'reference', header: 'Reference', rowHeader: true, cell: (r) => <span className="ref font-medium">{r.reference}</span>, sub: (r) => r.product.name },
  { id: 'amount', header: 'Amount', align: 'right', cell: (r) => (r.requested_amount ? <MoneyText amount={r.requested_amount.amount} currency={r.requested_amount.currency} compact /> : '—') },
  { id: 'status', header: 'Stage', cell: (r) => <StatusBadge status={r.status} /> },
  { id: 'created', header: 'Created', cell: (r) => <DateTimeText value={r.created_at} style="date" /> },
];

export function PartyDetailPage() {
  return (
    <RequirePermission anyOf={['party:manage', 'application:view']}>
      <PartyDetail />
    </RequirePermission>
  );
}

function PartyDetail() {
  const { id = '' } = useParams();
  const permissions = usePermissions();
  const party = usePartyQuery(id);
  const apps = useQuery({ queryKey: ['applications', 'list', { partyId: id }], queryFn: () => applicationsApi.list({ partyId: id, size: 50 }) });
  const canManage = permissions.has('party:manage');

  if (party.isPending)
    return (
      <div className="space-y-6" aria-busy="true">
        <span className="sr-only" role="status">
          Loading customer
        </span>
        <Skeleton className="h-[8rem] w-full rounded-card" />
        <Skeleton className="h-[16rem] w-full rounded-card" />
      </div>
    );
  if (party.isError)
    return (
      <Card>
        <SectionError error={party.error} title="This customer couldn't load" onRetry={() => void party.refetch()} />
      </Card>
    );
  const p = party.data;
  const Icon = p.type === 'limited_company' ? Building2 : User;

  return (
    <div className="space-y-6">
      <Link to="/parties" className="inline-flex min-h-target items-center gap-1.5 rounded-pill px-2 text-body-sm font-semibold text-link hover:bg-hover-nav">
        <ArrowLeft aria-hidden="true" className="h-icon-sm w-icon-sm" />
        Customers
      </Link>
      <Card as="section" aria-labelledby="party-title">
        <div className="flex flex-wrap items-center gap-3">
          <span aria-hidden="true" className="inline-flex h-icon-tile w-icon-tile items-center justify-center rounded-mark bg-muted text-emphasis">
            <Icon className="h-icon-lg w-icon-lg" />
          </span>
          <div>
            <h2 id="party-title" className="text-title-lg text-emphasis">
              {p.display_name}
            </h2>
            <p className="text-body-sm text-secondary">
              {p.type === 'limited_company' ? 'Limited company' : 'Individual'}
              {p.registration_number && (
                <>
                  {' · '}
                  <span className="ref">{p.registration_number}</span>
                </>
              )}
            </p>
          </div>
          <Pill tone={p.status === 'active' ? 'success' : 'neutral'} className="ml-auto">
            {p.status}
          </Pill>
        </div>
        <KeyValueGrid
          className="mt-5 border-t pt-4"
          columns={4}
          items={[
            { label: 'CBA customer id', value: p.cba_customer_id ? <span className="ref">{p.cba_customer_id}</span> : 'New to bank' },
            { label: p.type === 'limited_company' ? 'Incorporated' : 'Nationality', value: p.type === 'limited_company' ? (p.incorporation_date ? <DateTimeText value={p.incorporation_date} style="date" /> : '—') : (p.nationality ?? '—') },
            { label: 'Sector', value: p.sector?.replace(/_/g, ' ') ?? '—' },
            { label: 'Customer since', value: <DateTimeText value={p.created_at} style="date" /> },
          ]}
        />
      </Card>
      <div className="grid gap-6 xl:grid-cols-2">
        <Card padded={false} className="p-1">
          <IdentitiesPanel party={p} canManage={canManage} headingLevel={2} />
        </Card>
        <Card padded={false} className="p-1">
          <ConsentsPanel partyId={p.id} canManage={canManage} headingLevel={2} />
        </Card>
      </div>
      {p.type === 'limited_company' && (
        <Card padded={false} className="p-1">
          <DirectorsEditor companyId={p.id} canManage={canManage} headingLevel={2} />
        </Card>
      )}
      <Card aria-labelledby="party-apps">
        <CardHeader title="Applications" titleId="party-apps" />
        {apps.isError ? (
          <SectionError error={apps.error} onRetry={() => void apps.refetch()} />
        ) : (
          <DataTable caption={`Applications for ${p.display_name}`} columns={appColumns} rows={apps.data?.data} getRowId={(r) => r.id} rowHref={(r) => `/applications/${r.id}`} loading={apps.isPending} empty={<EmptyState title="No applications for this customer" headingLevel={3} />} />
        )}
      </Card>
    </div>
  );
}
