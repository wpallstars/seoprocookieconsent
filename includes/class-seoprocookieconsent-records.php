<?php
/**
 * Optional, site-local consent choice history. No visitor data on page views.
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

final class SEOProCookieConsent_Records {

    const CRON = 'seoprocookieconsent_records_cleanup';
    const SCHEMA = 'seoprocookieconsent_records_schema';

    /** Register narrow hooks, including retention when recording is disabled. */
    public static function boot() {
        add_action('admin_init', array(__CLASS__, 'sync'));
        add_action('seoprocookieconsent_settings_changed', array(__CLASS__, 'sync'));
        add_action(self::CRON, array(__CLASS__, 'cleanup'));
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action('admin_post_seoprocookieconsent_records_export', array(__CLASS__, 'export'));
    }

    /** @return bool Whether new records may be accepted. */
    private static function enabled() {
        return SEOProCookieConsent_Consent::enabled() && (bool) SEOProCookieConsent_Settings::get('consent_records');
    }

    /** @return string Site-local table name. */
    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'seoprocookieconsent_records';
    }

    /** Lazily create storage only after opt-in, never on a visitor request. */
    public static function sync() {
        global $wpdb;
        if (self::enabled() && '1' !== get_option(self::SCHEMA)) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $table = self::table();
            $collate = $wpdb->get_charset_collate();
            dbDelta("CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                visitor_id char(32) NOT NULL,
                recorded_at bigint(20) unsigned NOT NULL,
                choice_time bigint(20) unsigned NOT NULL,
                policy varchar(100) NOT NULL,
                choice longtext NOT NULL,
                PRIMARY KEY  (id),
                KEY recorded_at (recorded_at)
            ) {$collate};");
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- verify our own lazy schema migration succeeded.
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) {
                update_option(self::SCHEMA, '1', false);
            }
        }
        if ('1' === get_option(self::SCHEMA) && !wp_next_scheduled(self::CRON)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::CRON);
        }
    }

    /** Register the write-only anonymous endpoint. */
    public static function routes() {
        register_rest_route('seoprocookieconsent/v1', '/records', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'record'),
            'permission_callback' => array(__CLASS__, 'permission'),
        ));
    }

    /**
     * This is a public self-reported choice, not an authenticated identity.
     * JSON plus same-origin checks reject third-party form/beacon submissions.
     * No public read/delete operation and no cache-sensitive nonce.
     *
     * @param WP_REST_Request $request Request.
     * @return bool
     */
    public static function permission($request) {
        if (!self::enabled() || 'application/json' !== strtolower(trim(explode(';', $request->get_header('content-type') ?? '')[0]))) {
            return false;
        }
        $origin = $request->get_header('origin') ?? '';
        $home = wp_parse_url(home_url('/'));
        $source = wp_parse_url($origin);
        return is_array($source) && is_array($home)
            && isset($source['scheme'], $source['host'], $home['scheme'], $home['host'])
            && $source['scheme'] === $home['scheme'] && $source['host'] === $home['host']
            && ($source['port'] ?? null) === ($home['port'] ?? null);
    }

    /**
     * Validate a bounded snapshot and append once per explicit browser choice.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    public static function record($request) {
        global $wpdb;
        if (strlen($request->get_body()) > 8192) {
            return new WP_Error('spcc_record_size', __('Choice is too large.', 'seoprocookieconsent'), array('status' => 413));
        }
        $data = $request->get_json_params();
        if (!is_array($data) || !isset($data['id'], $data['c'], $data['s'], $data['m'], $data['t'], $data['v'], $data['policy'])
            || !is_string($data['id']) || !preg_match('/^[a-f0-9]{32}$/D', $data['id'])
            || !is_array($data['c']) || !is_array($data['s']) || count($data['s']) > 50
            || !in_array($data['m'], array('opt-in', 'opt-out'), true)
            || 1 !== $data['v'] || !is_int($data['t']) || $data['t'] < 1 || $data['t'] > time() + 300
            || !is_string($data['policy']) || strlen($data['policy']) > 400) {
            return new WP_Error('spcc_record_invalid', __('Invalid choice.', 'seoprocookieconsent'), array('status' => 400));
        }
        $cats = array();
        foreach (SEOProCookieConsent_Services::optional_categories() as $category) {
            if (!isset($data['c'][$category]) || !in_array($data['c'][$category], array(0, 1), true)) {
                return new WP_Error('spcc_record_categories', __('Invalid categories.', 'seoprocookieconsent'), array('status' => 400));
            }
            $cats[$category] = $data['c'][$category];
        }
        foreach ($data['s'] as $service) {
            if (!is_string($service) || !preg_match('/^[a-z0-9:_-]{1,100}$/D', $service)) {
                return new WP_Error('spcc_record_services', __('Invalid services.', 'seoprocookieconsent'), array('status' => 400));
            }
        }
        if ('1' !== get_option(self::SCHEMA)) {
            return new WP_Error('spcc_record_unavailable', __('Records are not ready yet.', 'seoprocookieconsent'), array('status' => 503));
        }
        $choice = array('v' => 1, 'c' => $cats, 's' => array_values(array_unique($data['s'])), 'm' => $data['m'], 'p' => empty($data['p']) ? 0 : 1);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- append-only, private table; no cached read to invalidate.
        $saved = $wpdb->insert(self::table(), array(
            'visitor_id' => $data['id'],
            'recorded_at' => time(),
            'choice_time' => $data['t'],
            'policy' => wp_html_excerpt(sanitize_text_field($data['policy']), 100, ''),
            'choice' => wp_json_encode($choice),
        ), array('%s', '%d', '%d', '%s', '%s'));
        return false === $saved
            ? new WP_Error('spcc_record_failed', __('Could not store choice.', 'seoprocookieconsent'), array('status' => 503))
            : new WP_REST_Response(null, 204);
    }

    /** @return int Oldest permitted server timestamp, following current lifetime. */
    private static function cutoff() {
        return time() - max(30, min(395, (int) SEOProCookieConsent_Settings::get('consent_days'))) * DAY_IN_SECONDS;
    }

    /** Delete at most 1,000 expired rows; continue later without long cron runs. */
    public static function cleanup() {
        global $wpdb;
        if ('1' !== get_option(self::SCHEMA)) {
            return;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- indexed, bounded retention on our own table.
        $deleted = $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE recorded_at < %d ORDER BY recorded_at LIMIT 1000', self::table(), self::cutoff()));
        if (1000 === $deleted) {
            wp_schedule_single_event(time() + 300, self::CRON);
        }
    }

    /** Export link lives inside the existing Consent card, not a nested form. */
    public static function panel() {
        if (!current_user_can('manage_options') || '1' !== get_option(self::SCHEMA)) {
            return;
        }
        $url = wp_nonce_url(admin_url('admin-post.php?action=seoprocookieconsent_records_export'), 'seoprocookieconsent_records_export');
        echo '<p><a class="button" href="' . esc_url($url) . '">' . esc_html__('Export consent records (CSV)', 'seoprocookieconsent') . '</a></p>';
    }

    /** Stream a bounded-memory snapshot, with authorization and formula protection. */
    public static function export() {
        global $wpdb;
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You cannot export consent records.', 'seoprocookieconsent'), '', array('response' => 403));
        }
        check_admin_referer('seoprocookieconsent_records_export');
        if ('1' !== get_option(self::SCHEMA)) {
            wp_die(esc_html__('No consent records yet.', 'seoprocookieconsent'));
        }
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="consent-records.csv"');
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming an authorized download, not a filesystem file.
        $stream = fopen('php://output', 'w');
        if (false === $stream) {
            exit;
        }
        fputcsv($stream, array('Record', 'Anonymous ID', 'Recorded (UTC)', 'Choice time (UTC)', 'Policy version', 'Choice (JSON)'), ',', '"', '');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- primary-key endpoint bounds the export snapshot.
        $last = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM %i ORDER BY id DESC LIMIT 1', self::table()));
        $after = 0;
        $cutoff = self::cutoff();
        do {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- keyset-paged private export; no shared cache of personal data.
            $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE id > %d AND id <= %d ORDER BY id LIMIT 500', self::table(), $after, $last), ARRAY_A);
            foreach ($rows as $row) {
                $after = (int) $row['id'];
                if ((int) $row['recorded_at'] < $cutoff) {
                    continue;
                }
                $policy = preg_match('/^[\s]*[=+@-]/', $row['policy']) ? "'" . $row['policy'] : $row['policy'];
                fputcsv($stream, array($row['id'], $row['visitor_id'], gmdate('c', (int) $row['recorded_at']), gmdate('c', (int) $row['choice_time']), $policy, $row['choice']), ',', '"', '');
            }
        } while (count($rows) === 500 && $after < $last);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closes the output stream.
        fclose($stream);
        exit;
    }
}
