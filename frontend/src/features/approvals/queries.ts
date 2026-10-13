import { queryOptions } from '@tanstack/react-query';
import { approvalsApi } from '@/api/approvals';
import { appKeys } from '@/features/applications/queries';

export const approvalKeys = {
  forApplication: (applicationId: string) => [...appKeys.case(applicationId), 'approvals'] as const,
  request: (id: string) => ['approval-requests', id] as const,
  inbox: ['approval-requests', 'inbox'] as const,
};

export const applicationApprovalsQuery = (applicationId: string) => queryOptions({ queryKey: approvalKeys.forApplication(applicationId), queryFn: () => approvalsApi.forApplication(applicationId) });
export const approvalRequestQuery = (id: string) => queryOptions({ queryKey: approvalKeys.request(id), queryFn: () => approvalsApi.get(id) });
export const approvalInboxQuery = queryOptions({ queryKey: approvalKeys.inbox, queryFn: () => approvalsApi.inbox(), refetchInterval: 60_000 });
