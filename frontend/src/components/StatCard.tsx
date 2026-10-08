import type { ReactNode } from 'react';
import { Card } from './Card';
import { TrendChip } from './TrendChip';

export type StatCardProps = {
  label: string;
  value: ReactNode;
  icon: ReactNode;
  /** Fractional change vs previous period (0.0178 = +1.78%). */
  change?: number;
  goodWhen?: 'up' | 'down';
  menu?: ReactNode;
  footnote?: ReactNode;
};

/** Icon tile, trend chip, big value, label — as in loan-ui "Total Income". */
export function StatCard({ label, value, icon, change, goodWhen, menu, footnote }: StatCardProps) {
  return (
    <Card as="article" className="flex flex-col">
      <div className="flex items-start justify-between">
        <span aria-hidden="true" className="inline-flex h-icon-tile w-icon-tile items-center justify-center rounded-mark bg-muted text-emphasis">
          {icon}
        </span>
        {menu}
      </div>
      <div className="mt-5">{change !== undefined && <TrendChip change={change} goodWhen={goodWhen} />}</div>
      <p className="mt-2 text-metric text-emphasis tabular">{value}</p>
      <h3 className="mt-1 text-body text-secondary">{label}</h3>
      {footnote && <p className="mt-1 text-meta text-tertiary">{footnote}</p>}
    </Card>
  );
}
