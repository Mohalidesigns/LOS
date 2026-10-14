import { useQuery } from '@tanstack/react-query';
import { Mail, MessageSquare, Paperclip, Smartphone } from 'lucide-react';
import { notificationsApi } from '@/api/notifications';
import { CardHeader, DateTimeText, Pill, SectionError, Skeleton } from '@/components';
import { useCase } from '@/features/applications/case/CaseContext';
import { Collapsible } from '@/features/applications/credit/CreditParts';
import { appKeys } from '@/features/applications/queries';
import { CHANNEL_LABEL, eventLabel, STATUS_META } from './domain';

/** SCR-NTF-01: everything sent about this application, to whom, when, how and its status (FR-NTF-005). */
export function CommunicationsTab() {
  const { app } = useCase();
  const q = useQuery({ queryKey: [...appKeys.case(app.id), 'communications'], queryFn: () => notificationsApi.forApplication(app.id) });
  if (q.isPending) return <Skeleton className="h-[16rem] w-full rounded-card" />;
  if (q.isError) return <SectionError error={q.error} title="Communications couldn't load" onRetry={() => void q.refetch()} />;

  return (
    <section aria-labelledby="com-h" className="rounded-card border bg-surface p-5 shadow-card">
      <CardHeader title="Communication log" titleId="com-h" level={2} subtitle="Customer emails and staff notifications about this application" />
      {q.data.length === 0 ? (
        <p className="text-body-sm text-secondary">Nothing sent yet.</p>
      ) : (
        <ul className="space-y-2">
          {q.data.map((c) => (
            <li key={c.id} className="rounded-control border p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="inline-flex min-w-0 items-center gap-2 text-body-sm">
                  {c.channel === 'email' ? <Mail aria-hidden="true" className="h-icon-sm w-icon-sm text-tertiary" /> : c.channel === 'sms' ? <Smartphone aria-hidden="true" className="h-icon-sm w-icon-sm text-tertiary" /> : <MessageSquare aria-hidden="true" className="h-icon-sm w-icon-sm text-tertiary" />}
                  <span className="font-semibold text-primary">{eventLabel(c.event)}</span>
                  <span className="text-secondary">
                    → {c.audience === 'customer' ? `customer ${c.recipient.hint ?? ''}` : (c.recipient.hint ?? 'staff')}
                  </span>
                </span>
                <Pill tone={STATUS_META[c.status].tone}>{STATUS_META[c.status].label}</Pill>
              </div>
              <p className="mt-1 text-meta text-tertiary">
                <DateTimeText value={c.created_at} /> · {CHANNEL_LABEL[c.channel]} · template {c.template.source === 'tenant' ? 'tenant' : 'built-in'} ({c.template.language})
                {c.error && c.status === 'failed' && <span className="text-danger"> · {c.error}</span>}
              </p>
              <Collapsible className="mt-2" summary={c.subject}>
                <p className="whitespace-pre-line text-body-sm text-primary">{c.body}</p>
                {c.attachments.length > 0 && (
                  <p className="mt-2 inline-flex items-center gap-1 text-meta text-tertiary">
                    <Paperclip aria-hidden="true" className="h-icon-sm w-icon-sm" /> {c.attachments.join(', ')}
                  </p>
                )}
              </Collapsible>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
