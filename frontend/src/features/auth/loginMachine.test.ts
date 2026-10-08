import { describe, expect, it } from 'vitest';
import { ApiProblem } from '@/api/problem';
import { groupSecret, initialLoginState, loginReducer, type LoginState } from './loginMachine';
import { safeNext } from './safeRedirect';

const failed = (detail = 'The credentials are invalid or the account cannot sign in at this time.') =>
  new ApiProblem({ type: 'urn:fundly:problem:authentication-failed', title: 'x', status: 401, detail, code: 'authentication-failed' });

describe('login state machine', () => {
  it('credentials → authenticated', () => {
    expect(loginReducer(initialLoginState, { type: 'RESULT', result: { status: 'authenticated' } })).toEqual({ step: 'authenticated' });
  });

  it('credentials → mfa → authenticated', () => {
    const mfa = loginReducer(initialLoginState, { type: 'RESULT', result: { status: 'mfa_required' } });
    expect(mfa).toEqual({ step: 'mfa', problem: null });
    expect(loginReducer(mfa, { type: 'RESULT', result: { status: 'authenticated' } }).step).toBe('authenticated');
  });

  it('credentials → enrol carries the secret and otpauth URI', () => {
    const s = loginReducer(initialLoginState, {
      type: 'RESULT',
      result: { status: 'mfa_enrollment_required', mfa_enrollment: { secret: 'JBSWY3DPEHPK3PXP', otpauth_uri: 'otpauth://totp/Fundly:ada?secret=JBSWY3DPEHPK3PXP' } },
    });
    expect(s).toEqual({ step: 'enrol', secret: 'JBSWY3DPEHPK3PXP', otpauthUri: 'otpauth://totp/Fundly:ada?secret=JBSWY3DPEHPK3PXP', problem: null });
  });

  it('a failed code keeps the user on the code step with the problem', () => {
    const mfa: LoginState = { step: 'mfa', problem: null };
    const s = loginReducer(mfa, { type: 'FAILED', problem: failed() });
    expect(s.step).toBe('mfa');
    expect(s.step === 'mfa' && s.problem?.code).toBe('authentication-failed');
  });

  it('an expired pending MFA sign-in returns to credentials', () => {
    const s = loginReducer({ step: 'mfa', problem: null }, { type: 'FAILED', problem: failed('No sign-in is awaiting MFA, or it has expired. Sign in again.') });
    expect(s.step).toBe('credentials');
  });

  it('a failed password stays on credentials; RESTART clears everything', () => {
    const s = loginReducer(initialLoginState, { type: 'FAILED', problem: failed() });
    expect(s).toMatchObject({ step: 'credentials' });
    expect(loginReducer({ step: 'enrol', secret: 'A', otpauthUri: 'B', problem: null }, { type: 'RESTART' })).toEqual(initialLoginState);
  });

  it('groups the setup key for legibility', () => {
    expect(groupSecret('JBSWY3DPEHPK3PXP')).toBe('JBSW Y3DP EHPK 3PXP');
  });
});

describe('safeNext', () => {
  it('only allows in-app paths', () => {
    expect(safeNext('/inbox?x=1')).toBe('/inbox?x=1');
    expect(safeNext('//evil.example')).toBe('/');
    expect(safeNext('https://evil.example')).toBe('/');
    expect(safeNext('/\\evil')).toBe('/');
    expect(safeNext('/login')).toBe('/');
    expect(safeNext(null)).toBe('/');
  });
});
