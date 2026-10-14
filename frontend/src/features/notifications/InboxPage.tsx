import { Link } from 'react-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { BellRing, CheckCheck } from 'lucide-react';
import { notificationsApi, type InAppNotification } from '@/api/notifications';
import { Button, Card, CardHeader, DateTimeText, EmptyState, SectionError, Skeleton } from '@/components';
import { RequirePermission } from '@/features/auth/RequirePermission';
import { cn } from '@/lib/cn';
import { eventLabel, groupByDay } from './domain';

export const inboxKey = ['notifications', 'inbox'] as const;

/** My in-app notifications: what needs me next, newest first. */
export function InboxPage() {
  return (
    <RequirePermission anyOf={['application:view']}>
      <Inbox />
    </RequirePermission>
  );
}

function Inbox() {
  const qc = useQueryClient();
  const q = useQuery({ queryKey: inboxKey, queryFn: () => notificationsApi.inbox(), refetchInterval: 30_000 });
  const refresh = () => Promise.all([qc.invalidateQueries({ queryKey: inboxKey }), qc.invalidateQueries({ queryKey: ['dashboard', 'nav-counts'] })]);
  const read = useMutation({ mutationFn: (id: string) => notificationsApi.read(id), onSuccess: refresh });
  const readAll = useMutation({ mutationFn: () => notificationsApi.readAll(), onSuccess: refresh });

  if (q.isPending) return <Skeleton className="h-[16rem] w-full rounded-card" />;
  if (q.isError) return <SectionError error={q.error} title="Your inbox couldn't load" onRetry={() => void q.refetch()} />;
  const groups = groupByDay(q.data.data);

  return (
    <Card>
      <CardHeader
        title="Inbox"
        titleId="inbox-h"
        level={2}
        subtitle={q.data.meta.unread === 0 ? 'All caught up.' : `${q.data.meta.unread} unread`}
        actions={
          <Button size="sm" variant="secondary" leadingIcon={<CheckCheck aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabledReason={q.data.meta.unread === 0 ? 'Nothing unread.' : null} loading={readAll.isPending} onClick={() => readAll.mutate()}>
            Mark all read
          </Button>
        }
      />
      {q.data.data.length === 0 ? (
        <EmptyState icon={<BellRing aria-hidden="true" className="h-icon-lg w-icon-lg" />} title="No notifications" headingLevel={3}>
          Approvals, releases and alerts that need you appear here.
        </EmptyState>
      ) : (
        <div className="space-y-4">
          {(['today', 'earlier'] as const).map((k) =>
            groups[k].length === 0 ? null : (
              <section key={k} aria-label={k === 'today' ? 'Today' : 'Earlier'}>
                <h3 className="mb-2 text-meta font-semibold uppercase tracking-wide text-tertiary">{k === 'today' ? 'Today' : 'Earlier'}</h3>
                <ul className="divide-y rounded-card border">
                  {groups[k].map((n) => (
                    <Item key={n.id} n={n} onOpen={() => n.read_at === null && read.mutate(n.id)} />
                  ))}
                </ul>
              </section>
            ),
          )}
        </div>
      )}
    </Card>
  );
}

function Item({ n, onOpen }: { n: InAppNotification; onOpen: () => void }) {
  const unread = n.read_at === null;
  const body = (
    <div className="flex items-start gap-3">
      <span aria-hidden="true" className={cn('mt-1.5 h-2 w-2 shrink-0 rounded-full', unread ? 'bg-accent-strong' : 'bg-transparent')} />
      <span className="min-w-0">
        <span className={cn('block text-body-sm', unread ? 'font-semibold text-emphasis' : 'text-primary')}>{n.title}</span>
        <span className="block text-body-sm text-secondary">{n.body}</span>
        <span className="block text-meta text-tertiary">
          {eventLabel(n.event)} · <DateTimeText value={n.created_at} />
          {unread && <span className="sr-only"> (unread)</span>}
        </span>
      </span>
    </div>
  );
  return (
    <li>
      {n.link ? (
        <Link to={n.link} onClick={onOpen} className="block px-4 py-3 hover:bg-hover-nav">
          {body}
        </Link>
      ) : (
        <button type="button" onClick={onOpen} className="block w-full px-4 py-3 text-left hover:bg-hover-nav">
          {body}
        </button>
      )}
    </li>
  );
}

export default InboxPage;
