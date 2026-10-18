# SEO Pro Cookie Consent design

Shared styling rules and tokens live in `STYLING.md`. The starter uses
WordPress admin components, its colour scheme and its existing header,
tabs and cards; it adds no independent brand palette to Light mode.

## Admin colour mode

The Consent card starts with a three-step guide, a standard WordPress **Scan
now** button when consent is enabled, a polite live status and the existing
findings list. While scanning, the button is disabled and the status says
Scanning. Findings update in place without reloading the settings or moving
focus; failures keep the old list and explain the browse-pages fallback.
All controls inherit the existing Light/Dark admin styling.

Consent-ID lookup posts from the Consent card to a private standalone
WordPress response, with a labelled field and nonce. Results use a semantic
table, newest first in pages of 100, with Older records and a separate fresh
lookup form. Long values wrap; the table scrolls horizontally on phones.
No extra menu, visitor ID in a URL, new palette or animation is added.

The Import tab uses native details, lists, page checkboxes and WordPress buttons
instead of a new card or palette. Dry-run output precedes each action; pages
require individual selection and irreversible cleanup has a required backup
acknowledgement. Forms remain usable without JavaScript. Content and actions
flow vertically on phones, with the existing wrapping navigation; no motion
or new breakpoints. Long artifact names must wrap rather than widen the page.

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
- Button text keeps the theme's colour when it reaches 4.5:1 on the button
  background (normal and hover). If not (Kadence dark mode puts palette 9
  text on palette 1), `spcc.js` uses the theme's own background or strong
  text colour, whichever reads better, then white or black, and checks
  again when the theme switches mode.
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
- With recording enabled and an existing choice ID, a supplementary row
  before the actions shows a labelled, read-only monospace ID and quiet
  Copy button. Both controls retain 44px targets and theme colours. The
  row wraps on phones without widening the dialog; copy feedback uses a
  polite status on its own line. No new breakpoints or motion. Preview,
  missing IDs and disabled recording keep the entire row hidden.
- Placeholders keep the embed's size (its width and height, or core's
  responsive wrapper) with a translucent tint, centred text, a Load
  button and a Cookie settings text button. Pixel width and height keep
  the shape; percentages fill the same container (Kadence's Maps block).
  A pixel height keeps compact players compact (64px floor, one button
  row); unsized placeholders are at least 180px.
- Every placeholder shows a decorative image cropped to fill it: a locally
  cached YouTube or Vimeo thumbnail when ready, else a bundled generic
  picture in `assets/images/`: `map`, `video` (dark frame, play button),
  `audio` (play button and waveform; Spotify, SoundCloud), `post` (social
  card; X, Instagram, Facebook) or `embed` (browser window; anything else).
  Key shapes sit near the vertical middle, so thin crops still read. The
  notice has a 96%-opaque theme-surface scrim behind it so text and
  buttons keep their light/dark contrast; no new motion or breakpoints.
  Actions continue wrapping on phones, with existing 44px touch targets.
- Small placeholders step the notice down while it overflows: tighter
  spacing and text, then the Load and Cookie settings buttons only (the
  sentence stays for screen readers and in the Load button's title), then
  the Load button only.
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

## Listing images

- Banner and icon (`.wordpress-org/banner.svg`, `icon.svg`; rebuild with
  `scripts/build-banner.sh`): the wpallstars red tile stack and five-star
  arc, with this plugin's mark on the top tile, a bitten chocolate-chip
  cookie (golden top `#f7d08a` to `#d6913d`, baked sides `#a3591d`, chips
  `#4a230c`, cream edge), raised like SEO Pro Stack's bolt. Keep the
  banner's and icon's cookie the same, and check it reads at 128 px.
- Words: "SEO Pro Cookie Consent" (94, not the starter's 104, so the name
  clears the stack), gold line "Nicer notices. Faster setup.", tagline "User-friendly
  notices & settings, only shown when needed."
- Screenshots: a disposable Kadence site with demo content (a bakery with
  YouTube, Google Maps, Spotify and Google Analytics), 1280 x 800 desktop
  and a 390 px phone at 2x. Show real plugin screens only, with no third-
  party video frames or logos beyond the bundled placeholders.
