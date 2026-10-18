<?php
/**
 * Cookie consent: holds back scripts and embeds that set cookies until
 * visitors allow them, with a short banner only on pages that need it and
 * only as strict as the visitor's region requires (docs/architecture.md).
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: 2026 Marcus Quinn
 * Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
 * Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
 *
 * @package SEOProCookieConsent
 * @since 0.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SEOProCookieConsent_Consent extends SEOProCookieConsent_Feature {

    const KEY = 'consent';

    /** Plugins this feature does the job of. */
    const REPLACES = array(
        'complianz-gdpr'         => 'Complianz',
        'complianz-gdpr-premium' => 'Complianz Premium',
        'cookie-law-info'        => 'CookieYes',
        'cookie-notice'          => 'Cookie Notice & Compliance',
        'cookieadmin'            => 'CookieAdmin',
        'gdpr-cookie-compliance' => 'GDPR Cookie Compliance',
        'wpconsent-cookies-banner-privacy-suite' => 'WPConsent',
        'real-cookie-banner'     => 'Real Cookie Banner',
        'real-cookie-banner-pro' => 'Real Cookie Banner Pro',
        'cookiebot'              => 'Cookiebot',
        'iubenda-cookie-law-solution' => 'iubenda',
        'uk-cookie-consent'      => 'Termly',
        'borlabs-cookie'         => 'Borlabs Cookie',
        'ct-ultimate-gdpr'       => 'Ultimate GDPR & CCPA',
    );

    /** Google services sent before consent with Consent Mode advanced. */
    const GOOGLE_SERVICES = array('google-analytics', 'google-ads', 'google-tag-manager');

    /** Theme menu locations treated as the footer menu. */
    const FOOTER_LOCATIONS = array('footer', 'footer-menu', 'footer_menu', 'footer-navigation', 'footer_navigation', 'footer-links', 'secondary-footer');

    /** Name of the consent cookie (necessary). */
    const COOKIE = 'spcc_consent';

    /** Query argument for the settings screen's banner preview: opt-in or opt-out. */
    const PREVIEW_ARG = 'spcc_preview';

    /** The banner preview's model, once worked out. @var string|null */
    private static $preview = null;

    /** The page's blocker, when holding back. @var SEOProCookieConsent_Blocker|null */
    private static $blocker = null;

    /** Whether the banner was printed. @var bool */
    private static $printed = false;

    /** Whether a Cookie settings link was printed in a menu. @var bool */
    private static $menu_link = false;

    /** Where the automatic footer link goes, once worked out. @var array|null */
    private static $menu_plan = null;

    /** URL of the Cookie settings link. */
    const SETTINGS_ANCHOR = '#cookie-settings';

    /**
     * Settings.
     *
     * @return array<string,array>
     */
    public static function settings() {
        return array(
            self::KEY                  => array(
                'type'        => 'bool',
                'default'     => false,
                'tab'         => 'consent',
                'label'       => __('Cookie consent', 'seoprocookieconsent'),
                'description' => __('Holds back scripts and embeds that set cookies, such as Google Analytics, ad pixels and YouTube videos, until visitors allow them. A short banner shows only on pages that have them.', 'seoprocookieconsent'),
                'replaces'    => self::REPLACES,
                'reload'      => true,
            ),
            'consent_regions'          => array(
                'type'        => 'select',
                'default'     => 'region',
                'parent'      => self::KEY,
                'label'       => __('Who is asked first', 'seoprocookieconsent'),
                'description' => __('Europe (EU, EEA, UK, Switzerland), Quebec and visitors whose location is unclear are always asked before anything loads. The browser\'s time zone tells them apart: nothing is looked up or sent anywhere.', 'seoprocookieconsent'),
                'options'     => array(
                    'region'       => __('By region, with a notice elsewhere', 'seoprocookieconsent'),
                    'region_quiet' => __('By region, no notice elsewhere', 'seoprocookieconsent'),
                    'everywhere'   => __('Everyone', 'seoprocookieconsent'),
                ),
            ),
            'consent_position'         => array(
                'type'    => 'select',
                'default' => 'bar',
                'parent'  => self::KEY,
                'label'   => __('Banner position', 'seoprocookieconsent'),
                'options' => array(
                    'bar'   => __('Bar along the bottom', 'seoprocookieconsent'),
                    'left'  => __('Box at the bottom left', 'seoprocookieconsent'),
                    'right' => __('Box at the bottom right', 'seoprocookieconsent'),
                ),
            ),
            'consent_message'          => array(
                'type'        => 'text',
                'default'     => '',
                'parent'      => self::KEY,
                'label'       => __('Banner message', 'seoprocookieconsent'),
                'description' => __('Leave empty for a message that names what is on the page, such as "This page uses YouTube and Google Analytics, which may set cookies."', 'seoprocookieconsent'),
                'placeholder' => __('Automatic', 'seoprocookieconsent'),
            ),
            'consent_policy_url'       => array(
                'type'        => 'url',
                'default'     => '',
                'parent'      => self::KEY,
                'network'     => false,
                'label'       => __('Cookie policy page', 'seoprocookieconsent'),
                'description' => __('Linked from the banner. Start typing to pick a page, or enter an address. Leave empty for your privacy policy page (Settings → Privacy). Add the Cookie policy block to whichever page you use.', 'seoprocookieconsent'),
                'placeholder' => __('Your privacy policy page', 'seoprocookieconsent'),
            ),
            'consent_menu_link'        => array(
                'type'        => 'bool',
                'default'     => true,
                'parent'      => self::KEY,
                'label'       => __('Cookie settings link in the footer menu', 'seoprocookieconsent'),
                'description' => __('Lets visitors change their mind at any time. It goes after your cookie policy link, or before your privacy policy link, in a footer menu; otherwise at the end of the theme\'s footer menu. To place it yourself, use the Cookie settings box in Appearance → Menus; then this adds nothing. No menu? Add the Cookie settings button block, or link to #cookie-settings anywhere.', 'seoprocookieconsent'),
            ),
            'consent_floating_button'  => array(
                'type'        => 'bool',
                'default'     => false,
                'parent'      => self::KEY,
                'label'       => __('Floating Cookie settings button', 'seoprocookieconsent'),
                'description' => __('A small button in the bottom corner after visitors choose. It shows only on pages with no other Cookie settings link or button.', 'seoprocookieconsent'),
            ),
            'consent_block'            => array(
                'type'        => 'lines',
                'default'     => '',
                'parent'      => self::KEY,
                'group'       => 'troubleshooting',
                'label'       => __('Also hold back', 'seoprocookieconsent'),
                'description' => __('Scripts or embeds the plugin does not know, one per line: part of their address or code. They count as Marketing; start a line with "statistics:", "preferences:" or "embeds:" for another category.', 'seoprocookieconsent'),
                'placeholder' => 'statistics: example-analytics.com',
            ),
            'consent_allow'            => array(
                'type'        => 'lines',
                'default'     => '',
                'parent'      => self::KEY,
                'group'       => 'troubleshooting',
                'label'       => __('Never hold back', 'seoprocookieconsent'),
                'description' => __('Scripts or embeds that must always load, one per line: part of their address or code. Use this only for something that does not set cookies or is needed for the site to work.', 'seoprocookieconsent'),
            ),
            'consent_google_advanced'  => array(
                'type'        => 'bool',
                'default'     => false,
                'parent'      => self::KEY,
                'group'       => 'troubleshooting',
                'label'       => __('Load Google tags before consent (Consent Mode advanced)', 'seoprocookieconsent'),
                'description' => __('Google Analytics, Ads and Tag Manager load straight away and are told consent is denied until visitors allow it, so Google sends cookieless pings. Leave off unless your Google set-up needs it.', 'seoprocookieconsent'),
            ),
            'consent_records'          => array(
                'type'        => 'bool',
                'default'     => false,
                'parent'      => self::KEY,
                'label'       => __('Keep consent records', 'seoprocookieconsent'),
                'description' => __('Optional anonymous choice history, stored on this site. Adds a request and database write per choice, not per page view. No IP addresses or browser details are stored.', 'seoprocookieconsent'),
            ),
            'consent_policy_version'   => array(
                'type'        => 'text',
                'default'     => '1',
                'parent'      => self::KEY,
                'label'       => __('Policy version', 'seoprocookieconsent'),
                'description' => __('A label saved with each record. Change it when your policy changes. This does not ask visitors again or keep a copy of the policy text.', 'seoprocookieconsent'),
            ),
            'consent_records_days'     => array(
                'type'        => 'int',
                'default'     => 0,
                'min'         => 0,
                'max'         => 3650,
                'parent'      => self::KEY,
                'label'       => __('Keep records for (days)', 'seoprocookieconsent'),
                'description' => __('0 keeps them as long as visitors\' choices. Set this to how long you need evidence of consent. Records older than this are deleted.', 'seoprocookieconsent'),
            ),
            'consent_days'             => array(
                'type'        => 'int',
                'default'     => 365,
                'min'         => 30,
                'max'         => 395,
                'parent'      => self::KEY,
                'group'       => 'troubleshooting',
                'label'       => __('Remember choices for (days)', 'seoprocookieconsent'),
                'description' => __('Visitors are asked again after this. 365 is usual; some regulators prefer 180.', 'seoprocookieconsent'),
            ),
        );
    }

    /**
     * Import Complianz's settings once.
     *
     * @param array $options      Stored settings.
     * @param int   $from_version Settings version before.
     * @return array
     */
    public static function migrate(array $options, $from_version) {
        if ($from_version >= 2) {
            return $options;
        }
        return SEOProCookieConsent_Migrate::legacy_settings($options);
    }

    /**
     * Hooks.
     */
    public static function boot() {
        require_once SEOPROCOOKIECONSENT_DIR . 'includes/class-seoprocookieconsent-thumbnails.php';
        add_action(SEOProCookieConsent_Thumbnails::HOOK, array('SEOProCookieConsent_Thumbnails', 'fetch'), 10, 2);
        if (is_admin()) {
            add_action('seoprocookieconsent_setting_panel', array(__CLASS__, 'panel'), 10, 1);
            add_action('seoprocookieconsent_admin_enqueue', array(__CLASS__, 'admin_enqueue'));
            add_action('wp_ajax_seoprocookieconsent_scan', array(__CLASS__, 'scan'));
        }
        if (!self::enabled()) {
            return;
        }
        add_action('admin_init', array(__CLASS__, 'register_translations'));
        add_action('seoprocookieconsent_settings_changed', array(__CLASS__, 'register_translations'));
        if (isset($_GET[self::PREVIEW_ARG])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only shows the banner to the admin; preview() checks who asks.
            add_filter('show_admin_bar', array(__CLASS__, 'preview_admin_bar'));
        }
        add_shortcode('spcc_cookie_settings', array(__CLASS__, 'settings_button'));
        add_action('init', array(__CLASS__, 'register_block'), 20);
        add_action('template_redirect', array(__CLASS__, 'start'), 1000);
        add_action('shutdown', array(__CLASS__, 'record'), 2);
        add_action('wp_head', array(__CLASS__, 'head'), 1);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('wp_body_open', array(__CLASS__, 'banner'), 1);
        add_action('wp_footer', array(__CLASS__, 'footer'), 5);
        if (is_admin()) {
            // Added this late, the box is not among those WordPress hides for a user's first visit.
            add_action('admin_head-nav-menus.php', array(__CLASS__, 'add_menu_box'));
        }
        if (SEOProCookieConsent_Settings::get('consent_menu_link')) {
            add_filter('wp_nav_menu_items', array(__CLASS__, 'menu_link'), 10, 2);
        }
        add_filter('wp_get_consent_type', array(__CLASS__, 'consent_type'));
    }

    /**
     * Whether this request is a front-end HTML page to hold back in.
     *
     * @return bool
     */
    private static function front_page() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_robots() || is_trackback()
            || (defined('REST_REQUEST') && REST_REQUEST) || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)
            || (defined('WP_CLI') && WP_CLI) || is_customize_preview()) {
            return false;
        }
        // Page builders' editing views run their own scripts in the page.
        foreach (array('elementor-preview', 'fl_builder', 'et_fb', 'bricks', 'ct_builder', 'tve', 'vc_editable', 'brizy-edit-iframe') as $arg) {
            if (isset($_GET[$arg])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only checks which view this is.
                return false;
            }
        }
        /**
         * Filter whether SEO Pro Cookie Consent holds back scripts on this page.
         *
         * @param bool $hold Whether it does.
         */
        return (bool) apply_filters('seoprocookieconsent_hold_back', true);
    }

    /**
     * Start holding back.
     */
    public static function start() {
        if (!self::front_page()) {
            return;
        }
        $skip = SEOProCookieConsent_Settings::get('consent_google_advanced') ? self::GOOGLE_SERVICES : array();
        $allow = preg_split('/[\r\n]+/', (string) SEOProCookieConsent_Settings::get('consent_allow')) ?: array();
        self::$blocker = new SEOProCookieConsent_Blocker(
            $allow,
            SEOProCookieConsent_Blocker::parse_block_lines((string) SEOProCookieConsent_Settings::get('consent_block')),
            $skip
        );
        ob_start(array(__CLASS__, 'process'));
    }

    /**
     * Output buffer callback.
     *
     * @param string $html Page.
     * @return string
     */
    public static function process($html) {
        if (null === self::$blocker || !self::is_html_response()) {
            return $html;
        }
        return self::$blocker->process($html);
    }

    /**
     * Whether the response is HTML (no Content-Type header sent yet means
     * PHP's default, text/html).
     *
     * @return bool
     */
    private static function is_html_response() {
        foreach (headers_list() as $header) {
            if (0 === stripos($header, 'content-type:')) {
                return false !== stripos($header, 'text/html');
            }
        }
        return true;
    }

    /**
     * Remember what the page held back, after it was sent.
     */
    public static function record() {
        if (null === self::$blocker) {
            return;
        }
        $found = array_filter(self::$blocker->found(), function ($id) {
            return 0 !== strpos($id, 'custom:');
        });
        SEOProCookieConsent_Services::record($found, self::$blocker->hosts());
    }

    /**
     * The config the browser reads.
     *
     * @return array
     */
    public static function config() {
        $categories = SEOProCookieConsent_Services::categories();
        $services   = array();
        foreach (SEOProCookieConsent_Services::all() as $id => $service) {
            $services[$id] = array(
                'name'     => $service['name'],
                'category' => $service['category'],
                'cookies'  => array_keys($service['cookies']),
                // URL fragments only: no built-in inline-code signatures.
                'patterns' => array_values(array_filter($service['patterns'], function ($pattern) {
                    return 'gtm.start' !== $pattern && false === strpos($pattern, '(')
                        && (false !== strpos($pattern, '.') || 0 === strpos($pattern, 'gtag/js?'));
                })),
            );
        }
        $labels = array();
        foreach ($categories as $id => $category) {
            $labels[$id] = $category['label'];
        }
        $config = array(
            'version'    => 1,
            'regions'    => (string) SEOProCookieConsent_Settings::get('consent_regions'),
            'days'       => (int) SEOProCookieConsent_Settings::get('consent_days'),
            'cookie'     => self::COOKIE,
            'secure'     => is_ssl(),
            'migrate'    => SEOProCookieConsent_Migrate::front_config(),
            'records'    => SEOProCookieConsent_Settings::get('consent_records') ? rest_url('seoprocookieconsent/v1/records') : '',
            'policy'     => wp_html_excerpt((string) SEOProCookieConsent_Settings::get('consent_policy_version'), 100, ''),
            'categories' => SEOProCookieConsent_Services::optional_categories(),
            'labels'     => $labels,
            'services'   => $services,
            'images'     => SEOProCookieConsent_Thumbnails::bundled(),
            'dynamic'    => null !== self::$blocker,
            'allow'      => array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', strtolower((string) SEOProCookieConsent_Settings::get('consent_allow'))) ?: array()))),
            'block'      => SEOProCookieConsent_Blocker::parse_block_lines((string) SEOProCookieConsent_Settings::get('consent_block')),
            'skip'       => SEOProCookieConsent_Settings::get('consent_google_advanced') ? self::GOOGLE_SERVICES : array(),
            'seen'       => array_keys(SEOProCookieConsent_Services::seen()['services']),
            'text'       => array(
                /* translators: %s: service names, such as "Google Analytics, Hotjar". */
                'usedBy'  => __('Used by: %s.', 'seoprocookieconsent'),
                'message' => self::translated_setting('consent_message'),
                /* translators: %s: the services on the page, such as "YouTube and Google Analytics". */
                'optIn'   => __('This page uses %s, which may set cookies and share data about your visit. Choose what to allow.', 'seoprocookieconsent'),
                /* translators: %s: the services on the page, such as "YouTube and Google Analytics". */
                'optOut'  => __('This page uses %s, which may set cookies. You can turn them off in Cookie settings.', 'seoprocookieconsent'),
                'other'   => __('other services', 'seoprocookieconsent'),
                /* translators: 1: service name; 2: cookie category. */
                'embed'   => __('%1$s can set cookies. It loads when you allow it, or allow %2$s in Cookie settings.', 'seoprocookieconsent'),
                /* translators: 1: service name, such as "YouTube"; 2: cookie category. */
                'ask'     => __('%1$s needs %2$s to show here. Allow it to see this content.', 'seoprocookieconsent'),
                'content' => __('This content', 'seoprocookieconsent'),
                'load'    => __('Load content', 'seoprocookieconsent'),
                'settings' => __('Cookie settings', 'seoprocookieconsent'),
            ),
        );
        $preview = self::preview();
        if ('' !== $preview) {
            // The settings screen's preview: this banner, whatever the page
            // holds or the admin chose before, and nothing saved or loaded.
            $config['model']        = $preview;
            $config['preview']      = true;
            $config['previewNames'] = array_values(array_filter(array(
                isset($services['youtube']) ? $services['youtube']['name'] : '',
                isset($services['google-analytics']) ? $services['google-analytics']['name'] : '',
            )));
        }
        /**
         * Filter the config SEO Pro Cookie Consent's browser script reads.
         * Set `model` to "opt-in", "opt-out" or "quiet" to choose the
         * model on the server (only on sites without page caching).
         *
         * @param array $config Config.
         */
        $config = (array) apply_filters('seoprocookieconsent_front_config', $config);
        $config['snapshot'] = SEOProCookieConsent_Records::snapshot_hash($config);
        return $config;
    }

    /**
     * The banner preview asked for by the settings screen: "opt-in" or
     * "opt-out" for someone who can change the settings, else "".
     *
     * @return string
     */
    public static function preview() {
        if (null === self::$preview) {
            $model         = isset($_GET[self::PREVIEW_ARG]) ? sanitize_key(wp_unslash($_GET[self::PREVIEW_ARG])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view for the admin.
            self::$preview = in_array($model, array('opt-in', 'opt-out'), true) && current_user_can('manage_options') ? $model : '';
        }
        return self::$preview;
    }

    /**
     * No toolbar in the banner preview.
     *
     * @param bool $show Whether to show it.
     * @return bool
     */
    public static function preview_admin_bar($show) {
        return '' === self::preview() && $show;
    }

    /**
     * Head: the config, the region decision and Google Consent Mode
     * defaults, before any other script.
     */
    public static function head() {
        $file = SEOPROCOOKIECONSENT_DIR . 'assets/js/spcc-head.js';
        $code = is_readable($file) ? (string) file_get_contents($file) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local plugin file.
        $code = 'window.spccConfig=' . wp_json_encode(self::config(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ";\n" . $code;
        wp_print_inline_script_tag($code, array('id' => 'spcc-head', 'data-spcc-ignore' => true));
    }

    /**
     * Styles and the main script.
     */
    public static function enqueue() {
        wp_enqueue_style('seoprocookieconsent', SEOPROCOOKIECONSENT_URL . 'assets/css/spcc.css', array(), SEOProCookieConsent_Setup::asset_version('assets/css/spcc.css'));
        // In the footer on WordPress 6.2; deferred from 6.3.
        wp_enqueue_script('seoprocookieconsent', SEOPROCOOKIECONSENT_URL . 'assets/js/spcc.js', array(), SEOProCookieConsent_Setup::asset_version('assets/js/spcc.js'), array('in_footer' => true, 'strategy' => 'defer'));
    }

    /**
     * Banner and Cookie settings dialog, at the start of the body so they
     * come first for keyboards and screen readers.
     */
    public static function banner() {
        if (self::$printed || is_admin()) {
            return;
        }
        self::$printed = true;
        include SEOPROCOOKIECONSENT_DIR . 'includes/views/consent-banner.php';
    }

    /**
     * Where the banner sits: bar, left or right.
     *
     * @return string
     */
    public static function position() {
        $position = (string) SEOProCookieConsent_Settings::get('consent_position');
        return in_array($position, array('bar', 'left', 'right'), true) ? $position : 'bar';
    }

    /**
     * Footer: the banner when the theme has no wp_body_open, and the
     * floating Cookie settings button, when switched on and no menu link
     * or button was printed.
     */
    public static function footer() {
        self::banner();
        if (!self::$menu_link && SEOProCookieConsent_Settings::get('consent_floating_button')) {
            printf(
                '<button type="button" class="spcc-reopen" data-spcc-action="settings" hidden>%s</button>',
                esc_html__('Cookie settings', 'seoprocookieconsent')
            );
        }
    }

    /** Register owner strings on admin requests and after settings changes. */
    public static function register_translations() {
        foreach (array('consent_message', 'consent_policy_url') as $key) {
            $value = (string) SEOProCookieConsent_Settings::get($key);
            if ('' === $value) {
                continue;
            }
            do_action('wpml_register_single_string', 'SEO Pro Cookie Consent', $key, $value); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public registration API.
            if (function_exists('pll_register_string')) {
                pll_register_string($key, $value, 'SEO Pro Cookie Consent', 'consent_message' === $key);
            }
        }
    }

    /**
     * Read owner text in the page language, never the visitor's language.
     *
     * @param string $key Setting key.
     * @return string
     */
    private static function translated_setting($key) {
        $value = (string) SEOProCookieConsent_Settings::get($key);
        if ('' === $value) {
            return '';
        }
        $translated = (string) apply_filters('wpml_translate_single_string', $value, 'SEO Pro Cookie Consent', $key); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public translation API.
        if (function_exists('pll__')) {
            $translated = (string) pll__($value);
        } elseif ('consent_message' === $key && function_exists('trp_translate')) {
            // Plain text for JSON, without TranslatePress's editing wrappers.
            $translated = (string) trp_translate($translated, null, false);
        }
        return '' !== $translated ? $translated : $value;
    }

    /**
     * The cookie policy link, including a translated policy page when present.
     *
     * @return string
     */
    public static function policy_url() {
        $url = self::translated_setting('consent_policy_url');
        if ('' !== $url && '/' === $url[0]) {
            $url = home_url($url);
        }
        if ('' === $url) {
            $url = get_privacy_policy_url();
        }
        $page = '' !== $url ? url_to_postid($url) : 0;
        if ($page) {
            $translated = (int) apply_filters('wpml_object_id', $page, 'page', true); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public page translation API.
            if (function_exists('pll_get_post')) {
                $translated = (int) pll_get_post($page);
            }
            if ($translated && $translated !== $page && 'publish' === get_post_status($translated)) {
                $url = get_permalink($translated) ?: $url;
            }
        }
        return $url;
    }

    /**
     * Add Cookie settings to a footer menu: just after the cookie policy link, else just
     * before the privacy policy link, else at the end of the theme's footer menu.
     *
     * Footer menus are those in a footer theme location or named "footer", which covers
     * menus shown by widgets and blocks. A saved Cookie settings menu item wins: the
     * owner has placed it, so nothing is added.
     *
     * @param string $items Menu items HTML.
     * @param object $args  wp_nav_menu() arguments.
     * @return string
     */
    public static function menu_link($items, $args) {
        if (false !== strpos($items, self::SETTINGS_ANCHOR)) {
            return $items;
        }
        $plan = self::menu_plan();
        if ($plan['placed']) {
            return $items;
        }
        $html = null;
        if ($plan['menu']) {
            $menu = self::printed_menu($args);
            if (!$menu || (int) $menu->term_id !== $plan['menu']) {
                return $items;
            }
            $html = self::place_menu_link($items, $plan);
        } else {
            $location = is_object($args) && isset($args->theme_location) ? (string) $args->theme_location : '';
            if ('' === $location || !in_array($location, self::FOOTER_LOCATIONS, true)) {
                return $items;
            }
        }
        self::$menu_link = true;
        return null !== $html ? $html : $items . self::menu_item_html(self::menu_label($plan['title']));
    }

    /**
     * Where the footer link goes. Worked out once per request, from the site's menus.
     *
     * @return array{placed:bool,menu:int,item:int,where:string,title:string}
     */
    private static function menu_plan() {
        if (null !== self::$menu_plan) {
            return self::$menu_plan;
        }
        $plan = array('placed' => false, 'menu' => 0, 'item' => 0, 'where' => '', 'title' => '');
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one indexed lookup, only on requests that print a menu; drafts (added but not saved) do not count.
        $plan['placed'] = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE p.post_type = 'nav_menu_item' AND p.post_status = 'publish' AND m.meta_key = '_menu_item_url' AND m.meta_value LIKE %s LIMIT 1",
            '%' . $wpdb->esc_like(self::SETTINGS_ANCHOR)
        ));
        $targets = array();
        if ('' !== (string) SEOProCookieConsent_Settings::get('consent_policy_url')) {
            $url = self::policy_url();
            $targets[] = array('after', $url, (int) url_to_postid($url));
        }
        $targets[] = array('before', get_privacy_policy_url(), (int) get_option('wp_page_for_privacy_policy'));
        $menus = self::footer_menus();
        foreach ($targets as $target) {
            list($where, $url, $page) = $target;
            if ('' === $url && !$page) {
                continue;
            }
            foreach ($menus as $menu_id => $items) {
                foreach ($items as $item) {
                    if (self::menu_item_is($item, (string) $url, $page)) {
                        $plan = array_merge($plan, array('menu' => $menu_id, 'item' => (int) $item->ID, 'where' => $where, 'title' => (string) $item->title));
                        break 3;
                    }
                }
            }
        }
        self::$menu_plan = $plan;
        return $plan;
    }

    /**
     * Items of the footer menus, by menu ID.
     *
     * @return array<int,array>
     */
    private static function footer_menus() {
        $locations = array_map('intval', (array) get_nav_menu_locations());
        $menus = array();
        foreach ((array) wp_get_nav_menus() as $menu) {
            $placed = array_intersect(array_keys($locations, (int) $menu->term_id, true), self::FOOTER_LOCATIONS);
            if ($placed || false !== stripos($menu->name . ' ' . $menu->slug, 'footer')) {
                $menus[(int) $menu->term_id] = (array) wp_get_nav_menu_items($menu->term_id, array('update_post_term_cache' => false));
            }
        }
        return $menus;
    }

    /**
     * Whether a menu item links to a page, by ID or address.
     *
     * @param object $item Menu item.
     * @param string $url  Page address.
     * @param int    $page Page ID.
     * @return bool
     */
    private static function menu_item_is($item, $url, $page) {
        if ($page && isset($item->type, $item->object_id) && 'post_type' === $item->type && (int) $item->object_id === $page) {
            return true;
        }
        return '' !== $url && isset($item->url) && '' !== self::url_key($url) && self::url_key((string) $item->url) === self::url_key($url);
    }

    /**
     * Host and path of an address, for comparing links: no scheme, www., query or trailing slash.
     *
     * @param string $url Address.
     * @return string
     */
    private static function url_key($url) {
        $parts = wp_parse_url($url);
        if (!is_array($parts) || (empty($parts['host']) && empty($parts['path']))) {
            return '';
        }
        $host = !empty($parts['host']) ? $parts['host'] : (string) wp_parse_url(home_url(), PHP_URL_HOST);
        return preg_replace('/^www\./', '', strtolower($host)) . '/' . trim((string) ($parts['path'] ?? ''), '/');
    }

    /**
     * The menu wp_nav_menu() is printing.
     *
     * @param object $args wp_nav_menu() arguments.
     * @return WP_Term|null
     */
    private static function printed_menu($args) {
        if (!is_object($args)) {
            return null;
        }
        $menu = !empty($args->menu) ? wp_get_nav_menu_object($args->menu) : false;
        if (!$menu && !empty($args->theme_location)) {
            $locations = (array) get_nav_menu_locations();
            $menu = !empty($locations[$args->theme_location]) ? wp_get_nav_menu_object($locations[$args->theme_location]) : false;
        }
        return $menu instanceof WP_Term ? $menu : null;
    }

    /**
     * Put the link beside the planned item, or null when it cannot be found.
     *
     * @param string $items Menu items HTML.
     * @param array  $plan  Plan.
     * @return string|null
     */
    private static function place_menu_link($items, array $plan) {
        $id = (int) $plan['item'];
        // Walker_Nav_Menu prints id="menu-item-N" and a menu-item-N class; custom walkers keep at least one.
        if (!preg_match('/<li\b[^>]*?(?:\bid="menu-item-' . $id . '"|\bclass="[^"]*\bmenu-item-' . $id . '(?=[\s"]))[^>]*>/', $items, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $link = self::menu_item_html(self::menu_label($plan['title']));
        $start = (int) $m[0][1];
        if ('before' === $plan['where']) {
            return substr_replace($items, $link, $start, 0);
        }
        $open = $start + strlen($m[0][0]);
        $close = strpos($items, '</li>', $open);
        $sub = strpos($items, '<ul', $open);
        if (false === $close || (false !== $sub && $sub < $close)) {
            return null; // It has a submenu: the end of the menu is safer.
        }
        return substr_replace($items, $link, $close + 5, 0);
    }

    /**
     * A Cookie settings menu item.
     *
     * @param string $label Text.
     * @return string
     */
    private static function menu_item_html($label) {
        return sprintf(
            '<li class="menu-item menu-item-type-custom menu-item-object-custom spcc-menu-item"><a href="%s" data-spcc-action="settings">%s</a></li>',
            esc_attr(self::SETTINGS_ANCHOR),
            esc_html($label)
        );
    }

    /**
     * "Cookie settings", or "Cookie Settings" beside an English Title Case item such as "Cookie Policy".
     *
     * @param string $neighbour Title of the item it sits beside.
     * @return string
     */
    private static function menu_label($neighbour) {
        $label = __('Cookie settings', 'seoprocookieconsent');
        $neighbour = trim(wp_strip_all_tags(html_entity_decode((string) $neighbour, ENT_QUOTES, 'UTF-8')));
        if ('Cookie settings' === $label && preg_match('/^\p{Lu}\S*(?:\s+\p{Lu}\S*)+$/u', $neighbour)) {
            return 'Cookie Settings';
        }
        return $label;
    }

    /**
     * Add the Cookie settings box to Appearance → Menus.
     */
    public static function add_menu_box() {
        add_meta_box('seoprocookieconsent-menu-link', __('Cookie settings', 'seoprocookieconsent'), array(__CLASS__, 'menu_box'), 'nav-menus', 'side', 'low');
    }

    /**
     * The Cookie settings menu box, in core's markup so its Add to Menu button works.
     */
    public static function menu_box() {
        global $_nav_menu_placeholder;
        $_nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- core's shared placeholder ID for new menu items.
        $id = (int) $_nav_menu_placeholder;
        $label = self::menu_label(self::menu_plan()['title']);
        $field = 'menu-item[' . $id . ']';
        ?>
        <div id="posttype-seoprocookieconsent" class="posttypediv">
            <p class="description"><?php esc_html_e('A link that opens Cookie settings, so visitors can change their choices. Once a menu has it, the automatic footer link is not added.', 'seoprocookieconsent'); ?></p>
            <div id="tabs-panel-seoprocookieconsent" class="tabs-panel tabs-panel-active">
                <ul id="seoprocookieconsent-checklist" class="categorychecklist form-no-clear">
                    <li>
                        <label class="menu-item-title">
                            <input type="checkbox" class="menu-item-checkbox" name="<?php echo esc_attr($field); ?>[menu-item-object-id]" value="<?php echo esc_attr((string) $id); ?>" />
                            <?php echo esc_html($label); ?>
                        </label>
                        <input type="hidden" class="menu-item-type" name="<?php echo esc_attr($field); ?>[menu-item-type]" value="custom" />
                        <input type="hidden" class="menu-item-title" name="<?php echo esc_attr($field); ?>[menu-item-title]" value="<?php echo esc_attr($label); ?>" />
                        <input type="hidden" class="menu-item-url" name="<?php echo esc_attr($field); ?>[menu-item-url]" value="<?php echo esc_attr(self::SETTINGS_ANCHOR); ?>" />
                    </li>
                </ul>
            </div>
            <p class="button-controls wp-clearfix" data-items-type="posttype-seoprocookieconsent">
                <span class="add-to-menu">
                    <input type="submit" class="button submit-add-to-menu right" value="<?php esc_attr_e('Add to Menu', 'seoprocookieconsent'); ?>" name="add-post-type-menu-item" id="submit-posttype-seoprocookieconsent" />
                    <span class="spinner"></span>
                </span>
            </p>
        </div>
        <?php
    }

    /**
     * WP Consent API: the consent type, for plugins that ask.
     *
     * @param string $type Type.
     * @return string
     */
    public static function consent_type($type) {
        unset($type);
        // The browser sets window.wp_consent_type for each visitor's region;
        // code that asks on the server gets the strict answer.
        return 'optin';
    }

    /**
     * Cookie settings button or link (shortcode and block).
     *
     * @param array|string $atts Attributes: label, style (button or link).
     * @return string
     */
    public static function settings_button($atts = array()) {
        $atts  = shortcode_atts(array('label' => '', 'style' => 'button'), is_array($atts) ? $atts : array(), 'spcc_cookie_settings');
        $label = '' !== trim((string) $atts['label']) ? (string) $atts['label'] : __('Cookie settings', 'seoprocookieconsent');
        if (self::enabled() && !self::$menu_link) {
            self::$menu_link = true; // A visible button: no floating one needed.
        }
        if ('link' === $atts['style']) {
            return sprintf('<a href="#cookie-settings" class="spcc-settings-link" data-spcc-action="settings">%s</a>', esc_html($label));
        }
        return sprintf(
            '<button type="button" class="spcc-settings-button wp-element-button" data-spcc-action="settings">%s</button>',
            esc_html($label)
        );
    }

    /**
     * Register the Cookie settings block.
     */
    public static function register_block() {
        SEOProCookieConsent_Setup::register_block('cookie-settings', array(
            'title'       => __('Cookie settings button', 'seoprocookieconsent'),
            'description' => __('Opens Cookie settings so visitors can change their choices.', 'seoprocookieconsent'),
            'icon'        => 'admin-settings',
            'attributes'  => array(
                'label' => array(
                    'type'        => 'string',
                    'default'     => '',
                    'label'       => __('Text', 'seoprocookieconsent'),
                    'placeholder' => __('Cookie settings', 'seoprocookieconsent'),
                ),
                'style' => array(
                    'type'    => 'string',
                    'default' => 'button',
                    'label'   => __('Show as', 'seoprocookieconsent'),
                    'options' => array(
                        'button' => __('Button', 'seoprocookieconsent'),
                        'link'   => __('Link', 'seoprocookieconsent'),
                    ),
                ),
            ),
        ), array(__CLASS__, 'settings_button'));
    }

    /**
     * What the plugin found, at the top of the card's options.
     *
     * @param string $key Setting key.
     */
    public static function panel($key) {
        if (self::KEY !== $key) {
            return;
        }
        echo '<div class="spcc-panel-note">';
        echo '<p><strong>' . esc_html__('Get started', 'seoprocookieconsent') . '</strong></p><ol>';
        echo '<li>' . esc_html__('Switch on Cookie consent.', 'seoprocookieconsent') . '</li>';
        echo '<li>' . esc_html__('Choose Scan now, then check Found on your pages below.', 'seoprocookieconsent') . '</li>';
        echo '<li>' . esc_html__('Switch on Cookie policy on the Policies tab and add the Cookie policy block to your privacy or cookie policy page.', 'seoprocookieconsent') . '</li></ol>';
        if (self::enabled()) {
            echo '<p><button type="button" class="button" data-spcc-scan>' . esc_html__('Scan now', 'seoprocookieconsent') . '</button></p>';
            echo '<p class="description">' . esc_html__('Checks your home page, privacy page and up to three recent posts and three pages. This is a sample, not a full audit; pages are also checked as visitors browse. Scripts are not run.', 'seoprocookieconsent') . '</p>';
            echo '<p role="status" aria-live="polite" data-spcc-scan-status></p>';
        }
        echo '<div data-spcc-findings>';
        self::findings();
        echo '</div></div>';
        SEOProCookieConsent_Records::panel();
        if (self::enabled()) {
            self::preview_panel();
        }
    }

    /**
     * The findings, also returned after a scan without reloading controls.
     * consent-admin.js inserts that copy as HTML, so escape every value here.
     */
    private static function findings() {
        $seen       = SEOProCookieConsent_Services::seen();
        $categories = SEOProCookieConsent_Services::categories();
        $groups = SEOProCookieConsent_Services::seen_by_category();
        if (empty($groups)) {
            echo '<p>' . esc_html__('No services that set cookies found yet. Scan now after switching this on, or open a few pages of your site.', 'seoprocookieconsent') . '</p>';
        } else {
            echo '<p>' . esc_html__('Found on your pages and held back until allowed:', 'seoprocookieconsent') . '</p><ul>';
            foreach ($groups as $category => $services) {
                $names = wp_list_pluck($services, 'name');
                printf('<li><strong>%1$s</strong>: %2$s</li>', esc_html($categories[$category]['label']), esc_html(implode(', ', $names)));
            }
            echo '</ul>';
        }
        if (!empty($seen['hosts'])) {
            printf(
                '<p>%1$s <code>%2$s</code></p>',
                esc_html__('Other scripts from these sites load as usual. If one sets cookies, add it under Troubleshooting → Also hold back:', 'seoprocookieconsent'),
                esc_html(implode(', ', array_keys($seen['hosts'])))
            );
        }
    }

    /** Scan a small public sample. No caller-supplied URLs or visitor cookies. */
    public static function scan() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You cannot scan this site.', 'seoprocookieconsent')), 403);
        }
        check_ajax_referer('seoprocookieconsent_scan', 'nonce');
        if (!self::enabled()) {
            wp_send_json_error(array('message' => __('Switch on Cookie consent before scanning.', 'seoprocookieconsent')), 400);
        }
        $urls = array(home_url('/'), get_privacy_policy_url());
        foreach (array('post', 'page') as $type) {
            $posts = get_posts(array(
                'post_type'              => $type,
                'post_status'            => 'publish',
                'has_password'           => false,
                'posts_per_page'         => 3,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ));
            foreach ($posts as $id) {
                $urls[] = get_permalink($id);
            }
        }
        $home    = wp_parse_url(home_url('/'));
        $checked = 0;
        $failed  = 0;
        // Create the record before loopbacks can create it in another request.
        // Otherwise this request's cached missing option can defeat its first save.
        SEOProCookieConsent_Services::record(array(), array());
        foreach (array_unique(array_filter($urls)) as $url) {
            $parts = wp_parse_url($url);
            if (!is_array($parts) || !is_array($home)
                || ($parts['scheme'] ?? '') !== ($home['scheme'] ?? '')
                || ($parts['host'] ?? '') !== ($home['host'] ?? '')
                || ($parts['port'] ?? 0) !== ($home['port'] ?? 0)
                || isset($parts['user']) || isset($parts['pass'])) {
                ++$failed;
                continue;
            }
            // Trusted same-origin loopback, including private/local hosts.
            // Redirects are forbidden so a page cannot send us off-site.
            $response = wp_remote_get(add_query_arg('spcc_scan', wp_generate_uuid4(), $url), array(
                'timeout'             => 3,
                'redirection'         => 0,
                'limit_response_size' => 2 * MB_IN_BYTES,
                'cookies'             => array(),
                'headers'             => array('Cache-Control' => 'no-cache'),
            ));
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)
                || !is_string($content_type) || false === stripos($content_type, 'text/html')) {
                ++$failed;
                continue;
            }
            // Loopbacks also record services. Refresh only our option's entries
            // before merging, preserving every other option in shared caches.
            $alloptions = wp_load_alloptions();
            unset($alloptions[SEOProCookieConsent_Services::SEEN_OPTION]);
            wp_cache_set('alloptions', $alloptions, 'options');
            $notoptions = wp_cache_get('notoptions', 'options');
            if (is_array($notoptions)) {
                unset($notoptions[SEOProCookieConsent_Services::SEEN_OPTION]);
                wp_cache_set('notoptions', $notoptions, 'options');
            }
            wp_cache_delete(SEOProCookieConsent_Services::SEEN_OPTION, 'options');
            self::scan_page(wp_remote_retrieve_body($response));
            ++$checked;
        }
        ob_start();
        self::findings();
        $html = ob_get_clean();
        wp_send_json_success(array('checked' => $checked, 'failed' => $failed, 'html' => $html));
    }

    /**
     * Use the same blocker for cached raw HTML and already held-back markup.
     * Record here too: cached pages need not execute the front-end buffer.
     *
     * @param string $html Public page HTML.
     */
    private static function scan_page($html) {
        $blocker = new SEOProCookieConsent_Blocker(
            preg_split('/[\r\n]+/', (string) SEOProCookieConsent_Settings::get('consent_allow')) ?: array(),
            SEOProCookieConsent_Blocker::parse_block_lines((string) SEOProCookieConsent_Settings::get('consent_block')),
            SEOProCookieConsent_Settings::get('consent_google_advanced') ? self::GOOGLE_SERVICES : array()
        );
        $tags     = new WP_HTML_Tag_Processor($blocker->process($html));
        $services = $blocker->found();
        while ($tags->next_tag()) {
            if (!in_array($tags->get_tag(), array('SCRIPT', 'IFRAME'), true)) {
                continue;
            }
            $service = $tags->get_attribute('data-spcc-service');
            if (is_string($service)) {
                $services[] = $service;
            }
        }
        SEOProCookieConsent_Services::record($services, $blocker->hosts());
    }

    /**
     * The banner preview: the home page in a frame, with the banner shown
     * as the chosen visitor sees it. The frame loads when opened and
     * reloads after each change on the card (assets/js/consent-admin.js).
     */
    private static function preview_panel() {
        ?>
        <details class="spcc-preview" data-spcc-preview data-spcc-preview-src="<?php echo esc_url(home_url('/')); ?>">
            <summary class="spcc-preview__summary"><?php esc_html_e('Preview the banner', 'seoprocookieconsent'); ?></summary>
            <div class="spcc-preview__body">
                <div class="spcc-preview__controls">
                    <label>
                        <?php esc_html_e('Visitor', 'seoprocookieconsent'); ?>
                        <select data-spcc-preview-model>
                            <option value="opt-in"><?php esc_html_e('Asked first (Europe, Quebec)', 'seoprocookieconsent'); ?></option>
                            <option value="opt-out"><?php esc_html_e('Shown a notice (elsewhere)', 'seoprocookieconsent'); ?></option>
                        </select>
                    </label>
                    <label>
                        <?php esc_html_e('Screen', 'seoprocookieconsent'); ?>
                        <select data-spcc-preview-device>
                            <option value="desktop"><?php esc_html_e('Desktop', 'seoprocookieconsent'); ?></option>
                            <option value="phone"><?php esc_html_e('Phone', 'seoprocookieconsent'); ?></option>
                        </select>
                    </label>
                    <button type="button" class="button" data-spcc-preview-reload><?php esc_html_e('Reload', 'seoprocookieconsent'); ?></button>
                </div>
                <div class="spcc-preview__viewport" data-spcc-preview-viewport>
                    <iframe title="<?php esc_attr_e('Banner preview on your home page', 'seoprocookieconsent'); ?>" tabindex="-1"></iframe>
                </div>
                <p class="description"><?php esc_html_e('Your home page with the banner as visitors see it, even if the page has nothing to hold back. It updates after each change. Choices made in the preview are not saved.', 'seoprocookieconsent'); ?></p>
            </div>
        </details>
        <?php
    }

    /**
     * The settings screen's own script and styles: the banner preview and
     * the page picker for the Cookie policy page.
     */
    public static function admin_enqueue() {
        if (wp_script_is('seoprocookieconsent-consent-admin', 'enqueued')) {
            return;
        }
        wp_enqueue_style('seoprocookieconsent-consent-admin', SEOPROCOOKIECONSENT_URL . 'assets/css/consent-admin.css', array('seoprocookieconsent-admin'), SEOProCookieConsent_Setup::asset_version('assets/css/consent-admin.css'));
        wp_enqueue_script('seoprocookieconsent-consent-admin', SEOPROCOOKIECONSENT_URL . 'assets/js/consent-admin.js', array('jquery', 'jquery-ui-autocomplete', 'wp-api-fetch', 'seoprocookieconsent-admin'), SEOProCookieConsent_Setup::asset_version('assets/js/consent-admin.js'), true);
        wp_localize_script('seoprocookieconsent-consent-admin', 'spccConsentAdmin', array(
            'home'    => home_url('/'),
            'arg'     => self::PREVIEW_ARG,
            'noPages' => __('No pages found.', 'seoprocookieconsent'),
            'ajax'    => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('seoprocookieconsent_scan'),
            'scanning' => __('Scanning…', 'seoprocookieconsent'),
            /* translators: 1: pages checked; 2: pages that could not be checked. */
            'scanned' => __('Scan finished: %1$s pages checked; %2$s could not be checked. Review the findings below.', 'seoprocookieconsent'),
            'scanFailed' => __('The scan could not check your pages. Loopback requests may be blocked. Try again, or open pages of your site to fill the list as you browse.', 'seoprocookieconsent'),
        ));
    }
}
