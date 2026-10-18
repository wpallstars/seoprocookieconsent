<?php
/**
 * Cookie Notice & Compliance (cookie-notice) data formats, checked against
 * version 3.1.17 in its local mode. Its Compliance (hosted) mode keeps the
 * banner and choices in the web app, so that mode is reported, not guessed.
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

final class SEOProCookieConsent_Migrate_Cookie_Notice extends SEOProCookieConsent_Migrate_Source {
    const KEY = 'cookie-notice';
    const NAME = 'Cookie Notice & Compliance';
    const SLUGS = array('cookie-notice');

    /** The shipped English message, which says continuing to browse is consent: never imported. */
    const STOCK_MESSAGE = 'We use cookies to ensure that we give you the best experience on our website. If you continue to use this site we will assume that you are happy with it.';

    /** Its "time" choices within our 30-395 day range. */
    const DAYS = array('month' => 30, '3months' => 91, '6months' => 183, 'year' => 365);

    /** Shortcodes in pages: name => kind. */
    const SHORTCODES = array('cookies_revoke' => 'settings', 'cookies_accepted' => 'gate', 'cookies_policy_link' => 'other');

    /** @return array */
    private function options() {
        $options = get_option('cookie_notice_options', array());
        return is_array($options) ? $options : array();
    }

    /** Compliance (hosted) mode: connected to the web app. @return bool */
    private function hosted() {
        $options = $this->options();
        return '' !== (string) ($options['app_id'] ?? '');
    }

    /** Scrolling or clicking anywhere counted as acceptance. @return bool */
    private function implied() {
        $options = $this->options();
        return !empty($options['on_scroll']) || !empty($options['on_click']);
    }

    /** @return array */
    public function detect() {
        $left = $this->leftovers();
        $notes = array();
        if ($this->hosted()) {
            $notes[] = __('Compliance (hosted) mode is on: the banner and visitors\' choices live in the Cookie Compliance web app. Only what is stored on this site is imported, and visitors are asked again.', 'seoprocookieconsent');
        } elseif ($this->implied()) {
            $notes[] = __('Cookie Notice accepted on scroll or click, which is not a clear choice: only rejections carry over, and visitors who accepted are asked again.', 'seoprocookieconsent');
        }
        $scripts = $this->code();
        if ($scripts) {
            $notes[] = __('Cookie Notice ran your "after acceptance" scripts itself; they stop when it is gone. Add them to your theme or a header-code plugin after switching: the script rules below, and services this plugin recognises, are then held back until visitors allow them. Copy them before removing leftover data.', 'seoprocookieconsent');
            foreach ($scripts as $snippet) {
                if (null === self::script_rule($snippet)) {
                    /* translators: %s: start of an inline script. */
                    $notes[] = sprintf(__('Add by hand to Also hold back (no address to recognise): %s', 'seoprocookieconsent'), mb_substr(trim((string) preg_replace('/\s+/', ' ', $snippet)), 0, 120));
                }
            }
        }
        return array('present' => (bool) array_filter($left) || (bool) $this->pages(), 'state' => $this->state(), 'notes' => $notes);
    }

    /** Script, iframe, image and noscript elements from its after-acceptance code. @return string[] */
    private function code() {
        $options = $this->options();
        $html = (string) ($options['refuse_code_head'] ?? '') . "\n" . (string) ($options['refuse_code'] ?? '');
        if ('' === trim($html)) {
            return array();
        }
        preg_match_all('~<script\b[^>]*>.*?</script>|<noscript\b[^>]*>.*?</noscript>|<(?:iframe|img)\b[^>]*>~is', $html, $matches);
        return array_values(array_filter(array_map('trim', $matches[0])));
    }

    /** @return array */
    public function settings() {
        if ($this->hosted()) {
            return array();
        }
        $options = $this->options();
        $result = array();
        // Its after-acceptance scripts waited for everyone, so everyone is asked first here too.
        if ($this->code()) {
            $result['consent_regions'] = 'everywhere';
        }
        if (isset(self::DAYS[$options['time'] ?? ''])) {
            $result['consent_days'] = self::DAYS[$options['time']];
        }
        if ('bottom' === ($options['position'] ?? '')) {
            $result['consent_position'] = 'bar';
        }
        $message = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags(strip_shortcodes((string) ($options['message_text'] ?? ''))), ENT_QUOTES, 'UTF-8')));
        if ('' !== $message && self::STOCK_MESSAGE !== $message) {
            $result['consent_message'] = $message;
        }
        $link = empty($options['see_more']) ? '' : self::policy_link((array) ($options['see_more_opt'] ?? array()));
        if ('' !== $link) {
            $result['consent_policy_url'] = $link;
        }
        return $result;
    }

    /**
     * Its "See more" link: a custom address or a published page.
     *
     * @param array $more Its see_more_opt setting.
     * @return string The address, or "".
     */
    private static function policy_link(array $more) {
        if ('custom' === ($more['link_type'] ?? '')) {
            return esc_url_raw((string) ($more['link'] ?? ''));
        }
        $page = (int) ($more['id'] ?? 0);
        return 'publish' === get_post_status($page) ? (string) get_permalink($page) : '';
    }

    /** @return array */
    public function scripts() {
        $result = array('consent_block' => array(), 'consent_allow' => array());
        foreach ($this->code() as $snippet) {
            $rule = self::script_rule($snippet);
            if (is_string($rule) && '' !== $rule) {
                $result['consent_block'][] = $rule;
            }
        }
        $result['consent_block'] = array_values(array_unique($result['consent_block']));
        return $result;
    }

    /** @return array */
    public function pages() {
        $needles = array('[cookies_revoke', '[cookies_accepted', '[cookies_policy_link');
        $regex = '/\[cookies_accepted\b[^\]]*\].*?\[\/cookies_accepted\]|\[(?:cookies_accepted|cookies_revoke|cookies_policy_link)\b[^\]]*\]/is';
        $pages = array();
        foreach ($this->find_pages($needles, $regex) as $page) {
            $tokens = array();
            foreach ($page['matches'] as $token) {
                $kind = preg_match('/^\[([a-z_]+)/i', $token, $name) ? (self::SHORTCODES[strtolower($name[1])] ?? 'other') : 'other';
                $entry = array('token' => $token, 'cookie' => false, 'renderable' => 'settings' === $kind, 'kind' => $kind);
                if ('settings' === $kind) {
                    $entry['replace'] = '<!-- wp:seoprocookieconsent/cookie-settings /-->';
                }
                $tokens[] = $entry;
            }
            $pages[] = array('id' => $page['id'], 'title' => $page['title'], 'cookie' => false, 'tokens' => $tokens);
        }
        return $pages;
    }

    /**
     * Accept ("true") allows every category and reject ("false", also set by
     * its close button) none. Accepting on scroll or click is not a clear
     * choice, so then only rejections count.
     *
     * @return array
     */
    public function consent_cookie() {
        // Without its settings (deleted on deactivation) the mode is unknown; the import keeps what it saw.
        if (!$this->options() || $this->hosted()) {
            return array();
        }
        $cookie = 'cookie_notice_accepted';
        return array('categories' => array('preferences' => $cookie, 'statistics' => $cookie, 'embeds' => $cookie, 'marketing' => $cookie), 'allow' => $this->implied() ? null : 'true', 'deny' => 'false');
    }

    /** Exact names only. It keeps no consent records on the site. @return array */
    public function leftovers() {
        global $wpdb;
        $options = array();
        foreach (array('cookie_notice_', '_transient_cookie_notice_', '_transient_timeout_cookie_notice_', '_transient_cn_', '_transient_timeout_cn_') as $prefix) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- indexed, source-owned option prefixes; names only.
            $options = array_merge($options, $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT 1000", $wpdb->esc_like($prefix) . '%')));
        }
        $hooks = array();
        foreach (_get_cron_array() ?: array() as $events) {
            foreach (array_keys($events) as $hook) {
                if (0 === strpos($hook, 'cookie_notice_')) {
                    $hooks[] = $hook;
                }
            }
        }
        return array('options' => array_values(array_unique($options)), 'tables' => array(), 'cron' => array_values(array_unique($hooks)), 'kept' => array());
    }

    /** @return string */
    public function uninstall_setting() {
        return __('Cookie Notice deletes its settings when deactivated if its Deactivation option is on: copy your after-acceptance scripts first. Imports never change its settings.', 'seoprocookieconsent');
    }
}
