<?php
/**
 * Read-only migration adapter contract. Cleanup returns an exact inventory.
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

abstract class SEOProCookieConsent_Migrate_Source {
    const KEY = '';
    const NAME = '';
    const SLUGS = array();

    /** @return array Presence and plugin state. */
    abstract public function detect();
    /** @return array Destination setting => raw value. */
    abstract public function settings();
    /** @return array Destination line setting => string[]. */
    abstract public function scripts();
    /** @return array Pages and individually replaceable document tokens. */
    abstract public function pages();
    /** @return array Browser-readable explicit-choice format, or empty. */
    abstract public function consent_cookie();
    /** @return array Exact present site-local artifacts, never consent records. */
    abstract public function leftovers();
    /** @return string Advice only; importing never writes the source. */
    abstract public function uninstall_setting();

    /** Read stored activation, including network activation, without trusting request filters. @return bool */
    public function active() {
        $files = SEOProCookieConsent_Feature::stored_active_plugins();
        if (is_multisite()) {
            $files = array_merge($files, array_keys((array) get_site_option('active_sitewide_plugins', array())));
        }
        foreach ($files as $file) {
            if (in_array(dirname($file), static::SLUGS, true)) {
                return true;
            }
        }
        return false;
    }

    /** @return string 'active', 'inactive' or 'not installed'. */
    protected function state() {
        if ($this->active()) {
            return 'active';
        }
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        foreach (array_keys(get_plugins()) as $file) {
            if (in_array(dirname($file), static::SLUGS, true)) {
                return 'inactive';
            }
        }
        return 'not installed';
    }

    /**
     * Bounded document lookup, only in admin/CLI: pages containing any
     * needle, with the unique regex matches in each.
     *
     * @param string[] $needles Literal fragments.
     * @param string   $regex   Token pattern.
     * @return array<int,array{id:int,title:string,matches:string[]}>
     */
    protected function find_pages(array $needles, $regex) {
        global $wpdb;
        $where = array();
        foreach ($needles as $needle) {
            $where[] = $wpdb->prepare('post_content LIKE %s', '%' . $wpdb->esc_like($needle) . '%');
        }
        if (!$where) {
            return array();
        }
        $where = implode(' OR ', $where);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- explicit, bounded document hand-over lookup; each $where clause is prepared above.
        $posts = $wpdb->get_results("SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status NOT IN ('trash', 'auto-draft') AND ($where) ORDER BY ID ASC LIMIT 100");
        $pages = array();
        foreach ($posts as $post) {
            if (preg_match_all($regex, $post->post_content, $matches)) {
                $pages[] = array('id' => (int) $post->ID, 'title' => $post->post_title, 'matches' => array_values(array_unique($matches[0])));
            }
        }
        return $pages;
    }

    /**
     * A hold-back rule for a script the consent feature does not already
     * recognise: '' when recognised or not needed, null when it can only be
     * added by hand (inline code with no recognisable address).
     *
     * @param string $snippet Script element, address or source pattern.
     * @return string|null
     */
    protected static function script_rule($snippet) {
        $snippet = strtolower(trim((string) $snippet));
        if ('' === $snippet || '' !== SEOProCookieConsent_Services::match($snippet)) {
            return '';
        }
        $site = (string) preg_replace('/^www\./', '', (string) wp_parse_url(home_url(), PHP_URL_HOST));
        if (!preg_match('~(?:https?:)?//([a-z0-9][a-z0-9.-]*\.[a-z]{2,})(/[^\s"\'<>?#]*)?~', $snippet, $match)) {
            // A bare source pattern, such as "hotjar.com" or "/js/pixel.js".
            if (preg_match('~^[a-z0-9.-]+\.[a-z]{2,}(?:/[^\s"\'<>]*)?$|^/[^\s"\'<>]{4,}$~', $snippet) && 0 !== strpos($snippet, $site)) {
                return self::known($snippet) ? '' : $snippet;
            }
            return null;
        }
        $host = preg_replace('/^www\./', '', $match[1]);
        // The site's own scripts are matched by path; holding back its host would hold back everything.
        $rule = $host === $site ? (string) ($match[2] ?? '') : $host;
        if ('' === $rule || '/' === $rule) {
            return null;
        }
        return self::known($rule) ? '' : $rule;
    }

    /** Whether a fragment is part of a recognised service pattern. @param string $fragment Fragment. @return bool */
    private static function known($fragment) {
        foreach (SEOProCookieConsent_Services::all() as $service) {
            foreach ($service['patterns'] as $pattern) {
                if (false !== strpos($pattern, $fragment) || false !== strpos($fragment, $pattern)) {
                    return true;
                }
            }
        }
        return false;
    }
}
