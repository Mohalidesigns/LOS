import { cn } from '@/lib/cn';

export type ProgressBarProps = {
  /** 0..1 */
  value: number;
  label: string;
  /** Visible value text, e.g. "12.5%". Defaults to the percentage. */
  valueText?: string;
  track?: 'lime' | 'neutral';
  className?: string;
};

/** Dark-green fill on a lime (or neutral) track, like loan-ui "Daily Limit". */
export function ProgressBar({ value, label, valueText, track = 'lime', className }: ProgressBarProps) {
  const clamped = Math.min(1, Math.max(0, value));
  const pct = Math.round(clamped * 1000) / 10;
  return (
    <div
      role="progressbar"
      aria-label={label}
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={pct}
      aria-valuetext={valueText ?? `${pct}%`}
      className={cn('h-2.5 w-full overflow-hidden rounded-pill', track === 'lime' ? 'bg-accent-fill' : 'bg-chart-track', className)}
    >
      <div className="h-full rounded-pill bg-accent-graphic transition-[width] duration-slow" style={{ width: `${pct}%` }} />
    </div>
  );
}
