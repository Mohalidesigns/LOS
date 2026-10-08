import type { ReactNode } from 'react';
import { Link } from 'react-router';
import { Lock } from 'lucide-react';
import { Card, EmptyState } from '@/components';
import { hasAny, usePermissions } from './session';

/** Page-level permission gate (deny by default): "You don't have access" + which permission is missing. */
export function RequirePermission({ anyOf, children }: { anyOf: readonly string[]; children: ReactNode }) {
  const permissions = usePermissions();
  if (anyOf.length === 0 || hasAny(permissions, anyOf)) return <>{children}</>;
  return (
    <Card>
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
    </Card>
  );
}
