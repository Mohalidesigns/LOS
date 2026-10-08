import { useEffect, useRef } from 'react';
import { AlertTriangle } from 'lucide-react';
import type { ApiProblem } from '@/api/problem';

export type ErrorSummaryProps = {
  title?: string;
  problem?: ApiProblem | null;
  messages?: string[];
  /** Move focus to the summary when it appears (default true). */
  autoFocus?: boolean;
};

/** GOV.UK-style error summary: role=alert, focused on appearance, carries the correlation id. */
export function ErrorSummary({ title = 'There is a problem', problem, messages, autoFocus = true }: ErrorSummaryProps) {
  const ref = useRef<HTMLDivElement>(null);
  const list = messages ?? problem?.messages ?? [];
  useEffect(() => {
    if (autoFocus && list.length > 0) ref.current?.focus();
  }, [autoFocus, list.length, problem]);
  if (list.length === 0) return null;
  return (
    <div ref={ref} tabIndex={-1} role="alert" aria-labelledby="error-summary-title" className="rounded-card border border-danger bg-danger p-4">
      <div className="flex items-start gap-3">
        <AlertTriangle aria-hidden="true" className="mt-0.5 h-icon w-icon shrink-0 text-danger" />
        <div className="min-w-0">
          <h2 id="error-summary-title" className="text-body font-semibold text-danger-strong">
            {title}
          </h2>
          <ul className="mt-1 list-disc space-y-0.5 pl-4 text-body-sm text-danger-strong">
            {list.map((m) => (
              <li key={m}>{m}</li>
            ))}
          </ul>
          {problem?.correlationId && (
            <p className="mt-2 text-meta text-danger-strong">
              Reference: <span className="ref">{problem.correlationId}</span>
            </p>
          )}
        </div>
      </div>
    </div>
  );
}
