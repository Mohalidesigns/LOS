import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CheckCircle2, CircleAlert, CircleDashed, Download, RotateCw, Send, Undo2 } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { disbursementApi, type Disbursement } from '@/api/disbursement';
import { documentsApi } from '@/api/lending';
import { isApiProblem } from '@/api/problem';
import { Banner, Button, CardHeader, DateTimeText, ErrorSummary, MoneyText, Pill, SectionError, Skeleton, useToast } from '@/components';
import { useCase } from '@/features/applications/case/CaseContext';
import { Collapsible } from '@/features/applications/credit/CreditParts';
import { problemLines } from '@/features/applications/credit/domain';
import { refreshCase } from '@/features/applications/queries';
import { meQuery } from '@/features/auth/session';
import { conditionRegisterQuery } from '@/features/conditions/queries';
import { saveBlob } from '@/lib/download';
import { currentInstruction, DSB_PERM, makeReason, releaseReason, retryReason, STATUS_META, stepLabel } from './domain';
import { CancelDialog, MakeDialog, ReleaseDialog } from './DisbursementDialogs';
import { applicationDisbursementsQuery } from './queries';

type Dialog = 'make' | 'release' | 'cancel' | null;

/** SCR-DSB-01/02: maker-checker release, booking progress, exceptions and the advice. */
export function DisbursementTab() {
  const { app, permissions } = useCase();
  const list = useQuery(applicationDisbursementsQuery(app.id));
  const conditions = useQuery(conditionRegisterQuery(app.id));
  const me = useQuery(meQuery);
  const [dialog, setDialog] = useState<Dialog>(null);

  if (list.isPending) return <Skeleton className="h-[20rem] w-full rounded-card" />;
  if (list.isError) return <SectionError error={list.error} title="Disbursements couldn't load" onRetry={() => void list.refetch()} />;

  const current = currentInstruction(list.data);
  const make = makeReason(app.status, permissions, current);
  const blockers = conditions.data?.readiness.blockers ?? [];
  const earlier = list.data.filter((d) => d.id !== current?.id);
  const reached = ['ready_for_disbursement', 'disbursing', 'booked'].includes(app.status);

  if (!reached && list.data.length === 0) {
    return (
      <Banner tone="info" title="Disbursement opens when the application is ready">
        Once every mandatory condition precedent is met or waived and the pre-disbursement check passes, a maker raises the instruction and a different checker releases it.
      </Banner>
    );
  }

  return (
    <div className="space-y-5">
      {(!current || current.status === 'compensated' || current.status === 'cancelled') && app.status !== 'booked' && (
        <section aria-labelledby="dsb-make" className="rounded-card border bg-surface p-5 shadow-card">
          <CardHeader
            title="Disbursement instruction"
            titleId="dsb-make"
            level={2}
            actions={
              <Button leadingIcon={<Send aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabledReason={make ?? (blockers.length > 0 ? 'Release is blocked; see the Conditions tab.' : null)} onClick={() => setDialog('make')}>
                Make instruction
              </Button>
            }
          />
          {blockers.length > 0 ? (
            <ul className="list-disc space-y-0.5 pl-5 text-body-sm text-secondary">
              {blockers.map((b) => (
                <li key={b}>{b}</li>
              ))}
            </ul>
          ) : (
            <p className="text-body-sm text-secondary">Ready: the amount, fees and destination come from the accepted offer and the verified account.</p>
          )}
        </section>
      )}
      {current && <InstructionDetail d={current} permissions={permissions} meId={me.data?.id ?? null} onDialog={setDialog} />}
      {earlier.length > 0 && (
        <section aria-labelledby="dsb-hist" className="rounded-card border bg-surface p-5 shadow-card">
          <CardHeader title="Earlier instructions" titleId="dsb-hist" level={2} />
          <ul className="space-y-2">
            {earlier.map((d) => (
              <li key={d.id} className="flex flex-wrap items-center justify-between gap-2 rounded-control border p-3 text-body-sm">
                <span>
                  #{d.sequence} · made <DateTimeText value={d.made_at} />
                  {d.last_error && <> · {d.last_error.code}</>}
                </span>
                <Pill tone={STATUS_META[d.status].tone}>{STATUS_META[d.status].label}</Pill>
              </li>
            ))}
          </ul>
        </section>
      )}
      {dialog === 'make' && <MakeDialog applicationId={app.id} onClose={() => setDialog(null)} />}
      {dialog === 'release' && current && <ReleaseDialog d={current} onClose={() => setDialog(null)} />}
      {dialog === 'cancel' && current && <CancelDialog d={current} onClose={() => setDialog(null)} />}
    </div>
  );
}

function InstructionDetail({ d, permissions, meId, onDialog }: { d: Disbursement; permissions: ReadonlySet<string>; meId: string | null; onDialog: (x: Dialog) => void }) {
  const qc = useQueryClient();
  const toast = useToast();
  const [retryKey, setRetryKey] = useState(idempotencyKey);
  const retry = useMutation({
    mutationFn: () => disbursementApi.retry(d.id, retryKey),
    onSuccess: async () => {
      setRetryKey(idempotencyKey());
      toast.show('Retry queued. Completed steps are not repeated.', 'success');
      await refreshCase(qc, d.application_id);
    },
  });
  const download = useMutation({ mutationFn: async () => d.advice && saveBlob(await documentsApi.content(d.advice.document_id, d.advice.version_id), 'disbursement-advice.pdf') });
  const meta = STATUS_META[d.status];
  const lastRun = Math.max(0, ...d.steps.map((s) => s.run));
  const steps = d.steps.filter((s) => s.run === lastRun || s.outcome === 'succeeded');

  return (
    <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <div className="min-w-0 space-y-5">
        <section aria-labelledby="dsb-main" className="rounded-card border bg-surface p-5 shadow-card">
          <CardHeader title={`Instruction #${d.sequence}`} titleId="dsb-main" level={2} subtitle={meta.help} actions={<Pill tone={meta.tone}>{meta.label}</Pill>} />
          {retry.error && <ErrorSummary title="Not retried" problem={isApiProblem(retry.error) ? retry.error : null} messages={problemLines(retry.error)} />}
          {d.last_error && d.status !== 'booked' && (
            <Banner tone={d.status === 'failed_retryable' ? 'warning' : 'danger'} title={d.last_error.code}>
              {d.last_error.message}
            </Banner>
          )}
          <dl className="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
              <dt className="text-meta text-tertiary">Loan amount</dt>
              <dd className="text-title text-emphasis">
                <MoneyText amount={d.principal.amount} currency={d.principal.currency} />
              </dd>
            </div>
            <div>
              <dt className="text-meta text-tertiary">Fees deducted</dt>
              <dd className="text-title text-emphasis">{d.fees.length === 0 ? '—' : <MoneyText amount={String(d.fees.reduce((a, f) => a + Number(f.amount), 0))} currency={d.net.currency} />}</dd>
            </div>
            <div>
              <dt className="text-meta text-tertiary">Paid out</dt>
              <dd className="text-title text-emphasis">
                <MoneyText amount={d.net.amount} currency={d.net.currency} />
              </dd>
            </div>
            <div>
              <dt className="text-meta text-tertiary">Value date</dt>
              <dd className="text-title text-emphasis">{d.value_date}</dd>
            </div>
          </dl>
          <p className="mt-3 text-body-sm text-secondary">
            To {d.destination.bank_code} / {d.destination.account}
            {d.destination.account_name && ` (${d.destination.account_name})`} · made by {d.made_by?.name ?? 'staff'} <DateTimeText value={d.made_at} />
            {d.released_by && (
              <>
                {' '}
                · released by {d.released_by.name ?? 'staff'} {d.released_at && <DateTimeText value={d.released_at} />}
              </>
            )}
          </p>
          <div className="mt-4 flex flex-wrap gap-2">
            {d.status === 'pending_release' && (
              <>
                <Button leadingIcon={<Send aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabledReason={releaseReason(d, permissions, meId)} onClick={() => onDialog('release')}>
                  Release
                </Button>
                <Button variant="secondary" leadingIcon={<Undo2 aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabledReason={permissions.has(DSB_PERM.make) ? null : `Requires the ${DSB_PERM.make} permission.`} onClick={() => onDialog('cancel')}>
                  Cancel
                </Button>
              </>
            )}
            {(d.status === 'failed_retryable' || d.status === 'failed_intervention') && (
              <Button leadingIcon={<RotateCw aria-hidden="true" className="h-icon-sm w-icon-sm" />} loading={retry.isPending} disabledReason={retryReason(d, permissions)} onClick={() => retry.mutate()}>
                Retry
              </Button>
            )}
            {d.advice && (
              <Button variant="secondary" leadingIcon={<Download aria-hidden="true" className="h-icon-sm w-icon-sm" />} loading={download.isPending} onClick={() => download.mutate()}>
                Disbursement advice
              </Button>
            )}
          </div>
        </section>
        {d.schedule.length > 0 && (
          <section aria-labelledby="dsb-sched" className="rounded-card border bg-surface p-5 shadow-card">
            <CardHeader
              title="Repayment schedule (as booked)"
              titleId="dsb-sched"
              level={2}
              subtitle={d.variance ? `Matches the accepted offer: largest difference ${d.variance.max_difference} (tolerance ${d.variance.tolerance}).` : undefined}
            />
            <Collapsible summary={`${d.schedule.length} instalments from loan account ${d.cba.loan_account_no ?? ''}`}>
              <div className="overflow-x-auto">
                <table className="w-full text-body-sm">
                  <caption className="sr-only">Booked repayment schedule</caption>
                  <thead>
                    <tr className="text-left text-meta text-tertiary">
                      <th scope="col" className="py-1.5 pr-3">No.</th>
                      <th scope="col" className="py-1.5 pr-3">Due</th>
                      <th scope="col" className="py-1.5 pr-3 text-right">Instalment</th>
                      <th scope="col" className="py-1.5 text-right">Balance</th>
                    </tr>
                  </thead>
                  <tbody>
                    {d.schedule.map((r) => (
                      <tr key={r.number} className="border-t">
                        <td className="py-1.5 pr-3 tabular">{r.number}</td>
                        <td className="py-1.5 pr-3 tabular">{r.due_date}</td>
                        <td className="py-1.5 pr-3 text-right">
                          <MoneyText amount={r.total} currency={d.net.currency} />
                        </td>
                        <td className="py-1.5 text-right">
                          <MoneyText amount={r.closing_balance} currency={d.net.currency} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </Collapsible>
          </section>
        )}
        {d.variance && !d.variance.within_tolerance && (
          <Banner tone="danger" title="Schedule variance (LOS-FR-313)">
            <ul className="list-disc pl-5">
              {d.variance.mismatches.map((m) => (
                <li key={`${m.number}-${m.field}`}>
                  Instalment {m.number} {m.field}: offered {m.offered ?? '—'}, booked {m.booked ?? '—'} (difference {m.difference})
                </li>
              ))}
            </ul>
          </Banner>
        )}
      </div>
      <aside aria-label="Booking progress" className="min-w-0">
        <section aria-labelledby="dsb-steps" className="rounded-card border bg-surface p-5 shadow-card xl:sticky xl:top-4">
          <CardHeader title="Booking progress" titleId="dsb-steps" level={2} subtitle={d.saga_runs > 1 ? `${d.saga_runs} runs; completed steps are never repeated` : undefined} />
          {steps.length === 0 ? (
            <p className="text-body-sm text-secondary">{d.status === 'pending_release' ? 'Starts when a checker releases the instruction.' : 'Waiting for the booking job to start.'}</p>
          ) : (
            <ol className="space-y-2">
              {steps.map((s, i) => (
                <li key={`${s.run}-${s.step}-${i}`} className="flex gap-2 text-body-sm">
                  {s.outcome === 'succeeded' || s.outcome === 'skipped' ? (
                    <CheckCircle2 aria-hidden="true" className="mt-0.5 h-icon-sm w-icon-sm shrink-0 text-success" />
                  ) : s.outcome === 'failed' ? (
                    <CircleAlert aria-hidden="true" className="mt-0.5 h-icon-sm w-icon-sm shrink-0 text-danger" />
                  ) : (
                    <CircleDashed aria-hidden="true" className="mt-0.5 h-icon-sm w-icon-sm shrink-0 text-tertiary" />
                  )}
                  <span className="min-w-0">
                    <span className="font-medium text-primary">{stepLabel(s)}</span>
                    {s.outcome === 'skipped' && <span className="text-tertiary"> · nothing to do</span>}
                    {(s.detail as Record<string, unknown>).resolved_by_lookup === true && <span className="block text-meta text-tertiary">Timed out; confirmed by reference lookup, not re-sent.</span>}
                    {s.error_code && <span className="block text-meta text-danger">{s.error_code}</span>}
                    <span className="block text-meta text-tertiary">
                      <DateTimeText value={s.occurred_at} />
                    </span>
                  </span>
                </li>
              ))}
            </ol>
          )}
        </section>
      </aside>
    </div>
  );
}
