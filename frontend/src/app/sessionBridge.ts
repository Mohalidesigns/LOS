import { setSessionEndedHandler } from '@/api/client';
import { sessionKeys } from '@/features/auth/session';
import { queryClient } from './queryClient';
import { router } from './router';

/**
 * 401 (unauthenticated / session-expired / session-revoked) from any call while
 * signed in → drop all cached server state and go to /login with the
 * "Your session ended" banner. A 401 before sign-in is handled by the loader.
 */
export function installSessionBridge(): void {
  let redirecting = false;
  setSessionEndedHandler(() => {
    if (redirecting || queryClient.getQueryData(sessionKeys.me) === undefined) return;
    redirecting = true;
    const here = window.location.pathname + window.location.search;
    queryClient.clear();
    const next = here.startsWith('/login') || here === '/' ? '' : `&next=${encodeURIComponent(here)}`;
    void router.navigate(`/login?reason=session-ended${next}`, { replace: true }).finally(() => {
      redirecting = false;
    });
  });
}
