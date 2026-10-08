import type { ReactNode } from 'react';
import { Link } from 'react-router';
import { Construction, Lock } from 'lucide-react';
import { Button, Card, EmptyState } from '@/components';
import { hasAny, usePermissions } from '@/features/auth/session';

export type PlaceholderProps = {
  title: string;
  description: ReactNode;
  phase: string;
  /** Permissions any of which grants the page (deny by default). Empty = any signed-in user. */
  anyOf: readonly string[];
  icon?: ReactNode;
};

/** Routed placeholder with an honest EmptyState until the module ships; enforces the nav permission. */
export function PlaceholderPage({ title, description, phase, anyOf, icon }: PlaceholderProps) {
  const permissions = usePermissions();
  const allowed = anyOf.length === 0 || hasAny(permissions, anyOf);
  return (
    <Card>
      {allowed ? (
        <EmptyState
          icon={icon ?? <Construction className="h-8 w-8" />}
          title={`${title} is on the way`}
          action={
            <Button variant="secondary" onClick={() => window.history.back()}>
              Go back
            </Button>
          }
        >
          <p>{description}</p>
          <p className="mt-2 text-body-sm text-tertiary">Planned for {phase}.</p>
        </EmptyState>
      ) : (
        <EmptyState
          icon={<Lock className="h-8 w-8" />}
          title="You don't have access to this page"
          action={
            <Link to="/" className="inline-flex min-h-target items-center rounded-pill bg-accent px-5 py-2 font-semibold text-on-accent hover:bg-accent-hover">
              Back to dashboard
            </Link>
          }
        >
          Ask an administrator for a role that includes one of: <span className="ref">{anyOf.join(', ')}</span>.
        </EmptyState>
      )}
    </Card>
  );
}
