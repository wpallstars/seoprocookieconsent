# How consent works

Read this before changing the consent banner, the blocker, the service
list, regions or the cookie policy. Users' docs are in `README.md`.

## Goals

- Free, light, and as close to zero set-up as possible: switch it on and
  it works. The plugin decides from what it can detect; settings are
  choices people actually make (where the banner sits, how strict to be
  outside Europe) or bypasses under Troubleshooting.
- Page-cache safe: the HTML never varies by visitor. The server holds back
  known services in every page; the browser decides, per visitor, what to
  release.
- Ask only where needed: no banner on pages with nothing held back, and
  only as strict as the visitor's region requires.
- Fits the theme (Kadence palettes, light and dark), works on phones, and
  is usable with a keyboard and a screen reader.

## Parts

| Part | File | Job |
|---|---|---|
| Services | `includes/class-seoprocookieconsent-services.php` | Categories, the known services (match patterns, cookies, privacy links) and what the site was seen using. |
| Blocker | `includes/class-seoprocookieconsent-blocker.php` | Output buffer on front-end HTML: holds back matching `<script>` and `<iframe>` tags and wraps iframes in a placeholder. |
| Thumbnails | `includes/class-seoprocookieconsent-thumbnails.php` | Validates YouTube/Vimeo embed IDs, queues WP-Cron, and serves only locally cached JPEGs. |
| Consent | `includes/features/class-seoprocookieconsent-consent.php` | The feature: settings, buffer start, head config, banner and dialog markup, footer menu link, Cookie settings shortcode and block. |
| Policy | `includes/features/class-seoprocookieconsent-policy.php` | Cookie policy shortcode and block (auto-updating), privacy policy guide text. |
| Front end | `assets/js/spcc.js`, `assets/css/spcc.css` | Region decision, consent cookie, banner and dialog, releasing held-back tags, Google Consent Mode and WP Consent API signals. |

## Holding back

- Matching is plain substring matching (lower case) against a script's
  `src`, an inline script's text or an iframe's `src`. First match wins,
  so put more specific services first.
- A held-back script gets `type="text/plain"`, `data-spcc-category`,
  `data-spcc-service` and, when it had one, its old type in
  `data-spcc-type`. Releasing re-creates each script in document order,
  waiting for external scripts that were not `async`, so inline code that
  needs them still runs after them.
- A held-back iframe loses `src` (kept in `data-spcc-src`) and is wrapped
  in `.spcc-embed` with a short notice and a **Load** button that allows
  that one service. Releasing unwraps it, restoring the original markup.
- Never held back: JSON and template script types, scripts with
  `data-spcc-ignore`, the plugin's own, and anything in Troubleshooting →
  Never hold back. Security and payment scripts (reCAPTCHA, Turnstile,
  Stripe, PayPal) are not in the list on purpose: blocking them breaks
  forms and checkouts, and they are necessary.
- Other third-party script hosts are recorded (up to 20) and listed on the
  settings card, so the owner can add any that set cookies to Also hold
  back. Records are written only when something new is seen, never on
  every page.

## Local thumbnails

The blocker reads a non-autoloaded option per validated provider/video ID and
queues one `seoprocookieconsent_thumbnail` event on a cache miss. No provider
HTTP runs during rendering. Cron uses fixed provider endpoints, rejects
redirects, limits response size, validates JPEG bytes and writes only under
`uploads/seoprocookieconsent-thumbnails`. Vimeo metadata may point only to
its HTTPS image CDN. Failed attempts are held for a day; unsupported/private
videos retain the plain placeholder. Disabled consent stops downloads.

The browser receives only a same-host uploads URL, never a provider or external
CDN URL. Markup is visitor-independent, and existing page caches need their
normal refresh before a newly downloaded image appears. Uninstall clears
the event, all thumbnail options and cached JPEGs on every site.

## Regions

The browser picks the model from its time zone (no IP lookup, nothing
sent anywhere, works with page caches). Time zones are a proxy for
location, so anything unclear is treated strictly.

| Model | Where (`region` setting) | Behaviour |
|---|---|---|
| `opt-in` | EEA, UK, Switzerland, Gibraltar and other European time zones, Quebec, and unknown or UTC time zones | Nothing held back loads until the visitor allows it. Banner: Accept all, Reject all (equal weight), Settings. |
| `opt-out` | Everywhere else | Loads by default; a short notice with OK and Settings. Global Privacy Control turns marketing off. |

The `regions` setting chooses: `everywhere` (opt-in for all), `region`
(the table, default), or `region_quiet` (as `region`, but no notice
outside opt-in places; the footer link still opens Cookie settings).
c15t's presets (EEA + UK + Gibraltar opt-in, US privacy states and Canada
outside Quebec opt-out with GPC, Quebec opt-in) were the reference. The
`seoprocookieconsent_front_config` filter changes the config the browser
reads, for example to force a model from a CDN country header on sites
without page caching.

## Consent cookie

`spcc_consent` (necessary, first party, `SameSite=Lax`, `Secure` on
HTTPS): JSON with `v` (format version), `c` (category => 0/1), `s`
(services allowed one at a time), `m` (model when given), `t` (time) and
`p: 1` while the only answer is a Load button: the banner stays until the
visitor chooses, and Reject all drops those services too.
Kept for the Consent kept for days setting (default 365). A dismissed
opt-out notice stores the defaults with `m: "opt-out"`; it never counts
as opt-in consent if the visitor later shows up in an opt-in place.

## Signals to other code

- `window.spcc.has(category)`, `window.spcc.open()` and the
  `spcc:consent` document event (detail: categories, services).
- Google Consent Mode v2: defaults denied in the head (unless the model
  is opt-out), then `gtag('consent', 'update', …)`. Statistics →
  `analytics_storage`; marketing → `ad_storage`, `ad_user_data`,
  `ad_personalization`; preferences → `functionality_storage`,
  `personalization_storage`.
- WP Consent API (when its plugin is active): `wp_set_consent()` for
  each category it knows.

## Policies

The Cookie policy block (`seoprocookieconsent/cookie-policy`) and
`[spcc_cookie_policy]` render a list of the services the site was seen
using, grouped by category, with their usual cookies, how long they are
kept and links to their privacy policies, plus the Cookie settings
button. Owners write the rest of the page as normal blocks, so their own
text is never overwritten and the list stays current. The privacy
policy guide (Settings → Privacy) gets suggested text too. Text is a
starting point, not legal advice: the README says so.

## Not yet

Tracked as issues: Terms and conditions generator (Complianz Terms &
Conditions), consent records, scripts added
after page load (lightboxes), a Scan now button and first-run guide,
and per-language banner text beyond translations.
