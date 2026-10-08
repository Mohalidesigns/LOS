import { formatMoney, HOME_CURRENCY, type MoneyValue } from '@/lib/format';
import { cn } from '@/lib/cn';

export type MoneyTextProps = {
  /** Decimal string (scale 4 from the API) or a number (display-only figures). */
  amount: MoneyValue['amount'] | number;
  currency?: string;
  /** "₦25.0M". The full amount stays available as a title + accessible label. */
  compact?: boolean;
  className?: string;
};

/** Money in en-NG: ₦ for NGN, ISO code for every other currency. Tabular figures. */
export function MoneyText({ amount, currency = HOME_CURRENCY, compact = false, className }: MoneyTextProps) {
  const full = formatMoney(amount, currency);
  if (!compact) return <span className={cn('tabular whitespace-nowrap', className)}>{full}</span>;
  const short = formatMoney(amount, currency, { compact: true });
  return (
    <span className={cn('tabular whitespace-nowrap', className)} title={full}>
      <span aria-hidden="true">{short}</span>
      <span className="sr-only">{full}</span>
    </span>
  );
}
