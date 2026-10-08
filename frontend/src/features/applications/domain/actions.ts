/**
 * Which lifecycle actions a user sees on a case, from the status (backend
 * StateMachine rules) and the user's effective permissions. Pure and tested.
 * Deny by default: an action the user lacks the permission for is returned
 * with `disabledReason` so the UI shows it disabled with the reason (brief §6.1).
 */
import type { ApplicationAction } from '@/api/lending';
import { rankOf } from './stages';

export const PERM = {
  view: 'application:view',
  originate: 'application:originate',
  recommend: 'application:recommend',
  partyManage: 'party:manage',
  docUpload: 'document:upload',
  docVerify: 'document:verify',
  screeningReview: 'screening:review',
  screeningClear: 'screening:clear',
} as const;

/** Permission the backend checks per action (actOnApplication description). */
export const ACTION_PERMISSION: Record<ApplicationAction, string> = {
  submit: PERM.originate,
  hold: PERM.originate,
  resume: PERM.originate,
  resubmit: PERM.originate,
  withdraw: PERM.originate,
  cancel: PERM.originate,
  recommend: PERM.recommend,
  return: PERM.recommend,
};

export const ACTION_LABEL: Record<ApplicationAction, string> = {
  submit: 'Submit application',
  resubmit: 'Resubmit',
  resume: 'Resume',
  recommend: 'Recommend',
  hold: 'Put on hold',
  return: 'Return for rework',
  withdraw: 'Withdraw',
  cancel: 'Cancel application',
};

/** Actions that need a reason code (backend ApplicationAction::requiresReason). */
export const REASON_REQUIRED: readonly ApplicationAction[] = ['withdraw', 'cancel', 'hold', 'return'];

export type ActionOption = { action: ApplicationAction; label: string; disabledReason: string | null };

const READY_RANK = rankOf('ready_for_disbursement') ?? 13;

/** Hold / rework are allowed on the canonical path up to Ready for disbursement. */
export function crossCuttingAllowed(status: string): boolean {
  const r = rankOf(status);
  return r !== null && r <= READY_RANK;
}

/** Withdraw / cancel also end an application that is on hold or back for rework. */
export function canClose(status: string): boolean {
  return crossCuttingAllowed(status) || status === 'on_hold' || status === 'returned_for_rework';
}

function option(action: ApplicationAction, permissions: ReadonlySet<string>): ActionOption {
  const needed = ACTION_PERMISSION[action];
  return {
    action,
    label: ACTION_LABEL[action],
    disabledReason: permissions.has(needed) ? null : `Requires the ${needed} permission.`,
  };
}

/** The one primary button in the context bar, chosen by status then permission. */
export function primaryAction(status: string, permissions: ReadonlySet<string>): ActionOption | null {
  switch (status) {
    case 'draft':
      return option('submit', permissions);
    case 'returned_for_rework':
      return option('resubmit', permissions);
    case 'on_hold':
      return option('resume', permissions);
    case 'assessment':
      return option('recommend', permissions);
    default:
      return null;
  }
}

/** Earlier canonical stages a case can be returned to (backend StateMachine::canReturnTo). */
export function returnTargets(status: string): string[] {
  const r = rankOf(status);
  if (r === null || !crossCuttingAllowed(status)) return [];
  const targets = ['draft', 'kyc_screening', 'documentation', 'assessment'];
  return targets.filter((t) => {
    const tr = rankOf(t);
    return tr !== null && tr < r;
  });
}

/** Secondary actions for the case "Actions" menu (never repeats the primary). */
export function menuActions(status: string, permissions: ReadonlySet<string>): ActionOption[] {
  const out: ActionOption[] = [];
  const primary = primaryAction(status, permissions)?.action;
  if (status === 'assessment' && primary !== 'recommend') out.push(option('recommend', permissions));
  if (crossCuttingAllowed(status)) out.push(option('hold', permissions));
  if (returnTargets(status).length > 0 && status !== 'draft') out.push(option('return', permissions));
  if (canClose(status)) {
    out.push(option('withdraw', permissions));
    out.push(option('cancel', permissions));
  }
  return out;
}

/** Captured fields may be amended before approval (backend CanonicalStatus::isPreApproval). */
export function canAmend(status: string): boolean {
  const r = rankOf(status);
  return status === 'returned_for_rework' || (r !== null && r <= (rankOf('recommended') ?? 6));
}
