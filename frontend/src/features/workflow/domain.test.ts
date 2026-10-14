import { describe, expect, it } from 'vitest';
import type { WorkflowTask } from '@/api/workflow';
import { mentionIds, remainingLabel, slaChip, stageOptions } from './domain';

const sla = (state: WorkflowTask['sla']['state'], breach: string | null = null): Pick<WorkflowTask, 'sla' | 'status'> => ({
  status: 'open',
  sla: { state, warn_at: null, breach_at: breach, paused_minutes: 0 },
});

describe('workflow presentation', () => {
  it('maps SLA states to chips', () => {
    expect(slaChip(sla('at_risk'))).toBe('at_risk');
    expect(slaChip(sla('none'))).toBeNull();
    expect(slaChip({ ...sla('breached'), status: 'completed' })).toBeNull();
  });

  it('shows time left or over', () => {
    const now = new Date('2026-10-19T10:00:00Z');
    expect(remainingLabel(sla('on_track', '2026-10-19T13:20:00Z'), now)).toBe('3h 20m left');
    expect(remainingLabel(sla('breached', '2026-10-19T08:00:00Z'), now)).toBe('2h 0m over');
    expect(remainingLabel(sla('on_track', '2026-10-21T11:00:00Z'), now)).toBe('2d 1h left');
    expect(remainingLabel(sla('paused', '2026-10-19T13:20:00Z'), now)).toBe('paused');
  });

  it('builds stage filters and resolves mentions', () => {
    const t = (code: string, name: string) => ({ stage: { code, name, status: code } });
    expect(stageOptions([t('kyc', 'KYC'), t('assessment', 'Assessment'), t('kyc', 'KYC')]).map((o) => o.value)).toEqual(['', 'assessment', 'kyc']);
    expect(
      mentionIds('Thanks @Tunde Analyst, see above', [
        { id: '1', name: 'Tunde Analyst' },
        { id: '2', name: 'Kemi' },
      ]),
    ).toEqual(['1']);
  });
});
