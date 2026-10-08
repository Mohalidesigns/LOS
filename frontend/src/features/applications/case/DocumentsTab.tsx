import { useId, useReducer, useRef, useState, type DragEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Ban, CheckCircle2, Download, FileText, ShieldAlert, ShieldCheck, Upload, XCircle } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { documentsApi, type ChecklistAction, type ChecklistItem, type DocumentRecord, type DocumentVersion } from '@/api/lending';
import { isApiProblem } from '@/api/problem';
import { Banner, Button, DateTimeText, EmptyState, ErrorSummary, Modal, Pill, ProgressBar, SectionError, Skeleton, TextArea, TextInput, useToast, type Tone } from '@/components';
import { cn } from '@/lib/cn';
import { formatBytes, saveBlob, shortHash } from '@/lib/download';
import { PERM } from '../domain/actions';
import { appKeys, checklistQuery, documentsQuery } from '../queries';
import { useCase } from './CaseContext';
import { initialUpload, uploadAnnouncement, uploadReducer } from './uploadState';
import { todayLagos } from '../wizard/schemas';

export const CHECKLIST_STATUS: Record<ChecklistItem['status'], { label: string; tone: Tone }> = {
  not_received: { label: 'Not received', tone: 'neutral' },
  received: { label: 'Received', tone: 'info' },
  under_review: { label: 'Under review', tone: 'info' },
  verified: { label: 'Verified', tone: 'success' },
  rejected: { label: 'Rejected', tone: 'danger' },
  waived: { label: 'Waived', tone: 'success' },
  expired: { label: 'Expired', tone: 'danger' },
};

/** "3 of 5 mandatory items satisfied" + progress + outstanding list. */
export function ChecklistSummary({ summary }: { summary: { mandatory_total: number; mandatory_satisfied: number; outstanding: readonly string[]; complete: boolean } }) {
  const { mandatory_total: total, mandatory_satisfied: done, outstanding, complete } = summary;
  const text = `${done} of ${total} mandatory item${total === 1 ? '' : 's'} satisfied`;
  return (
    <section aria-label="Checklist summary" className="rounded-card bg-muted p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-title text-emphasis">{text}</p>
        {complete ? <Pill tone="success">Checklist complete</Pill> : <Pill tone="warning">{`${outstanding.length} outstanding`}</Pill>}
      </div>
      <ProgressBar className="mt-3" value={total === 0 ? 1 : done / total} label="Mandatory documents satisfied" valueText={text} />
      {outstanding.length > 0 && <p className="mt-2 text-body-sm text-secondary">Outstanding: {outstanding.join(', ')}</p>}
    </section>
  );
}

export function DocumentsTab() {
  const { app } = useCase();
  const checklist = useQuery(checklistQuery(app.id));
  const documents = useQuery(documentsQuery(app.id));

  if (checklist.isPending) {
    return (
      <div className="space-y-4" aria-busy="true">
        <span className="sr-only" role="status">
          Loading documents
        </span>
        <Skeleton className="h-[6rem] w-full rounded-card" />
        <Skeleton className="h-[8rem] w-full rounded-card" />
        <Skeleton className="h-[8rem] w-full rounded-card" />
      </div>
    );
  }
  if (checklist.isError) return <SectionError error={checklist.error} title="The document checklist couldn't load" onRetry={() => void checklist.refetch()} />;

  const docs = documents.data ?? [];
  const byItem = (item: ChecklistItem): DocumentRecord | undefined => docs.find((d) => d.checklist_item_id === item.id) ?? docs.find((d) => d.id === item.document_id);
  const unlinked = docs.filter((d) => !d.checklist_item_id || !checklist.data.items.some((i) => i.id === d.checklist_item_id));

  return (
    <div className="space-y-5">
      <ChecklistSummary summary={checklist.data.summary} />
      {documents.isError && <SectionError error={documents.error} title="Uploaded files couldn't load" onRetry={() => void documents.refetch()} />}
      {checklist.data.items.length === 0 ? (
        <EmptyState title="No documents required" headingLevel={3}>
          This product's checklist has no items for this applicant.
        </EmptyState>
      ) : (
        <ul className="space-y-4">
          {checklist.data.items.map((item) => (
            <li key={item.id}>
              <ChecklistRow item={item} document={byItem(item)} />
            </li>
          ))}
        </ul>
      )}
      {unlinked.length > 0 && (
        <section aria-labelledby="other-docs" className="rounded-card border p-4">
          <h3 id="other-docs" className="text-title text-emphasis">
            Other documents
          </h3>
          <ul className="mt-2 space-y-3">
            {unlinked.map((d) => (
              <li key={d.id}>
                <p className="text-body-sm font-semibold text-primary">{d.title || d.document_type}</p>
                <Versions document={d} />
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}

function ChecklistRow({ item, document }: { item: ChecklistItem; document: DocumentRecord | undefined }) {
  const { app, permissions } = useCase();
  const [dialog, setDialog] = useState<ChecklistAction | null>(null);
  const status = CHECKLIST_STATUS[item.status];
  const canVerify = permissions.has(PERM.docVerify);
  const verifyReason = canVerify ? null : 'Requires the document:verify permission.';
  const waiverPending = item.waiver_change_request_id !== null && item.waiver_change_request_id !== undefined && item.status !== 'waived';
  const hasFile = Boolean(document?.versions.some((v) => v.scan_status === 'clean'));
  const terminal = item.status === 'verified' || item.status === 'waived';

  return (
    <article aria-labelledby={`ci-${item.id}`} className={cn('rounded-card border p-4', item.status === 'rejected' || item.status === 'expired' ? 'border-danger' : '')}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <h3 id={`ci-${item.id}`} className="flex flex-wrap items-center gap-2 text-body font-semibold text-primary">
            <FileText aria-hidden="true" className="h-icon w-icon text-indicator" />
            {item.name}
            <span className="text-meta font-normal text-tertiary">{item.mandatory ? 'Mandatory' : 'Optional'}</span>
          </h3>
          <p className="mt-0.5 text-meta text-tertiary">
            <span className="ref">{item.code}</span>
            {item.valid_until && (
              <>
                {' '}
                · valid until <DateTimeText value={item.valid_until} style="date" />
              </>
            )}
            {item.verified_at && (
              <>
                {' '}
                · verified <DateTimeText value={item.verified_at} />
              </>
            )}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {waiverPending ? <Pill tone="warning">Waiver awaiting approval</Pill> : <Pill tone={status.tone}>{status.label}</Pill>}
        </div>
      </div>
      {item.status === 'rejected' && item.rejection_reason && <p className="mt-2 text-body-sm text-danger">Rejected: {item.rejection_reason}</p>}
      {item.status === 'waived' && item.waiver_reason && <p className="mt-2 text-body-sm text-secondary">Waived: {item.waiver_reason}</p>}
      {waiverPending && (
        <p className="mt-2 text-body-sm text-secondary">
          Waiver requested{item.waiver_reason ? `: ${item.waiver_reason}` : ''}. A user with <span className="ref">{item.waiver_authority}</span> must approve change request{' '}
          <span className="ref">{item.waiver_change_request_id}</span>.
        </p>
      )}

      {document && <Versions document={document} />}

      {!terminal && <Uploader item={item} applicationId={app.id} canUpload={permissions.has(PERM.docUpload)} />}

      {!terminal && (
        <div className="mt-3 flex flex-wrap gap-2">
          <Button size="sm" variant="secondary" leadingIcon={<CheckCircle2 aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabledReason={verifyReason ?? (hasFile ? null : 'Upload a clean file first.')} onClick={() => setDialog('verify')}>
            Verify
          </Button>
          <Button size="sm" variant="secondary" leadingIcon={<XCircle aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabledReason={verifyReason ?? (hasFile ? null : 'Nothing to reject yet.')} onClick={() => setDialog('reject')}>
            Reject
          </Button>
          {!waiverPending && (
            <Button size="sm" variant="ghost" leadingIcon={<Ban aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabledReason={verifyReason} onClick={() => setDialog('waive')}>
              Request waiver
            </Button>
          )}
        </div>
      )}
      {dialog && <ChecklistActionDialog item={item} action={dialog} applicationId={app.id} onClose={() => setDialog(null)} />}
    </article>
  );
}

function Versions({ document }: { document: DocumentRecord }) {
  const toast = useToast();
  const download = useMutation({
    mutationFn: async (v: DocumentVersion) => saveBlob(await documentsApi.content(document.id, v.id), v.filename),
    onError: (e) => toast.show(isApiProblem(e) && e.status === 423 ? 'This version is quarantined and can never be opened.' : `Download failed${isApiProblem(e) && e.correlationId ? ` (reference ${e.correlationId})` : ''}`, 'danger'),
  });
  const versions = [...document.versions].sort((a, b) => b.version_no - a.version_no);
  return (
    <ul className="mt-3 divide-y divide-subtle rounded-control border">
      {versions.map((v) => {
        const infected = v.scan_status === 'infected';
        const dups = v.duplicates.filter((d) => !d.same_application);
        return (
          <li key={v.id} className={cn('flex flex-wrap items-center justify-between gap-3 px-3 py-2', infected && 'bg-danger-wash')}>
            <div className="min-w-0">
              <p className="text-body-sm font-semibold text-primary">
                v{v.version_no} · {v.filename} <span className="font-normal text-tertiary">· {formatBytes(v.size_bytes)}</span>
              </p>
              <p className="text-meta text-tertiary">
                SHA-256 <span className="ref" title={v.sha256}>{shortHash(v.sha256)}</span> · uploaded <DateTimeText value={v.uploaded_at} />
              </p>
              {dups.length > 0 && <p className="text-meta text-warning">Same file as {dups.map((d) => d.application_reference).join(', ')}</p>}
            </div>
            {infected ? (
              <span className="inline-flex items-center gap-1.5 text-body-sm font-semibold text-danger">
                <ShieldAlert aria-hidden="true" className="h-icon-sm w-icon-sm" />
                Quarantined — never readable{v.scan_signature ? ` (${v.scan_signature})` : ''}
              </span>
            ) : (
              <span className="flex items-center gap-2">
                <span className="inline-flex items-center gap-1 text-meta font-semibold text-success">
                  <ShieldCheck aria-hidden="true" className="h-icon-sm w-icon-sm" />
                  Scanned clean
                </span>
                <Button size="sm" variant="ghost" leadingIcon={<Download aria-hidden="true" className="h-icon-sm w-icon-sm" />} loading={download.isPending && download.variables.id === v.id} onClick={() => download.mutate(v)} aria-label={`Download ${v.filename} version ${v.version_no}`}>
                  Download
                </Button>
              </span>
            )}
          </li>
        );
      })}
    </ul>
  );
}

const MAX_BYTES = 20 * 1024 * 1024;

function Uploader({ item, applicationId, canUpload }: { item: ChecklistItem; applicationId: string; canUpload: boolean }) {
  const id = useId();
  const qc = useQueryClient();
  const inputRef = useRef<HTMLInputElement>(null);
  const [state, dispatch] = useReducer(uploadReducer, initialUpload);
  const [over, setOver] = useState(false);
  const busy = state.phase === 'uploading';

  const send = async (file: File) => {
    if (file.size > MAX_BYTES) {
      dispatch({ type: 'start', fileName: file.name, size: file.size });
      dispatch({ type: 'failure', message: `The file is ${formatBytes(file.size)}; the limit is ${formatBytes(MAX_BYTES)}.` });
      return;
    }
    dispatch({ type: 'start', fileName: file.name, size: file.size });
    try {
      const doc = await documentsApi.upload(applicationId, { file, documentType: item.code, checklistItemId: item.id, title: item.name }, idempotencyKey());
      dispatch({ type: 'success', document: doc });
      await Promise.all([qc.invalidateQueries({ queryKey: appKeys.documents(applicationId) }), qc.invalidateQueries({ queryKey: appKeys.checklist(applicationId) }), qc.invalidateQueries({ queryKey: appKeys.detail(applicationId) })]);
    } catch (e) {
      dispatch({ type: 'failure', message: isApiProblem(e) ? e.messages.join(' ') : e instanceof Error ? e.message : 'Upload failed', correlationId: isApiProblem(e) ? e.correlationId : null });
    }
  };

  const onDrop = (e: DragEvent) => {
    e.preventDefault();
    setOver(false);
    const file = e.dataTransfer.files[0];
    if (file && canUpload && !busy) void send(file);
  };

  if (!canUpload) return <p className="mt-3 text-meta text-tertiary">Uploading requires the document:upload permission.</p>;

  return (
    <div className="mt-3">
      {/* Drop target is a pointer convenience; the "Choose file" button is the keyboard path. */}
      {/* eslint-disable-next-line jsx-a11y/no-static-element-interactions */}
      <div
        onDragOver={(e) => {
          e.preventDefault();
          setOver(true);
        }}
        onDragLeave={() => setOver(false)}
        onDrop={onDrop}
        className={cn('flex flex-wrap items-center gap-3 rounded-control border-2 border-dashed px-4 py-3', over ? 'border-accent bg-accent-subtle' : 'border-strong bg-subtle')}
      >
        <Upload aria-hidden="true" className="h-icon w-icon text-indicator" />
        <span className="text-body-sm text-secondary">Drag a file here, or</span>
        <input
          ref={inputRef}
          id={`${id}-file`}
          type="file"
          className="sr-only"
          accept=".pdf,.png,.jpg,.jpeg,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv"
          onChange={(e) => {
            const file = e.target.files?.[0];
            e.target.value = '';
            if (file) void send(file);
          }}
          disabled={busy}
        />
        <Button size="sm" variant="secondary" loading={busy} onClick={() => inputRef.current?.click()} aria-describedby={`${id}-hint`}>
          Choose file for {item.name}
        </Button>
        <span id={`${id}-hint`} className="text-meta text-tertiary">
          PDF, image or office file up to {formatBytes(MAX_BYTES)}
        </span>
      </div>
      <div aria-live="polite" className="mt-2">
        <span className="sr-only">{uploadAnnouncement(state)}</span>
        {state.phase === 'uploading' && (
          <div aria-hidden="true">
            <p className="text-body-sm text-secondary">
              Uploading {state.fileName} ({formatBytes(state.size ?? 0)}): hashing and scanning…
            </p>
            <div className="mt-1 h-2 w-full overflow-hidden rounded-pill bg-accent-fill">
              <div className="h-full w-1/3 animate-shimmer rounded-pill bg-accent-graphic" />
            </div>
          </div>
        )}
        {state.phase === 'clean' && (
          <p className="inline-flex flex-wrap items-center gap-1.5 text-body-sm text-primary">
            <ShieldCheck aria-hidden="true" className="h-icon-sm w-icon-sm text-success" />
            {state.fileName} scanned clean · SHA-256 <span className="ref">{shortHash(state.sha256 ?? '')}</span>
            {state.duplicates.some((d) => !d.same_application) && <span className="text-warning">· same file already on {state.duplicates.map((d) => d.application_reference).join(', ')}</span>}
          </p>
        )}
        {state.phase === 'infected' && (
          <Banner tone="danger" title={`${state.fileName ?? 'File'} quarantined — never readable`}>
            Malware was detected{state.scanSignature ? ` (${state.scanSignature})` : ''}. The file is kept for audit but can never be opened. Ask the customer for a clean copy.
          </Banner>
        )}
        {state.phase === 'error' && (
          <Banner tone="danger" title="Upload failed" onDismiss={() => dispatch({ type: 'reset' })}>
            {state.error}
            {state.correlationId && (
              <>
                {' '}
                Reference <span className="ref">{state.correlationId}</span>
              </>
            )}
          </Banner>
        )}
      </div>
    </div>
  );
}

function ChecklistActionDialog({ item, action, applicationId, onClose }: { item: ChecklistItem; action: ChecklistAction; applicationId: string; onClose: () => void }) {
  const qc = useQueryClient();
  const toast = useToast();
  const [reason, setReason] = useState('');
  const [note, setNote] = useState('');
  const [validUntil, setValidUntil] = useState('');
  const [touched, setTouched] = useState(false);
  const needsReason = action !== 'verify';
  const reasonError = touched && needsReason && reason.trim().length < 5 ? 'Give a reason of at least 5 characters.' : undefined;
  const dateError = touched && validUntil !== '' && validUntil < todayLagos() ? 'The expiry date cannot be in the past.' : undefined;
  const run = useMutation({
    mutationFn: () => documentsApi.actOnItem(item.id, action, action === 'verify' ? { valid_until: validUntil || null, note: note.trim() || null } : { reason: reason.trim() }),
    onSuccess: async (res) => {
      if (res.kind === 'change_request') toast.show(`Waiver requested: awaiting approval (change request ${res.changeRequest.id.slice(0, 8)}…)`, 'info');
      else toast.show(`${item.name}: ${CHECKLIST_STATUS[res.item.status].label.toLowerCase()}`, 'success');
      await Promise.all([qc.invalidateQueries({ queryKey: appKeys.checklist(applicationId) }), qc.invalidateQueries({ queryKey: appKeys.detail(applicationId) })]);
      onClose();
    },
  });
  const title = action === 'verify' ? 'Verify document' : action === 'reject' ? 'Reject document' : 'Request a waiver';
  const description =
    action === 'verify'
      ? 'Confirm the document is genuine, legible and matches the applicant.'
      : action === 'reject'
        ? 'The originator is asked for a new version. Give the reason they will see.'
        : `A waiver needs approval by a user with ${item.waiver_authority}. The item stays outstanding until then.`;
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!run.isPending}
      title={`${title} · ${item.name}`}
      description={description}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={run.isPending}>
            Cancel
          </Button>
          <Button
            variant={action === 'reject' ? 'danger' : 'primary'}
            loading={run.isPending}
            onClick={() => {
              setTouched(true);
              if ((needsReason && reason.trim().length < 5) || (validUntil !== '' && validUntil < todayLagos())) return;
              run.mutate();
            }}
          >
            {action === 'verify' ? 'Mark verified' : action === 'reject' ? 'Reject' : 'Request waiver'}
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {run.error && <ErrorSummary problem={isApiProblem(run.error) ? run.error : null} messages={isApiProblem(run.error) ? undefined : [run.error.message]} />}
        {action === 'verify' ? (
          <>
            <TextInput label="Valid until (optional)" type="date" min={todayLagos()} value={validUntil} onChange={(e) => setValidUntil(e.target.value)} error={dateError} helper="For documents that expire, such as IDs or statements." />
            <TextArea label="Note (optional)" value={note} onChange={(e) => setNote(e.target.value)} />
          </>
        ) : (
          <TextArea label="Reason" required value={reason} onChange={(e) => setReason(e.target.value)} error={reasonError} />
        )}
      </div>
    </Modal>
  );
}
