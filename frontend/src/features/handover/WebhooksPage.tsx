import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Copy, Plus, RotateCw, Webhook } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { webhooksApi, type RegisteredEndpoint } from '@/api/handover';
import { isApiProblem } from '@/api/problem';
import { Banner, Button, Card, CardHeader, DateTimeText, EmptyState, ErrorSummary, Modal, Pill, SectionError, Skeleton, TextInput, useToast } from '@/components';
import { RequirePermission } from '@/features/auth/RequirePermission';
import { usePermissions } from '@/features/auth/session';
import { problemLines } from '@/features/applications/credit/domain';
import { DELIVERY_META, endpointErrors, HANDOVER_PERM, parseEvents } from './domain';

const keys = { endpoints: ['webhooks', 'endpoints'] as const, deliveries: ['webhooks', 'deliveries'] as const };

/** Outbound webhooks: subscriber endpoints and the delivery log (TRD §10.1). */
export function WebhooksPage() {
  return (
    <RequirePermission anyOf={[HANDOVER_PERM.integrationRead, HANDOVER_PERM.webhookManage]}>
      <Webhooks />
    </RequirePermission>
  );
}

function Webhooks() {
  const permissions = usePermissions();
  const manage = permissions.has(HANDOVER_PERM.webhookManage);
  const qc = useQueryClient();
  const toast = useToast();
  const endpoints = useQuery({ queryKey: keys.endpoints, queryFn: () => webhooksApi.endpoints() });
  const deliveries = useQuery({ queryKey: keys.deliveries, queryFn: () => webhooksApi.deliveries(), refetchInterval: 15_000 });
  const [adding, setAdding] = useState(false);
  const [created, setCreated] = useState<RegisteredEndpoint | null>(null);
  const refresh = () => Promise.all([qc.invalidateQueries({ queryKey: keys.endpoints }), qc.invalidateQueries({ queryKey: keys.deliveries })]);
  const disable = useMutation({ mutationFn: (id: string) => webhooksApi.disable(id, idempotencyKey()), onSuccess: async () => { toast.show('Endpoint disabled.', 'success'); await refresh(); } });
  const redeliver = useMutation({ mutationFn: (id: string) => webhooksApi.redeliver(id, idempotencyKey()), onSuccess: async () => { toast.show('Redelivery queued.', 'success'); await refresh(); } });

  return (
    <div className="space-y-6">
      {created && (
        <Banner tone="warning" title={`Signing secret for ${created.name}`} onDismiss={() => setCreated(null)}>
          <p>Copy it now; it is not shown again. Verify Fundly-Signature as t=&lt;unix&gt;,v1=HMAC-SHA256(secret, "&lt;t&gt;.&lt;body&gt;").</p>
          <p className="mt-2 flex flex-wrap items-center gap-2">
            <code className="ref rounded-control bg-subtle px-2 py-1">{created.secret}</code>
            <Button size="sm" variant="secondary" leadingIcon={<Copy aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={() => void navigator.clipboard.writeText(created.secret)}>
              Copy
            </Button>
          </p>
        </Banner>
      )}
      <Card>
        <CardHeader
          title="Webhook endpoints"
          titleId="wh-ep"
          level={2}
          subtitle="Systems that receive signed events such as facility.created"
          actions={
            <Button leadingIcon={<Plus aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabledReason={manage ? null : `Requires the ${HANDOVER_PERM.webhookManage} permission.`} onClick={() => setAdding(true)}>
              Add endpoint
            </Button>
          }
        />
        {disable.error && <ErrorSummary title="Not disabled" problem={isApiProblem(disable.error) ? disable.error : null} messages={problemLines(disable.error)} />}
        {endpoints.isPending ? (
          <Skeleton className="h-[6rem] w-full rounded-card" />
        ) : endpoints.isError ? (
          <SectionError error={endpoints.error} title="Endpoints couldn't load" onRetry={() => void endpoints.refetch()} />
        ) : endpoints.data.length === 0 ? (
          <EmptyState icon={<Webhook aria-hidden="true" className="h-icon-lg w-icon-lg" />} title="No endpoints" headingLevel={3}>
            Add the servicing system (LMS) to receive facility.created when a loan is booked.
          </EmptyState>
        ) : (
          <ul className="divide-y">
            {endpoints.data.map((e) => (
              <li key={e.id} className="flex flex-wrap items-center justify-between gap-3 py-3 text-body-sm">
                <span className="min-w-0">
                  <span className="font-semibold text-primary">{e.name}</span>
                  <span className="block break-all text-meta text-tertiary">
                    {e.url} · {e.events.join(', ')}
                  </span>
                </span>
                <span className="flex items-center gap-2">
                  <Pill tone={e.status === 'active' ? 'success' : 'neutral'}>{e.status === 'active' ? 'Active' : 'Disabled'}</Pill>
                  {e.status === 'active' && manage && (
                    <Button size="sm" variant="secondary" loading={disable.isPending && disable.variables === e.id} onClick={() => disable.mutate(e.id)}>
                      Disable
                    </Button>
                  )}
                </span>
              </li>
            ))}
          </ul>
        )}
      </Card>
      <Card>
        <CardHeader title="Deliveries" titleId="wh-del" level={2} subtitle="Every attempt is logged; failing deliveries are retried with backoff." />
        {redeliver.error && <ErrorSummary title="Not redelivered" problem={isApiProblem(redeliver.error) ? redeliver.error : null} messages={problemLines(redeliver.error)} />}
        {deliveries.isPending ? (
          <Skeleton className="h-[6rem] w-full rounded-card" />
        ) : deliveries.isError ? (
          <SectionError error={deliveries.error} title="Deliveries couldn't load" onRetry={() => void deliveries.refetch()} />
        ) : deliveries.data.length === 0 ? (
          <p className="text-body-sm text-secondary">Nothing sent yet.</p>
        ) : (
          <ul className="divide-y">
            {deliveries.data.map((d) => (
              <li key={d.id} className="flex flex-wrap items-center justify-between gap-3 py-2 text-body-sm">
                <span className="min-w-0">
                  <span className="font-medium text-primary">{d.event_type}</span> → {d.endpoint.name ?? 'endpoint'}
                  <span className="block text-meta text-tertiary">
                    {d.attempts} attempt(s){d.last_http_status !== null && ` · HTTP ${d.last_http_status}`} · <DateTimeText value={d.created_at} />
                    {d.last_error && d.status !== 'delivered' && ` · ${d.last_error.slice(0, 80)}`}
                  </span>
                </span>
                <span className="flex items-center gap-2">
                  <Pill tone={DELIVERY_META[d.status].tone}>{DELIVERY_META[d.status].label}</Pill>
                  {manage && d.status !== 'pending' && (
                    <Button size="sm" variant="ghost" leadingIcon={<RotateCw aria-hidden="true" className="h-icon-sm w-icon-sm" />} loading={redeliver.isPending && redeliver.variables === d.id} onClick={() => redeliver.mutate(d.id)}>
                      Redeliver
                    </Button>
                  )}
                </span>
              </li>
            ))}
          </ul>
        )}
      </Card>
      {adding && (
        <AddEndpointDialog
          onClose={() => setAdding(false)}
          onCreated={async (e) => {
            setCreated(e);
            setAdding(false);
            await refresh();
          }}
        />
      )}
    </div>
  );
}

function AddEndpointDialog({ onClose, onCreated }: { onClose: () => void; onCreated: (e: RegisteredEndpoint) => Promise<void> }) {
  const [name, setName] = useState('');
  const [url, setUrl] = useState('https://');
  const [events, setEvents] = useState('facility.created');
  const [touched, setTouched] = useState(false);
  const [key] = useState(idempotencyKey);
  const errors = touched ? endpointErrors({ name, url, events }) : {};
  const m = useMutation({ mutationFn: () => webhooksApi.register({ name: name.trim(), url: url.trim(), events: parseEvents(events) }, key), onSuccess: (e) => onCreated(e) });
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title="Add webhook endpoint"
      description="Events are signed with a secret shown once after you add the endpoint."
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={m.isPending}>
            Cancel
          </Button>
          <Button
            loading={m.isPending}
            onClick={() => {
              setTouched(true);
              if (Object.keys(endpointErrors({ name, url, events })).length === 0) m.mutate();
            }}
          >
            Add endpoint
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {m.error && <ErrorSummary title="Not added" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
        <TextInput label="Name" required value={name} onChange={(e) => setName(e.target.value)} error={errors.name} maxLength={120} />
        <TextInput label="URL" required value={url} onChange={(e) => setUrl(e.target.value)} error={errors.url} maxLength={500} />
        <TextInput label="Events" required value={events} onChange={(e) => setEvents(e.target.value)} error={errors.events} helper="Comma-separated, e.g. facility.created; * for every event" />
      </div>
    </Modal>
  );
}

export default WebhooksPage;
