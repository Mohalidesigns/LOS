import { Link } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { Gavel } from 'lucide-react';
import { Card, CardHeader, DateTimeText, EmptyState, MoneyText, Pill, SectionError, Skeleton } from '@/components';
import { RequirePermission } from '@/features/auth/RequirePermission';
import { approvalInboxQuery } from './queries';
import { APPROVE_PERM } from './domain';

/** Approvals waiting at a level one of my roles is eligible for. */
export function ApprovalInboxPage() {
  return (
    <RequirePermission anyOf={[APPROVE_PERM]}>
      <Inbox />
    </RequirePermission>
  );
}

function Inbox() {
  const inbox = useQuery(approvalInboxQuery);
  return (
    <Card>
      <CardHeader title="Approvals" titleId="apv-inbox" level={2} subtitle="Requests waiting at an authority level your roles are eligible for, oldest first." />
      {inbox.isPending ? (
        <Skeleton className="h-[12rem] w-full rounded-card" />
      ) : inbox.isError ? (
        <SectionError error={inbox.error} title="Approvals couldn't load" onRetry={() => void inbox.refetch()} />
      ) : inbox.data.length === 0 ? (
        <EmptyState icon={<Gavel aria-hidden="true" className="h-icon-lg w-icon-lg" />} title="Nothing waiting for you">
          New requests appear here when an application reaches your authority level.
        </EmptyState>
      ) : (
        <ul className="divide-y">
          {inbox.data.map((r) => {
            const level = r.current_level === null ? null : r.levels[r.current_level];
            return (
              <li key={r.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                <div className="min-w-0">
                  <Link to={`/applications/${r.application_id}/approval`} className="ref font-semibold text-link hover:underline">
                    {r.application_reference}
                  </Link>
                  <p className="text-meta text-tertiary">
                    Opened <DateTimeText value={r.opened_at} /> · recommended by {r.recommended_by.name ?? 'staff'}
                  </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-body font-semibold text-emphasis">
                    <MoneyText amount={r.exposure.amount} currency={r.exposure.currency} />
                  </span>
                  {level && <Pill tone="warning">{`${level.code} · ${level.name}`}</Pill>}
                  {r.my_eligibility.can_vote ? <Pill tone="success">You can vote</Pill> : <Pill tone="neutral">Not eligible</Pill>}
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </Card>
  );
}

export default ApprovalInboxPage;
