import { queryOptions } from '@tanstack/react-query';
import { disbursementApi } from '@/api/disbursement';
import { appKeys } from '@/features/applications/queries';
import { currentInstruction, isRunning } from './domain';

export const disbursementKeys = {
  forApplication: (applicationId: string) => [...appKeys.case(applicationId), 'disbursements'] as const,
  exceptions: ['disbursement', 'exceptions'] as const,
  runs: ['disbursement', 'reconciliation-runs'] as const,
  breaks: ['disbursement', 'reconciliation-breaks'] as const,
};

/** Polls every 5 s while the booking saga is running, so the case moves on by itself. */
export const applicationDisbursementsQuery = (applicationId: string) =>
  queryOptions({
    queryKey: disbursementKeys.forApplication(applicationId),
    queryFn: () => disbursementApi.forApplication(applicationId),
    refetchInterval: (q) => (isRunning(currentInstruction(q.state.data ?? [])) ? 5_000 : false),
  });
export const exceptionsQuery = queryOptions({ queryKey: disbursementKeys.exceptions, queryFn: () => disbursementApi.exceptions(), refetchInterval: 30_000 });
export const runsQuery = queryOptions({ queryKey: disbursementKeys.runs, queryFn: () => disbursementApi.runs() });
export const breaksQuery = queryOptions({ queryKey: disbursementKeys.breaks, queryFn: () => disbursementApi.openBreaks() });
