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

    /** This utility uses the base import sanitizer, not feature hooks. */
    public static function boot() {
    }

    /** @return array<string,SEOProCookieConsent_Migrate_Source> */
    public static function sources() {
        require_once __DIR__ . '/class-seoprocookieconsent-migrate-source.php';
        require_once __DIR__ . '/class-seoprocookieconsent-migrate-complianz.php';
        $sources = apply_filters('seoprocookieconsent_migrate_sources', array('complianz' => new SEOProCookieConsent_Migrate_Complianz()));
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
        foreach (self::sources() as $key => $source) {
            $imported = false;
            foreach ($imports as $record) {
                $imported = $imported || ($key === $record['source'] && empty($record['undone']));
            }
            if (!isset($previous[$key]['off']) && ($source->detect()['present'] || $imported)) {
                $config[$key] = $source->consent_cookie();
            } elseif (isset($previous[$key]['off'])) {
                $config[$key] = array('off' => true);
            }
        }
        update_option(self::CONFIG, $config, false);
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
                update_option(self::CONFIG, $config, false);
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
                $content = str_replace(array($wrapped, $token['token']), $replacement, $content);
            }
            if ($content !== $post->post_content) {
                $revision = self::revision($post);
                if (!$revision) {
                    return new WP_Error('revision', __('A page revision could not be saved. Nothing was replaced.', 'seoprocookieconsent'));
                }
                $pages[$post->ID] = array('revision' => $revision, 'before' => $post->post_content, 'after' => $content);
            }
        }
        if (!$after && !$pages) {
            self::refresh();
            return array('changed' => false);
        }
        $records = (array) get_option(self::IMPORTS, array());
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

    /** Undo only imported values, never silently overwrite subsequent owner edits. @param string $id Import ID. @return array|WP_Error */
    private static function undo($id) {
        $records = (array) get_option(self::IMPORTS, array());
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
        $config = (array) get_option(self::CONFIG, array());
        $config[$key] = array('off' => true);
        update_option(self::CONFIG, $config, false);
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
