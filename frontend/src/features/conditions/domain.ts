/**
 * Pure conditions logic (CPR-01): status presentation, grouping, what the
 * officer and the checker can do, and form rules that mirror the API.
 */
import type { CheckItem, Condition } from '@/api/conditions';
import type { Tone } from '@/components/StatusBadge';

export const CONDITION_PERM = { manage: 'condition:manage', waiveApprove: 'condition:waive_approve' } as const;

export const STATUS_META: Record<Condition['status'], { label: string; tone: Tone }> = {
  open: { label: 'Open', tone: 'warning' },
  waiver_pending: { label: 'Waiver awaiting approval', tone: 'info' },
  satisfied: { label: 'Satisfied', tone: 'success' },
  waived: { label: 'Waived', tone: 'neutral' },
};

export const CHECK_STATUS_META: Record<CheckItem['status'], { label: string; tone: Tone }> = {
  pass: { label: 'Pass', tone: 'success' },
  fail: { label: 'Fail', tone: 'danger' },
  not_applicable: { label: 'N/A', tone: 'neutral' },
};

/** Statuses where conditions are worked (between acceptance and booking). */
export const WORKING_STATUSES = ['accepted', 'conditions_precedent', 'ready_for_disbursement', 'disbursing'] as const;

export function isWorking(status: string): boolean {
  return (WORKING_STATUSES as readonly string[]).includes(status);
}

export function grouped(list: readonly Condition[]): { precedent: Condition[]; subsequent: Condition[] } {
  return { precedent: list.filter((c) => c.kind === 'precedent'), subsequent: list.filter((c) => c.kind === 'subsequent') };
}

export function outstandingMandatory(list: readonly Condition[]): Condition[] {
  return list.filter((c) => c.kind === 'precedent' && c.mandatory && (c.status === 'open' || c.status === 'waiver_pending'));
}

export function workReason(appStatus: string, permissions: ReadonlySet<string>, c?: Pick<Condition, 'status' | 'kind'>): string | null {
  const booked = appStatus === 'booked' && c?.kind === 'subsequent';
  if (!isWorking(appStatus) && !booked) return 'Conditions are worked between acceptance and booking.';
  if (!permissions.has(CONDITION_PERM.manage)) return `Requires the ${CONDITION_PERM.manage} permission.`;
  if (c && c.status !== 'open' && c.status !== 'waiver_pending') return `This condition is ${c.status}.`;
  return null;
}

export function canDecideWaiver(c: Pick<Condition, 'status' | 'waiver'>, permissions: ReadonlySet<string>): boolean {
  return c.status === 'waiver_pending' && c.waiver !== null && permissions.has(CONDITION_PERM.waiveApprove);
}

export function addErrors(f: { description: string; dueDate: string }, today: string): { description?: string; dueDate?: string } {
  const e: { description?: string; dueDate?: string } = {};
  if (f.description.trim().length < 5) e.description = 'Describe the condition (5 characters or more).';
  if (f.dueDate && f.dueDate < today) e.dueDate = 'The due date cannot be in the past.';
  return e;
}

export function satisfyErrors(f: { note: string; reference: string; file: File | null }): { note?: string; evidence?: string } {
  const e: { note?: string; evidence?: string } = {};
  if (f.note.trim().length < 5) e.note = 'Add a sign-off note (5 characters or more).';
  if (!f.file && f.reference.trim() === '') e.evidence = 'Attach the evidence or give a reference to it.';
  return e;
}

export function accountErrors(bankCode: string, accountNo: string): { bankCode?: string; accountNo?: string } {
  const e: { bankCode?: string; accountNo?: string } = {};
  if (!/^\d{3,6}$/.test(bankCode.trim())) e.bankCode = 'Enter the 3–6 digit bank code.';
  if (!/^\d{10}$/.test(accountNo.trim())) e.accountNo = 'Enter the 10-digit NUBAN account number.';
  return e;
}
