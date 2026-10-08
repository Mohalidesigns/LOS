import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router';
import { QueryClientProvider } from '@tanstack/react-query';
import '@fontsource-variable/plus-jakarta-sans';
import '@fontsource/roboto-mono/400.css';
import '@fontsource/roboto-mono/500.css';
import './index.css';
import { ToastProvider } from '@/components/Toast';
import { queryClient } from '@/app/queryClient';
import { router } from '@/app/router';
import { installSessionBridge } from '@/app/sessionBridge';

installSessionBridge();

const root = document.getElementById('root');
if (!root) throw new Error('#root missing');

createRoot(root).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <ToastProvider>
        <RouterProvider router={router} />
      </ToastProvider>
    </QueryClientProvider>
  </StrictMode>,
);
