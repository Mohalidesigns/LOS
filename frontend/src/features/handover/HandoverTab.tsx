import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Archive, Download, FileText, PackageCheck, ShieldCheck } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { handoverApi } from '@/api/handover';
import { documentsApi } from '@/api/lending';
import { isApiProblem } from '@/api/problem';
import { Banner, Button, CardHeader, DateTimeText, ErrorSummary, Modal, MoneyText, Pill, SectionError, Skeleton, TextArea, useToast } from '@/components';
import { useCase } from '@/features/applications/case/CaseContext';
import { problemLines } from '@/features/applications/credit/domain';
import { appKeys } from '@/features/applications/queries';
import { saveBlob, shortHash } from '@/lib/download';
import { DELIVERY_META, HANDOVER_PERM, packageView } from './domain';

const handoverKey = (id: string) => [...appKeys.case(id), 'handover'] as const;

/** SCR-HND-01: what servicing received, the facility.created deliveries, retention, acknowledgement. */
export function HandoverTab() {
  const { app, permissions } = useCase();
  const q = useQuery({ queryKey: handoverKey(app.id), queryFn: () => handoverApi.forApplication(app.id) });
  const [acking, setAcking] = useState(false);
  const download = useMutation({ mutationFn: async (d: { document_id: string; version_id: string; filename: string }) => saveBlob(await documentsApi.content(d.document_id, d.version_id), d.filename) });

  if (q.isPending) return <Skeleton className="h-[20rem] w-full rounded-card" />;
  if (q.isError) return <SectionError error={q.error} title="Handover couldn't load" onRetry={() => void q.refetch()} />;
  const h = q.data;
  if (!h.package) {
    return (
      <Banner tone="info" title={app.status === 'booked' ? 'Handover is being prepared' : 'Handover follows booking'}>
        When the facility is booked, a handover package goes to servicing (terms, booked schedule, document references, open conditions subsequent and insurance) and a signed facility.created event is sent to subscribed systems.
      </Banner>
    );
  }
  const p = h.package;
  const v = packageView(p.content);

  return (
    <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <div className="min-w-0 space-y-5">
        <section aria-labelledby="hnd-pkg" className="rounded-card border bg-surface p-5 shadow-card">
          <CardHeader
            title="Handover package"
            titleId="hnd-pkg"
            level={2}
            subtitle={`Created ${new Date(p.created_at).toLocaleString()} · sha256 ${shortHash(p.sha256)}`}
            actions={p.acknowledgement ? <Pill tone="success">Acknowledged</Pill> : <Pill tone="warning">Awaiting servicing</Pill>}
          />
          <p className="text-body-sm text-secondary">
            Loan account <span className="ref">{v.loanAccountNo ?? '—'}</span>
            {v.maturityDate && <> · matures {v.maturityDate}</>}
          </p>
          {p.acknowledgement ? (
            <p className="mt-2 text-body-sm text-secondary">
              <PackageCheck aria-hidden="true" className="mr-1 inline h-icon-sm w-icon-sm text-success" />
              Acknowledged by {p.acknowledgement.by.name ?? 'servicing'} · <DateTimeText value={p.acknowledgement.at} /> · {p.acknowledgement.note}
            </p>
          ) : (
            <Button className="mt-3" leadingIcon={<PackageCheck aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabledReason={permissions.has(HANDOVER_PERM.acknowledge) ? null : `Requires the ${HANDOVER_PERM.acknowledge} permission.`} onClick={() => setAcking(true)}>
              Acknowledge handover
            </Button>
          )}
        </section>

        <section aria-labelledby="hnd-items" className="rounded-card border bg-surface p-5 shadow-card">
          <CardHeader title="Handed over to servicing" titleId="hnd-items" level={2} />
          <h3 className="mb-1 text-body-sm font-semibold text-primary">Conditions subsequent</h3>
          {v.conditions.length === 0 ? (
            <p className="mb-3 text-body-sm text-secondary">None open.</p>
          ) : (
            <ul className="mb-3 space-y-1 text-body-sm">
              {v.conditions.map((c) => (
                <li key={c.condition_id} className="rounded-control border p-2">
                  {c.description}
                  {c.due_date && <span className="text-meta text-tertiary"> · due {c.due_date}</span>}
                </li>
              ))}
            </ul>
          )}
          <h3 className="mb-1 text-body-sm font-semibold text-primary">Insurance</h3>
          {v.insurance.length === 0 ? (
            <p className="mb-3 text-body-sm text-secondary">None.</p>
          ) : (
            <ul className="mb-3 space-y-1 text-body-sm">
              {v.insurance.map((i) => (
                <li key={i.fee_code} className="flex flex-wrap items-center gap-2 rounded-control border p-2">
                  <ShieldCheck aria-hidden="true" className="h-icon-sm w-icon-sm text-tertiary" />
                  {i.cover} · premium <MoneyText amount={i.premium.amount} currency={i.premium.currency} /> · cover until {i.cover_until}
                </li>
              ))}
            </ul>
          )}
          <h3 className="mb-1 text-body-sm font-semibold text-primary">Collateral</h3>
          <p className="text-body-sm text-secondary">{v.collateralNote ?? 'None.'}</p>
        </section>

        <section aria-labelledby="hnd-docs" className="rounded-card border bg-surface p-5 shadow-card">
          <CardHeader title="Documents" titleId="hnd-docs" level={2} subtitle="Durable references with content hashes; servicing reads them through the API." />
          <ul className="divide-y">
            {v.documents.map((d) => (
              <li key={d.version_id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-body-sm">
                <span className="inline-flex min-w-0 items-center gap-2">
                  <FileText aria-hidden="true" className="h-icon-sm w-icon-sm shrink-0 text-tertiary" />
                  <span className="min-w-0">
                    <span className="font-medium text-primary">{d.title}</span>
                    <span className="block text-meta text-tertiary">
                      {d.type} · <span className="ref">{shortHash(d.sha256)}</span>
                    </span>
                  </span>
                </span>
                <Button size="sm" variant="ghost" leadingIcon={<Download aria-hidden="true" className="h-icon-sm w-icon-sm" />} loading={download.isPending && download.variables.version_id === d.version_id} onClick={() => download.mutate(d)}>
                  Download
                </Button>
              </li>
            ))}
          </ul>
        </section>
      </div>
      <aside aria-label="Events and retention" className="min-w-0 space-y-5">
        <section aria-labelledby="hnd-evt" className="rounded-card border bg-surface p-5 shadow-card">
          <CardHeader title="facility.created" titleId="hnd-evt" level={2} subtitle="Signed webhook deliveries to subscribed systems" />
          {h.deliveries.length === 0 ? (
            <p className="text-body-sm text-secondary">No endpoints are subscribed. An administrator registers them under Webhooks.</p>
          ) : (
            <ul className="space-y-2 text-body-sm">
              {h.deliveries.map((d) => (
                <li key={d.id} className="rounded-control border p-2">
                  <div className="flex items-center justify-between gap-2">
                    <span className="font-medium text-primary">{d.endpoint.name ?? 'Endpoint'}</span>
                    <Pill tone={DELIVERY_META[d.status].tone}>{DELIVERY_META[d.status].label}</Pill>
                  </div>
                  <p className="text-meta text-tertiary">
                    {d.attempts} attempt(s){d.last_http_status !== null && ` · HTTP ${d.last_http_status}`}
                    {d.delivered_at && (
                      <>
                        {' '}
                        · <DateTimeText value={d.delivered_at} />
                      </>
                    )}
                  </p>
                </li>
              ))}
            </ul>
          )}
        </section>
        {h.retention && (
          <section aria-labelledby="hnd-ret" className="rounded-card border bg-surface p-5 shadow-card">
            <CardHeader title="Retention" titleId="hnd-ret" level={2} />
            <p className="flex items-start gap-2 text-body-sm text-secondary">
              <Archive aria-hidden="true" className="mt-0.5 h-icon-sm w-icon-sm shrink-0" />
              <span>
                The origination record is kept until <strong className="text-primary">{h.retention.retain_until}</strong>
                {h.retention.legal_hold && ' and is under legal hold'}. {h.retention.basis}
              </span>
            </p>
          </section>
        )}
      </aside>
      {acking && <AcknowledgeDialog applicationId={app.id} packageId={p.id} onClose={() => setAcking(false)} />}
    </div>
  );
}

function AcknowledgeDialog({ applicationId, packageId, onClose }: { applicationId: string; packageId: string; onClose: () => void }) {
  const qc = useQueryClient();
  const toast = useToast();
  const [note, setNote] = useState('');
  const [touched, setTouched] = useState(false);
  const [key] = useState(idempotencyKey);
  const error = touched && note.trim().length < 5 ? 'Add a note (5 characters or more).' : undefined;
  const m = useMutation({
    mutationFn: () => handoverApi.acknowledge(packageId, note.trim(), key),
    onSuccess: async () => {
      toast.show('Handover acknowledged.', 'success');
      await qc.invalidateQueries({ queryKey: handoverKey(applicationId) });
      onClose();
    },
  });
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title="Acknowledge handover"
      description="Confirm servicing has taken over the facility and the items handed to it."
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={m.isPending}>
            Cancel
          </Button>
          <Button
            loading={m.isPending}
            onClick={() => {
              setTouched(true);
              if (note.trim().length >= 5) m.mutate();
            }}
          >
            Acknowledge
          </Button>
        </>
      }
    >
      {m.error && <ErrorSummary title="Not acknowledged" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
      <TextArea label="Note" required rows={3} value={note} onChange={(e) => setNote(e.target.value)} error={error} maxLength={2000} />
    </Modal>
  );
}
