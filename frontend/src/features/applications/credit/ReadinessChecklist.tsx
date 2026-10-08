import { useQuery } from '@tanstack/react-query';
import { CheckCircle2, Circle } from 'lucide-react';
import type { Application } from '@/api/lending';
import { Pill, Skeleton } from '@/components';
import { cn } from '@/lib/cn';
import { bureauQuery, decisionsQuery, memoQuery } from '../queries';
import { readiness, type ReadinessItem } from './domain';

/** Live "Ready to recommend" state from the credit queries (shared cache with the Credit tab). */
export function useReadiness(app: Application, enabled = true) {
  const reports = useQuery({ ...bureauQuery(app.id), enabled });
  const decisions = useQuery({ ...decisionsQuery(app.id), enabled });
  const memo = useQuery({ ...memoQuery(app.id), enabled });
  const loading = reports.isPending || decisions.isPending || memo.isPending;
  const failed = reports.isError || decisions.isError || memo.isError;
  const result = loading || failed ? null : readiness({ primaryPartyId: app.primary_applicant.party_id, reports: reports.data, decisions: decisions.data, memo: memo.data.latest });
  return { loading, failed, result };
}

export function ReadinessList({ items, compact = false }: { items: ReadinessItem[]; compact?: boolean }) {
  return (
    <ul className={cn('space-y-2', compact && 'space-y-1')}>
      {items.map((i) => (
        <li key={i.key} className="flex items-start gap-2 rounded-control bg-surface p-3">
          {i.met ? <CheckCircle2 aria-hidden="true" className="mt-0.5 h-icon w-icon shrink-0 text-success" /> : <Circle aria-hidden="true" className="mt-0.5 h-icon w-icon shrink-0 text-warning" />}
          <div className="min-w-0">
            <p className="text-body-sm font-semibold text-primary">
              {i.label} <span className="font-normal text-secondary">· {i.met ? 'done' : 'to do'}</span>
            </p>
            {!compact && <p className="text-meta text-secondary">{i.detail}</p>}
            {compact && !i.met && <p className="text-meta text-secondary">{i.detail}</p>}
          </div>
        </li>
      ))}
    </ul>
  );
}

export function ReadinessChecklist({ app, canRecommend }: { app: Application; canRecommend: boolean }) {
  const { loading, failed, result } = useReadiness(app);
  return (
    <section aria-labelledby="ready-h" className="rounded-card bg-muted p-5">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 id="ready-h" className="text-title text-emphasis">
          Ready to recommend
        </h2>
        {result && (result.ready ? <Pill tone="success">Ready</Pill> : <Pill tone="warning">{`${result.items.filter((i) => !i.met).length} to do`}</Pill>)}
      </div>
      {loading ? (
        <Skeleton className="h-[9rem] w-full rounded-card" />
      ) : failed || !result ? (
        <p className="text-body-sm text-secondary">The checklist couldn’t be worked out because a credit section failed to load.</p>
      ) : (
        <>
          <ReadinessList items={result.items} />
          <p className="mt-3 text-body-sm text-secondary">
            {result.ready
              ? canRecommend
                ? 'Use Recommend at the top of the case to route it for approval.'
                : 'Someone with the application:recommend permission can now recommend this case.'
              : 'Recommend stays blocked by the server until all three are done.'}
          </p>
        </>
      )}
    </section>
  );
}
