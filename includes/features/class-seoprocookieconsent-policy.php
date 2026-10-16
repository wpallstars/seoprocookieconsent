<?php
/**
 * Cookie policy: a block and shortcode listing the cookies the site uses,
 * kept up to date from what Cookie consent finds, inside a page the owner
 * writes; and suggested text for the privacy policy guide.
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

final class SEOProCookieConsent_Policy extends SEOProCookieConsent_Feature {

    const KEY = 'policy';

    /** Heading levels for each kind of cookie (block and shortcode). */
    const HEADINGS = array(
        'h2' => 'H2',
        'h3' => 'H3',
        'h4' => 'H4',
    );

    /**
     * Settings.
     *
     * @return array<string,array>
     */
    public static function settings() {
        return array(
            self::KEY => array(
                'type'        => 'bool',
                'default'     => false,
                'tab'         => 'policies',
                'label'       => __('Cookie policy', 'seoprocookieconsent'),
                'description' => __('Adds a Cookie policy block (and the [spcc_cookie_policy] shortcode) that lists the cookies your site uses, grouped and kept up to date from what Cookie consent finds. Put it in your privacy or cookie policy page and write the rest of the page as you like. It is a starting point, not legal advice.', 'seoprocookieconsent'),
            ),
        );
    }

    /**
     * Hooks.
     */
    public static function boot() {
        if (!self::enabled()) {
            return;
        }
        add_shortcode('spcc_cookie_policy', array(__CLASS__, 'render'));
        add_action('init', array(__CLASS__, 'register_block'), 20);
        add_action('admin_init', array(__CLASS__, 'privacy_guide'));
    }

    /**
     * Register the Cookie policy block.
     */
    public static function register_block() {
        SEOProCookieConsent_Setup::register_block('cookie-policy', array(
            'title'       => __('Cookie policy', 'seoprocookieconsent'),
            'description' => __('Lists the cookies this site uses, kept up to date.', 'seoprocookieconsent'),
            'icon'        => 'privacy',
            'attributes'  => array(
                'intro'   => array(
                    'type'    => 'boolean',
                    'default' => true,
                    'label'   => __('Short introduction', 'seoprocookieconsent'),
                ),
                'button'  => array(
                    'type'    => 'boolean',
                    'default' => true,
                    'label'   => __('Cookie settings button', 'seoprocookieconsent'),
                ),
                'heading' => array(
                    'type'    => 'string',
                    'default' => 'h3',
                    'label'   => __('Heading level for each kind of cookie', 'seoprocookieconsent'),
                    'options' => self::HEADINGS,
                ),
                'updated' => array(
                    'type'    => 'boolean',
                    'default' => true,
                    'label'   => __('Date the list was last updated', 'seoprocookieconsent'),
                ),
            ),
        ), array(__CLASS__, 'render'));
    }

    /**
     * Rows for necessary cookies: the consent cookie and WordPress's own.
     *
     * @return array<string,array>
     */
    private static function necessary() {
        $days = (int) SEOProCookieConsent_Settings::get('consent_days');
        return array(
            'seoprocookieconsent' => array(
                'name'    => get_bloginfo('name'),
                'privacy' => '',
                'cookies' => array(
                    SEOProCookieConsent_Consent::COOKIE => array(
                        __('Remembers your cookie choices', 'seoprocookieconsent'),
                        /* translators: %d: number of days. */
                        sprintf(_n('%d day', '%d days', $days, 'seoprocookieconsent'), $days),
                    ),
                    'wordpress_logged_in_*' => array(
                        __('Keeps you logged in, only if you log in', 'seoprocookieconsent'),
                        __('Session, or 14 days with Remember me', 'seoprocookieconsent'),
                    ),
                    'comment_author_*' => array(
                        __('Fills in your name when you comment, only if you ask it to', 'seoprocookieconsent'),
                        __('1 year', 'seoprocookieconsent'),
                    ),
                ),
            ),
        );
    }

    /**
     * The cookie policy list.
     *
     * @param array|string $atts Block or shortcode attributes: intro, button
     *                           and updated (on unless "no"), heading (h2, h3 or h4).
     * @return string
     */
    public static function render($atts = array()) {
        wp_enqueue_style('seoprocookieconsent', SEOPROCOOKIECONSENT_URL . 'assets/css/spcc.css', array(), SEOProCookieConsent_Setup::asset_version('assets/css/spcc.css'));

        $atts    = shortcode_atts(array(
            'intro'   => true,
            'button'  => true,
            'heading' => 'h3',
            'updated' => true,
        ), is_array($atts) ? $atts : array(), 'spcc_cookie_policy');
        $heading = strtolower((string) $atts['heading']);
        $heading = array_key_exists($heading, self::HEADINGS) ? $heading : 'h3';

        $categories = SEOProCookieConsent_Services::categories();
        $groups     = array('necessary' => self::necessary()) + SEOProCookieConsent_Services::seen_by_category();
        $seen       = SEOProCookieConsent_Services::seen();

        $html = '<div class="spcc-policy">';
        if (SEOProCookieConsent_Setup::flag($atts['intro'])) {
            $html .= '<p>' . esc_html__('This site uses cookies: small files stored on your device. Necessary cookies are always on. Others are used only if you allow them where the law asks for consent, and you can change your choice at any time.', 'seoprocookieconsent') . '</p>';
        }
        if (SEOProCookieConsent_Setup::flag($atts['button']) && SEOProCookieConsent_Consent::enabled()) {
            $html .= '<p>' . SEOProCookieConsent_Consent::settings_button() . '</p>';
        }

        foreach ($groups as $category => $services) {
            $html .= sprintf('<%1$s>%2$s</%1$s>', $heading, esc_html($categories[$category]['label']));
            $html .= '<p>' . esc_html($categories[$category]['description']) . '</p>';
            $html .= self::table($services);
        }
        if ($seen['updated'] > 0 && SEOProCookieConsent_Setup::flag($atts['updated'])) {
            $html .= '<p class="spcc-policy__updated">' . esc_html(sprintf(
                /* translators: %s: date. */
                __('List last updated %s.', 'seoprocookieconsent'),
                wp_date(get_option('date_format'), $seen['updated'])
            )) . '</p>';
        }
        return $html . '</div>';
    }

    /**
     * A category's table.
     *
     * @param array<string,array> $services Services.
     * @return string
     */
    private static function table(array $services) {
        $html  = '<div class="spcc-policy__scroll"><table class="spcc-policy__table"><thead><tr>';
        $html .= '<th scope="col">' . esc_html__('Service', 'seoprocookieconsent') . '</th>';
        $html .= '<th scope="col">' . esc_html__('Cookie', 'seoprocookieconsent') . '</th>';
        $html .= '<th scope="col">' . esc_html__('What it does', 'seoprocookieconsent') . '</th>';
        $html .= '<th scope="col">' . esc_html__('Kept for', 'seoprocookieconsent') . '</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($services as $service) {
            $name = esc_html($service['name']);
            if (!empty($service['privacy'])) {
                $name .= '<br /><a href="' . esc_url($service['privacy']) . '" rel="noopener nofollow">' . esc_html__('Privacy policy', 'seoprocookieconsent') . '</a>';
            }
            $cookies = (array) $service['cookies'];
            if (empty($cookies)) {
                $html .= '<tr><td>' . $name . '</td><td colspan="3">' . esc_html__('Sets cookies on its own site when its content loads.', 'seoprocookieconsent') . '</td></tr>';
                continue;
            }
            $first = true;
            foreach ($cookies as $cookie => $about) {
                $about = array_values((array) $about);
                $html .= '<tr><td>' . ($first ? $name : '') . '</td>';
                $html .= '<td><code>' . esc_html((string) $cookie) . '</code></td>';
                $html .= '<td>' . esc_html(isset($about[0]) ? (string) $about[0] : '') . '</td>';
                $html .= '<td>' . esc_html(isset($about[1]) ? (string) $about[1] : '') . '</td></tr>';
                $first = false;
            }
        }
        return $html . '</tbody></table></div>';
    }

    /**
     * Suggested text in Settings → Privacy → Policy guide.
     */
    public static function privacy_guide() {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }
        $text  = '<h2>' . esc_html__('Cookies', 'seoprocookieconsent') . '</h2>';
        $text .= '<p class="privacy-policy-tutorial">' . esc_html__('Add the Cookie policy block below this heading: it lists the cookies your site uses and stays up to date.', 'seoprocookieconsent') . '</p>';
        $text .= '<p>' . esc_html__('We use cookies and similar technologies. Necessary cookies make the site work and remember your choices. With your consent where the law requires it, we also use cookies for preferences, statistics, embedded content and marketing. You can change or withdraw your consent at any time from the Cookie settings link at the bottom of every page.', 'seoprocookieconsent') . '</p>';
        wp_add_privacy_policy_content('SEO Pro Cookie Consent', wp_kses_post($text));
    }
}
