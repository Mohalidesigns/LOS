import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { idempotencyKey } from '@/api/client';
import { disbursementApi, type Disbursement } from '@/api/disbursement';
import { isApiProblem } from '@/api/problem';
import { Button, ErrorSummary, Modal, MoneyText, Select, TextArea, TextInput, useToast } from '@/components';
import { problemLines } from '@/features/applications/credit/domain';
import { refreshCase } from '@/features/applications/queries';
import { disbursementKeys } from './queries';

function useDsbMutation(applicationId: string, run: (key: string) => Promise<unknown>, success: string, onDone: () => void) {
  const qc = useQueryClient();
  const toast = useToast();
  const [key] = useState(idempotencyKey);
  return useMutation({
    mutationFn: () => run(key),
    onSuccess: async () => {
      toast.show(success, 'success');
      await Promise.all([refreshCase(qc, applicationId), qc.invalidateQueries({ queryKey: disbursementKeys.exceptions })]);
      onDone();
    },
  });
}

const today = () => new Date().toISOString().slice(0, 10);

/** Maker: the instruction pays the net amount to the account the pre-disbursement check verified. */
export function MakeDialog({ applicationId, onClose }: { applicationId: string; onClose: () => void }) {
  const [valueDate, setValueDate] = useState(today);
  const m = useDsbMutation(applicationId, (key) => disbursementApi.create(applicationId, valueDate || null, key), 'Instruction made. A checker must release it.', onClose);
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title="Make disbursement instruction"
      description="The instruction pays the accepted offer’s amount, less upfront fees, to the account verified by the latest pre-disbursement check. A different person releases it."
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={m.isPending}>
            Cancel
          </Button>
          <Button loading={m.isPending} onClick={() => m.mutate()}>
            Make instruction
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {m.error && <ErrorSummary title="Not made" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
        <TextInput label="Value date" type="date" min={today()} value={valueDate} onChange={(e) => setValueDate(e.target.value)} helper="The repayment schedule starts from this date." />
      </div>
    </Modal>
  );
}

/** Checker: release (or reject) the change request; release re-runs the pre-disbursement check. */
export function ReleaseDialog({ d, onClose }: { d: Disbursement; onClose: () => void }) {
  const [decision, setDecision] = useState<'approve' | 'reject'>('approve');
  const [reason, setReason] = useState('');
  const [touched, setTouched] = useState(false);
  const error = touched && reason.trim().length < 5 ? 'Record your reason (5 characters or more).' : undefined;
  const m = useDsbMutation(d.application_id, (key) => disbursementApi.decideRelease(d.change_request_id ?? '', decision, reason.trim(), key), decision === 'approve' ? 'Released. Booking with the core banking system.' : 'Release rejected.', onClose);
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title={`Release instruction #${d.sequence}`}
      description={
        <>
          Pays <MoneyText amount={d.net.amount} currency={d.net.currency} /> to {d.destination.bank_code} / {d.destination.account}
          {d.destination.account_name && ` (${d.destination.account_name})`} on {d.value_date}. Releasing re-runs the pre-disbursement check; you may be asked to re-authenticate.
        </>
      }
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={m.isPending}>
            Cancel
          </Button>
          <Button
            loading={m.isPending}
            variant={decision === 'reject' ? 'danger' : 'primary'}
            onClick={() => {
              setTouched(true);
              if (reason.trim().length >= 5) m.mutate();
            }}
          >
            {decision === 'approve' ? 'Release' : 'Reject'}
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {m.error && <ErrorSummary title="Not released" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
        <Select
          label="Decision"
          value={decision}
          onChange={(e) => setDecision(e.target.value as 'approve' | 'reject')}
          options={[
            { value: 'approve', label: 'Release the disbursement' },
            { value: 'reject', label: 'Reject the instruction' },
          ]}
        />
        <TextArea label="Reason" required rows={3} value={reason} onChange={(e) => setReason(e.target.value)} error={error} maxLength={2000} />
      </div>
    </Modal>
  );
}

export function CancelDialog({ d, onClose }: { d: Disbursement; onClose: () => void }) {
  const [reason, setReason] = useState('');
  const [touched, setTouched] = useState(false);
  const error = touched && reason.trim().length < 10 ? 'Say why it is withdrawn (10 characters or more).' : undefined;
  const m = useDsbMutation(d.application_id, (key) => disbursementApi.cancel(d.id, reason.trim(), key), 'Instruction cancelled.', onClose);
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title={`Cancel instruction #${d.sequence}`}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={m.isPending}>
            Keep it
          </Button>
          <Button
            loading={m.isPending}
            variant="danger"
            onClick={() => {
              setTouched(true);
              if (reason.trim().length >= 10) m.mutate();
            }}
          >
            Cancel instruction
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {m.error && <ErrorSummary title="Not cancelled" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
        <TextArea label="Reason" required rows={3} value={reason} onChange={(e) => setReason(e.target.value)} error={error} maxLength={2000} />
      </div>
    </Modal>
  );
}
