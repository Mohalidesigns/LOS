import { Link } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { BadgeCheck, Banknote, CirclePlus, FileStack, Gauge, Package, ScrollText, UsersRound, Wallet, Workflow, Info, type LucideIcon } from 'lucide-react';
import {
  ActivityTimeline,
  BarChart,
  Card,
  CardHeader,
  DataTable,
  DonutChart,
  KebabMenu,
  MoneyText,
  ProgressBar,
  Skeleton,
  SlaChip,
  StatCard,
  StatusBadge,
  FundlyGlyph,
  useToast,
  type Column,
} from '@/components';
import { formatNumber, formatPercent } from '@/lib/format';
import { useSession } from '@/features/auth/session';
import { dashboardQuery, type RecentApplication } from './api';

type QuickAction = { label: string; to: string; icon: LucideIcon; anyOf: readonly string[] };

const QUICK_ACTIONS: readonly QuickAction[] = [
  { label: 'New application', to: '/applications?new=1', icon: CirclePlus, anyOf: ['application:originate'] },
  { label: 'Pipeline', to: '/pipeline', icon: Workflow, anyOf: ['application:view'] },
  { label: 'Approvals', to: '/inbox?queue=approvals', icon: BadgeCheck, anyOf: ['application:approve'] },
  { label: 'Disbursements', to: '/pipeline?stage=disbursement', icon: Banknote, anyOf: ['disbursement:make', 'disbursement:check'] },
  // Fallbacks so administrators and auditors also get a useful row.
  { label: 'Users', to: '/admin/users', icon: UsersRound, anyOf: ['user:read'] },
  { label: 'Products', to: '/config/products', icon: Package, anyOf: ['product:manage', 'config:read'] },
  { label: 'Audit trail', to: '/audit/events', icon: ScrollText, anyOf: ['audit:read'] },
];

const QUICK_COLS: Record<number, string> = { 1: 'grid-cols-1', 2: 'grid-cols-2', 3: 'grid-cols-3', 4: 'grid-cols-4' };

const columns: readonly Column<RecentApplication>[] = [
  { id: 'reference', header: 'Reference', rowHeader: true, cell: (r) => <span className="ref font-medium">{r.reference}</span>, sub: (r) => r.product, sortValue: (r) => r.reference },
  { id: 'applicant', header: 'Applicant', cell: (r) => r.applicant, sub: (r) => r.applicantType, sortValue: (r) => r.applicant },
  {
    id: 'amount',
    header: 'Amount',
    align: 'right',
    cell: (r) => <MoneyText amount={r.amount.amount} currency={r.amount.currency} compact />,
    sortValue: (r) => Number(r.amount.amount),
  },
  { id: 'status', header: 'Stage', cell: (r) => <StatusBadge status={r.status} />, sortValue: (r) => r.status },
  { id: 'sla', header: 'SLA', cell: (r) => <SlaChip state={r.sla.state} remaining={r.sla.remaining} /> },
];

function PeriodPill({ children }: { children: string }) {
  return <span className="inline-flex min-h-target items-center rounded-pill border border-strong bg-surface px-3 text-body-sm text-primary">{children}</span>;
}

export function DashboardPage() {
  const { me, permissions } = useSession();
  const toast = useToast();
  const { data, isPending } = useQuery(dashboardQuery);
  const quick = QUICK_ACTIONS.filter((a) => a.anyOf.some((p) => permissions.has(p))).slice(0, 4);
  const menu = (label: string) => (
    <KebabMenu
      label={`${label} options`}
      items={[
        { label: 'Refresh', onSelect: () => toast.show(`${label} refreshed`, 'success') },
        { label: 'Export CSV', onSelect: () => toast.show('Exports arrive with reporting (P1).', 'info') },
      ]}
    />
  );

  if (isPending || !data) {
    return (
      <div className="grid gap-6 xl:grid-cols-dashboard" aria-busy="true">
        <span role="status" className="sr-only">
          Loading dashboard
        </span>
        <Skeleton className="h-[14rem] rounded-card" />
        <Skeleton className="h-[14rem] rounded-card" />
        <Skeleton className="h-[14rem] rounded-card" />
      </div>
    );
  }

  const firstName = me.name.split(' ')[0] ?? me.name;
  const slaTotal = data.sla.onTrack + data.sla.atRisk + data.sla.breached;
  const stageTotal = data.byStage.reduce((s, x) => s + x.count, 0);

  return (
    <div className="space-y-6">
      {data.isSample && (
        <p className="flex items-center gap-2 text-body-sm text-tertiary">
          <Info aria-hidden="true" className="h-icon-sm w-icon-sm text-indicator" />
          Welcome back, {firstName}. Figures below are sample data until operational reporting ships (P1).
        </p>
      )}

      <div className="grid gap-6 lg:grid-cols-2 xl:grid-cols-dashboard">
        {/* Left column */}
        <div className="min-w-0 space-y-6 xl:order-1">
          <Card tone="brand" as="section" aria-labelledby="hero-title" className="relative overflow-hidden">
            <div className="flex items-start justify-between">
              <FundlyGlyph onBrand className="h-10 w-10" />
              <span className="rounded-pill bg-brand-raised px-3 py-1 text-meta font-semibold text-on-brand tabular">{data.pipeline.applications} live applications</span>
            </div>
            <h2 id="hero-title" className="mt-6 text-title-lg text-on-brand">
              Portfolio in pipeline
            </h2>
            <div className="mt-4 flex flex-wrap items-end justify-between gap-x-4 gap-y-3">
              <div>
                <p className="text-meta text-on-brand-muted">Total requested</p>
                <p className="text-hero text-on-brand">
                  <MoneyText amount={data.pipeline.value.amount} compact />
                </p>
              </div>
              <dl className="flex gap-4 text-right">
                <div>
                  <dt className="whitespace-nowrap text-meta text-on-brand-muted">Awaiting me</dt>
                  <dd className="text-title text-on-brand tabular">{data.pipeline.awaitingMe}</dd>
                </div>
                <div>
                  <dt className="whitespace-nowrap text-meta text-on-brand-muted">Breached</dt>
                  <dd className="text-title text-on-brand tabular">{data.pipeline.breached}</dd>
                </div>
              </dl>
            </div>
          </Card>

          {quick.length > 0 && (
            <nav aria-label="Quick actions" className="rounded-card bg-muted p-2">
              <ul className={`grid divide-x divide-subtle ${QUICK_COLS[quick.length] ?? 'grid-cols-4'}`}>
                {quick.map((a) => {
                  const Icon = a.icon;
                  return (
                    <li key={a.label}>
                      <Link to={a.to} className="flex min-h-touch flex-col items-center gap-2 rounded-control px-1 py-3 text-center text-meta font-medium text-emphasis hover:bg-surface">
                        <Icon aria-hidden="true" className="h-icon-lg w-icon-lg" />
                        {a.label}
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </nav>
          )}

          <Card aria-labelledby="sla-title">
            <CardHeader title="SLA health" titleId="sla-title" menu={menu('SLA health')} />
            <div className="flex items-baseline justify-between gap-2">
              <p className="text-body text-primary">
                <span className="font-semibold tabular">{formatNumber(data.sla.onTrack)}</span>
                <span className="text-body-sm text-tertiary"> of {formatNumber(slaTotal)} within SLA</span>
              </p>
              <p className="text-body font-semibold text-primary tabular">{formatPercent(data.sla.withinSlaRate)}</p>
            </div>
            <ProgressBar className="mt-3" value={data.sla.withinSlaRate} label="Applications within SLA" valueText={`${formatPercent(data.sla.withinSlaRate)}, target ${formatPercent(data.sla.target, 0)}`} />
            <ul className="mt-4 grid grid-cols-3 gap-2 text-center">
              <li className="rounded-control bg-muted p-2">
                <SlaChip state="on_track" />
                <p className="mt-1 text-title text-emphasis tabular">{data.sla.onTrack}</p>
              </li>
              <li className="rounded-control bg-muted p-2">
                <SlaChip state="at_risk" />
                <p className="mt-1 text-title text-emphasis tabular">{data.sla.atRisk}</p>
              </li>
              <li className="rounded-control bg-muted p-2">
                <SlaChip state="breached" />
                <p className="mt-1 text-title text-emphasis tabular">{data.sla.breached}</p>
              </li>
            </ul>
            <p className="mt-3 text-meta text-tertiary">Target {formatPercent(data.sla.target, 0)} of applications within stage SLA (business hours).</p>
          </Card>
        </div>

        {/* Middle column */}
        <div className="min-w-0 space-y-6 lg:col-span-2 xl:order-2 xl:col-span-1">
          <section aria-label="Key figures" className="grid gap-4 sm:grid-cols-3">
            <StatCard
              label="Applications this month"
              value={formatNumber(data.stats.applicationsThisMonth.value)}
              change={data.stats.applicationsThisMonth.change}
              icon={<FileStack className="h-icon-lg w-icon-lg" />}
              menu={menu('Applications this month')}
            />
            <StatCard
              label="Approval rate"
              value={formatPercent(data.stats.approvalRate.value)}
              change={data.stats.approvalRate.change}
              icon={<Gauge className="h-icon-lg w-icon-lg" />}
              menu={menu('Approval rate')}
            />
            <StatCard
              label="Disbursed value"
              value={<MoneyText amount={data.stats.disbursedValue.value.amount} compact />}
              change={data.stats.disbursedValue.change}
              icon={<Wallet className="h-icon-lg w-icon-lg" />}
              menu={menu('Disbursed value')}
            />
          </section>

          <Card aria-labelledby="flow-title">
            <CardHeader title="Pipeline flow" titleId="flow-title" subtitle="Applications received vs booked, per month" actions={<PeriodPill>This year</PeriodPill>} />
            <BarChart title="Pipeline flow: applications received and booked per month" data={data.flow.map((f) => ({ label: f.label, a: f.received, b: f.booked }))} seriesA="Received" seriesB="Booked" />
          </Card>

        </div>

        {/* Right column */}
        <div className="min-w-0 space-y-6 xl:order-3">
          <Card aria-labelledby="stage-title">
            <CardHeader title="Pipeline by stage" titleId="stage-title" actions={<PeriodPill>Live</PeriodPill>} />
            <DonutChart
              title="Pipeline by stage"
              centerLabel="Applications"
              centerValue={formatNumber(stageTotal)}
              slices={data.byStage.map((s) => ({ label: s.label, value: s.count, display: <MoneyText amount={s.value.amount} compact /> }))}
            />
          </Card>

        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-2 xl:grid-cols-dashboard">
        <div className="min-w-0 lg:col-span-2">
            <Card aria-labelledby="recent-title">
              <CardHeader
                title="Recent applications"
                titleId="recent-title"
                actions={
                  <Link to="/applications" className="inline-flex min-h-target items-center rounded-pill px-3 text-body-sm font-semibold text-link hover:bg-hover-nav">
                    View all
                  </Link>
                }
              />
              <DataTable caption="Recent applications" columns={columns} rows={data.recent} getRowId={(r) => r.id} rowHref={(r) => `/applications/${r.id}`} />
            </Card>
        </div>
        <div className="min-w-0 lg:col-span-2 xl:col-span-1">
            <Card aria-labelledby="activity-title">
              <CardHeader title="Recent activity" titleId="activity-title" menu={menu('Recent activity')} />
              <ActivityTimeline items={data.activity} />
            </Card>
        </div>
      </div>
    </div>
  );
}

export default DashboardPage;
