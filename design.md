---
version: "alpha"
name: "Metadatapp Enterprise Serious"
description: "Design tokens for Metadatapp's default Osoma appearance preset."
colors:
  primary: "#24727f"
  secondary: "#212a3b"
  tertiary: "#1d9a77"
  success: "#1f7a4d"
  warning: "#e1860e"
  error: "#d32234"
  info: "#2875c3"
  neutral-50: "#f5f8fa"
  neutral-100: "#edf2f5"
  neutral-600: "#5f6f7f"
  neutral-900: "#1f2937"
  background: "#f5f8fa"
  surface: "#edf2f5"
  line: "#d5dde5"
  on-primary: "#ffffff"
  on-secondary: "#ffffff"
  on-tertiary: "#0f172a"
typography:
  heading-lg:
    fontFamily: "Space Grotesk"
    fontSize: "2rem"
    fontWeight: 700
    lineHeight: 1.2
  body-md:
    fontFamily: "IBM Plex Sans"
    fontSize: "0.9375rem"
    fontWeight: 400
    lineHeight: 1.5
  mono-sm:
    fontFamily: "JetBrains Mono"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.4
rounded:
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "20px"
spacing:
  xs: "8px"
  sm: "12px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.body-md}"
    rounded: "{rounded.lg}"
    padding: "{spacing.sm}"
  button-secondary:
    backgroundColor: "{colors.secondary}"
    textColor: "{colors.on-secondary}"
    typography: "{typography.body-md}"
    rounded: "{rounded.lg}"
    padding: "{spacing.sm}"
  button-accent:
    backgroundColor: "{colors.tertiary}"
    textColor: "{colors.on-tertiary}"
    typography: "{typography.body-md}"
    rounded: "{rounded.lg}"
    padding: "{spacing.sm}"
  card-default:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.neutral-900}"
    rounded: "{rounded.xl}"
    padding: "{spacing.lg}"
  status-success:
    backgroundColor: "{colors.success}"
    textColor: "{colors.on-primary}"
    typography: "{typography.body-md}"
    rounded: "{rounded.md}"
    padding: "{spacing.xs}"
  status-warning:
    backgroundColor: "{colors.warning}"
    textColor: "{colors.on-tertiary}"
    typography: "{typography.body-md}"
    rounded: "{rounded.md}"
    padding: "{spacing.xs}"
  status-info:
    backgroundColor: "{colors.info}"
    textColor: "{colors.on-primary}"
    typography: "{typography.body-md}"
    rounded: "{rounded.md}"
    padding: "{spacing.xs}"
  divider:
    backgroundColor: "{colors.line}"
    textColor: "{colors.neutral-900}"
    rounded: "{rounded.sm}"
    height: "1px"
  app-shell:
    backgroundColor: "{colors.background}"
    textColor: "{colors.neutral-900}"
    rounded: "{rounded.xl}"
    padding: "{spacing.lg}"
  panel-muted:
    backgroundColor: "{colors.neutral-100}"
    textColor: "{colors.neutral-600}"
    rounded: "{rounded.lg}"
    padding: "{spacing.md}"
  page-canvas:
    backgroundColor: "{colors.neutral-50}"
    textColor: "{colors.neutral-900}"
    rounded: "{rounded.xl}"
    padding: "{spacing.lg}"
---

## Overview

Metadatapp uses a research-grade enterprise visual language: quiet neutrals, clear hierarchy,
and accent color reserved for important interaction moments. The default tone should feel
trustworthy, precise, and readable for long-form scientific metadata workflows.

## Colors

The palette is built around cool, low-noise neutrals with teal-forward action colors.

- **Primary (`#24727f`)** drives principal actions and branded highlights.
- **Secondary (`#212a3b`)** supports navigation and dense informational UI.
- **Tertiary (`#1d9a77`)** is used for interactive emphasis and focused states.
- **Background/Surface (`#f5f8fa` / `#edf2f5`)** keep data-heavy views calm.
- **Semantic colors** (`success`, `warning`, `error`, `info`) provide status signals.

## Typography

Typography prioritizes clarity and scanning speed.

- **IBM Plex Sans** is the default body font for long metadata forms and tables.
- **Space Grotesk** is used for headings and section landmarks.
- **JetBrains Mono** is reserved for technical values, identifiers, and code-like fields.

## Layout

Spacing should follow the token scale and preserve consistent rhythm between sections,
forms, cards, and dense grid content. Use `md`/`lg` spacing by default for desktop layouts,
and avoid ad-hoc pixel values when a spacing token exists.

## Elevation & Depth

Metadatapp uses shallow elevation. Surfaces are separated primarily by border and subtle shadow,
not by dramatic depth. Cards and panels should remain lightweight to avoid visual noise in
data-rich screens.

## Shapes

Rounded corners are soft but restrained. Inputs and buttons generally use `rounded.lg`, while
container-level surfaces use `rounded.xl`.

## Components

Components should reference shared tokens rather than hardcoded values. Buttons and cards in the
tokens above represent the baseline style for interactive controls and content containers.

## Do's and Don'ts

- **Do** keep high contrast between text and backgrounds for long reading sessions.
- **Do** use semantic tokens for feedback states instead of custom colors.
- **Don't** introduce new one-off color values in component code.
- **Don't** overuse accent colors; reserve them for action and focus.
