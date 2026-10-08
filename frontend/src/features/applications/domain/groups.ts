import type { ApplicationStatus } from '@/components/StatusBadge';

/** Filter chips for the Applications / Pipeline queues (status groups). */
export type StatusGroupId = 'capture' | 'kyc' | 'documentation' | 'assessment' | 'closed';

export const STATUS_GROUPS: readonly { id: StatusGroupId; label: string; statuses: readonly ApplicationStatus[] }[] = [
  { id: 'capture', label: 'Capture', statuses: ['draft', 'submitted', 'pre_qualified', 'returned_for_rework'] },
  { id: 'kyc', label: 'KYC', statuses: ['kyc_screening'] },
  { id: 'documentation', label: 'Documentation', statuses: ['documentation'] },
  {
    id: 'assessment',
    label: 'Assessment+',
    statuses: [
      'assessment',
      'recommended',
      'approval',
      'approved',
      'counter_offered',
      'offer_issued',
      'accepted',
      'conditions_precedent',
      'ready_for_disbursement',
      'disbursing',
      'on_hold',
    ],
  },
  { id: 'closed', label: 'Closed', statuses: ['booked', 'declined', 'expired', 'withdrawn', 'cancelled'] },
];

export function groupStatuses(ids: readonly StatusGroupId[]): ApplicationStatus[] {
  return STATUS_GROUPS.filter((g) => ids.includes(g.id)).flatMap((g) => g.statuses);
}

export function countFor(byStatus: Readonly<Record<string, number>>, statuses: readonly string[]): number {
  return statuses.reduce((sum, s) => sum + (byStatus[s] ?? 0), 0);
}

export function isStatusGroupId(v: string): v is StatusGroupId {
  return STATUS_GROUPS.some((g) => g.id === v);
}
