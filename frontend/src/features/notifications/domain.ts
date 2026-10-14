/** Pure presentation for notifications and the communication log. */
import type { Communication } from '@/api/notifications';
import type { Tone } from '@/components/StatusBadge';

export const STATUS_META: Record<Communication['status'], { label: string; tone: Tone }> = {
  queued: { label: 'Queued', tone: 'info' },
  sent: { label: 'Sent', tone: 'success' },
  failed: { label: 'Not sent', tone: 'danger' },
  delivered: { label: 'Delivered', tone: 'success' },
  read: { label: 'Read', tone: 'neutral' },
};

export const CHANNEL_LABEL: Record<Communication['channel'], string> = { email: 'Email', sms: 'SMS', in_app: 'In-app' };

export const EVENT_LABEL: Record<string, string> = {
  'application.received': 'Application received',
  'offer.issued': 'Offer ready',
  'offer.expiring': 'Offer expiry reminder',
  'facility.booked': 'Loan disbursed',
  'facility.booked.staff': 'Booked',
  'approval.awaiting': 'Approval needed',
  'screening.alert': 'Screening alert',
  'disbursement.ready': 'Ready to disburse',
  'disbursement.awaiting_release': 'Release needed',
  'disbursement.failed': 'Disbursement needs attention',
};

export function eventLabel(event: string): string {
  return EVENT_LABEL[event] ?? event.replace(/[._]/g, ' ');
}

/** Group a newest-first list into Today / Earlier for the inbox. */
export function groupByDay<T extends { created_at: string }>(items: readonly T[], now: Date = new Date()): { today: T[]; earlier: T[] } {
  const day = now.toISOString().slice(0, 10);
  return { today: items.filter((i) => i.created_at.slice(0, 10) === day), earlier: items.filter((i) => i.created_at.slice(0, 10) !== day) };
}
