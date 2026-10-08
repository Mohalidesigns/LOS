#!/usr/bin/env python3
"""
Fundly LOS — WCAG 2.2 contrast check for design tokens (G-46, NFR-014).

Parses design-tokens.css (the single source of truth), resolves var() chains,
composites translucent colours over their real backdrop, and computes the WCAG
relative-luminance contrast ratio for every token pair the UI is allowed to use.

Thresholds (WCAG 2.2 AA):
  text   >= 4.5:1  (1.4.3, normal text)
  large  >= 3.0:1  (1.4.3, >= 24px regular or >= 18.66px bold)
  ui     >= 3.0:1  (1.4.11, component boundaries, state indicators, focus rings, meaningful graphics)
  exempt           (disabled controls, pure decoration): printed for information, not graded

Usage:
  python3 contrast-check.py                      # table of all graded pairs; exit 1 on any failure
  python3 contrast-check.py --legacy             # also print v3 / AuditPro originals vs corrected values
  python3 contrast-check.py --css path/to/design-tokens.css
No dependencies beyond the standard library.
"""
from __future__ import annotations

import argparse
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
THRESHOLD = {"text": 4.5, "large": 3.0, "ui": 3.0}

# --------------------------------------------------------------------------- colour maths
def _channel(c: float) -> float:
    c = c / 255.0
    return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4


def luminance(rgb: tuple[int, int, int]) -> float:
    r, g, b = (_channel(v) for v in rgb)
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def ratio(a: tuple[int, int, int], b: tuple[int, int, int]) -> float:
    la, lb = sorted((luminance(a), luminance(b)), reverse=True)
    return (la + 0.05) / (lb + 0.05)


def parse_colour(value: str) -> tuple[tuple[int, int, int], float]:
    v = value.strip().lower()
    m = re.fullmatch(r"#([0-9a-f]{6})", v)
    if m:
        h = m.group(1)
        return (int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16)), 1.0
    m = re.fullmatch(r"#([0-9a-f]{3})", v)
    if m:
        h = m.group(1)
        return (int(h[0] * 2, 16), int(h[1] * 2, 16), int(h[2] * 2, 16)), 1.0
    m = re.fullmatch(r"rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)", v)
    if m:
        a = float(m.group(4)) if m.group(4) is not None else 1.0
        return (int(m.group(1)), int(m.group(2)), int(m.group(3))), a
    raise ValueError(f"not a colour: {value!r}")


def over(fg: tuple[tuple[int, int, int], float], bg: tuple[int, int, int]) -> tuple[int, int, int]:
    (r, g, b), a = fg
    return tuple(round(c * a + d * (1 - a)) for c, d in zip((r, g, b), bg))  # type: ignore[return-value]


def hexs(rgb: tuple[int, int, int]) -> str:
    return "#%02x%02x%02x" % rgb


# --------------------------------------------------------------------------- token parsing
def load_tokens(css_path: str) -> dict[str, str]:
    css = open(css_path, encoding="utf-8").read()
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    root = re.search(r":root\s*\{(.*?)\n\}", css, flags=re.S)
    if not root:
        sys.exit(f"no :root block in {css_path}")
    return {k: v.strip() for k, v in re.findall(r"(--[\w-]+)\s*:\s*([^;]+);", root.group(1))}


class Resolver:
    def __init__(self, tokens: dict[str, str]):
        self.t = tokens

    def raw(self, name: str) -> str:
        if not name.startswith("--"):
            return name
        seen = set()
        v = self.t[name]
        while True:
            m = re.fullmatch(r"var\((--[\w-]+)\)", v.strip())
            if not m:
                return v
            if m.group(1) in seen:
                raise ValueError(f"var() cycle at {name}")
            seen.add(m.group(1))
            v = self.t[m.group(1)]

    def solid(self, spec: str) -> tuple[int, int, int]:
        """spec = 'token' or 'token@backdrop' (translucent token composited over backdrop, recursively)."""
        if "@" in spec:
            top, back = spec.split("@", 1)
            return over(parse_colour(self.raw(top)), self.solid(back))
        rgb, a = parse_colour(self.raw(spec))
        if a < 1.0:
            return over((rgb, a), (255, 255, 255))  # translucent with no stated backdrop: page white
        return rgb


# --------------------------------------------------------------------------- the pairs the UI may use
# (foreground, background, kind, usage)
PAIRS: list[tuple[str, str, str, str]] = [
    # text: neutrals on every surface they appear on
    ("--color-text-primary", "--color-bg", "text", "Body, values, titles"),
    ("--color-text-primary", "--color-bg-subtle", "text", "Sidebar items, side-peek"),
    ("--color-text-primary", "--color-bg-muted", "text", "Table header cells, property panel"),
    ("--color-text-primary", "--color-bg-neutral", "text", "Text on neutral fill"),
    ("--color-text-primary", "--color-bg-selected@--color-bg-subtle", "text", "Active nav item label"),
    ("--color-text-primary", "--color-bg-danger-wash@--color-bg", "text", "Breached queue row"),
    ("--color-text-primary", "--color-accent-subtle", "text", "Info banner title"),
    ("--color-text-primary", "--color-warning-bg", "text", "Warning banner title"),
    ("--color-text-primary", "--color-danger-bg", "text", "Danger banner body"),
    ("--color-text-primary", "--color-success-bg", "text", "Success banner body"),
    ("--color-text-secondary", "--color-bg", "text", "Secondary copy, inactive nav"),
    ("--color-text-secondary", "--color-bg-subtle", "text", "Inactive nav item"),
    ("--color-text-secondary", "--color-bg-muted", "text", "Secondary in property panel"),
    ("--color-text-secondary", "--color-bg-neutral", "text", "Neutral chip label"),
    ("--color-text-secondary", "--color-bg-selected@--color-bg-subtle", "text", "Count inside active nav item"),
    ("--color-text-secondary", "--color-bg-hover-nav@--color-bg-subtle", "text", "Hovered nav item"),
    ("--color-text-secondary", "--color-accent-subtle", "text", "Banner body (info)"),
    ("--color-text-secondary", "--color-warning-bg", "text", "Banner body (warning)"),
    ("--color-text-secondary", "--color-danger-bg", "text", "Banner body (danger)"),
    ("--color-text-secondary", "--color-success-bg", "text", "Banner body (success)"),
    ("--color-text-tertiary", "--color-bg", "text", "Meta, eyebrow, table header, inactive tab"),
    ("--color-text-tertiary", "--color-bg-subtle", "text", "Sidebar meta, nav counts"),
    ("--color-text-tertiary", "--color-bg-muted", "text", "Table header on muted fill"),
    ("--color-text-tertiary", "--color-bg-neutral", "text", "Meta on neutral fill"),
    ("--color-text-tertiary", "--color-bg-hover@--color-bg", "text", "Meta in hovered row"),
    ("--color-text-tertiary", "--color-bg-hover-nav@--color-bg-subtle", "text", "Count in hovered nav item"),
    ("--color-text-tertiary", "--color-bg-danger-wash@--color-bg", "text", "Meta in breached row"),
    ("--color-text-tertiary", "--color-bg-success-wash@--color-bg", "text", "Meta in acknowledged panel"),
    ("--color-text-tertiary", "--color-accent-subtle", "text", "Meta in info tile"),
    ("--color-text-placeholder", "--color-bg", "text", "Input placeholder"),
    ("--color-text-placeholder", "--color-bg-muted", "text", "Filter input placeholder"),
    # links and accent
    ("--color-text-link", "--color-bg", "text", "Links, text buttons"),
    ("--color-text-link", "--color-bg-subtle", "text", "Links in sidebar / side-peek"),
    ("--color-text-link", "--color-bg-muted", "text", "Links in property panel"),
    ("--color-text-link", "--color-accent-subtle", "text", "Links in info banner"),
    ("--color-text-link", "--color-bg-hover@--color-bg", "text", "Link in hovered row"),
    ("--color-text-link-hover", "--color-bg", "text", "Link hover"),
    ("--color-text-on-accent", "--color-accent", "text", "Primary button, selected filter chip"),
    ("--color-text-on-accent", "--color-accent-hover", "text", "Primary button hover"),
    ("--color-text-on-accent", "--color-accent-pressed", "text", "Primary button pressed"),
    ("--color-text-on-danger", "--color-danger-fill", "text", "Danger button"),
    ("--color-text-on-danger", "--color-danger-fill-hover", "text", "Danger button hover"),
    ("--color-text-inverse", "--color-bg-inverse", "text", "Tooltip, brand mark"),
    # status tones
    ("--color-success-fg", "--color-success-bg", "text", "Success chip"),
    ("--color-success-fg", "--color-bg", "text", "Success inline text"),
    ("--color-success-fg", "--color-bg-success-wash@--color-bg", "text", "Acknowledged panel text"),
    ("--color-warning-fg", "--color-warning-bg", "text", "Warning chip (was failing)"),
    ("--color-warning-fg", "--color-bg", "text", "Warning inline text, queue flag"),
    ("--color-warning-fg", "--color-bg-muted", "text", "Warning text in property panel"),
    ("--color-danger-fg", "--color-danger-bg", "text", "Danger chip"),
    ("--color-danger-fg", "--color-bg", "text", "Danger inline text, outline danger button"),
    ("--color-danger-fg", "--color-bg-danger-wash@--color-bg", "text", "Danger text in breached row"),
    ("--color-danger-fg", "--color-bg-muted", "text", "Danger text in property panel"),
    ("--color-danger-text-strong", "--color-danger-bg", "text", "Danger callout body"),
    ("--color-danger-text-strong", "--color-bg-danger-wash@--color-bg", "text", "Cross-validation warning body"),
    ("--color-info-fg", "--color-info-bg", "text", "Info / segment tag"),
    ("--color-info-fg", "--color-accent-subtle", "text", "Accent tile value and label"),
    ("--color-info-fg", "--color-bg", "text", "Info inline text"),
    ("--color-neutral-fg", "--color-neutral-bg", "text", "Neutral chip"),
    # large text
    ("--color-text-primary", "--color-bg", "large", "Stat tile value 30px"),
    ("--color-info-fg", "--color-accent-subtle", "large", "Awaiting-my-action tile value"),
    ("--color-danger-fg", "--color-danger-bg", "large", "SLA-breached tile value"),
    # non-text UI components and focus
    ("--color-border-control", "--color-bg", "ui", "Input / checkbox / radio boundary"),
    ("--color-border-control", "--color-bg-subtle", "ui", "Input boundary on subtle"),
    ("--color-border-control", "--color-bg-muted", "ui", "Filter input boundary"),
    ("--color-border-control-hover", "--color-bg", "ui", "Input boundary hover"),
    ("--focus-ring-color", "--color-bg", "ui", "Focus ring on page"),
    ("--focus-ring-color", "--color-bg-subtle", "ui", "Focus ring in sidebar / side-peek"),
    ("--focus-ring-color", "--color-bg-muted", "ui", "Focus ring in property panel"),
    ("--focus-ring-color", "--color-bg-neutral", "ui", "Focus ring on neutral fill"),
    ("--focus-ring-color", "--color-bg-selected@--color-bg-subtle", "ui", "Focus ring on active nav"),
    ("--focus-ring-color", "--color-accent-subtle", "ui", "Focus ring in info banner"),
    ("--focus-ring-color", "--color-bg-danger-wash@--color-bg", "ui", "Focus ring on breached row"),
    ("--focus-ring-color", "--color-warning-bg", "ui", "Focus ring in warning banner"),
    ("--focus-ring-color", "--color-danger-bg", "ui", "Focus ring in danger banner"),
    ("--color-accent", "--color-bg", "ui", "Selected chip / checked box vs page"),
    ("--color-accent-graphic", "--color-bg", "ui", "Stage tracker dot, progress fill, region outline"),
    ("--color-indicator", "--color-bg", "ui", "Pending stage marker, neutral icon"),
    ("--color-indicator", "--color-bg-subtle", "ui", "Neutral icon in sidebar"),
    ("--color-text-on-accent", "--color-accent", "ui", "Checkmark on checked box"),
    ("--color-score-1", "--color-bg", "ui", "Score band 1"),
    ("--color-score-2", "--color-bg", "ui", "Score band 2"),
    ("--color-score-3", "--color-bg", "ui", "Score band 3"),
    ("--color-score-4", "--color-bg", "ui", "Score band 4"),
    ("--color-score-5", "--color-bg", "ui", "Score band 5"),
    ("--color-chart-1", "--color-bg", "ui", "Chart series 1"),
    ("--color-chart-2", "--color-bg", "ui", "Chart series 2"),
    ("--color-chart-3", "--color-bg", "ui", "Chart series 3"),
    ("--color-chart-4", "--color-bg", "ui", "Chart series 4"),
    ("--color-chart-5", "--color-bg", "ui", "Chart series 5"),
    # exempt: information only
    ("--color-text-disabled", "--color-bg", "exempt", "Disabled control label (WCAG 1.4.3 exception)"),
    ("--color-decorative", "--color-bg", "exempt", "Decorative separators only"),
    ("--color-border", "--color-bg", "exempt", "Hairline rule (decorative)"),
    ("--color-border-strong", "--color-bg", "exempt", "Button / card edge (label identifies the control)"),
    ("--color-accent-border", "--color-bg", "exempt", "Tracker connector (decorative)"),
    ("--color-text-tertiary", "--color-bg-selected@--color-bg-subtle", "exempt",
     "PROHIBITED pair: use text-secondary inside the active nav item"),
]

# v3 / AuditPro originals vs the corrected token (fg, bg, kind, label, corrected fg token, corrected bg token)
LEGACY: list[tuple[str, str, str, str, str, str]] = [
    ("#ffffff", "#2383e2", "text", "White on v3 accent (primary button, selected chip)", "--color-text-on-accent", "--color-accent"),
    ("#2383e2", "#ffffff", "text", "v3 accent as link text", "--color-text-link", "--color-bg"),
    ("#2383e2", "#f2f8fd", "text", "v3 accent link on tint", "--color-text-link", "--color-accent-subtle"),
    ("#787774", "#ffffff", "text", "v3 tertiary on white", "--color-text-tertiary", "--color-bg"),
    ("#787774", "#f7f7f5", "text", "v3 tertiary on sidebar", "--color-text-tertiary", "--color-bg-subtle"),
    ("#9b9a97", "#ffffff", "text", "v3 muted (eyebrow, table header, meta) on white", "--color-text-tertiary", "--color-bg"),
    ("#9b9a97", "#fbfbfa", "text", "v3 muted on header fill", "--color-text-tertiary", "--color-bg-muted"),
    ("#b3b1ad", "#f7f7f5", "text", "v3 faint nav count on sidebar", "--color-text-tertiary", "--color-bg-subtle"),
    ("#b3b1ad", "#ffffff", "text", "v3 faint on white", "--color-text-tertiary", "--color-bg"),
    ("#8a6a10", "#fdecc8", "text", "v3 warn text on amber", "--color-warning-fg", "--color-warning-bg"),
    ("rgba(35,131,226,0.2)", "#ffffff", "ui", "v3 focus ring (2px soft ring)", "--focus-ring-color", "--color-bg"),
    ("rgba(55,53,47,0.14)", "#ffffff", "ui", "v3 input border", "--color-border-control", "--color-bg"),
    ("#d3d1cd", "#ffffff", "ui", "v3 pending stage dot border", "--color-indicator", "--color-bg"),
    ("#718096", "#ffffff", "text", "AuditPro secondary text on white", "--color-text-tertiary", "--color-bg"),
    ("#d4af37", "#ffffff", "ui", "AuditPro gold nav indicator", "--color-accent", "--color-bg"),
]


def solid_literal(value: str, backdrop: str) -> tuple[int, int, int]:
    bg_rgb, _ = parse_colour(backdrop)
    rgb, a = parse_colour(value)
    return over((rgb, a), bg_rgb) if a < 1 else rgb


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    ap.add_argument("--css", default=os.path.join(HERE, "design-tokens.css"))
    ap.add_argument("--legacy", action="store_true", help="print originals vs corrected")
    args = ap.parse_args()

    res = Resolver(load_tokens(args.css))
    failures = 0
    print("## Token contrast (WCAG 2.2 AA)\n")
    print("| # | Foreground | Background | Fg hex | Bg hex | Ratio | Need | Result | Usage |")
    print("|--:|---|---|---|---|--:|--:|---|---|")
    for i, (fg, bg, kind, usage) in enumerate(PAIRS, 1):
        f, b = res.solid(fg), res.solid(bg)
        r = ratio(f, b)
        need = THRESHOLD.get(kind)
        if need is None:
            result = "info (exempt)"
        elif r + 1e-9 >= need:
            result = "PASS"
        else:
            result = "**FAIL**"
            failures += 1
        need_s = f"{need:.1f}" if need else "—"
        print(f"| {i} | `{fg}` | `{bg}` | {hexs(f)} | {hexs(b)} | {r:.2f} | {need_s} | {result} | {usage} |")

    graded = sum(1 for p in PAIRS if p[2] in THRESHOLD)
    print(f"\n{graded} graded pairs, {failures} failing, {len(PAIRS) - graded} informational.")

    if args.legacy:
        print("\n## Originals vs corrected\n")
        print("| Pair | Original | Ratio | Corrected | Ratio | Need |")
        print("|---|---|--:|---|--:|--:|")
        for fg, bg, kind, label, nfg, nbg in LEGACY:
            old = ratio(solid_literal(fg, bg), solid_literal(bg, "#ffffff"))
            nf, nb = res.solid(nfg), res.solid(nbg)
            new = ratio(nf, nb)
            print(f"| {label} | {fg} on {bg} | {old:.2f} | {hexs(nf)} on {hexs(nb)} | {new:.2f} | {THRESHOLD[kind]:.1f} |")

    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
