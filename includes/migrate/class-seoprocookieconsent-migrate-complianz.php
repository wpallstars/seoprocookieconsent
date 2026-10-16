<?php
/**
 * Complianz data formats, checked against an installed Complianz Free 7.5.6.
 * Premium is not available on this runner: only shared formats are read;
 * unknown formats are left alone. Terms uses its existing identity importer.
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

final class SEOProCookieConsent_Migrate_Complianz extends SEOProCookieConsent_Migrate_Source {
    const KEY = 'complianz';
    const NAME = 'Complianz';
    const SLUGS = array('complianz-gdpr', 'complianz-gdpr-premium', 'complianz-terms-conditions');

    /** @return array */
    public function detect() {
        $left = $this->leftovers();
        $installed = false;
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        foreach (array_keys(get_plugins()) as $file) {
            $installed = $installed || in_array(dirname($file), self::SLUGS, true);
        }
        return array('present' => (bool) array_filter($left) || (bool) $this->pages(), 'state' => $this->active() ? 'active' : ($installed ? 'inactive' : 'not installed'));
    }

    /** @return array */
    public function settings() {
        $old = get_option('cmplz_options', array());
        $old = is_array($old) ? $old : array();
        $result = array();
        if (isset($old['use_country'])) {
            $result['consent_regions'] = !empty($old['use_country']) ? 'region' : 'everywhere';
        }
        if (isset($old['cookie_expiry']) && is_numeric($old['cookie_expiry'])) {
            $result['consent_days'] = (int) $old['cookie_expiry'];
        }
        if (isset($old['consent-mode'], $old['gtag-basic-consent-mode'])) {
            $result['consent_google_advanced'] = 'yes' === $old['consent-mode'] && 'yes' !== $old['gtag-basic-consent-mode'];
        }
        global $wpdb;
        if (in_array($wpdb->prefix . 'cmplz_cookiebanners', $this->tables(), true)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the verified source banner table.
            $banner = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i ORDER BY ID ASC LIMIT 1', $wpdb->prefix . 'cmplz_cookiebanners'), ARRAY_A);
            if (is_array($banner)) {
                $positions = array('bottom' => 'bar', 'bottom-left' => 'left', 'bottom-right' => 'right');
                if (isset($positions[$banner['position'] ?? ''])) {
                    $result['consent_position'] = $positions[$banner['position']];
                }
                if (!empty($banner['message_optin'])) {
                    $result['consent_message'] = wp_strip_all_tags($banner['message_optin']);
                }
            }
        }
        foreach ($this->pages() as $page) {
            if (!empty($page['cookie'])) {
                $result['consent_policy_url'] = get_permalink($page['id']);
                break;
            }
        }
        return $result;
    }

    /** @return array */
    public function scripts() {
        $data = get_option('complianz_options_custom-scripts', array());
        $result = array('consent_block' => array(), 'consent_allow' => array());
        foreach (array('block_script' => 'consent_block', 'whitelist_script' => 'consent_allow') as $type => $key) {
            foreach ((array) ($data[$type] ?? array()) as $script) {
                if (!is_array($script) || empty($script['enable'])) {
                    continue;
                }
                $category = $script['category'] ?? 'marketing';
                $category = in_array($category, array('preferences', 'statistics', 'embeds'), true) ? $category : 'marketing';
                foreach ((array) ($script['urls'] ?? array()) as $url) {
                    if (!is_string($url) || '' === trim($url)) {
                        continue;
                    }
                    $fragment = preg_replace('~^https?://~i', '', trim($url));
                    $result[$key][] = ('consent_block' === $key && 'marketing' !== $category ? $category . ': ' : '') . $fragment;
                }
            }
        }
        return $result;
    }

    /** Bounded document lookup, only in admin/CLI. @return array */
    public function pages() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- explicit, bounded document hand-over lookup; never a visitor query.
        $posts = $wpdb->get_results($wpdb->prepare("SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status NOT IN ('trash', 'auto-draft') AND (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s) ORDER BY ID ASC LIMIT 100", '%[cmplz-document%', '%[cmplz-terms-conditions%', '%wp:complianz/%'));
        $pages = array();
        foreach ($posts as $post) {
            preg_match_all('/\[cmplz-document\b[^\]]*\]|\[cmplz-terms-conditions\b[^\]]*\]|<!-- wp:complianz\/[^>]*\/-->/i', $post->post_content, $matches);
            if (empty($matches[0])) {
                continue;
            }
            $tokens = array();
            $cookie = false;
            foreach (array_unique($matches[0]) as $token) {
                $is_cookie = false !== strpos($token, 'cookie-statement') || false !== strpos($token, 'cookie-policy');
                $tokens[] = array('token' => $token, 'cookie' => $is_cookie, 'renderable' => $is_cookie || $this->renderable($token));
                $cookie = $cookie || $is_cookie;
            }
            $pages[] = array('id' => (int) $post->ID, 'title' => $post->post_title, 'cookie' => $cookie, 'tokens' => $tokens);
        }
        return $pages;
    }

    /** @param string $token Document token. @return bool */
    private function renderable($token) {
        if (0 === strpos($token, '[')) {
            return shortcode_exists(0 === strpos($token, '[cmplz-terms-conditions') ? 'cmplz-terms-conditions' : 'cmplz-document');
        }
        $blocks = parse_blocks($token);
        return !empty($blocks[0]['blockName']) && WP_Block_Type_Registry::get_instance()->is_registered($blocks[0]['blockName']);
    }

    /** Require category decisions, not the ambiguous banner-status cookie alone. @return array */
    public function consent_cookie() {
        return array('status' => 'cmplz_banner-status', 'value' => 'dismissed', 'categories' => array('preferences' => 'cmplz_preferences', 'statistics' => 'cmplz_statistics', 'marketing' => 'cmplz_marketing'), 'allow' => 'allow', 'deny' => 'deny');
    }

    /** @return string[] */
    private function tables() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- SHOW only; escapes the site's prefix literally.
        return $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'cmplz_') . '%'));
    }

    /** Exact names only; no record rows are read. @return array */
    public function leftovers() {
        global $wpdb;
        $options = array();
        foreach (array('cmplz_', 'complianz_', '_transient_cmplz_', '_transient_timeout_cmplz_', '_transient_complianz_', '_transient_timeout_complianz_') as $prefix) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- indexed, source-owned option prefixes; names only, never licence values.
            $options = array_merge($options, $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT 1000", $wpdb->esc_like($prefix) . '%')));
        }
        $hooks = array();
        foreach (_get_cron_array() ?: array() as $events) {
            foreach (array_keys($events) as $hook) {
                if (0 === strpos($hook, 'cmplz_') || 0 === strpos($hook, 'complianz_')) {
                    $hooks[] = $hook;
                }
            }
        }
        return array('options' => array_values(array_unique($options)), 'tables' => $this->tables(), 'cron' => array_values(array_unique($hooks)));
    }

    /** @return string */
    public function uninstall_setting() {
        return __('Check Complianz’s own delete-data-on-uninstall setting before deleting it. Imports never change its settings.', 'seoprocookieconsent');
    }
}
