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
| Consent | `includes/features/class-seoprocookieconsent-consent.php` | The feature: settings, buffer start, head config, banner and dialog markup, footer menu link (placed beside the policy links) and the Appearance → Menus box, Cookie settings shortcode and block. |
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
- `spcc-head.js` also guards dynamic script and iframe sources and common
  DOM insertion methods before they can start requests. Markup inserted by
  `innerHTML`, `outerHTML` or `insertAdjacentHTML` is checked in an inert
  template first. The early `MutationObserver` watches added subtrees and
  source changes, wraps held iframes, and tells `spcc.js` to refresh the
  existing banner, category rows and release queue. An observer alone is
  too late to stop script execution or iframe navigation.
- Browser matching uses compact URL fragments from the same service list,
  not the built-in inline-code signatures or cookies' descriptions. Keep
  paths where a shared host serves several purposes (Google tags versus
  reCAPTCHA); Also hold back, Never hold back and Google advanced mode
  retain their precedence. Dynamic matches are page-local: no reporting
  request or database write is added. A dynamic script's source is kept in
  `data-spcc-src` until release, and each queued script runs only once.
- This is not a browser-wide network firewall: scripts must not run before
  `spcc-head`, and optimizers must not delay or reorder it. Unintercepted
  parser APIs (`document.write`, range fragments), shadow roots, other
  documents, inline-only injected code, `srcdoc`, fetch/XHR and scripts
  using saved native methods are outside dynamic URL blocking. Integrations
  using those paths must consult `spcc.allows()` before loading content.

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
When optional records are on, `id` is a cryptographically random 128-bit hex
identifier, reused for choices until the cookie is lost or expires, and `policy`
is the configured policy label (up to 100 Unicode characters). Neither affects
the format version or whether the stored choice is accepted.
Kept for the Consent kept for days setting (default 365). A dismissed
opt-out notice stores the defaults with `m: "opt-out"`; it never counts
as opt-in consent if the visitor later shows up in an opt-in place.

## Optional consent records

`includes/class-seoprocookieconsent-records.php`, loaded by Setup, owns a
write-only `POST /seoprocookieconsent/v1/records` route. Only when Consent and
Keep consent records are enabled does it accept bounded same-origin JSON.
The browser calls `sendBeacon` only from `save()`, including Load, Reject and
withdrawals; page loads, automatic defaults and previews never send records.
No nonce is embedded in cached HTML. Origin checks stop cross-site browser
submissions, not forged clients. Records are self-reported, best-effort,
not authenticated proof; there is no public record read or deletion API.

Storage is a per-site `{db_prefix}seoprocookieconsent_records` table, lazily
created with dbDelta after opt-in on admin/settings work. The non-autoloaded
`seoprocookieconsent_records_schema` option versions this separate schema.
It stores only the cookie ID, categories/services/model/pending state, client
time, server receipt time, policy label and known snapshot hash. No IP, user agent or page URL.
The primary ID and receipt-time index cover export and expiry. CSV export
uses manage_options plus an admin-post nonce, primary-key pages of 500 and
an upper ID snapshot; expired rows are omitted and policy formula prefixes
escaped. Schema 2 adds a snapshot column and private
`{db_prefix}seoprocookieconsent_snapshots` table: unique SHA-256 hash over
recursively key-sorted JSON, creation time and immutable content. Settings changes,
admin sync and `wp_after_insert_post` for the linked policy deduplicate the banner
config text, view labels, categories/services, plugin version and local policy
source/modified time/latest revision ID. No snapshot is created on visitor or REST
choice requests. Front config exposes only an existing matching hash; old, missing,
malformed or unknown hashes are recorded as empty, including unarchived translations.
Templates/catalogues are evidence of what was shown, not an exact browser rendering
or capture of dynamic/external policy content. A separately authorized and paged
CSV exports the archive; JSON object cells cannot start spreadsheet formulas.
Label changes do not invalidate consent.

Hourly `seoprocookieconsent_records_cleanup` deletes up to 1,000 rows older
than the current consent lifetime by indexed server receipt time, continuing
after five minutes when a batch is full. Existing data continues to expire
with recording/Consent disabled. WP-Cron must run; purge page caches after
settings changes. Snapshots are kept until uninstall, never removed by record
retention, preserving every kept reference. Uninstall drops both per-site tables,
the schema option and event.
No record table or cron is created for sites that never enable records.

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

Terms (`includes/features/class-seoprocookieconsent-terms.php`) is a separate,
off-by-default feature on Policies. `seoprocookieconsent/terms` and
`[spcc_terms]` use the same live settings: business, country, offerings,
returns and contact, or a full plain-text override. Every line is escaped
as a paragraph; there are no extra front-end assets, HTML execution or
visitor-dependent responses. Incomplete suggested wording is hidden from
everyone until business and contact are filled; the settings explain this.
Owners write surrounding blocks normally. Full-page caches need purging
after answer changes, as for other dynamic WordPress blocks.

DB version 3 imports unset identity/contact answers from
`complianz_tc_options_terms-conditions` (`organisation_name`,
`country_company`, and `email_company` or `page_company` according to
`contact_company`). It neither enables the feature nor guesses sales or
returns clauses. The shared replacement gate waits while the plugin in
`SEOProCookieConsent_Terms::REPLACES` is active. Source options and pages are
untouched; the owner reviews the wording and replaces the old block or
shortcode. This is not a country-specific legal generator, checkout
acceptance system or withdrawal form.

## Move here

`SEOProCookieConsent_Migrate` (`includes/migrate/`) runs import, undo and
confirmed cleanup for each source adapter
(`SEOProCookieConsent_Migrate_Source`, filter
`seoprocookieconsent_migrate_sources`). Adapters read source data only on
the Import tab, in WP-CLI and in `refresh()`. `refresh()` stores two
options: the browser's consent carry-over descriptors (autoloaded) and
what it found per source: `present`, `data` (removable leftovers) and
`pages` (`seoprocookieconsent_migrate_found`, not autoloaded). It runs on
the Import tab, after import, undo and cleanup, at the end of a request
that activates, deactivates or deletes a source plugin, and once in
WP-Cron when the cache is missing.

`SEOProCookieConsent_Migrate_Notices`
(`includes/admin/class-seoprocookieconsent-migrate-notices.php`) reads
only the stored plugin lists, the import records and that cache. Each
source is `import` (active and not imported, or inactive with its data
present), `switch` (imported, still active), `remove` (inactive or deleted,
imported, leftovers remain) or nothing. It shows on the Plugins screen
(a top notice and a note under each source row) and on the settings
screen, for people who can activate plugins, not in the network admin.
For sources with a step, the starter's replaced-plugins lines are left
out: its row note is wrapped, and its notice reads that person's hidden
list with those sources added, so nobody is told to deactivate before
importing. Hide stores `source:state` in user meta.

## Settings screen preview

The Consent card's **Preview the banner** frames the home page with
`?spcc_preview=opt-in` or `opt-out` (`assets/js/consent-admin.js`). For
someone who can manage options, `SEOProCookieConsent_Consent::config()`
then sets the model, `preview` and sample service names; the head script
ignores the stored choice, and `spcc.js` shows the banner without
releasing, saving or signalling anything, and stops links navigating.
The toolbar is hidden. Anyone else gets the page as usual. The frame
reloads after each Consent setting saves
(`seoprocookieconsent:setting-saved`).

## Page language

Consent registers nonempty `consent_message` and `consent_policy_url` strings in
the SEO Pro Cookie Consent group on `admin_init` and settings changes. WPML's
single-string filter and Polylang's string API resolve the message and URL at
render time. TranslatePress's plain-string API handles the custom JSON message;
normal page-output translation handles its other markup. Default labels retain
WordPress gettext, and `Intl.ListFormat` uses `document.documentElement.lang`.

Policy links also resolve the current language's published page using
`wpml_object_id` or `pll_get_post`, including the default privacy policy page.
Missing translations retain the original text or link. No source setting is
rewritten, no registration writes occur on visitor requests, and no visitor
cookies, browser language or IP decide server output. Multilingual page caches
must separate page languages and be refreshed after translation/settings changes.

## First-run scan

The site Consent card's Scan now action requires `manage_options`, a nonce
and an enabled feature. It samples the home and privacy pages plus at most
three published, non-password-protected posts and three pages. URLs must
match the home origin; redirects are disabled, cookies are not forwarded,
each request times out after three seconds and reads at most 2 MB. A fresh
query argument and no-cache header avoid ordinary page-cache hits.

The response is checked with the existing blocker and its service/host
recorder, including service IDs in already-held-back cached markup. No
JavaScript or third-party resources run, so JavaScript-only insertions are
not discovered. Findings accumulate as usual, rather than being cleared on
a failed or partial scan. The card updates in place and reports checked and
failed page counts; blocked loopbacks offer browsing as the fallback.
