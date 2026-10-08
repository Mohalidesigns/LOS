import { cn } from '@/lib/cn';

export type SlaState = 'on_track' | 'at_risk' | 'breached' | 'paused';

const META: Record<SlaState, { label: string; dot: string; text: string }> = {
  on_track: { label: 'On track', dot: 'bg-tone-success', text: 'text-success' },
  at_risk: { label: 'At risk', dot: 'bg-tone-warning', text: 'text-warning' },
  breached: { label: 'Breached', dot: 'bg-tone-danger', text: 'text-danger' },
  paused: { label: 'Paused', dot: 'bg-indicator', text: 'text-neutral' },
};

/** SLA state with remaining business hours, e.g. "● At risk · 3h left". Colour is never the only signal. */
export function SlaChip({ state, remaining, className }: { state: SlaState; remaining?: string; className?: string }) {
  const m = META[state];
  return (
    <span className={cn('inline-flex items-center gap-1.5 whitespace-nowrap text-meta font-medium', m.text, className)}>
      <span aria-hidden="true" className={cn('h-dot w-dot rounded-full', m.dot)} />
      <span>
        {m.label}
        {remaining && <span className="text-tertiary"> · {remaining}</span>}
      </span>
    </span>
  );
}
