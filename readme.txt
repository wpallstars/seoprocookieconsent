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
* **A fair banner**: Accept all and Reject all look the same, Settings has one switch per kind of cookie, and the message names what is on the page.
* **Load buttons** on held-back videos, maps and posts, with locally cached YouTube and Vimeo thumbnails.
* **Fits your theme**: Kadence light and dark palettes or block theme colours, mobile friendly, keyboard and screen reader friendly.
* **Cookie settings** link in the footer menu, a block or shortcode, or an optional floating button; taking consent back removes cookies and stops scripts.
* **Global Privacy Control**, **Google Consent Mode v2** and the **WP Consent API**.
* **Cookie policy block** that lists your site's cookies and keeps itself up to date, plus suggested privacy policy text.
* **Replaces Complianz**: imports its region and expiry settings once.

It is a tool, not legal advice: check the defaults and your policy text with whoever advises you.

= Built with AI =

SEO Pro Cookie Consent is built and maintained with aidevops (https://aidevops.sh), the same developer's open-source AI harness for creating and managing anything online with AI.

Questions about using or changing SEO Pro Cookie Consent: ask aidevops. It reads the plugin's docs and code to answer, and can report a problem for you.

Made from WP Plugin Starter (https://github.com/wpallstars/wp-plugin-starter-template-for-ai-coding), the wpallstars starter plugin.

== Installation ==

1. Upload the `seoprocookieconsent` folder to `/wp-content/plugins/`, or install the zip from Plugins → Add New → Upload Plugin.
2. Activate it, then open Settings → SEO Pro Cookie Consent and switch on Cookie consent.
3. Open a few pages of your site: the settings card lists what it found.

== Frequently Asked Questions ==

= Does it do anything on its own? =

Not until you switch on Cookie consent, so nothing changes on a live site before you look.

= How does it know where visitors are? =

From the time zone their browser reports. Nothing is looked up or sent anywhere, and cached pages work. When the time zone is unclear (UTC, or none), visitors are asked first.

= A script that sets cookies still loads =

Add part of its address under Troubleshooting → Also hold back. The settings card lists other third-party scripts it saw.

= Where do I get help? =

Ask aidevops (https://aidevops.sh): open the plugin's repository, or your site, with it and ask. To report a problem, use the Support link on the settings screen.

= Does it contact other services? =

With Cookie consent on, the server fetches YouTube and Vimeo thumbnails in WP-Cron (External services below). The browser loads only the local copy before consent. Other held-back services load according to the visitor's region and choices.

== External services ==

With Cookie consent on, WP-Cron fetches thumbnails for embedded videos: YouTube images from i.ytimg.com; Vimeo metadata from vimeo.com and images from i.vimeocdn.com. Requests send the video ID and server IP, not visitor data. Images stay in local uploads; failures retry after a day. Disabling Cookie consent stops new downloads. Unavailable thumbnails leave a plain placeholder. YouTube privacy: https://policies.google.com/privacy ; Vimeo privacy: https://vimeo.com/privacy

= When do versions reach WordPress.org? =

GitHub releases are the stable beta channel: each version comes out there first. WordPress.org gets it 30 days later, except security releases, which come out on both at once.

== Screenshots ==

1. The settings screen (Settings → SEO Pro Cookie Consent) on the General tab, empty until features add settings.
2. The Read Me tab, showing the plugin's README.md inside WordPress.

== Changelog ==

= Unreleased =
* Local YouTube and Vimeo thumbnails, downloaded by WP-Cron without pre-consent browser requests to those services.
* Cookie consent, with a cookie policy block that keeps itself up to date.

= 0.1.0 =
* First version, made from WP Plugin Starter 1.0.39.

Every change: changelog.txt.
