import { queryOptions } from '@tanstack/react-query';
import { offersApi } from '@/api/offers';
import { appKeys } from '@/features/applications/queries';

export const offerKeys = {
  forApplication: (applicationId: string) => [...appKeys.case(applicationId), 'offers'] as const,
};

export const applicationOffersQuery = (applicationId: string) => queryOptions({ queryKey: offerKeys.forApplication(applicationId), queryFn: () => offersApi.forApplication(applicationId) });
