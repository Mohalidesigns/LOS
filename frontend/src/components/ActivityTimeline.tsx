import type { ReactNode } from 'react';
import { formatDateTime, lagosDayKey } from '@/lib/format';
import { Avatar } from './Avatar';
import { DateTimeText } from './DateTimeText';

export type ActivityItem = { id: string; actor: string; action: ReactNode; at: string };

/** Recent activity grouped by Lagos calendar day ("Today", "Yesterday", date). */
export function ActivityTimeline({ items, now = new Date() }: { items: readonly ActivityItem[]; now?: Date }) {
  const groups = new Map<string, ActivityItem[]>();
  for (const item of items) {
    const key = lagosDayKey(item.at);
    groups.set(key, [...(groups.get(key) ?? []), item]);
  }
  return (
    <div className="space-y-4">
      {[...groups.entries()].map(([key, list]) => {
        const heading = formatDateTime(list[0]?.at ?? key, 'relative-day', now);
        return (
          <section key={key} aria-label={heading}>
            <h3 className="mb-2 text-body font-semibold text-primary">{heading}</h3>
            <ol className="relative">
              {list.map((item, i) => (
                <li key={item.id} className="relative flex gap-3 pb-4 last:pb-0">
                  {i < list.length - 1 && <span aria-hidden="true" className="absolute bottom-0 left-5 top-10 w-px bg-neutral" />}
                  <Avatar name={item.actor} />
                  <div className="min-w-0 pt-0.5">
                    <p className="text-body-sm text-secondary">
                      <span className="font-semibold text-primary">{item.actor}</span> {item.action}
                    </p>
                    <DateTimeText value={item.at} style="time" className="text-meta text-tertiary" />
                  </div>
                </li>
              ))}
            </ol>
          </section>
        );
      })}
    </div>
  );
}
