# SEO Pro Cookie Consent design

Shared styling rules and tokens live in `STYLING.md`. The starter uses
WordPress admin components, its colour scheme and its existing header,
tabs and cards; it adds no independent brand palette to Light mode.

## Admin colour mode

- The header has an icon button after Buy me a coffee, with Light, Dark
  and System radio menu items. Each item has an icon, label and current
  choice tick. Screen readers get the mode name and save-failure message.
- Dark tokens: canvas `#101517`, surface `#1d2327`, raised `#2c3338`,
  border `#3c434a`, text `#f0f0f1`, muted `#c3c4c7`, field border `#8c8f94`.
  Accent fills follow WordPress's scheme; text accents are lightened.
- Palette overrides apply only below `html.spcc-dark` on own screens.
  Light keeps existing colours. System follows the OS live. There are no
  mode transitions, including for reduced-motion users.

## Front end

The banner, Cookie settings dialog, embed placeholders and Cookie
settings button (`assets/css/spcc.css`) take the theme's look: no brand
palette of their own.

- Colours are `--spcc-*` tokens set on each component from Kadence's
  palette (`--global-palette9` background, `3` strong text, `4` text, the
  `--global-palette-btn*` button colours), then block theme presets
  (`--wp--preset--color--base`, `contrast`, `primary`), then neutral
  fallbacks. Borders and tints are translucent grey, so Kadence's dark
  mode needs no extra rules. Fonts inherit.
- Accept all and Reject all share one solid button style; Settings and
  Save choices are the quiet outline style. Buttons are at least 44px
  tall; radius 6px (boxes and dialog 12px).
- Banner: a bar along the bottom by default, content up to the theme's
  content width, or a 420px box at the bottom left or right. Under 600px
  every position is a bottom sheet: message, then Reject all and Accept
  all side by side, then Settings across the full width.
- Dialog: native `<dialog>`, 560px wide, a sheet with 16px margins on
  phones, one switch row per kind of cookie with a top border, actions
  at the end (Reject all, Save choices, Accept all).
- Placeholders keep the embed's size (its width and height, or core's
  responsive wrapper) with a translucent tint, centred text, a Load
  button and a Cookie settings text button.
- Focus outlines are 2px in the strong text colour. Only the switches
  animate, and only without reduced motion.

## Responsive Behaviour

- Keep the existing header's wrapping and small-screen breakpoints.
  The mode control joins the header actions rather than adding another row.
- Use a 32px minimum icon button and 36px minimum menu rows; the mobile
  WordPress button sizing remains in effect. Menu minimum width is 168px.
- Position the menu at the logical end of the button, below it, for LTR
  and RTL. Keep focus outlines visible. Arrows, Home, End and Escape move
  within or close the menu; Tab closes it without trapping focus.
