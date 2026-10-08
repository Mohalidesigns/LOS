// Fundly LOS frontend — ESLint flat config.
// typescript-eslint strict (type-aware) + react-hooks + jsx-a11y, plus the
// project guard rails: no hex colours outside design-tokens, no raw fetch/axios
// outside src/api, no browser storage, no dangerouslySetInnerHTML.
import js from '@eslint/js';
import globals from 'globals';
import tseslint from 'typescript-eslint';
import reactHooks from 'eslint-plugin-react-hooks';
import jsxA11y from 'eslint-plugin-jsx-a11y';

const HEX = '/#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\\b/';

const hexRules = [
  {
    selector: `Literal[value=${HEX}]`,
    message: 'Hex colour literals are banned outside src/design-tokens. Use a semantic token class (see tailwind-preset.js).',
  },
  {
    selector: `TemplateElement[value.raw=${HEX}]`,
    message: 'Hex colour literals are banned outside src/design-tokens. Use a semantic token class.',
  },
  {
    selector: "JSXAttribute[name.name='dangerouslySetInnerHTML']",
    message: 'dangerouslySetInnerHTML is banned (XSS / CSP).',
  },
];

const storageGlobals = [
  { name: 'localStorage', message: 'Never persist tokens or PII in browser storage (TRD security).' },
  { name: 'sessionStorage', message: 'Never persist tokens or PII in browser storage (TRD security).' },
  { name: 'indexedDB', message: 'Never persist tokens or PII in browser storage (TRD security).' },
];

const storageProps = ['localStorage', 'sessionStorage', 'indexedDB'].map((property) => ({
  object: 'window',
  property,
  message: 'Never persist tokens or PII in browser storage.',
}));

export default tseslint.config(
  { ignores: ['dist', 'coverage', 'node_modules', 'src/api/schema.d.ts', 'scripts/**'] },
  {
    files: ['**/*.{ts,tsx}'],
    extends: [js.configs.recommended, ...tseslint.configs.strictTypeChecked, ...tseslint.configs.stylisticTypeChecked],
    languageOptions: {
      ecmaVersion: 2023,
      globals: globals.browser,
      parserOptions: {
        projectService: { allowDefaultProject: ['*.js'] },
        tsconfigRootDir: import.meta.dirname,
      },
    },
    plugins: { 'react-hooks': reactHooks, 'jsx-a11y': jsxA11y },
    rules: {
      ...reactHooks.configs.recommended.rules,
      ...jsxA11y.flatConfigs.strict.rules,
      '@typescript-eslint/restrict-template-expressions': ['error', { allowNumber: true }],
      '@typescript-eslint/no-confusing-void-expression': ['error', { ignoreArrowShorthand: true }],
      '@typescript-eslint/no-misused-promises': ['error', { checksVoidReturn: { attributes: false } }],
      '@typescript-eslint/consistent-type-definitions': 'off',
      'no-restricted-syntax': ['error', ...hexRules],
      'no-restricted-globals': [
        'error',
        { name: 'fetch', message: 'Use the generated API client (src/api/client.ts). Raw fetch is only allowed in src/api.' },
        { name: 'XMLHttpRequest', message: 'Use the generated API client (src/api/client.ts).' },
        ...storageGlobals,
      ],
      'no-restricted-properties': [
        'error',
        { object: 'window', property: 'fetch', message: 'Use the generated API client (src/api/client.ts).' },
        { object: 'globalThis', property: 'fetch', message: 'Use the generated API client (src/api/client.ts).' },
        ...storageProps,
      ],
      'no-restricted-imports': [
        'error',
        {
          paths: [{ name: 'axios', message: 'Use the generated API client (src/api/client.ts).' }],
          patterns: [{ group: ['axios/*'], message: 'Use the generated API client.' }],
        },
      ],
    },
  },
  // src/api owns the network boundary: raw fetch is allowed there only.
  {
    files: ['src/api/**/*.{ts,tsx}'],
    rules: {
      'no-restricted-globals': ['error', ...storageGlobals],
      'no-restricted-properties': ['error', ...storageProps],
    },
  },
  // Tests may stub fetch.
  {
    files: ['src/**/*.test.{ts,tsx}', 'src/test/**'],
    rules: {
      'no-restricted-globals': ['error', ...storageGlobals],
      'no-restricted-properties': 'off',
      '@typescript-eslint/no-non-null-assertion': 'off',
      '@typescript-eslint/unbound-method': 'off',
    },
  },
  // design-tokens may contain colour values.
  {
    files: ['src/design-tokens/**'],
    rules: { 'no-restricted-syntax': 'off' },
  },
  {
    files: ['*.config.{js,ts}', 'src/design-tokens/*.js'],
    languageOptions: { globals: globals.node },
    extends: [tseslint.configs.disableTypeChecked],
  },
);
