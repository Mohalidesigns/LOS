import { useState } from 'react';
import { Link } from 'react-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { DownloadCloud, ShieldOff } from 'lucide-react';
import { creditApi, type BureauReport } from '@/api/credit';
import type { Application } from '@/api/lending';
import { Banner, Button, CardHeader, DateTimeText, ErrorSummary, MoneyText, Pill, SectionError, Skeleton, useToast } from '@/components';
import { isApiProblem } from '@/api/problem';
import { cn } from '@/lib/cn';
import { appKeys, bureauQuery } from '../queries';
import { Collapsible, ScoreGauge, StaffName } from './CreditParts';
import { consentBlock, CREDIT_PERM, creditWorkReason, firstReason, needPermission } from './domain';

export function BureauSection({ app, permissions }: { app: Application; permissions: ReadonlySet<string> }) {
  const reports = useQuery(bureauQuery(app.id));
  const primaryId = app.primary_applicant.party_id;
  const parties = [...app.applicants].sort((a, b) => (a.party_id === primaryId ? -1 : b.party_id === primaryId ? 1 : 0));
  const [partyId, setPartyId] = useState(primaryId);
  const pullReason = firstReason(creditWorkReason(app.status), needPermission(permissions, CREDIT_PERM.bureauPull));

  return (
    <section aria-labelledby="bureau-h" className="rounded-card border bg-surface p-5 shadow-card">
      <CardHeader title="Credit bureau" titleId="bureau-h" subtitle="Canonical credit profile per party. A report stays valid for a limited time; the decision needs a valid report for the primary applicant." />
      {parties.length > 1 && (
        <div role="group" aria-label="Choose a party" className="mb-4 flex flex-wrap gap-2">
          {parties.map((a) => (
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
              <span className="text-meta capitalize text-tertiary">{a.party_id === primaryId ? 'Primary applicant' : a.role.replace(/_/g, ' ')}</span>
            </button>
          ))}
        </div>
      )}
      {reports.isPending ? (
        <Skeleton className="h-[12rem] w-full rounded-card" />
      ) : reports.isError ? (
        <SectionError error={reports.error} title="Bureau reports couldn't load" onRetry={() => void reports.refetch()} />
      ) : (
        <PartyBureau
          key={partyId}
          app={app}
          partyId={partyId}
          partyName={parties.find((p) => p.party_id === partyId)?.display_name ?? 'Party'}
          isPrimary={partyId === primaryId}
          reports={reports.data.filter((r) => r.party_id === partyId)}
          pullReason={pullReason}
        />
      )}
    </section>
  );
}

function PartyBureau({ app, partyId, partyName, isPrimary, reports, pullReason }: { app: Application; partyId: string; partyName: string; isPrimary: boolean; reports: BureauReport[]; pullReason: string | null }) {
  const qc = useQueryClient();
  const toast = useToast();
  const sorted = [...reports].sort((a, b) => b.pulled_at.localeCompare(a.pulled_at));
  const [latest, ...earlier] = sorted;
  const pull = useMutation({
    mutationFn: () => creditApi.pullBureau(app.id, partyId),
    onSuccess: async (r) => {
      toast.show(r.hit ? `Bureau report ${r.report_reference} pulled for ${partyName}` : `No hit for ${partyName}: thin file recorded`, 'success');
      await Promise.all([qc.invalidateQueries({ queryKey: appKeys.bureau(app.id) }), qc.invalidateQueries({ queryKey: appKeys.memo(app.id) })]);
    },
  });
  const blocked = consentBlock(pull.error);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-body-sm text-secondary">
          {latest ? (
            <>
              Latest report for <span className="font-semibold text-primary">{partyName}</span> pulled <DateTimeText value={latest.pulled_at} />
            </>
          ) : (
            <>
              No bureau report for <span className="font-semibold text-primary">{partyName}</span> yet{isPrimary ? '. The decision needs one.' : '.'}
            </>
          )}
        </p>
        <Button
          size="sm"
          variant={latest?.is_valid ? 'secondary' : 'primary'}
          leadingIcon={<DownloadCloud aria-hidden="true" className="h-icon-sm w-icon-sm" />}
          loading={pull.isPending}
          disabledReason={pullReason}
          onClick={() => pull.mutate()}
        >
          {latest ? 'Pull again' : 'Pull bureau report'}
        </Button>
      </div>

      {blocked && (
        <Banner tone="danger" title="Bureau enquiry blocked: no valid bureau consent">
          <p>{blocked.detail || `${partyName} has not granted the credit bureau consent, so no enquiry can be made.`}</p>
          <p className="mt-1">
            Record the customer’s <span className="font-semibold">credit_bureau</span> consent, then pull again.{' '}
            <Link to={`/applications/${app.id}/kyc?party=${blocked.partyId ?? partyId}#consents`} className="font-semibold text-link underline">
              Open Applicant &amp; KYC consents
            </Link>
          </p>
        </Banner>
      )}
      {pull.error && !blocked && (
        <ErrorSummary title="The bureau report was not pulled" problem={isApiProblem(pull.error) ? pull.error : null} messages={isApiProblem(pull.error) ? undefined : [pull.error.message]} />
      )}

      {latest ? (
        <ReportCard report={latest} />
      ) : (
        !blocked && (
          <div className="flex items-center gap-3 rounded-card bg-muted p-4">
            <ShieldOff aria-hidden="true" className="h-icon w-icon text-indicator" />
            <p className="text-body-sm text-secondary">Pulling a report needs the party’s credit bureau consent. The enquiry is recorded on the customer’s bureau file.</p>
          </div>
        )
      )}

      {earlier.length > 0 && (
        <Collapsible summary={`Earlier reports (${earlier.length})`}>
          <ul className="space-y-3">
            {earlier.map((r) => (
              <li key={r.id}>
                <ReportCard report={r} compact />
              </li>
            ))}
          </ul>
        </Collapsible>
      )}
    </div>
  );
}

export function ReportCard({ report: r, compact = false }: { report: BureauReport; compact?: boolean }) {
  const p = r.profile;
  return (
    <article aria-label={`Bureau report ${r.report_reference}`} className={cn('rounded-card border p-4', !r.is_valid && 'border-warning')}>
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="text-body font-semibold text-primary">
            {r.bureau} · <span className="ref">{r.report_reference}</span>
          </p>
          <p className="text-meta text-tertiary">
            Pulled <DateTimeText value={r.pulled_at} /> by <StaffName id={r.pulled_by} /> · valid until <DateTimeText value={r.valid_until} style="date" />
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          {r.is_valid ? <Pill tone="success">Valid</Pill> : <Pill tone="warning">Expired</Pill>}
          {r.hit ? <Pill tone="neutral">Hit</Pill> : <Pill tone="info">No hit · thin file</Pill>}
        </div>
      </div>
      {!r.is_valid && !compact && <p className="mt-2 text-body-sm text-warning">This report has expired. Pull again before running the decision.</p>}
      {!r.hit ? (
        <p className="mt-3 text-body-sm text-secondary">The bureau holds no credit history for this party. The decision treats it as a thin file: no score, no existing obligations.</p>
      ) : (
        <>
          <div className="mt-4 grid gap-4 lg:grid-cols-[minmax(0,20rem)_1fr]">
            <ScoreGauge score={p.score} />
            <dl className="grid grid-cols-2 gap-3 sm:grid-cols-3">
              <Tile label="Active facilities" value={p.active_facilities} />
              <Tile label="Outstanding" value={<MoneyText amount={p.total_outstanding.amount} currency={p.total_outstanding.currency} compact />} />
              <Tile label="Monthly obligations" value={<MoneyText amount={p.monthly_obligations.amount} currency={p.monthly_obligations.currency} compact />} />
              <Tile label="Max DPD (12m)" value={`${p.max_dpd_12m} days`} warn={p.max_dpd_12m > 30} />
              <Tile label="Delinquent facilities" value={p.delinquent_facilities} warn={p.delinquent_facilities > 0} />
              <Tile label="Enquiries (6m)" value={p.enquiries_6m} warn={p.enquiries_6m > 5} />
              <Tile label="Write-off" value={p.has_write_off ? 'Yes' : 'None'} warn={p.has_write_off} />
            </dl>
          </div>
          {!compact && p.facilities.length > 0 && (
            <div className="mt-4 overflow-x-auto rounded-control border">
              <table className="w-full text-table">
                <caption className="sr-only">Facilities reported by the bureau</caption>
                <thead>
                  <tr className="bg-muted text-left text-meta text-tertiary">
                    <th scope="col" className="px-cell-x py-2 font-medium">Lender</th>
                    <th scope="col" className="px-cell-x py-2 font-medium">Type</th>
                    <th scope="col" className="px-cell-x py-2 text-right font-medium">Outstanding</th>
                    <th scope="col" className="px-cell-x py-2 text-right font-medium">Monthly instalment</th>
                    <th scope="col" className="px-cell-x py-2 text-right font-medium">DPD</th>
                    <th scope="col" className="px-cell-x py-2 font-medium">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {p.facilities.map((f, i) => (
                    <tr key={`${f.lender}-${i}`} className="border-t border-subtle">
                      <th scope="row" className="px-cell-x py-2 text-left font-semibold">{f.lender}</th>
                      <td className="px-cell-x py-2 capitalize">{f.type.replace(/_/g, ' ')}</td>
                      <td className="px-cell-x py-2 text-right"><MoneyText amount={f.outstanding.amount} currency={f.outstanding.currency} /></td>
                      <td className="px-cell-x py-2 text-right"><MoneyText amount={f.monthly_instalment.amount} currency={f.monthly_instalment.currency} /></td>
                      <td className={cn('px-cell-x py-2 text-right tabular', f.dpd > 30 && 'font-semibold text-danger')}>{f.dpd}</td>
                      <td className="px-cell-x py-2 capitalize">{f.status.replace(/_/g, ' ')}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}
    </article>
  );
}

function Tile({ label, value, warn = false }: { label: string; value: React.ReactNode; warn?: boolean }) {
  return (
    <div className={cn('rounded-control bg-subtle px-3 py-2', warn && 'bg-warning')}>
      <dt className="text-meta text-tertiary">{label}</dt>
      <dd className={cn('text-body font-semibold tabular', warn ? 'text-warning' : 'text-primary')}>{value}</dd>
    </div>
  );
}
