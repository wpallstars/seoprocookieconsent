![WPALLSTARS SEO Pro Cookie Consent: Built with AI](admin/images/banner.svg)

# SEO Pro Cookie Consent

<!-- aidevops:badges:start -->
<!-- On GitHub only: the Read Me tab skips this block. scripts/rename-plugin.sh rewrites it. -->
[![CI](https://github.com/wpallstars/seoprocookieconsent/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/wpallstars/seoprocookieconsent/actions/workflows/ci.yml)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=wpallstars_seoprocookieconsent&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=wpallstars_seoprocookieconsent)
[![License: GPL v3 or later](https://img.shields.io/badge/License-GPL%20v3%20or%20later-blue.svg)](LICENSE)
[![Latest release](https://img.shields.io/github/v/release/wpallstars/seoprocookieconsent)](https://github.com/wpallstars/seoprocookieconsent/releases)

[![Requires WordPress](https://img.shields.io/badge/WordPress-6.2%2B-21759B.svg?logo=wordpress)](readme.txt)
[![Tested up to](https://img.shields.io/badge/tested%20up%20to-7.1-21759B.svg?logo=wordpress)](readme.txt)
[![Requires PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg?logo=php)](readme.txt)
[![Lines of code](docs/metrics/badges/loc.svg)](docs/metrics/repo-metrics.md)
[![Dependencies](docs/metrics/badges/dependencies.svg)](docs/metrics/repo-metrics.md)

[![Languages by lines of code](docs/metrics/badges/languages.svg)](docs/metrics/repo-metrics.md)
<!-- aidevops:badges:end -->

Simple, light cookie consent: blocks scripts until consent, asks only where the law and the page need it, and fits your theme.

SEO Pro Cookie Consent is a free cookie consent plugin for WordPress that you switch on rather than set up. It finds the scripts and embeds on your pages that set cookies (Google Analytics, ad pixels, YouTube, maps and more), holds them back until visitors allow them, and shows a short, theme-matched banner only on pages that have them, only as strictly as each visitor's region needs. It replaces Complianz, with nothing to scan, no wizard to finish and nothing sent to outside services.

It is a tool, not legal advice: check its defaults and your policy text with whoever advises you on privacy law.

If this saves you time, headaches and costs, feel free to [buy me a coffee](https://buymeacoffee.com/marcusquinn), or whatever you like, to invest in making more things open-source.

Version: 0.1.0

<!-- github-only:start -->
## Screenshots

**Settings → SEO Pro Cookie Consent**: the General tab, empty until features add settings, with search and the Source code, Support and Buy me a coffee links.

![The SEO Pro Cookie Consent settings screen on the General tab](.wordpress-org/screenshot-1.png)

**The Read Me tab** shows this file inside WordPress, banner included.

![The Read Me tab showing README.md](.wordpress-org/screenshot-2.png)

**Updates from GitHub** (GitHub build): each GitHub release is offered on the Plugins screen as a normal WordPress update.

![The Plugins screen offering SEO Pro Cookie Consent 1.0.12 from GitHub](docs/images/github-update.png)
<!-- github-only:end -->

## What you get

SEO Pro Cookie Consent replaces **2 plugins**, some of them in part, with free and Pro editions counted separately. The one that can be downloaded is 7.6 MB zipped; SEO Pro Cookie Consent is 278 KB.

- **Holds back what sets cookies.** Scripts and embeds from Google Analytics, Google Tag Manager, Google Ads, Meta Pixel, TikTok, LinkedIn, Pinterest, Microsoft Clarity and Advertising, Hotjar, HubSpot, X, YouTube, Vimeo, Google Maps, Spotify, SoundCloud, Instagram, Facebook and live chats (Tawk.to, Crisp, Intercom) wait until visitors allow them. Nothing to scan or list by hand: the plugin recognises them in each page as it is sent.
- **Asks only where needed.** No banner on pages with nothing held back. Visitors in Europe (EU, EEA, UK, Switzerland), Quebec, or anywhere their browser does not make clear, are asked before anything loads; everyone else gets the site as usual with a short notice, or no notice at all if you prefer. The browser's time zone decides, so nothing is looked up, nothing is sent anywhere and page caches keep working.
- **A short, fair banner.** Accept all and Reject all look the same, Settings opens a dialog with one switch per kind of cookie, and the message names what is on the page ("This page uses YouTube and Google Analytics…"). A bar along the bottom, or a box at the bottom left or right.
- **Videos and maps with a Load button.** A held-back YouTube video, map or post shows a placeholder the same size, with a button that loads just that one.
- **Local video thumbnails.** YouTube and Vimeo placeholders show a locally stored thumbnail. WP-Cron fetches it once per video, not during a visitor's page request; until it is ready (or if unavailable), the plain placeholder stays. Cached pages show it after their next refresh. No visitor data is sent to the video service for the thumbnail.
- **Fits your theme.** Colours, fonts and buttons come from Kadence's palette, light and dark mode included, or your block theme's colours. Comfortable on phones, keyboard friendly, with real switches and a proper dialog for screen readers.
- **Easy to change your mind.** A Cookie settings link joins your footer menu, the Cookie settings button block or any link to `#cookie-settings` opens it, and an optional floating button can show after visitors choose. Taking consent back removes those services' cookies and reloads the page so they stop.
- **Global Privacy Control** turns marketing off where visitors are not asked first.
- **Works with Google and WordPress.** Google Consent Mode v2 signals (denied by default where visitors are asked first) and the WP Consent API, so plugins that read it follow the visitor's choice.
- **A cookie policy that keeps itself current.** The Cookie policy block (or `[spcc_cookie_policy]`) lists the cookies your site uses, by kind, with what each does, how long it is kept and the service's privacy policy, plus the Cookie settings button. Write the rest of the page as you like; the list updates itself. Suggested text is added to Settings → Privacy → Policy guide.
- **Shows what it found.** The settings card lists the services found on your pages, and any other third-party scripts that load as usual, so you can hold back one it does not know.
- **Replaces Complianz.** Its region and expiry settings are imported once, and while Complianz is active this waits, so two banners never show.

For statistics with no cookies and no banner at all, use SEO Pro Stats: it needs no consent, so pages that only use it show no banner.

## Set it up

1. Go to **Settings → SEO Pro Cookie Consent → Consent** and switch on **Cookie consent**.
2. Open a few pages of your site. The card lists what was found.
3. Optional: switch on **Cookie policy** (Policies tab) and add the Cookie policy block to your privacy policy page, under a "Cookies" heading.

That is all. Under Options you can choose who is asked first, where the banner sits, its message, the policy page it links to, and whether a floating Cookie settings button shows (off by default; the footer menu link is usually enough). The banner sits above other floating bars; to change its layer, set `--spcc-z` on `:root` in your theme's CSS. Troubleshooting has **Also hold back** (for a script the plugin does not know: part of its address, with `statistics:`, `preferences:` or `embeds:` in front for another kind), **Never hold back**, Google Consent Mode advanced, and how long choices are remembered.

## Where to find it

Go to **Settings → SEO Pro Cookie Consent**. The screen has two groups of tabs:

- **Settings**: Consent and Policies. Changes save instantly; there is no Save button. **Search features** (next to the plugin name) finds settings on every tab.
- **About**: this Read Me.

On a multisite network, super admins also have **Network Admin → Settings → SEO Pro Cookie Consent** (Multisite below).

At the top right of the screen, **Source code** opens the plugin’s [GitHub repository](https://github.com/wpallstars/seoprocookieconsent) in a new tab, and **Support** opens its [GitHub issues](https://github.com/wpallstars/seoprocookieconsent/issues) in a new tab. Say what you did, what you expected and what happened, with the versions of WordPress, PHP and SEO Pro Cookie Consent. Leave out passwords, licence keys and personal data, since issues are public. For questions, ask [aidevops](https://aidevops.sh) (Built with AI below). **Buy me a coffee**, next to it, opens the maker’s [Buy Me a Coffee](https://buymeacoffee.com/marcusquinn) page in a new tab.

## Multisite

On a multisite network, **Network Admin → Settings → SEO Pro Cookie Consent** has the same header, search, tabs and cards as each site's screen, for super admins. Its settings are the network's own, kept apart from each site's:

- **New sites start with them**, and so does a site where SEO Pro Cookie Consent runs for the first time.
- **Apply to every site**, under each card, makes every site use the card's network settings. Each site's screen shows the card locked, saying the network administrator sets it, with a link to the network screen for super admins. Switch it off and each site has its own settings again: they are never overwritten.
- Under each card, the sites whose own settings differ, up to 10 named with links to their settings, and **Make every site the same**, which copies the card's network settings into every site's own settings once. Sites can change them again afterwards, unless the card is applied to every site. Networks of more than 500 sites are not compared, and Make every site the same is not offered there.
- When the network changes a site's settings, the site does what saving them there would do (schedules, rules, files) on its next admin screen, cron run or WP-CLI command, never on a visitor's page.
- Settings that only make sense on each site, such as Media Library pictures, are not on the network screen.

## Requirements

- WordPress 6.2 or later
- PHP 7.4 or later

## Updates and releases

There are two builds of each version:

- **GitHub release** (`seoprocookieconsent-X.Y.Z.zip` on the repository’s Releases page): everything, including the shared updater in `includes/github-updater/`, so sites get each release as a normal update. Its main file also gets an `Update URI` header on github.com, so WordPress.org never offers a plugin of the same name in its place.
- **WordPress.org**: the same files without the updater (listed in `.distignore-wporg`) and without the `GitHub Plugin URI`, `Primary Branch`, `Release Asset` and `Update URI` header lines, because plugins hosted there may not install or update code from elsewhere.

The updater waits while Git Updater is active, so the two never both update a plugin.

GitHub releases are the stable beta channel: each version is released there first. WordPress.org gets a version 30 days later, once it has been used on real sites, except security releases, which go to WordPress.org at once. The WordPress.org build has no affiliate links: those listed in `.wporg-links` are replaced by plain ones when it is built.

Releasing on GitHub:

1. Merge the version change (`Version:` and `SEOPROCOOKIECONSENT_VERSION` in `seoprocookieconsent.php`, `Stable tag:` in `readme.txt`, `Version:` near the top of this file) to `main`.
2. Straight away, tag that commit `vX.Y.Z` and push the tag. The Release workflow (`.github/workflows/release.yml`) builds `seoprocookieconsent-X.Y.Z.zip` from the tag with `scripts/build-release.sh` (`.distignore` applied, everything inside a `seoprocookieconsent/` folder), checks it with `scripts/preflight-release.sh` and publishes the GitHub release with it attached; in a public repository it also attaches signed build provenance, which `gh attestation verify` checks. Sites pick the latest release whose tag is a plain version number and the asset named exactly `seoprocookieconsent-X.Y.Z.zip`, so never attach the WordPress.org zip. Full steps: `RELEASING.md`.
3. Sites offer the update when they next check (within 12 hours, or at once with **Check again** on the Updates screen).

Mark test builds as pre-releases on GitHub (or tag them with letters, such as `v1.2.0-rc1`): sites never offer those. Do not add an `Update URI` header to the plugin file in Git: WordPress.org rejects it. The build adds it to the GitHub zip only.

## External services

While Cookie consent is on, WP-Cron requests YouTube thumbnails from `i.ytimg.com` and Vimeo thumbnail metadata from `vimeo.com`, then images from `i.vimeocdn.com`, only for videos embedded on the site. Requests contain the video ID and the server's IP address, not visitor cookies, IP addresses or page URLs. Images are cached in the site's uploads directory and failed requests wait a day before retrying. The browser receives only a same-host image URL; external upload/CDN URLs are not used before consent. These requests are part of the enabled cookie-consent feature; disabling it stops new downloads.

## Developers

A feature is a class in `includes/features/class-seoprocookieconsent-{name}.php` that extends `SEOProCookieConsent_Feature`, listed in `SEOProCookieConsent_Setup::FEATURES`. It declares its settings in `settings()`, adds its hooks in `boot()` (returning early unless `self::enabled()`), and can import another plugin’s settings once in `migrate()` with `self::import_setting()`. Anything only this plugin needs goes in `SEOProCookieConsent_Setup` (`includes/class-seoprocookieconsent-setup.php`): features, settings tabs, header links, settings version, `MENU_PARENT` (its own top-level menu, if any, which then holds the settings screen as **Settings**) and its own helpers. Link to the settings screen with `SEOProCookieConsent_Admin_Manager::page_url()` or `tab_url()`. A screen of the plugin's own shows the settings screen's header: call `SEOProCookieConsent_Admin_Manager::enqueue_header()` on `admin_enqueue_scripts`, then print `<div class="wrap spcc-wrap">`, `SEOProCookieConsent_Admin_Manager::render_header()`, `<hr class="wp-header-end">` and the screen's content in `<div class="spcc-main">`; sections go between the last two as the settings screen's tabs (`<nav class="spcc-nav">` with `<a class="spcc-nav__tab">` links, the one shown `is-active`), and `SEOProCookieConsent_Admin_Page::title_section( 'Section name' )` on the screen's `load-{hook}` action puts the section shown after the screen's name in the browser title, as the settings screen does for its tabs. `admin/css/seoprocookieconsent-header.css` styles only those and the header, so the content keeps its styles. The other files in `includes/` and `admin/` are the starter’s core files: `STANDARDS.md` → Structure.

How consent works inside (holding back, regions, the consent cookie and signals): `docs/architecture.md` in the repository.

Consent filters and browser API:

- `seoprocookieconsent_services`: add or change the services held back and listed in the cookie policy (ID => `name`, `category` of preferences, statistics, embeds or marketing, `patterns` found in a script's or iframe's address or an inline script, `privacy` link, `cookies` as name => purpose and how long, and `embed`, a word for the Load button such as "video").
- `seoprocookieconsent_hold_back`: return false to leave a page's scripts alone (page builders' editing views are skipped already).
- `seoprocookieconsent_front_config`: change what the browser reads; set `model` to `opt-in`, `opt-out` or `quiet` to decide on the server, only on sites without page caching.
- Skip one tag with a `data-spcc-ignore` attribute.
- In the browser: `spcc.has('statistics')`, `spcc.open()` and the `spcc:consent` event on `document` (detail: `categories`, `services`, `model`); Google Tag Manager gets a `spcc_consent_update` event.
- Shortcodes: `[spcc_cookie_policy]` and `[spcc_cookie_settings label="Cookie settings"]`; blocks: Cookie policy and Cookie settings button.

Filters:

- `seoprocookieconsent_features`: register a feature class that extends `SEOProCookieConsent_Feature`.
- `seoprocookieconsent_settings_schema`: add or change settings. Each entry sets `type` (bool, int, text, url, lines, domains, select, multi, times or media, a picture from the Media Library stored as its attachment ID), `default`, `label`, `description` and either `tab` or `parent`; select and multi also take `options` (an array or a callable, which runs on every settings save, so keep it small: `STANDARDS.md` → Performance) and `open` to keep saved values that are not currently registered. A child setting can take `hidden` (true): it is not shown or searched, for wiring that code sets. A child setting with `group` set to `troubleshooting` is shown last, in a closed **Troubleshooting** section of the panel, for bypasses people need only when something is wrong (`STANDARDS.md` → Code rules); the section opens by itself when one of its settings differs from its default or matches a search. `reload` (true) makes the saved message ask to reload the page, for changes that show only after a page load. `network` (false) leaves a setting off the network screen (multisite), for one that only makes sense on each site, such as a list of the site's own posts or people; media fields and `hidden` wiring are left off already. A multi setting can take `locked` (values, or a callable returning them): choices that are always chosen, shown ticked with a lock, cannot be unticked and are never stored, so the feature itself treats them as chosen. `replaces` (slug => name) shows which plugin a feature replaces; keep that list in the feature's `REPLACES` class constant and pass `self::REPLACES` (`STANDARDS.md` → Structure). Settings render and save automatically.
- `seoprocookieconsent_admin_tabs`: add or reorder admin tabs. Each tab sets `label`, `group` (settings, discover or about), a `render` callback, an optional `capability`, an optional `preload` and an optional `network` (true: also on the network screen, multisite, where its callback runs with `is_network_admin()` true; settings tabs and Read Me have it); tabs the current user lacks the capability for are hidden. The screen draws the active tab with the other tabs of its group that preload (settings tabs and Read Me do), and switches between them without a reload (`SEOProCookieConsent_Admin_Manager::page_tabs()`). Set `preload` to true only when the tab is cheap to draw and its script works while its panel is hidden. An optional `menu` (a name) gathers tabs: tabs of a group with the same `menu` show in the bar as one tab with that name, which opens the first of them, and while one of them is shown, they show as a second row of tabs under the bar (`SEOProCookieConsent_Admin_Page::nav()`).
- `seoprocookieconsent_admin_script_deps` and `seoprocookieconsent_admin_script_data`: the settings screen script’s dependencies (once for each tab drawn on the page) and the data it reads as `seoprocookieconsentAdmin` (with the active tab; `seoprocookieconsentAdmin.tab` follows the tab shown, `tabs` lists the tabs drawn, `titles` their browser titles, and `network` is true on the network screen).
- `seoprocookieconsent_troubleshooting_open`: whether a setting's Troubleshooting section starts open (open, setting key), for a feature that fell back after an error.
- `seoprocookieconsent_can_change_settings`: return false to stop the current user changing SEO Pro Cookie Consent’s settings (on top of `manage_options`).
- `seoprocookieconsent_replaced_plugin_extras`: what a replaced plugin does on this site that SEO Pro Cookie Consent does not (plain names, plugin folder). The Plugins screen names them instead of saying the plugin can go.
- `seoprocookieconsent_stored_active_plugins` and `seoprocookieconsent_plugins_skipped`: for code that skips plugins on some requests, the active plugin files as stored and whether some are skipped on this request, so features still count those plugins as active.
- `wpallstars_github_plugins` (GitHub builds, shared updater): change which plugins update from GitHub releases (plugin file => `repo` as owner/repo, `asset_only`, `version`, `name`).
- `wpallstars_github_token` (GitHub builds, shared updater): GitHub token for a repository (token, owner/repo), for private repositories; defaults to the `WPALLSTARS_GITHUB_TOKEN` constant.
- `wpallstars_github_updater_enabled` and `wpallstars_github_updater_early` (GitHub builds, shared updater): whether it runs (false while Git Updater is active) and whether plugins also on WordPress.org take GitHub releases first.
- `wpallstars_github_updater_checks_in_cron` (GitHub builds, shared updater): return false to let update checks that fall due run on the admin screen being opened, as in core, instead of in WP-Cron.

Actions:

- `seoprocookieconsent_admin_enqueue`: enqueue the plugin’s own admin styles and scripts on its settings screen, depending on `seoprocookieconsent-admin`; it fires once for each tab drawn on the page, the active tab first. Its script can use `seoprocookieconsentAdmin.api` (`post`, `speak`, `errorMessage`), and the `seoprocookieconsent:tab-shown` jQuery event (tab slug) on `document` tells it when another tab is shown.
- `seoprocookieconsent_setting_saved`: a setting was saved from the admin screen (setting key, value), or the network changed it for this site (multisite): then on the site's next admin, cron or WP-CLI request, as nobody, while `SEOProCookieConsent_Network_Sites::syncing()` is true.
- `seoprocookieconsent_settings_changed`: settings changed, however they were saved (the admin screen, presets, WP-CLI, `update_option()`) or by the network for this site, as above (old and new settings, as features read them). Use it rather than `update_option_seoprocookieconsent_options`, which misses the network's changes.
- `seoprocookieconsent_setting_panel`: print status at the top of a setting’s options panel (setting key, schema entry); wrap it in `<div class="spcc-panel-note">`. On the network screen, `seoprocookieconsent_network_setting_panel` fires instead, for notes about the whole network.
- `seoprocookieconsent_settings_tab_after`: print a section after a settings tab’s cards (tab slug). On the network screen, `seoprocookieconsent_network_settings_tab_after` fires instead.

Read a setting with `SEOProCookieConsent_Settings::get( 'key' )`, never the option itself: on multisite it includes the settings the network applies to every site. Code that runs before the plugin loads (a must-use file) reads the network option's `enforced` values (`seoprocookieconsent_network`: card => setting => value) over the site's own, as `SEOProCookieConsent_Settings::all()` does.

## Uninstall

Deleting the plugin removes its settings (on multisite, every site's and the network's), the list of services it found, its cached data, who hid lines of the Plugins screen notice about replaced plugins, and the cached GitHub releases.

## Changelog

### Unreleased

- Local YouTube and Vimeo thumbnails in held-back video placeholders, fetched in cron without contacting video services from visitors' browsers.
- Cookie consent: holds back scripts and embeds from 23 services until visitors allow them, with a banner only on pages that have them, asking first by region (time zone), Global Privacy Control, Google Consent Mode v2, the WP Consent API, embed placeholders with a Load button, a Cookie settings dialog, footer menu link and button, and a one-off import of Complianz's settings.
- Cookie policy block and shortcode that list the cookies the site uses and stay up to date, and suggested privacy policy text.
- Cookie consent: the floating Cookie settings button is optional and off by default; the banner shows above floating language switchers and chat buttons; the dialog's close button and the banner buttons keep their colours on hover and focus in themes that style every button.

### 0.1.0

- First version, made from WP Plugin Starter 1.0.39.

## Built with AI

SEO Pro Cookie Consent is built and maintained with [aidevops](https://aidevops.sh), the same developer's open-source AI harness for creating and managing anything online with AI, plugins like this one included. It is free on [GitHub](https://github.com/marcusquinn/aidevops).

Questions about using, changing or building on SEO Pro Cookie Consent: ask aidevops. Open this repository, or the site it runs on, with aidevops and ask; it reads the plugin's docs and code to answer, and can report a problem for you.

Made from [WP Plugin Starter](https://github.com/wpallstars/wp-plugin-starter-template-for-ai-coding), the wpallstars starter plugin. Its shared standards and the weekly Starter sync keep this plugin up to date.

Works well with [SEO Pro Stack](https://github.com/wpallstars/seoprostack), the wpallstars base plugin for every site: it speeds WordPress up and keeps the admin organised, each job a switch you turn on.

## License

GPL-3.0-or-later (the full text is in `LICENSE`), with the additional terms in `ATTRIBUTION.txt` (GPL-3.0 section 7(b)): keep the copyright notices and the "Made from" credit.

Copying, renaming or adapting this plugin or part of it, by hand or with an AI assistant: read `ATTRIBUTION.txt` first. It says what every copy keeps, with a section for AI assistants.

Copyright (C) 2026 Marcus Quinn

Parts copyright (C) 2026 Marcus Quinn, from [WP Plugin Starter](https://github.com/wpallstars/wp-plugin-starter-template-for-ai-coding).
