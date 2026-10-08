/**
 * Locale formatting for Nigeria (en-NG, ₦, Africa/Lagos). Money arrives from
 * the API as a decimal STRING at scale 4 (schema Money) and is never parsed to
 * a float for full-precision display: Intl formats the decimal string exactly.
 */
export const LOCALE = 'en-NG';
export const TIME_ZONE = 'Africa/Lagos';
export const HOME_CURRENCY = 'NGN';

export type MoneyValue = { amount: string; currency: string };

export type MoneyFormatOptions = {
  /** "₦25.0M" style. Uses a float internally: display only, never for arithmetic. */
  compact?: boolean;
  /** Fraction digits for full display (default 2). */
  fractionDigits?: number;
};

type NumericInput = number | bigint | `${number}`;

function toNumericInput(amount: string | number): NumericInput {
  if (typeof amount === 'number') return amount;
  const trimmed = amount.trim();
  if (!/^-?\d+(\.\d+)?$/.test(trimmed)) throw new RangeError(`Invalid decimal amount: ${amount}`);
  return trimmed as `${number}`;
}

export function formatMoney(amount: string | number, currency: string = HOME_CURRENCY, opts: MoneyFormatOptions = {}): string {
  const code = currency.toUpperCase();
  const isHome = code === HOME_CURRENCY;
  const base: Intl.NumberFormatOptions = {
    style: 'currency',
    currency: code,
    // Non-NGN amounts always show the ISO code so ₦ vs $ can never be confused.
    currencyDisplay: isHome ? 'narrowSymbol' : 'code',
  };
  if (opts.compact) {
    const n = typeof amount === 'number' ? amount : Number(toNumericInput(amount));
    const small = Math.abs(n) < 1000;
    return new Intl.NumberFormat(LOCALE, {
      ...base,
      notation: 'compact',
      compactDisplay: 'short',
      minimumFractionDigits: small ? 0 : 1,
      maximumFractionDigits: small ? 0 : 1,
    }).format(n);
  }
  const digits = opts.fractionDigits ?? 2;
  return new Intl.NumberFormat(LOCALE, {
    ...base,
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(toNumericInput(amount) as number);
}

export function formatNumber(n: number, opts: Intl.NumberFormatOptions = {}): string {
  return new Intl.NumberFormat(LOCALE, opts).format(n);
}

export function formatPercent(fraction: number, digits = 1): string {
  return new Intl.NumberFormat(LOCALE, { style: 'percent', minimumFractionDigits: digits, maximumFractionDigits: digits }).format(fraction);
}

export type DateTimeStyle = 'date' | 'datetime' | 'time' | 'relative-day';

const formatters: Record<Exclude<DateTimeStyle, 'relative-day'>, Intl.DateTimeFormat> = {
  date: new Intl.DateTimeFormat(LOCALE, { timeZone: TIME_ZONE, day: '2-digit', month: 'short', year: 'numeric' }),
  datetime: new Intl.DateTimeFormat(LOCALE, {
    timeZone: TIME_ZONE,
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }),
  time: new Intl.DateTimeFormat(LOCALE, { timeZone: TIME_ZONE, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }),
};

export function toDate(value: string | Date): Date {
  return value instanceof Date ? value : new Date(value);
}

/** Calendar day key in Africa/Lagos (YYYY-MM-DD). */
export function lagosDayKey(value: string | Date): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: TIME_ZONE, year: 'numeric', month: '2-digit', day: '2-digit' }).format(toDate(value));
}

export function formatDateTime(value: string | Date, style: DateTimeStyle = 'datetime', now: Date = new Date()): string {
  const d = toDate(value);
  if (Number.isNaN(d.getTime())) return '—';
  if (style === 'relative-day') {
    const key = lagosDayKey(d);
    if (key === lagosDayKey(now)) return 'Today';
    if (key === lagosDayKey(new Date(now.getTime() - 86_400_000))) return 'Yesterday';
    return formatters.date.format(d);
  }
  return formatters[style].format(d);
}
