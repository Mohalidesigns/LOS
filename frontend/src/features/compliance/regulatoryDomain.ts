/** Pure presentation for the regulatory rule register (FR-CMP-003). */
import type { RegulatoryLimitCheck, RegulatoryRuleRow } from '@/api/regulatory';
import type { Tone } from '@/components/StatusBadge';

export const LIMIT_OUTCOME: Record<RegulatoryLimitCheck['outcome'], { label: string; tone: Tone }> = {
  pass: { label: 'Within limit', tone: 'success' },
  breach: { label: 'Limit breached', tone: 'danger' },
  unavailable: { label: 'Exposure unavailable', tone: 'warning' },
  not_applicable: { label: 'Not applicable', tone: 'neutral' },
};

/** "BOFIA 2020 · s.20" style citation line. */
export function citationLine(c: Record<string, unknown>): string {
  const parts = [c.instrument, c.section].filter((p): p is string => typeof p === 'string' && p.trim() !== '');
  return parts.join(' · ');
}

/** "20% of shareholders funds unimpaired", "5 years", "30 days". */
export function parameterSummary(r: Pick<RegulatoryRuleRow, 'code' | 'parameters'>): string | null {
  const p = r.parameters ?? {};
  switch (r.code) {
    case 'single_obligor_limit':
      return typeof p.limit_percent === 'string' ? `${p.limit_percent}% of shareholders' funds unimpaired` : null;
    case 'origination_record_retention':
      return typeof p.years === 'number' ? `${p.years} years after booking` : null;
    case 'bureau_report_validity':
      return typeof p.days === 'number' ? `Bureau report valid ${p.days} days` : null;
    default:
      return null;
  }
}

/** Rules in force first, then by code; superseded and future versions after. */
export function orderRules(rules: readonly RegulatoryRuleRow[]): RegulatoryRuleRow[] {
  return [...rules].sort((a, b) => Number(b.in_force) - Number(a.in_force) || a.code.localeCompare(b.code) || b.effective_from.localeCompare(a.effective_from));
}
