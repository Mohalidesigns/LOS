import { useId, useRef, type ReactNode } from 'react';
import { cn } from '@/lib/cn';

export type TabItem = { id: string; label: ReactNode; panel: ReactNode };

/** WAI-ARIA tabs with automatic activation: Left/Right/Home/End move and select. */
export function Tabs({ items, value, onChange, label }: { items: readonly TabItem[]; value: string; onChange: (id: string) => void; label: string }) {
  const base = useId();
  const refs = useRef<Record<string, HTMLButtonElement | null>>({});
  const index = Math.max(0, items.findIndex((t) => t.id === value));

  const move = (next: number) => {
    const item = items[(next + items.length) % items.length];
    if (!item) return;
    onChange(item.id);
    refs.current[item.id]?.focus();
  };

  return (
    <div>
      <div role="tablist" aria-label={label} className="flex gap-1 border-b">
        {items.map((t, i) => {
          const selected = i === index;
          return (
            <button
              key={t.id}
              ref={(el) => {
                refs.current[t.id] = el;
              }}
              id={`${base}-tab-${t.id}`}
              type="button"
              role="tab"
              aria-selected={selected}
              aria-controls={`${base}-panel-${t.id}`}
              tabIndex={selected ? 0 : -1}
              onClick={() => onChange(t.id)}
              onKeyDown={(e) => {
                if (e.key === 'ArrowRight') move(i + 1);
                else if (e.key === 'ArrowLeft') move(i - 1);
                else if (e.key === 'Home') move(0);
                else if (e.key === 'End') move(items.length - 1);
                else return;
                e.preventDefault();
              }}
              className={cn(
                '-mb-px min-h-target border-b-2 px-3 py-2 text-body-sm font-medium transition-colors duration-fast',
                selected ? 'border-accent text-emphasis' : 'border-transparent text-tertiary hover:text-primary',
              )}
            >
              {t.label}
            </button>
          );
        })}
      </div>
      {items.map((t, i) => (
        <div key={t.id} id={`${base}-panel-${t.id}`} role="tabpanel" aria-labelledby={`${base}-tab-${t.id}`} hidden={i !== index} className="pt-4">
          {i === index && t.panel}
        </div>
      ))}
    </div>
  );
}
