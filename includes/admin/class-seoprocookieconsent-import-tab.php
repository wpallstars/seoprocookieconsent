<?php
/**
 * Import screen: read-only plans, individually confirmed pages, undo and cleanup.
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

final class SEOProCookieConsent_Import_Tab {
    /** Hook only the site admin. */
    public static function boot() {
        add_filter('seoprocookieconsent_admin_tabs', array(__CLASS__, 'tabs'));
        add_action('admin_post_seoprocookieconsent_import', array(__CLASS__, 'handle'));
        add_action('seoprocookieconsent_admin_enqueue', array(__CLASS__, 'enqueue'));
    }

    /** @param array $tabs Tabs. @return array */
    public static function tabs($tabs) {
        // A group is required for the tab bar; not preloaded, as it reads source data.
        $tabs['import'] = array('label' => __('Import', 'seoprocookieconsent'), 'group' => 'settings', 'render' => array(__CLASS__, 'render'), 'capability' => 'manage_options');
        return $tabs;
    }

    /** Native controls use existing admin styles in both themes. */
    public static function enqueue() {
        wp_enqueue_script('seoprocookieconsent-import', SEOPROCOOKIECONSENT_URL . 'admin/js/seoprocookieconsent-import.js', array(), SEOProCookieConsent_Setup::asset_version('admin/js/seoprocookieconsent-import.js'), true);
    }

    /** @param string $action Action. @param string $key Source/import ID. */
    private static function fields($action, $key) {
        echo '<input type="hidden" name="action" value="seoprocookieconsent_import">';
        printf('<input type="hidden" name="operation" value="%s"><input type="hidden" name="source" value="%s">', esc_attr($action), esc_attr($key));
        wp_nonce_field('seoprocookieconsent_import');
    }

    /** No source values or HTML are output unescaped. */
    public static function render() {
        if (!current_user_can('manage_options') || is_network_admin()) {
            return;
        }
        SEOProCookieConsent_Migrate::refresh();
        echo '<section class="spcc-import"><h2>' . esc_html__('Move here', 'seoprocookieconsent') . '</h2>';
        echo '<p>' . esc_html__('Review the dry run below. Import fills only default settings and adds missing script rules. Pages change only when selected. Check the banner and pages, then deactivate and delete the old plugin. Purge page caches after switching.', 'seoprocookieconsent') . '</p>';
        $found = false;
        foreach (SEOProCookieConsent_Migrate::sources() as $key => $source) {
            $plan = SEOProCookieConsent_Migrate::plan($key);
            if (is_wp_error($plan) || !$plan['detection']['present']) {
                continue;
            }
            $found = true;
            // The Move here notices link to each source by this ID.
            printf('<h3 id="spcc-import-%3$s">%1$s — %2$s</h3>', esc_html($source::NAME), esc_html($plan['detection']['state']), esc_attr($key));
            // Adapter notes: modes kept outside the site, and steps only the owner can take.
            foreach ((array) ($plan['detection']['notes'] ?? array()) as $note) {
                echo '<p class="description">' . esc_html((string) $note) . '</p>';
            }
            echo '<details open><summary>' . esc_html__('Dry run: settings and script rules to add', 'seoprocookieconsent') . '</summary>';
            self::listing(array_merge($plan['settings'], $plan['scripts']));
            echo '</details><details><summary>' . esc_html__('Consent cookie mapping', 'seoprocookieconsent') . '</summary>';
            if ($plan['consent']) {
                self::listing($plan['consent']);
            } else {
                echo '<p>' . esc_html__('No choices carry over: visitors are asked again.', 'seoprocookieconsent') . '</p>';
            }
            echo '<p>' . esc_html__('Only complete explicit choices carry over; dismissing the banner does not allow anything. Embeds without an equivalent category remain off. Carry-over starts only after the old banner is inactive.', 'seoprocookieconsent') . '</p></details>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            self::fields('import', $key);
            echo '<fieldset><legend>' . esc_html__('Policy pages (select each page to confirm conversion)', 'seoprocookieconsent') . '</legend>';
            foreach ($plan['pages'] as $page) {
                printf('<p><label><input type="checkbox" name="pages[]" value="%1$d"> %2$s</label></p><ul>', (int) $page['id'], esc_html($page['title']));
                foreach ($page['tokens'] as $token) {
                    $descriptions = array(
                        'cookie'   => __('Replace with Cookie policy block, and turn on Cookie policy (Policies tab) so it shows.', 'seoprocookieconsent'),
                        'settings' => __('Replace with Cookie settings button block.', 'seoprocookieconsent'),
                        'static'   => __('Keep your edited document as editable HTML, with a revision.', 'seoprocookieconsent'),
                        'empty'    => __('No document is selected, so it shows nothing. Remove it yourself.', 'seoprocookieconsent'),
                        'manual'   => __('Form or live list with no direct equivalent. Not changed: replace or remove it yourself.', 'seoprocookieconsent'),
                        'gate'     => __('Content shown only after acceptance. Not changed: take its scripts out of the shortcode yourself; services this plugin recognises, and your script rules, are then held back.', 'seoprocookieconsent'),
                        'other'    => __('Banner button, link or status with no equivalent here. Not changed: replace or remove it yourself.', 'seoprocookieconsent'),
                        'link'     => __('Make this link or button open Cookie settings, keeping its text.', 'seoprocookieconsent'),
                        'held'     => __('Keep the content and remove the tags around it. Its scripts are added to Also hold back, unless this plugin already recognises them.', 'seoprocookieconsent'),
                        'unwrap'   => __('Keep the content and remove the tags around it.', 'seoprocookieconsent'),
                        'hosted'   => __('Hosted document, button or widget that stops working when the old plugin or account goes. Not changed: replace it with your own page or link, or remove it yourself.', 'seoprocookieconsent'),
                        'marked'   => __('Marked to wait for the old banner, so it never loads without it. Not changed: make it a normal script or embed again yourself.', 'seoprocookieconsent'),
                    );
                    $kind = (string) ($token['kind'] ?? ($token['cookie'] ? 'cookie' : 'render'));
                    $description = $descriptions[$kind] ?? ($token['renderable'] ? __('Freeze the rendered document as editable HTML, with a revision.', 'seoprocookieconsent') : __('Cannot render while the source is inactive: rewrite this document or reactivate the source before importing.', 'seoprocookieconsent'));
                    printf('<li><code>%1$s</code> — %2$s</li>', esc_html(mb_strlen($token['token']) > 160 ? mb_substr($token['token'], 0, 160) . '…' : $token['token']), esc_html($description));
                }
                echo '</ul>';
            }
            echo '</fieldset>';
            submit_button(__('Import', 'seoprocookieconsent'), 'primary', 'submit', false);
            echo '</form><p>' . esc_html($plan['uninstall']) . '</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            self::fields('off', $key);
            submit_button(__('Stop carrying over old consent', 'seoprocookieconsent'), 'secondary', 'submit', false);
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only opens a section; the notices' "Remove leftover data" link.
            $open = isset($_GET['cleanup']) && sanitize_key(wp_unslash($_GET['cleanup'])) === $key ? ' open' : '';
            printf('</form><details id="spcc-cleanup-%1$s"%2$s><summary>%3$s</summary>', esc_attr($key), esc_attr($open), esc_html__('Remove leftover data', 'seoprocookieconsent'));
            self::listing($plan['leftovers']);
            if (!empty($plan['leftovers']['kept'])) {
                echo '<p>' . esc_html__('Kept: old consent records, data requests and anything not known to be generated. Export records from the old plugin before deleting it if you must keep them, then remove these tables or folders yourself after your retention period.', 'seoprocookieconsent') . '</p>';
            }
            $cleanup = SEOProCookieConsent_Migrate::cleanup($key, true);
            $removable = array_filter(array_intersect_key($plan['leftovers'], array_flip(SEOProCookieConsent_Migrate::REMOVABLE)));
            if (!$removable) {
                echo '<p>' . esc_html__('Nothing to remove.', 'seoprocookieconsent') . '</p>';
            } elseif (is_wp_error($cleanup)) {
                echo '<p>' . esc_html($cleanup->get_error_message()) . '</p>';
            } else {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-spcc-cleanup>';
                self::fields('cleanup', $key);
                printf('<input type="hidden" name="confirm" value="%s">', esc_attr($cleanup['confirm']));
                echo '<p><label><input type="checkbox" required name="ack" value="1"> ' . esc_html__('Remove exactly the listed data from this site. This cannot be undone. Make a backup first. Network-wide data is not removed.', 'seoprocookieconsent') . '</label></p>';
                submit_button(__('Remove leftover data', 'seoprocookieconsent'), 'delete', 'submit', false);
                echo '</form>';
            }
            echo '</details><hr>';
        }
        if (!$found) {
            echo '<p>' . esc_html__('No supported source data found.', 'seoprocookieconsent') . '</p>';
        }
        echo '<h3 id="spcc-import-history">' . esc_html__('Import history', 'seoprocookieconsent') . '</h3>';
        foreach (array_reverse((array) get_option(SEOProCookieConsent_Migrate::IMPORTS, array()), true) as $id => $record) {
            printf('<p>%1$s — %2$s — %3$s</p>', esc_html($record['source']), esc_html(wp_date('Y-m-d H:i', $record['time']) ?: ''), esc_html(!empty($record['undone']) ? __('Undone', 'seoprocookieconsent') : $record['state']));
            if (empty($record['undone'])) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                self::fields('undo', $id);
                submit_button(__('Undo', 'seoprocookieconsent'), 'secondary', 'submit', false);
                echo '</form>';
            }
        }
        echo '</section>';
    }

    /** @param array $items Safe text inventory. */
    private static function listing(array $items) {
        echo '<ul>';
        foreach ($items as $key => $value) {
            printf('<li><strong>%1$s</strong>: <code>%2$s</code></li>', esc_html((string) $key), esc_html(is_array($value) ? (string) wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $value));
        }
        echo '</ul>';
    }

    /** Post/redirect/get; an error stops without silently swallowing partial work. */
    public static function handle() {
        if (!current_user_can('manage_options') || is_network_admin()) {
            wp_die(esc_html__('You cannot import on this screen.', 'seoprocookieconsent'), '', array('response' => 403));
        }
        check_admin_referer('seoprocookieconsent_import');
        $action = isset($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';
        $source = isset($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : '';
        if ('cleanup' === $action && empty($_POST['ack'])) {
            wp_die(esc_html__('Confirm the irreversible cleanup first.', 'seoprocookieconsent'));
        }
        $result = SEOProCookieConsent_Migrate::run($action, $source, array(
            'pages' => isset($_POST['pages']) && is_array($_POST['pages']) ? array_map('absint', wp_unslash($_POST['pages'])) : array(),
            'confirm' => isset($_POST['confirm']) ? sanitize_text_field(wp_unslash($_POST['confirm'])) : '',
        ));
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }
        wp_safe_redirect(SEOProCookieConsent_Admin_Manager::tab_url('import'));
        exit;
    }
}
