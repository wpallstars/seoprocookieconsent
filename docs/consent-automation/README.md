# Consent automation drafts

These are local, tested drafts, **not rules already shipped by either browser
extension**. The public DOM contract is in the repository's `README.md` →
Consent automation contract. No appearance, legal wording, region defaults,
cookie format or save behaviour changes here. `assets/js/spcc.js` needs no
change: its existing dialog submit handler already ignores the document action
handler, so each click saves once.

## Sources and decisions

Read upstream on 2026-10-17, after discovering the repository trees:

- [DuckDuckGo autoconsent](https://github.com/duckduckgo/autoconsent),
  `a62208dd77950ee2f58aaa4f7d5d9fbaae923023`, version 16.55.0:
  `readme.md`, `docs/rule-syntax.md`, `.github/PULL_REQUEST_TEMPLATE.md`,
  `rules/autoconsent/complianz-optin.json`, `playwright/runner.ts` and
  `scripts/validate-json-rules.js`. There is no CONTRIBUTING file in this tree;
  its README, rule reference and PR template provide contribution guidance.
- [Consent-O-Matic](https://github.com/cavi-au/Consent-O-Matic),
  `8ca8500d26434c586039e126ad091e1bfccd205d`:
  `README.md` → Extending Consent-O-Matic, `rules.schema.json`,
  `rules/complianz.io.json` and the `Extension/` engine, detector, matcher and
  action modules. Its README is the contribution guide; no separate
  CONTRIBUTING file was present.

Use DOM actions, not a new JavaScript setter. Both projects describe DOM rules;
autoconsent recommends eval only as a last resort. The existing controls already
save the cookie, notify integrations, release or continue blocking content and
optionally record choices. There is no extra API surface to maintain.

Both drafts detect a visible banner **or** an already-open settings dialog. They
open Settings when needed, then submit through the actual form. They never use
OK (regional notice dismissal), close, hide, prehide, cookie writes or injected
consent events as a substitute for answering. Empty/quiet pages without a prompt
are not answered just because their footer has a Cookie settings link.

`autoconsent.json` uses explicit dialog Accept all and Reject all. The library's
rule interface offers binary opt-in/opt-out, not arbitrary category preferences.
Its self-test checks the cookie's presence and explicit-choice model and the
absence of a prompt; the local verification additionally asserts every category,
services and persistence. Refusal sets every optional category to 0 and drops
service-only grants, with `m: "opt-in"` (a real choice, not a dismissal).

`consent-o-matic.json` supports stored partial preferences:

| Plugin category | Consent-O-Matic preference |
| --- | --- |
| preferences | A — Preferences and Functionality |
| statistics | B — Performance and Analytics |
| embeds | E — Content selection, delivery, and reporting |
| marketing | F — Ad selection, delivery, and reporting |

Necessary stays on; D (Information Storage and Access) and X (Other Purposes)
have no independent plugin switches. They do not grant other categories.
The category mapping is an approximation of the plugin's coarse groups, not
per-vendor or per-purpose consent. Embeds is deliberately mapped to content,
not advertising. Each checkbox matcher compares the current state before
clicking, then Save runs even if no switch needed changing. All four optional
inputs remain present when unused category rows are hidden; omit display
filtering on these inputs so opt-out defaults cannot survive in hidden rows.

Automated choices are recorded exactly like other choices when recording is
enabled. Do not label them automated: synthetic clicks do not reliably identify
an extension or its user, and collecting fingerprints would not improve proof.
Records remain self-reported, best-effort evidence, not authenticated proof of
human interaction. CookiesClerk is a cookie/account switcher, not a banner
answerer, and is outside this work.

## Reproduce locally

Use disposable checkouts of the upstream revisions above. In autoconsent run
`npm ci --ignore-scripts` then `npm run prepublish` (its documented build), and
use the Playwright browser matching its installed version. No dependency is
added to the plugin and no extension installation or upstream file edit is
needed. The local spec feeds the exact draft into the upstream harness in
memory, in full and compact format, and bundles Consent-O-Matic's real engine
without its cosmetic progress overlay. It does not reimplement rule actions.

Commit the plugin changes first: `test-site.sh` builds Git, not dirty files.
From this plugin worktree:

```sh
scripts/test-site.sh up
# up prints local-only credentials: keep that output private.
scripts/test-site.sh wp eval '
SEOProCookieConsent_Settings::set("consent", true);
SEOProCookieConsent_Settings::set("consent_regions", "everywhere");
SEOProCookieConsent_Settings::set("consent_records", true);
SEOProCookieConsent_Settings::set("consent_block", "preferences: gh45_preferences\nstatistics: gh45_statistics\nmarketing: gh45_marketing\nembeds: gh45_embeds");
$content = "<h2>Consent automation example</h2>";
foreach (array("preferences", "statistics", "marketing", "embeds") as $category) {
    $content .= "<script>window.gh45_" . $category . " = 1;</script>";
}
echo wp_insert_post(array("post_title" => "Consent automation", "post_status" => "publish", "post_type" => "page", "post_content" => $content));'
scripts/test-site.sh url
# Set SPCC_TEST_URL to that local origin + /?page_id= + the returned post ID.
# Set AUTOCONSENT_ROOT and CONSENTOMATIC_ROOT to the two absolute checkout paths.
export AUTOCONSENT_ROOT CONSENTOMATIC_ROOT SPCC_TEST_URL
"$AUTOCONSENT_ROOT/node_modules/.bin/playwright" test --config docs/consent-automation/playwright.config.ts
scripts/test-site.sh log
scripts/test-site.sh down
```

The custom inline script markers make verification deterministic without
contacting any analytics/ad/embed provider. This is a real WordPress page and
blocker, **not** the admin banner preview (which intentionally never saves).
Use a fresh browser context per case. Each case requires all four switches,
checks `spcc_consent`, reloads, and checks which inline scripts ran. Records are
enabled on this throwaway site only. The spec requires one HTTP 204 from the
actual records endpoint and no second record on reload. Re-run `scripts/lint.sh`,
`scripts/smoke-test.sh` and `scripts/plugin-check.sh` for plugin checks.

## Local results — 2026-10-17

Plugin runtime: WordPress 7.1.3, PHP 8.3.35, Chromium through Playwright 1.63.0;
Node 26.4.0. Initial runtime build: `1e563f7` (same template as the final change).

| Check | Result |
| --- | --- |
| Both drafts against upstream JSON schemas | Passed |
| Autoconsent upstream harness: opt-in | Passed; all four optional categories 1 |
| Autoconsent upstream harness: opt-out and self-test | Passed; all four 0, services empty, explicit model |
| Consent-O-Matic real engine: refuse | Passed; all four 0 |
| Consent-O-Matic real engine: accept | Passed; all four 1 |
| Consent-O-Matic real engine: mixed A/E on, B/F off | Passed; preferences/embeds 1, statistics/marketing 0 |
| Cookie after reload and corresponding marker-script execution | Passed for all five answer cases |
| Optional record POST | Exactly one HTTP 204 per case, none on reload |
| Stored database choices | Latest five rows independently read with WP-CLI: two all-off, two all-on, one mixed; all explicit opt-in, services empty |
| `scripts/lint.sh` | Passed PHP/JS syntax, ShellCheck, actionlint, PHPCS and PHPStan; no JS build configured |
| `scripts/smoke-test.sh` | Passed on latest WordPress/PHP 8.3, 10,001 posts and 30,002 meta rows |
| `scripts/plugin-check.sh` | Passed both builds; only the documented GitHub updater findings/warnings, WordPress.org build 0 errors/warnings |
| Desktop 1440×900 / mobile 375×667 before/after | HTTP 200, no console errors, identical PNG hashes at each viewport |

Desktop before/after SHA-1: `9d78e054de328e4f8c2132b774923d1103fecbba`.
Mobile before/after SHA-1: `9436073b1de133e6a67a5bf0f1875d9fb7ed0288`.
Screenshots were taken through `browser-qa-helper.sh screenshot` against the
same page before (`origin/main`) and after reinstalling the branch, with a
fresh context and max dimension 1568. No CSS, labels or layout changed.

The upstream harness occasionally logs a non-fatal screenshot cancellation at
test teardown when the persistence reload triggers detection again; all six
tests pass. Initial test setup used an incorrect WP-CLI method name, corrected
to `Settings::set`; its CLI error is the only entry in that site's debug log,
not a banner/runtime error. Composer 2.8.8 emits pre-existing PHP 8.5 deprecation
notices on the host; the plugin lint and PHP 8.3 runtime checks pass.

## Upstream submission status

Waiting deliberately: the plugin repository is still private (verified through
the GitHub API), and no authorized public example site is supplied. Do not
publish the plugin, expose a private test site or submit inaccessible examples.
This is a recorded submission deferral, not a claim that either upstream
requires a public example under a written rule.

After the owner publishes the plugin and provides an unauthenticated public
example that actually triggers consent, recheck both formats against current
upstream and repeat accept/refuse/partial tests. Submit `autoconsent.json` as
`rules/autoconsent/seoprocookieconsent.json` with a `tests/` entry using the
existing `generateCMPTests` pattern, and `consent-o-matic.json` as
`rules/seoprocookieconsent.json`, following each current contribution guide.
Include the example and results, and update this status with the upstream PRs.
Do not claim shipped support until upstream distributes the rules.

## Licence

The original JSON rule drafts and verification files in this directory are
GPL-3.0-or-later, Copyright (C) 2026 Marcus Quinn, with the additional terms in
`ATTRIBUTION.txt`. Provenance: `wpas-647035161c3dc0ef`. JSON has no comments;
this companion notice supplies attribution for the two rule files. No upstream
source is copied into the drafts; the tests import local upstream checkouts.
