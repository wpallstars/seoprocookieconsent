<?php
/**
 * GDPR Cookie Compliance by Moove (gdpr-cookie-compliance) data formats,
 * checked against version 5.2.2. Its settings live in its own table, so
 * they are read even when the plugin is inactive. Pro add-on features
 * (geolocation, consent versions, consent logs) are reported, not guessed.
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

final class SEOProCookieConsent_Migrate_Moove extends SEOProCookieConsent_Migrate_Source {
    const KEY = 'gdpr-cookie-compliance';
    const NAME = 'GDPR Cookie Compliance (Moove)';
    const SLUGS = array('gdpr-cookie-compliance');

    /** The shipped English banner text, tags and [setting] links removed. */
    const STOCK_MESSAGE = 'We are using cookies to give you the best experience on our website. You can find out more about which cookies we are using or switch them off in settings.';

    /** Cookie key => switch setting. Performance and Preference come from its Pro add-on. */
    const CATEGORIES = array(
        'thirdparty'  => 'moove_gdpr_third_party_cookies_enable',
        'advanced'    => 'moove_gdpr_advanced_cookies_enable',
        'performance' => 'moove_gdpr_performance_ccat_enable',
        'preference'  => 'moove_gdpr_preference_ccat_enable',
    );

    /** Its integrations, which it loaded itself: key => name. */
    const INTEGRATIONS = array('ga4' => 'Google Analytics 4', 'gtm' => 'Google Tag Manager', 'gtmc2' => 'Google Tag Manager', 'gadc' => 'Google Ads', 'fbp' => 'Meta Pixel', 'muet' => 'Microsoft Ads', 'clarity' => 'Microsoft Clarity', 'gtm4wp' => 'GTM4WP');

    /** @var array|null */
    private $options = null;

    /** Settings from its table (current) or, before the table exists, the option. @return array */
    private function options() {
        if (null !== $this->options) {
            return $this->options;
        }
        global $wpdb;
        $this->options = array();
        if (in_array($wpdb->prefix . 'gdpr_cc_options', $this->tables(), true)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the verified source settings table.
            $rows = $wpdb->get_results($wpdb->prepare('SELECT option_key, option_value FROM %i ORDER BY id ASC LIMIT 2000', $wpdb->prefix . 'gdpr_cc_options'), ARRAY_A);
            foreach ((array) $rows as $row) {
                $this->options[(string) $row['option_key']] = maybe_unserialize($row['option_value']);
            }
        }
        if (!$this->options) {
            $old = get_option('moove_gdpr_plugin_settings', array());
            $this->options = is_array($old) ? $old : array();
        }
        return $this->options;
    }

    /** @param string $key Setting. @return int */
    private function int($key) {
        $options = $this->options();
        return isset($options[$key]) && is_scalar($options[$key]) ? (int) $options[$key] : 0;
    }

    /** Cookie keys of the categories visitors could switch. @return string[] */
    private function enabled() {
        return array_keys(array_filter(self::CATEGORIES, function ($setting) {
            return 1 === $this->int($setting);
        }));
    }

    /**
     * Acceptance that was not a clear choice: its close button accepted
     * everything (behaviour 3), or categories were on before any choice,
     * which scrolling can save as if accepted.
     *
     * @return bool
     */
    private function implied() {
        return 3 === $this->int('gdpr_close_button_bhv') || $this->preset();
    }

    /** A category was on before any choice ("Default status" enabled). @return bool */
    private function preset() {
        return 1 === $this->int('moove_gdpr_third_party_cookies_enable_first_visit') || 1 === $this->int('moove_gdpr_advanced_cookies_enable_first_visit');
    }

    /** @return array */
    public function detect() {
        $left = $this->leftovers();
        $notes = array();
        if ($this->preset()) {
            $notes[] = __('Some categories were on before visitors chose, which is not a clear choice: only rejections carry over, and visitors who accepted are asked again.', 'seoprocookieconsent');
        } elseif ($this->implied()) {
            $notes[] = __('Its close button accepted all cookies, which is not a clear choice: only rejections carry over, and visitors who accepted are asked again.', 'seoprocookieconsent');
        }
        $notes[] = __('If its add-on accepted cookies on scroll or after a timer, those look the same as Accept: choose Stop carrying over old consent so visitors are asked again.', 'seoprocookieconsent');
        if ($this->code()) {
            $notes[] = __('GDPR Cookie Compliance added your category scripts itself; they stop when it is gone. Add them to your theme or a header-code plugin after switching: the script rules below, and services this plugin recognises, are then held back until visitors allow them. Copy them before removing leftover data.', 'seoprocookieconsent');
            foreach ($this->code() as $snippet) {
                if (null === self::script_rule($snippet)) {
                    /* translators: %s: start of an inline script. */
                    $notes[] = sprintf(__('Add by hand to Also hold back (no address to recognise): %s', 'seoprocookieconsent'), mb_substr(trim((string) preg_replace('/\s+/', ' ', $snippet)), 0, 120));
                }
            }
        }
        foreach ($this->integrations() as $name => $id) {
            /* translators: 1: service name, 2: its tracking ID. */
            $notes[] = sprintf(__('GDPR Cookie Compliance loaded %1$s (%2$s) itself; it stops when the plugin is gone. Add it to your site another way, such as Site Kit or your theme: this plugin recognises it and holds it back.', 'seoprocookieconsent'), $name, $id);
        }
        return array('present' => (bool) array_filter($left), 'state' => $this->state(), 'notes' => $notes);
    }

    /** Integrations switched on with an ID: name => ID. @return array<string,string> */
    private function integrations() {
        $options = $this->options();
        $values = is_string($options['gdin_values'] ?? null) ? json_decode($options['gdin_values'], true) : array();
        $result = array();
        foreach (self::INTEGRATIONS as $key => $name) {
            if (is_array($values) && isset($values[$key])) {
                $id = is_scalar($values[$key . '_id'] ?? null) ? sanitize_text_field((string) $values[$key . '_id']) : '';
                $result[$name] = '' !== $id ? $id : __('no ID', 'seoprocookieconsent');
            }
        }
        return $result;
    }

    /** Script, iframe, image and noscript elements from its enabled categories' scripts. @return string[] */
    private function code() {
        $options = $this->options();
        $html = '';
        foreach (array('third_party' => 'moove_gdpr_third_party_cookies_enable', 'advanced_cookies' => 'moove_gdpr_advanced_cookies_enable') as $name => $setting) {
            if (1 !== $this->int($setting)) {
                continue;
            }
            foreach (array('header', 'body', 'footer') as $place) {
                // Saved serialized inside its serialized table value.
                $html .= "\n" . (string) maybe_unserialize($options['moove_gdpr_' . $name . '_' . $place . '_scripts'] ?? '');
            }
        }
        if ('' === trim($html)) {
            return array();
        }
        preg_match_all('~<script\b[^>]*>.*?</script>|<noscript\b[^>]*>.*?</noscript>|<(?:iframe|img)\b[^>]*>~is', $html, $matches);
        return array_values(array_filter(array_map('trim', $matches[0])));
    }

    /** @return array */
    public function settings() {
        $options = $this->options();
        if (!$options) {
            return array();
        }
        $result = array();
        // With every category off until visitors chose, everyone was asked first.
        if ($this->enabled() && !$this->preset()) {
            $result['consent_regions'] = 'everywhere';
        }
        $days = $this->int('moove_gdpr_consent_expiration');
        if ($days > 0) {
            $result['consent_days'] = $days;
        }
        $positions = array('bottom' => 'bar', 'bottom_left' => 'left', 'bottom_right' => 'right');
        $position = is_string($options['moove_gdpr_infobar_position'] ?? null) ? $options['moove_gdpr_infobar_position'] : 'bottom';
        if (isset($positions[$position])) {
            $result['consent_position'] = $positions[$position];
        }
        // Content in the site's main language only; translations use a language suffix.
        $content = (string) preg_replace('~\[/?setting\]~i', '', (string) ($options['moove_gdpr_info_bar_content'] ?? ''));
        $message = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags($content), ENT_QUOTES, 'UTF-8')));
        if ('' !== $message && self::STOCK_MESSAGE !== $message) {
            $result['consent_message'] = $message;
        }
        return $result;
    }

    /** @return array */
    public function scripts() {
        $result = array('consent_block' => array(), 'consent_allow' => array());
        foreach ($this->code() as $snippet) {
            $rule = self::script_rule($snippet);
            if (is_string($rule) && '' !== $rule) {
                $result['consent_block'][] = $rule;
            }
        }
        $result['consent_block'] = array_values(array_unique($result['consent_block']));
        return $result;
    }

    /** It has no page shortcodes or blocks; [setting] is used only in its banner. @return array */
    public function pages() {
        return array();
    }

    /**
     * One JSON cookie with "1" or "0" for each category, written by Accept,
     * Reject, Save and (by setting) the close button; "strict" is "1" for a
     * kept choice. Its categories hold whatever the owner put in them, so
     * each of ours needs every switchable one allowed: a mixed choice
     * carries over as Reject all.
     *
     * @return array
     */
    public function consent_cookie() {
        $enabled = $this->enabled();
        if (!$enabled) {
            return array();
        }
        $map = array_fill_keys(array('preferences', 'statistics', 'embeds', 'marketing'), $enabled);
        return array('json' => 'moove_gdpr_popup', 'status' => 'strict', 'value' => '1', 'categories' => $map, 'allow' => $this->implied() ? null : '1', 'deny' => '0');
    }

    /** @return string[] */
    private function tables() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- SHOW only; escapes the site's prefix literally.
        return $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'gdpr_cc_') . '%'));
    }

    /** Exact names only. It keeps no consent records on the site without its add-on. @return array */
    public function leftovers() {
        global $wpdb;
        $options = array();
        foreach (array('moove_gdpr_', 'gdpr_cc_', 'gdpr_hide_update_notice_', '_transient_gdpr_cookie_cache', '_transient_timeout_gdpr_cookie_cache', '_transient_gdpr_geo_', '_transient_timeout_gdpr_geo_') as $prefix) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- indexed, source-owned option prefixes; names only, never licence values.
            $options = array_merge($options, $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT 1000", $wpdb->esc_like($prefix) . '%')));
        }
        return array('options' => array_values(array_unique($options)), 'tables' => $this->tables(), 'cron' => array(), 'kept' => array());
    }

    /** @return string */
    public function uninstall_setting() {
        return __('GDPR Cookie Compliance keeps its settings table when deleted; use Remove leftover data afterwards. Copy its category scripts first. Imports never change its settings.', 'seoprocookieconsent');
    }
}
