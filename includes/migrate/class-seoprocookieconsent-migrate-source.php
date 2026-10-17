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

    /**
     * Lines a source let load before consent, as "Never hold back" lines.
     * Recognised services, and anything not an address, are left out:
     * they would load Google or ad tags before consent here. A line is kept
     * as written, never widened to its whole host.
     *
     * @param string[] $lines Address fragments.
     * @return string[]
     */
    protected static function allow_rules(array $lines) {
        $rules = array();
        foreach ($lines as $line) {
            $rule = self::script_rule($line);
            if (is_string($rule) && '' !== $rule) {
                $rules[] = strtolower(trim((string) $line));
            }
        }
        return array_values(array_unique($rules));
    }

    /**
     * A link or button that opened the source's own settings dialog, made
     * to open Cookie settings: its link target and click code are replaced.
     *
     * @param string $element Whole <a> or <button> element.
     * @return string|null Null when it is not one.
     */
    protected static function settings_link($element) {
        if (!preg_match('/^<(a|button)\b([^>]*)>(.*)$/is', $element, $m)) {
            return null;
        }
        $attributes = (string) preg_replace('/\s(?:href|onclick|data-spcc-action)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $m[2]);
        $target = 'a' === strtolower($m[1]) ? ' href="#cookie-settings"' : ' data-spcc-action="settings"';
        return '<' . $m[1] . $target . $attributes . '>' . $m[3];
    }

    /** @param string[] $prefixes Source-owned option name prefixes. @return string[] Names only. */
    protected static function option_names(array $prefixes) {
        global $wpdb;
        $options = array();
        foreach ($prefixes as $prefix) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- indexed, source-owned option prefixes; names only, never values.
            $options = array_merge($options, $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT 1000", $wpdb->esc_like($prefix) . '%')));
        }
        return array_values(array_unique($options));
    }

    /** @param string[] $prefixes Source-owned cron hook prefixes. @return string[] */
    protected static function cron_hooks(array $prefixes) {
        $hooks = array();
        foreach (_get_cron_array() ?: array() as $events) {
            foreach (array_keys($events) as $hook) {
                foreach ($prefixes as $prefix) {
                    if (0 === strpos($hook, $prefix)) {
                        $hooks[] = $hook;
                    }
                }
            }
        }
        return array_values(array_unique($hooks));
    }

    /**
     * Notes every hosted (web app) source shares: what stays in its
     * dashboard, why visitors are asked again, and what to compare.
     *
     * @return string[]
     */
    protected function hosted_notes() {
        $notes = array(
            /* translators: %s: name of the old consent plugin. */
            sprintf(__('%s keeps its banner texts, categories and cookie scan in its own dashboard, which this import never contacts; only settings saved on this site are imported. Check Who is asked first, Banner position, Banner message and Remember choices for here before switching.', 'seoprocookieconsent'), static::NAME),
            /* translators: %s: name of the old consent plugin. */
            sprintf(__('Visitors\' %s choices are not carried over: its hosted script stores them in a format not checked against a live account, so visitors are asked again once.', 'seoprocookieconsent'), static::NAME),
        );
        $names = array();
        foreach (SEOProCookieConsent_Services::seen_by_category() as $services) {
            foreach ($services as $service) {
                $names[] = $service['name'];
            }
        }
        if ($names) {
            /* translators: 1: name of the old consent plugin, 2: list of service names. */
            $notes[] = sprintf(__('Compare %1$s\'s cookie scan with the services this plugin found on your pages: %2$s. Add anything missing under Troubleshooting → Also hold back.', 'seoprocookieconsent'), static::NAME, implode(', ', $names));
        } else {
            /* translators: %s: name of the old consent plugin. */
            $notes[] = sprintf(__('This plugin has not found services on your pages yet. After switching, use Scan now on the Cookie consent tab and compare its list with %s\'s cookie scan; add anything missing under Troubleshooting → Also hold back.', 'seoprocookieconsent'), static::NAME);
        }
        $notes[] = __('Consent records it kept on its own servers stay there: export any you must keep before closing that account. Turn on Keep consent records here to go on documenting choices.', 'seoprocookieconsent');
        return $notes;
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
