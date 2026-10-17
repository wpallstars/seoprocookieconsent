=== SEO Pro Cookie Consent ===
Contributors: wpallstars
Donate link: https://buymeacoffee.com/marcusquinn
Tags: cookie consent, gdpr, cookie banner, consent mode, privacy
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Simple, light cookie consent: blocks scripts until consent, asks only where the law and the page need it, and fits your theme.

== Description ==

Free cookie consent you switch on rather than set up. It finds the scripts and embeds on your pages that set cookies, holds them back until visitors allow them, and shows a short banner that matches your theme, only on pages that have them and only as strictly as each visitor's region needs.

* **Holds back** Google Analytics, Tag Manager and Ads, Meta, TikTok, LinkedIn, Pinterest, Microsoft, Hotjar, HubSpot and X tags, YouTube, Vimeo, Google Maps, Spotify, SoundCloud, Instagram and Facebook embeds, and live chats. Nothing to scan.
* **Asks only where needed**: Europe (EU, EEA, UK, Switzerland), Quebec and unclear locations are asked first; elsewhere a short notice, or none. Decided from the browser's time zone, so nothing is looked up and page caches keep working.
* **A fair banner**: Accept all and Reject all look the same, Settings has one switch per kind of cookie, and the message names what is on the page. Preview it on your home page from the settings screen.
* **Load buttons** on held-back videos, maps and posts, with locally cached YouTube and Vimeo thumbnails.
* **Lightboxes too**: recognised script and iframe addresses added through common DOM APIs wait for consent. Exclude the consent head script from script-delay optimizers; inline-only code and unusual insertion APIs need integration.
* **Fits your theme**: Kadence light and dark palettes or block theme colours, mobile friendly, keyboard and screen reader friendly.
* **Cookie settings** link in the footer menu, a block or shortcode, or an optional floating button; taking consent back removes cookies and stops scripts.
* **Global Privacy Control**, **Google Consent Mode v2** and the **WP Consent API**.
* **Optional consent records**: anonymous choice history on your site, administrator CSV export and automatic deletion after the consent lifetime. Off by default; no IP addresses stored.
* **Cookie policy block** that lists your site's cookies and keeps itself up to date, with settings for what it shows, plus suggested privacy policy text.
* **Replaces Complianz, CookieYes, Cookie Notice, CookieAdmin, GDPR Cookie Compliance, WPConsent, Real Cookie Banner, Cookiebot, iubenda, Termly, Borlabs Cookie and Ultimate GDPR & CCPA**: the Import tab previews, then imports their settings, script rules, policy pages and, where their format is verified, visitors' explicit choices, with undo and leftover cleanup.
* **Terms and conditions** block and [spcc_terms] shortcode from a few answers, or your own plain-text wording; off by default. Imports matching Complianz Terms & Conditions identity/contact answers once.

It is a tool, not legal advice: check the defaults and your policy text with whoever advises you.

= Built with AI =

SEO Pro Cookie Consent is built and maintained with aidevops (https://aidevops.sh), the same developer's open-source AI harness for creating and managing anything online with AI.

Questions about using or changing SEO Pro Cookie Consent: ask aidevops. It reads the plugin's docs and code to answer, and can report a problem for you.

Made from WP Plugin Starter (https://github.com/wpallstars/wp-plugin-starter-template-for-ai-coding), the wpallstars starter plugin.

== Installation ==

1. Upload the `seoprocookieconsent` folder to `/wp-content/plugins/`, or install the zip from Plugins → Add New → Upload Plugin.
2. Activate it, then open Settings → SEO Pro Cookie Consent and switch on Cookie consent.
3. Choose Scan now and check Found on your pages. It samples up to eight public pages without running scripts; if loopbacks are blocked, open pages yourself instead. JavaScript-only services are not discovered by the scan.
4. Switch on Cookie policy on the Policies tab and add its block to your privacy or cookie policy page.

== Frequently Asked Questions ==

= Does it do anything on its own? =

Not until you switch on Cookie consent, so nothing changes on a live site before you look.

= Does it work on multilingual sites? =

With WPML String Translation or Polylang, translate consent_message and consent_policy_url in the SEO Pro Cookie Consent string group after opening admin with consent enabled. Published policy-page translations are linked automatically. TranslatePress uses its string API for custom banner text and translates ordinary page output. Default labels need available WordPress/plugin translations. Clear language page caches after changes; without a multilingual plugin the original text and links stay unchanged.

= How does it know where visitors are? =

From the time zone their browser reports. Nothing is looked up or sent anywhere, and cached pages work. When the time zone is unclear (UTC, or none), visitors are asked first.

= How do I add terms and conditions? =

Under Policies, fill in Terms and conditions, switch it on and add its block or [spcc_terms] to a page. Suggested wording needs your business name and contact details. Optional answers cover country, offerings and returns; Your own wording replaces the suggestions with plain text. Review before publishing: it is a starting point, not legal advice or country-specific compliance. No checkout acceptance or withdrawal form is provided. Old Complianz pages change only when you select them on the Import tab; otherwise replace their block or shortcode after review and deactivation. Clear page caches after changing answers.

= How do I move from another cookie plugin? =

Complianz, CookieYes, Cookie Notice, CookieAdmin, GDPR Cookie Compliance, WPConsent, Real Cookie Banner, Cookiebot, iubenda, Termly, Borlabs Cookie and Ultimate GDPR & CCPA: open the Import tab and review its dry run. Import fills only default settings, adds missing script rules and converts only the pages you select, with a revision and undo. Visitors' clear choices carry over; anyone else is asked again, as are all visitors of hosted (web app) modes, Borlabs Cookie and Ultimate GDPR & CCPA. Scripts the old plugin ran itself stop when it is gone: the Import tab lists them to copy to your theme, where they are held back. Pro-only features are reported, not imported. Then deactivate and delete the old plugin, remove its leftover data (consent records are kept) and purge page caches. The Plugins and settings screens show each next step. WP-CLI: wp seoprocookieconsent migrate. See README.md.

= A script that sets cookies still loads =

Add part of its address under Troubleshooting → Also hold back. The settings card lists other third-party scripts it saw.

= Where do I get help? =

Ask aidevops (https://aidevops.sh): open the plugin's repository, or your site, with it and ask. To report a problem, use the Support link on the settings screen.

= Does it contact other services? =

With Cookie consent on, the server fetches YouTube and Vimeo thumbnails in WP-Cron (External services below). The browser loads only the local copy before consent. Other held-back services load according to the visitor's region and choices.

= Can I keep consent records? =

Enable Keep consent records under Consent, then reload settings for Export consent records (CSV). Each choice sends a best-effort request to your own site; ordinary page views send none. Records include an anonymous cookie ID, choices, times and your policy version label, not IP addresses or browser details. They are self-reported, not authenticated proof. Keep policy text separately, clear page caches after settings changes, and disclose records in your privacy policy. WP-Cron removes expired records in batches even with recording off; uninstall removes them all. See README.md for limits.

= When do versions reach WordPress.org? =

GitHub releases are the stable beta channel: each version comes out there first. WordPress.org gets it 30 days later, except security releases, which come out on both at once.

== External services ==

With Cookie consent on, WP-Cron fetches thumbnails for embedded videos: YouTube images from i.ytimg.com; Vimeo metadata from vimeo.com and images from i.vimeocdn.com. Requests send the video ID and server IP, not visitor data. Images stay in local uploads; failures retry after a day. Disabling Cookie consent stops new downloads. Unavailable thumbnails leave a plain placeholder. YouTube privacy: https://policies.google.com/privacy ; Vimeo privacy: https://vimeo.com/privacy

== Screenshots ==

1. The settings screen (Settings → SEO Pro Cookie Consent) on the General tab, empty until features add settings.
2. The Read Me tab, showing the plugin's README.md inside WordPress.

== Changelog ==

= Unreleased =
* A video or map a visitor clicks that needs a category they have not allowed (such as a YouTube lightbox) now opens Cookie settings saying what it needs, instead of staying blank.

= 0.1.0 =
* Import tab and WP-CLI command for moving from the 12 consent plugins above: dry run, banner settings, script rules, shortcodes in selected policy pages, visitors' explicit choices, undo and exact leftover cleanup that keeps consent records.
* Move here notices on the Plugins and settings screens and Cookie consent card: the next step, import to cleanup.
* Multilingual custom banner messages and policy links with WPML/Polylang; TranslatePress custom-text translation.
* Optional consent records, CSV export and automatic retention; off by default.
* Scan now and a first-run guide on the Consent card, with progress and blocked-loopback feedback.
* Hold back recognised scripts and iframes added after page load, including lightboxes.
* Local YouTube and Vimeo thumbnails, downloaded by WP-Cron without pre-consent browser requests to those services.
* Cookie consent, with a cookie policy block that keeps itself up to date.
* Optional terms and conditions block and shortcode, editable wording and a one-time identity/contact import from Complianz Terms & Conditions.
* First version, made from WP Plugin Starter 1.0.39.

Every change: changelog.txt.
