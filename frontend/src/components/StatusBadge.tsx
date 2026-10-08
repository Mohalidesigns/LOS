import { cn } from '@/lib/cn';

export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral' | 'brand';

/** Canonical application status (TRD §6.2, platform-owned). */
export const APPLICATION_STATUSES = [
  'draft',
  'submitted',
  'pre_qualified',
  'kyc_screening',
  'documentation',
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
  'booked',
  'declined',
  'expired',
  'on_hold',
  'returned_for_rework',
  'withdrawn',
  'cancelled',
] as const;

export type ApplicationStatus = (typeof APPLICATION_STATUSES)[number];

export const STATUS_META: Record<ApplicationStatus, { label: string; tone: Tone }> = {
  draft: { label: 'Draft', tone: 'neutral' },
  submitted: { label: 'Submitted', tone: 'info' },
  pre_qualified: { label: 'Pre-qualified', tone: 'info' },
  kyc_screening: { label: 'KYC & screening', tone: 'info' },
  documentation: { label: 'Documentation', tone: 'info' },
  assessment: { label: 'Assessment', tone: 'info' },
  recommended: { label: 'Recommended', tone: 'info' },
  approval: { label: 'In approval', tone: 'warning' },
  approved: { label: 'Approved', tone: 'success' },
  counter_offered: { label: 'Counter-offered', tone: 'warning' },
  offer_issued: { label: 'Offer issued', tone: 'info' },
  accepted: { label: 'Accepted', tone: 'success' },
  conditions_precedent: { label: 'Conditions precedent', tone: 'warning' },
  ready_for_disbursement: { label: 'Ready to disburse', tone: 'success' },
  disbursing: { label: 'Disbursing', tone: 'warning' },
  booked: { label: 'Booked', tone: 'success' },
  declined: { label: 'Declined', tone: 'danger' },
  expired: { label: 'Expired', tone: 'neutral' },
  on_hold: { label: 'On hold', tone: 'warning' },
  returned_for_rework: { label: 'Returned for rework', tone: 'warning' },
  withdrawn: { label: 'Withdrawn', tone: 'neutral' },
  cancelled: { label: 'Cancelled', tone: 'neutral' },
};

export function statusMeta(status: string): { label: string; tone: Tone } {
  if (status in STATUS_META) return STATUS_META[status as ApplicationStatus];
  const label = status.replace(/[_-]+/g, ' ').replace(/^\w/, (c) => c.toUpperCase());
  return { label: label || 'Unknown', tone: 'neutral' };
}

export const toneClasses: Record<Tone, string> = {
  success: 'text-success border-success',
  warning: 'text-warning border-warning',
  danger: 'text-danger border-danger',
  info: 'text-info border-info',
  neutral: 'text-neutral border-neutral',
  brand: 'text-emphasis border-strong',
};

/** Outlined pill (loan-ui "Completed / Pending / Failed"). */
export function Pill({ tone, children, className }: { tone: Tone; children: React.ReactNode; className?: string }) {
  return (
    <span className={cn('inline-flex items-center whitespace-nowrap rounded-chip border bg-surface px-2 py-0.5 text-meta font-medium', toneClasses[tone], className)}>
      {children}
    </span>
  );
}

export function StatusBadge({ status, className }: { status: string; className?: string }) {
  const { label, tone } = statusMeta(status);
  return (
    <Pill tone={tone} className={className}>
      {label}
    </Pill>
  );
}
