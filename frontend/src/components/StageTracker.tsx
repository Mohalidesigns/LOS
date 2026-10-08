import { Check, PauseCircle, RotateCcw, XCircle } from 'lucide-react';
import { cn } from '@/lib/cn';

export type StageStep = { key: string; label: string; state: 'complete' | 'current' | 'upcoming' };
export type StageInterruption = { kind: 'on_hold' | 'returned' | 'closed'; label: string };

const ICON = { on_hold: PauseCircle, returned: RotateCcw, closed: XCircle } as const;

/**
 * Horizontal stage tracker (canonical path). Each step prints its state in
 * text for assistive tech ("completed", "current stage"), so the dot colours
 * never carry meaning alone. Scrolls inside its container on narrow screens.
 */
export function StageTracker({ steps, interruption, label = 'Application progress' }: { steps: readonly StageStep[]; interruption?: StageInterruption | null; label?: string }) {
  const Icon = interruption ? ICON[interruption.kind] : null;
  return (
    <nav aria-label={label}>
      {interruption && Icon && (
        <p className={cn('mb-3 inline-flex items-center gap-1.5 rounded-pill border px-3 py-1 text-meta font-semibold', interruption.kind === 'closed' ? 'border-neutral text-neutral' : 'border-warning text-warning')}>
          <Icon aria-hidden="true" className="h-icon-sm w-icon-sm" />
          {interruption.label}
          {interruption.kind !== 'closed' && <span className="font-normal">· paused at the highlighted stage</span>}
        </p>
      )}
      <ol className="relative flex min-w-0 overflow-x-auto pb-1">
        {steps.map((s, i) => {
          const last = i === steps.length - 1;
          return (
            <li key={s.key} aria-current={s.state === 'current' ? 'step' : undefined} className="flex min-w-[88px] flex-1 flex-col items-center text-center">
              <div className="flex w-full items-center">
                <span aria-hidden="true" className={cn('h-0.5 flex-1', i === 0 ? 'bg-transparent' : s.state === 'upcoming' ? 'bg-chart-track' : 'bg-accent-graphic')} />
                <span
                  aria-hidden="true"
                  className={cn(
                    'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 text-micro font-bold',
                    s.state === 'complete' && 'border-accent-graphic bg-accent-graphic text-on-accent',
                    s.state === 'current' && (interruption ? 'border-warning bg-warning text-warning' : 'border-accent-fill-edge bg-accent-fill text-on-accent-fill'),
                    s.state === 'upcoming' && 'border-strong bg-surface text-tertiary',
                  )}
                >
                  {s.state === 'complete' ? <Check className="h-icon-sm w-icon-sm" /> : i + 1}
                </span>
                <span aria-hidden="true" className={cn('h-0.5 flex-1', last ? 'bg-transparent' : s.state === 'complete' ? 'bg-accent-graphic' : 'bg-chart-track')} />
              </div>
              <span className={cn('mt-1.5 px-1 text-meta leading-tight', s.state === 'current' ? 'font-semibold text-emphasis' : s.state === 'complete' ? 'text-primary' : 'text-tertiary')}>
                {s.label}
              </span>
              <span className="sr-only">{s.state === 'complete' ? ', completed' : s.state === 'current' ? ', current stage' : ', not started'}</span>
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
