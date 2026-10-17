<?php
/**
 * CookieYes (cookie-law-info) data formats, checked against CookieYes 3.5.7
 * in its local mode. Settings kept in the CookieYes web app, its CCPA
 * banner and its legacy (version 2) mode are reported, not guessed.
 * No third-party PHP or JavaScript is copied into this adapter.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: 2026 Marcus Quinn
 * Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
 * Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
 *
 * @package SEOProCookieConsent
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SEOProCookieConsent_Migrate_CookieYes extends SEOProCookieConsent_Migrate_Source {
    const KEY = 'cookieyes';
    const NAME = 'CookieYes';
    const SLUGS = array('cookie-law-info');

    /** CookieYes category slug => our category; other custom slugs count as marketing. */
    const CATEGORIES = array('functional' => 'preferences', 'analytics' => 'statistics', 'performance' => 'statistics', 'advertisement' => 'marketing');

    /** The shipped English banner text, which is not worth importing over an automatic message. */
    const STOCK_MESSAGE = 'We use cookies to enhance your browsing experience, serve personalised ads or content, and analyse our traffic. By clicking "Accept All", you consent to our use of cookies.';

    /** Version 2 shortcodes left in pages: name => kind. */
    const SHORTCODES = array(
        'cookie_audit'          => 'cookie',
        'cookie_settings'       => 'settings',
        'wt_cli_manage_consent' => 'settings',
        'cookie_after_accept'   => 'gate',
        'wt_cli_ccpa_optout'    => 'manual',
        'cookie_accept'         => 'other',
        'cookie_accept_all'     => 'other',
        'cookie_reject'         => 'other',
        'cookie_button'         => 'other',
        'cookie_close'          => 'other',
        'cookie_link'           => 'other',
        'delete_cookies'        => 'other',
        'user_consent_state'    => 'other',
        'webtoffee_powered_by'  => 'other',
    );

    /** @return array */
    public function detect() {
        $left = $this->leftovers();
        $notes = array();
        if ($this->connected()) {
            $notes[] = __('This site is connected to the CookieYes web app, which keeps the banner, categories and scripts. Only what is stored on this site is imported, and visitors are asked again.', 'seoprocookieconsent');
        } elseif ($this->legacy()) {
            $notes[] = __('CookieYes is still in its legacy (version 2) mode. Its settings and visitors\' choices are not imported: set the banner up here, and visitors are asked again.', 'seoprocookieconsent');
        } elseif ($this->banner() && 'gdpr' !== $this->law()) {
            $notes[] = __('The CookieYes banner follows CCPA (opt-out), so visitors\' choices are not carried over: they are asked again.', 'seoprocookieconsent');
        }
        $notes[] = __('Scripts you marked for CookieYes in your theme (data-cookieyes, type="text/plain") stay switched off once it is gone: remove those two attributes so this plugin holds them back instead.', 'seoprocookieconsent');
        return array('present' => (bool) array_filter($left) || (bool) $this->pages(), 'state' => $this->state(), 'notes' => $notes);
    }

    /** @return bool */
    private function connected() {
        $settings = get_option('cky_settings', array());
        return is_array($settings) && !empty($settings['account']['connected']);
    }

    /** Mirrors CookieYes's own test: version 2 options and no newer database version. @return bool */
    private function legacy() {
        $version = get_option('cky_cookie_consent_lite_db_version', '2.0');
        return !empty(get_option('CookieLawInfo-0.9', array())) && !version_compare((string) $version, '2.0', '>');
    }

    /** The default local banner, decoded, or an empty array. @return array */
    private function banner() {
        global $wpdb;
        if (!in_array($wpdb->prefix . 'cky_banners', $this->tables(), true)) {
            return array();
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the verified source banner table.
        $row = $wpdb->get_row($wpdb->prepare('SELECT settings, contents FROM %i WHERE status = 1 ORDER BY banner_default DESC, banner_id ASC LIMIT 1', $wpdb->prefix . 'cky_banners'), ARRAY_A);
        if (!is_array($row)) {
            return array();
        }
        $settings = json_decode((string) $row['settings'], true);
        $contents = json_decode((string) $row['contents'], true);
        return array('settings' => is_array($settings) ? $settings : array(), 'contents' => is_array($contents) ? $contents : array());
    }

    /** @return string The banner's law, 'gdpr' or 'ccpa'. */
    private function law() {
        $banner = $this->banner();
        return (string) ($banner['settings']['settings']['applicableLaw'] ?? '');
    }

    /** Local mode only: the web app and legacy mode keep their own settings. @return bool */
    private function local() {
        return !$this->connected() && !$this->legacy();
    }

    /** @return array */
    public function settings() {
        $banner = $this->local() ? $this->banner() : array();
        if (!$banner) {
            return array();
        }
        $settings = (array) ($banner['settings']['settings'] ?? array());
        $result = array();
        // The local banner asks everyone first; CCPA's opt-out notice maps to our default.
        if ('gdpr' === ($settings['applicableLaw'] ?? '')) {
            $result['consent_regions'] = 'everywhere';
        }
        if (!empty($settings['consentExpiry']['status']) && is_numeric($settings['consentExpiry']['value'] ?? null)) {
            $result['consent_days'] = (int) $settings['consentExpiry']['value'];
        }
        $type = (string) ($settings['type'] ?? '');
        $position = (string) ($settings['position'] ?? '');
        if ('box' === $type && in_array($position, array('bottom-left', 'bottom-right'), true)) {
            $result['consent_position'] = 'bottom-left' === $position ? 'left' : 'right';
        } elseif (in_array($type, array('banner', 'classic'), true) && 'bottom' === $position) {
            $result['consent_position'] = 'bar';
        }
        $options = get_option('cky_settings', array());
        $language = is_array($options) && is_string($options['languages']['default'] ?? null) ? $options['languages']['default'] : 'en';
        $notice = (array) ($banner['contents'][$language]['notice']['elements'] ?? array());
        $message = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags((string) ($notice['description'] ?? '')), ENT_QUOTES, 'UTF-8')));
        if ('' !== $message && self::STOCK_MESSAGE !== $message) {
            $result['consent_message'] = $message;
        }
        $link = esc_url_raw((string) ($notice['privacyLink'] ?? ''));
        if ('' !== $link && !empty($banner['settings']['config']['notice']['elements']['buttons']['elements']['readMore']['status'])) {
            $result['consent_policy_url'] = $link;
        }
        return $result;
    }

    /** Category ID => our category, from the source's category table. @return array<int,string> */
    private function categories() {
        global $wpdb;
        if (!in_array($wpdb->prefix . 'cky_cookie_categories', $this->tables(), true)) {
            return array();
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the verified source category table.
        $rows = $wpdb->get_results($wpdb->prepare('SELECT category_id, slug FROM %i ORDER BY category_id ASC LIMIT 100', $wpdb->prefix . 'cky_cookie_categories'), ARRAY_A);
        $result = array();
        foreach ((array) $rows as $row) {
            if ('necessary' !== $row['slug']) {
                $result[(int) $row['category_id']] = self::CATEGORIES[$row['slug']] ?? 'marketing';
            }
        }
        return $result;
    }

    /** Script patterns from its cookie list; never its scan data or descriptions. @return array */
    public function scripts() {
        global $wpdb;
        $result = array('consent_block' => array(), 'consent_allow' => array());
        $categories = $this->local() ? $this->categories() : array();
        if (!$categories || !in_array($wpdb->prefix . 'cky_cookies', $this->tables(), true)) {
            return $result;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the verified source cookie table: patterns only.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT category, url_pattern FROM %i WHERE url_pattern <> '' ORDER BY cookie_id ASC LIMIT 1000", $wpdb->prefix . 'cky_cookies'), ARRAY_A);
        foreach ((array) $rows as $row) {
            $category = $categories[(int) $row['category']] ?? '';
            $rule = '' === $category ? '' : self::script_rule((string) preg_replace('~^https?://~i', '', (string) $row['url_pattern']));
            if (is_string($rule) && '' !== $rule) {
                $result['consent_block'][] = ('marketing' !== $category ? $category . ': ' : '') . $rule;
            }
        }
        $result['consent_block'] = array_values(array_unique($result['consent_block']));
        return $result;
    }

    /** @return array */
    public function pages() {
        $names = implode('|', array_map('preg_quote', array_diff(array_keys(self::SHORTCODES), array('cookie_after_accept'))));
        $needles = array_map(function ($name) {
            return '[' . $name;
        }, array_keys(self::SHORTCODES));
        $pages = array();
        // Only cookie_after_accept encloses content; an unclosed one is listed on its own.
        $regex = '/\[cookie_after_accept\b[^\]]*\].*?\[\/cookie_after_accept\]|\[cookie_after_accept\b[^\]]*\]|\[(?:' . $names . ')\b[^\]]*\]/is';
        foreach ($this->find_pages($needles, $regex) as $page) {
            $tokens = array();
            $cookie = false;
            foreach ($page['matches'] as $token) {
                $kind = preg_match('/^\[([a-z_]+)/i', $token, $name) ? (self::SHORTCODES[strtolower($name[1])] ?? 'other') : 'other';
                $entry = array('token' => $token, 'cookie' => 'cookie' === $kind, 'renderable' => in_array($kind, array('cookie', 'settings'), true), 'kind' => $kind);
                if ('settings' === $kind) {
                    $entry['replace'] = '<!-- wp:seoprocookieconsent/cookie-settings /-->';
                }
                $tokens[] = $entry;
                $cookie = $cookie || $entry['cookie'];
            }
            $pages[] = array('id' => $page['id'], 'title' => $page['title'], 'cookie' => $cookie, 'tokens' => $tokens);
        }
        return $pages;
    }

    /**
     * One cookie of key:value pairs. "action:yes" is set by any choice,
     * including closing the banner, which leaves the starting values; so
     * a choice counts only when it consented ("consent:yes") or denied
     * every category. The local banner's law must be GDPR.
     *
     * @return array
     */
    public function consent_cookie() {
        if (!$this->local() || 'gdpr' !== $this->law()) {
            return array();
        }
        global $wpdb;
        $map = array();
        if (in_array($wpdb->prefix . 'cky_cookie_categories', $this->tables(), true)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the verified source category table.
            foreach ((array) $wpdb->get_col($wpdb->prepare('SELECT slug FROM %i ORDER BY category_id ASC LIMIT 100', $wpdb->prefix . 'cky_cookie_categories')) as $slug) {
                if ('necessary' !== $slug && preg_match('/^[a-z0-9_-]+$/', $slug)) {
                    $map[self::CATEGORIES[$slug] ?? 'marketing'][] = $slug;
                }
            }
        }
        // CookieYes's cookie library files YouTube, Vimeo and similar embeds under Advertisement.
        if (isset($map['marketing']) && in_array('advertisement', $map['marketing'], true)) {
            $map['embeds'] = array('advertisement');
        }
        if (!$map) {
            return array();
        }
        return array('pairs' => 'cookieyes-consent', 'status' => 'action', 'value' => 'yes', 'grant' => 'consent', 'categories' => $map, 'allow' => 'yes', 'deny' => 'no');
    }

    /** @return string[] */
    private function tables() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- SHOW only; escapes the site's prefix literally.
        return $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'cky_') . '%'));
    }

    /** Exact names only. Consent logs live in the web app, so none are kept here. @return array */
    public function leftovers() {
        global $wpdb;
        $options = array();
        foreach (array('cky_', '_transient_cky_', '_transient_timeout_cky_', '_site_transient__cky_', '_site_transient_timeout__cky_', 'wt_cli_', 'cookielawinfo_', 'cookielawinfo-', 'CookieLawInfo-') as $prefix) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- indexed, source-owned option prefixes; names only.
            $options = array_merge($options, $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT 1000", $wpdb->esc_like($prefix) . '%')));
        }
        foreach (array('cli_heading_version', 'cli_pg_content_data', 'cli_script_blocker_status') as $name) {
            if (false !== get_option($name, false)) {
                $options[] = $name;
            }
        }
        $hooks = array();
        foreach (_get_cron_array() ?: array() as $events) {
            foreach (array_keys($events) as $hook) {
                if (0 === strpos($hook, 'cky_') || 0 === strpos($hook, 'wt_cli_')) {
                    $hooks[] = $hook;
                }
            }
        }
        return array('options' => array_values(array_unique($options)), 'tables' => $this->tables(), 'cron' => array_values(array_unique($hooks)), 'kept' => array());
    }

    /** @return string */
    public function uninstall_setting() {
        return __('CookieYes keeps its data when deleted unless CKY_REMOVE_ALL_DATA is set; use Remove leftover data afterwards. Imports never change its settings.', 'seoprocookieconsent');
    }
}
