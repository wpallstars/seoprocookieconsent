<?php
/**
 * What makes this plugin SEO Pro Cookie Consent: its features, settings tabs,
 * links, settings history and the helpers only it needs.
 *
 * The other files in includes/ and admin/ that the starter plugin also has
 * (the feature registry, settings store, base feature and admin screen)
 * read this class and differ from the starter's only in names. Keep
 * anything that only this plugin needs here, in features or in its own
 * files loaded from here.
 *
 * The starter has no features yet, so its settings screen shows the first
 * settings tab with "No settings yet" and the Read Me tab. Add a feature:
 * STANDARDS.md → Structure and README.md → Developers.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: 2026 Marcus Quinn
 * Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
 * Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
 *
 * @package SEOProCookieConsent
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SEOProCookieConsent_Setup {

    /**
     * Built-in features, in the order their cards appear within each tab.
     * Each lives in includes/features/class-{lowercase-hyphenated-name}.php,
     * for example SEOProCookieConsent_Example in class-seoprocookieconsent-example.php.
     */
    const FEATURES = array(
        'SEOProCookieConsent_Consent',
        'SEOProCookieConsent_Policy',
        'SEOProCookieConsent_Terms',
    );

    /** Handle of the editor script for the plugin's blocks. */
    const BLOCKS_SCRIPT = 'seoprocookieconsent-blocks';

    /**
     * Features that some builds leave out, loaded only when their file is
     * present (for example one listed in .distignore-wporg).
     */
    const OPTIONAL_FEATURES = array();

    /**
     * Settings version. SEOProCookieConsent_Settings::maybe_migrate() runs
     * migrate() below and every feature's migrate() once per version.
     * After a release, bump it for a new or changed import and add a line.
     *
     * v1: first version.
     * v2: imports Complianz's region and consent expiry settings.
     * v3: imports Complianz Terms & Conditions identity and contact answers.
     */
    const DB_VERSION = 3;

    /**
     * Renamed tab slugs, old => new. Settings that still use an old slug
     * land on the new tab, and old admin links open the new tab.
     */
    const RENAMED_TABS = array();

    /**
     * Slug of the plugin's own top-level menu (add_menu_page()), when it
     * has one: the settings screen is then Settings, the last item in that
     * menu, and not in WordPress's Settings menu. Empty: Settings → SEO Pro Cookie Consent.
     */
    const MENU_PARENT = '';

    /**
     * Links in the settings screen header; leave one out for no button.
     *
     * - source:  the plugin's code (its GitHub repository)
     * - support: where people report problems (the plugin's GitHub issues)
     * - donate:  where people can support the maker
     *
     * @return array<string,string>
     */
    public static function header_links() {
        return array(
            'source'  => 'https://github.com/wpallstars/seoprocookieconsent',
            'support' => 'https://github.com/wpallstars/seoprocookieconsent/issues',
            'donate'  => 'https://buymeacoffee.com/marcusquinn',
        );
    }

    /**
     * Load the plugin's own helpers, before the features.
     */
    public static function load() {
        require_once SEOPROCOOKIECONSENT_DIR . 'includes/class-seoprocookieconsent-services.php';
        require_once SEOPROCOOKIECONSENT_DIR . 'includes/class-seoprocookieconsent-blocker.php';
        require_once SEOPROCOOKIECONSENT_DIR . 'includes/class-seoprocookieconsent-records.php';
        SEOProCookieConsent_Records::boot();
        require_once SEOPROCOOKIECONSENT_DIR . 'includes/migrate/class-seoprocookieconsent-migrate.php';
        SEOProCookieConsent_Migrate::cli();
        SEOProCookieConsent_Migrate::watch();
        if (is_admin()) {
            require_once SEOPROCOOKIECONSENT_DIR . 'includes/admin/class-seoprocookieconsent-import-tab.php';
            SEOProCookieConsent_Import_Tab::boot();
        }
    }

    /**
     * A plugin file's version for its asset URL: its modified time, or the
     * plugin version when that can't be read.
     *
     * @param string $file Path relative to the plugin folder.
     * @return string
     */
    public static function asset_version($file) {
        $time = file_exists(SEOPROCOOKIECONSENT_DIR . $file) ? filemtime(SEOPROCOOKIECONSENT_DIR . $file) : false;
        return false === $time ? SEOPROCOOKIECONSENT_VERSION : (string) $time;
    }

    /**
     * Register a server-rendered block, seoprocookieconsent/{name}, shown
     * in the editor with ServerSideRender (assets/js/blocks.js).
     *
     * Settings: `attributes` maps each attribute to `type` (boolean or
     * string), `default` and `label`, plus `options` (value => label) for a
     * choice or `placeholder` for text. They show in the block's sidebar
     * and reach $render as its first argument.
     *
     * @param string   $name   Block name without the namespace.
     * @param array    $meta   title, description, icon (a Dashicon name), attributes.
     * @param callable $render Returns the block's HTML from its attributes.
     */
    public static function register_block($name, array $meta, callable $render) {
        if (!function_exists('register_block_type')) {
            return;
        }
        if (!wp_script_is(self::BLOCKS_SCRIPT, 'registered')) {
            wp_register_script(
                self::BLOCKS_SCRIPT,
                SEOPROCOOKIECONSENT_URL . 'assets/js/blocks.js',
                array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render'),
                self::asset_version('assets/js/blocks.js'),
                true
            );
        }
        $controls   = array();
        $attributes = array();
        foreach (isset($meta['attributes']) ? (array) $meta['attributes'] : array() as $key => $attribute) {
            $type               = 'boolean' === $attribute['type'] ? 'boolean' : 'string';
            $attributes[$key]   = array('type' => $type, 'default' => 'boolean' === $type ? (bool) $attribute['default'] : (string) $attribute['default']);
            $controls[]         = array(
                'key'         => (string) $key,
                'type'        => isset($attribute['options']) ? 'select' : $type,
                'label'       => (string) $attribute['label'],
                'options'     => isset($attribute['options']) ? self::block_options((array) $attribute['options']) : array(),
                'placeholder' => isset($attribute['placeholder']) ? (string) $attribute['placeholder'] : '',
            );
        }
        $block = array(
            'name'        => 'seoprocookieconsent/' . $name,
            'title'       => (string) $meta['title'],
            'description' => (string) $meta['description'],
            'icon'        => (string) $meta['icon'],
            'attributes'  => $attributes,
            'controls'    => $controls,
            'panel'       => __('Settings', 'seoprocookieconsent'),
        );
        wp_add_inline_script(self::BLOCKS_SCRIPT, 'window.spccBlocks=(window.spccBlocks||[]).concat(' . wp_json_encode(array($block)) . ');', 'before');
        register_block_type($block['name'], array(
            'api_version'           => '2',
            'title'                 => $block['title'],
            'description'           => $block['description'],
            'icon'                  => $block['icon'],
            'category'              => 'widgets',
            'attributes'            => $attributes,
            'supports'              => array('html' => false),
            'editor_script_handles' => array(self::BLOCKS_SCRIPT),
            'render_callback'       => function ($block_attributes = array()) use ($render) {
                return (string) call_user_func($render, is_array($block_attributes) ? $block_attributes : array());
            },
        ));
    }

    /**
     * A choice's options as SelectControl reads them.
     *
     * @param array<string,string> $options value => label.
     * @return array<int,array{value:string,label:string}>
     */
    private static function block_options(array $options) {
        $list = array();
        foreach ($options as $value => $label) {
            $list[] = array('value' => (string) $value, 'label' => (string) $label);
        }
        return $list;
    }

    /**
     * Whether a block attribute or shortcode attribute is on: true, or
     * text other than 0, false, no or off.
     *
     * @param mixed $value Value.
     * @return bool
     */
    public static function flag($value) {
        if (is_bool($value)) {
            return $value;
        }
        return !in_array(strtolower(trim((string) $value)), array('', '0', 'false', 'no', 'off'), true);
    }

    /**
     * Register the helpers' hooks, after the settings store's.
     */
    public static function init() {
    }

    /**
     * Imports that belong to no feature, run once per DB_VERSION before
     * the features' own (for example settings from the plugin's earlier
     * names). Never write or delete other plugins' options.
     *
     * @param array $options      Stored settings (raw, without defaults).
     * @param int   $from_version Stored settings version before this upgrade.
     * @return array
     */
    public static function migrate(array $options, $from_version) {
        unset($from_version);
        return $options;
    }

    /**
     * Settings tabs, in navigation order. A tab shows only when a setting
     * uses it; with no settings at all, the first one shows, empty.
     *
     * @return array<string,array{label:string,description:string}>
     */
    public static function settings_tabs() {
        return array(
            'consent'  => array(
                'label'       => __('Consent', 'seoprocookieconsent'),
                'description' => __('Hold back cookies until visitors allow them, with a short banner only where it is needed.', 'seoprocookieconsent'),
            ),
            'policies' => array(
                'label'       => __('Policies', 'seoprocookieconsent'),
                'description' => __('Cookie policy and terms and conditions, inside pages you write. Review before publishing; not legal advice.', 'seoprocookieconsent'),
            ),
        );
    }

    /**
     * Admin requests: load and start the plugin's own admin parts (other
     * tabs with the seoprocookieconsent_admin_tabs filter, scripts with the
     * seoprocookieconsent_admin_enqueue action).
     */
    public static function admin() {
        // Move here: one next step per other cookie plugin, from import to removal.
        require_once SEOPROCOOKIECONSENT_DIR . 'includes/admin/class-seoprocookieconsent-migrate-notices.php';
        SEOProCookieConsent_Migrate_Notices::boot();
    }
}
