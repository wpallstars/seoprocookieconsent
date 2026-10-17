<?php
/**
 * Complianz data formats, checked against Complianz Free 7.5.6, Premium
 * 7.5.4/7.6.5 and Terms & Conditions 1.4.1 sites. Unknown formats are left
 * alone. Terms answers use the existing identity importer.
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
        $regions = $this->consent_regions($old);
        if ('' !== $regions) {
            $result['consent_regions'] = $regions;
        }
        if (isset($old['cookie_expiry']) && is_numeric($old['cookie_expiry'])) {
            $result['consent_days'] = (int) $old['cookie_expiry'];
        }
        if (isset($old['consent-mode'], $old['gtag-basic-consent-mode'])) {
            $result['consent_google_advanced'] = 'yes' === $old['consent-mode'] && 'yes' !== $old['gtag-basic-consent-mode'];
        }
        global $wpdb;
        $records = $wpdb->prefix . 'cmplz_statistics';
        if (in_array($records, $this->tables(), true)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only existence check; never reads consent record contents.
            if ($wpdb->get_var($wpdb->prepare('SELECT 1 FROM %i LIMIT 1', $records))) {
                $result['consent_records'] = true;
            }
        }
        if (in_array($wpdb->prefix . 'cmplz_cookiebanners', $this->tables(), true)) {
            // The default banner is the one visitors see; older rows often remain from A/B tests.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the verified source banner table.
            $banner = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i ORDER BY %i DESC, ID ASC LIMIT 1', $wpdb->prefix . 'cmplz_cookiebanners', 'default'), ARRAY_A);
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

    /**
     * Map geo-targeting conservatively: an opt-in fallback region (EU, UK,
     * ZA, BR) means every other visitor was asked first, so keep that.
     *
     * @param array $old Source options.
     * @return string
     */
    private function consent_regions(array $old) {
        $optin = array('eu', 'uk', 'za', 'br');
        if (!empty($old['use_country'])) {
            $other = (string) ($old['other_region_behaviour'] ?? '');
            if ('' === $other) {
                return '';
            }
            return 'none' === $other ? 'region_quiet' : (in_array($other, $optin, true) ? 'everywhere' : 'region');
        }
        if (!isset($old['use_country'])) {
            return '';
        }
        $regions = array_values((array) ($old['regions'] ?? array()));
        return !$regions || array_intersect($regions, $optin) ? 'everywhere' : 'region';
    }

    /** Bounded document lookup, only in admin/CLI. @return array */
    public function pages() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- explicit, bounded document hand-over lookup; never a visitor query.
        $posts = $wpdb->get_results($wpdb->prepare("SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status NOT IN ('trash', 'auto-draft') AND (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s) ORDER BY ID ASC LIMIT 100", '%[cmplz-%', '%wp:complianz/%', '%wp:complianztc/%'));
        $pages = array();
        foreach ($posts as $post) {
            // Serialized block attributes escape "<" and ">", so [^>]* cannot end inside JSON.
            preg_match_all('/\[cmplz-[a-z-]+\b[^\]]*\]|<!-- wp:complianz(?:tc)?\/[^>]*\/-->/i', $post->post_content, $matches);
            if (empty($matches[0])) {
                continue;
            }
            $tokens = array();
            $cookie = false;
            foreach (array_unique($matches[0]) as $token) {
                $entry = $this->token($token);
                $tokens[] = $entry;
                $cookie = $cookie || $entry['cookie'];
            }
            $pages[] = array('id' => (int) $post->ID, 'title' => $post->post_title, 'cookie' => $cookie, 'tokens' => $tokens);
        }
        return $pages;
    }

    /**
     * Classify one document token. Mirrors Complianz's block rendering:
     * an "unlink"ed custom document renders its stored HTML, a block without
     * a selected document renders nothing.
     *
     * @param string $token Shortcode or self-closing block.
     * @return array
     */
    private function token($token) {
        $entry = array('token' => $token, 'cookie' => false, 'renderable' => false, 'kind' => 'render');
        if (0 === stripos($token, '[cmplz-manage-consent') || 0 === stripos($token, '[cmplz-revoke-link')) {
            return array_merge($entry, array('renderable' => true, 'kind' => 'settings', 'replace' => '<!-- wp:seoprocookieconsent/cookie-settings /-->'));
        }
        if (0 === strpos($token, '[') && !preg_match('/^\[cmplz-(?:document|terms-conditions)\b/i', $token)) {
            return array_merge($entry, array('kind' => 'manual')); // Forms and live lists have no static equivalent.
        }
        if (0 === strpos($token, '[')) {
            $type = preg_match('/\btype=["\']?([a-z-]+)/i', $token, $match) ? strtolower($match[1]) : '';
            $entry['cookie'] = 0 === strpos($type, 'cookie-statement') || 0 === strpos($type, 'cookie-policy');
            $entry['kind'] = $entry['cookie'] ? 'cookie' : 'render';
            $entry['renderable'] = $entry['cookie'] || $this->renderable($token);
            return $entry;
        }
        $blocks = parse_blocks($token);
        $attrs = (array) ($blocks[0]['attrs'] ?? array());
        $name = (string) ($blocks[0]['blockName'] ?? '');
        if ('complianz/document' === $name) {
            $selected = (string) ($attrs['selectedDocument'] ?? '');
            if ('' === $selected) {
                return array_merge($entry, array('kind' => 'empty'));
            }
            if (0 === strpos($selected, 'cookie-statement')) {
                return array_merge($entry, array('cookie' => true, 'renderable' => true, 'kind' => 'cookie'));
            }
        }
        // Both Complianz plugins return an unlinked custom document verbatim.
        if (in_array($name, array('complianz/document', 'complianztc/terms-conditions'), true) && 'unlink' === ($attrs['documentSyncStatus'] ?? '') && is_string($attrs['customDocument'] ?? null) && '' !== trim($attrs['customDocument'])) {
            return array_merge($entry, array('renderable' => true, 'kind' => 'static', 'replace' => "<!-- wp:html -->\n" . wp_kses_post($attrs['customDocument']) . "\n<!-- /wp:html -->"));
        }
        $entry['renderable'] = $this->renderable($token);
        return $entry;
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
        // Mirrors Complianz's prefix rule: a multisite main site uses cmplz_rt_ unless set_cookies_on_root is on.
        $old = get_option('cmplz_options', array());
        $p = is_multisite() && is_main_site() && empty(is_array($old) ? ($old['set_cookies_on_root'] ?? '') : '') ? 'cmplz_rt_' : 'cmplz_';
        return array('status' => $p . 'banner-status', 'value' => 'dismissed', 'categories' => array('preferences' => $p . 'preferences', 'statistics' => $p . 'statistics', 'marketing' => $p . 'marketing'), 'allow' => 'allow', 'deny' => 'deny');
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
        foreach (array('cmplz_', 'cmplz-', 'complianz_', '_transient_cmplz_', '_transient_timeout_cmplz_', '_transient_complianz_', '_transient_timeout_complianz_') as $prefix) {
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
        // Scanner bookkeeping (_cmplz_scanned_post, _cmplz_remote_scanned_post) and the per-page cmplz_hide_cookiebanner toggle.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- distinct key names only, of the source's own meta prefixes.
        $meta = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s ORDER BY meta_key LIMIT 100", $wpdb->esc_like('_cmplz_') . '%', $wpdb->esc_like('cmplz_') . '%'));
        // Premium's records of consent (cmplz_statistics) and data requests (cmplz_dnsmpd) may need keeping: listed, never removed.
        $records = array($wpdb->prefix . 'cmplz_statistics', $wpdb->prefix . 'cmplz_dnsmpd');
        $tables = $this->tables();
        list($uploads, $kept_files) = $this->upload_dirs();
        return array(
            'options' => array_values(array_unique($options)),
            'tables'  => array_values(array_diff($tables, $records)),
            'cron'    => array_values(array_unique($hooks)),
            'meta'    => array_values($meta),
            'uploads' => $uploads,
            'kept'    => array_merge(array_values(array_intersect($tables, $records)), $kept_files),
        );
    }

    /**
     * Folders under uploads/complianz: the ones it generates (vendor lists, banner CSS and
     * scripts, placeholders, GeoLite database, PDF font cache) are removable; anything else,
     * such as Premium's proof-of-consent snapshots, is listed as kept.
     *
     * @return array{0:string[],1:string[]}
     */
    private function upload_dirs() {
        $base = trailingslashit(wp_upload_dir(null, false)['basedir']) . 'complianz';
        if (!is_dir($base)) {
            return array(array(), array());
        }
        $generated = array('cmp', 'css', 'js', 'placeholders', 'maxmind', 'tmp');
        $remove = array();
        $keep = array();
        foreach ((array) scandir($base) as $name) {
            if ('.' === $name || '..' === $name || !is_string($name)) {
                continue;
            }
            if (in_array($name, $generated, true) && is_dir($base . '/' . $name) && !is_link($base . '/' . $name)) {
                $remove[] = 'complianz/' . $name;
            } else {
                $keep[] = 'uploads/complianz/' . $name;
            }
        }
        return array($remove, $keep);
    }

    /** @return string */
    public function uninstall_setting() {
        return __('Check Complianz’s own delete-data-on-uninstall setting before deleting it. Imports never change its settings.', 'seoprocookieconsent');
    }
}
