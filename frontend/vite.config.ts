/// <reference types="vitest/config" />
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';

const BACKEND = process.env.FUNDLY_BACKEND_URL ?? 'http://127.0.0.1:8091';

/**
 * Same-origin dev setup: the browser only ever talks to http://localhost:5173.
 * Vite proxies the API to `php artisan serve`, so Sanctum sees a same-origin
 * SPA (Origin/Referer localhost:5173 ∈ SANCTUM_STATEFUL_DOMAINS) and the
 * session + XSRF-TOKEN cookies are set on localhost:5173. `changeOrigin` is
 * false on purpose: Laravel must see Host: localhost:5173 for the stateful
 * check and cookie scoping.
 */
const proxyTarget = { target: BACKEND, changeOrigin: false, secure: false, xfwd: true };

export default defineConfig({
  plugins: [react()],
  resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) } },
  server: {
    port: 5173,
    strictPort: true,
    host: 'localhost',
    proxy: {
      '/api': proxyTarget,
      // Sanctum's own /sanctum/* routes are disabled server-side (the SPA uses
      // /api/v1/auth/csrf-cookie); proxied anyway so nothing 404s at the Vite layer.
      '/sanctum': proxyTarget,
      '/health': proxyTarget,
      '/ready': proxyTarget,
    },
  },
  preview: { port: 4173, strictPort: true },
  build: {
    target: 'es2022',
    sourcemap: true,
    // No inline assets: CSP default-src 'self' (fonts are emitted as files).
    assetsInlineLimit: 0,
    rolldownOptions: {
      output: {
        // Long-lived vendor chunks; forms/zod only load with routes that render a form.
        codeSplitting: {
          groups: [
            { name: 'vendor-react', test: /node_modules[\\/](react|react-dom|scheduler|react-router)[\\/]/, priority: 20 },
            { name: 'vendor-query', test: /node_modules[\\/]@tanstack[\\/]/, priority: 15 },
            { name: 'vendor-forms', test: /node_modules[\\/](react-hook-form|zod|@hookform)[\\/]/, priority: 10 },
          ],
        },
      },
    },
  },
  test: {
    environment: 'jsdom',
    globals: false,
    setupFiles: ['./src/test/setup.ts'],
    css: false,
    include: ['src/**/*.test.{ts,tsx}'],
  },
});
