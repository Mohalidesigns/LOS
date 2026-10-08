import { useEffect, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { Link, Outlet, useLocation, useNavigate, useParams } from 'react-router';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, RotateCw } from 'lucide-react';
import type { ApplicationAction } from '@/api/lending';
import { Banner, Button, Card, KebabMenu, MoneyText, SectionError, Skeleton, StageTracker, StatusBadge } from '@/components';
import { cn } from '@/lib/cn';
import { RequirePermission } from '@/features/auth/RequirePermission';
import { usePermissions } from '@/features/auth/session';
import { menuActions, PERM, primaryAction } from '../domain/actions';
import { isTerminal, stageModel } from '../domain/stages';
import { appKeys, applicationQuery, kycQuery, timelineQuery } from '../queries';
import { ActionDialog } from './ActionDialog';
import type { CaseContextValue } from './CaseContext';

export const CASE_TABS = [
  { path: '', label: 'Summary' },
  { path: 'kyc', label: 'Applicant & KYC' },
  { path: 'documents', label: 'Documents' },
  { path: 'credit', label: 'Credit' },
  { path: 'timeline', label: 'Timeline' },
] as const;

export function CasePage() {
  return (
    <RequirePermission anyOf={[PERM.view]}>
      <CaseWorkspace />
    </RequirePermission>
  );
}

/** Tab row where each tab is its own route (browser back works); arrow keys move between tabs. */
function RouteTabs({ base, active }: { base: string; active: string }) {
  const navigate = useNavigate();
  const refs = useRef<(HTMLAnchorElement | null)[]>([]);
  const index = Math.max(0, CASE_TABS.findIndex((t) => t.path === active));
  const go = (i: number) => {
    const t = CASE_TABS[(i + CASE_TABS.length) % CASE_TABS.length];
    if (!t) return;
    void navigate(t.path ? `${base}/${t.path}` : base);
    refs.current[(i + CASE_TABS.length) % CASE_TABS.length]?.focus();
  };
  const onKey = (e: KeyboardEvent, i: number) => {
    if (e.key === 'ArrowRight') go(i + 1);
    else if (e.key === 'ArrowLeft') go(i - 1);
    else if (e.key === 'Home') go(0);
    else if (e.key === 'End') go(CASE_TABS.length - 1);
    else return;
    e.preventDefault();
  };
  return (
    <div role="tablist" aria-label="Case sections" className="flex gap-1 overflow-x-auto border-b">
      {CASE_TABS.map((t, i) => {
        const selected = i === index;
        return (
          <Link
            key={t.path}
            ref={(el) => {
              refs.current[i] = el;
            }}
            id={`case-tab-${t.path || 'summary'}`}
            role="tab"
            aria-selected={selected}
            aria-controls="case-panel"
            tabIndex={selected ? 0 : -1}
            to={t.path ? `${base}/${t.path}` : base}
            onKeyDown={(e) => onKey(e, i)}
            className={cn(
              '-mb-px inline-flex min-h-target items-center whitespace-nowrap border-b-2 px-3 py-2.5 text-body-sm font-semibold transition-colors duration-fast',
              selected ? 'border-accent text-emphasis' : 'border-transparent text-tertiary hover:text-primary',
            )}
          >
            {t.label}
          </Link>
        );
      })}
    </div>
  );
}

function CaseWorkspace() {
  const { id = '' } = useParams();
  const location = useLocation();
  const qc = useQueryClient();
  const permissions = usePermissions();
  const query = useQuery(applicationQuery(id));
  const status = query.data?.data.status;
  // While screening runs asynchronously (outbox), poll so the case advances on its own.
  const kyc = useQuery({ ...kycQuery(id), enabled: status === 'kyc_screening', refetchInterval: status === 'kyc_screening' ? 10_000 : false });
  const timeline = useQuery({ ...timelineQuery(id), enabled: status !== undefined && isTerminal(status) });
  const [stale, setStale] = useState(false);
  const [dialog, setDialog] = useState<ApplicationAction | null>(null);
  const created = (location.state as { created?: string } | null)?.created;

  // When the KYC gate clears the server moves the case to documentation: refetch the header.
  const kycOutcome = kyc.data?.outcome;
  const kycStatus = kyc.data?.status;
  useEffect(() => {
    if (status && kycStatus && kycStatus !== status) void qc.invalidateQueries({ queryKey: appKeys.detail(id) });
  }, [kycStatus, kycOutcome, status, id, qc]);

  const base = `/applications/${id}`;
  const rest = location.pathname.slice(base.length).replace(/^\//, '');
  const activeTab = rest.split('/')[0] ?? '';

  const lastActive = useMemo(() => {
    const events = timeline.data ?? [];
    for (let i = events.length - 1; i >= 0; i--) {
      const from = events[i]?.payload.from;
      if (typeof from === 'string' && !isTerminal(from) && from !== 'on_hold' && from !== 'returned_for_rework') return from;
    }
    return null;
  }, [timeline.data]);

  if (query.isPending) {
    return (
      <div className="space-y-6" aria-busy="true">
        <span className="sr-only" role="status">
          Loading application
        </span>
        <Skeleton className="h-[8rem] w-full rounded-card" />
        <Skeleton className="h-[5rem] w-full rounded-card" />
        <Skeleton className="h-[20rem] w-full rounded-card" />
      </div>
    );
  }
  if (query.isError) {
    return (
      <Card>
        <SectionError error={query.error} title="This application couldn't load" onRetry={() => void query.refetch()} />
      </Card>
    );
  }

  const { data: app, etag } = query.data;
  const model = stageModel(app.status, { resumeTo: app.resume_to, returnTo: app.return_to, lastActive });
  const primary = primaryAction(app.status, permissions);
  const menu = menuActions(app.status, permissions);
  const reload = async () => {
    setStale(false);
    await qc.invalidateQueries({ queryKey: appKeys.case(id) });
  };
  const ctx: CaseContextValue = { app, etag, permissions, stale, markStale: () => setStale(true), reload };

  return (
    <div className="space-y-6">
      <Link to="/applications" className="inline-flex min-h-target items-center gap-1.5 rounded-pill px-2 text-body-sm font-semibold text-link hover:bg-hover-nav">
        <ArrowLeft aria-hidden="true" className="h-icon-sm w-icon-sm" />
        Applications
      </Link>

      {created && app.status === 'draft' && (
        <Banner tone="success" title={`Draft ${created} created`}>
          Add applicants, record consents and upload documents, then submit it from the button above.
        </Banner>
      )}
      {stale && (
        <Banner tone="warning" title="This application changed since you opened it">
          <p>Someone else updated it. Reload to see the latest version before you try again; anything you typed is kept until you reload.</p>
          <Button size="sm" variant="secondary" className="mt-2" leadingIcon={<RotateCw aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={() => void reload()}>
            Reload application
          </Button>
        </Banner>
      )}

      <Card as="section" aria-labelledby="case-title">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-3">
              <h2 id="case-title" className="text-title-lg text-emphasis">
                {app.primary_applicant.display_name}
              </h2>
              <StatusBadge status={app.status} />
            </div>
            <p className="mt-1 text-body-sm text-secondary">
              <span className="ref font-medium text-primary">{app.reference}</span> · {app.product.name} · {app.segment.toUpperCase()}
              {app.close_reason_code && (
                <>
                  {' '}
                  · closed with <span className="ref">{app.close_reason_code}</span>
                </>
              )}
            </p>
          </div>
          {!isTerminal(app.status) && (
            <div className="flex flex-wrap items-center gap-2">
              {menu.length > 0 && (
                <KebabMenu
                  label="Case actions"
                  buttonText="Actions"
                  items={menu.map((m) => ({ label: m.label, disabledReason: m.disabledReason, danger: m.action === 'withdraw' || m.action === 'cancel', onSelect: () => setDialog(m.action) }))}
                />
              )}
              {primary && (
                <Button disabledReason={primary.disabledReason} onClick={() => setDialog(primary.action)}>
                  {primary.label}
                </Button>
              )}
            </div>
          )}
        </div>
        <dl className="mt-5 grid grid-cols-2 gap-4 border-t pt-4 sm:grid-cols-4">
          <div>
            <dt className="text-meta text-tertiary">Requested</dt>
            <dd className="text-title text-emphasis">{app.requested_amount ? <MoneyText amount={app.requested_amount.amount} currency={app.requested_amount.currency} /> : '—'}</dd>
          </div>
          <div>
            <dt className="text-meta text-tertiary">Tenor</dt>
            <dd className="text-title text-emphasis">{app.tenor_months ? `${app.tenor_months} months` : '—'}</dd>
          </div>
          <div>
            <dt className="text-meta text-tertiary">Repayment</dt>
            <dd className="text-title capitalize text-emphasis">{app.repayment_frequency ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-meta text-tertiary">Completeness</dt>
            <dd className="text-title text-emphasis tabular">{app.completeness.percent}%</dd>
          </div>
        </dl>
      </Card>

      <Card aria-label="Stage tracker">
        <StageTracker steps={model.steps} interruption={model.interruption} />
        {app.status === 'kyc_screening' && kyc.data?.conditions.some((c) => c.code === 'SCREENING_COMPLETE' && !c.met) && (
          <p role="status" className="mt-3 text-body-sm text-secondary">
            Screening in progress. The case moves to Documentation by itself when the KYC gate clears.
          </p>
        )}
      </Card>

      <Card padded={false} className="overflow-hidden">
        <div className="px-card-x pt-2">
          <RouteTabs base={base} active={activeTab} />
        </div>
        <div id="case-panel" role="tabpanel" aria-labelledby={`case-tab-${activeTab || 'summary'}`} className="px-card-x py-card-y">
          <Outlet context={ctx} />
        </div>
      </Card>

      {dialog && <ActionDialog key={dialog} action={dialog} ctx={ctx} onClose={() => setDialog(null)} />}
    </div>
  );
}

export default CasePage;
