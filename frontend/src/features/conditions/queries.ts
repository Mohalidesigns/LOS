import { queryOptions } from '@tanstack/react-query';
import { conditionsApi } from '@/api/conditions';
import { appKeys } from '@/features/applications/queries';

export const conditionKeys = {
  register: (applicationId: string) => [...appKeys.case(applicationId), 'conditions'] as const,
};

export const conditionRegisterQuery = (applicationId: string) => queryOptions({ queryKey: conditionKeys.register(applicationId), queryFn: () => conditionsApi.register(applicationId) });
