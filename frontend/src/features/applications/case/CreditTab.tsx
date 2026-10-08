import { useQuery } from '@tanstack/react-query';
import { Banner, SectionError, Skeleton } from '@/components';
import { rankOf } from '../domain/stages';
import { bureauQuery, decisionsQuery } from '../queries';
import { BureauSection } from '../credit/BureauSection';
import { DecisionSection } from '../credit/DecisionSection';
import { CREDIT_PERM, latestDecision, latestValidReport } from '../credit/domain';
import { ExceptionsSection } from '../credit/ExceptionsSection';
import { MemoSection } from '../credit/MemoSection';
import { ReadinessChecklist } from '../credit/ReadinessChecklist';
import { useCase } from './CaseContext';

/** SCR-CRD-01..04 on one tab: bureau, decision snapshot, exceptions, memo and the recommend checklist. */
export function CreditTab() {
  const { app, permissions } = useCase();
  const reports = useQuery(bureauQuery(app.id));
  const decisions = useQuery(decisionsQuery(app.id));
  const inAssessment = app.status === 'assessment';
  const rank = rankOf(app.status);
  const before = rank !== null && rank < (rankOf('assessment') ?? 5);

  const decisionList = decisions.data ?? [];
  const latest = latestDecision(decisionList);
  const hasValidPrimary = reports.data ? latestValidReport(reports.data, app.primary_applicant.party_id) !== null : false;

  return (
    <div className="space-y-5">
      {!inAssessment && (
        <Banner tone="info" title={before ? 'Credit work opens at Assessment' : 'Credit work is read-only at this stage'}>
          {before
            ? 'Bureau pulls, decisions and the credit memo become available once KYC clears and every mandatory document is verified or waived; the case then moves to Assessment by itself.'
            : 'This application has left Assessment. You can review the bureau reports, decisions and memo versions recorded for it.'}
        </Banner>
      )}
      {inAssessment && !permissions.has(CREDIT_PERM.analyse) && !permissions.has(CREDIT_PERM.bureauPull) && (
        <Banner tone="info" title="View only">
          You can review the credit work. Pulling reports, running decisions and writing the memo need a credit analyst role.
        </Banner>
      )}
      <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div className="min-w-0 space-y-5">
          <BureauSection app={app} permissions={permissions} />
          {decisions.isPending ? (
            <Skeleton className="h-[20rem] w-full rounded-card" />
          ) : decisions.isError ? (
            <SectionError error={decisions.error} title="Decisions couldn't load" onRetry={() => void decisions.refetch()} />
          ) : (
            <DecisionSection app={app} permissions={permissions} decisions={decisionList} hasValidPrimaryReport={hasValidPrimary} />
          )}
        </div>
        <aside aria-label="Recommendation readiness and exceptions" className="min-w-0 space-y-5">
          <div className="xl:sticky xl:top-4 space-y-5">
            <ReadinessChecklist app={app} canRecommend={permissions.has(CREDIT_PERM.recommend)} />
            <ExceptionsSection app={app} permissions={permissions} decision={latest} />
          </div>
        </aside>
      </div>
      <MemoSection app={app} permissions={permissions} decision={latest} />
    </div>
  );
}
