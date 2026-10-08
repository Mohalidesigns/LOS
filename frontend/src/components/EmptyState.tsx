import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export function EmptyState({ icon, title, children, action, className, headingLevel = 2 }: { icon?: ReactNode; title: string; children?: ReactNode; action?: ReactNode; className?: string; headingLevel?: 2 | 3 }) {
  const H = headingLevel === 2 ? 'h2' : 'h3';
  return (
    <div className={cn('flex flex-col items-center px-6 py-12 text-center', className)}>
      {icon && (
        <span aria-hidden="true" className="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-accent-fill text-on-accent-fill">
          {icon}
        </span>
      )}
      <H className="text-title-lg text-emphasis">{title}</H>
      {children && <div className="mt-2 max-w-prose text-body text-secondary">{children}</div>}
      {action && <div className="mt-5">{action}</div>}
    </div>
  );
}
