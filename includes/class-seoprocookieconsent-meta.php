<?php
/**
 * Meta's own plugins (Meta Pixel for WordPress, Facebook for WooCommerce)
 * hold their pixel, their server events (Conversions API) and their
 * _fbp/_fbc cookies while their state cookie says "held". While Cookie
 * consent is on, the browser keeps that cookie in step with Meta Pixel
 * (spcc.syncMeta() in spcc-head.js), and the server treats a request
 * without it as held (docs/architecture.md → Signals to other code).
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

    /** Meta's state cookie, shared by both plugins: "active" or "held". */
    const COOKIE = 'wc_facebook_signals_state';

    /** Cookies Meta's server code sets for the pixel. */
    const COOKIES = array('_fbp', '_fbc');

    /**
     * Whether one of Meta's plugins with the state cookie is active.
     *
     * @return bool
     */
    public static function active() {
        return class_exists('FacebookPixelPlugin\Core\Signals') || class_exists('WooCommerce\Facebook\Signals');
    }

    /**
     * Hook in while Cookie consent is on (SEOProCookieConsent_Consent::boot()).
     */
    public static function boot() {
        if (!self::active()) {
            return;
        }
        add_filter('facebook_signals_held', array(__CLASS__, 'held'));
        add_filter('seoprocookieconsent_front_config', array(__CLASS__, 'config'));
        self::drop_cookies();
    }

    /**
     * Meta Pixel for WordPress: held unless Meta's state cookie says
     * "active", so a request without it (a first page, a crawler, a
     * browser without JavaScript) sends no server events.
     *
     * @param mixed $held Whether Meta's plugin holds signals already.
     * @return bool
     */
    public static function held($held) {
        return (bool) $held || !self::released();
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
     * Whether the visitor's browser released Meta's signals.
     *
     * @return bool
     */
    private static function released() {
        return isset($_COOKIE[self::COOKIE]) && 'active' === sanitize_key(wp_unslash($_COOKIE[self::COOKIE]));
    }

    /**
     * Take back the _fbp and _fbc cookies Meta's plugin set on a held
     * request before this plugin loaded: it sets them when its own file
     * loads, from PHP.
     */
    private static function drop_cookies() {
        if (self::released() || headers_sent()) {
            return;
        }
        $keep    = array();
        $dropped = false;
        foreach (headers_list() as $header) {
            if (0 !== stripos($header, 'set-cookie:')) {
                continue;
            }
            if (preg_match('/^set-cookie:\s*(' . implode('|', self::COOKIES) . ')=/i', $header)) {
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
