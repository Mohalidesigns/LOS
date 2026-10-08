import { useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ShieldAlert } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { alertsApi, applicationsApi, type ScreeningAlert } from '@/api/lending';
import { isApiProblem } from '@/api/problem';
import {
  Banner,
  Button,
  Card,
  CardHeader,
  DataTable,
  DateTimeText,
  Drawer,
  EmptyState,
  ErrorSummary,
  FilterChips,
  KeyValueGrid,
  Pill,
  SectionError,
  Skeleton,
  TextArea,
  TextInput,
  useToast,
  type Column,
  type Tone,
} from '@/components';
import { RequirePermission } from '@/features/auth/RequirePermission';
import { usePermissions, useSession } from '@/features/auth/session';
import { alertActions, PROPOSE_REASON_MIN } from './fourEyes';

const STATUS: Record<ScreeningAlert['status'], { label: string; tone: Tone }> = {
  open: { label: 'Open', tone: 'danger' },
  pending_confirmation: { label: 'Pending confirmation', tone: 'warning' },
  cleared: { label: 'Cleared', tone: 'success' },
  confirmed_match: { label: 'Confirmed match', tone: 'danger' },
};
const STATUS_IDS = Object.keys(STATUS) as ScreeningAlert['status'][];
const CATEGORY: Record<ScreeningAlert['category'], string> = { sanction: 'Sanctions', pep: 'PEP', adverse_media: 'Adverse media', watchlist: 'Watchlist' };

const alertKeys = { list: (s: readonly string[]) => ['alerts', 'list', s] as const, detail: (id: string) => ['alerts', id] as const };

export function AlertsPage() {
  return (
    <RequirePermission anyOf={['screening:review']}>
      <AlertsQueue />
    </RequirePermission>
  );
}

function AlertsQueue() {
  const [params, setParams] = useSearchParams();
  const raw = params.get('status');
  const statuses = raw === null ? (['open', 'pending_confirmation'] as string[]) : raw.split(',').filter((s) => (STATUS_IDS as string[]).includes(s));
  const selected = params.get('alert');
  const list = useInfiniteQuery({
    queryKey: alertKeys.list(statuses),
    queryFn: ({ pageParam }) => alertsApi.list({ status: statuses, after: pageParam }),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => (last.meta.page.has_more ? last.meta.page.next_cursor : null),
  });
  const rows = list.data?.pages.flatMap((p) => p.data);

  const setStatuses = (next: string[]) => {
    const p = new URLSearchParams(params);
    p.set('status', next.join(','));
    setParams(p, { replace: true });
  };
  const hrefFor = (a: ScreeningAlert) => {
    const p = new URLSearchParams(params);
    p.set('alert', a.id);
    return `/compliance/alerts?${p.toString()}`;
  };
  const close = () => {
    const p = new URLSearchParams(params);
    p.delete('alert');
    setParams(p);
  };

  const columns: readonly Column<ScreeningAlert>[] = [
    { id: 'name', header: 'Matched name', rowHeader: true, cell: (a) => a.matched_name, sub: (a) => `${CATEGORY[a.category]} · ${a.list_name}`, sortValue: (a) => a.matched_name },
    { id: 'party', header: 'Party', cell: (a) => a.party_name ?? '—', sortValue: (a) => a.party_name ?? '' },
    { id: 'score', header: 'Score', align: 'right', cell: (a) => <span className="tabular">{a.score}</span>, sortValue: (a) => Number(a.score) },
    { id: 'status', header: 'Status', cell: (a) => <Pill tone={STATUS[a.status].tone}>{STATUS[a.status].label}</Pill>, sortValue: (a) => a.status },
    { id: 'raised', header: 'Raised', cell: (a) => <DateTimeText value={a.created_at} />, sortValue: (a) => a.created_at },
  ];

  return (
    <div className="space-y-6">
      <Card aria-labelledby="alerts-title">
        <CardHeader title="Screening alerts" titleId="alerts-title" subtitle="Sanctions, PEP and adverse-media hits. One officer proposes, a different officer confirms." />
        <div className="mb-4">
          <FilterChips
            label="Filter by status"
            options={STATUS_IDS.map((s) => ({ id: s, label: STATUS[s].label }))}
            selected={statuses}
            onToggle={(id) => setStatuses(statuses.includes(id) ? statuses.filter((s) => s !== id) : [...statuses, id])}
          />
        </div>
        {list.isError ? (
          <SectionError error={list.error} title="Alerts couldn't load" onRetry={() => void list.refetch()} />
        ) : (
          <DataTable
            caption="Screening alerts"
            columns={columns}
            rows={rows}
            getRowId={(a) => a.id}
            rowHref={hrefFor}
            loading={list.isPending}
            skeletonRows={8}
            empty={
              <EmptyState icon={<ShieldAlert className="h-8 w-8" />} title={statuses.length ? 'No alerts with these statuses' : 'Choose at least one status'} headingLevel={3}>
                New screening hits appear here as soon as the screening run finishes.
              </EmptyState>
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
      </Card>
      <Drawer open={selected !== null} onClose={close} title="Screening alert" subtitle={selected ? <span className="ref">{selected}</span> : undefined}>
        {selected && <AlertDetail key={selected} id={selected} />}
      </Drawer>
    </div>
  );
}

function AlertDetail({ id }: { id: string }) {
  const { me } = useSession();
  const permissions = usePermissions();
  const alert = useQuery({ queryKey: alertKeys.detail(id), queryFn: () => alertsApi.get(id) });
  const appId = alert.data?.application_id ?? null;
  const app = useQuery({ queryKey: ['applications', 'case', appId ?? '', 'detail'], queryFn: () => applicationsApi.get(appId ?? ''), enabled: appId !== null });

  if (alert.isPending) return <Skeleton className="h-[16rem] w-full" />;
  if (alert.isError) return <SectionError error={alert.error} onRetry={() => void alert.refetch()} />;
  const a = alert.data;
  const actions = alertActions(a, me.id, permissions, app.data?.data.originator_id ?? null);
  const s = STATUS[a.status];

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center gap-2">
        <Pill tone={s.tone}>{s.label}</Pill>
        <Pill tone="neutral">{CATEGORY[a.category]}</Pill>
      </div>
      <KeyValueGrid
        items={[
          { label: 'Matched name', value: a.matched_name },
          { label: 'Score', value: <span className="tabular">{a.score}</span> },
          { label: 'List', value: a.list_name },
          { label: 'List version', value: <span className="ref">{a.list_version}</span> },
          { label: 'Entry', value: <span className="ref">{a.entry_id}</span> },
          { label: 'Raised', value: <DateTimeText value={a.created_at} /> },
          { label: 'Party', value: <Link to={`/parties/${a.party_id}`} className="text-link hover:underline">{a.party_name ?? 'Open party'}</Link> },
          {
            label: 'Application',
            value: a.application_id ? (
              <Link to={`/applications/${a.application_id}/kyc`} className="text-link hover:underline">
                {app.data ? <span className="ref">{app.data.data.reference}</span> : 'Open application'}
              </Link>
            ) : (
              '—'
            ),
          },
        ]}
      />
      {a.proposed_decision && (
        <section aria-labelledby="proposal-h" className="rounded-card bg-muted p-4">
          <h3 id="proposal-h" className="text-body font-semibold text-primary">
            Proposed: {a.proposed_decision === 'clear' ? 'clear (false positive)' : 'true match'}
          </h3>
          <p className="mt-1 text-body-sm text-primary">{a.proposed_reason}</p>
          <p className="mt-1 text-meta text-tertiary">
            {a.proposed_by === me.id ? 'Proposed by you' : `Proposed by staff user ${a.proposed_by?.slice(-6) ?? ''}`}
            {a.proposed_at && (
              <>
                {' '}
                · <DateTimeText value={a.proposed_at} />
              </>
            )}
            {a.evidence_ref && (
              <>
                {' '}
                · evidence <span className="ref">{a.evidence_ref}</span>
              </>
            )}
          </p>
          {a.confirmed_at && (
            <p className="mt-2 text-meta text-tertiary">
              Confirmed <DateTimeText value={a.confirmed_at} />
              {a.confirmation_note ? ` · ${a.confirmation_note}` : ''}
            </p>
          )}
        </section>
      )}
      {actions.propose.visible && <ProposeForm alert={a} reason={actions.propose.reason} />}
      {actions.confirm.visible && <ConfirmForm alert={a} reason={actions.confirm.reason} />}
    </div>
  );
}

function useDisposition(alert: ScreeningAlert, step: 'propose' | 'confirm') {
  const qc = useQueryClient();
  const toast = useToast();
  const [key] = useState(idempotencyKey);
  return useMutation({
    mutationFn: (body: { decision?: 'clear' | 'true_match'; reason: string; evidence_ref?: string | null }) => alertsApi.disposition(alert.id, step, body, key),
    onSuccess: async (res) => {
      // The disposition response omits party_name (GET includes it): keep what we already know.
      const updated: ScreeningAlert = { ...res, party_name: res.party_name ?? alert.party_name ?? null };
      qc.setQueryData(alertKeys.detail(alert.id), updated);
      toast.show(step === 'propose' ? 'Disposition proposed. A different officer must confirm it.' : `Alert ${STATUS[updated.status].label.toLowerCase()}`, 'success');
      await qc.invalidateQueries({ queryKey: ['alerts', 'list'] });
      if (alert.application_id) await qc.invalidateQueries({ queryKey: ['applications', 'case', alert.application_id] });
    },
  });
}

function RefusalSummary({ error }: { error: Error }) {
  const problem = isApiProblem(error) ? error : null;
  const refused = problem !== null && (problem.status === 403 || problem.status === 409 || problem.status === 422);
  return <ErrorSummary title={refused ? 'The disposition was refused' : 'The disposition was not recorded'} problem={problem} messages={problem ? undefined : [error.message]} />;
}

function ProposeForm({ alert, reason: blocked }: { alert: ScreeningAlert; reason: string | null }) {
  const [decision, setDecision] = useState<'clear' | 'true_match'>('clear');
  const [reason, setReason] = useState('');
  const [evidence, setEvidence] = useState('');
  const [touched, setTouched] = useState(false);
  const run = useDisposition(alert, 'propose');
  const reasonError = touched && reason.trim().length < PROPOSE_REASON_MIN ? `Explain the decision in at least ${PROPOSE_REASON_MIN} characters.` : undefined;
  return (
    <form
      className="space-y-4 rounded-card border p-4"
      aria-labelledby="propose-h"
      onSubmit={(e) => {
        e.preventDefault();
        setTouched(true);
        if (blocked || reason.trim().length < PROPOSE_REASON_MIN) return;
        run.mutate({ decision, reason: reason.trim(), evidence_ref: evidence.trim() || null });
      }}
    >
      <h3 id="propose-h" className="text-title text-emphasis">
        Propose a disposition
      </h3>
      {blocked && <Banner tone="info" title={blocked} />}
      {run.error && <RefusalSummary error={run.error} />}
      <fieldset>
        <legend className="mb-2 text-body-sm font-semibold text-primary">Decision</legend>
        <div className="space-y-2">
          {(
            [
              ['clear', 'Clear: false positive', 'The customer is not the listed person or entity.'],
              ['true_match', 'True match', 'The customer is the listed person or entity. The application stays blocked.'],
            ] as const
          ).map(([v, label, hint]) => (
            <div key={v} className="flex items-start gap-2.5">
              <input id={`decision-${alert.id}-${v}`} type="radio" name={`decision-${alert.id}`} value={v} checked={decision === v} onChange={() => setDecision(v)} aria-describedby={`decision-${alert.id}-${v}-hint`} className="mt-1 h-5 w-5 cursor-pointer accent-accent" />
              <div>
                <label htmlFor={`decision-${alert.id}-${v}`} className="block cursor-pointer text-body text-primary">
                  {label}
                </label>
                <p id={`decision-${alert.id}-${v}-hint`} className="text-meta text-tertiary">
                  {hint}
                </p>
              </div>
            </div>
          ))}
        </div>
      </fieldset>
      <TextArea label="Reason" required value={reason} onChange={(e) => setReason(e.target.value)} error={reasonError} helper={`At least ${PROPOSE_REASON_MIN} characters. The confirming officer reads this.`} />
      <TextInput label="Evidence reference" value={evidence} onChange={(e) => setEvidence(e.target.value)} helper="Document id, case file or link reference (optional)" />
      <div className="flex justify-end">
        <Button type="submit" loading={run.isPending} disabledReason={blocked}>
          Propose
        </Button>
      </div>
    </form>
  );
}

function ConfirmForm({ alert, reason: blocked }: { alert: ScreeningAlert; reason: string | null }) {
  const [reason, setReason] = useState('');
  const [touched, setTouched] = useState(false);
  const run = useDisposition(alert, 'confirm');
  const reasonError = touched && reason.trim().length < 5 ? 'Add a short confirmation note.' : undefined;
  return (
    <form
      className="space-y-4 rounded-card border p-4"
      aria-labelledby="confirm-h"
      onSubmit={(e) => {
        e.preventDefault();
        setTouched(true);
        if (blocked || reason.trim().length < 5) return;
        run.mutate({ reason: reason.trim() });
      }}
    >
      <h3 id="confirm-h" className="text-title text-emphasis">
        Confirm the proposal
      </h3>
      <p className="text-body-sm text-secondary">
        Confirming applies “{alert.proposed_decision === 'true_match' ? 'true match' : 'clear'}” to this alert. You must be a different officer from the one who proposed it.
      </p>
      {blocked && <Banner tone="info" title={blocked} />}
      {run.error && <RefusalSummary error={run.error} />}
      <TextArea label="Confirmation note" required value={reason} onChange={(e) => setReason(e.target.value)} error={reasonError} />
      <div className="flex justify-end">
        <Button type="submit" loading={run.isPending} disabledReason={blocked}>
          Confirm
        </Button>
      </div>
    </form>
  );
}

export default AlertsPage;
