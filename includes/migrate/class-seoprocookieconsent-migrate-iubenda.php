<?php
/**
 * iubenda (iubenda-cookie-law-solution) data formats, checked against
 * version 3.13.5 of its plugin. Its banner texts, policies, consent
 * records and most settings live in its dashboard, which is never
 * contacted; only what the plugin stores on the site is read.
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

final class SEOProCookieConsent_Migrate_Iubenda extends SEOProCookieConsent_Migrate_Source {
    const KEY = 'iubenda';
    const NAME = 'iubenda';
    const SLUGS = array('iubenda-cookie-law-solution');

    /** Its form-mapping post type for its hosted consent database. */
    const POST_TYPE = 'iubenda_form';

    /** Its purposes by number: our category, or '' for strictly necessary. 0 ("not set") is held back for every purpose. */
    const PURPOSES = array(0 => 'marketing', 1 => '', 2 => 'preferences', 3 => 'embeds', 4 => 'statistics', 5 => 'marketing');

    /** Its banner positions with an equivalent here. */
    const POSITIONS = array('full-bottom' => 'bar', 'float-bottom-left' => 'left', 'float-bottom-right' => 'right');

    /** @return array Its cookie banner settings. */
    private function options() {
        $options = get_option('iubenda_cookie_law_solution', array());
        return is_array($options) ? $options : array();
    }

    /** Its banner code is saved for at least one language. @return bool */
    private function configured() {
        foreach ($this->options() as $key => $value) {
            if (0 === strpos((string) $key, 'code_') && is_string($value) && '' !== trim($value)) {
                return true;
            }
        }
        return false;
    }

    /** Its simplified set-up, built from the settings stored here. @return array|null */
    private function simplified() {
        $options = $this->options();
        if ('simplified' !== ($options['configuration_type'] ?? '') || !is_array($options['simplified'] ?? null)) {
            return null;
        }
        return $options['simplified'];
    }

    /** @return array */
    public function detect() {
        $left = $this->leftovers();
        $notes = array();
        if ($this->configured()) {
            $notes = $this->hosted_notes();
            $options = $this->options();
            $simplified = $this->simplified();
            if (!empty($simplified['tcf'])) {
                $notes[] = __('Its TCF (IAB framework) setting is not imported and stops when it is removed.', 'seoprocookieconsent');
            }
            if (!empty($simplified['legislation']['uspr']) || !empty($simplified['legislation']['lgpd']) || !empty($simplified['legislation']['all'])) {
                $notes[] = __('iubenda also handled US or Brazilian privacy laws, which have no equivalent here. Check Who is asked first before switching.', 'seoprocookieconsent');
            }
            if (!empty($options['block_gtm'])) {
                $notes[] = __('iubenda held back Google Tag Manager; this plugin recognises and holds it back too.', 'seoprocookieconsent');
            }
            if (null === $simplified) {
                $notes[] = __('iubenda was set up with pasted code, so its banner position and who it asked are not imported.', 'seoprocookieconsent');
            }
        }
        if ($this->menu_links()) {
            $notes[] = __('A menu has an iubenda preferences link (class iubenda-cs-preferences-link). Replace it with a #cookie-settings link, or rely on the Cookie settings footer link.', 'seoprocookieconsent');
        }
        if (is_active_widget(false, false, 'iubenda_legal_widget', true)) {
            $notes[] = __('An iubenda legal widget is in a sidebar. It stops showing when iubenda is removed: link to your policies instead.', 'seoprocookieconsent');
        }
        if ($this->forms()) {
            $notes[] = __('iubenda sent form submissions to its hosted consent database. That stops when it is removed; its records stay with iubenda.', 'seoprocookieconsent');
        }
        return array('present' => (bool) array_filter($left) || (bool) $this->pages(), 'state' => $this->state(), 'notes' => $notes);
    }

    /** @return array */
    public function settings() {
        $simplified = $this->simplified();
        if (!$this->configured() || null === $simplified) {
            return array();
        }
        $result = array();
        // Its simplified GDPR banner asks everyone ("worldwide" is its only choice).
        $legislation = (array) ($simplified['legislation'] ?? array());
        if ((!empty($legislation['gdpr']) || !empty($legislation['all'])) && 'worldwide' === ($simplified['require_consent'] ?? '')) {
            $result['consent_regions'] = 'everywhere';
        }
        $position = (string) ($simplified['position'] ?? '');
        if (isset(self::POSITIONS[$position])) {
            $result['consent_position'] = self::POSITIONS[$position];
        }
        return $result;
    }

    /**
     * Its custom script and iframe patterns (address => purpose) become
     * hold-back rules by category; addresses in its content-blocking
     * shortcodes are held back as Marketing. Wildcards other than a
     * leading "*." are left to the owner.
     *
     * @return array
     */
    public function scripts() {
        $options = $this->options();
        $lines = array();
        foreach (array('custom_scripts', 'custom_iframes') as $key) {
            foreach ((array) ($options[$key] ?? array()) as $pattern => $purpose) {
                $category = self::PURPOSES[(int) $purpose] ?? 'marketing';
                $pattern = (string) preg_replace('/^\*\./', '', trim(stripslashes((string) $pattern)));
                if ('' === $category || '' === $pattern || false !== strpos($pattern, '*')) {
                    continue;
                }
                $rule = self::script_rule($pattern);
                if (is_string($rule) && '' !== $rule) {
                    $lines[] = ('marketing' !== $category ? $category . ': ' : '') . $rule;
                }
            }
        }
        foreach ($this->pages() as $page) {
            foreach ($page['tokens'] as $token) {
                if ('held' !== $token['kind']) {
                    continue;
                }
                preg_match_all('~<(?:script|iframe|img|link)\b[^>]*\b(?:src|href)=["\']([^"\']+)["\']~i', $token['token'], $urls);
                foreach (array_unique($urls[1]) as $url) {
                    $rule = self::script_rule($url);
                    if (is_string($rule) && '' !== $rule) {
                        $lines[] = $rule;
                    }
                }
            }
        }
        return array('consent_block' => array_values(array_unique($lines)), 'consent_allow' => array());
    }

    /** @return array */
    public function pages() {
        $needles = array('[iub-', 'wp:iubenda/', 'iubenda-embed', 'iubenda-cs-preferences-link', 'IUB-COOKIE-BLOCK');
        $regex = '/\[(iub-cookie-policy|iub-cookie-block|iub-cookie-skip)\b[^\]]*\].*?\[\/\1\]'
            . '|<!--IUB-COOKIE-BLOCK(?:-SKIP)?-START-->.*?<!--IUB-COOKIE-BLOCK(?:-SKIP)?-END-->'
            . '|\[(?:iub-pp-button|iub-tc-button|iub-wp-block-buttons)\b[^\]]*\]'
            . '|<!--\s+wp:iubenda\/legal-block\b.*?(?:\/-->|-->.*?<!--\s+\/wp:iubenda\/legal-block\s+-->)'
            . '|<a\b[^>]*\bhref=["\']https?:\/\/(?:www\.)?iubenda\.com\/[^"\']+["\'][^>]*\biubenda-embed\b[^>]*>.*?<\/a>'
            . '|<a\b[^>]*\biubenda-embed\b[^>]*\bhref=["\']https?:\/\/(?:www\.)?iubenda\.com\/[^"\']+["\'][^>]*>.*?<\/a>'
            . '|<(a|button)\b[^>]*\biubenda-cs-preferences-link\b[^>]*>.*?<\/\2>/is';
        $pages = array();
        foreach ($this->find_pages($needles, $regex) as $page) {
            $tokens = array();
            $cookie = false;
            foreach ($page['matches'] as $token) {
                $entry = array('token' => $token, 'cookie' => false, 'renderable' => false, 'kind' => 'hosted');
                if (preg_match('/^(?:\[iub-cookie-(?:policy|block)\b[^\]]*\]|<!--IUB-COOKIE-BLOCK-START-->)(.*)(?:\[\/iub-cookie-(?:policy|block)\]|<!--IUB-COOKIE-BLOCK-END-->)$/is', $token, $inner)) {
                    // Content it held back: keep it, without the tags; scripts() holds its addresses back here.
                    $entry = array('token' => $token, 'cookie' => false, 'renderable' => true, 'kind' => 'held', 'replace' => $inner[1]);
                } elseif (preg_match('/^(?:\[iub-cookie-skip\b[^\]]*\]|<!--IUB-COOKIE-BLOCK-SKIP-START-->)(.*)(?:\[\/iub-cookie-skip\]|<!--IUB-COOKIE-BLOCK-SKIP-END-->)$/is', $token, $inner)) {
                    // Content it always loaded: keep it, without the tags.
                    $entry = array('token' => $token, 'cookie' => false, 'renderable' => true, 'kind' => 'unwrap', 'replace' => $inner[1]);
                } elseif (preg_match('/\biubenda-cs-preferences-link\b/i', $token) && !preg_match('/iubenda\.com\//i', $token)) {
                    $entry = array('token' => $token, 'cookie' => false, 'renderable' => true, 'kind' => 'link', 'replace' => self::settings_link($token));
                } elseif (preg_match('~iubenda\.com/privacy-policy/\d+/cookie-policy~i', $token)) {
                    $entry = array('token' => $token, 'cookie' => true, 'renderable' => true, 'kind' => 'cookie');
                }
                $tokens[] = $entry;
                $cookie = $cookie || $entry['cookie'];
            }
            $pages[] = array('id' => $page['id'], 'title' => $page['title'], 'cookie' => $cookie, 'tokens' => $tokens);
        }
        return $pages;
    }

    /**
     * Not carried over. Unverified: its hosted script writes "_iub_cs-" plus
     * a policy or site ID, holding JSON with "consent" and, per purpose,
     * "purposes" (1-5 => true/false). Its plugin reads only those keys;
     * whether a cookie without a purpose means it was refused has not been
     * checked against a live account, so visitors are asked again.
     *
     * @return array
     */
    public function consent_cookie() {
        return array();
    }

    /** Menu items with its preferences class. @return int */
    private function menu_links() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only count of the source's menu links.
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_classes' AND meta_value LIKE %s", '%' . $wpdb->esc_like('iubenda-cs-preferences-link') . '%'));
    }

    /** Its form mappings for its hosted consent database. @return int[] */
    private function forms() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- IDs only, of the source's own post type.
        return array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID ASC LIMIT 1000", self::POST_TYPE)));
    }

    /** Exact names and IDs only. Its consent records are kept by iubenda, not on the site. @return array */
    public function leftovers() {
        $options = self::option_names(array('iubenda_', 'iubenda-', 'widget_iubenda_legal_widget', '_transient_iub', '_transient_timeout_iub', '_site_transient_iub', '_site_transient_timeout_iub'));
        return array('options' => $options, 'cron' => self::cron_hooks(array('iubenda_')), 'posts' => $this->forms(), 'kept' => array());
    }

    /** @return string */
    public function uninstall_setting() {
        return __('Deleting iubenda removes its settings only if its own delete-on-deactivation option is on; Remove leftover data deletes its settings, form mappings and scheduled checks. Imports never change its settings or contact iubenda.', 'seoprocookieconsent');
    }
}
