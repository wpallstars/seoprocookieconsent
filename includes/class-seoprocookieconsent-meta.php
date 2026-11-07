<?php
/**
 * Meta Pixel for WordPress (Meta's own plugin, 5.2 and later) sets the
 * _fbp/_fbc cookies from the server and sends server events (Conversions
 * API) unless its state cookie says "held". While Cookie consent is on, the
 * browser sets that cookie to "active" only while Meta Pixel is allowed and
 * removes it otherwise (spcc.syncMeta() in spcc-head.js); the server then
 * sends no server events and sets no _fbp/_fbc cookies for a visitor
 * without "active". Meta's "held" state is never used: it changes the page
 * HTML, which page caches would then serve to visitors who allowed Meta
 * Pixel (docs/architecture.md → Signals to other code).
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: 2026 Marcus Quinn
 * Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
 * Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
 *
 * @package SEOProCookieConsent
 * @since 1.0.17
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SEOProCookieConsent_Meta {

    /** Meta's state cookie: "active" or "held". */
    const COOKIE = 'wc_facebook_signals_state';

    /** Cookies Meta's server code sets for the pixel. */
    const COOKIES = array('_fbp', '_fbc');

    /**
     * Whether Meta Pixel for WordPress with the state cookie is active.
     *
     * @return bool
     */
    public static function active() {
        return class_exists('FacebookPixelPlugin\Core\Signals');
    }

    /**
     * Hook in while Cookie consent is on (SEOProCookieConsent_Consent::boot()).
     */
    public static function boot() {
        if (!self::active()) {
            return;
        }
        add_filter('before_conversions_api_event_sent', array(__CLASS__, 'server_events'), 99);
        add_filter('seoprocookieconsent_front_config', array(__CLASS__, 'config'));
        // Never render Meta's held HTML (it varies by visitor); its JS reads
        // the state cookie itself.
        add_filter('facebook_signals_held', '__return_false', 99);
        self::drop_cookies();
        // Meta can set "held" again later in the request: strip it once
        // WordPress sends its headers, even for an "active" visitor.
        add_action('send_headers', array(__CLASS__, 'drop_cookies'), 99);
    }

    /**
     * Send server events only for a visitor who allowed Meta Pixel. A
     * request without "active" (a first page, a crawler, a browser without
     * JavaScript) sends none. Meta's background send carries the visitor's
     * cookies, so it follows the same rule. Scheduled tasks and wp-admin
     * screens (Meta's test events) have no visitor and send as before.
     *
     * @param mixed $events Server events about to be sent.
     * @return mixed
     */
    public static function server_events($events) {
        if (self::released() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
            return $events;
        }
        if (is_admin() && !wp_doing_ajax() && 'admin-post.php' !== ($GLOBALS['pagenow'] ?? '')) {
            return $events;
        }
        return array();
    }

    /**
     * The state cookie's name for the browser, the same for every visitor.
     *
     * @param mixed $config Browser config.
     * @return array
     */
    public static function config($config) {
        $config                = (array) $config;
        $config['metaSignals'] = self::COOKIE;
        return $config;
    }

    /**
     * Whether the visitor's browser says Meta Pixel is allowed.
     *
     * @return bool
     */
    private static function released() {
        return isset($_COOKIE[self::COOKIE]) && 'active' === sanitize_key(wp_unslash($_COOKIE[self::COOKIE]));
    }

    /**
     * Take back the _fbp and _fbc cookies Meta's plugin set for a visitor
     * who has not allowed Meta Pixel: it sets them from PHP when its own
     * file loads, before this plugin loads.
     */
    public static function drop_cookies() {
        if (headers_sent()) {
            return;
        }
        $released = self::released();
        $keep    = array();
        $dropped = false;
        foreach (headers_list() as $header) {
            if (0 !== stripos($header, 'set-cookie:')) {
                continue;
            }
            if (
                (!$released && preg_match('/^set-cookie:\s*(' . implode('|', self::COOKIES) . ')=/i', $header))
                || preg_match('/^set-cookie:\s*' . self::COOKIE . '=held(;|\s|$)/i', $header)
            ) {
                $dropped = true;
                continue;
            }
            $keep[] = $header;
        }
        if (!$dropped) {
            return;
        }
        header_remove('Set-Cookie');
        foreach ($keep as $header) {
            header($header, false);
        }
    }
}
