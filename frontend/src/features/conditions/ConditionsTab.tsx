import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CheckCircle2, ClipboardCheck, Download, Plus, ShieldCheck } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { conditionsApi, type Condition, type PreDisbursementCheck } from '@/api/conditions';
import { documentsApi } from '@/api/lending';
import { isApiProblem } from '@/api/problem';
import { Banner, Button, CardHeader, DateTimeText, ErrorSummary, Pill, SectionError, Skeleton, TextInput, useToast } from '@/components';
import { useCase } from '@/features/applications/case/CaseContext';
import { problemLines } from '@/features/applications/credit/domain';
import { refreshCase } from '@/features/applications/queries';
import { meQuery } from '@/features/auth/session';
import { saveBlob } from '@/lib/download';
import { accountErrors, canDecideWaiver, CHECK_STATUS_META, CONDITION_PERM, grouped, isWorking, STATUS_META, workReason } from './domain';
import { AddConditionDialog, DecideWaiverDialog, SatisfyDialog, WaiverDialog } from './ConditionDialogs';
import { conditionRegisterQuery } from './queries';

type Dialog = { kind: 'add' } | { kind: 'satisfy' | 'waive' | 'decide'; condition: Condition } | null;

/** SCR-CPR-01: the CP/CS register, waivers and the final pre-disbursement check. */
export function ConditionsTab() {
  const { app, permissions } = useCase();
  const register = useQuery(conditionRegisterQuery(app.id));
  const me = useQuery(meQuery);
  const [dialog, setDialog] = useState<Dialog>(null);

  if (register.isPending) return <Skeleton className="h-[20rem] w-full rounded-card" />;
  if (register.isError) return <SectionError error={register.error} title="Conditions couldn't load" onRetry={() => void register.refetch()} />;

  const r = register.data;
  const groups = grouped(r.conditions);
  const addReason = workReason(app.status, permissions);
  const opened = r.conditions.length > 0 || isWorking(app.status) || app.status === 'booked';

  if (!opened) {
    return (
      <Banner tone="info" title="Conditions open when the offer is accepted">
        The approvers’ conditions become tracked items here, with owners, due dates, evidence and sign-off. Mandatory conditions precedent must be met or waived before release.
      </Banner>
    );
  }

  return (
    <div className="space-y-5">
      {r.readiness.ready_for_release ? (
        <Banner tone="success" title="Ready for release">
          Every mandatory condition precedent is met or waived and the latest pre-disbursement check passed.
        </Banner>
      ) : (
        isWorking(app.status) && (
          <Banner tone="warning" title="Release is blocked">
            <ul className="list-disc space-y-0.5 pl-5">
              {r.readiness.blockers.map((b) => (
                <li key={b}>{b}</li>
              ))}
            </ul>
          </Banner>
        )
      )}
      <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div className="min-w-0 space-y-5">
          <section aria-labelledby="cp-h" className="rounded-card border bg-surface p-5 shadow-card">
            <CardHeader
              title="Conditions precedent"
              titleId="cp-h"
              level={2}
              subtitle="Met or waived before disbursement"
              actions={
                <Button size="sm" variant="secondary" leadingIcon={<Plus aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabledReason={addReason} onClick={() => setDialog({ kind: 'add' })}>
                  Add condition
                </Button>
              }
            />
            <ConditionList list={groups.precedent} appStatus={app.status} permissions={permissions} onAct={setDialog} empty="No conditions precedent." />
          </section>
          <section aria-labelledby="cs-h" className="rounded-card border bg-surface p-5 shadow-card">
            <CardHeader title="Conditions subsequent" titleId="cs-h" level={2} subtitle="Tracked after drawdown and handed over to servicing" />
            <ConditionList list={groups.subsequent} appStatus={app.status} permissions={permissions} onAct={setDialog} empty="No conditions subsequent." />
          </section>
        </div>
        <aside aria-label="Pre-disbursement check" className="min-w-0">
          <div className="space-y-5 xl:sticky xl:top-4">
            <CheckPanel applicationId={app.id} appStatus={app.status} permissions={permissions} checks={r.checks} />
          </div>
        </aside>
      </div>
      {dialog?.kind === 'add' && <AddConditionDialog applicationId={app.id} meId={me.data?.id ?? null} onClose={() => setDialog(null)} />}
      {dialog?.kind === 'satisfy' && <SatisfyDialog condition={dialog.condition} onClose={() => setDialog(null)} />}
      {dialog?.kind === 'waive' && <WaiverDialog condition={dialog.condition} onClose={() => setDialog(null)} />}
      {dialog?.kind === 'decide' && <DecideWaiverDialog condition={dialog.condition} onClose={() => setDialog(null)} />}
    </div>
  );
}

function ConditionList({ list, appStatus, permissions, onAct, empty }: { list: Condition[]; appStatus: string; permissions: ReadonlySet<string>; onAct: (d: Dialog) => void; empty: string }) {
  const download = useMutation({ mutationFn: async (c: Condition) => c.evidence?.document_id && c.evidence.version_id && saveBlob(await documentsApi.content(c.evidence.document_id, c.evidence.version_id), 'evidence') });
  if (list.length === 0) return <p className="rounded-control bg-subtle p-3 text-body-sm text-secondary">{empty}</p>;
  return (
    <ul className="space-y-2">
      {list.map((c) => {
        const reason = workReason(appStatus, permissions, c);
        return (
          <li key={c.id} className="rounded-control border p-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
              <p className="min-w-0 flex-1 text-body-sm font-semibold text-primary">{c.description}</p>
              <span className="flex flex-wrap gap-1.5">
                {c.mandatory && c.kind === 'precedent' && <Pill tone="neutral">Mandatory</Pill>}
                {c.is_overdue && <Pill tone="danger">Overdue</Pill>}
                <Pill tone={STATUS_META[c.status].tone}>{STATUS_META[c.status].label}</Pill>
              </span>
            </div>
            <p className="mt-1 text-meta text-tertiary">
              {c.source === 'approval' ? 'Set by the approver' : `Added by ${c.created_by?.name ?? 'staff'}`}
              {c.owner && ` · owner ${c.owner.name ?? 'staff'}`}
              {c.due_date && (
                <>
                  {' '}
                  · due <DateTimeText value={c.due_date} style="date" />
                </>
              )}
            </p>
            {c.signoff && (
              <p className="mt-1 text-meta text-tertiary">
                <CheckCircle2 aria-hidden="true" className="mr-1 inline h-icon-sm w-icon-sm text-success" />
                {c.status === 'waived' ? 'Waived' : 'Signed off'} by {c.signoff.by?.name ?? 'staff'} · <DateTimeText value={c.signoff.at} />
                {c.signoff.note && ` · ${c.signoff.note}`}
                {c.evidence?.reference && ` · ref ${c.evidence.reference}`}
              </p>
            )}
            {c.status === 'waiver_pending' && c.waiver?.reason && <p className="mt-1 text-meta text-tertiary">Waiver requested: {c.waiver.reason}</p>}
            <div className="mt-2 flex flex-wrap gap-2">
              {(c.status === 'open' || c.status === 'waiver_pending') && (
                <>
                  <Button size="sm" disabledReason={reason} onClick={() => onAct({ kind: 'satisfy', condition: c })}>
                    Sign off
                  </Button>
                  <Button size="sm" variant="secondary" disabledReason={reason} onClick={() => onAct({ kind: 'waive', condition: c })}>
                    {c.status === 'waiver_pending' ? 'Request again' : 'Request waiver'}
                  </Button>
                </>
              )}
              {canDecideWaiver(c, permissions) && (
                <Button size="sm" variant="accent" leadingIcon={<ShieldCheck aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={() => onAct({ kind: 'decide', condition: c })}>
                  Decide waiver
                </Button>
              )}
              {c.evidence?.version_id && (
                <Button size="sm" variant="ghost" leadingIcon={<Download aria-hidden="true" className="h-icon-sm w-icon-sm" />} loading={download.isPending} onClick={() => download.mutate(c)}>
                  Evidence
                </Button>
              )}
            </div>
          </li>
        );
      })}
    </ul>
  );
}

function CheckPanel({ applicationId, appStatus, permissions, checks }: { applicationId: string; appStatus: string; permissions: ReadonlySet<string>; checks: PreDisbursementCheck[] }) {
  const qc = useQueryClient();
  const toast = useToast();
  const [bankCode, setBankCode] = useState(checks[0]?.destination.bank_code ?? '');
  const [accountNo, setAccountNo] = useState('');
  const [touched, setTouched] = useState(false);
  const [key, setKey] = useState(idempotencyKey);
  const errors = touched ? accountErrors(bankCode, accountNo) : {};
  const run = useMutation({
    mutationFn: () => conditionsApi.check(applicationId, bankCode.trim(), accountNo.trim(), key),
    onSuccess: async (c) => {
      toast.show(c.passed ? 'Pre-disbursement check passed.' : 'Pre-disbursement check recorded with failures.', c.passed ? 'success' : 'info');
      setKey(idempotencyKey());
      await refreshCase(qc, applicationId);
    },
  });
  const reason = appStatus === 'conditions_precedent' || appStatus === 'ready_for_disbursement' ? (permissions.has(CONDITION_PERM.manage) ? null : `Requires the ${CONDITION_PERM.manage} permission.`) : 'The check runs after acceptance and before release.';
  const latest = checks[0];
  return (
    <section aria-labelledby="pdc-h" className="rounded-card border bg-surface p-5 shadow-card">
      <CardHeader title="Pre-disbursement check" titleId="pdc-h" level={2} subtitle="KYC, re-screening, documents, CPs, approval and the destination account" />
      {run.error && <ErrorSummary title="The check did not run" problem={isApiProblem(run.error) ? run.error : null} messages={problemLines(run.error)} />}
      <div className="grid gap-3 sm:grid-cols-[7rem_minmax(0,1fr)] xl:grid-cols-[7rem_minmax(0,1fr)]">
        <TextInput label="Bank code" inputMode="numeric" value={bankCode} onChange={(e) => setBankCode(e.target.value)} error={errors.bankCode} />
        <TextInput label="Account number" inputMode="numeric" value={accountNo} onChange={(e) => setAccountNo(e.target.value)} error={errors.accountNo} helper="10-digit NUBAN; the name is checked against the borrower" />
      </div>
      <Button
        className="mt-3 w-full"
        leadingIcon={<ClipboardCheck aria-hidden="true" className="h-icon-sm w-icon-sm" />}
        disabledReason={reason}
        loading={run.isPending}
        onClick={() => {
          setTouched(true);
          if (Object.keys(accountErrors(bankCode, accountNo)).length === 0) run.mutate();
        }}
      >
        Run check
      </Button>
      {latest && (
        <div className="mt-4 border-t pt-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="text-body-sm font-semibold text-primary">
              Check #{latest.sequence} · <DateTimeText value={latest.checked_at} />
            </p>
            <Pill tone={latest.passed ? (latest.is_current ? 'success' : 'warning') : 'danger'}>{latest.passed ? (latest.is_current ? 'Passed' : 'Passed · out of date') : 'Failed'}</Pill>
          </div>
          <p className="mt-1 text-meta text-tertiary">
            {latest.destination.bank_code} / {latest.destination.account}
            {latest.destination.account_name && ` · ${latest.destination.account_name}`}
            {latest.destination.name_match_score !== null && ` (${latest.destination.name_match_score}% match)`} · by {latest.checked_by?.name ?? 'staff'}
          </p>
          <ul className="mt-3 space-y-2">
            {latest.items.map((i) => (
              <li key={i.code} className="text-body-sm">
                <div className="flex items-center justify-between gap-2">
                  <span className="font-medium text-primary">{i.label}</span>
                  <Pill tone={CHECK_STATUS_META[i.status].tone}>{CHECK_STATUS_META[i.status].label}</Pill>
                </div>
                <p className="text-meta text-tertiary">{i.detail}</p>
              </li>
            ))}
          </ul>
          {checks.length > 1 && <p className="mt-3 text-meta text-tertiary">{checks.length - 1} earlier check(s) on record.</p>}
        </div>
      )}
    </section>
  );
}
