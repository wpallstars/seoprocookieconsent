<?php
/**
 * Admin-only import, undo and explicitly confirmed site-local cleanup.
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

final class SEOProCookieConsent_Migrate extends SEOProCookieConsent_Feature {
    const IMPORTS = 'seoprocookieconsent_imports';
    const CONFIG = 'seoprocookieconsent_migrate';
    const LOCK = 'seoprocookieconsent_import_lock';
    /** What refresh() last found per source, so notices never scan on page load. */
    const FOUND = 'seoprocookieconsent_migrate_found';
    /** One-off background refresh when FOUND is missing. */
    const REFRESH = 'seoprocookieconsent_migrate_refresh';

    /** This utility uses the base import sanitizer, not feature hooks. */
    public static function boot() {
    }

    /** Leftover kinds cleanup removes; "kept" is listed only. */
    const REMOVABLE = array('options', 'tables', 'cron', 'posts', 'terms');

    /** Refresh once, at the end of a request that activates, deactivates or deletes a source. */
    public static function watch() {
        add_action('activated_plugin', array(__CLASS__, 'plugin_changed'));
        add_action('deactivated_plugin', array(__CLASS__, 'plugin_changed'));
        add_action('deleted_plugin', array(__CLASS__, 'plugin_changed'));
        add_action(self::REFRESH, array(__CLASS__, 'refresh'));
    }

    /** @param string $file Plugin file. */
    public static function plugin_changed($file) {
        foreach (self::sources() as $source) {
            if (in_array(dirname((string) $file), $source::SLUGS, true) && !has_action('shutdown', array(__CLASS__, 'refresh'))) {
                add_action('shutdown', array(__CLASS__, 'refresh'));
            }
        }
    }

    /** Cached detection: key => {present, data, pages}; schedules one background refresh when missing. @return array|null */
    public static function found() {
        $found = get_option(self::FOUND, null);
        if (!is_array($found)) {
            if (!wp_next_scheduled(self::REFRESH)) {
                wp_schedule_single_event(time(), self::REFRESH);
            }
            return null;
        }
        return $found;
    }

    /** Whether a source has a complete import that was not undone. @param string $key Source. @return bool */
    public static function imported($key) {
        foreach ((array) get_option(self::IMPORTS, array()) as $record) {
            if (is_array($record) && $key === ($record['source'] ?? '') && 'complete' === ($record['state'] ?? '') && empty($record['undone'])) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string,SEOProCookieConsent_Migrate_Source> */
    public static function sources() {
        require_once __DIR__ . '/class-seoprocookieconsent-migrate-source.php';
        require_once __DIR__ . '/class-seoprocookieconsent-migrate-complianz.php';
        require_once __DIR__ . '/class-seoprocookieconsent-migrate-cookieyes.php';
        require_once __DIR__ . '/class-seoprocookieconsent-migrate-cookie-notice.php';
        require_once __DIR__ . '/class-seoprocookieconsent-migrate-cookieadmin.php';
        require_once __DIR__ . '/class-seoprocookieconsent-migrate-moove.php';
        require_once __DIR__ . '/class-seoprocookieconsent-migrate-wpconsent.php';
        require_once __DIR__ . '/class-seoprocookieconsent-migrate-real-cookie-banner.php';
        $sources = apply_filters('seoprocookieconsent_migrate_sources', array(
            'complianz'              => new SEOProCookieConsent_Migrate_Complianz(),
            'cookieyes'              => new SEOProCookieConsent_Migrate_CookieYes(),
            'cookie-notice'          => new SEOProCookieConsent_Migrate_Cookie_Notice(),
            'cookieadmin'            => new SEOProCookieConsent_Migrate_CookieAdmin(),
            'gdpr-cookie-compliance' => new SEOProCookieConsent_Migrate_Moove(),
            'wpconsent'              => new SEOProCookieConsent_Migrate_WPConsent(),
            'real-cookie-banner'     => new SEOProCookieConsent_Migrate_Real_Cookie_Banner(),
        ));
        return array_filter((array) $sources, function ($source) {
            return $source instanceof SEOProCookieConsent_Migrate_Source;
        });
    }

    /** @param string $key Source key. @return SEOProCookieConsent_Migrate_Source|WP_Error */
    private static function source($key) {
        $sources = self::sources();
        return $sources[$key] ?? new WP_Error('source', __('Unknown import source.', 'seoprocookieconsent'));
    }

    /** Refresh on the Import screen or an explicit action, never a visitor request. */
    public static function refresh() {
        $config = array();
        $previous = (array) get_option(self::CONFIG, array());
        $imports = (array) get_option(self::IMPORTS, array());
        $found = array();
        foreach (self::sources() as $key => $source) {
            $imported = false;
            foreach ($imports as $record) {
                $imported = $imported || ($key === $record['source'] && empty($record['undone']));
            }
            $detection = $source->detect();
            $left = $source->leftovers();
            $found[$key] = array(
                'present' => !empty($detection['present']),
                'data'    => (bool) array_filter(array_intersect_key($left, array_flip(self::REMOVABLE))),
                'pages'   => (bool) $source->pages(),
            );
            if (!isset($previous[$key]['off']) && ($detection['present'] || $imported)) {
                $config[$key] = $source->consent_cookie();
                // Once its settings are deleted a source cannot describe its cookie; keep what the import saw.
                if (!$config[$key] && $imported && !empty($previous[$key]['categories'])) {
                    $config[$key] = $previous[$key];
                }
            } elseif (isset($previous[$key]['off'])) {
                $config[$key] = array('off' => true);
            }
        }
        // Autoloaded: the consent script reads it on every front-end page.
        update_option(self::CONFIG, $config, true);
        // Admin notices only: not autoloaded.
        update_option(self::FOUND, $found, false);
    }

    /** Cached cookie descriptors only; no detection, scans or source queries. @return array */
    public static function front_config() {
        return array_filter((array) get_option(self::CONFIG, array()), function ($entry) {
            return is_array($entry) && empty($entry['off']);
        });
    }

    /** Historical automatic migration uses the same adapter mapping. @param array $options Settings. @return array */
    public static function legacy_settings(array $options) {
        $source = self::source('complianz');
        if (is_wp_error($source)) {
            return $options;
        }
        foreach ($source->settings() as $key => $value) {
            $options = self::import_setting($options, $key, $value);
        }
        return $options;
    }

    /** Read-only preview. @param string $key Source key. @return array|WP_Error */
    public static function plan($key) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('permission', __('You cannot import settings.', 'seoprocookieconsent'));
        }
        $source = self::source($key);
        if (is_wp_error($source)) {
            return $source;
        }
        $schema = SEOProCookieConsent_Settings::schema();
        $all = SEOProCookieConsent_Settings::all();
        $settings = array();
        foreach ($source->settings() as $name => $value) {
            if (isset($schema[$name]) && $all[$name] === $schema[$name]['default']) {
                $clean = self::import_setting(array(), $name, $value);
                if (isset($clean[$name]) && $clean[$name] !== $all[$name]) {
                    $settings[$name] = $clean[$name];
                }
            }
        }
        $scripts = array();
        foreach ($source->scripts() as $name => $lines) {
            if (!in_array($name, array('consent_block', 'consent_allow'), true)) {
                continue;
            }
            $existing = self::lines((string) $all[$name]);
            $scripts[$name] = array_values(array_diff(self::lines(implode("\n", $lines)), $existing));
        }
        return array('source' => $key, 'detection' => $source->detect(), 'settings' => $settings, 'scripts' => $scripts, 'pages' => $source->pages(), 'consent' => $source->consent_cookie(), 'leftovers' => $source->leftovers(), 'uninstall' => $source->uninstall_setting());
    }

    /** @param string $text Lines. @return string[] */
    private static function lines($text) {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n]+/', $text) ?: array()))));
    }

    /** All mutations share a fail-closed lock, with no unsafe stale-lock stealing. @param string $action Action. @param string $key Source. @param array $args Arguments. @return array|WP_Error */
    public static function run($action, $key, array $args = array()) {
        if (!current_user_can('manage_options') || (in_array($action, array('cleanup', 'cleanup-plan'), true) && !current_user_can('delete_plugins'))) {
            return new WP_Error('permission', __('You do not have permission for this action.', 'seoprocookieconsent'));
        }
        if (!add_option(self::LOCK, time(), '', false)) {
            return new WP_Error('locked', __('Another import action is running. If an interrupted action left a lock, review its import record before removing the lock.', 'seoprocookieconsent'));
        }
        try {
            if ('import' === $action) {
                return self::apply($key, (array) ($args['pages'] ?? array()));
            }
            if ('undo' === $action) {
                return self::undo($key);
            }
            if ('cleanup' === $action) {
                return self::cleanup($key, false, (string) ($args['confirm'] ?? ''));
            }
            if ('off' === $action) {
                $config = (array) get_option(self::CONFIG, array());
                $config[$key] = array('off' => true);
                update_option(self::CONFIG, $config, true);
                return array('carry_over' => false);
            }
            return new WP_Error('action', __('Unknown import action.', 'seoprocookieconsent'));
        } finally {
            delete_option(self::LOCK);
        }
    }

    /** @param string $key Source. @param array $confirmed Page IDs. @return array|WP_Error */
    private static function apply($key, array $confirmed) {
        $plan = self::plan($key);
        if (is_wp_error($plan)) {
            return $plan;
        }
        $before = SEOProCookieConsent_Settings::all();
        $after = $plan['settings'];
        foreach ($plan['scripts'] as $name => $lines) {
            if ($lines) {
                $after[$name] = implode("\n", array_merge(self::lines((string) $before[$name]), $lines));
            }
        }
        $pages = array();
        foreach ($plan['pages'] as $page) {
            if (!in_array($page['id'], array_map('intval', $confirmed), true)) {
                continue;
            }
            $post = get_post($page['id']);
            if (!$post || !current_user_can('edit_post', $post->ID) || !wp_revisions_enabled($post)) {
                return new WP_Error('page', __('A selected page cannot be edited with a saved revision.', 'seoprocookieconsent'));
            }
            $content = $post->post_content;
            foreach ($page['tokens'] as $token) {
                if (!$token['renderable']) {
                    continue;
                }
                if ($token['cookie']) {
                    $replacement = '<!-- wp:seoprocookieconsent/cookie-policy /-->';
                } elseif (isset($token['replace']) && is_string($token['replace'])) {
                    $replacement = $token['replace']; // Adapter-built markup, already sanitised.
                } else {
                    $html = 0 === strpos($token['token'], '[') ? do_shortcode($token['token']) : do_blocks($token['token']);
                    // An unfinished source wizard renders only a wrapper and comment: never freeze a blank document.
                    if ('' === trim(wp_strip_all_tags($html)) || $html === $token['token']) {
                        return new WP_Error('render', __('A source document could not render. It was not replaced.', 'seoprocookieconsent'));
                    }
                    $replacement = "<!-- wp:html -->\n" . wp_kses_post($html) . "\n<!-- /wp:html -->";
                }
                // A shortcode in core/shortcode must not become a nested invalid block.
                $wrapped = '<!-- wp:shortcode -->' . "\n" . $token['token'] . "\n" . '<!-- /wp:shortcode -->';
                $content = str_replace($wrapped, $replacement, $content);
                // Anywhere else, such as inside a paragraph, a shortcode becomes ours; a block comment there is invalid.
                $inline = array('<!-- wp:seoprocookieconsent/cookie-policy /-->' => '[spcc_cookie_policy]', '<!-- wp:seoprocookieconsent/cookie-settings /-->' => '[spcc_cookie_settings]');
                $content = str_replace($token['token'], 0 === strpos($token['token'], '[') && isset($inline[$replacement]) ? $inline[$replacement] : $replacement, $content);
            }
            if ($content !== $post->post_content) {
                $revision = self::revision($post);
                if (!$revision) {
                    return new WP_Error('revision', __('A page revision could not be saved. Nothing was replaced.', 'seoprocookieconsent'));
                }
                $pages[$post->ID] = array('revision' => $revision, 'before' => $post->post_content, 'after' => $content);
            }
        }
        $records = (array) get_option(self::IMPORTS, array());
        if (!$after && !$pages) {
            // Nothing left to bring across still counts as imported, so the notices move on to switching.
            if (!self::imported($key)) {
                $records[wp_generate_uuid4()] = array('source' => $key, 'time' => time(), 'before' => array(), 'after' => array(), 'lines' => array(), 'pages' => array(), 'state' => 'complete');
                update_option(self::IMPORTS, $records, false);
            }
            self::refresh();
            return array('changed' => false);
        }
        $id = wp_generate_uuid4();
        $record = array('source' => $key, 'time' => time(), 'before' => array_intersect_key($before, $after), 'after' => $after, 'lines' => $plan['scripts'], 'pages' => $pages, 'state' => 'applying');
        $records[$id] = $record;
        update_option(self::IMPORTS, $records, false);
        if (get_option(self::IMPORTS) !== $records) {
            return new WP_Error('journal', __('The undo record could not be saved.', 'seoprocookieconsent'));
        }
        foreach ($after as $name => $value) {
            SEOProCookieConsent_Settings::set($name, $value);
        }
        foreach ($pages as $page_id => $page) {
            $saved = wp_update_post(wp_slash(array('ID' => $page_id, 'post_content' => $page['after'])), true);
            if (is_wp_error($saved)) {
                return $saved; // The durable applying record remains available for undo.
            }
        }
        $records[$id]['state'] = 'complete';
        update_option(self::IMPORTS, $records, false);
        self::refresh();
        return array('changed' => true, 'id' => $id);
    }

    /**
     * A revision holding the current content. Core skips saving when the
     * newest revision already matches, which is normal after an editor save.
     *
     * @param WP_Post $post Page.
     * @return int Revision ID, or 0.
     */
    private static function revision($post) {
        $revision = wp_save_post_revision($post->ID);
        if ($revision && !is_wp_error($revision)) {
            return (int) $revision;
        }
        $latest = wp_get_post_revisions($post->ID, array('numberposts' => 1, 'orderby' => 'ID', 'order' => 'DESC'));
        $latest = $latest ? reset($latest) : null;
        return $latest instanceof WP_Post && $latest->post_content === $post->post_content ? (int) $latest->ID : 0;
    }

    /** Undo only imported values, never silently overwrite subsequent owner edits. @param string $id Import ID, or a source key for its newest import. @return array|WP_Error */
    private static function undo($id) {
        $records = (array) get_option(self::IMPORTS, array());
        if (!isset($records[$id])) {
            // WP-CLI: `undo cookieyes` means that source's newest import still in place.
            foreach ($records as $record_id => $record) {
                if (is_array($record) && $id === ($record['source'] ?? '') && empty($record['undone'])) {
                    $found = $record_id;
                }
            }
            $id = $found ?? $id;
        }
        if (!isset($records[$id]) || !empty($records[$id]['undone'])) {
            return new WP_Error('record', __('This import cannot be undone.', 'seoprocookieconsent'));
        }
        $record = $records[$id];
        foreach ($record['after'] as $key => $value) {
            $current = SEOProCookieConsent_Settings::get($key);
            if ($current !== $value && $current !== $record['before'][$key]) {
                return new WP_Error('conflict', __('Settings changed after import. Restore the imported values before undoing, so later edits are not lost.', 'seoprocookieconsent'));
            }
        }
        foreach ($record['pages'] as $page_id => $page) {
            $post = get_post($page_id);
            if (!$post || !current_user_can('edit_post', $page_id) || !wp_get_post_revision($page['revision']) || !in_array($post->post_content, array($page['before'], $page['after']), true)) {
                return new WP_Error('conflict', __('A page changed after import or its revision is missing. Undo was stopped to protect later edits.', 'seoprocookieconsent'));
            }
        }
        foreach ($record['pages'] as $page_id => $page) {
            if (!wp_restore_post_revision($page['revision'], array('post_content'))) {
                return new WP_Error('restore', __('A page revision could not be restored. The undo record was kept.', 'seoprocookieconsent'));
            }
        }
        foreach ($record['before'] as $key => $value) {
            SEOProCookieConsent_Settings::set($key, $value);
        }
        $records[$id]['undone'] = time();
        update_option(self::IMPORTS, $records, false);
        self::refresh();
        return array('undone' => $id);
    }

    /** Exact-inventory cleanup, never active plugins or network data. @param string $key Source. @param bool $dry_run Preview. @param string $confirm Inventory digest. @return array|WP_Error */
    public static function cleanup($key, $dry_run = true, $confirm = '') {
        if (!current_user_can('manage_options') || !current_user_can('delete_plugins')) {
            return new WP_Error('permission', __('Cleanup needs permission to manage options and delete plugins.', 'seoprocookieconsent'));
        }
        $source = self::source($key);
        if (is_wp_error($source)) {
            return $source;
        }
        if ($source->active()) {
            return new WP_Error('active', __('Deactivate the source on this site and network before removing leftover data.', 'seoprocookieconsent'));
        }
        $items = $source->leftovers();
        $digest = hash('sha256', (string) wp_json_encode($items));
        if ($dry_run) {
            return array('items' => $items, 'confirm' => $digest, 'network' => is_multisite() ? __('Network-wide data is not deleted.', 'seoprocookieconsent') : '');
        }
        if (!hash_equals($digest, $confirm)) {
            return new WP_Error('changed', __('The leftovers list changed. Review a fresh dry run before confirming.', 'seoprocookieconsent'));
        }
        global $wpdb;
        foreach ($items['tables'] ?? array() as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- explicitly confirmed, adapter-listed site-local source table.
            if (false === $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table))) {
                return new WP_Error('database', __('A source table could not be removed. Review a new dry run.', 'seoprocookieconsent'));
            }
        }
        foreach ($items['options'] ?? array() as $option) {
            delete_option($option);
        }
        foreach ($items['cron'] ?? array() as $hook) {
            wp_clear_scheduled_hook($hook);
        }
        // Source posts and terms, with their meta and relationships; nothing goes to the trash.
        foreach ($items['posts'] ?? array() as $post_id) {
            if (get_post((int) $post_id) && !wp_delete_post((int) $post_id, true)) {
                return new WP_Error('post', __('A source post could not be removed. Review a new dry run.', 'seoprocookieconsent'));
            }
        }
        foreach ($items['terms'] ?? array() as $taxonomy => $term_ids) {
            // An inactive source no longer registers its taxonomy, which core requires to delete terms.
            $registered = taxonomy_exists($taxonomy);
            if (!$registered) {
                register_taxonomy($taxonomy, array(), array('public' => false, 'rewrite' => false));
            }
            $failed = false;
            foreach ((array) $term_ids as $term_id) {
                $deleted = wp_delete_term((int) $term_id, $taxonomy);
                if (is_wp_error($deleted) || false === $deleted) {
                    $failed = true;
                    break;
                }
            }
            if (!$registered) {
                unregister_taxonomy($taxonomy);
            }
            if ($failed) {
                return new WP_Error('term', __('A source term could not be removed. Review a new dry run.', 'seoprocookieconsent'));
            }
        }
        $config = (array) get_option(self::CONFIG, array());
        $config[$key] = array('off' => true);
        update_option(self::CONFIG, $config, true);
        self::refresh();
        return array('removed' => $items);
    }

    /** Register the existing WP-CLI runtime, with explicit user/capability checks. */
    public static function cli() {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }
        WP_CLI::add_command('seoprocookieconsent migrate', function ($args, $assoc) {
            $action = $args[0] ?? 'plan';
            $key = $args[1] ?? 'complianz';
            if ('plan' === $action) {
                $result = self::plan($key);
            } elseif ('cleanup' === $action) {
                $result = self::cleanup($key, true);
                if (!is_wp_error($result) && !isset($assoc['dry-run'])) {
                    WP_CLI::confirm('Remove exactly the listed source data? This cannot be undone. Back up first. ' . wp_json_encode($result['items']), $assoc);
                    $result = self::run('cleanup', $key, array('confirm' => $result['confirm']));
                }
            } else {
                $result = self::run($action, $key, array('pages' => isset($assoc['pages']) ? explode(',', $assoc['pages']) : array()));
            }
            if (is_wp_error($result)) {
                WP_CLI::error($result->get_error_message());
            }
            WP_CLI::line((string) wp_json_encode($result, JSON_PRETTY_PRINT));
        });
    }
}
