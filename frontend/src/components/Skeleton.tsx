import { cn } from '@/lib/cn';

/** Decorative loading placeholder; pair with an aria-busy container or a live "Loading" label. */
export function Skeleton({ className }: { className?: string }) {
  return <span aria-hidden="true" className={cn('block animate-shimmer rounded-mark bg-skeleton', className)} />;
}
