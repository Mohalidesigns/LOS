/**
 * Visual primitives for the Credit tab: score ramp, DSR meter, outcome chip,
 * grade badge, collapsible key/value viewers for facts, trace and memo
 * section content. Token-only styling; values are always printed as text.
 */
import { useState, type ReactNode } from 'react';
import { ChevronRight } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { meQuery } from '@/features/auth/session';
import { Pill } from '@/components';
import { cn } from '@/lib/cn';
import { formatDateTime, formatMoney } from '@/lib/format';
import { staffLabel, flatten, gradeBand, gradeTone, humanKey, outcomeMeta, printable, scoreBar, type Band, type DsrBar } from './domain';

const BAND_BG: Record<Band, string> = { 1: 'bg-score-1', 2: 'bg-score-2', 3: 'bg-score-3', 4: 'bg-score-4', 5: 'bg-score-5' };

export function OutcomeChip({ outcome, className }: { outcome: string; className?: string }) {
  const m = outcomeMeta(outcome);
  return (
    <Pill tone={m.tone} className={className}>
      {m.label}
    </Pill>
  );
}

/** Square grade mark (loan-ui "60%" chips) with the band printed for screen readers. */
export function GradeBadge({ grade, size = 'md' }: { grade: string; size?: 'md' | 'lg' }) {
  const band = gradeBand(grade);
  return (
    <span className="inline-flex items-center gap-2">
      <span
        aria-hidden="true"
        className={cn(
          'inline-flex items-center justify-center rounded-mark font-bold',
          band ? cn(BAND_BG[band], 'text-inverse') : 'bg-neutral text-primary',
          size === 'lg' ? 'h-12 w-12 text-metric' : 'h-9 w-9 text-title-lg',
        )}
      >
        {grade || '?'}
      </span>
      <span className="sr-only">{`Risk grade ${grade}${band ? `, band ${band} of 5` : ''}`}</span>
    </span>
  );
}

export function gradeText(grade: string): string {
  const band = gradeBand(grade);
  return band ? `Grade ${grade} · band ${band} of 5` : `Grade ${grade}`;
}

export { gradeTone };

/** Five-band ramp (score-1…5) with a marker for the current band and labels under each segment. */
export function BandRamp({ band, labels, markerFraction, ariaLabel }: { band: Band | null; labels: readonly string[]; markerFraction?: number; ariaLabel: string }) {
  const bands: Band[] = [5, 4, 3, 2, 1];
  return (
    <div>
      <div className="relative" role="img" aria-label={ariaLabel}>
        <div className="flex h-2.5 gap-0.5 overflow-hidden rounded-pill">
          {bands.map((b) => (
            <span key={b} className={cn('flex-1', BAND_BG[b], band !== null && b !== band && 'opacity-40')} />
          ))}
        </div>
        {markerFraction !== undefined && (
          <span aria-hidden="true" className="absolute -top-1 w-1 -translate-x-1/2 rounded-pill border-2 border-surface bg-inverse" style={{ left: `${markerFraction * 100}%`, height: '1.125rem' }} />
        )}
      </div>
      <div aria-hidden="true" className="mt-1 flex text-micro text-tertiary">
        {labels.map((l) => (
          <span key={l} className="flex-1 text-center">
            {l}
          </span>
        ))}
      </div>
    </div>
  );
}

export function ScoreGauge({ score }: { score: number | null }) {
  const s = scoreBar(score);
  return (
    <div>
      <div className="flex items-baseline justify-between gap-2">
        <p className="text-meta text-tertiary">Bureau score</p>
        <p className="text-meta text-secondary">{s.label}</p>
      </div>
      <p className="text-metric text-emphasis tabular">{score ?? '—'}</p>
      <BandRamp
        band={s.band}
        labels={['<450', '450–549', '550–639', '640–719', '720+']}
        markerFraction={score === null ? undefined : s.fraction}
        ariaLabel={score === null ? 'No bureau score' : `Score ${score} of 850, ${s.label}`}
      />
    </div>
  );
}

/** DSR vs maximum: allowed zone in lime (fill only, with edge), the DSR in forest (or danger when over), a marker at the limit. */
export function DsrMeter({ bar }: { bar: DsrBar }) {
  const over = bar.passed === false;
  return (
    <div>
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <p className="text-body-sm text-primary">
          <span className="text-title text-emphasis tabular">{bar.dsr === null ? '—' : `${bar.dsr.toFixed(1)}%`}</span>{' '}
          <span className="text-secondary">debt service ratio{bar.max !== null && <> of max {bar.max.toFixed(1)}%</>}</span>
        </p>
        {bar.passed !== null && <Pill tone={bar.passed ? 'success' : 'danger'}>{bar.passed ? 'Within limit' : 'Over limit'}</Pill>}
      </div>
      <div
        role="meter"
        aria-label="Debt service ratio"
        aria-valuemin={0}
        aria-valuemax={bar.scale}
        aria-valuenow={bar.dsr ?? 0}
        aria-valuetext={bar.text}
        className="relative mt-2 h-3 w-full overflow-hidden rounded-pill bg-chart-track"
      >
        {bar.marker !== null && <span aria-hidden="true" className="absolute inset-y-0 left-0 rounded-pill border border-accent-fill-edge bg-accent-fill" style={{ width: `${bar.marker * 100}%` }} />}
        <span aria-hidden="true" className={cn('absolute inset-y-0 left-0 rounded-pill transition-[width] duration-slow', over ? 'bg-danger-fill' : 'bg-accent-graphic')} style={{ width: `${bar.fill * 100}%` }} />
        {bar.marker !== null && <span aria-hidden="true" className="absolute inset-y-0 w-0.5 bg-inverse" style={{ left: `calc(${bar.marker * 100}% - 1px)` }} />}
      </div>
      <p className="mt-1 text-meta text-secondary">
        {bar.text}
        {bar.headroom !== null && bar.passed && <> · {bar.headroom.toFixed(1)} points of headroom</>}
      </p>
    </div>
  );
}

/** Collapsible region (native details/summary: keyboard + screen reader support built in). Children render only once opened. */
export function Collapsible({ summary, children, defaultOpen = false, className }: { summary: ReactNode; children: ReactNode; defaultOpen?: boolean; className?: string }) {
  const [open, setOpen] = useState(defaultOpen);
  return (
    <details open={open} onToggle={(e) => setOpen(e.currentTarget.open)} className={cn('group rounded-control border', className)}>
      <summary className="flex min-h-target cursor-pointer list-none items-center gap-2 px-3 py-2 text-body-sm font-semibold text-primary hover:bg-hover [&::-webkit-details-marker]:hidden">
        <ChevronRight aria-hidden="true" className={cn('h-icon-sm w-icon-sm shrink-0 text-indicator transition-transform duration-fast', open && 'rotate-90')} />
        {summary}
      </summary>
      {open && <div className="border-t px-3 py-3">{children}</div>}
    </details>
  );
}

/** Dotted-path key/value table for arbitrary JSON (facts, trace entries). Text only, no HTML. */
export function KeyValueTable({ value, caption, empty = 'Nothing recorded.' }: { value: unknown; caption: string; empty?: string }) {
  const rows = flatten(value);
  if (rows.length === 0 || (rows.length === 1 && (rows[0]?.value === '{}' || rows[0]?.value === '[]'))) return <p className="text-body-sm text-secondary">{empty}</p>;
  return (
    <div className="max-h-[24rem] overflow-auto rounded-control bg-subtle">
      <table className="w-full text-table">
        <caption className="sr-only">{caption}</caption>
        <thead className="sticky top-0 bg-muted">
          <tr className="text-left text-meta text-tertiary">
            <th scope="col" className="px-cell-x py-1.5 font-medium">
              Field
            </th>
            <th scope="col" className="px-cell-x py-1.5 font-medium">
              Value
            </th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.path} className="border-t border-subtle align-top">
              <th scope="row" className="px-cell-x py-1.5 text-left font-normal">
                <span className="ref break-all">{r.path}</span>
              </th>
              <td className="break-all px-cell-x py-1.5 font-medium tabular">{r.value}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/** Top-level object as a definition list; nested values print as compact text. Used for memo section content. */
export function ContentList({ content }: { content: Record<string, unknown> }) {
  const entries = Object.entries(content);
  if (entries.length === 0) return <p className="text-body-sm text-secondary">No content.</p>;
  return (
    <dl className="grid gap-x-4 gap-y-2 sm:grid-cols-2">
      {entries.map(([k, v]) => (
        <div key={k} className="min-w-0">
          <dt className="text-meta text-tertiary">{humanKey(k)}</dt>
          <dd className="break-words text-body-sm font-semibold text-primary">{contentValue(v)}</dd>
        </div>
      ))}
    </dl>
  );
}

function contentValue(v: unknown): ReactNode {
  if (Array.isArray(v) && v.some((x) => typeof x === 'object' && x !== null && !isMoney(x))) {
    return (
      <ul className="list-disc pl-4 font-normal">
        {v.map((x, i) => (
          <li key={i}>{inlineValue(x)}</li>
        ))}
      </ul>
    );
  }
  if (typeof v === 'object' && v !== null && !Array.isArray(v) && !isMoney(v)) {
    const entries = Object.entries(v as Record<string, unknown>);
    if (entries.length === 0) return '—';
    return (
      <ul className="font-normal">
        {entries.map(([k, x]) => (
          <li key={k}>
            <span className="text-secondary">{humanKey(k)}:</span> {inlineValue(x)}
          </li>
        ))}
      </ul>
    );
  }
  return inlineValue(v);
}

function isMoney(v: unknown): v is { amount: string; currency: string } {
  return typeof v === 'object' && v !== null && typeof (v as Record<string, unknown>).amount === 'string' && typeof (v as Record<string, unknown>).currency === 'string';
}

/** One-line text for any JSON value: money and timestamps formatted, objects as "Key: value · …". */
export function inlineValue(v: unknown, depth = 0): string {
  if (isMoney(v)) return formatMoney(v.amount, v.currency);
  if (typeof v === 'string' && ISO_DT.test(v)) return formatDateTime(v);
  if (Array.isArray(v)) return v.length === 0 ? '—' : v.map((x) => inlineValue(x, depth + 1)).join(', ');
  if (typeof v === 'object' && v !== null) {
    if (depth > 4) return '…';
    return Object.entries(v as Record<string, unknown>)
      .map(([k, x]) => `${humanKey(k)}: ${inlineValue(x, depth + 1)}`)
      .join(' · ');
  }
  return printable(v);
}

const ISO_DT = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/;


/** Staff ids from the credit API: "You" for the signed-in user, else a short id (the API returns ids only). */
export function StaffName({ id }: { id: string }) {
  const me = useQuery({ ...meQuery, enabled: false });
  return <>{staffLabel(id, me.data?.id ?? '')}</>;
}
