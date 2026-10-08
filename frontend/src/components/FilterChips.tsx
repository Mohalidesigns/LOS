import type { ReactNode } from 'react';
import { Check } from 'lucide-react';
import { cn } from '@/lib/cn';

export type FilterChipOption = { id: string; label: string; count?: number | undefined };

/**
 * Toggle chips (brief WRK-02 FilterBar): a group of pressed/unpressed buttons.
 * Selected = forest fill with white text and a check mark (not colour alone).
 */
export function FilterChips({ label, options, selected, onToggle, extra }: { label: string; options: readonly FilterChipOption[]; selected: readonly string[]; onToggle: (id: string) => void; extra?: ReactNode }) {
  return (
    <div role="group" aria-label={label} className="flex flex-wrap items-center gap-2">
      {options.map((o) => {
        const on = selected.includes(o.id);
        return (
          <button
            key={o.id}
            type="button"
            aria-pressed={on}
            onClick={() => onToggle(o.id)}
            className={cn(
              'inline-flex min-h-target items-center gap-1.5 rounded-pill border px-3.5 py-1 text-body-sm font-medium transition-colors duration-fast',
              on ? 'border-accent bg-accent text-on-accent' : 'border-strong bg-surface text-primary hover:bg-hover',
            )}
          >
            {on && <Check aria-hidden="true" className="h-icon-sm w-icon-sm" />}
            {o.label}
            {o.count !== undefined && <span className={cn('tabular', on ? 'text-on-accent' : 'text-tertiary')}>{o.count}</span>}
          </button>
        );
      })}
      {extra}
    </div>
  );
}
