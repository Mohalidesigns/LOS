import { useRef, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { FileUp } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { offersApi, type Offer, type OfferSignatory } from '@/api/offers';
import { isApiProblem } from '@/api/problem';
import { Button, ErrorSummary, Modal, Select, TextArea, TextInput, useToast } from '@/components';
import { refreshCase } from '@/features/applications/queries';
import { problemLines } from '@/features/applications/credit/domain';
import { declineErrors, localNow, ROLE_LABEL, signedAtError } from './domain';

function useOfferMutation<T>(offer: Offer, run: (key: string) => Promise<T>, success: string, onDone: () => void) {
  const qc = useQueryClient();
  const toast = useToast();
  const [key] = useState(idempotencyKey);
  return useMutation({
    mutationFn: () => run(key),
    onSuccess: async () => {
      toast.show(success, 'success');
      await refreshCase(qc, offer.application_id);
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

/** FR-OFR-005/009: a wet signature, recorded for the next signatory in order. */
export function SignDialog({ offer, signatory, onClose }: { offer: Offer; signatory: OfferSignatory; onClose: () => void }) {
  const [signedAt, setSignedAt] = useState(localNow);
  const [witness, setWitness] = useState('');
  const [place, setPlace] = useState('');
  const [touched, setTouched] = useState(false);
  const error = touched ? signedAtError(signedAt, offer.issued_at) : null;
  const m = useOfferMutation(
    offer,
    (key) => offersApi.sign(offer.id, signatory.id, { signed_at: new Date(signedAt).toISOString(), witness_name: witness.trim() || null, place: place.trim() || null }, key),
    `Signature recorded for ${signatory.name}.`,
    onClose,
  );
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title={`Record signature · ${signatory.position}. ${signatory.name}`}
      description={`${ROLE_LABEL[signatory.role]}. Record the wet signature on the printed offer; the signed copy is uploaded when everyone has signed.`}
      footer={
        <Footer
          busy={m.isPending}
          onClose={onClose}
          label="Record signature"
          onSubmit={() => {
            setTouched(true);
            if (!signedAtError(signedAt, offer.issued_at)) m.mutate();
          }}
        />
      }
    >
      <div className="space-y-4">
        {m.error && <ErrorSummary title="The signature was not recorded" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
        <TextInput label="Signed at" type="datetime-local" required value={signedAt} onChange={(e) => setSignedAt(e.target.value)} error={error ?? undefined} />
        <TextInput label="Witness (optional)" value={witness} onChange={(e) => setWitness(e.target.value)} maxLength={200} />
        <TextInput label="Place (optional)" value={place} onChange={(e) => setPlace(e.target.value)} maxLength={200} helper="Branch or address where it was signed" />
      </div>
    </Modal>
  );
}

/** FR-OFR-005/010: upload the executed agreement; it is stored immutably with its hash. */
export function AcceptDialog({ offer, onClose }: { offer: Offer; onClose: () => void }) {
  const [file, setFile] = useState<File | null>(null);
  const [touched, setTouched] = useState(false);
  const input = useRef<HTMLInputElement>(null);
  const m = useOfferMutation(offer, (key) => (file ? offersApi.accept(offer.id, file, key) : Promise.reject(new Error('Choose the signed agreement to upload.'))), 'Offer accepted. The executed agreement is on file.', onClose);
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title={`Accept offer #${offer.sequence}`}
      description="Upload the scanned, fully signed agreement. It is malware-scanned, hashed and stored immutably, linked to the template version used."
      footer={
        <Footer
          busy={m.isPending}
          onClose={onClose}
          label="Accept offer"
          onSubmit={() => {
            setTouched(true);
            if (file) m.mutate();
          }}
        />
      }
    >
      <div className="space-y-3">
        {m.error && <ErrorSummary title="The offer was not accepted" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
        <input ref={input} id="executed-file" type="file" className="sr-only" accept=".pdf,.png,.jpg,.jpeg,.tif,.tiff" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
        <Button variant="secondary" leadingIcon={<FileUp aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={() => input.current?.click()}>
          {file ? 'Choose a different file' : 'Choose executed agreement'}
        </Button>
        <p className="text-body-sm text-secondary" role="status">
          {file ? `Selected: ${file.name}` : 'No file selected.'}
        </p>
        {touched && !file && <p className="text-meta font-medium text-danger">Choose the signed agreement to upload.</p>}
      </div>
    </Modal>
  );
}

/** FR-OFR-008: the customer rejects or wants to negotiate; both go back to credit. */
export function DeclineDialog({ offer, onClose }: { offer: Offer; onClose: () => void }) {
  const [outcome, setOutcome] = useState<'reject' | 'negotiate'>('negotiate');
  const [code, setCode] = useState('');
  const [note, setNote] = useState('');
  const [touched, setTouched] = useState(false);
  const errors = touched ? declineErrors(code, note) : {};
  const m = useOfferMutation(
    offer,
    (key) => offersApi.decline(offer.id, { outcome, reason_code: code.trim().toUpperCase(), note: note.trim() }, key),
    'Recorded. The application is back in Assessment for revised terms.',
    onClose,
  );
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title={`Customer response · offer #${offer.sequence}`}
      description="The application returns to Assessment. Revised terms need a new decision, recommendation and approval."
      footer={
        <Footer
          busy={m.isPending}
          danger={outcome === 'reject'}
          onClose={onClose}
          label={outcome === 'reject' ? 'Record rejection' : 'Send back to credit'}
          onSubmit={() => {
            setTouched(true);
            if (Object.keys(declineErrors(code, note)).length === 0) m.mutate();
          }}
        />
      }
    >
      <div className="space-y-4">
        {m.error && <ErrorSummary title="Not recorded" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
        <Select
          label="Customer response"
          value={outcome}
          onChange={(e) => setOutcome(e.target.value as 'reject' | 'negotiate')}
          options={[
            { value: 'negotiate', label: 'Wants to negotiate the terms' },
            { value: 'reject', label: 'Rejects the offer' },
          ]}
        />
        <TextInput label="Reason code" required value={code} onChange={(e) => setCode(e.target.value)} error={errors.reasonCode} helper="e.g. CUST_RATE, CUST_TENOR" />
        <TextArea label="Note" required rows={3} value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={2000} />
      </div>
    </Modal>
  );
}

/** FR-OFR-007: replace an unsigned offer (fresh schedule and validity). */
export function ReissueDialog({ offer, onClose }: { offer: Offer; onClose: () => void }) {
  const [reason, setReason] = useState('');
  const [touched, setTouched] = useState(false);
  const error = touched && reason.trim().length < 10 ? 'Say why the offer is re-issued (10 characters or more).' : undefined;
  const m = useOfferMutation(offer, (key) => offersApi.reissue(offer.id, reason.trim(), key), 'Offer re-issued.', onClose);
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title={`Re-issue offer #${offer.sequence}`}
      description="A new offer replaces this one with a fresh schedule, key facts and validity. Deliver the new letter to the customer."
      footer={
        <Footer
          busy={m.isPending}
          onClose={onClose}
          label="Re-issue"
          onSubmit={() => {
            setTouched(true);
            if (reason.trim().length >= 10) m.mutate();
          }}
        />
      }
    >
      <div className="space-y-4">
        {m.error && <ErrorSummary title="Not re-issued" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
        <TextArea label="Reason" required rows={3} value={reason} onChange={(e) => setReason(e.target.value)} error={error} maxLength={2000} />
      </div>
    </Modal>
  );
}
