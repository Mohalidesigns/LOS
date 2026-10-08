import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { History } from 'lucide-react';
import { applicationsApi, type ApplicationEvent } from '@/api/lending';
import { isApiProblem } from '@/api/problem';
import { ActivityTimeline, Button, CardHeader, ErrorSummary, KeyValueGrid, MoneyText, SectionError, Skeleton, StatusBadge, TextInput } from '@/components';
import { statusMeta } from '@/components/StatusBadge';
import { formatDateTime } from '@/lib/format';
import { useSession } from '@/features/auth/session';
import { timelineQuery } from '../queries';
import { useCase } from './CaseContext';

const ACTION_VERB: Record<string, string> = {
  submit: 'submitted the application',
  resubmit: 'resubmitted the application',
  resume: 'resumed the application',
  hold: 'put the application on hold',
  return: 'returned the application for rework',
  withdraw: 'withdrew the application',
  cancel: 'cancelled the application',
  recommend: 'recommended the application',
};

function str(v: unknown): string | null {
  return typeof v === 'string' && v !== '' ? v : null;
}

/** Actor display: the API returns ids only (see report), so name "You" / the system and shorten other ids. */
export function actorLabel(actor: ApplicationEvent['actor'], myId: string): string {
  if (actor.type === 'system') return actor.id === 'system:workflow' ? 'Workflow engine' : 'System';
  if (actor.id === myId) return 'You';
  return `Staff user ${actor.id.slice(-6)}`;
}

/** Human sentence for a timeline event (actor is rendered separately). */
export function describeEvent(e: ApplicationEvent): string {
  const p = e.payload as Record<string, unknown>;
  switch (e.type) {
    case 'application.created':
      return `created draft ${str(p.reference) ?? ''} for ${str(p.primary_party_name) ?? 'the applicant'} (${str(p.product_name) ?? 'product'})`.replace(/\s+/g, ' ');
    case 'application.status_changed': {
      const to = str(p.to);
      const from = str(p.from);
      const action = str(p.action);
      const code = str(p.reason_code);
      const text = str(p.reason_text);
      const base = action && ACTION_VERB[action] ? ACTION_VERB[action] : `moved the application ${from ? `from ${statusMeta(from).label} ` : ''}to ${to ? statusMeta(to).label : 'a new stage'}`;
      const tail = action && to ? ` → ${statusMeta(to).label}` : '';
      return `${base}${tail}${code ? ` · ${code}` : ''}${text ? ` — ${text}` : ''}`;
    }
    case 'application.amended': {
      const fields = p.changes && typeof p.changes === 'object' ? Object.keys(p.changes) : Array.isArray(p.fields) ? p.fields.filter((f): f is string => typeof f === 'string') : [];
      return fields.length > 0 ? `amended ${fields.map((f) => f.replace(/_/g, ' ')).join(', ')}` : 'amended captured fields';
    }
    case 'application.applicant_added':
      return `added ${str(p.display_name) ?? 'an applicant'} as ${(str(p.role) ?? 'applicant').replace('_', ' ')}`;
    case 'application.applicant_removed':
      return `removed ${str(p.display_name) ?? 'an applicant'}`;
    default:
      return e.type.replace(/^application\./, '').replace(/[._]/g, ' ');
  }
}

/** A datetime-local value read as Africa/Lagos wall-clock time (UTC+1, no DST) → ISO 8601. */
export function lagosLocalToIso(local: string): string {
  return new Date(`${local.length === 16 ? `${local}:00` : local}+01:00`).toISOString();
}

export function TimelineTab() {
  const { app } = useCase();
  const { me } = useSession();
  const timeline = useQuery(timelineQuery(app.id));

  return (
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
      <section aria-labelledby="tl-h" className="min-w-0">
        <CardHeader title="Timeline" titleId="tl-h" subtitle={timeline.data ? `${timeline.data.length} events · immutable, event-sourced history` : undefined} />
        {timeline.isPending && <Skeleton className="h-[12rem] w-full rounded-card" />}
        {timeline.isError && <SectionError error={timeline.error} onRetry={() => void timeline.refetch()} />}
        {timeline.data && (
          <ActivityTimeline
            items={[...timeline.data].reverse().map((e) => ({
              id: String(e.version),
              actor: actorLabel(e.actor, me.id),
              action: describeEvent(e),
              at: e.occurred_at,
            }))}
          />
        )}
      </section>
      <AsAtPanel applicationId={app.id} />
    </div>
  );
}

function AsAtPanel({ applicationId }: { applicationId: string }) {
  const [value, setValue] = useState('');
  const [touched, setTouched] = useState(false);
  const replay = useMutation({ mutationFn: (iso: string) => applicationsApi.asAt(applicationId, iso) });
  const error = touched && value === '' ? 'Choose a date and time.' : undefined;
  const state = replay.data?.state as Record<string, unknown> | null | undefined;
  const status = state ? str(state.status) : null;
  const amount = state?.requested_amount;
  const amountText = typeof amount === 'string' ? amount : amount && typeof amount === 'object' && 'amount' in amount && typeof amount.amount === 'string' ? amount.amount : null;

  return (
    <section aria-labelledby="asat-h" className="min-w-0 rounded-card bg-muted p-5">
      <h3 id="asat-h" className="flex items-center gap-2 text-title text-emphasis">
        <History aria-hidden="true" className="h-icon w-icon" />
        As at…
      </h3>
      <p className="mt-1 text-body-sm text-secondary">Replay the history to see the application exactly as it stood at an instant (Lagos time).</p>
      <form
        className="mt-3 flex flex-wrap items-end gap-2"
        onSubmit={(e) => {
          e.preventDefault();
          setTouched(true);
          if (value) replay.mutate(lagosLocalToIso(value));
        }}
      >
        <TextInput label="Date and time (WAT)" type="datetime-local" value={value} onChange={(e) => setValue(e.target.value)} error={error} className="min-w-[14rem] flex-1" />
        <Button type="submit" variant="secondary" loading={replay.isPending}>
          Reconstruct
        </Button>
      </form>
      <div aria-live="polite" className="mt-4">
        {replay.error && <ErrorSummary problem={isApiProblem(replay.error) ? replay.error : null} messages={isApiProblem(replay.error) ? undefined : [replay.error.message]} />}
        {replay.data && !state && <p className="text-body-sm text-secondary">The application did not exist yet at {formatDateTime(replay.data.as_at)}.</p>}
        {replay.data && state && (
          <div className="rounded-control bg-surface p-4">
            <p className="text-meta text-tertiary">As at {formatDateTime(replay.data.as_at)}</p>
            <KeyValueGrid
              className="mt-2"
              items={[
                { label: 'Status', value: status ? <StatusBadge status={status} /> : '—' },
                { label: 'Requested amount', value: amountText ? <MoneyText amount={amountText} /> : '—' },
                { label: 'Tenor', value: typeof state.tenor_months === 'number' ? `${state.tenor_months} months` : '—' },
                { label: 'Version', value: typeof state.version === 'number' ? String(state.version) : '—' },
              ]}
            />
          </div>
        )}
      </div>
    </section>
  );
}
