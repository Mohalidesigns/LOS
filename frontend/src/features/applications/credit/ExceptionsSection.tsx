import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ShieldAlert } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { creditApi, type Decision, type ExceptionSeverity } from '@/api/credit';
import type { Application } from '@/api/lending';
import { Button, CardHeader, DateTimeText, ErrorSummary, Modal, Pill, SectionError, Select, Skeleton, TextArea, TextInput, useToast, type Tone } from '@/components';
import { isApiProblem } from '@/api/problem';
import { StaffName } from './CreditParts';
import { appKeys, exceptionsQuery } from '../queries';
import { CREDIT_PERM, firstReason, needPermission, problemLines } from './domain';

const SEVERITY: Record<ExceptionSeverity, { label: string; tone: Tone }> = {
  low: { label: 'Low', tone: 'neutral' },
  medium: { label: 'Medium', tone: 'warning' },
  high: { label: 'High', tone: 'danger' },
};

export function ExceptionsSection({ app, permissions, decision }: { app: Application; permissions: ReadonlySet<string>; decision: Decision | null }) {
  const list = useQuery(exceptionsQuery(app.id));
  const [open, setOpen] = useState(false);
  const reason = firstReason(
    app.status === 'assessment' ? null : 'Exceptions are raised during Assessment.',
    needPermission(permissions, CREDIT_PERM.exceptionRaise),
    decision ? null : 'Run a decision first; an exception is raised against one.',
  );

  return (
    <section aria-labelledby="exc-h" className="rounded-card border bg-surface p-5 shadow-card">
      <CardHeader
        title="Exceptions"
        titleId="exc-h"
        level={2}
        actions={
          <Button size="sm" variant="secondary" leadingIcon={<ShieldAlert aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabledReason={reason} onClick={() => setOpen(true)}>
            Raise exception
          </Button>
        }
      />
      <p className="mb-3 text-body-sm text-secondary">Raising an exception escalates the required approval authority. Each one records the rule, your justification, evidence and severity.</p>
      {list.isPending ? (
        <Skeleton className="h-[5rem] w-full rounded-card" />
      ) : list.isError ? (
        <SectionError error={list.error} title="Exceptions couldn't load" onRetry={() => void list.refetch()} />
      ) : list.data.length === 0 ? (
        <p className="rounded-control bg-subtle p-3 text-body-sm text-secondary">No exceptions raised.</p>
      ) : (
        <ul className="space-y-2">
          {list.data.map((x) => (
            <li key={x.id} className="rounded-control border p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="ref font-semibold text-primary">{x.reason_code}</span>
                <Pill tone={SEVERITY[x.severity].tone}>{`${SEVERITY[x.severity].label} severity`}</Pill>
              </div>
              <p className="mt-1 text-body-sm text-primary">{x.justification}</p>
              <p className="mt-1 text-meta text-tertiary">
                {x.evidence_ref && (
                  <>
                    Evidence <span className="ref">{x.evidence_ref}</span> ·{' '}
                  </>
                )}
                Raised by <StaffName id={x.raised_by} /> · <DateTimeText value={x.raised_at} />
                {decision && x.decision_id !== decision.id && ' · on an earlier decision'}
              </p>
            </li>
          ))}
        </ul>
      )}
      {open && decision && <RaiseDialog app={app} decision={decision} onClose={() => setOpen(false)} />}
    </section>
  );
}

function RaiseDialog({ app, decision, onClose }: { app: Application; decision: Decision; onClose: () => void }) {
  const qc = useQueryClient();
  const toast = useToast();
  const [key] = useState(idempotencyKey);
  const codes = [...new Set(decision.reason_codes.filter((r) => r.stage !== 'grade').map((r) => r.code))];
  const options = codes.length > 0 ? codes : [...new Set(decision.reason_codes.map((r) => r.code))];
  const [code, setCode] = useState(options[0] ?? '');
  const [justification, setJustification] = useState('');
  const [evidence, setEvidence] = useState('');
  const [severity, setSeverity] = useState<ExceptionSeverity>('medium');
  const [touched, setTouched] = useState(false);
  const justError = touched && justification.trim().length < 20 ? 'Explain why the exception is justified (20 characters or more).' : undefined;
  const codeError = touched && !code ? 'Choose the reason code this exception overrides.' : undefined;

  const raise = useMutation({
    mutationFn: () => creditApi.raiseException(decision.id, { reason_code: code, justification: justification.trim(), evidence_ref: evidence.trim() || null, severity }, key),
    onSuccess: async (x) => {
      toast.show(`Exception on ${x.reason_code} recorded. Approval authority escalates.`, 'success');
      await Promise.all([qc.invalidateQueries({ queryKey: appKeys.exceptions(app.id) }), qc.invalidateQueries({ queryKey: appKeys.decisions(app.id) }), qc.invalidateQueries({ queryKey: appKeys.memo(app.id) })]);
      onClose();
    },
  });

  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!raise.isPending}
      title={`Raise a policy exception · decision #${decision.sequence}`}
      description="Raising an exception escalates the required approval authority. The approver sees it in the decision packet."
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={raise.isPending}>
            Cancel
          </Button>
          <Button
            loading={raise.isPending}
            onClick={() => {
              setTouched(true);
              if (!code || justification.trim().length < 20) return;
              raise.mutate();
            }}
          >
            Raise exception
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {raise.error && <ErrorSummary title="The exception was not recorded" problem={isApiProblem(raise.error) ? raise.error : null} messages={problemLines(raise.error)} />}
        <Select
          label="Reason code"
          required
          value={code}
          onChange={(e) => setCode(e.target.value)}
          options={options.length > 0 ? options.map((c) => ({ value: c, label: c })) : [{ value: '', label: 'This decision has no reason codes' }]}
          error={codeError}
          helper="The rule outcome this exception overrides"
        />
        <TextArea label="Justification" required rows={4} value={justification} onChange={(e) => setJustification(e.target.value)} maxLength={2000} error={justError} />
        <TextInput label="Evidence reference (optional)" value={evidence} onChange={(e) => setEvidence(e.target.value)} maxLength={200} helper="Document id, file reference or committee minute" />
        <Select
          label="Severity"
          required
          value={severity}
          onChange={(e) => setSeverity(e.target.value as ExceptionSeverity)}
          options={(Object.keys(SEVERITY) as ExceptionSeverity[]).map((s) => ({ value: s, label: SEVERITY[s].label }))}
        />
      </div>
    </Modal>
  );
}
