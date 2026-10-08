/**
 * Four-eyes screening disposition (FR-CMP-014): one officer proposes
 * (screening:review), a DIFFERENT officer confirms (screening:clear), and the
 * originator of the application may not clear it. Pure, unit-tested; the
 * server remains the authority and its refusal is shown verbatim.
 */
import type { ScreeningAlert } from '@/api/lending';

export type StepAvailability = { visible: boolean; reason: string | null };
export type AlertActions = { propose: StepAvailability; confirm: StepAvailability };

export function alertActions(alert: Pick<ScreeningAlert, 'status' | 'proposed_by'>, userId: string, permissions: ReadonlySet<string>, originatorId: string | null = null): AlertActions {
  const none: StepAvailability = { visible: false, reason: null };
  const originator = originatorId !== null && originatorId === userId ? 'You originated this application, so you cannot disposition its screening alerts (segregation of duties).' : null;

  if (alert.status === 'open') {
    return {
      propose: { visible: true, reason: !permissions.has('screening:review') ? 'Requires the screening:review permission.' : originator },
      confirm: none,
    };
  }
  if (alert.status === 'pending_confirmation') {
    let reason: string | null = null;
    if (!permissions.has('screening:clear')) reason = 'Requires the screening:clear permission.';
    else if (alert.proposed_by && alert.proposed_by === userId) reason = 'You proposed this disposition. A different officer must confirm it (four-eyes).';
    else reason = originator;
    return { propose: none, confirm: { visible: true, reason } };
  }
  return { propose: none, confirm: none };
}

export const PROPOSE_REASON_MIN = 10;
