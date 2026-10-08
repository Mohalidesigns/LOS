import { useState } from 'react';
import { idempotencyKey } from '@/api/client';
import { applicationsApi, type ApplicationAction } from '@/api/lending';
import { isApiProblem } from '@/api/problem';
import { Banner, Button, ErrorSummary, Modal, Select, TextArea, TextInput, useToast } from '@/components';
import { statusMeta } from '@/components/StatusBadge';
import { ReadinessList, useReadiness } from '../credit/ReadinessChecklist';
import { ACTION_LABEL, REASON_REQUIRED, returnTargets } from '../domain/actions';
import { isStaleProblem, useIfMatchMutation } from '../queries';
import type { CaseContextValue } from './CaseContext';

export const REASON_CODE_RE = /^[A-Z0-9_]{2,64}$/;

const REASONS: Partial<Record<ApplicationAction, readonly { value: string; label: string }[]>> = {
  hold: [
    { value: 'AWAITING_CUSTOMER', label: 'Awaiting customer' },
    { value: 'AWAITING_THIRD_PARTY', label: 'Awaiting third party' },
  ],
  withdraw: [
    { value: 'CUSTOMER_REQUEST', label: 'Customer request' },
    { value: 'CUSTOMER_FOUND_ALTERNATIVE', label: 'Customer found alternative funding' },
    { value: 'DUPLICATE_APPLICATION', label: 'Duplicate application' },
  ],
  cancel: [
    { value: 'DUPLICATE_APPLICATION', label: 'Duplicate application' },
    { value: 'DATA_ERROR', label: 'Captured in error' },
    { value: 'POLICY_BREACH', label: 'Policy breach' },
  ],
  return: [
    { value: 'INCOMPLETE_INFORMATION', label: 'Incomplete information' },
    { value: 'DOCUMENT_ISSUE', label: 'Document issue' },
    { value: 'DATA_CORRECTION', label: 'Data correction needed' },
  ],
};

const CONSEQUENCE: Record<ApplicationAction, string> = {
  submit: 'The application is checked for completeness and moves on to pre-qualification and KYC & screening automatically.',
  resubmit: 'The application returns to the stage it was sent back from.',
  resume: 'The application returns to the stage it was paused at and its SLA clock restarts.',
  recommend: 'The application is recommended and routed for approval.',
  hold: 'The application is paused and its SLA clock stops until someone resumes it.',
  return: 'The application goes back to an earlier stage for rework. The originator resubmits it when fixed.',
  withdraw: 'This ends the application at the customer’s request. It can’t be reopened.',
  cancel: 'This ends the application. It can’t be reopened.',
};

/** Blockers from a 422 business-rule problem ("not ready to submit"). */
export function blockersOf(e: unknown): string[] {
  if (!isApiProblem(e)) return [];
  const b = e.extensions.blockers;
  if (!Array.isArray(b)) return [];
  // Strings, or { code, message } objects (credit recommend guard).
  return b
    .map((x: unknown) => {
      if (typeof x === 'string') return x;
      if (typeof x === 'object' && x !== null) {
        const r = x as Record<string, unknown>;
        if (typeof r.message === 'string') return r.message;
        if (typeof r.detail === 'string') return r.detail;
        if (typeof r.code === 'string') return r.code;
      }
      return null;
    })
    .filter((x): x is string => x !== null);
}

export function ActionDialog({ action, ctx, onClose }: { action: ApplicationAction; ctx: CaseContextValue; onClose: () => void }) {
  const toast = useToast();
  const [key] = useState(idempotencyKey);
  const presets = REASONS[action] ?? [];
  const needsReason = REASON_REQUIRED.includes(action);
  const targets = action === 'return' ? returnTargets(ctx.app.status) : [];
  const [code, setCode] = useState(presets[0]?.value ?? '');
  const [customCode, setCustomCode] = useState('');
  const [text, setText] = useState('');
  const [target, setTarget] = useState(targets[targets.length - 1] ?? '');
  const [touched, setTouched] = useState(false);

  const reasonCode = code === 'OTHER' ? customCode.trim().toUpperCase() : code;
  const codeError = needsReason && touched && !REASON_CODE_RE.test(reasonCode) ? 'Use 2–64 capital letters, digits or underscores, for example AWAITING_CUSTOMER.' : undefined;
  const textError = touched && code === 'OTHER' && text.trim().length < 5 ? 'Explain the reason in a few words.' : undefined;

  const mutation = useIfMatchMutation<null>(ctx.app.id, (etag) =>
    applicationsApi.act(ctx.app.id, etag, action, {
      reason_code: needsReason ? reasonCode : null,
      reason_text: text.trim() || null,
      return_to: action === 'return' ? target : null,
    }, key),
    {
      onStale: ctx.markStale,
      onSuccess: (app) => {
        // The response reflects this action only; the server may advance the case further (refetched).
        toast.show(
          action === 'submit'
            ? `${app.reference} submitted. Pre-qualification and KYC screening run automatically.`
            : `${ACTION_LABEL[action]}: ${app.reference} is now ${statusMeta(app.status).label.toLowerCase()}`,
          'success',
        );
        onClose();
      },
    },
  );

  const destructive = action === 'withdraw' || action === 'cancel';
  const blockers = blockersOf(mutation.error);
  const problem = isApiProblem(mutation.error) ? mutation.error : null;

  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!mutation.isPending}
      role={destructive ? 'alertdialog' : 'dialog'}
      title={`${ACTION_LABEL[action]} · ${ctx.app.reference}`}
      description={CONSEQUENCE[action]}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={mutation.isPending}>
            Cancel
          </Button>
          <Button
            variant={destructive ? 'danger' : 'primary'}
            loading={mutation.isPending}
            onClick={() => {
              setTouched(true);
              if ((needsReason && !REASON_CODE_RE.test(reasonCode)) || (code === 'OTHER' && text.trim().length < 5)) return;
              if (action === 'return' && !target) return;
              mutation.mutate(null);
            }}
          >
            {ACTION_LABEL[action]}
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {isStaleProblem(mutation.error) && (
          <Banner tone="warning" title="This application changed since you opened it">
            <p>Reload the latest version, check what changed, then try again. What you typed here is kept.</p>
            <Button
              size="sm"
              variant="secondary"
              className="mt-2"
              onClick={() => {
                mutation.reset();
                void ctx.reload();
              }}
            >
              Reload application
            </Button>
          </Banner>
        )}
        {mutation.error && !isStaleProblem(mutation.error) && (
          <ErrorSummary
            title={blockers.length > 0 ? 'The application is not ready' : 'The action was not completed'}
            problem={problem}
            messages={blockers.length > 0 ? blockers : problem ? undefined : [mutation.error.message]}
          />
        )}
        {action === 'recommend' && <RecommendReadiness ctx={ctx} />}
        {action === 'return' && (
          <Select label="Return to stage" required value={target} onChange={(e) => setTarget(e.target.value)} options={targets.map((t) => ({ value: t, label: statusMeta(t).label }))} />
        )}
        {needsReason && (
          <>
            <Select label="Reason" required value={code} onChange={(e) => setCode(e.target.value)} options={[...presets, { value: 'OTHER', label: 'Other (enter a code)' }]} error={code === 'OTHER' ? undefined : codeError} />
            {code === 'OTHER' && <TextInput label="Reason code" required value={customCode} onChange={(e) => setCustomCode(e.target.value)} error={codeError} helper="Capital letters, digits and underscores" />}
          </>
        )}
        <TextArea label={needsReason ? 'Note' : 'Note (optional)'} value={text} onChange={(e) => setText(e.target.value)} maxLength={1000} error={textError} />
      </div>
    </Modal>
  );
}

/** Pre-empts the server's credit guard: shows what is still missing before Recommend is pressed. */
function RecommendReadiness({ ctx }: { ctx: CaseContextValue }) {
  const { loading, result } = useReadiness(ctx.app);
  if (loading || !result) return null;
  return (
    <section aria-label="Credit readiness" className="rounded-card bg-muted p-3">
      <p className="mb-2 text-body-sm font-semibold text-primary">{result.ready ? 'Credit work is complete' : 'Credit work still to do'}</p>
      <ReadinessList items={result.items} compact />
    </section>
  );
}
