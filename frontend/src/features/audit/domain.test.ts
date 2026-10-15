import { describe, expect, it } from 'vitest';
import { canSee, NAV_GROUPS } from '@/features/navigation/nav';
import { actionLabel, evidenceFilename, personLabel, toFilterQuery } from './domain';

describe('audit explorer helpers', () => {
  it('builds filter[...] queries without blanks and converts the period to ISO 8601', () => {
    expect(toFilterQuery({ actor_id: ' u-1 ', action: '', entity_type: undefined, from: '2026-10-15T09:30' })).toEqual({
      'filter[actor_id]': 'u-1',
      'filter[from]': '2026-10-15T08:30:00.000Z',
    });
  });

  it('labels actions, people and the pack file', () => {
    expect(actionLabel('document.checklist_item.waived')).toBe('Document checklist item waived');
    expect(personLabel({ id: 'abc-123456', name: 'Kemi Ade' })).toBe('Kemi Ade');
    expect(personLabel({ id: '0190-aaaa-bbbbbb', name: null })).toBe('User bbbbbb');
    expect(personLabel({ id: 'system:workflow', name: null })).toBe('System · workflow');
    expect(personLabel(null)).toBe('—');
    expect(evidenceFilename('DEMO/2026 000006')).toBe('evidence-pack-DEMO_2026_000006.zip');
  });

  it('shows the assurance pages only to holders of audit:read', () => {
    const items = NAV_GROUPS.find((g) => g.id === 'assurance')?.items ?? [];
    expect(items.map((i) => i.to)).toEqual(['/audit/events', '/audit/overrides']);
    expect(items.every((i) => canSee(i, new Set(['audit:read'])))).toBe(true);
    expect(items.some((i) => canSee(i, new Set(['application:view'])))).toBe(false);
  });
});
