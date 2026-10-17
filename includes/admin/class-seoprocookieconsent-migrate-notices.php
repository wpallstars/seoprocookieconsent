<?php
/**
 * Move here notices: one next step per other cookie plugin SEO Pro Cookie
 * Consent can import from, from the moment its data is found until the
 * plugin and its leftovers are gone.
 *
 * - import: the source is active and nothing is imported yet, or it is
 *   inactive (or deleted) with its data still here;
 * - switch: imported, the source still active: check, then deactivate;
 * - remove: inactive or deleted, imported, leftovers remain.
 *
 * Shown on the Plugins screen (a top notice and a note under each source's
 * row) and on the settings screen (the top notice), to people who can
 * activate plugins. Reads only stored plugin lists, the import records and
 * the detection SEOProCookieConsent_Migrate::refresh() cached: no source
 * table or page content is read on page load. For sources shown here, the
 * Plugins screen's "can do the job of these plugins" lines are left out, so
 * nobody is told to deactivate before importing.
 *
 * "Hide" hides the steps listed at the time for that person; a source shows
 * again when its step changes.
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

final class SEOProCookieConsent_Migrate_Notices {

    /** admin-post action, also the nonce action. */
    const HIDE = 'seoprocookieconsent_hide_migrate';

    /** User meta: list of "source:state" items the person hid. */
    const HIDDEN = 'seoprocookieconsent_migrate_hidden';

    /** Steps for core's replaced-plugins lines, hidden for sources shown here. */
    const CORE_STEPS = array('deactivate', 'partial', 'switch_on', 'switch_on_partial', 'delete', 'switch_on_inactive');

    /** Register hooks. */
    public static function boot() {
        // After SEOProCookieConsent_Replaced_Plugins::load_screen() (priority 10).
        add_action('load-plugins.php', array(__CLASS__, 'plugins_screen'), 20);
        add_action('current_screen', array(__CLASS__, 'settings_screen'));
        add_action('admin_post_' . self::HIDE, array(__CLASS__, 'hide'));
    }

    /** Whether this person and screen get the notices. @return bool */
    private static function allowed() {
        return !is_network_admin() && current_user_can('activate_plugins');
    }

    /** Plugins screen: the top notice, row notes, and core's lines without these sources. */
    public static function plugins_screen() {
        if (!self::allowed() || !self::states()) {
            return;
        }
        add_action('admin_notices', array(__CLASS__, 'notice'), 9);
        if (has_action('admin_notices', array('SEOProCookieConsent_Replaced_Plugins', 'notice'))) {
            remove_action('admin_notices', array('SEOProCookieConsent_Replaced_Plugins', 'notice'));
            add_action('admin_notices', array(__CLASS__, 'core_notice'));
        }
        if (has_action('after_plugin_row', array('SEOProCookieConsent_Replaced_Plugins', 'row_note'))) {
            remove_action('after_plugin_row', array('SEOProCookieConsent_Replaced_Plugins', 'row_note'), 10);
        }
        add_action('after_plugin_row', array(__CLASS__, 'row_note'), 10, 1);
        if (!has_action('admin_head', array('SEOProCookieConsent_Replaced_Plugins', 'row_note_style'))) {
            add_action('admin_head', array('SEOProCookieConsent_Replaced_Plugins', 'row_note_style'));
        }
    }

    /** @param WP_Screen $screen Current screen. */
    public static function settings_screen($screen) {
        if ($screen instanceof WP_Screen && SEOProCookieConsent_Admin_Manager::hook() === $screen->id && self::allowed()) {
            add_action('admin_notices', array(__CLASS__, 'notice'), 9);
        }
    }

    /** Core's replaced-plugins notice, with the sources shown here hidden. */
    public static function core_notice() {
        add_filter('get_user_metadata', array(__CLASS__, 'core_hidden'), 10, 3);
        try {
            SEOProCookieConsent_Replaced_Plugins::notice();
        } finally {
            remove_filter('get_user_metadata', array(__CLASS__, 'core_hidden'), 10);
        }
    }

    /**
     * While core's notice reads what the person hid, add every step of the
     * sources shown here.
     *
     * @param mixed  $value     Short-circuit value.
     * @param int    $object_id User ID.
     * @param string $meta_key  Meta key.
     * @return mixed
     */
    public static function core_hidden($value, $object_id, $meta_key) {
        if (SEOProCookieConsent_Replaced_Plugins::HIDDEN !== $meta_key) {
            return $value;
        }
        remove_filter('get_user_metadata', array(__CLASS__, 'core_hidden'), 10);
        $hidden = (array) get_user_meta((int) $object_id, $meta_key, true);
        add_filter('get_user_metadata', array(__CLASS__, 'core_hidden'), 10, 3);
        foreach (self::states() as $item) {
            foreach ($item['source']::SLUGS as $slug) {
                foreach (self::CORE_STEPS as $step) {
                    $hidden[] = $slug . ':' . $step;
                }
            }
        }
        return array(array_values(array_unique(array_filter($hidden, 'is_string'))));
    }

    /** @param string $file Plugin file. */
    public static function row_note($file) {
        global $wp_list_table;
        $item = null;
        foreach (self::states() as $state) {
            $item = in_array(dirname((string) $file), $state['source']::SLUGS, true) ? $state : $item;
        }
        if (!$item) {
            SEOProCookieConsent_Replaced_Plugins::row_note($file);
            return;
        }
        $columns = ($wp_list_table instanceof WP_List_Table) ? $wp_list_table->get_column_count() : 4;
        $active  = in_array($file, SEOProCookieConsent_Feature::stored_active_plugins(), true) ? ' active' : ' inactive';
        printf(
            '<tr class="plugin-update-tr spcc-replaced-row%1$s"><td colspan="%2$d" class="plugin-update colspanchange"><div class="notice inline notice-info notice-alt"><p>%3$s</p></div></td></tr>',
            esc_attr($active),
            (int) $columns,
            self::line($item) // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts in line().
        );
    }

    /** The top notice. */
    public static function notice() {
        $hidden = (array) get_user_meta(get_current_user_id(), self::HIDDEN, true);
        $items  = array_filter(self::states(), function ($item) use ($hidden) {
            return !in_array($item['key'] . ':' . $item['state'], $hidden, true);
        });
        if (!$items) {
            return;
        }
        $keys = array_map(function ($item) {
            return $item['key'] . ':' . $item['state'];
        }, $items);
        $hide = wp_nonce_url(add_query_arg(array('action' => self::HIDE, 'items' => implode(',', $keys)), admin_url('admin-post.php')), self::HIDE);
        echo '<div class="notice notice-info spcc-migrate-notice"><p><strong>' . esc_html__('Move to SEO Pro Cookie Consent:', 'seoprocookieconsent') . '</strong></p><ul class="ul-disc">';
        foreach ($items as $item) {
            echo '<li>' . self::line($item) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts in line().
        }
        printf('</ul><p><a href="%1$s">%2$s</a></p></div>', esc_url($hide), esc_html__('Hide', 'seoprocookieconsent'));
    }

    /**
     * One source's step, with its links.
     *
     * @param array $item From states().
     * @return string HTML.
     */
    private static function line(array $item) {
        $name    = esc_html($item['name']);
        $manage  = current_user_can('manage_options');
        $links   = array();
        $consent = (bool) SEOProCookieConsent_Settings::get(SEOProCookieConsent_Consent::KEY);

        if ('import' === $item['state']) {
            if ($item['active']) {
                /* translators: %s: other cookie plugin's name */
                $text = sprintf(esc_html__('%s is asking your visitors now. Bring its settings across, then switch.', 'seoprocookieconsent'), $name);
            } else {
                /* translators: %s: other cookie plugin's name */
                $text = sprintf(esc_html__('%s is not active, but its settings are still here. Bring them across.', 'seoprocookieconsent'), $name);
                if ($item['pages']) {
                    $text .= ' ' . esc_html__('Pages that show its documents may already be blank.', 'seoprocookieconsent');
                }
            }
            if ($manage) {
                /* translators: %s: other cookie plugin's name */
                $links[] = self::link(SEOProCookieConsent_Admin_Manager::tab_url('import', array('source' => $item['key'])) . '#spcc-import-' . $item['key'], sprintf(__('Import from %s', 'seoprocookieconsent'), $item['name']));
            }
        } elseif ('switch' === $item['state']) {
            if ($consent) {
                $text = $item['carry']
                    /* translators: %s: other cookie plugin's name */
                    ? sprintf(esc_html__('Check the import, then deactivate %s: SEO Pro Cookie Consent takes over at once and visitors keep their choices.', 'seoprocookieconsent'), $name)
                    /* translators: %s: other cookie plugin's name */
                    : sprintf(esc_html__('Check the import, then deactivate %s: SEO Pro Cookie Consent takes over at once.', 'seoprocookieconsent'), $name);
            } else {
                /* translators: %s: other cookie plugin's name */
                $text = sprintf(esc_html__('Check the import and turn on Cookie consent, then deactivate %s: SEO Pro Cookie Consent takes over at once.', 'seoprocookieconsent'), $name);
            }
            $text .= ' ' . esc_html__('If it has a delete-data setting, turn that on first.', 'seoprocookieconsent');
            if ($manage) {
                $links[] = self::link(SEOProCookieConsent_Admin_Manager::tab_url('import') . '#spcc-import-history', __('Check the import', 'seoprocookieconsent'));
                if (!$consent && SEOProCookieConsent_Settings::can_change()) {
                    $links[] = self::link(SEOProCookieConsent_Admin_Manager::tab_url('consent'), __('Turn on Cookie consent', 'seoprocookieconsent'));
                }
            }
            if ($consent) {
                foreach ($item['site_active'] as $file => $plugin) {
                    if (current_user_can('deactivate_plugin', $file)) {
                        /* translators: %s: plugin name */
                        $links[] = self::link(SEOProCookieConsent_Replaced_Plugins::deactivate_url($file), sprintf(__('Deactivate %s', 'seoprocookieconsent'), $plugin));
                    }
                }
            }
        } else {
            /* translators: %s: other cookie plugin's name */
            $text = sprintf(esc_html__('%s left data on the site.', 'seoprocookieconsent'), $name);
            if (!is_multisite() && current_user_can('delete_plugins')) {
                foreach ($item['inactive'] as $file => $plugin) {
                    $url = wp_nonce_url(add_query_arg(array('action' => 'delete-selected', 'checked[]' => $file, 'plugin_status' => 'all'), self_admin_url('plugins.php')), 'bulk-plugins');
                    /* translators: %s: plugin name */
                    $links[] = self::link($url, sprintf(__('Delete %s', 'seoprocookieconsent'), $plugin));
                }
            }
            if ($manage && current_user_can('delete_plugins')) {
                $links[] = self::link(SEOProCookieConsent_Admin_Manager::tab_url('import', array('cleanup' => $item['key'])) . '#spcc-cleanup-' . $item['key'], __('Remove leftover data', 'seoprocookieconsent'));
            }
        }
        return $text . ($links ? ' ' . implode(' · ', $links) : '');
    }

    /** @param string $url Unescaped URL. @param string $label Plain text. @return string HTML. */
    private static function link($url, $label) {
        return sprintf('<a href="%1$s">%2$s</a>', esc_url($url), esc_html($label));
    }

    /**
     * Every source with a step, keyed by source key.
     *
     * @return array<string,array{key:string,source:SEOProCookieConsent_Migrate_Source,name:string,state:string,active:bool,pages:bool,carry:bool,site_active:array<string,string>,inactive:array<string,string>}>
     */
    private static function states() {
        static $states = null;
        if (null !== $states) {
            return $states;
        }
        $states = array();
        $found  = SEOProCookieConsent_Migrate::found();
        $carry  = SEOProCookieConsent_Migrate::front_config();
        $site   = SEOProCookieConsent_Feature::stored_active_plugins();
        $plugins = self::installed();
        foreach (SEOProCookieConsent_Migrate::sources() as $key => $source) {
            $active   = $source->active();
            $imported = SEOProCookieConsent_Migrate::imported($key);
            $seen     = is_array($found) && isset($found[$key]) ? (array) $found[$key] : null;
            $state    = '';
            if ($active) {
                $state = $imported ? 'switch' : 'import';
            } elseif ($seen && !$imported && !empty($seen['present'])) {
                $state = 'import';
            } elseif ($seen && $imported && !empty($seen['data'])) {
                $state = 'remove';
            }
            if ('' === $state) {
                continue;
            }
            $site_active = array();
            $inactive    = array();
            foreach ($plugins as $file => $plugin) {
                if (!in_array(dirname($file), $source::SLUGS, true)) {
                    continue;
                }
                if (in_array($file, $site, true)) {
                    $site_active[$file] = $plugin;
                } elseif (!isset(SEOProCookieConsent_Feature::active_plugins()[dirname($file)])) {
                    $inactive[$file] = $plugin;
                }
            }
            $states[$key] = array(
                'key'         => (string) $key,
                'source'      => $source,
                'name'        => (string) $source::NAME,
                'state'       => $state,
                'active'      => $active,
                'pages'       => !empty($seen['pages']),
                'carry'       => isset($carry[$key]),
                'site_active' => $site_active,
                'inactive'    => $inactive,
            );
        }
        return $states;
    }

    /** Installed plugins in a folder: file => short name (the settings' `replaces` name, else its header's). @return array<string,string> */
    private static function installed() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $names = array();
        foreach (SEOProCookieConsent_Settings::schema() as $field) {
            if (!empty($field['replaces']) && is_array($field['replaces'])) {
                $names += array_map('strval', $field['replaces']);
            }
        }
        $installed = array();
        foreach (get_plugins() as $file => $data) {
            $slug = dirname($file);
            if ('.' !== $slug) {
                $installed[$file] = $names[$slug] ?? (isset($data['Name']) && '' !== $data['Name'] ? (string) $data['Name'] : $slug);
            }
        }
        return $installed;
    }

    /** admin-post: hide the listed steps for this person. */
    public static function hide() {
        check_admin_referer(self::HIDE);
        if (!current_user_can('activate_plugins')) {
            wp_die(esc_html__('You are not allowed to manage plugins on this site.', 'seoprocookieconsent'), '', array('response' => 403));
        }
        $items  = isset($_GET['items']) ? explode(',', sanitize_text_field(wp_unslash($_GET['items']))) : array();
        $items  = array_filter($items, function ($item) {
            return (bool) preg_match('/^[a-z0-9_-]+:(import|switch|remove)$/', $item);
        });
        $hidden = (array) get_user_meta(get_current_user_id(), self::HIDDEN, true);
        update_user_meta(get_current_user_id(), self::HIDDEN, array_values(array_unique(array_filter(array_merge($hidden, $items)))));
        $back = wp_get_referer();
        wp_safe_redirect($back ? $back : self_admin_url('plugins.php'));
        exit;
    }
}
