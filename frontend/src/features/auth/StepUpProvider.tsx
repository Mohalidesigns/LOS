import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { setStepUpHandler } from '@/api/client';
import { authApi } from '@/api/auth';
import type { ApiProblem } from '@/api/problem';
import { StepUpDialog, type StepUpValues } from '@/components/StepUpDialog';

type StepUpApi = {
  /** Open the dialog; resolves true once the user has re-authenticated. */
  requestStepUp: (windowMinutes?: number) => Promise<boolean>;
};

const StepUpContext = createContext<StepUpApi | null>(null);

/**
 * Mount once inside the authenticated shell. Registers the API client's
 * step-up handler: any call answered with `step-up-required` opens the dialog
 * and is retried transparently after a successful POST /auth/step-up.
 */
export function StepUpProvider({ children }: { children: ReactNode }) {
  const [open, setOpen] = useState(false);
  const [windowMinutes, setWindowMinutes] = useState(5);
  const pending = useRef<((ok: boolean) => void) | null>(null);
  const inflight = useRef<Promise<boolean> | null>(null);

  const requestStepUp = useCallback((minutes = 5) => {
    // Coalesce concurrent step-up demands into one dialog.
    if (inflight.current) return inflight.current;
    setWindowMinutes(minutes);
    setOpen(true);
    inflight.current = new Promise<boolean>((resolve) => {
      pending.current = resolve;
    }).finally(() => {
      inflight.current = null;
    });
    return inflight.current;
  }, []);

  useEffect(() => {
    setStepUpHandler((problem: ApiProblem) => {
      const m = problem.extensions.max_age_minutes;
      return requestStepUp(typeof m === 'number' ? m : 5);
    });
    return () => setStepUpHandler(null);
  }, [requestStepUp]);

  const settle = (ok: boolean) => {
    setOpen(false);
    pending.current?.(ok);
    pending.current = null;
  };

  const onSubmit = async ({ password, code }: StepUpValues) => {
    await authApi.stepUp(password, code);
    settle(true);
  };

  const api = useMemo(() => ({ requestStepUp }), [requestStepUp]);

  return (
    <StepUpContext.Provider value={api}>
      {children}
      <StepUpDialog open={open} windowMinutes={windowMinutes} onSubmit={onSubmit} onCancel={() => settle(false)} />
    </StepUpContext.Provider>
  );
}

export function useStepUp(): StepUpApi {
  const ctx = useContext(StepUpContext);
  if (!ctx) throw new Error('useStepUp must be used inside <StepUpProvider>');
  return ctx;
}
