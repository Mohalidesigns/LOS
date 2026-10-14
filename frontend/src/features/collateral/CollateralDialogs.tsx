import { useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { FileUp } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { collateralApi, type Collateral, type PerfectionStep } from '@/api/collateral';
import { conditionsApi } from '@/api/conditions';
import { isApiProblem } from '@/api/problem';
import { Button, Checkbox, ErrorSummary, Modal, MoneyText, Select, TextArea, TextInput, useToast } from '@/components';
import { problemLines } from '@/features/applications/credit/domain';
import { refreshCase } from '@/features/applications/queries';
import { allocatable, captureErrors, insuranceErrors, METHODS, normaliseMoney, STATES, valuationErrors } from './domain';

export const collateralTypesQuery = {
  queryKey: ['collateral-types'] as const,
  queryFn: () => collateralApi.types(),
  staleTime: 300_000,
};

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

function FilePicker({ file, onFile, prompt }: { file: File | null; onFile: (f: File | null) => void; prompt: string }) {
  const input = useRef<HTMLInputElement>(null);
  return (
    <div>
      <input ref={input} type="file" className="sr-only" accept=".pdf,.png,.jpg,.jpeg,.tif,.tiff,.doc,.docx" onChange={(e) => onFile(e.target.files?.[0] ?? null)} />
      <Button variant="secondary" leadingIcon={<FileUp aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={() => input.current?.click()}>
        {file ? 'Choose a different file' : prompt}
      </Button>
      <p className="mt-1.5 text-meta text-tertiary" role="status">
        {file ? `Selected: ${file.name}` : 'Scanned for malware and filed under Documents.'}
      </p>
    </div>
  );
}

const today = () => new Date().toISOString().slice(0, 10);
const errorBox = (error: unknown, title: string) => (error ? <ErrorSummary title={title} problem={isApiProblem(error) ? error : null} messages={problemLines(error)} /> : null);

/** FR-COL-001: capture an item; its checklist is created and its lending value allocated here. */
export function AddCollateralDialog({ applicationId, applicant, onClose }: { applicationId: string; applicant: { party_id: string; display_name: string }; onClose: () => void }) {
  const types = useQuery(collateralTypesQuery);
  const [typeCode, setTypeCode] = useState('');
  const [description, setDescription] = useState('');
  const [marketValue, setMarketValue] = useState('');
  const [ownerIsApplicant, setOwnerIsApplicant] = useState(true);
  const [ownerName, setOwnerName] = useState('');
  const [state, setState] = useState('');
  const [touched, setTouched] = useState(false);
  const f = { typeCode, description, marketValue, ownerName, ownerIsApplicant };
  const errors = touched ? captureErrors(f) : {};
  const type = types.data?.find((t) => t.code === typeCode);
  const m = useCaseMutation(
    applicationId,
    (key) =>
      collateralApi.create(
        {
          application_id: applicationId,
          type_code: typeCode,
          description: description.trim(),
          market_value: normaliseMoney(marketValue),
          owner_party_id: ownerIsApplicant ? applicant.party_id : null,
          owner_name: ownerIsApplicant ? null : ownerName.trim(),
          state_code: state || null,
        },
        key,
      ),
    'Collateral captured.',
    onClose,
  );
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title="Add collateral"
      description="The type sets the haircut, valuation and insurance needs and the perfection checklist."
      footer={
        <Footer
          busy={m.isPending}
          onClose={onClose}
          label="Add collateral"
          onSubmit={() => {
            setTouched(true);
            if (Object.keys(captureErrors(f)).length === 0) m.mutate();
          }}
        />
      }
    >
      <div className="space-y-4">
        {errorBox(m.error, 'Not captured')}
        <Select
          label="Type"
          required
          value={typeCode}
          onChange={(e) => setTypeCode(e.target.value)}
          error={errors.typeCode}
          options={[
            {
              value: '',
              label: types.isPending ? 'Loading types…' : 'Choose a type',
            },
            ...(types.data ?? []).map((t) => ({
              value: t.code,
              label: t.name,
            })),
          ]}
          helper={
            type
              ? `Haircut ${Number(type.haircut_percent)}%${type.requires_valuation ? ` · valuation valid ${type.valuation_validity_months ?? '—'} months` : ''}${type.requires_insurance ? ' · insurance required' : ''} · ${type.perfection_steps.length} perfection step(s)`
              : undefined
          }
        />
        <TextArea
          label="Description"
          required
          rows={2}
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          error={errors.description}
          maxLength={500}
          helper="Address and title, registration number or serial number"
        />
        <TextInput
          label="Market value (NGN)"
          required
          inputMode="decimal"
          value={marketValue}
          onChange={(e) => setMarketValue(e.target.value)}
          error={errors.marketValue}
          helper="Declared until a valuation is recorded"
        />
        <Checkbox label={`Owned by ${applicant.display_name}`} checked={ownerIsApplicant} onChange={(e) => setOwnerIsApplicant(e.target.checked)} />
        {!ownerIsApplicant && <TextInput label="Owner (third-party security)" required value={ownerName} onChange={(e) => setOwnerName(e.target.value)} error={errors.ownerName} maxLength={200} />}
        {(type?.category === 'real_estate' || type?.category === 'vehicle') && (
          <Select
            label="State"
            value={state}
            onChange={(e) => setState(e.target.value)}
            options={[{ value: '', label: 'Not state-specific' }, ...STATES]}
            helper="Perfection steps can differ by state (e.g. Minister's consent in the FCT)"
          />
        )}
      </div>
    </Modal>
  );
}

/** FR-COL-003: valuer, date, method, amount and expiry, with the report. */
export function ValuationDialog({ applicationId, collateral, onClose }: { applicationId: string; collateral: Collateral; onClose: () => void }) {
  const [valuer, setValuer] = useState('');
  const [date, setDate] = useState(today());
  const [method, setMethod] = useState<(typeof METHODS)[number]['value']>('market_comparison');
  const [marketValue, setMarketValue] = useState('');
  const [forcedSaleValue, setForcedSaleValue] = useState('');
  const [validUntil, setValidUntil] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [touched, setTouched] = useState(false);
  const f = { valuer, date, marketValue, forcedSaleValue };
  const errors = touched ? valuationErrors(f, today()) : {};
  const m = useCaseMutation(
    applicationId,
    (key) =>
      collateralApi.valuation(
        collateral.id,
        {
          valuer: valuer.trim(),
          valuation_date: date,
          method,
          market_value: normaliseMoney(marketValue),
          forced_sale_value: forcedSaleValue.trim() ? normaliseMoney(forcedSaleValue) : null,
          valid_until: validUntil || null,
          note: null,
          file,
        },
        key,
      ),
    'Valuation recorded.',
    onClose,
  );
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title="Record valuation"
      description={collateral.description}
      footer={
        <Footer
          busy={m.isPending}
          onClose={onClose}
          label="Record valuation"
          onSubmit={() => {
            setTouched(true);
            if (Object.keys(valuationErrors(f, today())).length === 0) m.mutate();
          }}
        />
      }
    >
      <div className="space-y-4">
        {errorBox(m.error, 'Not recorded')}
        <TextInput label="Valuer" required value={valuer} onChange={(e) => setValuer(e.target.value)} error={errors.valuer} maxLength={200} helper="Firm and estate surveyor" />
        <div className="grid gap-3 sm:grid-cols-2">
          <TextInput label="Valuation date" type="date" required max={today()} value={date} onChange={(e) => setDate(e.target.value)} error={errors.date} />
          <Select label="Method" value={method} onChange={(e) => setMethod(e.target.value as typeof method)} options={[...METHODS]} />
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <TextInput label="Market value (NGN)" required inputMode="decimal" value={marketValue} onChange={(e) => setMarketValue(e.target.value)} error={errors.marketValue} />
          <TextInput
            label="Forced-sale value (optional)"
            inputMode="decimal"
            value={forcedSaleValue}
            onChange={(e) => setForcedSaleValue(e.target.value)}
            error={errors.forcedSaleValue}
            helper={`Default: market less the ${Number(collateral.haircut_percent)}% haircut`}
          />
        </div>
        <TextInput label="Valid until (optional)" type="date" value={validUntil} min={date} onChange={(e) => setValidUntil(e.target.value)} helper="Default: the type's validity period" />
        <FilePicker file={file} onFile={setFile} prompt="Attach valuation report" />
      </div>
    </Modal>
  );
}

/** FR-COL-006: policy, insurer, sum insured, expiry and the bank's interest. */
export function InsuranceDialog({ applicationId, collateral, onClose }: { applicationId: string; collateral: Collateral; onClose: () => void }) {
  const [policyNo, setPolicyNo] = useState('');
  const [insurer, setInsurer] = useState('');
  const [sumInsured, setSumInsured] = useState('');
  const [start, setStart] = useState(today());
  const [expiry, setExpiry] = useState('');
  const [noted, setNoted] = useState(true);
  const [file, setFile] = useState<File | null>(null);
  const [touched, setTouched] = useState(false);
  const f = { policyNo, insurer, sumInsured, start, expiry };
  const errors = touched ? insuranceErrors(f) : {};
  const m = useCaseMutation(
    applicationId,
    (key) =>
      collateralApi.insurance(
        collateral.id,
        {
          policy_no: policyNo.trim(),
          insurer: insurer.trim(),
          sum_insured: normaliseMoney(sumInsured),
          start_date: start,
          expiry_date: expiry,
          bank_interest_noted: noted,
          file,
        },
        key,
      ),
    'Insurance recorded.',
    onClose,
  );
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title="Record insurance"
      description={collateral.description}
      footer={
        <Footer
          busy={m.isPending}
          onClose={onClose}
          label="Record policy"
          onSubmit={() => {
            setTouched(true);
            if (Object.keys(insuranceErrors(f)).length === 0) m.mutate();
          }}
        />
      }
    >
      <div className="space-y-4">
        {errorBox(m.error, 'Not recorded')}
        <div className="grid gap-3 sm:grid-cols-2">
          <TextInput label="Policy number" required value={policyNo} onChange={(e) => setPolicyNo(e.target.value)} error={errors.policyNo} maxLength={64} />
          <TextInput label="Insurer" required value={insurer} onChange={(e) => setInsurer(e.target.value)} error={errors.insurer} maxLength={200} />
        </div>
        <TextInput
          label="Sum insured (NGN)"
          required
          inputMode="decimal"
          value={sumInsured}
          onChange={(e) => setSumInsured(e.target.value)}
          error={errors.sumInsured}
          helper={
            <>
              Market value <MoneyText amount={collateral.market_value.amount} />
            </>
          }
        />
        <div className="grid gap-3 sm:grid-cols-2">
          <TextInput label="Start date" type="date" required value={start} onChange={(e) => setStart(e.target.value)} />
          <TextInput label="Expiry date" type="date" required min={start} value={expiry} onChange={(e) => setExpiry(e.target.value)} error={errors.expiry} />
        </div>
        <Checkbox label="Bank's interest noted on the policy" description="Without it the policy does not count towards release." checked={noted} onChange={(e) => setNoted(e.target.checked)} />
        <FilePicker file={file} onFile={setFile} prompt="Attach policy schedule" />
      </div>
    </Modal>
  );
}

/** FR-COL-005: how much lending value secures this facility; 0 releases it. */
export function AllocateDialog({ applicationId, collateral, onClose }: { applicationId: string; collateral: Collateral; onClose: () => void }) {
  const current = collateral.allocation.items.find((a) => a.application_id === applicationId && a.status === 'active');
  const [amount, setAmount] = useState(current ? String(Number(current.amount.amount)) : '');
  const [touched, setTouched] = useState(false);
  const max = allocatable(collateral, applicationId);
  const value = normaliseMoney(amount);
  const error = touched ? (!/^\d{1,16}(\.\d{1,4})?$/.test(value) ? 'Enter an amount.' : Number(value) > max ? 'More than the lending value available on this item.' : undefined) : undefined;
  const m = useCaseMutation(applicationId, (key) => collateralApi.allocate(collateral.id, applicationId, value, key), 'Allocation updated.', onClose);
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title="Allocate to this facility"
      description={collateral.description}
      footer={
        <Footer
          busy={m.isPending}
          onClose={onClose}
          label="Save allocation"
          onSubmit={() => {
            setTouched(true);
            if (/^\d{1,16}(\.\d{1,4})?$/.test(value) && Number(value) <= max) m.mutate();
          }}
        />
      }
    >
      <div className="space-y-4">
        {errorBox(m.error, 'Not allocated')}
        <TextInput
          label="Lending value allocated (NGN)"
          inputMode="decimal"
          value={amount}
          onChange={(e) => setAmount(e.target.value)}
          error={error}
          helper={
            <>
              Up to <MoneyText amount={max} /> · 0 releases it from this facility
            </>
          }
        />
        {collateral.allocation.items.filter((a) => a.application_id !== applicationId && a.status === 'active').length > 0 && (
          <ul className="space-y-1 rounded-control bg-subtle p-3 text-meta text-secondary">
            {collateral.allocation.items
              .filter((a) => a.application_id !== applicationId && a.status === 'active')
              .map((a) => (
                <li key={a.id}>
                  Also secures {a.application_reference ?? 'another facility'}: <MoneyText amount={a.amount.amount} />
                </li>
              ))}
          </ul>
        )}
      </div>
    </Modal>
  );
}

/** FR-COL-004: complete a step with evidence (file and/or reference). */
export function CompleteStepDialog({ applicationId, step, onClose }: { applicationId: string; step: PerfectionStep; onClose: () => void }) {
  const [note, setNote] = useState('');
  const [reference, setReference] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [touched, setTouched] = useState(false);
  const errors = touched
    ? {
        note: note.trim().length < 3 ? 'Add a note.' : undefined,
        evidence: !file && !reference.trim() ? 'Attach the evidence or give a reference to it.' : undefined,
      }
    : {};
  const m = useCaseMutation(applicationId, (key) => collateralApi.completeStep(step.id, { note: note.trim(), reference: reference.trim() || null, file }, key), 'Step completed.', onClose);
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title="Complete perfection step"
      description={step.name}
      footer={
        <Footer
          busy={m.isPending}
          onClose={onClose}
          label="Complete"
          onSubmit={() => {
            setTouched(true);
            if (note.trim().length >= 3 && (file || reference.trim())) m.mutate();
          }}
        />
      }
    >
      <div className="space-y-4">
        {errorBox(m.error, 'Not completed')}
        <TextArea label="Note" required rows={2} value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={2000} />
        <FilePicker file={file} onFile={setFile} prompt="Attach evidence" />
        <TextInput
          label="Reference (optional with a file)"
          value={reference}
          onChange={(e) => setReference(e.target.value)}
          maxLength={200}
          helper="e.g. NCR registration number, stamp duty receipt, registry search number"
        />
        {errors.evidence && <p className="text-meta font-medium text-danger">{errors.evidence}</p>}
      </div>
    </Modal>
  );
}

export type WaiverTarget = {
  kind: 'perfection_step' | 'insurance';
  id: string;
  label: string;
};

/** FR-COL-008: the exception route; a checker with condition:waive_approve decides. */
export function CollateralWaiverDialog({ applicationId, target, onClose }: { applicationId: string; target: WaiverTarget; onClose: () => void }) {
  const [reason, setReason] = useState('');
  const [touched, setTouched] = useState(false);
  const error = touched && reason.trim().length < 10 ? 'Explain why (10 characters or more).' : undefined;
  const m = useCaseMutation(applicationId, (key) => collateralApi.requestWaiver(target, reason.trim(), key), 'Waiver requested. It needs approval by the waiver authority.', onClose);
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title="Request a waiver"
      description={target.label}
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
        {errorBox(m.error, 'Not requested')}
        <TextArea label="Reason" required rows={3} value={reason} onChange={(e) => setReason(e.target.value)} error={error} maxLength={2000} />
      </div>
    </Modal>
  );
}

/** The checker's side of a collateral waiver (step-up applies). */
export function DecideCollateralWaiverDialog({
  applicationId,
  changeRequestId,
  label,
  requestedBecause,
  onClose,
}: {
  applicationId: string;
  changeRequestId: string;
  label: string;
  requestedBecause: string | null;
  onClose: () => void;
}) {
  const [decision, setDecision] = useState<'approve' | 'reject'>('approve');
  const [reason, setReason] = useState('');
  const [touched, setTouched] = useState(false);
  const error = touched && reason.trim().length < 5 ? 'Record your reason (5 characters or more).' : undefined;
  const m = useCaseMutation(
    applicationId,
    (key) => conditionsApi.decideWaiver(changeRequestId, decision, reason.trim(), key),
    decision === 'approve' ? 'Waiver approved.' : 'Waiver rejected; the item still blocks release.',
    onClose,
  );
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title="Decide waiver"
      description={
        <>
          {label}
          {requestedBecause && (
            <>
              <br />
              Requested because: {requestedBecause}
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
        {errorBox(m.error, 'Not recorded')}
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
