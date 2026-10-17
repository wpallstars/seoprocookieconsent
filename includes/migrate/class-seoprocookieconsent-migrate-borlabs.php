<?php
/**
 * Borlabs Cookie (borlabs-cookie, sold by Borlabs, not on WordPress.org)
 * data formats, checked against version 3.4.6 and a running 3.4.6 site.
 * Its settings are serialized PHP objects; they are read as plain arrays,
 * never as its classes, so they are read while it is inactive or deleted.
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

final class SEOProCookieConsent_Migrate_Borlabs extends SEOProCookieConsent_Migrate_Source {
    const KEY = 'borlabs-cookie';
    const NAME = 'Borlabs Cookie';
    const SLUGS = array('borlabs-cookie');

    /** Its option and cron hook prefix. */
    const PREFIX = 'BorlabsCookie';

    /** Its built-in service, always accepted with a choice. */
    const OWN = 'borlabs-cookie';

    /** Its built-in groups: group key => category here. */
    const GROUPS = array('statistics' => 'statistics', 'marketing' => 'marketing', 'external-media' => 'embeds', 'unclassified' => 'marketing');

    /** Its shortcode types: kind here. */
    const LINKS = array('btn-consent-preferences' => 'settings', 'btn-cookie-preference' => 'settings', 'service-list' => 'cookie', 'cookie-list' => 'cookie');

    /** The class it turns into a privacy settings link. */
    const OPEN_CLASS = 'borlabs-cookie-open-dialog-preferences';

    /** @var array<string,array> */
    private $configs = array();

    /** @var string|null */
    private $language = null;

    /**
     * One of its per-language settings objects as a plain array: the site's
     * language, else the first one saved. Unserialized without classes.
     *
     * @param string $name GeneralConfig or DialogSettingsConfig.
     * @return array
     */
    private function config($name) {
        if (isset($this->configs[$name])) {
            return $this->configs[$name];
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the source's own per-language settings.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT 50", $wpdb->esc_like(self::PREFIX . $name . '_') . '%'), ARRAY_A);
        $wanted = self::PREFIX . $name . '_' . $this->language();
        $row = null;
        foreach ((array) $rows as $candidate) {
            if ($wanted === $candidate['option_name']) {
                $row = $candidate;
                break;
            }
            $row = $row ?: $candidate;
        }
        $value = false;
        if ($row && is_serialized($row['option_value'])) {
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- no classes are instantiated; its objects become incomplete-class arrays.
            $value = unserialize($row['option_value'], array('allowed_classes' => false));
        }
        $this->configs[$name] = is_object($value) || is_array($value) ? (array) $value : array();
        return $this->configs[$name];
    }

    /** Its language code for this site: the first part of the locale. @return string */
    private function language() {
        if (null === $this->language) {
            $this->language = strtolower((string) strtok((string) get_locale(), '_'));
        }
        return $this->language;
    }

    /** @param array $config Settings. @param string $key Setting. @return bool */
    private static function on(array $config, $key) {
        return !empty($config[$key]);
    }

    /** Its settings exist. @return bool */
    private function configured() {
        return (bool) $this->config('GeneralConfig');
    }

    /**
     * Its dialog asked every visitor. With geo-IP or hidden pages it saved
     * "accept all" for some visitors without asking.
     *
     * @return bool
     */
    private function asked() {
        $general = $this->config('GeneralConfig');
        $dialog = $this->config('DialogSettingsConfig');
        return self::on($general, 'borlabsCookieStatus') && !self::on($general, 'setupMode')
            && $dialog && self::on($dialog, 'showDialog') && !self::on($dialog, 'geoIpActive') && empty($dialog['hideDialogOnPages']);
    }

    /** @return array */
    public function detect() {
        $left = $this->leftovers();
        $notes = array();
        $notes[] = __('Visitors\' Borlabs Cookie choices are not carried over: its cookie version, which decides whether a saved choice is still valid, could not be checked, so visitors are asked again once.', 'seoprocookieconsent');
        $coded = array();
        foreach ($this->services() as $service) {
            if ('' !== trim($service['code'])) {
                $coded[] = $service['name'];
            }
        }
        if ($coded) {
            /* translators: %s: list of service names. */
            $notes[] = sprintf(__('Borlabs Cookie ran code for these services, which stops when it is removed: %s. Add any code you still need to your theme or a header-code plugin; services this plugin recognises, and the script rules below, are then held back.', 'seoprocookieconsent'), implode(', ', array_unique($coded)));
        }
        $blockers = $this->script_blockers();
        if ($blockers) {
            /* translators: %s: list of script blocker names. */
            $notes[] = sprintf(__('Borlabs Cookie script blockers match scripts by handle or code, which this import does not turn into rules: %s. Add anything this plugin does not recognise under Troubleshooting → Also hold back.', 'seoprocookieconsent'), implode(', ', $blockers));
        }
        if ($this->menu_links()) {
            /* translators: %s: CSS class name. */
            $notes[] = sprintf(__('A menu link has the %s class. Point it at #cookie-settings instead, or rely on the Cookie settings footer link.', 'seoprocookieconsent'), self::OPEN_CLASS);
        }
        if ($this->configured()) {
            $notes[] = __('Its dialog texts are not imported: they describe its own dialog. Borlabs Cookie kept consent records; turn on Keep consent records if you need to go on documenting choices.', 'seoprocookieconsent');
        }
        return array('present' => (bool) array_filter($left) || (bool) $this->pages(), 'state' => $this->state(), 'notes' => $notes);
    }

    /** @return array */
    public function settings() {
        $general = $this->config('GeneralConfig');
        $dialog = $this->config('DialogSettingsConfig');
        if (!$general) {
            return array();
        }
        $result = array();
        if ($this->asked()) {
            $result['consent_regions'] = 'everywhere';
        }
        $days = (int) ($general['cookieLifetime'] ?? 0);
        if ($days > 0) {
            $result['consent_days'] = $days;
        }
        $layout = (string) ($dialog['layout'] ?? '');
        $position = (string) ($dialog['position'] ?? '');
        if (0 === strpos($layout, 'bar') && 0 === strpos($position, 'bottom-')) {
            $result['consent_position'] = 'bar';
        } elseif (0 === strpos($layout, 'box') && in_array($position, array('bottom-left', 'bottom-right'), true)) {
            $result['consent_position'] = 'bottom-left' === $position ? 'left' : 'right';
        }
        $page = (int) ($dialog['privacyPageId'] ?? 0);
        $url = (string) ($dialog['privacyPageCustomUrl'] ?? '');
        if ('' === $url && $page > 0 && 'publish' === get_post_status($page) && (int) get_option('wp_page_for_privacy_policy') !== $page) {
            $url = (string) get_permalink($page);
        }
        if ('' !== $url && wp_http_validate_url($url)) {
            $result['consent_policy_url'] = $url;
        }
        return $result;
    }

    /**
     * Addresses its optional services' opt-in code loads, its service
     * locations and its content blocker locations become hold-back rules
     * in the service's category; a content blocker without a service holds
     * back embeds.
     *
     * @return array
     */
    public function scripts() {
        $lines = array();
        $add = function ($snippet, $category) use (&$lines) {
            $rule = self::script_rule($snippet);
            if (is_string($rule) && '' !== $rule) {
                $lines[] = ('marketing' !== $category ? $category . ': ' : '') . $rule;
            }
        };
        $services = $this->services();
        foreach ($services as $service) {
            if ('' === $service['category']) {
                continue;
            }
            preg_match_all('~<(?:script|iframe|img|link)\b[^>]*\b(?:src|href)=["\']([^"\']+)["\']~i', $service['optin'], $urls);
            foreach (array_merge(array_unique($urls[1]), $service['locations']) as $url) {
                $add($url, $service['category']);
            }
        }
        foreach ($this->content_blockers() as $blocker) {
            $category = isset($services[$blocker['service']]) ? $services[$blocker['service']]['category'] : 'embeds';
            if ('' === $category) {
                continue;
            }
            foreach ($blocker['locations'] as $url) {
                $add($url, $category);
            }
        }
        return array('consent_block' => self::narrowest($lines), 'consent_allow' => array());
    }

    /** @return array */
    public function pages() {
        $needles = array('[borlabs-cookie', self::OPEN_CLASS);
        $regex = '/\[borlabs-cookie\b[^\]]*\]|<(a|button)\b[^>]*\b' . self::OPEN_CLASS . '\b[^>]*>.*?<\/\1>/is';
        $pages = array();
        foreach ($this->find_pages($needles, $regex) as $page) {
            $tokens = array();
            $cookie = false;
            foreach ($page['matches'] as $token) {
                if ('[' !== $token[0]) {
                    $entry = array('token' => $token, 'cookie' => false, 'renderable' => true, 'kind' => 'link', 'replace' => self::settings_link($token));
                } else {
                    $kind = preg_match('/\btype=["\']?([a-z-]+)/i', $token, $type) ? (self::LINKS[strtolower($type[1])] ?? 'other') : 'other';
                    $entry = array('token' => $token, 'cookie' => 'cookie' === $kind, 'renderable' => 'other' !== $kind, 'kind' => $kind);
                    if ('settings' === $kind) {
                        $entry['replace'] = '<!-- wp:seoprocookieconsent/cookie-settings /-->';
                    }
                }
                $tokens[] = $entry;
                $cookie = $cookie || $entry['cookie'];
            }
            $pages[] = array('id' => $page['id'], 'title' => $page['title'], 'cookie' => $cookie, 'tokens' => $tokens);
        }
        return $pages;
    }

    /**
     * Not carried over. Its "borlabs-cookie" cookie lists accepted service
     * keys per group, but whether a saved choice is still current depends
     * on a cookie version that could not be checked, so visitors are asked
     * again.
     *
     * @return array
     */
    public function consent_cookie() {
        return array();
    }

    /**
     * Its active services in the site's language (else the first saved),
     * with category ('' for essential), opt-in code and locations.
     *
     * @return array<int,array{key:string,name:string,category:string,optin:string,code:string,locations:string[]}>
     */
    private function services() {
        global $wpdb;
        $services = $wpdb->prefix . 'borlabs_cookie_services';
        $groups = $wpdb->prefix . 'borlabs_cookie_service_groups';
        if (!$this->has_table($services) || !$this->has_table($groups)) {
            return array();
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the source's own services.
        $rows = $wpdb->get_results($wpdb->prepare('SELECT s.id, s.key, s.name, s.language, s.opt_in_code, s.opt_out_code, s.fallback_code, g.key AS group_key FROM %i s LEFT JOIN %i g ON g.id = s.service_group_id WHERE s.status = 1 ORDER BY s.id ASC LIMIT 500', $services, $groups));
        $languages = array_unique(wp_list_pluck((array) $rows, 'language'));
        $language = in_array($this->language(), $languages, true) ? $this->language() : (string) reset($languages);
        $locations = array();
        if ($this->has_table($wpdb->prefix . 'borlabs_cookie_service_locations')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the source's own service locations.
            foreach ((array) $wpdb->get_results($wpdb->prepare('SELECT service_id, hostname, path FROM %i ORDER BY id ASC LIMIT 1000', $wpdb->prefix . 'borlabs_cookie_service_locations')) as $location) {
                $locations[(int) $location->service_id][] = '//' . $location->hostname . $location->path;
            }
        }
        $known = SEOProCookieConsent_Services::all();
        $result = array();
        foreach ((array) $rows as $row) {
            if ($language !== $row->language || self::OWN === $row->key) {
                continue;
            }
            $id = (int) $row->id;
            $here = $locations[$id] ?? array();
            $result[$id] = array(
                'key'       => (string) $row->key,
                'name'      => (string) $row->name,
                'category'  => 'essential' === $row->group_key ? '' : $this->category((string) $row->key, (string) $row->group_key, $row->opt_in_code . ' ' . implode(' ', $here), $known),
                'optin'     => (string) $row->opt_in_code,
                'code'      => implode("\n", array_filter(array((string) $row->opt_in_code, (string) $row->opt_out_code, (string) $row->fallback_code))),
                'locations' => $here,
            );
        }
        return $result;
    }

    /**
     * A recognised service by key, then by its code and locations;
     * otherwise its group. A group of the owner's own counts as Marketing.
     *
     * @param string $key   Service key.
     * @param string $group Group key.
     * @param string $text  Code and locations.
     * @param array  $known Recognised services.
     * @return string
     */
    private function category($key, $group, $text, array $known) {
        foreach ($known as $name => $service) {
            if ($key === $name || 0 === strpos($key, $name . '-')) {
                return $service['category'];
            }
        }
        $match = SEOProCookieConsent_Services::match($text);
        if ('' !== $match) {
            return $known[$match]['category'];
        }
        return self::GROUPS[$group] ?? 'marketing';
    }

    /** Active content blockers other than its default, with their service and locations. @return array<int,array{service:int,locations:string[]}> */
    private function content_blockers() {
        global $wpdb;
        $table = $wpdb->prefix . 'borlabs_cookie_content_blockers';
        $places = $wpdb->prefix . 'borlabs_cookie_content_blocker_locations';
        if (!$this->has_table($table) || !$this->has_table($places)) {
            return array();
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the source's own content blockers.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT b.id, b.service_id, l.hostname, l.path FROM %i b JOIN %i l ON l.content_blocker_id = b.id WHERE b.status = 1 AND b.key <> 'default' ORDER BY b.id ASC LIMIT 1000", $table, $places));
        $blockers = array();
        foreach ((array) $rows as $row) {
            $blockers[(int) $row->id]['service'] = (int) $row->service_id;
            $blockers[(int) $row->id]['locations'][] = '//' . $row->hostname . $row->path;
        }
        return $blockers;
    }

    /** Names of its active script blockers. @return string[] */
    private function script_blockers() {
        global $wpdb;
        $table = $wpdb->prefix . 'borlabs_cookie_script_blockers';
        if (!$this->has_table($table)) {
            return array();
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only read of the source's own script blocker names.
        return array_values(array_unique(array_map('strval', $wpdb->get_col($wpdb->prepare('SELECT name FROM %i WHERE status = 1 ORDER BY id ASC LIMIT 100', $table)))));
    }

    /** Menu items with its settings class. @return int */
    private function menu_links() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded admin-only count of the source's menu links.
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_classes' AND meta_value LIKE %s", '%' . $wpdb->esc_like(self::OPEN_CLASS) . '%'));
    }

    /** @param string $table Table. @return bool */
    private function has_table($table) {
        return in_array($table, $this->tables(), true);
    }

    /** @return string[] */
    private function tables() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- SHOW only; escapes the site's prefix literally.
        return $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'borlabs_cookie_') . '%'));
    }

    /**
     * Exact names only. Its consent log and consent statistics tables are
     * listed, never removed. Its generated files in uploads are not listed.
     *
     * @return array
     */
    public function leftovers() {
        global $wpdb;
        $tables = $this->tables();
        $records = array_values(array_filter($tables, function ($table) use ($wpdb) {
            return 0 === strpos($table, $wpdb->prefix . 'borlabs_cookie_consent_');
        }));
        // Its foreign keys: cleanup drops in this order, so tables others refer to go last.
        $parents = array($wpdb->prefix . 'borlabs_cookie_services' => 1, $wpdb->prefix . 'borlabs_cookie_providers' => 2, $wpdb->prefix . 'borlabs_cookie_service_groups' => 2);
        $removable = array_values(array_diff($tables, $records));
        usort($removable, function ($a, $b) use ($parents) {
            return (($parents[$a] ?? 0) <=> ($parents[$b] ?? 0)) ?: strcmp($a, $b);
        });
        return array(
            'options' => self::option_names(array(self::PREFIX)),
            'tables'  => $removable,
            'cron'    => self::cron_hooks(array(self::PREFIX)),
            'kept'    => $records,
        );
    }

    /** @return string */
    public function uninstall_setting() {
        return __('Deleting Borlabs Cookie runs its own uninstall, which removes all its data, including its consent log: export the consent records you must keep before deleting it. Remove leftover data removes its settings, services, content blockers and other tables after it is deactivated, keeps its consent log and statistics, and leaves its generated files in uploads/borlabs-cookie. Imports never change its settings.', 'seoprocookieconsent');
    }
}
