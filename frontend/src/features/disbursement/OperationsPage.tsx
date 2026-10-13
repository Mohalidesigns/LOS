import { useState } from 'react';
import { Link } from 'react-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Scale } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { disbursementApi, type ReconciliationBreak } from '@/api/disbursement';
import { isApiProblem } from '@/api/problem';
import { Button, Card, CardHeader, DateTimeText, EmptyState, ErrorSummary, Modal, MoneyText, Pill, SectionError, Skeleton, TextArea, useToast } from '@/components';
import { RequirePermission } from '@/features/auth/RequirePermission';
import { usePermissions } from '@/features/auth/session';
import { problemLines } from '@/features/applications/credit/domain';
import { DSB_PERM, STATUS_META } from './domain';
import { breaksQuery, disbursementKeys, exceptionsQuery, runsQuery } from './queries';

const KIND_LABEL: Record<ReconciliationBreak['kind'], string> = {
  missing_in_los: 'In the CBA, not in the LOS',
  missing_in_cba: 'In the LOS, not in the CBA',
  amount_mismatch: 'Amounts differ',
};

/** Disbursement exception queue and daily reconciliation (FR-DSB-007/008, PR-08). */
export function OperationsPage() {
  return (
    <RequirePermission anyOf={[DSB_PERM.check, DSB_PERM.reconcile]}>
      <Operations />
    </RequirePermission>
  );
}

function Operations() {
  const permissions = usePermissions();
  return (
    <div className="space-y-6">
      {permissions.has(DSB_PERM.check) && <Exceptions />}
      {permissions.has(DSB_PERM.reconcile) && <Reconciliation />}
    </div>
  );
}

function Exceptions() {
  const q = useQuery(exceptionsQuery);
  return (
    <Card>
      <CardHeader title="Disbursement exceptions" titleId="ops-exc" level={2} subtitle="Instructions that failed or were compensated. Open the case to retry or raise a new instruction." />
      {q.isPending ? (
        <Skeleton className="h-[8rem] w-full rounded-card" />
      ) : q.isError ? (
        <SectionError error={q.error} title="Exceptions couldn't load" onRetry={() => void q.refetch()} />
      ) : q.data.length === 0 ? (
        <p className="rounded-control bg-subtle p-3 text-body-sm text-secondary">No disbursement exceptions.</p>
      ) : (
        <ul className="divide-y">
          {q.data.map((d) => (
            <li key={d.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
              <div className="min-w-0">
                <Link to={`/applications/${d.application_id}/disbursement`} className="ref font-semibold text-link hover:underline">
                  {d.application_reference}
                </Link>{' '}
                <span className="text-body-sm text-secondary">instruction #{d.sequence}</span>
                <p className="text-meta text-tertiary">
                  {d.last_error?.code ?? '—'} · <MoneyText amount={d.net.amount} currency={d.net.currency} />
                </p>
              </div>
              <Pill tone={STATUS_META[d.status].tone}>{STATUS_META[d.status].label}</Pill>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

function Reconciliation() {
  const qc = useQueryClient();
  const toast = useToast();
  const runs = useQuery(runsQuery);
  const breaks = useQuery(breaksQuery);
  const [key, setKey] = useState(idempotencyKey);
  const [resolving, setResolving] = useState<ReconciliationBreak | null>(null);
  const run = useMutation({
    mutationFn: () => disbursementApi.reconcile(key),
    onSuccess: async (r) => {
      setKey(idempotencyKey());
      toast.show(r.breaks === 0 ? `Reconciled: ${r.matched} posting(s) matched, no breaks.` : `${r.breaks} break(s) found.`, r.breaks === 0 ? 'success' : 'info');
      await Promise.all([qc.invalidateQueries({ queryKey: disbursementKeys.runs }), qc.invalidateQueries({ queryKey: disbursementKeys.breaks })]);
    },
  });
  return (
    <Card>
      <CardHeader
        title="Reconciliation"
        titleId="ops-rec"
        level={2}
        subtitle="LOS disbursement and fee postings against the CBA, matched by posting reference. Runs daily at 02:00."
        actions={
          <Button leadingIcon={<Scale aria-hidden="true" className="h-icon-sm w-icon-sm" />} loading={run.isPending} onClick={() => run.mutate()}>
            Run now
          </Button>
        }
      />
      {run.error && <ErrorSummary title="Reconciliation did not run" problem={isApiProblem(run.error) ? run.error : null} messages={problemLines(run.error)} />}
      <h3 className="mb-2 text-body font-semibold text-primary">Open breaks</h3>
      {breaks.isPending ? (
        <Skeleton className="h-[5rem] w-full rounded-card" />
      ) : breaks.isError ? (
        <SectionError error={breaks.error} title="Breaks couldn't load" onRetry={() => void breaks.refetch()} />
      ) : breaks.data.length === 0 ? (
        <EmptyState title="No open breaks" headingLevel={3} />
      ) : (
        <ul className="space-y-2">
          {breaks.data.map((b) => (
            <li key={b.id} className="flex flex-wrap items-center justify-between gap-2 rounded-control border p-3 text-body-sm">
              <span className="min-w-0">
                <span className="font-semibold text-primary">{KIND_LABEL[b.kind]}</span> · <span className="ref">{b.posting_ref ?? b.reference}</span>
                {b.account_no && ` · account ${b.account_no}`}
                <span className="block text-meta text-tertiary">
                  LOS {b.los_amount ? <MoneyText amount={b.los_amount} currency={b.currency ?? 'NGN'} /> : '—'} · CBA {b.cba_amount ? <MoneyText amount={b.cba_amount} currency={b.currency ?? 'NGN'} /> : '—'}
                </span>
              </span>
              <Button size="sm" variant="secondary" onClick={() => setResolving(b)}>
                Resolve
              </Button>
            </li>
          ))}
        </ul>
      )}
      <h3 className="mb-2 mt-5 text-body font-semibold text-primary">Recent runs</h3>
      {runs.isPending ? (
        <Skeleton className="h-[5rem] w-full rounded-card" />
      ) : runs.isError ? (
        <SectionError error={runs.error} title="Runs couldn't load" onRetry={() => void runs.refetch()} />
      ) : runs.data.length === 0 ? (
        <p className="text-body-sm text-secondary">No runs yet.</p>
      ) : (
        <ul className="divide-y text-body-sm">
          {runs.data.map((r) => (
            <li key={r.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
              <span>
                {r.from} → {r.to} · run <DateTimeText value={r.ran_at} /> · LOS {r.los_postings} / CBA {r.cba_postings} · {r.matched} matched
              </span>
              <Pill tone={r.breaks === 0 ? 'success' : 'danger'}>{r.breaks === 0 ? 'Clean' : `${r.breaks} break(s)`}</Pill>
            </li>
          ))}
        </ul>
      )}
      {resolving && <ResolveDialog b={resolving} onClose={() => setResolving(null)} />}
    </Card>
  );
}

function ResolveDialog({ b, onClose }: { b: ReconciliationBreak; onClose: () => void }) {
  const qc = useQueryClient();
  const [note, setNote] = useState('');
  const [touched, setTouched] = useState(false);
  const [key] = useState(idempotencyKey);
  const error = touched && note.trim().length < 10 ? 'Record what the investigation found (10 characters or more).' : undefined;
  const m = useMutation({
    mutationFn: () => disbursementApi.resolveBreak(b.id, note.trim(), key),
    onSuccess: async () => {
      await qc.invalidateQueries({ queryKey: disbursementKeys.breaks });
      onClose();
    },
  });
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title="Resolve break"
      description={`${KIND_LABEL[b.kind]} · ${b.posting_ref ?? b.reference}`}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={m.isPending}>
            Cancel
          </Button>
          <Button
            loading={m.isPending}
            onClick={() => {
              setTouched(true);
              if (note.trim().length >= 10) m.mutate();
            }}
          >
            Resolve
          </Button>
        </>
      }
    >
      {m.error && <ErrorSummary title="Not resolved" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
      <TextArea label="Resolution" required rows={3} value={note} onChange={(e) => setNote(e.target.value)} error={error} maxLength={2000} />
    </Modal>
  );
}

export default OperationsPage;
