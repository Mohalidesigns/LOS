import { Check } from 'lucide-react';
import { cn } from '@/lib/cn';

/** Wizard step indicator: an ordered list with "Step 2 of 4" semantics and aria-current. */
export function Stepper({ steps, current, label = 'Progress' }: { steps: readonly string[]; current: number; label?: string }) {
  return (
    <nav aria-label={label}>
      <p className="sr-only">{`Step ${current + 1} of ${steps.length}: ${steps[current] ?? ''}`}</p>
      <ol className="flex flex-wrap items-center gap-2">
        {steps.map((s, i) => {
          const state = i < current ? 'done' : i === current ? 'current' : 'todo';
          return (
            <li key={s} aria-current={state === 'current' ? 'step' : undefined} className="flex items-center gap-2">
              <span
                aria-hidden="true"
                className={cn(
                  'inline-flex h-8 w-8 items-center justify-center rounded-full text-meta font-bold',
                  state === 'done' && 'bg-accent text-on-accent',
                  state === 'current' && 'border-2 border-accent-fill-edge bg-accent-fill text-on-accent-fill',
                  state === 'todo' && 'border border-strong bg-surface text-tertiary',
                )}
              >
                {state === 'done' ? <Check className="h-icon-sm w-icon-sm" /> : i + 1}
              </span>
              <span className={cn('text-body-sm', state === 'current' ? 'font-semibold text-emphasis' : state === 'done' ? 'text-primary' : 'text-tertiary')}>{s}</span>
              {i < steps.length - 1 && <span aria-hidden="true" className="mx-1 hidden h-px w-8 bg-neutral sm:block" />}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
