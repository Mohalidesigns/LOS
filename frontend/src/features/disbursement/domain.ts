/**
 * Pure disbursement logic (DSB-01/02): status presentation, saga step labels,
 * which instruction is current, and what maker, checker and retrier may do.
 */
import type { Disbursement, SagaStep } from '@/api/disbursement';
import type { Tone } from '@/components/StatusBadge';

export const DSB_PERM = { make: 'disbursement:make', check: 'disbursement:check', retry: 'disbursement:retry', reconcile: 'reconciliation:run' } as const;

export const STATUS_META: Record<Disbursement['status'], { label: string; tone: Tone; help: string }> = {
  pending_release: { label: 'Awaiting release', tone: 'warning', help: 'A checker who is neither the maker nor an approver must release it, with a fresh step-up.' },
  pending_cba: { label: 'Booking with the CBA', tone: 'info', help: 'The booking saga is running. Nothing is assumed until the core banking system confirms.' },
  failed_retryable: { label: 'Failed · will retry', tone: 'warning', help: 'A transient CBA failure. It is retried automatically with the same keys, so nothing is posted twice.' },
  failed_intervention: { label: 'Needs intervention', tone: 'danger', help: 'The saga stopped and needs a person. Fix the cause, then retry: completed steps are not repeated.' },
  compensated: { label: 'Rejected · account closed', tone: 'danger', help: 'The CBA refused the disbursement; the loan account was closed. Make a new instruction once the cause is fixed.' },
  booked: { label: 'Booked', tone: 'success', help: 'Disbursed and on the books.' },
  cancelled: { label: 'Cancelled', tone: 'neutral', help: 'Withdrawn before release.' },
};

export const STEP_LABEL: Record<string, string> = {
  ensure_customer: 'Customer in the CBA',
  create_loan_account: 'Loan account opened',
  disburse: 'Funds disbursed',
  post_fees: 'Upfront fees posted',
  schedule_check: 'Schedule matches the offer',
  book: 'Booked',
  advice: 'Disbursement advice',
  compensate: 'Compensation (account closed)',
};

export function stepLabel(s: Pick<SagaStep, 'step'>): string {
  return STEP_LABEL[s.step] ?? s.step.replace(/_/g, ' ');
}

/** A live instruction if there is one, else the newest. */
export function currentInstruction(list: readonly Disbursement[]): Disbursement | null {
  return list.find((d) => d.status !== 'cancelled' && d.status !== 'compensated') ?? list[0] ?? null;
}

export function makeReason(appStatus: string, permissions: ReadonlySet<string>, current: Disbursement | null): string | null {
  if (appStatus !== 'ready_for_disbursement' && appStatus !== 'disbursing') return 'An instruction is made once the application is ready for disbursement.';
  if (current && current.status !== 'cancelled' && current.status !== 'compensated') return 'There is already a live instruction.';
  if (!permissions.has(DSB_PERM.make)) return `Requires the ${DSB_PERM.make} permission.`;
  return null;
}

/** Client-side mirror of FR-DSB-009 so the checker sees why before trying; the server decides. */
export function releaseReason(d: Pick<Disbursement, 'status' | 'made_by'>, permissions: ReadonlySet<string>, meId: string | null): string | null {
  if (d.status !== 'pending_release') return 'Only an instruction awaiting release can be released.';
  if (!permissions.has(DSB_PERM.check)) return `Requires the ${DSB_PERM.check} permission.`;
  if (meId !== null && d.made_by?.id === meId) return 'You made this instruction; a different person must release it.';
  return null;
}

export function retryReason(d: Pick<Disbursement, 'status'>, permissions: ReadonlySet<string>): string | null {
  if (d.status !== 'failed_retryable' && d.status !== 'failed_intervention') return 'Only a failed instruction can be retried.';
  if (!permissions.has(DSB_PERM.retry)) return `Requires the ${DSB_PERM.retry} permission.`;
  return null;
}

export function isRunning(d: Pick<Disbursement, 'status'> | null): boolean {
  return d?.status === 'pending_cba' || d?.status === 'failed_retryable';
}
