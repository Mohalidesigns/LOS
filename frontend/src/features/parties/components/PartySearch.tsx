import { useEffect, useId, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Building2, Search, User } from 'lucide-react';
import { partiesApi, type PartySummary } from '@/api/lending';
import { Button, SectionError, Skeleton } from '@/components';

/** Search existing customers (GET /parties?filter[q]=) and pick one. */
export function PartySearch({ onSelect, selectLabel = 'Select', excludeIds = [], typeFilter }: { onSelect: (p: PartySummary) => void; selectLabel?: string; excludeIds?: readonly string[]; typeFilter?: 'individual' | 'limited_company' }) {
  const id = useId();
  const [q, setQ] = useState('');
  const [debounced, setDebounced] = useState('');
  useEffect(() => {
    const t = window.setTimeout(() => setDebounced(q.trim()), 350);
    return () => window.clearTimeout(t);
  }, [q]);

  const result = useQuery({
    queryKey: ['parties', 'search', debounced, typeFilter ?? ''],
    queryFn: () => partiesApi.list({ q: debounced, size: 10, ...(typeFilter ? { type: typeFilter } : {}) }),
    enabled: debounced.length >= 2,
  });
  const rows = (result.data?.data ?? []).filter((p) => !excludeIds.includes(p.id));

  return (
    <div className="space-y-3">
      <div>
        <label htmlFor={`${id}-q`} className="mb-1.5 block text-body-sm font-semibold text-primary">
          Find an existing customer
        </label>
        <div className="relative">
        <Search aria-hidden="true" className="pointer-events-none absolute left-3.5 top-1/2 h-icon w-icon -translate-y-1/2 text-indicator" />
        <input
          id={`${id}-q`}
          type="search"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Name or RC number"
          autoComplete="off"
          aria-describedby={`${id}-hint`}
          className="h-control w-full rounded-control border border-control bg-surface pl-10 pr-3.5 text-body text-primary placeholder:text-placeholder hover:border-control-hover"
        />
        </div>
        <p id={`${id}-hint`} className="mt-1.5 text-meta text-tertiary">
          Type at least 2 characters.
        </p>
      </div>
      <div aria-live="polite" aria-busy={result.isFetching || undefined}>
        {result.isError && <SectionError error={result.error} title="Search failed" onRetry={() => void result.refetch()} />}
        {result.isFetching && !result.data && (
          <div className="space-y-2">
            <Skeleton className="h-12 w-full" />
            <Skeleton className="h-12 w-full" />
          </div>
        )}
        {debounced.length >= 2 && result.data && rows.length === 0 && <p className="text-body-sm text-secondary">No customers match “{debounced}”.</p>}
        {rows.length > 0 && (
          <ul className="divide-y divide-subtle rounded-control border">
            {rows.map((p) => {
              const Icon = p.type === 'limited_company' ? Building2 : User;
              return (
                <li key={p.id} className="flex items-center justify-between gap-3 px-3 py-2">
                  <div className="flex min-w-0 items-center gap-3">
                    <Icon aria-hidden="true" className="h-icon w-icon shrink-0 text-indicator" />
                    <div className="min-w-0">
                      <p className="truncate text-body font-semibold text-primary">{p.display_name}</p>
                      <p className="text-meta text-tertiary">
                        {p.type === 'limited_company' ? 'Limited company' : 'Individual'}
                        {p.registration_number ? (
                          <>
                            {' · '}
                            <span className="ref">{p.registration_number}</span>
                          </>
                        ) : null}
                      </p>
                    </div>
                  </div>
                  <Button size="sm" variant="secondary" onClick={() => onSelect(p)} aria-label={`${selectLabel} ${p.display_name}`}>
                    {selectLabel}
                  </Button>
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </div>
  );
}
