/**
 * Fundly LOS — Tailwind theme mapping for design-tokens.css (D-027, G-46).
 *
 * Every value points at a CSS custom property, so design-tokens.css stays the
 * single source of truth and contrast-check.py stays authoritative.
 *
 * The colour, spacing, radius, shadow, font and z-index scales REPLACE
 * Tailwind's defaults (they sit in `theme`, not `theme.extend`). Tailwind's
 * stock palette (blue-500, gray-400 …) is therefore unavailable on purpose: an
 * ad-hoc colour fails the build instead of shipping.
 *
 * Use
 *   Tailwind v3:  tailwind.config.js → `presets: [fundlyPreset]`
 *   Tailwind v4:  in the main CSS → `@import "./design-tokens.css"; @config "./design-tokens.tailwind.js";`
 *   Both: import design-tokens.css once, before Tailwind's layers.
 *
 * Opacity modifiers (`bg-accent/50`) do not work on var()-based colours and are
 * not allowed: tints are tokens (`bg-accent-subtle`, `bg-hover`, …).
 *
 * Class cheat-sheet
 *   text:    text-primary · text-secondary · text-tertiary · text-link · text-on-accent
 *            text-success · text-warning · text-danger · text-danger-strong · text-info · text-neutral
 *   fills:   bg-surface · bg-subtle · bg-muted · bg-neutral · bg-hover · bg-hover-nav · bg-selected
 *            bg-accent · bg-accent-hover · bg-accent-subtle · bg-success · bg-warning · bg-danger · bg-info
 *            bg-danger-fill · bg-danger-wash · bg-success-wash · bg-overlay · bg-inverse
 *   borders: border (hairline) · border-subtle · border-strong · border-control · border-accent
 *            border-success · border-warning · border-danger · border-info
 *   type:    text-micro · text-meta · text-table · text-body-sm · text-nav · text-body
 *            text-title · text-title-lg · text-heading · text-metric · font-sans · font-mono
 *   shape:   rounded-chip · rounded-mark · rounded (control) · rounded-card · rounded-overlay
 *   depth:   shadow-button · shadow-seg · shadow-document · shadow-popover · shadow-drawer · shadow-modal
 *   focus:   outline-focus · ring-focus  (or rely on the global :focus-visible rule)
 */

const v = (name) => `var(--${name})`;

const fontSize = (size) => [v(`text-${size}`), { lineHeight: v(`leading-${size}`) }];

/** @type {import('tailwindcss').Config} */
const fundlyPreset = {
  theme: {
    // Breakpoints (NFR-015). Mobile-first min-widths.
    //   base  <640   mobile (portal, approvals, inbox)
    //   sm    ≥640   large phone / portal two-column
    //   md    ≥768   tablet: icon rail, single-column case view with tabs
    //   lg    ≥1024  laptop: full sidebar, case grid without right rail (drawer)
    //   xl    ≥1280  desktop: full 3-column case grid
    //   2xl   ≥1440  v3 design width
    screens: {
      sm: '640px',
      md: '768px',
      lg: '1024px',
      xl: '1280px',
      '2xl': '1440px',
    },

    colors: {
      transparent: 'transparent',
      current: 'currentColor',
      inherit: 'inherit',
      white: v('p-white'),
      accent: {
        DEFAULT: v('color-accent'),
        hover: v('color-accent-hover'),
        pressed: v('color-accent-pressed'),
        subtle: v('color-accent-subtle'),
        graphic: v('color-accent-graphic'),
        border: v('color-accent-border'),
      },
      score: {
        1: v('color-score-1'),
        2: v('color-score-2'),
        3: v('color-score-3'),
        4: v('color-score-4'),
        5: v('color-score-5'),
      },
      chart: {
        1: v('color-chart-1'),
        2: v('color-chart-2'),
        3: v('color-chart-3'),
        4: v('color-chart-4'),
        5: v('color-chart-5'),
      },
      indicator: v('color-indicator'),
      decorative: v('color-decorative'),
    },

    textColor: {
      transparent: 'transparent',
      current: 'currentColor',
      inherit: 'inherit',
      primary: v('color-text-primary'),
      secondary: v('color-text-secondary'),
      tertiary: v('color-text-tertiary'),
      placeholder: v('color-text-placeholder'),
      disabled: v('color-text-disabled'),
      inverse: v('color-text-inverse'),
      link: { DEFAULT: v('color-text-link'), hover: v('color-text-link-hover') },
      'on-accent': v('color-text-on-accent'),
      'on-danger': v('color-text-on-danger'),
      accent: v('color-accent'),
      success: v('color-success-fg'),
      warning: v('color-warning-fg'),
      danger: { DEFAULT: v('color-danger-fg'), strong: v('color-danger-text-strong') },
      info: v('color-info-fg'),
      neutral: v('color-neutral-fg'),
      indicator: v('color-indicator'), // icons only
    },

    backgroundColor: {
      transparent: 'transparent',
      current: 'currentColor',
      surface: v('color-bg'),
      subtle: v('color-bg-subtle'),
      muted: v('color-bg-muted'),
      neutral: v('color-bg-neutral'),
      hover: v('color-bg-hover'),
      'hover-nav': v('color-bg-hover-nav'),
      selected: v('color-bg-selected'),
      pressed: v('color-bg-pressed'),
      inverse: v('color-bg-inverse'),
      overlay: v('color-bg-overlay'),
      accent: {
        DEFAULT: v('color-accent'),
        hover: v('color-accent-hover'),
        pressed: v('color-accent-pressed'),
        subtle: v('color-accent-subtle'),
        graphic: v('color-accent-graphic'),
      },
      region: v('color-region-fill'),
      success: v('color-success-bg'),
      'success-wash': v('color-bg-success-wash'),
      warning: v('color-warning-bg'),
      danger: v('color-danger-bg'),
      'danger-wash': v('color-bg-danger-wash'),
      'danger-fill': { DEFAULT: v('color-danger-fill'), hover: v('color-danger-fill-hover') },
      info: v('color-info-bg'),
      score: {
        1: v('color-score-1'),
        2: v('color-score-2'),
        3: v('color-score-3'),
        4: v('color-score-4'),
        5: v('color-score-5'),
      },
      indicator: v('color-indicator'),
    },

    borderColor: {
      transparent: 'transparent',
      current: 'currentColor',
      DEFAULT: v('color-border'),
      subtle: v('color-border-subtle'),
      strong: v('color-border-strong'),
      control: { DEFAULT: v('color-border-control'), hover: v('color-border-control-hover') },
      accent: { DEFAULT: v('color-accent'), graphic: v('color-accent-graphic'), border: v('color-accent-border') },
      focus: v('focus-ring-color'),
      success: v('color-success-border'),
      warning: v('color-warning-border'),
      danger: { DEFAULT: v('color-danger-border'), soft: v('color-border-danger') },
      info: v('color-info-border'),
      indicator: v('color-indicator'),
      decorative: v('color-decorative'),
    },

    outlineColor: { focus: v('focus-ring-color'), transparent: 'transparent' },
    ringColor: { focus: v('focus-ring-color'), transparent: 'transparent' },
    ringOffsetColor: { surface: v('color-bg'), subtle: v('color-bg-subtle') },
    outlineWidth: { 0: '0px', DEFAULT: v('focus-ring-width'), 2: '2px' },
    outlineOffset: { 0: '0px', DEFAULT: v('focus-ring-offset'), 2: '2px', '-2': '-2px' },
    divideColor: { DEFAULT: v('color-border'), subtle: v('color-border-subtle') },
    placeholderColor: { DEFAULT: v('color-text-placeholder') },
    caretColor: { accent: v('color-accent') },
    accentColor: { accent: v('color-accent') },
    fill: { current: 'currentColor', none: 'none' },
    stroke: { current: 'currentColor', none: 'none' },

    fontFamily: {
      sans: v('font-sans'),
      mono: v('font-mono'),
    },
    fontWeight: {
      normal: v('font-weight-regular'),
      control: v('font-weight-control'),
      medium: v('font-weight-medium'),
      semibold: v('font-weight-semibold'),
    },
    fontSize: {
      micro: fontSize('micro'),
      meta: fontSize('meta'),
      table: fontSize('table'),
      'body-sm': fontSize('body-sm'),
      nav: fontSize('nav'),
      body: fontSize('body'),
      title: [v('text-title'), { lineHeight: v('leading-title'), letterSpacing: v('tracking-tight'), fontWeight: '600' }],
      'title-lg': [v('text-title-lg'), { lineHeight: v('leading-title-lg'), letterSpacing: v('tracking-tight'), fontWeight: '600' }],
      heading: [v('text-heading'), { lineHeight: v('leading-heading'), letterSpacing: v('tracking-tighter'), fontWeight: '600' }],
      metric: [v('text-metric'), { lineHeight: v('leading-metric'), letterSpacing: v('tracking-metric'), fontWeight: '600' }],
    },
    letterSpacing: {
      normal: '0',
      tight: v('tracking-tight'),
      tighter: v('tracking-tighter'),
      metric: v('tracking-metric'),
      eyebrow: v('tracking-eyebrow'),
      ref: v('tracking-ref'),
    },

    spacing: {
      0: v('space-0'),
      px: v('space-px'),
      0.5: v('space-0_5'),
      1: v('space-1'),
      1.5: v('space-1_5'),
      2: v('space-2'),
      2.5: v('space-2_5'),
      3: v('space-3'),
      3.5: v('space-3_5'),
      4: v('space-4'),
      5: v('space-5'),
      6: v('space-6'),
      8: v('space-8'),
      10: v('space-10'),
      12: v('space-12'),
      16: v('space-16'),
      // component and layout tokens
      'card-x': v('card-padding-x'),
      'card-y': v('card-padding-y'),
      'cell-x': v('table-cell-px'),
      'cell-y': v('table-cell-py'),
      gutter: v('page-gutter'),
      target: v('target-min'),
      touch: v('target-touch'),
      'control-sm': v('control-h-sm'),
      control: v('control-h'),
      'control-lg': v('control-h-lg'),
      row: v('row-min-h'),
      'icon-sm': v('icon-sm'),
      icon: v('icon'),
      'icon-lg': v('icon-lg'),
      dot: v('status-dot'),
      sidebar: v('layout-sidebar-w'),
      rail: v('layout-rail-w'),
      header: v('layout-header-h'),
      context: v('layout-context-h'),
      tabs: v('layout-tabs-h'),
      stage: v('layout-stage-w'),
      props: v('layout-props-w'),
      drawer: v('layout-drawer-w'),
      peek: v('layout-peek-w'),
    },
    maxWidth: {
      none: 'none',
      full: '100%',
      form: v('layout-form-max'),
      portal: v('layout-portal-max'),
      peek: v('layout-peek-w'),
      prose: '68ch',
    },
    gridTemplateColumns: {
      // v3 case grid and its responsive reductions
      case: `${v('layout-stage-w')} minmax(0, 1fr) ${v('layout-props-w')}`, // xl+
      'case-laptop': `${v('layout-stage-w')} minmax(0, 1fr)`,                 // lg: props rail → drawer
      'case-tablet': 'minmax(0, 1fr)',                                         // md: tracker → horizontal stepper
      shell: `${v('layout-sidebar-w')} minmax(0, 1fr)`,
      'shell-rail': `${v('layout-rail-w')} minmax(0, 1fr)`,
      peek: 'minmax(0, 1fr) minmax(360px, 420px)',                             // document pane | field list
      kv: 'minmax(120px, 40%) minmax(0, 1fr)',                                 // property panel rows
      1: 'repeat(1, minmax(0, 1fr))',
      2: 'repeat(2, minmax(0, 1fr))',
      3: 'repeat(3, minmax(0, 1fr))',
      4: 'repeat(4, minmax(0, 1fr))',
    },

    borderRadius: {
      none: v('radius-none'),
      chip: v('radius-chip'),
      mark: v('radius-mark'),
      DEFAULT: v('radius-control'),
      control: v('radius-control'),
      card: v('radius-card'),
      overlay: v('radius-overlay'),
      full: v('radius-full'),
    },
    borderWidth: { 0: '0px', DEFAULT: '1px', 2: '2px' },

    boxShadow: {
      none: v('shadow-none'),
      button: v('shadow-button'),
      seg: v('shadow-seg'),
      document: v('shadow-document'),
      popover: v('shadow-popover'),
      drawer: v('shadow-drawer'),
      modal: v('shadow-modal'),
      focus: v('focus-ring-shadow'),
      'focus-inset': v('focus-ring-inset'),
    },

    transitionDuration: {
      0: v('duration-instant'),
      fast: v('duration-fast'),
      DEFAULT: v('duration-base'),
      base: v('duration-base'),
      slow: v('duration-slow'),
    },
    transitionTimingFunction: {
      DEFAULT: v('ease-standard'),
      standard: v('ease-standard'),
      enter: v('ease-enter'),
      exit: v('ease-exit'),
    },

    zIndex: {
      auto: 'auto',
      base: v('z-base'),
      sticky: v('z-sticky'),
      header: v('z-header'),
      rail: v('z-rail'),
      drawer: v('z-drawer'),
      modal: v('z-modal'),
      toast: v('z-toast'),
      tooltip: v('z-tooltip'),
    },

    extend: {
      minHeight: { target: v('target-min'), touch: v('target-touch'), control: v('control-h'), row: v('row-min-h') },
      minWidth: { target: v('target-min'), touch: v('target-touch') },
      height: { screen: '100dvh' },
    },
  },
};

export default fundlyPreset;
export { fundlyPreset };
