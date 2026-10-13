import { useRef, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { FileUp } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { conditionsApi, type Condition } from '@/api/conditions';
import { isApiProblem } from '@/api/problem';
import { Button, Checkbox, ErrorSummary, Modal, Select, TextArea, TextInput, useToast } from '@/components';
import { refreshCase } from '@/features/applications/queries';
import { problemLines } from '@/features/applications/credit/domain';
import { addErrors, satisfyErrors } from './domain';

function useCaseMutation<T>(applicationId: string, run: (key: string) => Promise<T>, success: string, onDone: () => void) {
  const qc = useQueryClient();
  const toast = useToast();
  const [key] = useState(idempotencyKey);
  return useMutation({
    mutationFn: () => run(key),
    onSuccess: async () => {
      toast.show(success, 'success');
      await refreshCase(qc, applicationId);
      onDone();
    },
  });
}

function Footer({ busy, onClose, onSubmit, label, danger }: { busy: boolean; onClose: () => void; onSubmit: () => void; label: string; danger?: boolean }) {
  return (
    <>
      <Button variant="secondary" onClick={onClose} disabled={busy}>
        Cancel
      </Button>
      <Button loading={busy} variant={danger ? 'danger' : 'primary'} onClick={onSubmit}>
        {label}
      </Button>
    </>
  );
}

const today = () => new Date().toISOString().slice(0, 10);

/** FR-CPR-001: a further CP or CS, with owner (yourself by default) and due date. */
export function AddConditionDialog({ applicationId, meId, onClose }: { applicationId: string; meId: string | null; onClose: () => void }) {
  const [kind, setKind] = useState<'precedent' | 'subsequent'>('precedent');
  const [description, setDescription] = useState('');
  const [mandatory, setMandatory] = useState(true);
  const [mine, setMine] = useState(true);
  const [dueDate, setDueDate] = useState('');
  const [touched, setTouched] = useState(false);
  const errors = touched ? addErrors({ description, dueDate }, today()) : {};
  const m = useCaseMutation(
    applicationId,
    (key) => conditionsApi.add(applicationId, { kind, description: description.trim(), mandatory, owner_id: mine ? meId : null, due_date: dueDate || null }, key),
    'Condition added.',
    onClose,
  );
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title="Add a condition"
      description="Mandatory conditions precedent block disbursement until they are met or waived."
      footer={
        <Footer
          busy={m.isPending}
          onClose={onClose}
          label="Add condition"
          onSubmit={() => {
            setTouched(true);
            if (Object.keys(addErrors({ description, dueDate }, today())).length === 0) m.mutate();
          }}
        />
      }
    >
      <div className="space-y-4">
        {m.error && <ErrorSummary title="Not added" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
        <Select
          label="Type"
          value={kind}
          onChange={(e) => setKind(e.target.value as 'precedent' | 'subsequent')}
          options={[
            { value: 'precedent', label: 'Condition precedent (before disbursement)' },
            { value: 'subsequent', label: 'Condition subsequent (after drawdown)' },
          ]}
        />
        <TextArea label="Description" required rows={3} value={description} onChange={(e) => setDescription(e.target.value)} error={errors.description} maxLength={1000} />
        <Checkbox label="Mandatory" description="A mandatory condition precedent blocks release until it is met or waived." checked={mandatory} onChange={(e) => setMandatory(e.target.checked)} />
        <Checkbox label="I own this condition" checked={mine} onChange={(e) => setMine(e.target.checked)} />
        <TextInput label="Due date (optional)" type="date" value={dueDate} min={today()} onChange={(e) => setDueDate(e.target.value)} error={errors.dueDate} />
      </div>
    </Modal>
  );
}

/** FR-CPR-001: sign off with evidence (file and/or reference). */
export function SatisfyDialog({ condition, onClose }: { condition: Condition; onClose: () => void }) {
  const [note, setNote] = useState('');
  const [reference, setReference] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [touched, setTouched] = useState(false);
  const input = useRef<HTMLInputElement>(null);
  const errors = touched ? satisfyErrors({ note, reference, file }) : {};
  const m = useCaseMutation(condition.application_id, (key) => conditionsApi.satisfy(condition.id, { note: note.trim(), reference: reference.trim() || null, file }, key), 'Condition signed off.', onClose);
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title="Sign off condition"
      description={condition.description}
      footer={
        <Footer
          busy={m.isPending}
          onClose={onClose}
          label="Sign off"
          onSubmit={() => {
            setTouched(true);
            if (Object.keys(satisfyErrors({ note, reference, file })).length === 0) m.mutate();
          }}
        />
      }
    >
      <div className="space-y-4">
        {m.error && <ErrorSummary title="Not signed off" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
        <TextArea label="Sign-off note" required rows={3} value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={2000} />
        <div>
          <input ref={input} type="file" className="sr-only" accept=".pdf,.png,.jpg,.jpeg,.tif,.tiff,.doc,.docx" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
          <Button variant="secondary" leadingIcon={<FileUp aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={() => input.current?.click()}>
            {file ? 'Choose a different file' : 'Attach evidence'}
          </Button>
          <p className="mt-1.5 text-meta text-tertiary" role="status">
            {file ? `Selected: ${file.name}` : 'Scanned for malware and filed under Documents.'}
          </p>
        </div>
        <TextInput label="Evidence reference (optional with a file)" value={reference} onChange={(e) => setReference(e.target.value)} maxLength={200} helper="e.g. registry number, policy number, committee minute" />
        {errors.evidence && <p className="text-meta font-medium text-danger">{errors.evidence}</p>}
      </div>
    </Modal>
  );
}

/** FR-CPR-002: a waiver goes to a checker with condition:waive_approve. */
export function WaiverDialog({ condition, onClose }: { condition: Condition; onClose: () => void }) {
  const [reason, setReason] = useState('');
  const [touched, setTouched] = useState(false);
  const error = touched && reason.trim().length < 10 ? 'Explain why the condition should be waived (10 characters or more).' : undefined;
  const m = useCaseMutation(condition.application_id, (key) => conditionsApi.requestWaiver(condition.id, reason.trim(), key), 'Waiver requested. It needs approval by the waiver authority.', onClose);
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title="Request a waiver"
      description={condition.description}
      footer={
        <Footer
          busy={m.isPending}
          onClose={onClose}
          label="Request waiver"
          onSubmit={() => {
            setTouched(true);
            if (reason.trim().length >= 10) m.mutate();
          }}
        />
      }
    >
      <div className="space-y-4">
        {m.error && <ErrorSummary title="Not requested" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
        <TextArea label="Reason" required rows={3} value={reason} onChange={(e) => setReason(e.target.value)} error={error} maxLength={2000} />
      </div>
    </Modal>
  );
}

/** The checker's side of a waiver: approve or reject the change request (step-up applies). */
export function DecideWaiverDialog({ condition, onClose }: { condition: Condition; onClose: () => void }) {
  const [decision, setDecision] = useState<'approve' | 'reject'>('approve');
  const [reason, setReason] = useState('');
  const [touched, setTouched] = useState(false);
  const error = touched && reason.trim().length < 5 ? 'Record your reason (5 characters or more).' : undefined;
  const crId = condition.waiver?.change_request_id ?? '';
  const m = useCaseMutation(condition.application_id, (key) => conditionsApi.decideWaiver(crId, decision, reason.trim(), key), decision === 'approve' ? 'Waiver approved.' : 'Waiver rejected; the condition stays outstanding.', onClose);
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title="Decide waiver"
      description={
        <>
          {condition.description}
          {condition.waiver?.reason && (
            <>
              <br />
              Requested because: {condition.waiver.reason}
            </>
          )}
        </>
      }
      footer={
        <Footer
          busy={m.isPending}
          danger={decision === 'reject'}
          onClose={onClose}
          label={decision === 'approve' ? 'Approve waiver' : 'Reject waiver'}
          onSubmit={() => {
            setTouched(true);
            if (reason.trim().length >= 5) m.mutate();
          }}
        />
      }
    >
      <div className="space-y-4">
        {m.error && <ErrorSummary title="Not recorded" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
        <Select
          label="Decision"
          value={decision}
          onChange={(e) => setDecision(e.target.value as 'approve' | 'reject')}
          options={[
            { value: 'approve', label: 'Approve the waiver' },
            { value: 'reject', label: 'Reject the waiver' },
          ]}
        />
        <TextArea label="Reason" required rows={3} value={reason} onChange={(e) => setReason(e.target.value)} error={error} maxLength={2000} />
      </div>
    </Modal>
  );
}
