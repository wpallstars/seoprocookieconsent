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

final class SEOProCookieConsent_Privacy extends SEOProCookieConsent_Document {

    const KEY = 'privacy';

    const SHORTCODE = 'spcc_privacy_policy';

    const BLOCK = 'privacy-policy';

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
                'label'       => __('Privacy policy', 'seoprocookieconsent'),
                'description' => __('Adds the Privacy policy block and [spcc_privacy_policy] shortcode: a general privacy policy from your answers, covering who is responsible, what you collect and why, AI, cookies, sharing, transfers, retention, security, rights, complaints, children and changes. Review the wording before publishing and add anything your site does that it does not cover. This is a starting point, not legal advice, and may not cover your business or local law. Existing privacy pages are not changed.', 'seoprocookieconsent'),
                'reload'      => true,
            ),
            array(
                'privacy_business'     => array('type' => 'text', 'label' => __('Business or owner name', 'seoprocookieconsent'), 'description' => __('The legal name responsible for personal information collected through this site. Leave empty to use the Terms and conditions answer. Required for the suggested wording.', 'seoprocookieconsent')),
                'privacy_country'      => array('type' => 'text', 'label' => __('Country', 'seoprocookieconsent'), 'description' => __('Where the business is based, named in the introduction and under Complaints. Leave empty to use the Terms and conditions answer.', 'seoprocookieconsent')),
                'privacy_registration' => array('type' => 'lines', 'label' => __('Data protection registration', 'seoprocookieconsent'), 'description' => __('Optional: your registration with a data protection authority, where the law asks for one, such as the authority\'s name and your registration number.', 'seoprocookieconsent')),
                'privacy_contact'      => array('type' => 'lines', 'label' => __('Contact details', 'seoprocookieconsent'), 'description' => __('Public email, contact page or postal address for privacy questions and requests. Leave empty to use the Terms and conditions answer. Required for the suggested wording; this is shown as plain text.', 'seoprocookieconsent')),
                'privacy_providers'    => array('type' => 'lines', 'label' => __('Main service providers', 'seoprocookieconsent'), 'description' => __('Optional: services that handle personal information for you, one per line with their country and what they do, such as "Web host, Germany: hosting and backups". Listed where the policy explains sharing.', 'seoprocookieconsent')),
                'privacy_ai'           => array('type' => 'bool', 'default' => true, 'label' => __('Section on the use of AI', 'seoprocookieconsent'), 'description' => __('Says that you use AI services from several providers to develop the site and your services and to help answer questions and support requests, and that you endeavour to remove personal information before anything is processed by AI. Turn it off if that is not true for this site.', 'seoprocookieconsent')),
                'privacy_wording'      => array('type' => 'lines', 'label' => __('Your own wording', 'seoprocookieconsent'), 'description' => __('Optional: replaces all suggested wording. Plain text only, one paragraph per line. Leave empty to use the answers above. You can also write normal blocks around the Privacy policy block; those are never changed.', 'seoprocookieconsent')),
            )
        );
    }

    /**
     * Block title, description and icon.
     *
     * @return array<string,string>
     */
    protected static function block() {
        return array(
            'title'       => __('Privacy policy', 'seoprocookieconsent'),
            'description' => __('Privacy policy from your answers. Review before publishing; not legal advice.', 'seoprocookieconsent'),
            'icon'        => 'shield',
        );
    }

    /**
     * The suggested policy, or '' until a business name and contact
     * details are given (here or in Terms and conditions).
     *
     * @return string
     */
    protected static function suggested() {
        $business = self::answer('privacy_business', 'terms_business');
        $contact  = self::answer('privacy_contact', 'terms_contact');
        if ('' === trim($business) || '' === trim($contact)) {
            return '';
        }
        return self::introduction($business)
            . self::collection()
            . self::purposes()
            . self::ai_use()
            . self::cookies()
            . self::sharing()
            . self::keeping()
            . self::added_sections(false)
            . self::rights()
            . self::closing()
            . self::part(__('Contact', 'seoprocookieconsent'), array(
                __('If you have any questions about this policy or how we handle your personal information, or want to use your rights, please contact us:', 'seoprocookieconsent'),
                $contact,
            ));
    }

    /**
     * Who is responsible, where the business is based, and what this
     * policy covers.
     *
     * @param string $business Business or owner name.
     * @return string
     */
    private static function introduction($business) {
        $html = self::paragraphs(sprintf(
            /* translators: %s: legal business or owner name. */
            __('This privacy policy explains how %s ("we") collects, uses and protects personal information through this website and our services, and the choices and rights you have. We are responsible for that information (its controller, in data protection law).', 'seoprocookieconsent'),
            $business
        ));
        $country = self::country();
        if ('' !== $country) {
            $html .= self::paragraphs(sprintf(
                /* translators: %s: country where the business is based. */
                __('We are based in %s.', 'seoprocookieconsent'),
                $country
            ));
        }
        $html .= self::paragraphs((string) SEOProCookieConsent_Settings::get('privacy_registration'));
        $html .= self::paragraphs(__('This policy applies wherever you visit from. Please read it with any more specific notice we give you when we collect information, such as on a form; those notices add to this policy.', 'seoprocookieconsent'));
        return $html . self::paragraphs(__('If we handle personal information for a customer as part of a service we provide to them, we do so on their instructions, and their privacy notice explains how it is used.', 'seoprocookieconsent'));
    }

    /**
     * What we collect and how.
     *
     * @return string
     */
    private static function collection() {
        return self::part(__('What we collect', 'seoprocookieconsent'), array(
            __('Personal information means any information about a person who can be identified from it. Depending on how you use this website and our services, we may collect:', 'seoprocookieconsent'),
            array(
                __('identity details, such as your name or username;', 'seoprocookieconsent'),
                __('contact details, such as your email address, phone number and postal or delivery address;', 'seoprocookieconsent'),
                __('order and payment details, such as what you buy from us and confirmation of payment; when a payment provider handles a card payment, we do not receive your full card number;', 'seoprocookieconsent'),
                __('account and profile details, such as your login, preferences, interests, feedback and survey answers;', 'seoprocookieconsent'),
                __('what you write to us or post, such as messages, comments, reviews and attachments;', 'seoprocookieconsent'),
                __('technical details, such as your IP address, browser and device type, operating system and an approximate location based on your IP address;', 'seoprocookieconsent'),
                __('usage details, such as the pages you visit, how you reached them and how you use the site;', 'seoprocookieconsent'),
                __('your marketing preferences and cookie choices.', 'seoprocookieconsent'),
            ),
            __('We collect it in these ways:', 'seoprocookieconsent'),
            array(
                __('Information you give us: when you contact us, comment, create an account, subscribe to updates, take part in a survey or buy from us.', 'seoprocookieconsent'),
                __('Information collected automatically: your IP address, browser and device details and the pages you visit, which our hosting and security services record to deliver and protect the site, and information from cookies and similar technologies, as explained under Cookies.', 'seoprocookieconsent'),
                __('Information from others: for example confirmation of a payment from a payment provider, or details from a service you choose to sign in with.', 'seoprocookieconsent'),
            ),
            __('We may also use aggregated or anonymised information, such as how many people visit a page, which does not identify you. If we combine it with other information so that it can identify you, we treat it as personal information.', 'seoprocookieconsent'),
            __('We do not ask for sensitive information, such as about your health, religious beliefs or ethnic origin. Please do not send it to us unless we have agreed how it will be handled.', 'seoprocookieconsent'),
            __('Where we need information to provide what you ask for, or because the law requires it, we say so when we ask. Without it, we may not be able to provide what you asked for, such as an order or a reply.', 'seoprocookieconsent'),
            __('If you post a comment, review or other content on this website, other people can see it. We will consider reasonable requests to remove it, but copies may already be held by search engines or archives that we do not control.', 'seoprocookieconsent'),
        ));
    }

    /**
     * How we use it, the legal basis for each purpose, and marketing.
     *
     * @return string
     */
    private static function purposes() {
        return self::part(__('How we use it and why', 'seoprocookieconsent'), array(
            __('We use personal information only for these purposes. Where data protection law asks for a legal basis, we rely on the one given for each:', 'seoprocookieconsent'),
            array(
                __('To reply to you and provide what you ask for, including orders and support: to take steps at your request or to perform a contract with you.', 'seoprocookieconsent'),
                __('To tell you about changes to our terms, this policy or a service you use: to perform a contract with you, or legal obligation.', 'seoprocookieconsent'),
                __('To run, secure and improve the website and our services, and to prevent fraud and abuse: our legitimate interests, balanced against your rights.', 'seoprocookieconsent'),
                __('To send newsletters or marketing you asked for: your consent, which you can withdraw at any time with the unsubscribe link or by contacting us.', 'seoprocookieconsent'),
                __('To use optional cookies, such as for statistics or marketing: your consent, where the law requires it.', 'seoprocookieconsent'),
                __('To keep records and meet our legal, tax and accounting obligations: legal obligation.', 'seoprocookieconsent'),
                __('To establish, exercise or defend legal claims: our legitimate interests.', 'seoprocookieconsent'),
            ),
            __('More than one legal basis may apply, and you can ask us which one we rely on for a particular use. When we rely on legitimate interests, we weigh them against the effect on you and your rights, and do not rely on them where your interests override ours. You can ask us how we made that assessment.', 'seoprocookieconsent'),
            __('We send marketing only if you ask for it or, where the law allows, about similar products and services after you buy from us. Every message explains how to stop. Stopping marketing does not stop messages we need to send about an order, account or service.', 'seoprocookieconsent'),
            __('We do not sell your personal information or share it with others for their own marketing.', 'seoprocookieconsent'),
            __('We do not use personal information for an unrelated purpose without telling you first.', 'seoprocookieconsent'),
        ));
    }

    /**
     * The section on the use of AI, or '' while it is switched off.
     *
     * @return string
     */
    private static function ai_use() {
        if (!(bool) SEOProCookieConsent_Settings::get('privacy_ai')) {
            return '';
        }
        return self::heading(__('Artificial intelligence (AI)', 'seoprocookieconsent'))
            . self::paragraphs(__('We use AI services from several providers to help develop and maintain this website and our services, and to help us answer questions and support requests.', 'seoprocookieconsent'))
            . self::paragraphs(__('We endeavour to remove personal information, such as names, contact details and account or order details, from anything we send to an AI service, so that only the general content of a message is processed to help prepare an answer.', 'seoprocookieconsent'))
            . self::paragraphs(__('Where we can, we choose AI services and settings that do not use what we send to train their models. AI helps us prepare answers; it does not make decisions about you that have legal or similarly significant effects.', 'seoprocookieconsent'));
    }

    /**
     * What cookies are, the kinds we may use, and how to choose.
     *
     * @return string
     */
    private static function cookies() {
        $kinds = array();
        foreach (SEOProCookieConsent_Services::categories() as $category) {
            $kinds[] = $category['label'] . ': ' . $category['description'];
        }
        return self::part(__('Cookies', 'seoprocookieconsent'), array(
            __('Cookies are small text files that a website stores in your browser. Similar technologies, such as local storage and tracking pixels, work in a similar way, so we call them all cookies here.', 'seoprocookieconsent'),
            __('We use cookies and similar technologies. Necessary ones make the site work and are always on. Others, such as for statistics, embedded content or marketing, are used only with your consent where the law requires it. The kinds we may use are:', 'seoprocookieconsent'),
            $kinds,
            __('Embedded content, such as videos, maps and social media posts, and sharing buttons from other websites can set their own cookies and work as if you visited those websites.', 'seoprocookieconsent'),
            __('You can also set your browser to refuse or delete cookies. Some parts of this website may not work without them.', 'seoprocookieconsent'),
        )) . self::cookie_choices();
    }

    /**
     * Who we share information with, and international transfers.
     *
     * @return string
     */
    private static function sharing() {
        $html = self::part(__('Who we share it with', 'seoprocookieconsent'), array(
            __('We share personal information only when needed:', 'seoprocookieconsent'),
            array(
                __('with service providers who handle it for us on our instructions, such as for hosting, backups, IT support, email, payments and customer support. They may use it only to provide their services to us, must keep it secure, and may use other providers only on the same terms;', 'seoprocookieconsent'),
                __('with professional advisers, such as accountants, auditors, lawyers, banks and insurers;', 'seoprocookieconsent'),
                __('with tax authorities, regulators, courts, the police and others when the law requires it, or to protect the rights, property or safety of our customers, others or us;', 'seoprocookieconsent'),
                __('with a buyer or successor if our business or this website is sold or merged, who may use it as this policy describes;', 'seoprocookieconsent'),
                __('with others when you ask us to or agree to it, such as a business you ask us to put you in touch with, which then uses it under its own privacy notice.', 'seoprocookieconsent'),
            ),
            __('If you pay on a payment provider\'s page, the provider handles your payment details under its own terms and privacy notice.', 'seoprocookieconsent'),
        ));
        $providers = (string) SEOProCookieConsent_Settings::get('privacy_providers');
        if ('' !== trim($providers)) {
            $html .= self::paragraphs(__('Our main service providers are:', 'seoprocookieconsent'));
            $html .= self::items(preg_split('/\r\n|\r|\n/', $providers) ?: array());
        }
        return $html . self::part(__('International transfers', 'seoprocookieconsent'), array(
            __('Some of our service providers store or process information in other countries, depending on their settings, where their systems are and where their support teams work. Where practical, we choose providers and settings that keep information in our own region.', 'seoprocookieconsent'),
            __('Where data protection law requires it, we make sure suitable safeguards are in place, such as an adequacy decision, standard contractual clauses or an equivalent agreement, together with measures such as encryption and access controls. You can ask us for more information about them.', 'seoprocookieconsent'),
        ));
    }

    /**
     * How long we keep information, and how we protect it.
     *
     * @return string
     */
    private static function keeping() {
        return self::part(__('How long we keep it', 'seoprocookieconsent'), array(
            __('We keep personal information only as long as we need it for the purposes above, including to meet legal, accounting or reporting requirements, then delete or anonymise it.', 'seoprocookieconsent'),
            __('To decide how long, we consider the amount, nature and sensitivity of the information, the risk of harm from unauthorised use, why we hold it and whether we can achieve that another way, and what the law requires.', 'seoprocookieconsent'),
            __('For example, we keep messages as long as needed to deal with them and any follow-up, account details while your account is open, and order and accounting records as long as tax and accounting law requires, which can be several years. If you ask us to delete information, we keep a short record of the request to show we dealt with it. Copies in backups are deleted as the backups are replaced.', 'seoprocookieconsent'),
        )) . self::part(__('Security', 'seoprocookieconsent'), array(
            __('We protect personal information with appropriate technical and organisational measures. Depending on the service, these may include encrypted connections, secure logins with strong passwords and multi-factor authentication, access only for people who need it and who must keep it confidential, regular updates, firewalls and monitoring, and backups. We expect our service providers to protect it in the same way.', 'seoprocookieconsent'),
            __('If a data breach puts your rights at risk, we will tell you and the authorities where the law requires it.', 'seoprocookieconsent'),
            __('No website or email is completely secure, so please think carefully before sending sensitive information by email, or ask us for a more secure way to send it.', 'seoprocookieconsent'),
        ));
    }

    /**
     * Your rights and how to use them.
     *
     * @return string
     */
    private static function rights() {
        return self::part(__('Your rights', 'seoprocookieconsent'), array(
            __('Depending on where you live, you may have the right to:', 'seoprocookieconsent'),
            array(
                __('be told how we use your personal information, as this policy does;', 'seoprocookieconsent'),
                __('ask for a copy of the personal information we hold about you;', 'seoprocookieconsent'),
                __('have it corrected if it is wrong or incomplete;', 'seoprocookieconsent'),
                __('have it deleted, or its use restricted;', 'seoprocookieconsent'),
                __('object to how we use it, including to direct marketing at any time and to uses based on our legitimate interests;', 'seoprocookieconsent'),
                __('receive information you gave us in a commonly used, machine-readable format, or have it sent to another organisation, where we use it with your consent or for a contract;', 'seoprocookieconsent'),
                __('not be subject to decisions based solely on automated processing that have legal or similarly significant effects on you;', 'seoprocookieconsent'),
                __('withdraw your consent at any time, without affecting what we did before.', 'seoprocookieconsent'),
            ),
            __('Some rights apply only in certain circumstances or may be limited by law, for example where we must keep records. If so, we will tell you.', 'seoprocookieconsent'),
            __('To use these rights, contact us using the details below and tell us what you would like. We may need to confirm your identity first, so that we do not give out, change or delete someone else\'s information, and may ask for details that help us find what you need. If someone asks on your behalf, we need your written permission.', 'seoprocookieconsent'),
            __('We reply without undue delay and within one month. If a request is complex or you send several, we may take longer where the law allows, and will tell you why. We do not charge, unless a request is clearly unfounded, repetitive or excessive, when we may charge a reasonable fee or decline it.', 'seoprocookieconsent'),
            __('Please tell us if your personal information changes, so we can keep it accurate.', 'seoprocookieconsent'),
        ));
    }

    /**
     * Complaints (naming the country where the business is based, when
     * known), children, other websites and changes.
     *
     * @return string
     */
    private static function closing() {
        $country   = self::country();
        $authority = '' === $country
            ? __('You also have the right to complain at any time to the data protection authority where you live or work, or where we are based.', 'seoprocookieconsent')
            : sprintf(
                /* translators: %s: country where the business is based. */
                __('You also have the right to complain at any time to the data protection authority where you live or work, or in %s, where we are based.', 'seoprocookieconsent'),
                $country
            );
        return self::part(__('Complaints', 'seoprocookieconsent'), array(
            __('If you are unhappy with how we handle your personal information, including our use of automated tools such as AI, please contact us first so we can try to put it right. Tell us what happened and what you would like us to do. We will acknowledge your complaint, look into it promptly and fairly, and tell you the outcome and what we will do about it.', 'seoprocookieconsent'),
            $authority,
        )) . self::part(__('Children', 'seoprocookieconsent'), array(
            __('This website is not intended for children. We do not knowingly collect personal information from children under the age at which they can consent to it where they live, and ask them not to send it without a parent\'s or guardian\'s permission. If you think a child has given us personal information, please contact us and we will delete it.', 'seoprocookieconsent'),
        )) . self::part(__('Other websites', 'seoprocookieconsent'), array(
            __('This website may link to other websites, or include content, plug-ins or applications from them, such as videos, maps and social media posts. Following those links or allowing those connections may let the other websites collect information about you. We do not control them and are not responsible for their privacy practices, and this policy does not cover them; please read their own privacy policies.', 'seoprocookieconsent'),
        )) . self::part(__('Changes to this policy', 'seoprocookieconsent'), array(
            __('We may update this policy when our services or the law change. The current version is always on this page, and we will tell you about significant changes where we can. Please check this page from time to time.', 'seoprocookieconsent'),
        ));
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
     * Where the business is based, or ''.
     *
     * @return string
     */
    private static function country() {
        return trim(self::answer('privacy_country', 'terms_country'));
    }

    /**
     * How visitors change their cookie choices, and where the cookie list
     * is: a Cookie settings link and what Cookie consent does with choices
     * while it is on, and a link to the cookie policy page while Cookie
     * policy is on, unless it is this page.
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
            $html .= self::paragraphs(implode("\n", self::consent_notes()));
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
     * What Cookie consent does with choices, from its settings: holding
     * back optional cookies, how long a choice is remembered, Global
     * Privacy Control, and consent records while they are kept.
     *
     * @return string[]
     */
    private static function consent_notes() {
        $days  = max(30, min(395, (int) SEOProCookieConsent_Settings::get('consent_days')));
        $notes = array(
            __('Where the law requires consent, we hold back optional cookies, and the scripts and embedded content that set them, until you allow them.', 'seoprocookieconsent'),
            sprintf(
                /* translators: %s: number of days. */
                _n('We remember your choice in a necessary cookie in your browser for %s day, then ask again.', 'We remember your choice in a necessary cookie in your browser for %s days, then ask again.', $days, 'seoprocookieconsent'),
                number_format_i18n($days)
            ),
            __('If your browser sends a Global Privacy Control signal, we treat it as a refusal of marketing cookies unless you choose otherwise.', 'seoprocookieconsent'),
        );
        if ((bool) SEOProCookieConsent_Settings::get('consent_records')) {
            $keep    = max($days, min(3650, (int) SEOProCookieConsent_Settings::get('consent_records_days')));
            $notes[] = sprintf(
                /* translators: %s: number of days. */
                _n('To show what was agreed, we also keep a record of each choice on this website: the choice, when it was made, the wording shown and a random ID that is kept with your choice and shown in Cookie settings, but not your IP address or browser details. We keep these records for %s day, relying on our legal obligation to show consent and our legitimate interests.', 'To show what was agreed, we also keep a record of each choice on this website: the choice, when it was made, the wording shown and a random ID that is kept with your choice and shown in Cookie settings, but not your IP address or browser details. We keep these records for %s days, relying on our legal obligation to show consent and our legitimate interests.', $keep, 'seoprocookieconsent'),
                number_format_i18n($keep)
            );
        }
        return $notes;
    }

    /**
     * Sections other plugins add for what they do with personal
     * information, such as the Google data an analytics plugin reads, from
     * the seoprocookieconsent_privacy_sections filter. They come before
     * "Your rights", and also follow Your own wording ($own_wording true),
     * so an add-on that the owner's wording already covers can leave its
     * section out.
     *
     * @param bool $own_wording Whether Your own wording replaces the suggestions.
     * @return string
     */
    protected static function added_sections($own_wording) {
        /**
         * Sections an add-on adds to the Privacy policy.
         *
         * @since 1.0.16
         *
         * @param array<int,array{heading:string,text:string}> $sections    Sections, in order.
         * @param bool                                         $own_wording Whether Your own wording replaces the suggestions.
         */
        return self::sections(apply_filters('seoprocookieconsent_privacy_sections', array(), (bool) $own_wording));
    }
}
