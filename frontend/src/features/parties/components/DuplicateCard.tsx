import type { ReactNode } from 'react';
import { AlertTriangle } from 'lucide-react';
import type { PartyMatch } from '@/api/lending';
import { Button } from '@/components';
import { formatPercent } from '@/lib/format';

const FIELD_LABEL: Record<string, string> = {
  name: 'name',
  phone: 'phone',
  email: 'email',
  tin: 'TIN',
  registration_number: 'RC number',
  bvn: 'BVN',
  nin: 'NIN',
  identity: 'identity number',
};

export function matchedFieldsText(fields: readonly string[]): string {
  return fields.map((f) => FIELD_LABEL[f] ?? f.replace(/_/g, ' ')).join(', ');
}

/** Dedupe warning (FR-CHN-007): candidates with what matched and a "Use existing" option. */
export function DuplicateCard({ title, matches, onUse, footer, live = false }: { title: string; matches: readonly PartyMatch[]; onUse: (m: PartyMatch) => void; footer?: ReactNode; live?: boolean }) {
  return (
    <section aria-label={title} aria-live={live ? 'polite' : undefined} className="rounded-card border border-warning bg-warning p-4">
      <h4 className="flex items-center gap-2 text-body font-semibold text-primary">
        <AlertTriangle aria-hidden="true" className="h-icon w-icon text-warning" />
        {title}
      </h4>
      <ul className="mt-3 space-y-2">
        {matches.map((m) => (
          <li key={m.party_id} className="flex flex-wrap items-center justify-between gap-3 rounded-control bg-surface px-3 py-2">
            <div className="min-w-0">
              <p className="text-body font-semibold text-primary">{m.display_name}</p>
              <p className="text-meta text-secondary">
                {m.type === 'limited_company' ? 'Limited company' : 'Individual'} · matched on {matchedFieldsText(m.matched_fields)}
                {m.name_similarity ? ` · name similarity ${formatPercent(Number(m.name_similarity), 0)}` : ''}
              </p>
            </div>
            <Button size="sm" variant="secondary" onClick={() => onUse(m)}>
              Use existing
            </Button>
          </li>
        ))}
      </ul>
      {footer && <div className="mt-3 flex justify-end">{footer}</div>}
    </section>
  );
}
