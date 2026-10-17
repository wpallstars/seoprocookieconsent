<?php
/**
 * WPConsent (wpconsent-cookies-banner-privacy-suite) data formats, checked
 * against version 1.2.0. Its Pro features (geolocation, custom scripts,
 * consent logs) are reported, not guessed.
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

final class SEOProCookieConsent_Migrate_WPConsent extends SEOProCookieConsent_Migrate_Source {
    const KEY = 'wpconsent';
    const NAME = 'WPConsent';
    const SLUGS = array('wpconsent-cookies-banner-privacy-suite');

    /** The shipped English message, which says using the site is consent: never imported. */
    const STOCK_MESSAGE = 'We use cookies to improve your experience on our site. By using our site, you consent to cookies.';

    /** Shortcodes in pages: name => kind. */
    const SHORTCODES = array('wpconsent_cookie_policy' => 'cookie', 'wpconsent_preferences_button' => 'settings');

    /** @return array */
    private function options() {
        $options = get_option('wpconsent_settings', array());
        return is_array($options) ? $options : array();
    }

    /** @param string $key Setting. @param mixed $default Default. @return mixed */
    private function option($key, $default) {
        $options = $this->options();
        return array_key_exists($key, $options) ? $options[$key] : $default;
    }

    /** Its banner asked first ("opt-in", the default) and was switched on. @return bool */
    private function asked() {
        return $this->options() && !empty($this->option('enable_consent_banner', 0)) && empty($this->option('default_allow', 0));
    }

    /** @return array */
    public function detect() {
        $left = $this->leftovers();
        $notes = array();
        if ($this->options() && empty($this->option('enable_consent_banner', 0))) {
            $notes[] = __('The WPConsent banner is switched off, so visitors\' choices are not carried over.', 'seoprocookieconsent');
        }
        if (!empty($this->option('manual_toggle_services', 0))) {
            $notes[] = __('WPConsent let visitors choose single services. Only its Statistics and Marketing choices carry over; anyone who chose only some services in a category is asked again.', 'seoprocookieconsent');
        }
        $notes[] = __('WPConsent Pro geolocation rules and custom scripts are not imported: check Who is asked first, and add any custom scripts to your theme or a header-code plugin before switching.', 'seoprocookieconsent');
        if (!empty($this->settings()['consent_records'])) {
            $notes[] = __('The import turns on Keep consent records to continue its records.', 'seoprocookieconsent');
        }
        return array('present' => (bool) array_filter($left) || (bool) $this->pages(), 'state' => $this->state(), 'notes' => $notes);
    }

    /** @return array */
    public function settings() {
        $result = array();
        global $wpdb;
        $table = $wpdb->prefix . 'wpconsent_consent_logs';
        if (in_array($table, $this->tables(), true)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only existence check, excluding Do Not Track requests; never reads record contents.
            if ($wpdb->get_var($wpdb->prepare('SELECT 1 FROM %i LIMIT 1', $table))) {
                $result['consent_records'] = true;
            }
        }
        if (!$this->options()) {
            return $result;
        }
        if ($this->asked()) {
            $result['consent_regions'] = 'everywhere';
        }
        $days = (int) $this->option('consent_duration', 30);
        if ($days > 0) {
            $result['consent_days'] = $days;
        }
        $layout = (string) $this->option('banner_layout', 'long');
        $position = (string) $this->option('banner_position', 'top');
        if ('long' === $layout && 'bottom' === $position) {
            $result['consent_position'] = 'bar';
        } elseif ('floating' === $layout && in_array($position, array('left-bottom', 'right-bottom'), true)) {
            $result['consent_position'] = 'left-bottom' === $position ? 'left' : 'right';
        }
        $text = (string) $this->option('banner_message', '');
        $message = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags(str_replace('{cookie_policy}', '', $text)), ENT_QUOTES, 'UTF-8')));
        if ('' !== $message && self::STOCK_MESSAGE !== $message) {
            $result['consent_message'] = $message;
        }
        $page = (int) $this->option('cookie_policy_page', 0);
        if ($page > 0 && 'publish' === get_post_status($page)) {
            $result['consent_policy_url'] = (string) get_permalink($page);
        }
        return $result;
    }

    /** It blocks its own service list, which this plugin recognises; Pro custom scripts are not read. @return array */
    public function scripts() {
        return array('consent_block' => array(), 'consent_allow' => array());
    }

    /** @return array */
    public function pages() {
        $needles = array('[wpconsent_cookie_policy', '[wpconsent_preferences_button', 'wpconsent-open-preferences');
        $regex = '/\[(?:wpconsent_cookie_policy|wpconsent_preferences_button)\b[^\]]*\]|<a\b[^>]*\bwpconsent-open-preferences\b[^>]*>.*?<\/a>/is';
        $pages = array();
        foreach ($this->find_pages($needles, $regex) as $page) {
            $tokens = array();
            $cookie = false;
            foreach ($page['matches'] as $token) {
                // A link with its class opened its dialog: listed, since it is the owner's own markup.
                $kind = preg_match('/^\[([a-z_]+)/i', $token, $name) ? (self::SHORTCODES[strtolower($name[1])] ?? 'other') : 'other';
                $entry = array('token' => $token, 'cookie' => 'cookie' === $kind, 'renderable' => 'other' !== $kind, 'kind' => $kind);
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
     * One JSON cookie of true/false per slug, written only by Accept all,
     * Reject all, Save preferences, a placeholder's accept button or the
     * Global Privacy Control signal; closing the banner writes nothing.
     * WPConsent files embeds under Marketing. It has no preferences
     * category, so that follows Accept all.
     *
     * @return array
     */
    public function consent_cookie() {
        if (!$this->asked()) {
            return array();
        }
        $map = array('preferences' => array('statistics', 'marketing'), 'statistics' => 'statistics', 'embeds' => 'marketing', 'marketing' => 'marketing');
        return array('json' => 'wpconsent_preferences', 'status' => 'essential', 'value' => 'true', 'categories' => $map, 'allow' => 'true', 'deny' => 'false');
    }

    /** @return string[] */
    private function tables() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- SHOW only; escapes the site's prefix literally.
        return $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'wpconsent_') . '%'));
    }

    /** Exact names only. Its consent logs and Do Not Track requests are listed, never removed. @return array */
    public function leftovers() {
        global $wpdb;
        $options = array();
        foreach (array('wpconsent_', '_transient_wpconsent_', '_transient_timeout_wpconsent_', '_site_transient_wpconsent_', '_site_transient_timeout_wpconsent_') as $prefix) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- indexed, source-owned option prefixes; names only, never licence values.
            $options = array_merge($options, $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT 1000", $wpdb->esc_like($prefix) . '%')));
        }
        $hooks = array();
        foreach (_get_cron_array() ?: array() as $events) {
            foreach (array_keys($events) as $hook) {
                if (0 === strpos($hook, 'wpconsent_')) {
                    $hooks[] = $hook;
                }
            }
        }
        $tables = $this->tables();
        $records = array_values(array_filter($tables, function ($table) use ($wpdb) {
            return in_array($table, array($wpdb->prefix . 'wpconsent_consent_logs', $wpdb->prefix . 'wpconsent_dnt_requests'), true);
        }));
        return array('options' => array_values(array_unique($options)), 'tables' => array_values(array_diff($tables, $records)), 'cron' => array_values(array_unique($hooks)), 'kept' => $records);
    }

    /** @return string */
    public function uninstall_setting() {
        return __('WPConsent removes its cookie list and settings when deleted only if its own delete-data option is on; Remove leftover data deletes its settings and scan tables but not that cookie list. Imports never change its settings.', 'seoprocookieconsent');
    }
}
