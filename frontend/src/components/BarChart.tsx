import { useEffect, useId, useRef, useState } from 'react';
import { Table2, BarChart3 } from 'lucide-react';
import { cn } from '@/lib/cn';
import { formatNumber } from '@/lib/format';

export type BarDatum = { label: string; a: number; b: number };

export type BarChartProps = {
  title: string;
  data: readonly BarDatum[];
  seriesA: string;
  seriesB: string;
  /** Format axis + tooltip values. */
  format?: (n: number) => string;
  height?: number;
};

const DEFAULT_W = 720;
const PAD = { top: 12, right: 8, bottom: 28, left: 44 };

function niceMax(n: number): number {
  if (n <= 0) return 1;
  const pow = 10 ** Math.floor(Math.log10(n));
  const steps = [1, 2, 2.5, 5, 10];
  for (const s of steps) if (s * pow >= n) return s * pow;
  return 10 * pow;
}

/**
 * Two-series grouped bar chart in plain SVG (loan-ui "Cashflow"): series A in
 * forest, series B in lime with a 3:1 edge. The SVG is labelled for AT, and the
 * same numbers are available as a real <table> (toggle) for WCAG 1.1.1 / 1.4.11.
 */
/** Track the rendered width so SVG units = CSS pixels (labels stay legible at any size). */
function useWidth<T extends HTMLElement>(): [React.RefObject<T | null>, number] {
  const ref = useRef<T>(null);
  const [width, setWidth] = useState(DEFAULT_W);
  useEffect(() => {
    const el = ref.current;
    if (!el || typeof ResizeObserver === 'undefined') return;
    const ro = new ResizeObserver(([entry]) => {
      if (entry) setWidth(Math.max(280, Math.round(entry.contentRect.width)));
    });
    ro.observe(el);
    return () => ro.disconnect();
  }, []);
  return [ref, width];
}

export function BarChart({ title, data, seriesA, seriesB, format = (n) => formatNumber(n), height = 260 }: BarChartProps) {
  const [asTable, setAsTable] = useState(false);
  const [boxRef, W] = useWidth<HTMLElement>();
  const [hover, setHover] = useState<number | null>(null);
  const id = useId();
  const max = niceMax(Math.max(1, ...data.flatMap((d) => [d.a, d.b])));
  const innerH = height - PAD.top - PAD.bottom;
  const innerW = W - PAD.left - PAD.right;
  const band = innerW / Math.max(1, data.length);
  const barW = Math.max(4, Math.min(18, band / 3.2));
  const y = (v: number) => PAD.top + innerH - (v / max) * innerH;
  const ticks = [0, 0.25, 0.5, 0.75, 1].map((t) => t * max);
  const hovered = hover !== null ? data[hover] : undefined;

  return (
    <figure ref={boxRef} className="m-0">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
        <ul className="flex items-center gap-4 text-body-sm text-secondary" aria-label="Legend">
          <li className="flex items-center gap-2">
            <span aria-hidden="true" className="h-3 w-3 rounded-chip bg-chart-1" />
            {seriesA}
          </li>
          <li className="flex items-center gap-2">
            <span aria-hidden="true" className="h-3 w-3 rounded-chip border border-accent-fill-edge bg-chart-2" />
            {seriesB}
          </li>
        </ul>
        <button
          type="button"
          onClick={() => setAsTable((v) => !v)}
          aria-pressed={asTable}
          className="inline-flex min-h-target items-center gap-1.5 rounded-pill px-2.5 py-1 text-meta font-medium text-link hover:bg-hover-nav"
        >
          {asTable ? <BarChart3 aria-hidden="true" className="h-icon-sm w-icon-sm" /> : <Table2 aria-hidden="true" className="h-icon-sm w-icon-sm" />}
          {asTable ? 'Show chart' : 'Show as table'}
        </button>
      </div>

      {asTable ? (
        <div className="overflow-x-auto">
          <table className="w-full text-table">
            <caption className="sr-only">{title}</caption>
            <thead>
              <tr className="bg-muted text-left text-tertiary">
                <th scope="col" className="px-cell-x py-2 font-medium">Month</th>
                <th scope="col" className="px-cell-x py-2 text-right font-medium">{seriesA}</th>
                <th scope="col" className="px-cell-x py-2 text-right font-medium">{seriesB}</th>
              </tr>
            </thead>
            <tbody>
              {data.map((d) => (
                <tr key={d.label} className="border-b border-subtle">
                  <th scope="row" className="px-cell-x py-2 text-left font-medium text-primary">{d.label}</th>
                  <td className="px-cell-x py-2 text-right tabular">{format(d.a)}</td>
                  <td className="px-cell-x py-2 text-right tabular">{format(d.b)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="relative">
          <svg viewBox={`0 0 ${W} ${height}`} role="img" aria-labelledby={`${id}-t ${id}-d`} className="h-auto w-full">
            <title id={`${id}-t`}>{title}</title>
            <desc id={`${id}-d`}>
              {`${seriesA} and ${seriesB} by month. Highest ${seriesA}: ${format(Math.max(...data.map((d) => d.a)))}. Use "Show as table" for all values.`}
            </desc>
            {ticks.map((t) => (
              <g key={t}>
                <line x1={PAD.left} x2={W - PAD.right} y1={y(t)} y2={y(t)} className="stroke-[var(--color-chart-grid)]" strokeDasharray={t === 0 ? undefined : '3 4'} strokeWidth={1} />
                <text x={PAD.left - 8} y={y(t) + 4} textAnchor="end" className="fill-[var(--color-text-tertiary)] text-micro">
                  {format(t)}
                </text>
              </g>
            ))}
            {data.map((d, i) => {
              const cx = PAD.left + band * i + band / 2;
              const active = hover === i;
              return (
                <g key={d.label} onMouseEnter={() => setHover(i)} onMouseLeave={() => setHover(null)}>
                  <rect x={cx - band / 2} y={PAD.top} width={band} height={innerH} className={active ? 'fill-[var(--color-bg-hover)]' : 'fill-none'} pointerEvents="all" />
                  <rect x={cx - barW - 2} y={y(d.a)} width={barW} height={Math.max(0, PAD.top + innerH - y(d.a))} rx={4} className="fill-[var(--color-chart-1)]" />
                  <rect
                    x={cx + 2}
                    y={y(d.b)}
                    width={barW}
                    height={Math.max(0, PAD.top + innerH - y(d.b))}
                    rx={4}
                    className="fill-[var(--color-chart-2)] stroke-[var(--color-accent-fill-edge)]"
                    strokeWidth={1}
                  />
                  <text x={cx} y={height - 8} textAnchor="middle" className="fill-[var(--color-text-tertiary)] text-meta">
                    {d.label}
                  </text>
                </g>
              );
            })}
          </svg>
          {hovered && hover !== null && (
            <div
              aria-hidden="true"
              className={cn('pointer-events-none absolute top-0 rounded-control border bg-surface px-3 py-2 text-meta shadow-popover')}
              style={{ left: `${((PAD.left + band * hover + band / 2) / W) * 100}%`, transform: 'translateX(-50%)' }}
            >
              <p className="font-semibold text-primary">{hovered.label}</p>
              <p className="text-tertiary">
                {seriesA} <span className="ml-2 font-semibold text-primary tabular">{format(hovered.a)}</span>
              </p>
              <p className="text-tertiary">
                {seriesB} <span className="ml-2 font-semibold text-primary tabular">{format(hovered.b)}</span>
              </p>
            </div>
          )}
        </div>
      )}
    </figure>
  );
}
