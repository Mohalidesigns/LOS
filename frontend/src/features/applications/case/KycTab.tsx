import { useState } from 'react';
import { Link } from 'react-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CheckCircle2, Circle, Loader2, RefreshCw } from 'lucide-react';
import { applicationsApi, type KycStatus } from '@/api/lending';
import { isApiProblem } from '@/api/problem';
import { Button, CardHeader, DateTimeText, ErrorSummary, Pill, SectionError, Skeleton, useToast, type Tone } from '@/components';
import { cn } from '@/lib/cn';
import { DirectorsEditor } from '@/features/parties/components/DirectorsEditor';
import { ConsentsPanel, IdentitiesPanel, usePartyQuery } from '@/features/parties/components/PartyPanels';
import { PERM } from '../domain/actions';
import { appKeys, kycQuery } from '../queries';
import { useCase } from './CaseContext';

const OUTCOME: Record<KycStatus['outcome'], { label: string; tone: Tone }> = {
  clear: { label: 'KYC clear', tone: 'success' },
  pending: { label: 'KYC pending', tone: 'warning' },
  blocked: { label: 'KYC blocked', tone: 'danger' },
};

const CONDITION_LABEL: Record<string, string> = {
  IDENTITY_VERIFIED: 'Identity verified',
  DATA_CONSENT: 'Data-processing consent',
  SCREENING_COMPLETE: 'Screening complete',
  ALERTS_RESOLVED: 'Screening alerts resolved',
  DIRECTORS_RECORDED: 'Directors recorded',
  BENEFICIAL_OWNERS: 'Beneficial owners identified',
};

export const ALERT_STATUS: Record<string, { label: string; tone: Tone }> = {
  open: { label: 'Open', tone: 'danger' },
  pending_confirmation: { label: 'Pending confirmation', tone: 'warning' },
  cleared: { label: 'Cleared', tone: 'success' },
  confirmed_match: { label: 'Confirmed match', tone: 'danger' },
};

export function KycTab() {
  const { app, permissions } = useCase();
  const [partyId, setPartyId] = useState(app.applicants[0]?.party_id ?? app.primary_applicant.party_id);
  const current = app.applicants.find((a) => a.party_id === partyId) ?? app.applicants[0];
  const canManage = permissions.has(PERM.partyManage);

  return (
    <div className="space-y-6">
      <KycGate />
      <div>
        <h3 className="mb-2 text-title text-emphasis">Parties on this application</h3>
        <div role="group" aria-label="Choose a party" className="flex flex-wrap gap-2">
          {app.applicants.map((a) => (
            <button
              key={a.party_id}
              type="button"
              aria-pressed={a.party_id === partyId}
              onClick={() => setPartyId(a.party_id)}
              className={cn(
                'inline-flex min-h-target flex-col items-start rounded-control border px-3 py-1.5 text-left',
                a.party_id === partyId ? 'border-accent bg-accent-subtle' : 'border-strong bg-surface hover:bg-hover',
              )}
            >
              <span className="text-body-sm font-semibold text-primary">{a.display_name}</span>
              <span className="text-meta capitalize text-tertiary">{a.role.replace('_', ' ')}</span>
            </button>
          ))}
        </div>
      </div>
      {current && <PartyKyc key={current.party_id} partyId={current.party_id} canManage={canManage} applicationId={app.id} />}
    </div>
  );
}

function PartyKyc({ partyId, canManage, applicationId }: { partyId: string; canManage: boolean; applicationId: string }) {
  const party = usePartyQuery(partyId);
  if (party.isPending) return <Skeleton className="h-[12rem] w-full rounded-card" />;
  if (party.isError) return <SectionError error={party.error} onRetry={() => void party.refetch()} />;
  return (
    <div className="grid gap-6 xl:grid-cols-2">
      <IdentitiesPanel party={party.data} canManage={canManage} />
      <ConsentsPanel partyId={partyId} canManage={canManage} applicationId={applicationId} />
      {party.data.type === 'limited_company' && (
        <div className="xl:col-span-2">
          <DirectorsEditor companyId={partyId} canManage={canManage} />
        </div>
      )}
    </div>
  );
}

function KycGate() {
  const { app, permissions } = useCase();
  const qc = useQueryClient();
  const toast = useToast();
  const kyc = useQuery({ ...kycQuery(app.id), refetchInterval: (q) => (q.state.data?.conditions.some((c) => c.code === 'SCREENING_COMPLETE' && !c.met) ? 10_000 : false) });
  const rescreen = useMutation({
    mutationFn: () => applicationsApi.rescreen(app.id),
    onSuccess: async (data) => {
      qc.setQueryData(appKeys.kyc(app.id), data);
      toast.show('Screening re-run for every party', 'success');
      await qc.invalidateQueries({ queryKey: appKeys.detail(app.id) });
    },
  });

  if (kyc.isPending) return <Skeleton className="h-[10rem] w-full rounded-card" />;
  if (kyc.isError) return <SectionError error={kyc.error} title="KYC status couldn't load" onRetry={() => void kyc.refetch()} />;
  const k = kyc.data;
  const outcome = OUTCOME[k.outcome];
  const screening = k.conditions.find((c) => c.code === 'SCREENING_COMPLETE');
  const inProgress = screening ? !screening.met : false;
  const notStarted = app.status === 'draft' || app.status === 'submitted' || app.status === 'pre_qualified';

  return (
    <section aria-labelledby="kyc-gate-h" className="rounded-card bg-muted p-5">
      <CardHeader
        title="KYC / CDD gate"
        titleId="kyc-gate-h"
        level={3}
        subtitle={`Gate ${k.gate_version} · decides when the case may leave KYC & screening`}
        actions={
          <>
            <Pill tone={outcome.tone}>{outcome.label}</Pill>
            <Pill tone={k.risk === 'enhanced' ? 'warning' : 'neutral'}>{k.risk === 'enhanced' ? 'Enhanced due diligence' : 'Standard risk'}</Pill>
          </>
        }
      />
      {notStarted && <p className="mb-3 text-body-sm text-secondary">The gate is evaluated after submission. You can verify identities and record consents now.</p>}
      {inProgress && !notStarted && (
        <p role="status" className="mb-3 inline-flex items-center gap-2 text-body-sm font-semibold text-primary">
          <Loader2 aria-hidden="true" className="h-icon-sm w-icon-sm animate-spin" />
          Screening in progress. This updates automatically.
        </p>
      )}
      <ul className="grid gap-2 md:grid-cols-2">
        {k.conditions.map((c) => (
          <li key={c.code} className="flex items-start gap-2 rounded-control bg-surface p-3">
            {c.met ? <CheckCircle2 aria-hidden="true" className="mt-0.5 h-icon w-icon shrink-0 text-success" /> : <Circle aria-hidden="true" className="mt-0.5 h-icon w-icon shrink-0 text-warning" />}
            <div className="min-w-0">
              <p className="text-body-sm font-semibold text-primary">
                {CONDITION_LABEL[c.code] ?? c.code.replace(/_/g, ' ').toLowerCase()} <span className="font-normal text-secondary">· {c.met ? 'met' : 'not met'}</span>
              </p>
              <p className="text-meta text-secondary">{c.detail}</p>
            </div>
          </li>
        ))}
      </ul>

      <div className="mt-5 flex flex-wrap items-center justify-between gap-2">
        <h4 className="text-body font-semibold text-primary">Screening runs</h4>
        <Button
          size="sm"
          variant="secondary"
          leadingIcon={<RefreshCw aria-hidden="true" className="h-icon-sm w-icon-sm" />}
          loading={rescreen.isPending}
          disabledReason={permissions.has(PERM.screeningReview) ? null : 'Requires the screening:review permission.'}
          onClick={() => rescreen.mutate()}
        >
          Re-screen
        </Button>
      </div>
      {rescreen.error && <div className="mt-2"><ErrorSummary problem={isApiProblem(rescreen.error) ? rescreen.error : null} messages={isApiProblem(rescreen.error) ? undefined : [rescreen.error.message]} /></div>}
      {k.screening_runs.length === 0 ? (
        <p className="mt-2 text-body-sm text-secondary">{inProgress ? 'Waiting for the first screening run.' : 'No screening runs yet.'}</p>
      ) : (
        <div className="mt-2 overflow-x-auto rounded-control bg-surface">
          <table className="w-full text-table">
            <caption className="sr-only">Screening runs</caption>
            <thead>
              <tr className="bg-muted text-left text-meta text-tertiary">
                <th scope="col" className="px-cell-x py-2 font-medium">Subject</th>
                <th scope="col" className="px-cell-x py-2 font-medium">Trigger</th>
                <th scope="col" className="px-cell-x py-2 font-medium">List version</th>
                <th scope="col" className="px-cell-x py-2 text-right font-medium">Hits</th>
                <th scope="col" className="px-cell-x py-2 font-medium">Screened</th>
              </tr>
            </thead>
            <tbody>
              {k.screening_runs.map((r) => (
                <tr key={r.id} className="border-t border-subtle">
                  <th scope="row" className="px-cell-x py-2 text-left font-semibold">{r.subject_name}</th>
                  <td className="px-cell-x py-2 capitalize">{r.trigger.replace(/_/g, ' ')}</td>
                  <td className="px-cell-x py-2"><span className="ref">{r.list_version}</span></td>
                  <td className="px-cell-x py-2 text-right tabular">{r.hit_count}</td>
                  <td className="px-cell-x py-2"><DateTimeText value={r.screened_at} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {k.alerts.length > 0 && (
        <>
          <h4 className="mt-5 text-body font-semibold text-primary">Alerts</h4>
          <ul className="mt-2 space-y-2">
            {k.alerts.map((a) => {
              const s = ALERT_STATUS[a.status] ?? { label: a.status, tone: 'neutral' as Tone };
              return (
                <li key={a.id} className="flex flex-wrap items-center justify-between gap-2 rounded-control bg-surface p-3">
                  <div className="min-w-0">
                    <p className="text-body-sm font-semibold text-primary">
                      {a.matched_name} <span className="font-normal text-secondary">· {a.category.replace('_', ' ')} · {a.list_name} · score {a.score}</span>
                    </p>
                    <p className="text-meta text-tertiary">{a.party_name ?? 'Party'} · raised <DateTimeText value={a.created_at} /></p>
                  </div>
                  <div className="flex items-center gap-2">
                    <Pill tone={s.tone}>{s.label}</Pill>
                    {permissions.has(PERM.screeningReview) && (
                      <Link to={`/compliance/alerts?alert=${a.id}`} className="inline-flex min-h-target items-center rounded-pill px-3 text-body-sm font-semibold text-link hover:bg-hover-nav">
                        Open alert
                      </Link>
                    )}
                  </div>
                </li>
              );
            })}
          </ul>
        </>
      )}
    </section>
  );
}
