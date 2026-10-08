import { useId, type ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { formatPercent } from '@/lib/format';

export type DonutSlice = { label: string; value: number; display?: ReactNode };

/** Slice colours in order: forest, lime (edged), mid-green, amber, grey. */
const SLICE_STROKE = [
  'stroke-[var(--color-chart-1)]',
  'stroke-[var(--color-chart-2)]',
  'stroke-[var(--color-chart-3)]',
  'stroke-[var(--color-chart-4)]',
  'stroke-[var(--color-chart-5)]',
] as const;
const CHIP = [
  'bg-chart-1 text-on-accent',
  'bg-accent-fill text-on-accent-fill',
  'bg-accent-subtle text-emphasis',
  'bg-warning text-warning',
  'bg-neutral text-neutral',
] as const;

/**
 * Donut (loan-ui "Statistic"): SVG ring + centre total + legend with percentage
 * chips. The legend is the text alternative: every slice has a label, percentage
 * and value in real text, so colour never carries meaning alone.
 */
export function DonutChart({ title, slices, centerLabel, centerValue }: { title: string; slices: readonly DonutSlice[]; centerLabel: string; centerValue: ReactNode }) {
  const id = useId();
  const total = slices.reduce((s, x) => s + x.value, 0) || 1;
  const R = 70;
  const C = 2 * Math.PI * R;
  const GAP = 6;
  const offsets = slices.map((_, i) => slices.slice(0, i).reduce((sum, x) => sum + (x.value / total) * C, 0));

  return (
    <div>
      <div className="relative mx-auto w-[220px] max-w-full">
        <svg viewBox="0 0 200 200" role="img" aria-labelledby={`${id}-t`} className="h-auto w-full -rotate-90">
          <title id={`${id}-t`}>{`${title}: ${slices.map((s) => `${s.label} ${formatPercent(s.value / total, 0)}`).join(', ')}`}</title>
          <circle cx="100" cy="100" r={R} fill="none" className="stroke-[var(--color-chart-track)]" strokeWidth={22} />
          {slices.map((s, i) => {
            const len = Math.max(0, (s.value / total) * C - GAP);
            return (
              <circle
                key={s.label}
                cx="100"
                cy="100"
                r={R}
                fill="none"
                strokeWidth={i === 0 ? 26 : 20}
                strokeDasharray={`${len} ${C - len}`}
                strokeDashoffset={-(offsets[i] ?? 0)}
                className={SLICE_STROKE[i % SLICE_STROKE.length]}
              />
            );
          })}
        </svg>
        <div aria-hidden="true" className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
          <span className="text-meta text-tertiary">{centerLabel}</span>
          <span className="text-title-lg font-bold text-emphasis tabular">{centerValue}</span>
        </div>
      </div>
      <ul className="mt-5 space-y-2.5" aria-label={`${title} legend`}>
        {slices.map((s, i) => (
          <li key={s.label} className="flex items-center gap-3">
            <span className={cn('inline-flex min-w-[46px] justify-center rounded-mark px-1.5 py-1.5 text-meta font-semibold tabular', CHIP[i % CHIP.length])}>
              {formatPercent(s.value / total, 0)}
            </span>
            <span className="flex-1 text-body text-primary">{s.label}</span>
            <span className="text-body font-semibold text-primary tabular">{s.display ?? s.value}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}
