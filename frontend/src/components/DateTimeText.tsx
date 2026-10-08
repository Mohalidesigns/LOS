import { formatDateTime, toDate, type DateTimeStyle } from '@/lib/format';

/** A <time> rendered in Africa/Lagos with a machine-readable ISO datetime. */
export function DateTimeText({ value, style = 'datetime', className }: { value: string | Date; style?: DateTimeStyle; className?: string }) {
  const d = toDate(value);
  const iso = Number.isNaN(d.getTime()) ? undefined : d.toISOString();
  return (
    <time dateTime={iso} className={className}>
      {formatDateTime(d, style)}
    </time>
  );
}
