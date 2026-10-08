import { useEffect, useId, useRef, type ReactNode } from 'react';
import { X } from 'lucide-react';
import { cn } from '@/lib/cn';
import { IconButton } from './IconButton';

export type ModalProps = {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: ReactNode;
  children?: ReactNode;
  footer?: ReactNode;
  /** Prevent closing with Escape / backdrop (e.g. while submitting). */
  dismissible?: boolean;
  role?: 'dialog' | 'alertdialog';
  className?: string;
};

/**
 * Native <dialog> + showModal(): the browser provides the focus trap, inert
 * background, top layer and Escape. Focus returns to the opener on close.
 */
export function Modal({ open, onClose, title, description, children, footer, dismissible = true, role = 'dialog', className }: ModalProps) {
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const descId = useId();
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

  return (
    // Backdrop click is a pointer convenience; keyboard users close with Escape (native <dialog>).
    // eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-noninteractive-element-interactions
    <dialog
      ref={ref}
      role={role === 'alertdialog' ? 'alertdialog' : undefined}
      aria-labelledby={titleId}
      aria-describedby={description ? descId : undefined}
      onCancel={(e) => {
        e.preventDefault();
        if (dismissible) onClose();
      }}
      onClick={(e) => {
        if (dismissible && e.target === e.currentTarget) onClose();
      }}
      className={cn(
        'm-auto w-[calc(100%-2rem)] max-w-modal rounded-overlay border-0 bg-surface p-0 text-primary shadow-modal backdrop:bg-overlay',
        className,
      )}
    >
      {open && (
        <div className="flex flex-col">
          <div className="flex items-start justify-between gap-4 px-6 pb-2 pt-6">
            <div>
              <h2 id={titleId} className="text-title-lg text-emphasis">
                {title}
              </h2>
              {description && (
                <div id={descId} className="mt-1 text-body text-secondary">
                  {description}
                </div>
              )}
            </div>
            {dismissible && <IconButton label="Close dialog" size="sm" icon={<X className="h-icon w-icon" />} onClick={onClose} />}
          </div>
          {children && <div className="px-6 py-3">{children}</div>}
          {footer && <div className="flex flex-wrap justify-end gap-2 px-6 pb-6 pt-3">{footer}</div>}
        </div>
      )}
    </dialog>
  );
}
