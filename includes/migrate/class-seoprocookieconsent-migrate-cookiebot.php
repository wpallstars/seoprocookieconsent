<?php
/**
 * Cookiebot by Usercentrics (cookiebot) data formats, checked against
 * version 4.7.5 of its plugin. Its banner, regions, categories and cookie
 * scan live in its dashboard, which is never contacted; only what the
 * plugin stores on the site is read.
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

final class SEOProCookieConsent_Migrate_Cookiebot extends SEOProCookieConsent_Migrate_Source {
    const KEY = 'cookiebot';
    const NAME = 'Cookiebot';
    const SLUGS = array('cookiebot');

    /** Its widget's base ID. */
    const WIDGET = 'cookiebot_declaration_widget';

    /** Its account ID. A UUID is a Cookiebot account; anything else is a Usercentrics one. @return string */
    private function cbid() {
        return trim((string) get_option('cookiebot-cbid', ''));
    }

    /** @param string $name Option. @return bool */
    private function on($name) {
        $value = get_option($name, '');
        return '' !== $value && false !== $value && '0' !== $value && 0 !== $value;
    }

    /** @return array */
    public function detect() {
        $left = $this->leftovers();
        $notes = array();
        if ('' !== $this->cbid()) {
            $notes = $this->hosted_notes();
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $this->cbid())) {
                $notes[] = __('This site uses a Usercentrics account through Cookiebot, so its banner and choices are kept by Usercentrics.', 'seoprocookieconsent');
            }
            if (!$this->on('cookiebot-banner-enabled') && false !== get_option('cookiebot-banner-enabled', false)) {
                $notes[] = __('The Cookiebot banner is switched off on this site.', 'seoprocookieconsent');
            }
        }
        if ($this->on('cookiebot-gtm') && '' !== trim((string) get_option('cookiebot-gtm-id', ''))) {
            /* translators: %s: Google Tag Manager container ID. */
            $notes[] = sprintf(__('Cookiebot loaded Google Tag Manager (%s) itself, which stops when it is removed. Add the Tag Manager code to your theme or a header-code plugin; this plugin holds it back until allowed.', 'seoprocookieconsent'), sanitize_text_field((string) get_option('cookiebot-gtm-id')));
        }
        $pro = array();
        foreach (array('cookiebot-gcm' => __('Google Consent Mode', 'seoprocookieconsent'), 'cookiebot-iab' => __('TCF', 'seoprocookieconsent'), 'cookiebot-ccpa' => __('US privacy (CCPA)', 'seoprocookieconsent'), 'cookiebot-multiple-config' => __('banners by region', 'seoprocookieconsent')) as $option => $label) {
            if ($this->on($option)) {
                $pro[] = $label;
            }
        }
        if ($pro) {
            /* translators: %s: list of Cookiebot features. */
            $notes[] = sprintf(__('These Cookiebot settings are not imported and stop when it is removed: %s. Check Who is asked first and your Google tags before switching.', 'seoprocookieconsent'), implode(', ', $pro));
        }
        $addons = $this->addons();
        if ($addons) {
            /* translators: %s: list of Cookiebot add-on keys. */
            $notes[] = sprintf(__('Cookiebot add-ons held back other plugins\' scripts: %s. Services this plugin recognises are held back without them; check the rest after switching.', 'seoprocookieconsent'), implode(', ', $addons));
        }
        if (in_array('wpforms', $addons, true) || in_array('wpforms_pro', $addons, true)) {
            $notes[] = __('Its WPForms add-on switched off WPForms\' own GDPR settings: check them in WPForms.', 'seoprocookieconsent');
        }
        if (is_active_widget(false, false, self::WIDGET, true)) {
            $notes[] = __('A Cookiebot cookie declaration widget is in a sidebar. Replace it with the Cookie policy block, or link to your cookie policy page.', 'seoprocookieconsent');
        }
        if ('' !== $this->cbid()) {
            $notes[] = __('Scripts marked for Cookiebot with type="text/plain" and data-cookieconsent never run without it: make them normal scripts again in your theme or pages; services this plugin recognises, and your script rules, are then held back.', 'seoprocookieconsent');
        }
        return array('present' => (bool) array_filter($left) || (bool) $this->pages(), 'state' => $this->state(), 'notes' => $notes);
    }

    /** Add-ons switched on, by key. @return string[] */
    private function addons() {
        $addons = get_option('cookiebot_available_addons', array());
        $on = array();
        foreach (is_array($addons) ? $addons : array() as $key => $addon) {
            if (is_array($addon) && !empty($addon['enabled'])) {
                $on[] = sanitize_key((string) $key);
            }
        }
        return $on;
    }

    /** Everything it shows is set in its dashboard. @return array */
    public function settings() {
        return array();
    }

    /** Its "ignore scripts" list let scripts load before consent. @return array */
    public function scripts() {
        $lines = preg_split('/[\r\n]+/', (string) get_option('cookiebot-ignore-scripts', '')) ?: array();
        return array('consent_block' => array(), 'consent_allow' => self::allow_rules($lines));
    }

    /** @return array */
    public function pages() {
        $needles = array('[cookie_declaration', '[uc_embedding', 'wp:cookiebot/cookie-declaration', 'consent.cookiebot.', 'Cookiebot.renew', 'Cookiebot.show', 'data-cookieconsent');
        $regex = '/\[(?:cookie_declaration|uc_embedding)\b[^\]]*\]'
            . '|<!--\s+wp:cookiebot\/cookie-declaration\b.*?(?:\/-->|-->.*?<!--\s+\/wp:cookiebot\/cookie-declaration\s+-->)'
            . '|<script\b[^>]*\bsrc=["\'](?:https?:)?\/\/consent\.cookiebot\.(?:com|eu)\/[^"\']*cd\.js[^"\']*["\'][^>]*>\s*<\/script>'
            . '|<(a|button)\b[^>]*["\'](?:javascript:)?\s*Cookiebot\.(?:renew|show)\(\)[^>]*>.*?<\/\1>'
            . '|<(?:script|iframe|img|video|audio)\b[^>]*\bdata-cookieconsent=["\'](?!ignore["\'])[^"\']+["\'][^>]*>/is';
        $pages = array();
        foreach ($this->find_pages($needles, $regex) as $page) {
            $tokens = array();
            $cookie = false;
            foreach ($page['matches'] as $token) {
                $entry = array('token' => $token, 'cookie' => false, 'renderable' => false, 'kind' => 'marked');
                if (preg_match('/^(?:\[|<!--|<script\b[^>]*cd\.js)/i', $token)) {
                    $entry = array('token' => $token, 'cookie' => true, 'renderable' => true, 'kind' => 'cookie');
                } elseif (preg_match('/^<(?:a|button)\b/i', $token)) {
                    $entry = array('token' => $token, 'cookie' => false, 'renderable' => true, 'kind' => 'link', 'replace' => self::settings_link($token));
                }
                $tokens[] = $entry;
                $cookie = $cookie || $entry['cookie'];
            }
            $pages[] = array('id' => $page['id'], 'title' => $page['title'], 'cookie' => $cookie, 'tokens' => $tokens);
        }
        return $pages;
    }

    /**
     * Not carried over. Unverified: its hosted script writes "CookieConsent"
     * as "0", "-1" (no consent needed in that region) or a JavaScript-style
     * object with necessary, preferences, statistics and marketing flags
     * and a method ("explicit" or "implied"). Its plugin reads only the
     * flags; which values its script writes for an explicit choice has not
     * been checked against a live account, so visitors are asked again.
     *
     * @return array
     */
    public function consent_cookie() {
        return array();
    }

    /** Exact names only. Its consent records are kept by Cookiebot, not on the site. @return array */
    public function leftovers() {
        $options = self::option_names(array('cookiebot-', 'cookiebot_', 'widget_' . self::WIDGET, '_transient_cookiebot', '_transient_timeout_cookiebot', '_site_transient_cookiebot', '_site_transient_timeout_cookiebot'));
        return array('options' => $options, 'cron' => self::cron_hooks(array('cookiebot')), 'kept' => array());
    }

    /** @return string */
    public function uninstall_setting() {
        return __('Deleting Cookiebot removes its main settings but leaves its add-on, widget and notice settings; Remove leftover data deletes those too. Imports never change its settings or contact Cookiebot.', 'seoprocookieconsent');
    }
}
