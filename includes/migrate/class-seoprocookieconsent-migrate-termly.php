<?php
/**
 * Termly (uk-cookie-consent) data formats, checked against version 3.3.1
 * of its plugin. Its banner, policies, consent records and cookie scan
 * live in its dashboard, which is never contacted; only what the plugin
 * stores on the site is read. Its API key is never read or shown.
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

final class SEOProCookieConsent_Migrate_Termly extends SEOProCookieConsent_Migrate_Source {
    const KEY = 'termly';
    const NAME = 'Termly';
    const SLUGS = array('uk-cookie-consent');

    /** Its custom blocking map categories: ours, '' to never hold back, or null to skip. */
    const CATEGORIES = array('essential' => '', 'advertising' => 'marketing', 'analytics' => 'statistics', 'performance' => 'preferences', 'social' => 'embeds');

    /** Its banner was shown on this site. @return bool */
    private function shown() {
        return 'yes' === get_option('termly_display_banner', 'no');
    }

    /** @return array */
    public function detect() {
        $left = $this->leftovers();
        $notes = array();
        if ($this->shown() || false !== get_option('termly_website', false)) {
            $notes = $this->hosted_notes();
            $auto = (string) get_option('termly_display_auto_blocker', 'off');
            if ('off' !== $auto && '' !== $auto) {
                $notes[] = __('Termly\'s auto blocker held back scripts it recognised. This plugin holds back the services it recognises; compare the lists above.', 'seoprocookieconsent');
            }
            $scan = get_option('termly_site_scan', array());
            if (is_array($scan) && !empty($scan['robots_txt'])) {
                $notes[] = __('Termly may have added a "TermlyBot" section to your robots.txt file. Remove it after switching, if it is there.', 'seoprocookieconsent');
            }
        }
        return array('present' => (bool) array_filter($left) || (bool) $this->pages(), 'state' => $this->state(), 'notes' => $notes);
    }

    /** Everything it shows is set in its dashboard. @return array */
    public function settings() {
        return array();
    }

    /** Its custom blocking map (comma-separated addresses per category), when switched on. @return array */
    public function scripts() {
        $block = array();
        $allow = array();
        $map = get_option('termly_custom_blocking_map', array());
        if ('on' !== get_option('termly_display_custom_map', 'off') || !is_array($map)) {
            return array('consent_block' => array(), 'consent_allow' => array());
        }
        foreach ($map as $category => $value) {
            $mapped = self::CATEGORIES[(string) $category] ?? null;
            if (null === $mapped) {
                continue;
            }
            $lines = array_filter(array_map('trim', preg_split('/[,\r\n]+/', (string) $value) ?: array()));
            if ('' === $mapped) {
                $allow = array_merge($allow, $lines);
                continue;
            }
            foreach ($lines as $line) {
                $rule = self::script_rule($line);
                if (is_string($rule) && '' !== $rule) {
                    $block[] = ('marketing' !== $mapped ? $mapped . ': ' : '') . $rule;
                }
            }
        }
        return array('consent_block' => array_values(array_unique($block)), 'consent_allow' => self::allow_rules($allow));
    }

    /** @return array */
    public function pages() {
        $needles = array('termly-embed', 'app.termly.io/', 'displayPreferenceModal', 'termly-display-preferences');
        $regex = '/<div\b[^>]*\bname=["\']termly-embed["\'][^>]*>\s*<\/div>'
            . '|<script\b[^>]*\bsrc=["\'](?:https?:)?\/\/app\.termly\.io\/[^"\']*["\'][^>]*>\s*<\/script>'
            . '|<script\b[^>]*>[^<]*app\.termly\.io\/embed[^<]*<\/script>'
            . '|<iframe\b[^>]*\bsrc=["\'](?:https?:)?\/\/app\.termly\.io\/[^"\']*["\'][^>]*>\s*<\/iframe>'
            . '|<(a|button)\b[^>]*(?:displayPreferenceModal|\btermly-display-preferences\b)[^>]*>.*?<\/\1>/is';
        $pages = array();
        foreach ($this->find_pages($needles, $regex) as $page) {
            $tokens = array();
            foreach ($page['matches'] as $token) {
                $entry = array('token' => $token, 'cookie' => false, 'renderable' => false, 'kind' => 'hosted');
                if (preg_match('/^<(?:a|button)\b/i', $token)) {
                    $entry = array('token' => $token, 'cookie' => false, 'renderable' => true, 'kind' => 'link', 'replace' => self::settings_link($token));
                }
                $tokens[] = $entry;
            }
            // Its embeds name a document by ID only, so a cookie policy cannot be told apart from others.
            $pages[] = array('id' => $page['id'], 'title' => $page['title'], 'cookie' => false, 'tokens' => $tokens);
        }
        return $pages;
    }

    /**
     * Not carried over. Unverified: its hosted script keeps choices in the
     * browser in a format its plugin never reads, so it cannot be checked
     * from the site; visitors are asked again.
     *
     * @return array
     */
    public function consent_cookie() {
        return array();
    }

    /** Exact names only, including its API key's option name, never its value. @return array */
    public function leftovers() {
        $options = self::option_names(array('termly_', '_transient_termly', '_transient_timeout_termly', '_transient_ctcc_', '_transient_timeout_ctcc_'));
        return array('options' => $options, 'cron' => self::cron_hooks(array('termly_')), 'kept' => array());
    }

    /** @return string */
    public function uninstall_setting() {
        return __('Deleting Termly leaves its settings and daily account check; Remove leftover data deletes them, including its saved API key. Imports never change its settings or contact Termly.', 'seoprocookieconsent');
    }
}
