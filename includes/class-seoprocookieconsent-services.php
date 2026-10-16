<?php
/**
 * Consent categories, the services SEO Pro Cookie Consent recognises, and
 * what this site was seen using (docs/architecture.md).
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: 2026 Marcus Quinn
 * Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
 * Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
 *
 * @package SEOProCookieConsent
 * @since 0.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SEOProCookieConsent_Services {

    /** Option with the services and hosts seen on the site's pages. */
    const SEEN_OPTION = 'seoprocookieconsent_seen';

    /** Most unrecognised third-party hosts remembered. */
    const MAX_HOSTS = 20;

    /** Resolved services. @var array<string,array>|null */
    private static $services = null;

    /**
     * Consent categories, in the order they are shown. Necessary is always
     * on and never held back.
     *
     * @return array<string,array{label:string,description:string}>
     */
    public static function categories() {
        return array(
            'necessary'   => array(
                'label'       => __('Necessary', 'seoprocookieconsent'),
                'description' => __('Needed for the site to work, such as remembering your cookie choices. Always on.', 'seoprocookieconsent'),
            ),
            'preferences' => array(
                'label'       => __('Preferences', 'seoprocookieconsent'),
                'description' => __('Remember your choices and load extras such as live chat.', 'seoprocookieconsent'),
            ),
            'statistics'  => array(
                'label'       => __('Statistics', 'seoprocookieconsent'),
                'description' => __('Help us see how the site is used, so we can improve it.', 'seoprocookieconsent'),
            ),
            'embeds'      => array(
                'label'       => __('Embedded content', 'seoprocookieconsent'),
                'description' => __('Videos, maps and posts from other sites, which may track you there.', 'seoprocookieconsent'),
            ),
            'marketing'   => array(
                'label'       => __('Marketing', 'seoprocookieconsent'),
                'description' => __('Measure and personalise ads, on this site and others.', 'seoprocookieconsent'),
            ),
        );
    }

    /**
     * Categories a visitor can turn on or off.
     *
     * @return string[]
     */
    public static function optional_categories() {
        return array_values(array_diff(array_keys(self::categories()), array('necessary')));
    }

    /**
     * Known services, keyed by ID. More specific services come first:
     * the first whose pattern matches wins.
     *
     * Each has `name`, `category`, `patterns` (lower-case text found in a
     * script's address, an inline script or an iframe's address),
     * `privacy` (its privacy policy) and `cookies` (name => array of
     * purpose and how long it is kept), and optionally `embed` (a word
     * for its iframes in the placeholder, such as "video").
     *
     * @return array<string,array>
     */
    public static function all() {
        if (null !== self::$services) {
            return self::$services;
        }
        $services = self::builtin();

        /**
         * Filter the services SEO Pro Cookie Consent holds back and lists in
         * the cookie policy. Add one with a unique ID and the keys above;
         * `category` must be one of SEOProCookieConsent_Services::optional_categories().
         *
         * @param array<string,array> $services Services keyed by ID.
         */
        $services = (array) apply_filters('seoprocookieconsent_services', $services);

        $categories     = self::optional_categories();
        self::$services = array();
        foreach ($services as $id => $service) {
            if (!is_array($service) || empty($service['name']) || empty($service['patterns'])
                || !isset($service['category']) || !in_array($service['category'], $categories, true)) {
                continue;
            }
            $service['patterns']         = array_values(array_filter(array_map('strtolower', array_map('strval', (array) $service['patterns']))));
            $service['cookies']          = isset($service['cookies']) ? (array) $service['cookies'] : array();
            $service['privacy']          = isset($service['privacy']) ? (string) $service['privacy'] : '';
            self::$services[(string) $id] = $service;
        }
        return self::$services;
    }

    /**
     * One service, or null.
     *
     * @param string $id Service ID.
     * @return array|null
     */
    public static function get($id) {
        $all = self::all();
        return isset($all[$id]) ? $all[$id] : null;
    }

    /**
     * The built-in services. Cookie lists are the usual ones each service
     * sets; services change them, so the policy says "such as".
     *
     * @return array<string,array>
     */
    private static function builtin() {
        $google = 'https://policies.google.com/privacy';
        return array(
            // Marketing first: Google Ads also loads through gtag/js.
            'google-ads'      => array(
                'name'     => 'Google Ads',
                'category' => 'marketing',
                'patterns' => array('googleadservices.com', 'googlesyndication.com', 'doubleclick.net', 'gtag/js?id=aw-', "'config', 'aw-", '"config", "aw-', 'adsbygoogle'),
                'privacy'  => $google,
                'cookies'  => array(
                    '_gcl_au' => array(__('Measures ad conversions', 'seoprocookieconsent'), __('3 months', 'seoprocookieconsent')),
                    'IDE'     => array(__('Shows and measures ads', 'seoprocookieconsent'), __('13 months', 'seoprocookieconsent')),
                ),
            ),
            'google-tag-manager' => array(
                'name'     => 'Google Tag Manager',
                'category' => 'statistics',
                'patterns' => array('googletagmanager.com/gtm.js', 'gtm.start', 'googletagmanager.com/ns.html'),
                'privacy'  => $google,
                'cookies'  => array(),
            ),
            'google-analytics' => array(
                'name'     => 'Google Analytics',
                'category' => 'statistics',
                'patterns' => array('googletagmanager.com/gtag/js', 'google-analytics.com/analytics.js', 'google-analytics.com/ga.js', "gtag('config'", 'gtag("config"', '__gatracker', "ga('create'", 'ga("create"'),
                'privacy'  => $google,
                'cookies'  => array(
                    '_ga'   => array(__('Tells visitors apart', 'seoprocookieconsent'), __('2 years', 'seoprocookieconsent')),
                    '_ga_*' => array(__('Keeps the visit together', 'seoprocookieconsent'), __('2 years', 'seoprocookieconsent')),
                ),
            ),
            'microsoft-clarity' => array(
                'name'     => 'Microsoft Clarity',
                'category' => 'statistics',
                'patterns' => array('clarity.ms/tag'),
                'privacy'  => 'https://privacy.microsoft.com/privacystatement',
                'cookies'  => array(
                    '_clck' => array(__('Tells visitors apart', 'seoprocookieconsent'), __('1 year', 'seoprocookieconsent')),
                    '_clsk' => array(__('Keeps the visit together', 'seoprocookieconsent'), __('1 day', 'seoprocookieconsent')),
                ),
            ),
            'hotjar'          => array(
                'name'     => 'Hotjar',
                'category' => 'statistics',
                'patterns' => array('static.hotjar.com', 'hotjar.com/c/hotjar'),
                'privacy'  => 'https://www.hotjar.com/legal/policies/privacy/',
                'cookies'  => array(
                    '_hjSessionUser_*' => array(__('Tells visitors apart', 'seoprocookieconsent'), __('1 year', 'seoprocookieconsent')),
                    '_hjSession_*'     => array(__('Keeps the visit together', 'seoprocookieconsent'), __('30 minutes', 'seoprocookieconsent')),
                ),
            ),
            'meta-pixel'      => array(
                'name'     => 'Meta Pixel',
                'category' => 'marketing',
                'patterns' => array('connect.facebook.net/en_us/fbevents.js', 'fbevents.js', "fbq('init'", 'fbq("init"'),
                'privacy'  => 'https://www.facebook.com/privacy/policy/',
                'cookies'  => array(
                    '_fbp' => array(__('Measures and personalises ads', 'seoprocookieconsent'), __('3 months', 'seoprocookieconsent')),
                ),
            ),
            'tiktok-pixel'    => array(
                'name'     => 'TikTok Pixel',
                'category' => 'marketing',
                'patterns' => array('analytics.tiktok.com', 'ttq.load('),
                'privacy'  => 'https://www.tiktok.com/legal/page/global/privacy-policy/en',
                'cookies'  => array(
                    '_ttp' => array(__('Measures and personalises ads', 'seoprocookieconsent'), __('13 months', 'seoprocookieconsent')),
                ),
            ),
            'linkedin-insight' => array(
                'name'     => 'LinkedIn Insight Tag',
                'category' => 'marketing',
                'patterns' => array('snap.licdn.com', '_linkedin_partner_id'),
                'privacy'  => 'https://www.linkedin.com/legal/privacy-policy',
                'cookies'  => array(
                    'li_fat_id' => array(__('Measures ad conversions', 'seoprocookieconsent'), __('30 days', 'seoprocookieconsent')),
                ),
            ),
            'pinterest-tag'   => array(
                'name'     => 'Pinterest Tag',
                'category' => 'marketing',
                'patterns' => array('s.pinimg.com/ct/', "pintrk('load'", 'pintrk("load"'),
                'privacy'  => 'https://policy.pinterest.com/privacy-policy',
                'cookies'  => array(
                    '_pin_unauth' => array(__('Measures ad conversions', 'seoprocookieconsent'), __('1 year', 'seoprocookieconsent')),
                ),
            ),
            'microsoft-ads'   => array(
                'name'     => 'Microsoft Advertising',
                'category' => 'marketing',
                'patterns' => array('bat.bing.com'),
                'privacy'  => 'https://privacy.microsoft.com/privacystatement',
                'cookies'  => array(
                    '_uetvid' => array(__('Measures ad conversions', 'seoprocookieconsent'), __('13 months', 'seoprocookieconsent')),
                    '_uetsid' => array(__('Keeps the visit together', 'seoprocookieconsent'), __('1 day', 'seoprocookieconsent')),
                ),
            ),
            'x-ads'           => array(
                'name'     => 'X (Twitter) Ads',
                'category' => 'marketing',
                'patterns' => array('static.ads-twitter.com', "twq('config'", 'twq("config"'),
                'privacy'  => 'https://x.com/privacy',
                'cookies'  => array(),
            ),
            'hubspot'         => array(
                'name'     => 'HubSpot',
                'category' => 'marketing',
                'patterns' => array('js.hs-scripts.com', 'js.hs-analytics.net', 'js.hsforms.net'),
                'privacy'  => 'https://legal.hubspot.com/privacy-policy',
                'cookies'  => array(
                    'hubspotutk' => array(__('Tells visitors apart', 'seoprocookieconsent'), __('6 months', 'seoprocookieconsent')),
                ),
            ),
            'youtube'         => array(
                'name'     => 'YouTube',
                'category' => 'embeds',
                'embed'    => __('video', 'seoprocookieconsent'),
                'patterns' => array('youtube.com/embed', 'youtube-nocookie.com/embed', 'youtube.com/iframe_api'),
                'privacy'  => $google,
                'cookies'  => array(
                    'VISITOR_INFO1_LIVE' => array(__('Remembers player settings', 'seoprocookieconsent'), __('6 months', 'seoprocookieconsent')),
                    'YSC'                => array(__('Counts views', 'seoprocookieconsent'), __('Session', 'seoprocookieconsent')),
                ),
            ),
            'vimeo'           => array(
                'name'     => 'Vimeo',
                'category' => 'embeds',
                'embed'    => __('video', 'seoprocookieconsent'),
                'patterns' => array('player.vimeo.com'),
                'privacy'  => 'https://vimeo.com/privacy',
                'cookies'  => array(
                    'vuid' => array(__('Counts views', 'seoprocookieconsent'), __('2 years', 'seoprocookieconsent')),
                ),
            ),
            'google-maps'     => array(
                'name'     => 'Google Maps',
                'category' => 'embeds',
                'embed'    => __('map', 'seoprocookieconsent'),
                'patterns' => array('google.com/maps/embed', 'maps.google.', 'maps.googleapis.com'),
                'privacy'  => $google,
                'cookies'  => array(
                    'NID' => array(__('Remembers preferences', 'seoprocookieconsent'), __('6 months', 'seoprocookieconsent')),
                ),
            ),
            'spotify'         => array(
                'name'     => 'Spotify',
                'category' => 'embeds',
                'embed'    => __('player', 'seoprocookieconsent'),
                'patterns' => array('open.spotify.com/embed'),
                'privacy'  => 'https://www.spotify.com/legal/privacy-policy/',
                'cookies'  => array(),
            ),
            'soundcloud'      => array(
                'name'     => 'SoundCloud',
                'category' => 'embeds',
                'embed'    => __('player', 'seoprocookieconsent'),
                'patterns' => array('w.soundcloud.com/player'),
                'privacy'  => 'https://soundcloud.com/pages/privacy',
                'cookies'  => array(),
            ),
            'x-embeds'        => array(
                'name'     => 'X (Twitter)',
                'category' => 'embeds',
                'embed'    => __('post', 'seoprocookieconsent'),
                'patterns' => array('platform.twitter.com', 'platform.x.com'),
                'privacy'  => 'https://x.com/privacy',
                'cookies'  => array(),
            ),
            'instagram'       => array(
                'name'     => 'Instagram',
                'category' => 'embeds',
                'embed'    => __('post', 'seoprocookieconsent'),
                'patterns' => array('instagram.com/embed.js', 'www.instagram.com/p/', 'www.instagram.com/reel/'),
                'privacy'  => 'https://privacycenter.instagram.com/policy',
                'cookies'  => array(),
            ),
            'facebook-embeds' => array(
                'name'     => 'Facebook',
                'category' => 'embeds',
                'embed'    => __('post', 'seoprocookieconsent'),
                'patterns' => array('facebook.com/plugins/', 'connect.facebook.net/'),
                'privacy'  => 'https://www.facebook.com/privacy/policy/',
                'cookies'  => array(),
            ),
            'tawk'            => array(
                'name'     => 'Tawk.to',
                'category' => 'preferences',
                'patterns' => array('embed.tawk.to'),
                'privacy'  => 'https://www.tawk.to/privacy-policy/',
                'cookies'  => array(
                    'TawkConnectionTime' => array(__('Keeps the chat connected', 'seoprocookieconsent'), __('Session', 'seoprocookieconsent')),
                ),
            ),
            'crisp'           => array(
                'name'     => 'Crisp',
                'category' => 'preferences',
                'patterns' => array('client.crisp.chat'),
                'privacy'  => 'https://crisp.chat/en/privacy/',
                'cookies'  => array(
                    'crisp-client/session/*' => array(__('Keeps the chat going', 'seoprocookieconsent'), __('6 months', 'seoprocookieconsent')),
                ),
            ),
            'intercom'        => array(
                'name'     => 'Intercom',
                'category' => 'preferences',
                'patterns' => array('widget.intercom.io', 'js.intercomcdn.com'),
                'privacy'  => 'https://www.intercom.com/legal/privacy',
                'cookies'  => array(
                    'intercom-id-*' => array(__('Keeps the chat going', 'seoprocookieconsent'), __('9 months', 'seoprocookieconsent')),
                ),
            ),
        );
    }

    /**
     * The service whose pattern is in the text, or ''.
     *
     * @param string $text Address or inline script, any case.
     * @return string Service ID.
     */
    public static function match($text) {
        $text = strtolower((string) $text);
        if ('' === $text) {
            return '';
        }
        foreach (self::all() as $id => $service) {
            foreach ($service['patterns'] as $pattern) {
                if (false !== strpos($text, $pattern)) {
                    return $id;
                }
            }
        }
        return '';
    }

    /**
     * What the site was seen using: service ID => first seen, and
     * unrecognised third-party host => first seen.
     *
     * @return array{services:array<string,int>,hosts:array<string,int>,updated:int}
     */
    public static function seen() {
        $seen = get_option(self::SEEN_OPTION, array());
        $seen = is_array($seen) ? $seen : array();
        return array(
            'services' => isset($seen['services']) && is_array($seen['services']) ? $seen['services'] : array(),
            'hosts'    => isset($seen['hosts']) && is_array($seen['hosts']) ? $seen['hosts'] : array(),
            'updated'  => isset($seen['updated']) ? (int) $seen['updated'] : 0,
        );
    }

    /**
     * Remember services and hosts found on a page. Writes only when one
     * is new, so a normal page view writes nothing.
     *
     * @param string[] $services Service IDs.
     * @param string[] $hosts    Unrecognised third-party hosts.
     */
    public static function record(array $services, array $hosts) {
        $seen = self::seen();
        // Store it even when empty, once: a missing option costs a query
        // on every page (no row to autoload).
        $changed = false === get_option(self::SEEN_OPTION, false);
        $now     = time();
        foreach (array_unique($services) as $id) {
            if (!isset($seen['services'][$id]) && null !== self::get($id)) {
                $seen['services'][$id] = $now;
                $changed               = true;
            }
        }
        foreach (array_unique($hosts) as $host) {
            if (count($seen['hosts']) >= self::MAX_HOSTS) {
                break;
            }
            if (!isset($seen['hosts'][$host])) {
                $seen['hosts'][$host] = $now;
                $changed              = true;
            }
        }
        if ($changed) {
            $seen['updated'] = $now;
            // Small, and read on every front-end page: keep it autoloaded.
            update_option(self::SEEN_OPTION, $seen, true);
        }
    }

    /**
     * Forget what was seen (for example after removing a service).
     */
    public static function forget() {
        delete_option(self::SEEN_OPTION);
    }

    /**
     * Seen services grouped by category, in category order.
     *
     * @return array<string,array<string,array>> category => id => service
     */
    public static function seen_by_category() {
        $groups = array_fill_keys(self::optional_categories(), array());
        foreach (array_keys(self::seen()['services']) as $id) {
            $service = self::get($id);
            if (null !== $service) {
                $groups[$service['category']][$id] = $service;
            }
        }
        return array_filter($groups);
    }
}
