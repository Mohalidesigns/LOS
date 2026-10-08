import { useEffect, useId, useRef, type ReactNode } from 'react';
import { X } from 'lucide-react';
import { cn } from '@/lib/cn';
import { IconButton } from './IconButton';

export type DrawerProps = {
  open: boolean;
  onClose: () => void;
  title: string;
  subtitle?: ReactNode;
  children?: ReactNode;
  footer?: ReactNode;
  className?: string;
};

/**
 * Right-hand side panel on a native modal <dialog>: focus trap, inert page,
 * Escape to close, focus returns to the opener. Full width below 768 px.
 */
export function Drawer({ open, onClose, title, subtitle, children, footer, className }: DrawerProps) {
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const opener = useRef<Element | null>(null);

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (open && !dialog.open) {
      opener.current = document.activeElement;
      if (typeof dialog.showModal === 'function') dialog.showModal();
      else dialog.setAttribute('open', '');
    } else if (!open && dialog.open) {
      if (typeof dialog.close === 'function') dialog.close();
      else dialog.removeAttribute('open');
      if (opener.current instanceof HTMLElement) opener.current.focus();
    }
  }, [open]);

  // Unmounted while open (conditionally rendered dialogs): still return focus to the opener.
  useEffect(
    () => () => {
      if (opener.current instanceof HTMLElement && document.contains(opener.current)) opener.current.focus();
    },
    [],
  );

  return (
    // Backdrop click is a pointer convenience; keyboard users close with Escape.
    // eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-noninteractive-element-interactions
    <dialog
      ref={ref}
      aria-labelledby={titleId}
      onCancel={(e) => {
        e.preventDefault();
        onClose();
      }}
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
      className={cn(
        'fixed inset-y-0 left-auto right-0 !m-0 h-full max-h-full w-full max-w-full border-0 bg-surface p-0 text-primary shadow-drawer backdrop:bg-overlay md:w-panel md:rounded-l-overlay',
        className,
      )}
    >
      {open && (
        <div className="flex h-full flex-col">
          <div className="flex items-start justify-between gap-4 border-b px-6 py-5">
            <div className="min-w-0">
              <h2 id={titleId} className="text-title-lg text-emphasis">
                {title}
              </h2>
              {subtitle && <div className="mt-1 text-body-sm text-tertiary">{subtitle}</div>}
            </div>
            <IconButton label="Close panel" size="sm" icon={<X className="h-icon w-icon" />} onClick={onClose} />
          </div>
          <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5">{children}</div>
          {footer && <div className="flex flex-wrap justify-end gap-2 border-t px-6 py-4">{footer}</div>}
        </div>
      )}
    </dialog>
  );
}
