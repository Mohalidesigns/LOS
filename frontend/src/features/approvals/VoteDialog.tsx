import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2 } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { approvalsApi, type ApprovalRequest, type VoteDecision } from '@/api/approvals';
import { isApiProblem } from '@/api/problem';
import { Button, ErrorSummary, IconButton, Modal, MoneyText, Select, TextArea, TextInput, useToast } from '@/components';
import { refreshCase } from '@/features/applications/queries';
import { problemLines } from '@/features/applications/credit/domain';
import { approvalKeys } from './queries';
import { DECISION_META, EMPTY_VOTE, hasErrors, needsReasonCodes, validateVote, voteBody, type VoteErrors, type VoteForm } from './domain';

/**
 * SCR-APV-02 vote. The server re-checks authority, SoD and step-up; a
 * step-up demand opens the re-authentication dialog and the vote is retried.
 */
export function VoteDialog({ request, onClose }: { request: ApprovalRequest; onClose: () => void }) {
  const qc = useQueryClient();
  const toast = useToast();
  const [key] = useState(idempotencyKey);
  const [form, setForm] = useState<VoteForm>(EMPTY_VOTE);
  const [errors, setErrors] = useState<VoteErrors>({});
  const level = request.current_level === null ? null : request.levels[request.current_level];
  const set = <K extends keyof VoteForm>(k: K, v: VoteForm[K]) => setForm((f) => ({ ...f, [k]: v }));

  const vote = useMutation({
    mutationFn: () => approvalsApi.vote(request.id, voteBody(form), key),
    onSuccess: async (r) => {
      const outcome = r.status === 'pending' ? `Vote recorded. ${r.levels[r.current_level ?? 0]?.code ?? 'The next level'} votes next.` : `Application ${DECISION_META[form.decision].verb}.`;
      toast.show(outcome, 'success');
      await Promise.all([
        refreshCase(qc, request.application_id),
        qc.invalidateQueries({ queryKey: approvalKeys.request(request.id) }),
        qc.invalidateQueries({ queryKey: approvalKeys.inbox }),
      ]);
      onClose();
    },
  });

  const submit = () => {
    const e = validateVote(form, request.exposure.amount);
    setErrors(e);
    if (!hasErrors(e)) vote.mutate();
  };

  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!vote.isPending}
      title={`Vote at ${level?.code ?? ''} · ${level?.name ?? ''}`}
      description={
        <>
          Exposure <MoneyText amount={request.exposure.amount} currency={request.exposure.currency} />. Your vote records your rationale and the authority it relies on.
          {request.my_eligibility.step_up_required && ' This amount needs a recent re-authentication; you may be asked to confirm your password and code.'}
        </>
      }
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={vote.isPending}>
            Cancel
          </Button>
          <Button loading={vote.isPending} variant={form.decision === 'decline' ? 'danger' : 'primary'} onClick={submit}>
            {`Record ${DECISION_META[form.decision].label.toLowerCase()}`}
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {vote.error && <ErrorSummary title="Your vote was not recorded" problem={isApiProblem(vote.error) ? vote.error : null} messages={problemLines(vote.error)} />}
        <Select
          label="Decision"
          required
          value={form.decision}
          onChange={(e) => set('decision', e.target.value as VoteDecision)}
          options={(Object.keys(DECISION_META) as VoteDecision[]).map((d) => ({ value: d, label: DECISION_META[d].label }))}
        />
        <TextArea label="Rationale" required rows={4} maxLength={4000} value={form.rationale} onChange={(e) => set('rationale', e.target.value)} error={errors.rationale} />
        <TextInput
          label={needsReasonCodes(form.decision) ? 'Reason codes' : 'Reason codes (optional)'}
          required={needsReasonCodes(form.decision)}
          value={form.reasonCodes}
          onChange={(e) => set('reasonCodes', e.target.value)}
          helper="Separate codes with commas, e.g. COND_GUARANTEE, DEC_SECTOR_RISK"
          error={errors.reasonCodes}
        />
        {form.decision === 'approve_with_conditions' && (
          <fieldset className="space-y-3">
            <legend className="text-body-sm font-semibold text-primary">Conditions</legend>
            <p className="text-meta text-tertiary">Conditions precedent must be met before disbursement; conditions subsequent are tracked after booking.</p>
            {form.conditions.map((c, i) => (
              <div key={i} className="grid gap-2 rounded-control border p-3 sm:grid-cols-[11rem_minmax(0,1fr)_auto] sm:items-start">
                <Select
                  label={`Condition ${i + 1} type`}
                  hideLabel
                  value={c.kind}
                  onChange={(e) => set('conditions', form.conditions.map((x, j) => (j === i ? { ...x, kind: e.target.value as 'precedent' | 'subsequent' } : x)))}
                  options={[
                    { value: 'precedent', label: 'Precedent (CP)' },
                    { value: 'subsequent', label: 'Subsequent (CS)' },
                  ]}
                />
                <TextInput
                  label={`Condition ${i + 1}`}
                  hideLabel
                  value={c.description}
                  onChange={(e) => set('conditions', form.conditions.map((x, j) => (j === i ? { ...x, description: e.target.value } : x)))}
                  error={errors.conditionRows?.[i]}
                  maxLength={1000}
                />
                <IconButton label={`Remove condition ${i + 1}`} icon={<Trash2 aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={() => set('conditions', form.conditions.filter((_, j) => j !== i))} />
              </div>
            ))}
            {errors.conditions && <p className="text-meta font-medium text-danger">{errors.conditions}</p>}
            <Button size="sm" variant="secondary" leadingIcon={<Plus aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={() => set('conditions', [...form.conditions, { kind: 'precedent', description: '' }])}>
              Add condition
            </Button>
          </fieldset>
        )}
        {form.decision === 'counter_offer' && (
          <div className="grid gap-3 sm:grid-cols-2">
            <TextInput label={`Counter amount (${request.exposure.currency})`} required inputMode="decimal" value={form.counterAmount} onChange={(e) => set('counterAmount', e.target.value)} error={errors.counterAmount} />
            <TextInput label="Tenor (months)" required inputMode="numeric" value={form.counterTenor} onChange={(e) => set('counterTenor', e.target.value)} error={errors.counterTenor} />
          </div>
        )}
      </div>
    </Modal>
  );
}
