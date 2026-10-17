<?php
/**
 * Real Cookie Banner (real-cookie-banner, and its Pro edition) data formats,
 * checked against version 5.4.0 of both and a running 5.4.0 site. Pro-only
 * features (TCF, Google Consent Mode, country bypass, consent forwarding,
 * banner-less consent) are reported, not guessed.
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

final class SEOProCookieConsent_Migrate_Real_Cookie_Banner extends SEOProCookieConsent_Migrate_Source {
    const KEY = 'real-cookie-banner';
    const NAME = 'Real Cookie Banner';
    const SLUGS = array('real-cookie-banner', 'real-cookie-banner-pro');

    /** Its post types: services, content blockers, banner links and Pro TCF vendors. */
    const POST_TYPES = array('rcb-cookie', 'rcb-blocker', 'rcb-banner-link', 'rcb-tcf-vendor-conf');
    const GROUPS = 'rcb-cookie-group';

    /**
     * Buttons a visitor pressed to choose. Left out: closing the banner with
     * its icon, implicit choices (country bypass) and "none" (bots, Do Not Track).
     */
    const BUTTONS = array('main_all', 'main_essential', 'main_custom', 'ind_all', 'ind_essential', 'ind_custom', 'unblock', 'shortcode_revoke');

    /** Its shortcode link types: kind here. Mistranslated types are its own aliases. */
    const LINKS = array('change' => 'settings', 'revoke' => 'settings', 'cambiar' => 'settings', 'revocar' => 'settings');

    /** Pro options that change who sees what: option => what it was. */
    private function pro() {
        return array(
            'rcb-tcf'                 => __('TCF', 'seoprocookieconsent'),
            'rcb-gcm'                 => __('Google Consent Mode', 'seoprocookieconsent'),
            'rcb-country-bypass'      => __('country bypass', 'seoprocookieconsent'),
            'rcb-consent-forwarding'  => __('consent forwarding', 'seoprocookieconsent'),
            'rcb-banner-less-consent' => __('banner-less consent', 'seoprocookieconsent'),
        );
    }

    /** It stores booleans as '1'/'' or true/false. @param string $name Option. @return bool */
    private function on($name) {
        $value = get_option($name, '');
        return true === $value || '1' === $value || 1 === $value || 'true' === $value;
    }

    /** Its settings exist: a fresh install writes them on activation. @return bool */
    private function configured() {
        return false !== get_option('rcb-banner-active', false);
    }

    /** Its banner was on, so visitors were asked first everywhere. @return bool */
    private function asked() {
        return $this->configured() && $this->on('rcb-banner-active') && !$this->on('rcb-country-bypass') && !$this->on('rcb-banner-less-consent');
    }

    /** @return array */
    public function detect() {
        $left = $this->leftovers();
        $notes = array();
        if ($this->configured() && !$this->on('rcb-banner-active')) {
            $notes[] = __('The Real Cookie Banner banner is switched off, so visitors\' choices are not carried over.', 'seoprocookieconsent');
        }
        $pro = array();
        foreach ($this->pro() as $option => $label) {
            if ($this->on($option)) {
                $pro[] = $label;
            }
        }
        if ($pro) {
            /* translators: %s: list of Real Cookie Banner Pro features. */
            $notes[] = sprintf(__('Real Cookie Banner Pro settings are not imported and stop when it is removed: %s. Check Who is asked first and your Google tags before switching; visitors\' choices are not carried over while country bypass or banner-less consent is on.', 'seoprocookieconsent'), implode(', ', $pro));
        }
        $notes[] = __('Real Cookie Banner let visitors choose single services. A category carries over as allowed only when every Real Cookie Banner service in it was accepted; otherwise it is refused. Closing its banner with the close icon is not carried over.', 'seoprocookieconsent');
        $coded = array();
        foreach ($this->services() as $service) {
            if ('' !== trim($service['code'])) {
                $coded[] = $service['title'];
            }
        }
        if ($coded) {
            /* translators: %s: list of service names. */
            $notes[] = sprintf(__('Real Cookie Banner ran code for these services, which stops when it is removed: %s. Add any code you still need to your theme or a header-code plugin; services this plugin recognises, and the script rules below, are then held back.', 'seoprocookieconsent'), implode(', ', array_unique($coded)));
        }
        if ($this->menu_links()) {
            $notes[] = __('A menu has Real Cookie Banner privacy links (#consent-change, #consent-history or #consent-revoke). Replace them with a #cookie-settings link, or rely on the Cookie settings footer link.', 'seoprocookieconsent');
        }
        if ($this->configured()) {
            $notes[] = __('Real Cookie Banner always kept consent records. Turn on Keep consent records if you need to go on documenting choices. Its banner texts are not imported: they describe its own dialog.', 'seoprocookieconsent');
        }
        return array('present' => (bool) array_filter($left) || (bool) $this->pages(), 'state' => $this->state(), 'notes' => $notes);
    }

    /** @return array */
    public function settings() {
        if (!$this->configured()) {
            return array();
        }
        $result = array();
        if ($this->asked()) {
            $result['consent_regions'] = 'everywhere';
        }
        $days = (int) get_option('rcb-cookie-duration', 365);
        if ($days > 0) {
            $result['consent_days'] = $days;
        }
        $type = (string) get_option('rcb-banner-layout-type', 'dialog');
        $dialog = array('bottomLeft' => 'left', 'bottomRight' => 'right');
        if ('banner' === $type && 'bottom' === get_option('rcb-banner-layout-banner-position', 'bottom')) {
            $result['consent_position'] = 'bar';
        } elseif ('dialog' === $type && isset($dialog[(string) get_option('rcb-banner-layout-dialog-position', '')])) {
            $result['consent_position'] = $dialog[(string) get_option('rcb-banner-layout-dialog-position', '')];
        }
        $page = (int) get_option('rcb-cookie-policy-id', 0);
        if ($page > 0 && 'publish' === get_post_status($page)) {
            $result['consent_policy_url'] = (string) get_permalink($page);
        } else {
            foreach ($this->pages() as $page) {
                if (!empty($page['cookie']) && 'publish' === get_post_status($page['id'])) {
                    $result['consent_policy_url'] = (string) get_permalink($page['id']);
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Addresses in its services' code and its content blockers' plain
     * address rules become hold-back rules in the service's category.
     * Wildcards inside a rule and element selectors are left to the owner.
     *
     * @return array
     */
    public function scripts() {
        $result = array('consent_block' => array(), 'consent_allow' => array());
        $services = $this->services();
        $add = function ($snippet, $category) use (&$result) {
            $rule = self::script_rule($snippet);
            if (is_string($rule) && '' !== $rule) {
                $result['consent_block'][] = ('marketing' !== $category ? $category . ': ' : '') . $rule;
            }
        };
        foreach ($services as $service) {
            if ($service['essential']) {
                continue;
            }
            // Only what its opt-in code loads; addresses inside script text may be anything.
            preg_match_all('~<(?:script|iframe|img|link)\b[^>]*\b(?:src|href)=["\']([^"\']+)["\']~i', $service['optin'], $urls);
            foreach (array_unique($urls[1]) as $url) {
                $add($url, $service['category']);
            }
        }
        foreach ($this->blockers() as $blocker) {
            $categories = array();
            foreach ($blocker['services'] as $id) {
                if (isset($services[$id]) && !$services[$id]['essential']) {
                    $categories[] = $services[$id]['category'];
                }
            }
            $categories = array_values(array_unique($categories));
            // Several categories: the strictest.
            $category = 1 === count($categories) ? $categories[0] : 'marketing';
            foreach ($blocker['rules'] as $rule) {
                // Its element selectors always have "[" (tag[attr...]); ":$name=" lines are variables; the rest match as "contains".
                $rule = trim($rule, "* \t");
                if ('' !== $rule && false === strpos($rule, '*') && !preg_match('/[\[\]\s<>"\']|^:/', $rule)) {
                    $add($rule, $category);
                }
            }
        }
        $result['consent_block'] = self::narrowest($result['consent_block']);
        return $result;
    }

    /** @return array */
    public function pages() {
        $needles = array('[rcb-cookie-policy', '[rcb-consent', '#consent-change', '#consent-history', '#consent-revoke');
        $regex = '/\[rcb-(?:cookie-policy|consent-history-uuids|consent-print-uuid|consent)\b[^\]]*\]|<a\b[^>]*href=["\']#consent-(?:change|history|revoke)["\'][^>]*>.*?<\/a>/is';
        $pages = array();
        foreach ($this->find_pages($needles, $regex) as $page) {
            $tokens = array();
            $cookie = false;
            foreach ($page['matches'] as $token) {
                $kind = 'other';
                if (0 === stripos($token, '[rcb-cookie-policy')) {
                    $kind = 'cookie';
                } elseif (preg_match('/^\[rcb-consent\s[^\]]*\btype=["\']?([a-z]+)/i', $token, $type)) {
                    $kind = self::LINKS[strtolower($type[1])] ?? 'other';
                }
                $entry = array('token' => $token, 'cookie' => 'cookie' === $kind, 'renderable' => 'other' !== $kind, 'kind' => $kind);
                if ('settings' === $kind) {
                    $entry['replace'] = '<!-- wp:seoprocookieconsent/cookie-settings /-->';
                }
                $tokens[] = $entry;
                $cookie = $cookie || $entry['cookie'];
            }
            $pages[] = array('id' => $page['id'], 'title' => $page['title'], 'cookie' => $cookie, 'tokens' => $tokens);
        }
        return $pages;
    }

    /**
     * One cookie per site and context, named from its cookie version, blog,
     * home address, cookie path and domain (a language suffix may follow).
     * Its value is "time:uuid:revision:JSON"; the JSON's "d" maps group term
     * IDs to the service post IDs accepted, "bc" names the button pressed.
     * A category allows when every optional service in it was accepted;
     * one with no services of its own follows them all, as Accept all does.
     *
     * @return array
     */
    public function consent_cookie() {
        if (!$this->asked()) {
            return array();
        }
        $optional = array();
        $map = array('preferences' => array(), 'statistics' => array(), 'embeds' => array(), 'marketing' => array());
        foreach ($this->services() as $id => $service) {
            if (!$service['essential'] && 'publish' === $service['status']) {
                $optional[] = (string) $id;
                $map[$service['category']][] = (string) $id;
            }
        }
        if (!$optional) {
            return array();
        }
        foreach ($map as $category => $ids) {
            $map[$category] = $ids ?: $optional;
        }
        return array('ids' => $this->cookie_names(), 'buttons' => self::BUTTONS, 'categories' => $map, 'allow' => '1', 'deny' => '0');
    }

    /**
     * Its cookie names for both schemes: the name follows the request's scheme.
     * Version 3 appends "wildcard" or "no-wildcard" to the hashed address, with no separator.
     *
     * @return string[]
     */
    private function cookie_names() {
        $version = (int) get_option('rcb-cookie-version', 3);
        $version = in_array($version, array(1, 2, 3), true) ? $version : 3;
        $home = !is_multisite() && defined('WP_HOME') ? (string) constant('WP_HOME') : (string) get_option('home');
        $names = array();
        foreach (array('http', 'https') as $scheme) {
            $context = 'blog:' . get_current_blog_id();
            if ($version > 1) {
                $address = untrailingslashit(set_url_scheme(trailingslashit($home), $scheme)) . (defined('COOKIEPATH') ? COOKIEPATH : '/');
                if ($version > 2) {
                    $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : false;
                    $address .= is_string($domain) && 0 === strpos($domain, '.') ? 'wildcard' : 'no-wildcard';
                }
                $context = 'v:' . $version . '_' . $context . '_path:' . substr(md5($address), 0, 7);
            }
            $names[] = 'real_cookie_banner-' . $context;
        }
        return array_values(array_unique($names));
    }

    /**
     * Its services with their group, category and code. Read directly: its
     * taxonomy is not registered while it is inactive.
     *
     * @return array<int,array{title:string,status:string,essential:bool,category:string,optin:string,code:string}>
     */
    private function services() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the source's own services and groups.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT p.ID, p.post_title, p.post_name, p.post_status, t.slug, t.term_id FROM {$wpdb->posts} p LEFT JOIN {$wpdb->term_relationships} r ON r.object_id = p.ID LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id AND tt.taxonomy = %s LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE p.post_type = 'rcb-cookie' AND p.post_status NOT IN ('trash', 'auto-draft') ORDER BY p.ID ASC LIMIT 500", self::GROUPS));
        $blocked = array();
        foreach ($this->blockers() as $blocker) {
            foreach ($blocker['services'] as $id) {
                $blocked[$id] = ($blocked[$id] ?? '') . ' ' . implode(' ', $blocker['rules']);
            }
        }
        $known = SEOProCookieConsent_Services::all();
        $services = array();
        foreach ($rows as $row) {
            $id = (int) $row->ID;
            if (isset($services[$id]) && null === $row->term_id) {
                continue;
            }
            // Opt-in code runs after consent; page-load code runs for everyone.
            $optin = (string) get_post_meta($id, 'codeOptIn', true);
            $code = implode("\n", array_filter(array($optin, (string) get_post_meta($id, 'codeOptOut', true), (string) get_post_meta($id, 'codeOnPageLoad', true))));
            $essential = $row->term_id && (bool) get_term_meta((int) $row->term_id, 'isEssential', true);
            $services[$id] = array(
                'title'     => (string) $row->post_title,
                'status'    => (string) $row->post_status,
                'essential' => $essential,
                'category'  => $this->category(array((string) get_post_meta($id, 'presetId', true), (string) get_post_meta($id, 'uniqueName', true), (string) $row->post_name), (string) $row->slug, $optin . ' ' . ($blocked[$id] ?? ''), $known),
                'optin'     => $optin,
                'code'      => $code,
            );
        }
        return $services;
    }

    /**
     * A recognised service by template or unique name, then by its code and
     * blocked addresses; otherwise its group: Statistics, Marketing, or
     * functional groups as Preferences.
     *
     * @param string[] $names Template ID, unique name, slug.
     * @param string   $group Group slug.
     * @param string   $text  Code and content blocker rules.
     * @param array    $known Recognised services.
     * @return string
     */
    private function category(array $names, $group, $text, array $known) {
        foreach (array_filter($names) as $name) {
            foreach ($known as $key => $service) {
                if ($name === $key || 0 === strpos($name, $key . '-')) {
                    return $service['category'];
                }
            }
        }
        $match = SEOProCookieConsent_Services::match(str_replace('*', '', $text));
        if ('' !== $match) {
            return $known[$match]['category'];
        }
        return in_array($group, array('statistics', 'marketing'), true) ? $group : 'preferences';
    }

    /** Published content blockers: rules (one per line) and service IDs (comma-separated). @return array<int,array{rules:string[],services:int[]}> */
    private function blockers() {
        global $wpdb;
        $blockers = array();
        // Direct, so multilingual plugins do not narrow it to one language; works while the source is inactive.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the source's own content blockers.
        foreach ($wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'rcb-blocker' AND post_status = 'publish' ORDER BY ID ASC LIMIT 200") as $id) {
            $rules = array_values(array_filter(array_map('trim', explode("\n", (string) get_post_meta((int) $id, 'rules', true))), function ($rule) {
                return '' !== $rule;
            }));
            $services = array_values(array_filter(array_map('intval', explode(',', (string) get_post_meta((int) $id, 'services', true)))));
            $blockers[(int) $id] = array('rules' => $rules, 'services' => $services);
        }
        return $blockers;
    }

    /** Menu items with its link targets. @return int */
    private function menu_links() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only count of the source's menu links.
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_url' AND meta_value IN ('#consent-change', '#consent-history', '#consent-revoke')");
    }

    /** @return string[] */
    private function tables() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- SHOW only; escapes the site's prefix literally.
        return $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'rcb_') . '%'));
    }

    /**
     * Exact names and IDs only. Its consent records and the revisions they
     * point to are listed, never removed.
     *
     * @return array
     */
    public function leftovers() {
        global $wpdb;
        $options = array();
        foreach (array('rcb-', 'rcb_', 'real-cookie-banner', 'real_cookie_banner', '_transient_rcb', '_transient_timeout_rcb', '_site_transient_rcb', '_site_transient_timeout_rcb') as $prefix) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- indexed, source-owned option prefixes; names only, never licence values.
            $options = array_merge($options, $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT 1000", $wpdb->esc_like($prefix) . '%')));
        }
        // Its licence client's options carry its slug as a suffix; others belong to sibling plugins.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- source-owned licence client options; names only.
        $options = array_merge($options, $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND (option_name LIKE %s OR option_name LIKE %s) ORDER BY option_name LIMIT 100", $wpdb->esc_like('rpm-wpc-') . '%', '%' . $wpdb->esc_like('_real-cookie-banner'), '%' . $wpdb->esc_like('_real-cookie-banner-') . '%')));
        $hooks = array();
        foreach (_get_cron_array() ?: array() as $events) {
            foreach (array_keys($events) as $hook) {
                if (0 === strpos($hook, 'rcb') || 0 === strpos($hook, 'real_cookie_banner') || 0 === strpos($hook, 'real-cookie-banner')) {
                    $hooks[] = $hook;
                }
            }
        }
        $tables = $this->tables();
        $records = array_values(array_filter($tables, function ($table) use ($wpdb) {
            return 0 === strpos($table, $wpdb->prefix . 'rcb_consent') || 0 === strpos($table, $wpdb->prefix . 'rcb_revision');
        }));
        list($service, $blocker, $link, $vendor) = self::POST_TYPES;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- IDs only, of the source's own post types.
        $posts = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type IN (%s, %s, %s, %s) ORDER BY ID ASC LIMIT 1000", $service, $blocker, $link, $vendor)));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- term IDs only, of the source's own taxonomy.
        $terms = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s ORDER BY term_id ASC LIMIT 200", self::GROUPS)));
        return array(
            'options' => array_values(array_unique($options)),
            'tables'  => array_values(array_diff($tables, $records)),
            'cron'    => array_values(array_unique($hooks)),
            'posts'   => $posts,
            'terms'   => $terms ? array(self::GROUPS => $terms) : array(),
            'kept'    => $records,
        );
    }

    /** @return string */
    public function uninstall_setting() {
        return __('Deleting Real Cookie Banner removes only its copied script files; its settings, services, content blockers and consent records stay. Remove leftover data deletes its settings, services, content blockers, groups and scan and statistics tables, but keeps consent records and the revisions they refer to. Imports never change its settings.', 'seoprocookieconsent');
    }
}
