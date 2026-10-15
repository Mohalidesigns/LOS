import type { OverrideRecord } from '@/api/audit';
import { lagosLocalToIso } from '@/features/applications/case/TimelineTab';

export const AUDIT_PERM = { read: 'audit:read', integrationRead: 'integration:read' } as const;

/**
 * Form values → `filter[...]` query: blanks dropped, values trimmed, and the
 * datetime-local `from`/`to` (Lagos wall-clock) converted to ISO 8601.
 */
export function toFilterQuery(values: Record<string, string | undefined>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, raw] of Object.entries(values)) {
    const v = raw?.trim();
    if (!v) continue;
    out[`filter[${k}]`] = k === 'from' || k === 'to' ? lagosLocalToIso(v) : v;
  }
  return out;
}

export const OVERRIDE_TYPE: Record<OverrideRecord['type'], { label: string; tone: 'warning' | 'info' | 'neutral' }> = {
  policy_exception: { label: 'Policy exception', tone: 'warning' },
  waiver: { label: 'Waiver', tone: 'info' },
  manual_intervention: { label: 'Manual intervention', tone: 'neutral' },
};

/** "condition.waived" → "Condition waived". */
export function actionLabel(action: string): string {
  const s = action.replace(/[._]/g, ' ').trim();
  return s.charAt(0).toUpperCase() + s.slice(1);
}

/** A person from the report, or a short id when the name is unknown (system identities). */
export function personLabel(p: { id: string; name: string | null } | null): string {
  if (!p) return '—';
  return p.name ?? (p.id.startsWith('system:') ? p.id.replace('system:', 'System · ') : `User ${p.id.slice(-6)}`);
}

export function evidenceFilename(reference: string): string {
  return `evidence-pack-${reference.replace(/[^A-Za-z0-9._-]+/g, '_')}.zip`;
}
