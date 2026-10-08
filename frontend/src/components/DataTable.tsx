import { useMemo, useState, type ReactNode } from 'react';
import { Link } from 'react-router';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import { cn } from '@/lib/cn';
import { EmptyState } from './EmptyState';
import { Skeleton } from './Skeleton';

export type Column<T> = {
  id: string;
  header: string;
  cell: (row: T) => ReactNode;
  /** Enables sorting on this column. */
  sortValue?: (row: T) => string | number;
  align?: 'left' | 'right';
  /** The cell that names the row: rendered as <th scope="row"> and, with rowHref, as the row link. */
  rowHeader?: boolean;
  /** Secondary line under the cell (e.g. product category). */
  sub?: (row: T) => ReactNode;
  className?: string;
};

export type SortState = { id: string; dir: 'asc' | 'desc' } | null;

export type DataTableProps<T> = {
  caption: string;
  /** Hide the caption visually (keep it for AT). */
  hideCaption?: boolean;
  columns: readonly Column<T>[];
  rows: readonly T[] | undefined;
  getRowId: (row: T) => string;
  /** Row navigation: the row-header cell becomes a real <a>. */
  rowHref?: (row: T) => string;
  loading?: boolean;
  skeletonRows?: number;
  empty?: ReactNode;
  initialSort?: SortState;
  /** Max height of the scroll area; the header row sticks inside it. */
  maxHeightClass?: string;
};

export function DataTable<T>({
  caption,
  hideCaption = true,
  columns,
  rows,
  getRowId,
  rowHref,
  loading = false,
  skeletonRows = 5,
  empty,
  initialSort = null,
  maxHeightClass,
}: DataTableProps<T>) {
  const [sort, setSort] = useState<SortState>(initialSort);

  const sorted = useMemo(() => {
    if (!rows) return [];
    if (!sort) return rows;
    const col = columns.find((c) => c.id === sort.id);
    if (!col?.sortValue) return rows;
    const get = col.sortValue;
    const out = [...rows].sort((a, b) => {
      const va = get(a);
      const vb = get(b);
      const r = typeof va === 'number' && typeof vb === 'number' ? va - vb : String(va).localeCompare(String(vb), 'en-NG', { numeric: true });
      return sort.dir === 'asc' ? r : -r;
    });
    return out;
  }, [rows, sort, columns]);

  const toggle = (id: string) =>
    setSort((s) => (s?.id === id ? (s.dir === 'asc' ? { id, dir: 'desc' } : null) : { id, dir: 'asc' }));

  const showEmpty = !loading && sorted.length === 0;

  return (
    <div className={cn('relative overflow-auto rounded-control', maxHeightClass)} aria-busy={loading || undefined}>
      <table className="w-full border-separate border-spacing-0 text-table">
        <caption className={cn(hideCaption ? 'sr-only' : 'mb-2 text-left text-body-sm text-tertiary')}>
          {caption}
          {sort && <span className="sr-only">, sorted by {columns.find((c) => c.id === sort.id)?.header} {sort.dir === 'asc' ? 'ascending' : 'descending'}</span>}
        </caption>
        <thead className="sticky top-0 z-sticky">
          <tr>
            {columns.map((c, i) => {
              const active = sort?.id === c.id;
              const ariaSort = active ? (sort.dir === 'asc' ? 'ascending' : 'descending') : c.sortValue ? 'none' : undefined;
              const Icon = active ? (sort.dir === 'asc' ? ArrowUp : ArrowDown) : ChevronsUpDown;
              return (
                <th
                  key={c.id}
                  scope="col"
                  aria-sort={ariaSort}
                  className={cn(
                    'whitespace-nowrap bg-muted px-cell-x py-2.5 text-meta font-medium text-tertiary',
                    c.align === 'right' ? 'text-right' : 'text-left',
                    i === 0 && 'rounded-l-control',
                    i === columns.length - 1 && 'rounded-r-control',
                  )}
                >
                  {c.sortValue ? (
                    <button
                      type="button"
                      onClick={() => toggle(c.id)}
                      className={cn('inline-flex min-h-target items-center gap-1 rounded-mark hover:text-primary', c.align === 'right' && 'flex-row-reverse')}
                    >
                      {c.header}
                      <Icon aria-hidden="true" className="h-icon-sm w-icon-sm" />
                    </button>
                  ) : (
                    c.header
                  )}
                </th>
              );
            })}
          </tr>
        </thead>
        <tbody>
          {loading &&
            Array.from({ length: skeletonRows }, (_, r) => (
              <tr key={`sk-${r}`}>
                {columns.map((c) => (
                  <td key={c.id} className="border-b border-subtle px-cell-x py-cell-y">
                    <Skeleton className="h-4 w-full" />
                  </td>
                ))}
              </tr>
            ))}
          {!loading &&
            sorted.map((row) => (
              <tr key={getRowId(row)} className="group hover:bg-hover">
                {columns.map((c) => {
                  const content = (
                    <>
                      <span className="block">{c.cell(row)}</span>
                      {c.sub && <span className="mt-0.5 block text-meta font-normal text-tertiary">{c.sub(row)}</span>}
                    </>
                  );
                  const cls = cn(
                    'border-b border-subtle px-cell-x py-cell-y align-middle text-primary',
                    c.align === 'right' ? 'text-right' : 'text-left',
                    c.className,
                  );
                  if (c.rowHeader) {
                    return (
                      <th key={c.id} scope="row" className={cn(cls, 'font-semibold')}>
                        {rowHref ? (
                          <Link to={rowHref(row)} className="rounded-mark text-primary underline-offset-2 hover:text-link hover:underline">
                            {content}
                          </Link>
                        ) : (
                          content
                        )}
                      </th>
                    );
                  }
                  return (
                    <td key={c.id} className={cls}>
                      {content}
                    </td>
                  );
                })}
              </tr>
            ))}
        </tbody>
      </table>
      {loading && <span className="sr-only" role="status">Loading {caption}</span>}
      {showEmpty && (empty ?? <EmptyState title="Nothing to show yet" headingLevel={3} />)}
    </div>
  );
}
