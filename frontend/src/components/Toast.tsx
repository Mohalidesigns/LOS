import { createContext, useCallback, useContext, useMemo, useRef, useState, type ReactNode } from 'react';
import { CheckCircle2, Info, XCircle, X } from 'lucide-react';
import { cn } from '@/lib/cn';

export type ToastTone = 'success' | 'info' | 'danger';
type ToastItem = { id: number; tone: ToastTone; message: string };
type ToastApi = { show: (message: string, tone?: ToastTone) => void };

const ToastContext = createContext<ToastApi | null>(null);

const ICON = { success: CheckCircle2, info: Info, danger: XCircle } as const;
const ICON_CLS = { success: 'text-success', info: 'text-info', danger: 'text-danger' } as const;

/** Toasts render inside a persistent polite live region (always in the DOM so it is announced). */
export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<ToastItem[]>([]);
  const nextId = useRef(1);

  const dismiss = useCallback((id: number) => setItems((list) => list.filter((t) => t.id !== id)), []);

  const show = useCallback(
    (message: string, tone: ToastTone = 'info') => {
      const id = nextId.current++;
      setItems((list) => [...list.slice(-2), { id, tone, message }]);
      window.setTimeout(() => dismiss(id), 6000);
    },
    [dismiss],
  );

  const api = useMemo(() => ({ show }), [show]);

  return (
    <ToastContext.Provider value={api}>
      {children}
      <div aria-live="polite" aria-atomic="false" role="status" className="pointer-events-none fixed bottom-4 right-4 z-toast flex w-[min(380px,calc(100vw-2rem))] flex-col gap-2">
        {items.map((t) => {
          const Icon = ICON[t.tone];
          return (
            <div key={t.id} className="pointer-events-auto flex items-start gap-3 rounded-card border bg-surface p-4 shadow-popover">
              <Icon aria-hidden="true" className={cn('mt-0.5 h-icon w-icon shrink-0', ICON_CLS[t.tone])} />
              <p className="flex-1 text-body-sm text-primary">{t.message}</p>
              <button type="button" onClick={() => dismiss(t.id)} aria-label="Dismiss notification" className="inline-flex min-h-target min-w-target items-center justify-center rounded-full text-secondary hover:bg-hover-nav">
                <X aria-hidden="true" className="h-icon-sm w-icon-sm" />
              </button>
            </div>
          );
        })}
      </div>
    </ToastContext.Provider>
  );
}

export function useToast(): ToastApi {
  const ctx = useContext(ToastContext);
  if (!ctx) throw new Error('useToast must be used inside <ToastProvider>');
  return ctx;
}
