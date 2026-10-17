<?php
/**
 * CookieAdmin (cookieadmin) data formats, checked against CookieAdmin 1.2.6.
 * Its Pro add-on (consent logs, script blocking, geo targeting) was not
 * available to check, so Pro-only behaviour is reported, not guessed.
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

final class SEOProCookieConsent_Migrate_CookieAdmin extends SEOProCookieConsent_Migrate_Source {
    const KEY = 'cookieadmin';
    const NAME = 'CookieAdmin';
    const SLUGS = array('cookieadmin');

    /** Its dialog's switches => our categories. Unclassified cookies count as marketing. */
    const CATEGORIES = array('functional' => 'preferences', 'analytics' => 'statistics', 'marketing' => 'marketing');

    /** The shipped English GDPR message, tags removed; CookieAdmin stores only changed text. */
    const STOCK_MESSAGE = 'Cookies help us improve your experience, deliver personalized content, and analyze traffic. You can choose which cookies to allow by clicking Customize. Click Accept All to consent or Reject All to decline non-essential cookies.';

    /** @return string The law visitors see: cookieadmin_gdpr, cookieadmin_us or a Pro law. */
    private function law() {
        return (string) get_option('cookieadmin_law', 'cookieadmin_gdpr');
    }

    /** Saved banner settings for the current law. @return array */
    private function policy() {
        $all = get_option('cookieadmin_consent_settings', array());
        $policy = is_array($all) ? ($all[$this->law()] ?? array()) : array();
        return is_array($policy) ? $policy : array();
    }

    /** Settings were saved by an installed copy: without them the law is unknown. @return bool */
    private function known() {
        return false !== get_option('cookieadmin_version', false);
    }

    /** @return array */
    public function detect() {
        $left = $this->leftovers();
        $notes = array();
        if ($this->known() && 'cookieadmin_gdpr' !== $this->law()) {
            $notes[] = __('CookieAdmin follows US state laws (opt-out) or a Pro law here, so visitors\' choices are not carried over: they are asked again.', 'seoprocookieconsent');
        }
        $geo = (string) ($this->policy()['cookieadmin_geo_tgt'] ?? '');
        if ('' !== $geo && 'www' !== $geo) {
            $notes[] = __('CookieAdmin Pro showed its banner only in some regions. That is not imported: check Who is asked first.', 'seoprocookieconsent');
        }
        $notes[] = __('Scripts you marked for CookieAdmin in your theme (type="text/plain" with data-cookieadmin-category) stay switched off once it is gone: remove those two attributes so this plugin holds them back instead.', 'seoprocookieconsent');
        foreach ($this->patterns() as $pattern) {
            if (null === self::script_rule($pattern['pattern'])) {
                /* translators: %s: a script pattern. */
                $notes[] = sprintf(__('Add by hand to Also hold back (no address to recognise): %s', 'seoprocookieconsent'), mb_substr($pattern['pattern'], 0, 120));
            }
        }
        return array('present' => (bool) array_filter($left), 'state' => $this->state(), 'notes' => $notes);
    }

    /** @return array */
    public function settings() {
        if (!$this->known()) {
            return array();
        }
        $policy = $this->policy();
        $result = array();
        $geo = (string) ($policy['cookieadmin_geo_tgt'] ?? 'www');
        // Its GDPR banner asks everyone first, unless Pro limited it to some regions.
        if ('cookieadmin_gdpr' === $this->law() && ('' === $geo || 'www' === $geo)) {
            $result['consent_regions'] = 'everywhere';
        }
        if (is_numeric($policy['cookieadmin_days'] ?? null)) {
            $result['consent_days'] = (int) $policy['cookieadmin_days'];
        }
        $layout = (string) ($policy['cookieadmin_layout'] ?? 'box');
        $position = (string) ($policy['cookieadmin_position'] ?? 'bottom_left');
        if ('box' === $layout && in_array($position, array('bottom_left', 'bottom_right'), true)) {
            $result['consent_position'] = 'bottom_left' === $position ? 'left' : 'right';
        } elseif ('footer' === $layout && 'bottom' === $position) {
            $result['consent_position'] = 'bar';
        }
        $message = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags((string) ($policy['cookieadmin_notice'] ?? '')), ENT_QUOTES, 'UTF-8')));
        if ('' !== $message && self::STOCK_MESSAGE !== $message) {
            $result['consent_message'] = $message;
        }
        $link = esc_url_raw((string) ($policy['cookieadmin_cookie_policy'] ?? ''));
        if ('' !== $link) {
            $result['consent_policy_url'] = $link;
        }
        return $result;
    }

    /** Script patterns from its cookie list, with our category; never scan data or descriptions. @return array<int,array{category:string,pattern:string}> */
    private function patterns() {
        global $wpdb;
        $table = $wpdb->prefix . 'cookieadmin_cookies';
        if (!in_array($table, $this->tables(), true)) {
            return array();
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the verified source cookie table: categories and patterns only.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT category, patterns FROM %i WHERE patterns NOT IN ('', '[]') ORDER BY id ASC LIMIT 1000", $table), ARRAY_A);
        $result = array();
        foreach ((array) $rows as $row) {
            $category = strtolower((string) $row['category']);
            if ('necessary' === $category) {
                continue;
            }
            $patterns = json_decode((string) $row['patterns'], true);
            foreach (is_array($patterns) ? $patterns : array() as $pattern) {
                if (is_string($pattern) && '' !== trim($pattern)) {
                    $result[] = array('category' => self::CATEGORIES[$category] ?? 'marketing', 'pattern' => trim($pattern));
                }
            }
        }
        return $result;
    }

    /** @return array */
    public function scripts() {
        $result = array('consent_block' => array(), 'consent_allow' => array());
        foreach ($this->patterns() as $pattern) {
            $rule = self::script_rule((string) preg_replace('~^https?://~i', '', $pattern['pattern']));
            if (is_string($rule) && '' !== $rule) {
                $result['consent_block'][] = ('marketing' !== $pattern['category'] ? $pattern['category'] . ': ' : '') . $rule;
            }
        }
        $result['consent_block'] = array_values(array_unique($result['consent_block']));
        return $result;
    }

    /** CookieAdmin has no page shortcodes or blocks. @return array */
    public function pages() {
        return array();
    }

    /**
     * One JSON cookie: {"accept":"true"} or {"reject":"true"} from its
     * buttons, or "true" for each category switched on when preferences
     * are saved (switched-off categories are left out). Closing its dialog
     * saves nothing. Only its GDPR banner is carried over.
     *
     * @return array
     */
    public function consent_cookie() {
        if (!$this->known() || 'cookieadmin_gdpr' !== $this->law()) {
            return array();
        }
        // Embeds have no switch of their own; like unclassified cookies, they follow Marketing.
        $map = array_flip(self::CATEGORIES) + array('embeds' => 'marketing');
        return array('json' => 'cookieadmin_consent', 'all' => array('accept', 'true'), 'none' => array('reject', 'true'), 'missing' => true, 'categories' => $map, 'allow' => 'true', 'deny' => 'false');
    }

    /** @return string[] */
    private function tables() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- SHOW only; escapes the site's prefix literally.
        return $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'cookieadmin_') . '%'));
    }

    /** Exact names only. Pro's consent logs may need keeping: listed, never removed. @return array */
    public function leftovers() {
        global $wpdb;
        $options = array();
        foreach (array('cookieadmin_', '_transient_cookieadmin_', '_transient_timeout_cookieadmin_') as $prefix) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- indexed, source-owned option prefixes; names only.
            $options = array_merge($options, $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT 1000", $wpdb->esc_like($prefix) . '%')));
        }
        $hooks = array();
        foreach (_get_cron_array() ?: array() as $events) {
            foreach (array_keys($events) as $hook) {
                if (0 === strpos($hook, 'cookieadmin_')) {
                    $hooks[] = $hook;
                }
            }
        }
        $tables = $this->tables();
        $records = array_values(array_filter($tables, function ($table) {
            return false !== strpos($table, 'consent');
        }));
        return array('options' => array_values(array_unique($options)), 'tables' => array_values(array_diff($tables, $records)), 'cron' => array_values(array_unique($hooks)), 'kept' => $records);
    }

    /** @return string */
    public function uninstall_setting() {
        return __('Deleting CookieAdmin removes its settings but leaves its cookie list table; use Remove leftover data afterwards. Imports never change its settings.', 'seoprocookieconsent');
    }
}
