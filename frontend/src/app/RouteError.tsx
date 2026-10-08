import { isRouteErrorResponse, Link, useRouteError } from 'react-router';
import { AlertTriangle, Compass } from 'lucide-react';
import { FundlyGlyph } from '@/components/FundlyLogo';
import { isApiProblem } from '@/api/problem';
import { Card } from '@/components/Card';
import { EmptyState } from '@/components/EmptyState';

export function NotFoundPage() {
  return (
    <Card>
      <EmptyState
        icon={<Compass className="h-8 w-8" />}
        title="Page not found"
        action={
          <Link to="/" className="inline-flex min-h-target items-center rounded-pill bg-accent px-5 py-2 font-semibold text-on-accent hover:bg-accent-hover">
            Back to dashboard
          </Link>
        }
      >
        The page you asked for does not exist or has moved.
      </EmptyState>
    </Card>
  );
}

export function RouteError() {
  const error = useRouteError();
  const problem = isApiProblem(error) ? error : null;
  const detail = problem ? problem.detail : isRouteErrorResponse(error) ? `${error.status} ${error.statusText}` : 'Something went wrong while loading this page.';
  return (
    <main className="flex min-h-screen items-center justify-center bg-page p-6">
      <Card className="w-full max-w-modal">
        <EmptyState
          icon={<AlertTriangle className="h-8 w-8" />}
          title="We couldn't load this page"
          action={
            <a href="/" className="inline-flex min-h-target items-center rounded-pill bg-accent px-5 py-2 font-semibold text-on-accent hover:bg-accent-hover">
              Reload Fundly
            </a>
          }
        >
          <p>{detail}</p>
          {problem?.correlationId && (
            <p className="mt-2 text-meta text-tertiary">
              Reference: <span className="ref">{problem.correlationId}</span>
            </p>
          )}
        </EmptyState>
      </Card>
    </main>
  );
}

/** Shown while the first route loader (session check) runs on a hard load. */
export function BootFallback() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-3 bg-page" aria-busy="true">
      <FundlyGlyph className="h-10 w-10 animate-shimmer" />
      <p role="status" className="text-body-sm text-tertiary">
        Loading Fundly…
      </p>
    </div>
  );
}
