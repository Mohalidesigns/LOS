/**
 * Pure mapping from the 22 canonical statuses (TRD §6.2) to the 12-step
 * canonical path shown by the StageTracker, plus the off-path interruptions
 * (on hold, returned for rework, closed). Unit-tested for every status.
 */
import { APPLICATION_STATUSES, type ApplicationStatus } from '@/components/StatusBadge';

export type StageKey =
  | 'draft'
  | 'submitted'
  | 'pre_qualified'
  | 'kyc_screening'
  | 'documentation'
  | 'assessment'
  | 'recommended'
  | 'approval'
  | 'offer'
  | 'conditions'
  | 'disbursement'
  | 'booked';

export const STAGES: readonly { key: StageKey; label: string }[] = [
  { key: 'draft', label: 'Draft' },
  { key: 'submitted', label: 'Submitted' },
  { key: 'pre_qualified', label: 'Pre-qualified' },
  { key: 'kyc_screening', label: 'KYC & screening' },
  { key: 'documentation', label: 'Documentation' },
  { key: 'assessment', label: 'Assessment' },
  { key: 'recommended', label: 'Recommended' },
  { key: 'approval', label: 'Approval' },
  { key: 'offer', label: 'Offer' },
  { key: 'conditions', label: 'Conditions' },
  { key: 'disbursement', label: 'Disbursement' },
  { key: 'booked', label: 'Booked' },
];

/** Where each on-path status sits on the tracker. */
const ON_PATH: Partial<Record<ApplicationStatus, StageKey>> = {
  draft: 'draft',
  submitted: 'submitted',
  pre_qualified: 'pre_qualified',
  kyc_screening: 'kyc_screening',
  documentation: 'documentation',
  assessment: 'assessment',
  recommended: 'recommended',
  approval: 'approval',
  approved: 'offer', // approval done; the offer is being prepared
  counter_offered: 'offer',
  offer_issued: 'offer',
  accepted: 'conditions',
  conditions_precedent: 'conditions',
  ready_for_disbursement: 'disbursement',
  disbursing: 'disbursement',
  booked: 'booked',
};

export const TERMINAL_STATUSES: readonly ApplicationStatus[] = ['booked', 'declined', 'expired', 'withdrawn', 'cancelled'];
export const CLOSED_STATUSES: readonly ApplicationStatus[] = ['declined', 'expired', 'withdrawn', 'cancelled'];

export function isApplicationStatus(s: string): s is ApplicationStatus {
  return (APPLICATION_STATUSES as readonly string[]).includes(s);
}

export function isTerminal(status: string): boolean {
  return (TERMINAL_STATUSES as readonly string[]).includes(status);
}

/** Canonical rank (backend CanonicalStatus::rank); null for off-path statuses. */
const RANK: readonly ApplicationStatus[] = [
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
];

export function rankOf(status: string): number | null {
  const i = (RANK as readonly string[]).indexOf(status);
  return i < 0 ? null : i;
}

export type StepState = 'complete' | 'current' | 'upcoming';
export type Interruption = { kind: 'on_hold' | 'returned' | 'closed'; status: ApplicationStatus; label: string };
export type StageModel = {
  steps: { key: StageKey; label: string; state: StepState }[];
  /** Index of the current step; -1 when it cannot be placed (closed without history). */
  currentIndex: number;
  interruption: Interruption | null;
  /** True once the application is booked (every step complete). */
  done: boolean;
};

export type StageContext = {
  /** Application.resume_to (on hold). */
  resumeTo?: string | null;
  /** Application.return_to (returned for rework). */
  returnTo?: string | null;
  /** Last on-path status before a closure (e.g. from the timeline). */
  lastActive?: string | null;
};

const INTERRUPTION_LABEL: Partial<Record<ApplicationStatus, string>> = {
  on_hold: 'On hold',
  returned_for_rework: 'Returned for rework',
  declined: 'Declined',
  expired: 'Expired',
  withdrawn: 'Withdrawn',
  cancelled: 'Cancelled',
};

function placed(status: string | null | undefined): StageKey | null {
  if (!status || !isApplicationStatus(status)) return null;
  return ON_PATH[status] ?? null;
}

export function stageModel(status: string, ctx: StageContext = {}): StageModel {
  let anchor: StageKey | null = placed(status);
  let interruption: Interruption | null = null;

  if (isApplicationStatus(status) && anchor === null) {
    const label = INTERRUPTION_LABEL[status] ?? status;
    if (status === 'on_hold') {
      interruption = { kind: 'on_hold', status, label };
      anchor = placed(ctx.resumeTo);
    } else if (status === 'returned_for_rework') {
      interruption = { kind: 'returned', status, label };
      anchor = placed(ctx.returnTo);
    } else {
      interruption = { kind: 'closed', status, label };
      // A decline happens at approval (or at pre-qualification knock-out); otherwise use history.
      anchor = placed(ctx.lastActive) ?? (status === 'declined' ? 'approval' : null);
    }
  }

  const currentIndex = anchor === null ? -1 : STAGES.findIndex((s) => s.key === anchor);
  const done = status === 'booked';
  const steps = STAGES.map((s, i) => {
    let state: StepState = 'upcoming';
    if (done || i < currentIndex) state = 'complete';
    else if (i === currentIndex) state = 'current';
    return { key: s.key, label: s.label, state };
  });
  return { steps, currentIndex, interruption, done };
}
