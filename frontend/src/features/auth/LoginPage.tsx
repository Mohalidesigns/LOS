import { useEffect, useReducer, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, KeyRound, LockKeyhole, Mail, ShieldCheck } from 'lucide-react';
import { authApi, type LoginResult } from '@/api/auth';
import { isApiProblem, networkProblem, type ApiProblem } from '@/api/problem';
import { Banner, Button, ErrorSummary, FundlyGlyph, FundlyLogo, TextInput } from '@/components';
import { accessQuery, sessionKeys } from './session';
import { groupSecret, initialLoginState, loginReducer } from './loginMachine';
import { safeNext } from './safeRedirect';

const credentialsSchema = z.object({
  email: z.string().trim().min(1, 'Enter your work email').max(254).email('Enter a valid email address'),
  password: z.string().min(1, 'Enter your password').max(1024),
});
type Credentials = z.infer<typeof credentialsSchema>;

const codeSchema = z.object({ code: z.string().regex(/^\d{6}$/, 'Enter the 6-digit code from your authenticator app') });
type CodeValues = z.infer<typeof codeSchema>;

function asProblem(e: unknown): ApiProblem {
  return isApiProblem(e) ? e : networkProblem(e);
}

export function LoginPage() {
  const [state, dispatch] = useReducer(loginReducer, initialLoginState);
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const qc = useQueryClient();
  const sessionEnded = params.get('reason') === 'session-ended';
  const signedOut = params.get('reason') === 'signed-out';
  const [noticeDismissed, setNoticeDismissed] = useState(false);

  const finish = async (result: LoginResult) => {
    if (result.status !== 'authenticated') {
      dispatch({ type: 'RESULT', result });
      return;
    }
    qc.removeQueries({ queryKey: ['session'] });
    if (result.user) qc.setQueryData(sessionKeys.me, result.user);
    await qc.query(accessQuery);
    dispatch({ type: 'RESULT', result });
    await navigate(safeNext(params.get('next')), { replace: true });
  };

  const credentials = useForm<Credentials>({ resolver: zodResolver(credentialsSchema), defaultValues: { email: '', password: '' } });
  const code = useForm<CodeValues>({ resolver: zodResolver(codeSchema), defaultValues: { code: '' } });

  const onCredentials = credentials.handleSubmit(async ({ email, password }) => {
    try {
      await finish(await authApi.login(email, password));
      credentials.resetField('password');
    } catch (e) {
      credentials.resetField('password');
      dispatch({ type: 'FAILED', problem: asProblem(e) });
    }
  });

  const onCode = code.handleSubmit(async ({ code: value }) => {
    try {
      await finish(await authApi.verifyMfa(value));
    } catch (e) {
      code.reset({ code: '' });
      dispatch({ type: 'FAILED', problem: asProblem(e) });
    }
  });

  // Move focus to the code field when the MFA step appears (instead of autoFocus).
  useEffect(() => {
    document.title = 'Sign in · Fundly LOS';
  }, []);

  const { setFocus } = code;
  useEffect(() => {
    if (state.step === 'mfa' || state.step === 'enrol') setFocus('code');
  }, [state.step, setFocus]);

  const codeField = code.register('code', {
    // Accept pasted codes with spaces or dashes ("123 456").
    setValueAs: (v: string) => v.replace(/\D/g, '').slice(0, 6),
  });

  const restart = () => {
    code.reset({ code: '' });
    dispatch({ type: 'RESTART' });
  };

  return (
    <main className="flex min-h-screen items-center justify-center bg-page p-4 md:p-8">
      <div className="grid w-full max-w-auth overflow-hidden rounded-overlay border bg-surface shadow-card lg:grid-cols-2">
        {/* Brand panel */}
        <section data-surface="brand" aria-label="Fundly" className="relative flex flex-col justify-between overflow-hidden bg-brand p-8 text-on-brand md:p-10">
          <FundlyLogo onBrand />
          <div className="relative z-base mt-10 hidden lg:block">
            <p className="text-meta font-semibold uppercase tracking-eyebrow text-on-brand-muted">Loan origination</p>
            <p className="mt-3 text-heading text-on-brand">Lending decisions, from application to booking.</p>
            <ul className="mt-6 space-y-3 text-body text-on-brand-muted">
              <li className="flex items-center gap-3">
                <span aria-hidden="true" className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-contrast text-on-accent-fill">
                  <ShieldCheck className="h-icon w-icon" />
                </span>
                Multi-factor sign-in on every session
              </li>
              <li className="flex items-center gap-3">
                <span aria-hidden="true" className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-contrast text-on-accent-fill">
                  <KeyRound className="h-icon w-icon" />
                </span>
                Maker-checker on every sensitive change
              </li>
            </ul>
          </div>
          <p className="relative z-base mt-8 hidden text-meta text-on-brand-muted lg:block">Authorised staff only. Activity is recorded in the audit trail.</p>
          <FundlyGlyph onBrand className="pointer-events-none absolute -bottom-12 -right-12 h-[14rem] w-[14rem] opacity-20" />
        </section>

        {/* Form panel */}
        <section className="p-6 sm:p-10" aria-labelledby="login-title">
          {(sessionEnded || signedOut) && !noticeDismissed && state.step === 'credentials' && (
            <Banner
              tone={sessionEnded ? 'warning' : 'success'}
              title={sessionEnded ? 'Your session ended' : 'You have signed out'}
              onDismiss={() => setNoticeDismissed(true)}
              className="mb-6"
            >
              {sessionEnded ? 'For your security you were signed out. Sign in again to continue.' : 'Sign in again whenever you are ready.'}
            </Banner>
          )}

          {state.step === 'credentials' && (
            <>
              <h1 id="login-title" className="text-heading text-emphasis">
                Sign in
              </h1>
              <p className="mt-1 text-body text-secondary">Use your bank work account.</p>
              <form noValidate onSubmit={onCredentials} className="mt-6 space-y-4">
                <ErrorSummary problem={state.problem} title="Sign-in failed" />
                <TextInput
                  label="Work email"
                  type="email"
                  autoComplete="username"
                  inputMode="email"
                  required
                  inputSize="lg"
                  leading={<Mail className="h-icon w-icon" />}
                  error={credentials.formState.errors.email?.message}
                  {...credentials.register('email')}
                />
                <TextInput
                  label="Password"
                  type="password"
                  autoComplete="current-password"
                  required
                  inputSize="lg"
                  leading={<LockKeyhole className="h-icon w-icon" />}
                  error={credentials.formState.errors.password?.message}
                  {...credentials.register('password')}
                />
                <Button type="submit" size="lg" fullWidth loading={credentials.formState.isSubmitting}>
                  Continue
                </Button>
              </form>
            </>
          )}

          {(state.step === 'mfa' || state.step === 'enrol') && (
            <>
              <Button variant="ghost" size="sm" onClick={restart} leadingIcon={<ArrowLeft aria-hidden="true" className="h-icon-sm w-icon-sm" />} className="-ml-3 mb-4">
                Use a different account
              </Button>
              <h1 id="login-title" className="text-heading text-emphasis">
                {state.step === 'enrol' ? 'Set up your authenticator' : 'Enter your code'}
              </h1>
              {state.step === 'enrol' ? (
                <div className="mt-2 space-y-3 text-body text-secondary">
                  <p>Multi-factor sign-in is required. Add this account to an authenticator app (for example Microsoft or Google Authenticator) using the setup key, then enter the 6-digit code it shows.</p>
                  <div className="rounded-card bg-muted p-4">
                    <p className="text-meta font-semibold uppercase tracking-eyebrow text-tertiary">Setup key</p>
                    <p className="ref mt-1 select-all break-all text-title text-emphasis" aria-label={`Setup key ${state.secret.split('').join(' ')}`}>
                      {groupSecret(state.secret)}
                    </p>
                    <details className="mt-3">
                      <summary className="min-h-target cursor-pointer text-body-sm font-medium text-link">Show setup URI</summary>
                      <p className="ref mt-2 select-all break-all text-meta text-secondary">{state.otpauthUri}</p>
                    </details>
                  </div>
                </div>
              ) : (
                <p className="mt-1 text-body text-secondary">Open your authenticator app and enter the 6-digit code for Fundly LOS.</p>
              )}
              <form noValidate onSubmit={onCode} className="mt-6 space-y-4">
                <ErrorSummary problem={state.problem} title="Code not accepted" />
                <TextInput
                  label="6-digit code"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  pattern="[0-9]*"
                  maxLength={9}
                  required
                  inputSize="lg"
                  className="[&_input]:font-mono [&_input]:text-title [&_input]:tracking-eyebrow"
                  error={code.formState.errors.code?.message}
                  {...codeField}
                />
                <Button type="submit" size="lg" fullWidth loading={code.formState.isSubmitting}>
                  Verify and sign in
                </Button>
              </form>
            </>
          )}

          {state.step === 'authenticated' && (
            <p role="status" className="text-body text-secondary">
              Signed in. Loading your workspace…
            </p>
          )}
        </section>
      </div>
    </main>
  );
}

export default LoginPage;
