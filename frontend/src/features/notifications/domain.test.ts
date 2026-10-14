import { describe, expect, it } from 'vitest';
import { CHANNEL_LABEL, eventLabel, groupByDay } from './domain';

describe('notifications presentation', () => {
  it('labels known events and humanises unknown ones', () => {
    expect(eventLabel('approval.awaiting')).toBe('Approval needed');
    expect(eventLabel('collateral.revalued')).toBe('collateral revalued');
  });

  it('labels every channel', () => {
    expect(Object.values(CHANNEL_LABEL)).toEqual(['Email', 'SMS', 'WhatsApp', 'In-app']);
  });

  it('splits today from earlier', () => {
    const now = new Date('2026-10-14T15:00:00Z');
    const g = groupByDay([{ created_at: '2026-10-14T09:00:00Z' }, { created_at: '2026-10-13T22:00:00Z' }], now);
    expect(g.today).toHaveLength(1);
    expect(g.earlier).toHaveLength(1);
  });
});
