import { useOutletContext } from 'react-router';
import type { Application } from '@/api/lending';

export type CaseContextValue = {
  app: Application;
  etag: string;
  permissions: ReadonlySet<string>;
  /** Show the "This application changed — reload" banner (412 on If-Match). */
  markStale: () => void;
  stale: boolean;
  /** Refetch the case (clears the stale banner); typed input elsewhere is kept. */
  reload: () => Promise<void>;
};

export function useCase(): CaseContextValue {
  return useOutletContext<CaseContextValue>();
}
