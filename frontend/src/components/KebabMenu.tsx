import { useEffect, useId, useRef, useState } from 'react';
import { ChevronDown, MoreVertical } from 'lucide-react';
import { IconButton } from './IconButton';

export type MenuItem = {
  label: string;
  onSelect: () => void;
  disabled?: boolean;
  /** Shown under the label; the item stays focusable but inert (aria-disabled). */
  disabledReason?: string | null;
  danger?: boolean;
};

/** Menu button (WAI-ARIA APG pattern): Enter/Space/ArrowDown opens, arrows move, Escape closes. */
export function KebabMenu({ label, items, buttonText }: { label: string; items: readonly MenuItem[]; /** Text button ("Actions ▾") instead of the kebab icon. */ buttonText?: string }) {
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
      {buttonText ? (
        <button
          ref={buttonRef}
          type="button"
          aria-haspopup="menu"
          aria-expanded={open}
          aria-controls={open ? menuId : undefined}
          aria-label={label === buttonText ? undefined : label}
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
          className="inline-flex h-control min-h-target items-center gap-1.5 rounded-pill border border-strong bg-surface px-4 text-body font-semibold text-primary shadow-button hover:bg-hover"
        >
          {buttonText}
          <ChevronDown aria-hidden="true" className="h-icon-sm w-icon-sm" />
        </button>
      ) : (
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
      )}
      {open && (
        <div
          id={menuId}
          role="menu"
          aria-label={label}
          tabIndex={-1}
          className="absolute right-0 top-full z-drawer mt-1 min-w-[220px] max-w-[min(320px,90vw)] rounded-control border bg-surface p-1 shadow-popover"
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
              aria-disabled={item.disabledReason ? true : undefined}
              className={`flex min-h-target w-full flex-col items-start rounded-mark px-3 py-2 text-left text-body-sm hover:bg-hover-nav disabled:text-disabled aria-disabled:cursor-not-allowed aria-disabled:text-disabled ${item.danger ? 'text-danger' : 'text-primary'}`}
              onClick={() => {
                if (item.disabledReason) return;
                item.onSelect();
                close();
              }}
            >
              <span>{item.label}</span>
              {item.disabledReason && <span className="text-meta text-tertiary">{item.disabledReason}</span>}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
