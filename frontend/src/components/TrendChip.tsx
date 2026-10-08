import { TrendingDown, TrendingUp } from 'lucide-react';
import { cn } from '@/lib/cn';
import { formatPercent } from '@/lib/format';

/** "+1.78%" chip. `direction` decides tone; `good` lets a fall be good news (e.g. SLA breaches). */
export function TrendChip({ change, goodWhen = 'up', label }: { change: number; goodWhen?: 'up' | 'down'; label?: string }) {
  const up = change >= 0;
  const good = goodWhen === 'up' ? up : !up;
  const Icon = up ? TrendingUp : TrendingDown;
  const text = `${up ? '+' : '−'}${formatPercent(Math.abs(change), 2)}`;
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-chip px-1.5 py-0.5 text-micro font-semibold tabular',
        good ? 'bg-accent-fill-subtle text-on-accent-fill' : 'bg-danger text-danger',
      )}
    >
      <Icon aria-hidden="true" className="h-icon-sm w-icon-sm" />
      <span>{text}</span>
      <span className="sr-only">{label ?? (up ? 'increase' : 'decrease')} versus previous period</span>
    </span>
  );
}
