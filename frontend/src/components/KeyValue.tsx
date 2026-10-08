import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export type KeyValueItem = { label: string; value: ReactNode; hint?: ReactNode };

/** Definition list of facts ("PropertyGrid"): label above value, 2–4 columns. */
export function KeyValueGrid({ items, columns = 2, className }: { items: readonly KeyValueItem[]; columns?: 2 | 3 | 4; className?: string }) {
  const cols = { 2: 'sm:grid-cols-2', 3: 'sm:grid-cols-3', 4: 'sm:grid-cols-2 xl:grid-cols-4' }[columns];
  return (
    <dl className={cn('grid gap-x-6 gap-y-4', cols, className)}>
      {items.map((i) => (
        <div key={i.label} className="min-w-0">
          <dt className="text-meta text-tertiary">{i.label}</dt>
          <dd className="mt-0.5 break-words text-body font-semibold text-primary">{i.value}</dd>
          {i.hint && <dd className="text-meta text-tertiary">{i.hint}</dd>}
        </div>
      ))}
    </dl>
  );
}
