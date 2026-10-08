import { RotateCw } from 'lucide-react';
import { isApiProblem } from '@/api/problem';
import { Banner } from './Banner';
import { Button } from './Button';

/** Section-level error (brief §6.1): danger banner, the problem detail, its correlation id and Retry. */
export function SectionError({ error, title = "This section couldn't load", onRetry }: { error: unknown; title?: string; onRetry?: () => void }) {
  const problem = isApiProblem(error) ? error : null;
  const notFound = problem?.status === 404;
  return (
    <Banner tone="danger" title={notFound ? 'Not found or outside your scope' : title}>
      <p>{problem ? problem.detail : error instanceof Error ? error.message : 'Something went wrong.'}</p>
      {problem?.correlationId && (
        <p className="mt-1 text-meta">
          Reference: <span className="ref">{problem.correlationId}</span>
        </p>
      )}
      {onRetry && !notFound && (
        <Button variant="secondary" size="sm" className="mt-2" leadingIcon={<RotateCw aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={onRetry}>
          Retry
        </Button>
      )}
    </Banner>
  );
}
