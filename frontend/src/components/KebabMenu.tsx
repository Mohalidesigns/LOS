import { useEffect, useId, useRef, useState } from 'react';
import { MoreVertical } from 'lucide-react';
import { IconButton } from './IconButton';

export type MenuItem = { label: string; onSelect: () => void; disabled?: boolean };

/** Menu button (WAI-ARIA APG pattern): Enter/Space/ArrowDown opens, arrows move, Escape closes. */
export function KebabMenu({ label, items }: { label: string; items: readonly MenuItem[] }) {
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(0);
  const menuId = useId();
  const buttonRef = useRef<HTMLButtonElement>(null);
  const itemRefs = useRef<(HTMLButtonElement | null)[]>([]);
  const rootRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (open) itemRefs.current[active]?.focus();
  }, [open, active]);

  useEffect(() => {
    if (!open) return;
    const onDown = (e: MouseEvent) => {
      if (rootRef.current && !rootRef.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', onDown);
    return () => document.removeEventListener('mousedown', onDown);
  }, [open]);

  const close = () => {
    setOpen(false);
    buttonRef.current?.focus();
  };

  return (
    <div ref={rootRef} className="relative">
      <IconButton
        ref={buttonRef}
        label={label}
        icon={<MoreVertical className="h-icon w-icon" />}
        size="sm"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        onClick={() => {
          setActive(0);
          setOpen((o) => !o);
        }}
        onKeyDown={(e) => {
          if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive(0);
            setOpen(true);
          }
        }}
      />
      {open && (
        <div
          id={menuId}
          role="menu"
          aria-label={label}
          tabIndex={-1}
          className="absolute right-0 top-full z-drawer mt-1 min-w-[180px] rounded-control border bg-surface p-1 shadow-popover"
          onKeyDown={(e) => {
            if (e.key === 'Escape') {
              e.preventDefault();
              close();
            } else if (e.key === 'ArrowDown') {
              e.preventDefault();
              setActive((i) => (i + 1) % items.length);
            } else if (e.key === 'ArrowUp') {
              e.preventDefault();
              setActive((i) => (i - 1 + items.length) % items.length);
            } else if (e.key === 'Tab') {
              setOpen(false);
            }
          }}
        >
          {items.map((item, i) => (
            <button
              key={item.label}
              ref={(el) => {
                itemRefs.current[i] = el;
              }}
              type="button"
              role="menuitem"
              tabIndex={i === active ? 0 : -1}
              disabled={item.disabled}
              className="flex min-h-target w-full items-center rounded-mark px-3 py-2 text-left text-body-sm text-primary hover:bg-hover-nav disabled:text-disabled"
              onClick={() => {
                item.onSelect();
                close();
              }}
            >
              {item.label}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
