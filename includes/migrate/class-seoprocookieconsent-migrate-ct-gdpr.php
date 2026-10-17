<?php
/**
 * Ultimate GDPR & CCPA by createIT (ct-ultimate-gdpr, sold on CodeCanyon,
 * not on WordPress.org) data formats, checked against version 5.4.0. Its
 * settings are plain option arrays; its services are posts. Only its
 * cookie consent part is imported: its privacy request forms, age gate,
 * terms and policy acceptance and TCF vendors are reported, not replaced.
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

final class SEOProCookieConsent_Migrate_CT_GDPR extends SEOProCookieConsent_Migrate_Source {
    const KEY = 'ct-ultimate-gdpr';
    const NAME = 'Ultimate GDPR & CCPA';
    const SLUGS = array('ct-ultimate-gdpr');

    /** Its cookie consent settings. */
    const OPTION = 'ct-ultimate-gdpr-cookie';

    /** Its services and cookies post type. */
    const POST_TYPE = 'ct_ugdpr_service';

    /** Its consent levels: level => category here. 1 blocks all and 2 is necessary. */
    const LEVELS = array(3 => 'preferences', 4 => 'statistics', 5 => 'marketing');

    /** Its positions that have one here. */
    const POSITIONS = array('bottom_panel_' => 'bar', 'bottom_left_' => 'left', 'bottom_right_' => 'right');

    /** Its shortcodes: kind here. */
    const LINKS = array('ultimate_gdpr_cookie_popup' => 'settings', 'block_cookies' => 'settings', 'render_cookies_list' => 'cookie');

    /** Its other shortcodes: privacy forms and acceptance boxes, not cookie consent. */
    const OTHER = array('ultimate_gdpr_myaccount', 'ultimate_gdpr_center', 'ultimate_gdpr_terms_accept', 'ultimate_gdpr_policy_accept', 'ultimate_gdpr_protection', 'tcf_non_iab_vendors');

    /** The account its cookie scanner signs in as; normally removed after each scan. */
    const SCAN_USER = 'gdpr-dummy@createit.pl';

    /** @return array */
    private function options() {
        $options = get_option(self::OPTION, array());
        return is_array($options) ? $options : array();
    }

    /** Its banner showed on every page, to every visitor or by location. @return bool */
    private function everywhere() {
        $options = $this->options();
        return !array_key_exists('cookie_display_all', $options) || !empty($options['cookie_display_all']);
    }

    /** @return array */
    public function detect() {
        $left = $this->leftovers();
        $notes = array(
            __('Visitors\' Ultimate GDPR & CCPA choices are not carried over: its cookie format was not checked on a running site, so visitors are asked again once.', 'seoprocookieconsent'),
        );
        $options = $this->options();
        if ($options && empty($options['cookie_block'])) {
            $notes[] = __('Ultimate GDPR & CCPA was not set to block cookies, so its banner did not hold anything back. This plugin holds back recognised services and the rules below until visitors agree.', 'seoprocookieconsent');
        }
        if (!$this->everywhere()) {
            $notes[] = __('Ultimate GDPR & CCPA showed its banner only on selected pages. Here the banner shows on every page, so Who is asked first is left as it is.', 'seoprocookieconsent');
        }
        $manual = array();
        foreach ($this->services() as $service) {
            foreach ($service['scripts'] as $keyword) {
                if (null === self::script_rule($keyword)) {
                    $manual[] = $service['name'] . ' (' . $keyword . ')';
                }
            }
        }
        if ($manual) {
            /* translators: %s: list of service names with their script keywords. */
            $notes[] = sprintf(__('These Ultimate GDPR & CCPA script keywords are not addresses, so they are not imported: %s. Add any this plugin does not recognise under Troubleshooting → Also hold back.', 'seoprocookieconsent'), implode(', ', array_unique($manual)));
        }
        $other = array();
        foreach ($this->pages() as $page) {
            foreach ($page['tokens'] as $token) {
                if ('other' === $token['kind']) {
                    $other[] = $token['token'];
                }
            }
        }
        if ($other) {
            /* translators: %s: list of shortcodes. */
            $notes[] = sprintf(__('Its privacy request forms, age gate and acceptance boxes are not cookie consent and are not replaced: %s. They stop working when it is removed; use WordPress\'s own export and erase personal data tools, or another forms plugin.', 'seoprocookieconsent'), implode(', ', array_unique($other)));
        }
        if (get_user_by('login', self::SCAN_USER)) {
            /* translators: %s: user name. */
            $notes[] = sprintf(__('Its cookie scanner left the user %s behind, which it hides from the Users screen while active. Delete that user after deactivating it.', 'seoprocookieconsent'), self::SCAN_USER);
        }
        if ($options) {
            $notes[] = __('Its banner texts are not imported: they describe its own banner. Ultimate GDPR & CCPA kept consent records; turn on Keep consent records if you need to go on documenting choices.', 'seoprocookieconsent');
        }
        return array('present' => (bool) array_filter($left) || (bool) $this->pages(), 'state' => $this->state(), 'notes' => $notes);
    }

    /** @return array */
    public function settings() {
        $options = $this->options();
        if (!$options) {
            return array();
        }
        $result = array();
        if ($this->everywhere()) {
            // Its location check asked only European visitors and showed nothing elsewhere.
            $result['consent_regions'] = empty($options['cookie_check_if_user_is_from_eu']) ? 'everywhere' : 'region_quiet';
        }
        $days = (int) floor((int) ($options['cookie_expire'] ?? 0) / DAY_IN_SECONDS);
        if ($days > 0) {
            $result['consent_days'] = $days;
        }
        $position = (string) ($options['cookie_position'] ?? '');
        if (isset(self::POSITIONS[$position])) {
            $result['consent_position'] = self::POSITIONS[$position];
        }
        $url = trim((string) ($options['cookie_read_page_custom'] ?? ''));
        $page = (int) ($options['cookie_read_page'] ?? 0);
        if ('' === $url && $page > 0 && 'publish' === get_post_status($page) && (int) get_option('wp_page_for_privacy_policy') !== $page) {
            $url = (string) get_permalink($page);
        }
        if ('' !== $url && wp_http_validate_url($url)) {
            $result['consent_policy_url'] = $url;
        }
        return $result;
    }

    /**
     * Its services' script keywords, which it matched as "contains" like
     * the rules here, become hold-back rules in the service's category.
     *
     * @return array
     */
    public function scripts() {
        $lines = array();
        foreach ($this->services() as $service) {
            foreach ($service['scripts'] as $keyword) {
                $rule = self::script_rule($keyword);
                if (is_string($rule) && '' !== $rule) {
                    $lines[] = ('marketing' !== $service['category'] ? $service['category'] . ': ' : '') . $rule;
                }
            }
        }
        return array('consent_block' => self::narrowest($lines), 'consent_allow' => array());
    }

    /** @return array */
    public function pages() {
        $tags = array_merge(array_keys(self::LINKS), self::OTHER);
        $needles = array_map(function ($tag) {
            return '[' . $tag;
        }, $tags);
        // Opening tags only: other kinds are reported, never replaced, so enclosed content stays as it is.
        $regex = '/\[(?:' . implode('|', array_map('preg_quote', $tags)) . ')\b[^\]]*\]/i';
        $pages = array();
        foreach ($this->find_pages($needles, $regex) as $page) {
            $tokens = array();
            $cookie = false;
            foreach ($page['matches'] as $token) {
                $tag = preg_match('/^\[([a-z_]+)/i', $token, $m) ? strtolower($m[1]) : '';
                $kind = self::LINKS[$tag] ?? 'other';
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
     * Not carried over. Its "ct-ultimate-gdpr-cookie" cookie holds an
     * encoded consent level that was not checked on a running site, so
     * visitors are asked again.
     *
     * @return array
     */
    public function consent_cookie() {
        return array();
    }

    /**
     * Its active services with an optional consent level and script keywords.
     *
     * @return array<int,array{name:string,category:string,scripts:string[]}>
     */
    private function services() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the source's own services; it is not registered while inactive.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, MAX(CASE WHEN m.meta_key = 'is_active' THEN m.meta_value END) AS active, MAX(CASE WHEN m.meta_key = 'type_of_cookie' THEN m.meta_value END) AS level, MAX(CASE WHEN m.meta_key = 'script_name' THEN m.meta_value END) AS scripts FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN ('is_active', 'type_of_cookie', 'script_name') WHERE p.post_type = %s AND p.post_status = 'publish' GROUP BY p.ID ORDER BY p.ID ASC LIMIT 500",
            self::POST_TYPE
        ), ARRAY_A);
        $services = array();
        foreach ((array) $rows as $row) {
            $level = (int) ($row['level'] ?? 0);
            if (empty($row['active']) || !isset(self::LEVELS[$level])) {
                continue;
            }
            $scripts = array_values(array_filter(array_map('trim', explode(',', (string) ($row['scripts'] ?? '')))));
            $services[(int) $row['ID']] = array('name' => (string) $row['post_title'], 'category' => self::LEVELS[$level], 'scripts' => $scripts);
        }
        return $services;
    }

    /** @return string[] */
    private function tables() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- SHOW only; escapes the site's prefix literally.
        return $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'ct_ugdpr_') . '%'));
    }

    /**
     * Exact names only. Its consent log table is listed, never removed;
     * consents it saved on user accounts stay with those accounts.
     *
     * @return array
     */
    public function leftovers() {
        global $wpdb;
        $options = self::option_names(array('ct-ultimate-gdpr', 'ct_ultimate_gdpr', 'ct_gdpr_check_last_cookies_scan'));
        $tables = $this->tables();
        $records = array_values(array_filter($tables, function ($table) use ($wpdb) {
            return $wpdb->prefix . 'ct_ugdpr_consent_log' === $table;
        }));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- IDs only, of the source's own post type.
        $posts = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID ASC LIMIT 1000", self::POST_TYPE)));
        return array(
            'options' => $options,
            'tables'  => array_values(array_diff($tables, $records)),
            'cron'    => self::cron_hooks(array('ct_ultimate_gdpr', 'ctgdpr_')),
            'posts'   => $posts,
            'kept'    => $records,
        );
    }

    /** @return string */
    public function uninstall_setting() {
        return __('Deleting Ultimate GDPR & CCPA removes only its files: its settings, services and consent log stay. Remove leftover data deletes its settings and services after it is deactivated, and keeps its consent log and the consents saved on user accounts. Imports never change its settings.', 'seoprocookieconsent');
    }
}
