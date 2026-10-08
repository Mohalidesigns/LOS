import type { LoginResult } from '@/api/auth';
import type { ApiProblem } from '@/api/problem';

/**
 * Sign-in state machine (pure; unit-tested):
 *
 *   credentials ──login──▶ authenticated
 *        │                   ▲
 *        ├─ mfa_required ──▶ mfa ──verify──┤
 *        └─ mfa_enrollment_required ──▶ enrol ──verify──┘
 *   any ──RESTART──▶ credentials
 */
export type LoginState =
  | { step: 'credentials'; problem: ApiProblem | null }
  | { step: 'mfa'; problem: ApiProblem | null }
  | { step: 'enrol'; secret: string; otpauthUri: string; problem: ApiProblem | null }
  | { step: 'authenticated' };

export type LoginEvent =
  | { type: 'RESULT'; result: LoginResult }
  | { type: 'FAILED'; problem: ApiProblem }
  | { type: 'RESTART' };

export const initialLoginState: LoginState = { step: 'credentials', problem: null };

export function stateFromResult(result: LoginResult, previous: LoginState): LoginState {
  switch (result.status) {
    case 'authenticated':
      return { step: 'authenticated' };
    case 'mfa_required':
      return { step: 'mfa', problem: null };
    case 'mfa_enrollment_required': {
      const enrolment = result.mfa_enrollment ?? (previous.step === 'enrol' ? { secret: previous.secret, otpauth_uri: previous.otpauthUri } : undefined);
      if (!enrolment) return { step: 'mfa', problem: null };
      return { step: 'enrol', secret: enrolment.secret, otpauthUri: enrolment.otpauth_uri, problem: null };
    }
  }
}

export function loginReducer(state: LoginState, event: LoginEvent): LoginState {
  switch (event.type) {
    case 'RESULT':
      return stateFromResult(event.result, state);
    case 'FAILED':
      if (state.step === 'authenticated') return state;
      // The pending MFA sign-in expired server-side: start over.
      if (state.step !== 'credentials' && event.problem.detail.includes('Sign in again')) {
        return { step: 'credentials', problem: event.problem };
      }
      return { ...state, problem: event.problem };
    case 'RESTART':
      return initialLoginState;
  }
}

/** Split a base32 secret into groups of 4 for legible manual entry. */
export function groupSecret(secret: string): string {
  return secret.replace(/\s+/g, '').replace(/(.{4})/g, '$1 ').trim();
}
