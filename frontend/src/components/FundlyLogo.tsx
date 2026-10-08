import { cn } from '@/lib/cn';

/**
 * Fundly brand mark: a four-petal glyph (after loan-ui's mark) + wordmark.
 * Colours come from currentColor / tokens; `onBrand` renders lime petals for dark surfaces.
 */
export function FundlyGlyph({ className, onBrand = false }: { className?: string; onBrand?: boolean }) {
  return (
    <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false" className={cn(onBrand ? 'text-brand-contrast' : 'text-emphasis', className)}>
      <g fill="currentColor">
        <path d="M4 9.5A5.5 5.5 0 0 1 9.5 4H14v4.5A5.5 5.5 0 0 1 8.5 14H4V9.5Z" />
        <path d="M28 9.5A5.5 5.5 0 0 0 22.5 4H18v4.5a5.5 5.5 0 0 0 5.5 5.5H28V9.5Z" />
        <path d="M4 22.5A5.5 5.5 0 0 0 9.5 28H14v-4.5A5.5 5.5 0 0 0 8.5 18H4v4.5Z" />
        <path d="M28 22.5a5.5 5.5 0 0 1-5.5 5.5H18v-4.5a5.5 5.5 0 0 1 5.5-5.5H28v4.5Z" />
      </g>
    </svg>
  );
}

export function FundlyLogo({ className, onBrand = false, compact = false }: { className?: string; onBrand?: boolean; compact?: boolean }) {
  return (
    <span className={cn('inline-flex items-center gap-2.5', className)}>
      <FundlyGlyph className="h-8 w-8" onBrand={onBrand} />
      <span className={cn('text-title-lg font-bold tracking-tight', onBrand ? 'text-on-brand' : 'text-emphasis', compact && 'sr-only')}>Fundly</span>
    </span>
  );
}
