import { Lock } from 'lucide-react';
import { cn } from '@/lib/cn';

/**
 * PII exactly as the API serves it (masked by default, brief §7.3). Mono text
 * with a lock glyph; screen readers hear "<label>, masked, ending 91".
 * Unmask (POST /pii/unmask) is not in the P1 contract yet, so there is no Reveal.
 */
export function MaskedValue({ label, value, className }: { label: string; value: string | null | undefined; className?: string }) {
  if (!value) return <span className={cn('text-tertiary', className)}>Not recorded</span>;
  const tail = value.replace(/[^0-9A-Za-z]/g, '').slice(-2);
  return (
    <span className={cn('inline-flex items-center gap-1.5', className)}>
      <Lock aria-hidden="true" className="h-icon-sm w-icon-sm text-indicator" />
      <span aria-hidden="true" className="ref">
        {value}
      </span>
      <span className="sr-only">{`${label}, masked${tail ? `, ending ${tail}` : ''}`}</span>
    </span>
  );
}
