import { QueryClient } from '@tanstack/react-query';
import { isApiProblem } from '@/api/problem';

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      refetchOnWindowFocus: false,
      // Never retry client errors (401/403/404/422…); one retry for network/5xx.
      retry: (count, error) => {
        if (isApiProblem(error) && error.status >= 400 && error.status < 500) return false;
        return count < 1;
      },
    },
    mutations: { retry: false },
  },
});
