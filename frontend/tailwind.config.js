import fundlyPreset from './src/design-tokens/tailwind-preset.js';

/** @type {import('tailwindcss').Config} */
export default {
  presets: [fundlyPreset],
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  plugins: [],
};
