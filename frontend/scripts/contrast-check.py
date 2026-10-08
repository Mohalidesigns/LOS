#!/usr/bin/env python3
"""
Fundly LOS — WCAG 2.2 contrast check for the frontend design tokens (G-46, NFR-014).
Adapted from docs/contrast-check.py for the D-042 (loan-ui.jpg) re-theme.

Parses src/design-tokens/tokens.css (the single source of truth), resolves
var() chains, composites translucent colours over their real backdrop, and
computes the WCAG relative-luminance contrast ratio for every token pair the UI
is allowed to use.

Thresholds (WCAG 2.2 AA):
  text   >= 4.5:1  (1.4.3, normal text)
  large  >= 3.0:1  (1.4.3, >= 24px regular or >= 18.66px bold)
  ui     >= 3.0:1  (1.4.11, component boundaries, state indicators, focus rings, meaningful graphics)
  exempt           (disabled controls, pure decoration): printed for information, not graded

Usage:
  python3 scripts/contrast-check.py               # table of all graded pairs; exit 1 on any failure
  python3 scripts/contrast-check.py --css path/to/tokens.css
  python3 scripts/contrast-check.py --failures    # only print failing rows
No dependencies beyond the standard library.
"""
from __future__ import annotations

import argparse
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
DEFAULT_CSS = os.path.join(HERE, "..", "src", "design-tokens", "tokens.css")
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
            return over((rgb, a), (255, 255, 255))  # translucent with no stated backdrop: white
        return rgb


# --------------------------------------------------------------------------- the pairs the UI may use
# (foreground, background, kind, usage)
SURFACES_LIGHT = [
    "--color-bg", "--color-bg-page", "--color-bg-panel",
    "--color-bg-subtle", "--color-bg-muted", "--color-bg-neutral",
]

PAIRS: list[tuple[str, str, str, str]] = []
for _s in SURFACES_LIGHT:
    PAIRS += [
        ("--color-text-primary", _s, "text", f"Body text on {_s}"),
        ("--color-text-secondary", _s, "text", f"Secondary text on {_s}"),
        ("--color-text-tertiary", _s, "text", f"Meta / table header on {_s}"),
        ("--color-text-heading", _s, "text", f"Headings, big figures on {_s}"),
        ("--color-text-link", _s, "text", f"Links on {_s}"),
        ("--color-border-control", _s, "ui", f"Control boundary on {_s}"),
        ("--focus-ring-color", _s, "ui", f"Focus ring on {_s}"),
        ("--color-indicator", _s, "ui", f"Meaningful icon on {_s}"),
    ]

PAIRS += [
    ("--color-text-placeholder", "--color-bg", "text", "Input placeholder"),
    ("--color-text-placeholder", "--color-bg-neutral", "text", "Search field placeholder"),
    ("--color-text-primary", "--color-bg-hover@--color-bg", "text", "Hovered row"),
    ("--color-text-tertiary", "--color-bg-hover@--color-bg", "text", "Meta in hovered row"),
    ("--color-text-secondary", "--color-bg-hover-nav@--color-bg-subtle", "text", "Hovered nav item"),
    ("--color-text-tertiary", "--color-bg-danger-wash@--color-bg", "text", "Meta in breached row"),
    ("--color-text-link-hover", "--color-bg", "text", "Link hover"),
    ("--color-text-tertiary", "--color-accent-subtle", "text", "Meta in brand tint tile"),
    ("--color-text-heading", "--color-accent-subtle", "text", "Icon/value in brand tint tile"),
    # lime fills: dark text only
    ("--color-text-on-accent-fill", "--color-bg-selected", "text", "Active nav pill label"),
    ("--color-text-on-accent-fill", "--color-accent-fill", "text", "Accent (lime) button label"),
    ("--color-text-on-accent-fill", "--color-accent-fill-hover", "text", "Accent button hover"),
    ("--color-text-on-accent-fill", "--color-accent-fill-subtle", "text", "Positive trend chip"),
    ("--color-text-heading", "--color-accent-fill-subtle", "text", "Positive trend chip (heading ink)"),
    ("--focus-ring-color", "--color-bg-selected", "ui", "Focus ring on active nav pill"),
    # brand surfaces
    ("--color-text-on-accent", "--color-accent", "text", "Primary button"),
    ("--color-text-on-accent", "--color-accent-hover", "text", "Primary button hover"),
    ("--color-text-on-accent", "--color-accent-pressed", "text", "Primary button pressed"),
    ("--color-text-on-brand", "--color-bg-brand", "text", "Hero card, login panel text"),
    ("--color-text-on-brand-muted", "--color-bg-brand", "text", "Hero card labels"),
    ("--color-text-on-brand", "--color-bg-brand-raised", "text", "Tile on brand surface"),
    ("--color-text-on-brand-muted", "--color-bg-brand-raised", "text", "Label on brand tile"),
    ("--color-text-on-accent-fill", "--color-brand-contrast", "text", "Lime button on brand surface"),
    ("--focus-ring-color-on-brand", "--color-bg-brand", "ui", "Focus ring on brand surface"),
    ("--color-brand-contrast", "--color-bg-brand", "ui", "Logo petals / lime mark on brand"),
    ("--color-text-inverse", "--color-bg-inverse", "text", "Tooltip"),
    ("--color-text-on-danger", "--color-danger-fill", "text", "Danger button"),
    ("--color-text-on-danger", "--color-danger-fill-hover", "text", "Danger button hover"),
    # status tones (outlined pills on white and muted; chip fills; banners)
    ("--color-success-fg", "--color-bg", "text", "Success pill / inline"),
    ("--color-success-fg", "--color-success-bg", "text", "Success banner / chip fill"),
    ("--color-success-fg", "--color-bg-muted", "text", "Success text on muted"),
    ("--color-warning-fg", "--color-bg", "text", "Warning pill / inline"),
    ("--color-warning-fg", "--color-warning-bg", "text", "Warning banner / chip fill"),
    ("--color-warning-fg", "--color-bg-muted", "text", "Warning text on muted"),
    ("--color-danger-fg", "--color-bg", "text", "Danger pill / inline"),
    ("--color-danger-fg", "--color-danger-bg", "text", "Danger banner / trend chip"),
    ("--color-danger-fg", "--color-bg-muted", "text", "Danger text on muted"),
    ("--color-danger-fg", "--color-bg-danger-wash@--color-bg", "text", "Danger text in breached row"),
    ("--color-danger-text-strong", "--color-danger-bg", "text", "Danger callout body"),
    ("--color-info-fg", "--color-bg", "text", "Info pill / inline"),
    ("--color-info-fg", "--color-info-bg", "text", "Info banner"),
    ("--color-neutral-fg", "--color-bg", "text", "Neutral pill"),
    ("--color-neutral-fg", "--color-neutral-bg", "text", "Neutral chip fill"),
    ("--color-text-primary", "--color-success-bg", "text", "Success banner body"),
    ("--color-text-primary", "--color-warning-bg", "text", "Warning banner body"),
    ("--color-text-primary", "--color-danger-bg", "text", "Danger banner body"),
    ("--color-text-primary", "--color-info-bg", "text", "Info banner body"),
    ("--focus-ring-color", "--color-warning-bg", "ui", "Focus ring in warning banner"),
    ("--focus-ring-color", "--color-danger-bg", "ui", "Focus ring in danger banner"),
    ("--focus-ring-color", "--color-info-bg", "ui", "Focus ring in info banner"),
    # P1-FE-02 origination slice
    ("--color-text-on-accent", "--color-accent-graphic", "ui", "Check mark in a completed stage dot"),
    ("--color-text-on-accent", "--color-accent", "text", "Selected filter chip label"),
    ("--color-text-on-accent-fill", "--color-accent-fill", "text", "Current stage / step number on lime"),
    ("--color-text-primary", "--color-accent-subtle", "text", "Selected product card / party chip"),
    ("--color-text-secondary", "--color-bg-subtle", "text", "Upload drop-zone text"),
    ("--color-success-fg", "--color-bg-muted", "ui", "KYC condition met icon"),
    ("--color-warning-fg", "--color-bg-muted", "ui", "KYC condition unmet icon"),
    ("--color-danger-fg", "--color-bg-danger-wash@--color-bg", "text", "Quarantined version label"),
    ("--color-text-tertiary", "--color-bg-danger-wash@--color-bg", "text", "Meta in quarantined version row"),
    # large text
    ("--color-text-heading", "--color-bg", "large", "Stat card value 28px"),
    ("--color-text-on-brand", "--color-bg-brand", "large", "Hero amount 34px"),
    # non-text graphics
    ("--color-accent", "--color-bg", "ui", "Checked box / selected chip vs card"),
    ("--color-text-on-accent", "--color-accent", "ui", "Checkmark on checked box"),
    ("--color-accent-graphic", "--color-bg", "ui", "Chart series 1 / tracker dot"),
    ("--color-accent-graphic", "--color-accent-fill", "ui", "Progress fill vs lime track"),
    ("--color-accent-fill-edge", "--color-bg", "ui", "Edge around lime chart bars (series 2)"),
    ("--color-chart-3", "--color-bg", "ui", "Chart series 3"),
    ("--color-chart-4", "--color-bg", "ui", "Chart series 4"),
    ("--color-chart-5", "--color-bg", "ui", "Chart series 5"),
    ("--color-score-1", "--color-bg", "ui", "Score band 1"),
    ("--color-score-2", "--color-bg", "ui", "Score band 2"),
    ("--color-score-3", "--color-bg", "ui", "Score band 3"),
    ("--color-score-4", "--color-bg", "ui", "Score band 4"),
    ("--color-score-5", "--color-bg", "ui", "Score band 5"),
    ("--color-success-fg", "--color-bg", "ui", "SLA on-track dot"),
    ("--color-warning-fg", "--color-bg", "ui", "SLA at-risk dot"),
    ("--color-danger-fg", "--color-bg", "ui", "SLA breached dot / notification dot"),
    # exempt: information only
    ("--color-text-disabled", "--color-bg", "exempt", "Disabled control label (WCAG 1.4.3 exception)"),
    ("--color-chart-2", "--color-bg", "exempt", "Lime fill alone (always edged with accent-fill-edge)"),
    ("--color-accent-fill", "--color-bg", "exempt", "Lime nav pill / track (label carries meaning)"),
    ("--color-decorative", "--color-bg", "exempt", "Decorative separators only"),
    ("--color-border", "--color-bg", "exempt", "Hairline card edge (decorative)"),
    ("--color-border-strong", "--color-bg", "exempt", "Secondary button edge (label identifies control)"),
    ("--color-success-border", "--color-bg", "exempt", "Outlined pill edge (label carries meaning)"),
    ("--color-chart-grid", "--color-bg", "exempt", "Chart gridlines (decorative)"),
]


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    ap.add_argument("--css", default=DEFAULT_CSS)
    ap.add_argument("--failures", action="store_true", help="only print failing rows")
    args = ap.parse_args()

    res = Resolver(load_tokens(args.css))
    failures = 0
    print("## Token contrast (WCAG 2.2 AA) — D-042 loan-ui palette\n")
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
        if args.failures and result != "**FAIL**":
            continue
        need_s = f"{need:.1f}" if need else "—"
        print(f"| {i} | `{fg}` | `{bg}` | {hexs(f)} | {hexs(b)} | {r:.2f} | {need_s} | {result} | {usage} |")

    graded = sum(1 for p in PAIRS if p[2] in THRESHOLD)
    print(f"\n{graded} graded pairs, {failures} failing, {len(PAIRS) - graded} informational.")
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
