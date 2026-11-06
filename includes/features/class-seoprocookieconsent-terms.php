<?php
/**
 * Plain-English terms from the owner's answers, as a block and shortcode.
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

final class SEOProCookieConsent_Terms extends SEOProCookieConsent_Document {

    const KEY = 'terms';

    const SHORTCODE = 'spcc_terms';

    const BLOCK = 'terms';

    const REPLACES = array('complianz-terms-conditions' => 'Complianz Terms & Conditions');

    /**
     * Settings rendered and saved by the shared settings screen.
     *
     * @return array<string,array>
     */
    public static function settings() {
        return self::with_questions(
            array(
                'type'        => 'bool',
                'default'     => false,
                'tab'         => 'policies',
                'label'       => __('Terms and conditions', 'seoprocookieconsent'),
                'description' => __('Adds the Terms and conditions block and [spcc_terms] shortcode. Answer the questions, then review the wording before publishing. This is a starting point, not legal advice, and may not cover your business or local law. Existing Complianz pages are not changed: replace their block or shortcode yourself after review.', 'seoprocookieconsent'),
                'replaces'    => self::REPLACES,
                'reload'      => true,
            ),
            array(
                'terms_business' => array('type' => 'text', 'label' => __('Business or owner name', 'seoprocookieconsent'), 'description' => __('The legal name responsible for this site. Required for the suggested wording.', 'seoprocookieconsent')),
                'terms_country'  => array('type' => 'text', 'label' => __('Country', 'seoprocookieconsent'), 'description' => __('Where the business is based. This does not choose governing law or generate country-specific clauses.', 'seoprocookieconsent')),
                'terms_sells'    => array('type' => 'lines', 'label' => __('What the site offers or sells', 'seoprocookieconsent'), 'description' => __('Describe your goods, services, digital content or information, including payment and delivery terms where needed.', 'seoprocookieconsent')),
                'terms_returns'  => array('type' => 'lines', 'label' => __('Returns, refunds and cancellations', 'seoprocookieconsent'), 'description' => __('Your policy and how customers use it. Include any withdrawal rights and required contact or online withdrawal process. Leave empty if not applicable; no return period is assumed.', 'seoprocookieconsent')),
                'terms_ai'       => array('type' => 'bool', 'default' => true, 'label' => __('Section on AI-assisted services', 'seoprocookieconsent'), 'description' => __('Says that the site and your services are provided with the help of AI from several providers, and that visitors accept this and should check what you provide for correctness, themselves or with their own AI, before relying on it. Turn it off if you do not use AI.', 'seoprocookieconsent')),
                'terms_contact'  => array('type' => 'lines', 'label' => __('Contact details', 'seoprocookieconsent'), 'description' => __('Public email, contact page, postal address or phone number for questions about these terms. Required for the suggested wording; this is shown as plain text.', 'seoprocookieconsent')),
                'terms_wording'  => array('type' => 'lines', 'label' => __('Your own wording', 'seoprocookieconsent'), 'description' => __('Optional: replaces all suggested wording. Plain text only, one paragraph per line. Leave empty to use the answers above. You can also write normal blocks around the Terms and conditions block; those are never changed.', 'seoprocookieconsent')),
            )
        );
    }

    /**
     * Import only matching identity/contact answers, without changing the source.
     * Legal choices and custom documents need the owner's review.
     *
     * @param array $options      Stored settings, without defaults.
     * @param int   $from_version Previous settings version.
     * @return array
     */
    public static function migrate(array $options, $from_version) {
        if ($from_version >= 3) {
            return $options;
        }
        $source = get_option('complianz_tc_options_terms-conditions', array());
        if (!is_array($source)) {
            return $options;
        }
        $map = array(
            'organisation_name' => 'terms_business',
            'country_company'   => 'terms_country',
        );
        $contact = isset($source['contact_company']) ? $source['contact_company'] : '';
        if ('manually' === $contact) {
            $map['email_company'] = 'terms_contact';
        } elseif ('webpage' === $contact) {
            $map['page_company'] = 'terms_contact';
        }
        foreach ($map as $old => $new) {
            if (isset($source[$old]) && is_string($source[$old]) && '' !== trim($source[$old])) {
                $options = self::import_setting($options, $new, $source[$old]);
            }
        }
        return $options;
    }

    /**
     * Block title, description and icon.
     *
     * @return array<string,string>
     */
    protected static function block() {
        return array(
            'title'       => __('Terms and conditions', 'seoprocookieconsent'),
            'description' => __('Terms from your answers. Review before publishing; not legal advice.', 'seoprocookieconsent'),
            'icon'        => 'media-text',
        );
    }

    /**
     * The suggested terms, or '' until a business name and contact details
     * are given.
     *
     * @return string
     */
    protected static function suggested() {
        $business = (string) SEOProCookieConsent_Settings::get('terms_business');
        $contact  = (string) SEOProCookieConsent_Settings::get('terms_contact');
        if ('' === trim($business) || '' === trim($contact)) {
            return '';
        }

        $html = self::paragraphs(sprintf(
            /* translators: %s: legal business or owner name. */
            __('This website is operated by %s. These terms explain the use of this website and the offerings described below. Please read them before using the site or placing an order.', 'seoprocookieconsent'),
            $business
        ));
        $country = (string) SEOProCookieConsent_Settings::get('terms_country');
        if ('' !== trim($country)) {
            $html .= self::paragraphs(sprintf(
                /* translators: %s: country where the business is based. */
                __('The business is based in %s.', 'seoprocookieconsent'),
                $country
            ));
        }
        $html .= '<h2>' . esc_html__('Using this website', 'seoprocookieconsent') . '</h2>';
        $html .= self::paragraphs(__('Please use this website lawfully and do not interfere with its security or other people’s use of it. Respect the rights of others when using or sharing content.', 'seoprocookieconsent'));
        $sections = array(
            'terms_sells'   => __('What we offer', 'seoprocookieconsent'),
            'terms_returns' => __('Returns, refunds and cancellations', 'seoprocookieconsent'),
        );
        foreach ($sections as $key => $heading) {
            $text = (string) SEOProCookieConsent_Settings::get($key);
            if ('' !== trim($text)) {
                $html .= '<h2>' . esc_html($heading) . '</h2>' . self::paragraphs($text);
            }
        }
        $html .= self::added_sections(false);
        if ((bool) SEOProCookieConsent_Settings::get('terms_ai')) {
            $html .= '<h2>' . esc_html__('AI-assisted services', 'seoprocookieconsent') . '</h2>';
            $html .= self::paragraphs(__('We use artificial intelligence (AI) services from several providers to help develop this website and our services, and to help answer questions and support requests. By using this website and our services, you accept that they are provided with AI support.', 'seoprocookieconsent'));
            $html .= self::paragraphs(__('AI can make mistakes. Please check information, answers and other content we provide for correctness, yourself or with your own AI tools, before you rely on it, especially for important decisions.', 'seoprocookieconsent'));
        }
        $html .= '<h2>' . esc_html__('Your rights', 'seoprocookieconsent') . '</h2>';
        $html .= self::paragraphs(__('Nothing in these terms takes away rights that applicable law gives you and that cannot be excluded, including mandatory consumer rights.', 'seoprocookieconsent'));
        $html .= '<h2>' . esc_html__('Contact', 'seoprocookieconsent') . '</h2>';
        return $html . self::paragraphs($contact);
    }

    /**
     * Sections other plugins add for what they sell, such as a deposit and
     * balance on delivery, from the seoprocookieconsent_terms_sections
     * filter. They follow the owner's offering and returns answers, before
     * "AI-assisted services" (when that section is on) and "Your rights",
     * and also follow Your own wording ($own_wording true), so an add-on
     * that the owner's wording already covers can leave its section out.
     *
     * @param bool $own_wording Whether Your own wording replaces the suggestions.
     * @return string
     */
    protected static function added_sections($own_wording) {
        /**
         * Sections an add-on adds to the Terms and conditions.
         *
         * @since 1.0.5
         *
         * @param array<int,array{heading:string,text:string}> $sections    Sections, in order.
         * @param bool                                         $own_wording Whether Your own wording replaces the suggestions.
         */
        return self::sections(apply_filters('seoprocookieconsent_terms_sections', array(), (bool) $own_wording));
    }
}
