<?php
/**
 * A general privacy policy from the owner's answers, as a block and
 * shortcode, with an optional section on the use of AI.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: 2026 Marcus Quinn
 * Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
 * Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
 *
 * @package SEOProCookieConsent
 * @since 1.0.16
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SEOProCookieConsent_Privacy extends SEOProCookieConsent_Feature {

    const KEY = 'privacy';

    /**
     * Settings rendered and saved by the shared settings screen.
     *
     * @return array<string,array>
     */
    public static function settings() {
        return array(
            self::KEY              => array(
                'type'        => 'bool',
                'default'     => false,
                'tab'         => 'policies',
                'label'       => __('Privacy policy', 'seoprocookieconsent'),
                'description' => __('Adds the Privacy policy block and [spcc_privacy_policy] shortcode: a general privacy policy from your answers, covering what you collect and why, AI, cookies, sharing, retention, security, rights and complaints. Review the wording before publishing and add anything your site does that it does not cover. This is a starting point, not legal advice, and may not cover your business or local law. Existing privacy pages are not changed.', 'seoprocookieconsent'),
                'reload'      => true,
            ),
            'privacy_business'     => array(
                'type'        => 'text',
                'default'     => '',
                'parent'      => self::KEY,
                'label'       => __('Business or owner name', 'seoprocookieconsent'),
                'description' => __('The legal name responsible for personal information collected through this site. Leave empty to use the Terms and conditions answer. Required for the suggested wording.', 'seoprocookieconsent'),
            ),
            'privacy_country'      => array(
                'type'        => 'text',
                'default'     => '',
                'parent'      => self::KEY,
                'label'       => __('Country', 'seoprocookieconsent'),
                'description' => __('Where the business is based. Leave empty to use the Terms and conditions answer.', 'seoprocookieconsent'),
            ),
            'privacy_registration' => array(
                'type'        => 'lines',
                'default'     => '',
                'parent'      => self::KEY,
                'label'       => __('Data protection registration', 'seoprocookieconsent'),
                'description' => __('Optional: your registration with a data protection authority, where the law asks for one, such as the authority\'s name and your registration number.', 'seoprocookieconsent'),
            ),
            'privacy_contact'      => array(
                'type'        => 'lines',
                'default'     => '',
                'parent'      => self::KEY,
                'label'       => __('Contact details', 'seoprocookieconsent'),
                'description' => __('Public email, contact page or postal address for privacy questions and requests. Leave empty to use the Terms and conditions answer. Required for the suggested wording; this is shown as plain text.', 'seoprocookieconsent'),
            ),
            'privacy_providers'    => array(
                'type'        => 'lines',
                'default'     => '',
                'parent'      => self::KEY,
                'label'       => __('Main service providers', 'seoprocookieconsent'),
                'description' => __('Optional: services that handle personal information for you, one per line with their country and what they do, such as "Web host, Germany: hosting and backups". Listed where the policy explains sharing.', 'seoprocookieconsent'),
            ),
            'privacy_ai'           => array(
                'type'        => 'bool',
                'default'     => true,
                'parent'      => self::KEY,
                'label'       => __('Section on the use of AI', 'seoprocookieconsent'),
                'description' => __('Says that you use AI services from several providers to develop the site and your services and to help answer questions and support requests, and that you endeavour to remove personal information before anything is processed by AI. Turn it off if that is not true for this site.', 'seoprocookieconsent'),
            ),
            'privacy_wording'      => array(
                'type'        => 'lines',
                'default'     => '',
                'parent'      => self::KEY,
                'label'       => __('Your own wording', 'seoprocookieconsent'),
                'description' => __('Optional: replaces all suggested wording. Plain text only, one paragraph per line. Leave empty to use the answers above. You can also write normal blocks around the Privacy policy block; those are never changed.', 'seoprocookieconsent'),
            ),
        );
    }

    /** Register hooks only when enabled. */
    public static function boot() {
        if (!self::enabled()) {
            return;
        }
        add_shortcode('spcc_privacy_policy', array(__CLASS__, 'render'));
        add_action('init', array(__CLASS__, 'register_block'), 20);
    }

    /** Register the dynamic block using the shared editor preview. */
    public static function register_block() {
        SEOProCookieConsent_Setup::register_block('privacy-policy', array(
            'title'       => __('Privacy policy', 'seoprocookieconsent'),
            'description' => __('Privacy policy from your answers. Review before publishing; not legal advice.', 'seoprocookieconsent'),
            'icon'        => 'shield',
        ), array(__CLASS__, 'render'));
    }

    /**
     * Render fresh from settings; no visitor-specific data or extra assets.
     *
     * @return string
     */
    public static function render() {
        if (!self::enabled()) {
            return '';
        }
        $wording = (string) SEOProCookieConsent_Settings::get('privacy_wording');
        if ('' !== trim($wording)) {
            return '<div class="spcc-privacy">' . self::paragraphs($wording) . self::added_sections(true) . '</div>';
        }
        $business = self::answer('privacy_business', 'terms_business');
        $contact  = self::answer('privacy_contact', 'terms_contact');
        if ('' === trim($business) || '' === trim($contact)) {
            return '';
        }

        $html  = '<div class="spcc-privacy">';
        $html .= self::paragraphs(sprintf(
            /* translators: %s: legal business or owner name. */
            __('This privacy policy explains how %s ("we") collects, uses and protects personal information through this website and our services, and the choices and rights you have. We are responsible for that information (its controller, in data protection law).', 'seoprocookieconsent'),
            $business
        ));
        $country = self::answer('privacy_country', 'terms_country');
        if ('' !== trim($country)) {
            $html .= self::paragraphs(sprintf(
                /* translators: %s: country where the business is based. */
                __('We are based in %s.', 'seoprocookieconsent'),
                $country
            ));
        }
        $html .= self::paragraphs((string) SEOProCookieConsent_Settings::get('privacy_registration'));

        $html .= self::heading(__('What we collect', 'seoprocookieconsent'));
        $html .= self::items(array(
            __('Information you give us: for example your name, email address, phone number, postal address and what you write, when you contact us, comment, create an account, subscribe to updates or buy from us.', 'seoprocookieconsent'),
            __('Information collected automatically: your IP address, browser and device details and the pages you visit, which our hosting and security services record to deliver and protect the site, and information from cookies and similar technologies, as explained under Cookies.', 'seoprocookieconsent'),
            __('Information from others: for example confirmation of a payment from a payment provider, or details from a service you choose to sign in with.', 'seoprocookieconsent'),
        ));
        $html .= self::paragraphs(__('We do not ask for sensitive information, such as about your health, religious beliefs or ethnic origin. Please do not send it to us unless we have agreed how it will be handled.', 'seoprocookieconsent'));

        $html .= self::heading(__('How we use it and why', 'seoprocookieconsent'));
        $html .= self::paragraphs(__('We use personal information only for these purposes. Where data protection law asks for a legal basis, we rely on the one given for each:', 'seoprocookieconsent'));
        $html .= self::items(array(
            __('To reply to you and provide what you ask for, including orders and support: to take steps at your request or to perform a contract with you.', 'seoprocookieconsent'),
            __('To run, secure and improve the website and our services, and to prevent fraud and abuse: our legitimate interests, balanced against your rights.', 'seoprocookieconsent'),
            __('To send newsletters or marketing you asked for: your consent, which you can withdraw at any time with the unsubscribe link or by contacting us.', 'seoprocookieconsent'),
            __('To use optional cookies, such as for statistics or marketing: your consent, where the law requires it.', 'seoprocookieconsent'),
            __('To keep records and meet our legal, tax and accounting obligations: legal obligation.', 'seoprocookieconsent'),
        ));
        $html .= self::paragraphs(__('We do not use personal information for an unrelated purpose without telling you first.', 'seoprocookieconsent'));

        if ((bool) SEOProCookieConsent_Settings::get('privacy_ai')) {
            $html .= self::heading(__('Artificial intelligence (AI)', 'seoprocookieconsent'));
            $html .= self::paragraphs(__('We use AI services from several providers to help develop and maintain this website and our services, and to help us answer questions and support requests.', 'seoprocookieconsent'));
            $html .= self::paragraphs(__('We endeavour to remove personal information, such as names, contact details and account or order details, from anything we send to an AI service, so that only the general content of a message is processed to help prepare an answer.', 'seoprocookieconsent'));
            $html .= self::paragraphs(__('Where we can, we choose AI services and settings that do not use what we send to train their models. AI helps us prepare answers; it does not make decisions about you that have legal or similarly significant effects.', 'seoprocookieconsent'));
        }

        $html .= self::heading(__('Cookies', 'seoprocookieconsent'));
        $html .= self::paragraphs(__('We use cookies and similar technologies. Necessary ones make the site work and are always on. Others, such as for statistics, embedded content or marketing, are used only with your consent where the law requires it.', 'seoprocookieconsent'));
        $html .= self::cookie_choices();

        $html .= self::heading(__('Who we share it with', 'seoprocookieconsent'));
        $html .= self::paragraphs(__('We share personal information only when needed:', 'seoprocookieconsent'));
        $html .= self::items(array(
            __('with service providers who handle it for us on our instructions, such as for hosting, backups, email, payments and support, and who may use it only to provide their services to us;', 'seoprocookieconsent'),
            __('with professional advisers, such as accountants and lawyers, and with authorities when the law requires it;', 'seoprocookieconsent'),
            __('with a buyer or successor if our business or this website is sold or merged, who may use it as this policy describes;', 'seoprocookieconsent'),
            __('with others when you ask us to or agree to it.', 'seoprocookieconsent'),
        ));
        $providers = (string) SEOProCookieConsent_Settings::get('privacy_providers');
        if ('' !== trim($providers)) {
            $html .= self::paragraphs(__('Our main service providers are:', 'seoprocookieconsent'));
            $html .= self::items(preg_split('/\r\n|\r|\n/', $providers) ?: array());
        }

        $html .= self::heading(__('International transfers', 'seoprocookieconsent'));
        $html .= self::paragraphs(__('Some of our service providers store or process information in other countries. Where data protection law requires it, we make sure suitable safeguards are in place, such as an adequacy decision or standard contractual clauses.', 'seoprocookieconsent'));

        $html .= self::heading(__('How long we keep it', 'seoprocookieconsent'));
        $html .= self::paragraphs(__('We keep personal information only as long as we need it for the purposes above, then delete or anonymise it. For example, we keep messages as long as needed to deal with them and any follow-up, account details while your account is open, and order and accounting records as long as tax and accounting law requires. Copies in backups are deleted as the backups are replaced.', 'seoprocookieconsent'));

        $html .= self::heading(__('Security', 'seoprocookieconsent'));
        $html .= self::paragraphs(__('We protect personal information with appropriate technical and organisational measures, such as encrypted connections, access controls, and regular updates and backups. If a data breach puts your rights at risk, we will tell you and the authorities where the law requires it. No website or email is completely secure, so please think carefully before sending sensitive information by email.', 'seoprocookieconsent'));

        $html .= self::added_sections(false);

        $html .= self::heading(__('Your rights', 'seoprocookieconsent'));
        $html .= self::paragraphs(__('Depending on where you live, you may have the right to:', 'seoprocookieconsent'));
        $html .= self::items(array(
            __('ask for a copy of the personal information we hold about you;', 'seoprocookieconsent'),
            __('have it corrected if it is wrong or incomplete;', 'seoprocookieconsent'),
            __('have it deleted, or its use restricted;', 'seoprocookieconsent'),
            __('object to how we use it, including to direct marketing at any time;', 'seoprocookieconsent'),
            __('receive it in a commonly used format, or have it sent to another organisation;', 'seoprocookieconsent'),
            __('withdraw your consent at any time, without affecting what we did before.', 'seoprocookieconsent'),
        ));
        $html .= self::paragraphs(__('To use these rights, contact us using the details below. We may need to confirm your identity first. We reply as soon as we can and within the time the law sets, and do not charge unless a request is clearly unfounded or excessive.', 'seoprocookieconsent'));

        $html .= self::heading(__('Complaints', 'seoprocookieconsent'));
        $html .= self::paragraphs(__('If you are unhappy with how we handle your personal information, please contact us first so we can try to put it right. You also have the right to complain to the data protection authority where you live or work, or where we are based.', 'seoprocookieconsent'));

        $html .= self::heading(__('Children', 'seoprocookieconsent'));
        $html .= self::paragraphs(__('This website is not intended for children. We do not knowingly collect personal information from children under the age at which they can consent to it where they live. If you think a child has given us personal information, please contact us and we will delete it.', 'seoprocookieconsent'));

        $html .= self::heading(__('Other websites', 'seoprocookieconsent'));
        $html .= self::paragraphs(__('Other websites that we link to, or whose content we embed, have their own privacy policies; this policy does not cover them.', 'seoprocookieconsent'));

        $html .= self::heading(__('Changes to this policy', 'seoprocookieconsent'));
        $html .= self::paragraphs(__('We may update this policy when our services or the law change. The current version is always on this page, and we will tell you about significant changes where we can.', 'seoprocookieconsent'));

        $html .= self::heading(__('Contact', 'seoprocookieconsent'));
        $html .= self::paragraphs($contact);
        return $html . '</div>';
    }

    /**
     * A privacy answer, or the matching Terms and conditions answer when it
     * is empty, so owners enter their name and contact details once.
     *
     * @param string $key      Privacy setting key.
     * @param string $fallback Terms setting key.
     * @return string
     */
    private static function answer($key, $fallback) {
        $value = (string) SEOProCookieConsent_Settings::get($key);
        return '' !== trim($value) ? $value : (string) SEOProCookieConsent_Settings::get($fallback);
    }

    /**
     * How visitors change their cookie choices, and where the cookie list
     * is: a Cookie settings link while Cookie consent is on, and a link to
     * the cookie policy page while Cookie policy is on, unless it is this
     * page.
     *
     * @return string
     */
    private static function cookie_choices() {
        $html = '';
        if (SEOProCookieConsent_Consent::enabled()) {
            $html .= '<p>' . sprintf(
                /* translators: %s: "Cookie settings" link. */
                esc_html__('You can change or withdraw your consent at any time in %s.', 'seoprocookieconsent'),
                SEOProCookieConsent_Consent::settings_button(array('style' => 'link'))
            ) . '</p>';
        }
        if (!SEOProCookieConsent_Policy::enabled()) {
            return $html;
        }
        $url = SEOProCookieConsent_Consent::policy_url();
        if ('' === $url) {
            return $html;
        }
        $page = (int) url_to_postid($url);
        if ($page && $page === (int) get_queried_object_id()) {
            return $html;
        }
        return $html . '<p>' . sprintf(
            /* translators: %s: "Our cookie policy" link. */
            esc_html__('%s lists the cookies we use, what each does and how long it is kept.', 'seoprocookieconsent'),
            '<a href="' . esc_url($url) . '">' . esc_html__('Our cookie policy', 'seoprocookieconsent') . '</a>'
        ) . '</p>';
    }

    /**
     * Sections other plugins add for what they do with personal
     * information, such as the Google data an analytics plugin reads, from
     * the seoprocookieconsent_privacy_sections filter. Each section is a
     * heading and plain text, one paragraph per line, escaped like the
     * owner's answers. They come before "Your rights", and also follow
     * Your own wording ($own_wording true), so an add-on that the owner's
     * wording already covers can leave its section out.
     *
     * @param bool $own_wording Whether Your own wording replaces the suggestions.
     * @return string
     */
    private static function added_sections($own_wording) {
        /**
         * Sections an add-on adds to the Privacy policy.
         *
         * @since 1.0.16
         *
         * @param array<int,array{heading:string,text:string}> $sections    Sections, in order.
         * @param bool                                         $own_wording Whether Your own wording replaces the suggestions.
         */
        $sections = apply_filters('seoprocookieconsent_privacy_sections', array(), (bool) $own_wording);
        $html     = '';
        foreach (is_array($sections) ? $sections : array() as $section) {
            if (!is_array($section)) {
                continue;
            }
            $heading = isset($section['heading']) && is_string($section['heading']) ? trim($section['heading']) : '';
            $text    = isset($section['text']) && is_string($section['text']) ? $section['text'] : '';
            if ('' !== $heading && '' !== trim($text)) {
                $html .= self::heading($heading) . self::paragraphs($text);
            }
        }
        return $html;
    }

    /**
     * A section heading.
     *
     * @param string $text Plain text.
     * @return string
     */
    private static function heading($text) {
        return '<h2>' . esc_html($text) . '</h2>';
    }

    /**
     * Escape each nonempty item as a list item.
     *
     * @param string[] $items Plain text.
     * @return string
     */
    private static function items(array $items) {
        $html = '';
        foreach ($items as $item) {
            if ('' !== trim((string) $item)) {
                $html .= '<li>' . esc_html(trim((string) $item)) . '</li>';
            }
        }
        return '' !== $html ? '<ul>' . $html . '</ul>' : '';
    }

    /**
     * Escape each nonempty line as a paragraph, never executing HTML or shortcodes.
     *
     * @param string $text Plain text.
     * @return string
     */
    private static function paragraphs($text) {
        $html = '';
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: array() as $line) {
            if ('' !== trim($line)) {
                $html .= '<p>' . esc_html($line) . '</p>';
            }
        }
        return $html;
    }
}
