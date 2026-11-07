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
                'description' => __('Adds the Terms and conditions block and [spcc_terms] shortcode: general terms from your answers, covering acceptance and changes, use of the site, accounts, content, intellectual property, links, orders, information, AI, liability, ending access, privacy, complaints, your rights and, with a country, governing law. Answer the questions, then review the wording before publishing. This is a starting point, not legal advice, and may not cover your business or local law. Existing Complianz pages are not changed: replace their block or shortcode yourself after review.', 'seoprocookieconsent'),
                'replaces'    => self::REPLACES,
                'reload'      => true,
            ),
            array(
                'terms_business' => array('type' => 'text', 'label' => __('Business or owner name', 'seoprocookieconsent'), 'description' => __('The legal name responsible for this site. Required for the suggested wording.', 'seoprocookieconsent')),
                'terms_country'  => array('type' => 'text', 'label' => __('Country', 'seoprocookieconsent'), 'description' => __('Where the business is based, named as it should be for its law and courts, such as "England and Wales" or "Jersey". Adds a Governing law section, which also keeps consumers\' protection where they live; leave empty to leave that section out.', 'seoprocookieconsent')),
                'terms_sells'    => array('type' => 'lines', 'label' => __('What the site offers or sells', 'seoprocookieconsent'), 'description' => __('Describe your goods, services, digital content or information, including payment and delivery terms where needed. When this is answered, general wording on orders and payment follows it.', 'seoprocookieconsent')),
                'terms_returns'  => array('type' => 'lines', 'label' => __('Returns, refunds and cancellations', 'seoprocookieconsent'), 'description' => __('Your policy and how customers use it. Include any withdrawal rights and required contact or online withdrawal process. Leave empty if not applicable; no return period is assumed.', 'seoprocookieconsent')),
                'terms_ai'       => array('type' => 'bool', 'default' => true, 'label' => __('Section on AI-assisted services', 'seoprocookieconsent'), 'description' => __('Says that the site and your services are developed with the help of AI from several providers, which may also help answer questions and support requests, and that visitors accept this and should check what you provide for correctness, themselves or with their own AI, before relying on it. Turn it off if you do not use AI.', 'seoprocookieconsent')),
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
        return self::introduction($business)
            . self::use_rules()
            . self::contributions()
            . self::ownership()
            . self::offering()
            . self::added_sections(false)
            . self::information()
            . self::ai_services()
            . self::liability()
            . self::relations()
            . self::closing()
            . self::part(__('Contact', 'seoprocookieconsent'), array(
                __('If you have any questions about these terms, please contact us:', 'seoprocookieconsent'),
                $contact,
            ));
    }

    /**
     * Who operates the site, accepting these terms, changes and who can
     * use the site.
     *
     * @param string $business Business or owner name.
     * @return string
     */
    private static function introduction($business) {
        $html = self::paragraphs(sprintf(
            /* translators: %s: legal business or owner name. */
            __('This website is operated by %s ("we", "us" or "our"). These terms apply to anyone using it ("you") and to the offerings described below. Please read them before using the site or placing an order.', 'seoprocookieconsent'),
            $business
        ));
        $country = trim((string) SEOProCookieConsent_Settings::get('terms_country'));
        if ('' !== $country) {
            $html .= self::paragraphs(sprintf(
                /* translators: %s: country where the business is based. */
                __('The business is based in %s.', 'seoprocookieconsent'),
                $country
            ));
        }
        return $html . self::part(__('Accepting these terms', 'seoprocookieconsent'), array(
            __('By using this website, you agree to these terms. If you do not agree, please do not use it. Where we ask you to accept these or other terms, such as when you create an account or place an order, those apply too.', 'seoprocookieconsent'),
            __('These terms work together with our privacy policy and any other agreement we make with you, such as an order confirmation or a contract for services. If another agreement with you conflicts with these terms, that agreement applies.', 'seoprocookieconsent'),
        )) . self::part(__('Changes to these terms', 'seoprocookieconsent'), array(
            __('We may update these terms from time to time, for example when our services or the law change. The current version is always on this page, and changes apply from when they are posted, so please check it from time to time. If you pay us for an ongoing service, we will give you reasonable notice of significant changes to it or its price, and you can end it before they apply.', 'seoprocookieconsent'),
        )) . self::part(__('Who can use this website', 'seoprocookieconsent'), array(
            __('This website is not aimed at children. If you are under the age at which you can make a contract where you live, please ask a parent or guardian to use it with you and to agree to these terms and place any order for you.', 'seoprocookieconsent'),
        ));
    }

    /**
     * Acceptable use, accounts and passwords.
     *
     * @return string
     */
    private static function use_rules() {
        return self::part(__('Using this website', 'seoprocookieconsent'), array(
            __('Please use this website only for its intended purposes, lawfully, in line with these terms and generally accepted online practice, and with respect for the rights of others. You must not:', 'seoprocookieconsent'),
            array(
                __('post or send anything unlawful, harmful, threatening, abusive, harassing, defamatory, obscene or hateful, or that invades someone\'s privacy or breaks a confidence;', 'seoprocookieconsent'),
                __('infringe anyone\'s intellectual property or other rights, or post anything without the permission it needs;', 'seoprocookieconsent'),
                __('upload or spread viruses, malware or other harmful code;', 'seoprocookieconsent'),
                __('try to gain unauthorised access to the website, its servers or other people\'s accounts, or get around any restriction on access;', 'seoprocookieconsent'),
                __('interfere with or disrupt the website, its security or other people\'s use of it, for example by overloading it;', 'seoprocookieconsent'),
                __('collect information from the website by automated means, such as scraping or harvesting email addresses, without our written permission, or use information from it for unsolicited marketing;', 'seoprocookieconsent'),
                __('pretend to be someone else, or misrepresent your connection with anyone;', 'seoprocookieconsent'),
                __('send spam, chain letters or unsolicited advertising through the website;', 'seoprocookieconsent'),
                __('show the website in a frame on another website, or present it as part of another website;', 'seoprocookieconsent'),
                __('use the website where its content or our offerings are illegal, or in breach of export or sanctions laws.', 'seoprocookieconsent'),
            ),
            __('To protect this website and its content, we may use measures that detect and block automated access. We may also mark our content and data in ways unique to us (data fingerprinting) and include decoy details that appear nowhere else (canary tokens), so that we can recognise copies and trace who made them. We may keep what these measures record and use it as evidence, including in legal action against unauthorised copying, and to recover our costs where the law allows.', 'seoprocookieconsent'),
        )) . self::part(__('Accounts and passwords', 'seoprocookieconsent'), array(
            __('If you create an account, please give accurate information and keep it up to date. Keep your password secure and do not share your account; you are responsible for what happens under it, unless that is our fault. Tell us straight away if you think someone else has used it.', 'seoprocookieconsent'),
            __('If we close your account because you broke these terms, please do not open another one without our permission.', 'seoprocookieconsent'),
        ));
    }

    /**
     * Content visitors post or send, moderation and ideas.
     *
     * @return string
     */
    private static function contributions() {
        return self::part(__('Content you send or post', 'seoprocookieconsent'), array(
            __('Where this website lets you post comments, reviews or other content, you keep ownership of it, and you give us a non-exclusive, royalty-free permission to publish, display, store and adapt it for format or length in connection with this website and our services. You are responsible for what you post and confirm that you have the right to post it.', 'seoprocookieconsent'),
            __('What you post may be public. We will consider reasonable requests to remove it, but copies may remain with search engines or archives that we do not control.', 'seoprocookieconsent'),
            __('We cannot check everything posted, but we may review, edit, refuse or remove any content at our discretion, for example if it breaks these terms. Where the law requires it, such as under a court order, we will cooperate with the authorities, including by disclosing who posted unlawful content.', 'seoprocookieconsent'),
            __('If you send us ideas, suggestions or feedback, we may use them freely without paying you. Please do not send us anything you consider confidential or your own invention unless we have agreed in writing how it will be handled. Personal information you send is handled as our privacy policy explains.', 'seoprocookieconsent'),
        ));
    }

    /**
     * Intellectual property, permitted use, downloads, and links.
     *
     * @return string
     */
    private static function ownership() {
        return self::part(__('Intellectual property', 'seoprocookieconsent'), array(
            __('This website and its content, such as text, images, graphics, logos and software, and its design and arrangement, belong to us or to those who license them to us, and are protected by copyright and other laws. All rights are reserved except as these terms allow.', 'seoprocookieconsent'),
            __('You may view, download and print pages for your own personal, non-commercial use, and share links to them. Unless we agree in writing or the law allows it, such as quoting a short extract with acknowledgement, you must not copy, republish, sell, adapt or otherwise use our content commercially, or remove copyright or other notices.', 'seoprocookieconsent'),
            __('Names, logos and trade marks on this website belong to us or their owners. Please do not use them without permission.', 'seoprocookieconsent'),
            __('Where we provide software, files or other downloads, they are licensed, not sold, and any licence that comes with them applies. Unless that licence or the law allows it, do not share, resell, reverse engineer or decompile them.', 'seoprocookieconsent'),
            __('If we send you a newsletter, you are welcome to forward it to people who may be interested.', 'seoprocookieconsent'),
        )) . self::part(__('Links and other websites', 'seoprocookieconsent'), array(
            __('Links to other websites, and content from them, are provided for convenience. We do not control or endorse them and are not responsible for their content, products or privacy practices; their own terms and policies apply, and you use them at your own risk. Where we list or link to other businesses, any arrangement you make with them is between you and them.', 'seoprocookieconsent'),
            __('Some links may be affiliate or sponsored links, which means we may earn a commission or payment if you buy. We say so where the law requires it.', 'seoprocookieconsent'),
            __('If you pay on a payment provider\'s page, its own terms and privacy policy also apply.', 'seoprocookieconsent'),
            __('You may link to this website fairly and lawfully, as long as you do not suggest a connection with us, or approval or endorsement from us, that does not exist, and do not link from a website with unlawful or offensive content. We may ask you to remove a link.', 'seoprocookieconsent'),
        ));
    }

    /**
     * The owner's offering, with general wording on orders and payment
     * after it, and returns.
     *
     * @return string
     */
    private static function offering() {
        $html  = '';
        $sells = (string) SEOProCookieConsent_Settings::get('terms_sells');
        if ('' !== trim($sells)) {
            $html .= self::part(__('What we offer', 'seoprocookieconsent'), array($sells)) . self::orders();
        }
        $returns = (string) SEOProCookieConsent_Settings::get('terms_returns');
        if ('' !== trim($returns)) {
            $html .= self::part(__('Returns, refunds and cancellations', 'seoprocookieconsent'), array($returns));
        }
        return $html;
    }

    /**
     * General wording on prices, orders, delivery and subscriptions.
     *
     * @return string
     */
    private static function orders() {
        return self::part(__('Orders and payment', 'seoprocookieconsent'), array(
            __('If you buy from us, prices are as shown on the website or in our quote, with any taxes and delivery charges shown before you order. Quotes are valid for the period they state.', 'seoprocookieconsent'),
            __('Your order is an offer to buy. A contract is made when we confirm that we have accepted it, for example by email or by providing what you ordered. We may decline an order, for example if something is unavailable, and will refund anything you have paid for it.', 'seoprocookieconsent'),
            __('If we find a mistake in a price or description before we accept your order, we will tell you, and you can go ahead at the correct price or cancel.', 'seoprocookieconsent'),
            __('Delivery and completion dates are estimates unless we agree a fixed date. We will tell you if there is a delay.', 'seoprocookieconsent'),
            __('If you subscribe or pay regularly, you can cancel as explained when you sign up, and keep access until the end of the period you have paid for.', 'seoprocookieconsent'),
        ));
    }

    /**
     * Accuracy, no professional advice, availability and security.
     *
     * @return string
     */
    private static function information() {
        return self::part(__('Information on this website', 'seoprocookieconsent'), array(
            __('We take reasonable care to keep this website accurate and up to date, but its content is for general information only. It may contain errors or be out of date, and we may change it at any time without notice.', 'seoprocookieconsent'),
            __('Nothing on this website is legal, financial, medical or other professional advice. Please get advice from a suitably qualified professional before acting on it. Examples and case studies are illustrations; we cannot promise any particular result.', 'seoprocookieconsent'),
        )) . self::part(__('Availability and security', 'seoprocookieconsent'), array(
            __('We aim to keep this website available, but it may sometimes be unavailable because of maintenance, technical problems or other things outside our control, and we will warn you in advance where we reasonably can. We may change, suspend or withdraw the website or any part of it. If this affects something you have paid for, we will tell you and refund what you paid for anything you do not receive.', 'seoprocookieconsent'),
            __('We take reasonable steps to keep this website secure, but cannot promise that it, or anything you download from it, is free from viruses or other harmful code. Please use your own virus protection and backups, and make sure your device and connection are suitable; we are not responsible for problems caused by them.', 'seoprocookieconsent'),
        ));
    }

    /**
     * The section on AI-assisted services, or '' while it is switched off.
     *
     * @return string
     */
    private static function ai_services() {
        if (!(bool) SEOProCookieConsent_Settings::get('terms_ai')) {
            return '';
        }
        return self::part(__('AI-assisted services', 'seoprocookieconsent'), array(
            __('We use artificial intelligence (AI) services from several providers to help develop this website and our services, and may also use them to help answer questions and support requests. By using this website and our services, you accept that they are provided with AI support.', 'seoprocookieconsent'),
            __('AI can make mistakes. Please check information, answers and other content we provide for correctness, yourself or with your own AI tools, before you rely on it, especially for important decisions.', 'seoprocookieconsent'),
        ));
    }

    /**
     * Liability, indemnity, events outside anyone's control, and ending
     * access.
     *
     * @return string
     */
    private static function liability() {
        return self::part(__('Our responsibility for loss or damage', 'seoprocookieconsent'), array(
            __('Nothing in these terms limits or excludes our liability for death or personal injury caused by our negligence, for fraud or fraudulent misrepresentation, or for anything else that the law does not allow us to limit or exclude.', 'seoprocookieconsent'),
            __('If you are a consumer, we are responsible for loss or damage you suffer that is a foreseeable result of our breaking these terms or failing to use reasonable care and skill, but not for loss or damage that is not foreseeable. We provide this website for domestic and private use, so we are not responsible to consumers for business losses, such as loss of profit, revenue, business or opportunity.', 'seoprocookieconsent'),
            __('If you use this website or buy from us for business purposes, then, as far as the law allows: the website and its content are provided "as is" and "as available", without any promise not set out in these terms; we are not responsible for indirect or consequential loss, or for loss of profit, revenue, data, business or opportunity; and our total liability to you is limited to the price you paid for the product or service concerned.', 'seoprocookieconsent'),
            __('If you use this website in breach of these terms or the law, you are responsible for any reasonable, foreseeable loss or claim that results, as far as the law allows.', 'seoprocookieconsent'),
            __('Neither of us is responsible for delays or failures caused by events outside our reasonable control, for as long as they last, except for payments due. If such an event affects something you have ordered, we will tell you and do what we reasonably can to limit the effect.', 'seoprocookieconsent'),
        )) . self::part(__('Suspending or ending access', 'seoprocookieconsent'), array(
            __('If you break these terms, we may take the action we think appropriate, such as removing content, suspending or closing your account, blocking your access, or taking legal action. We may also restrict or end access to the website or any part of it for other good reasons, such as security. If this affects a service you have paid for, we will give you reasonable notice where we can.', 'seoprocookieconsent'),
        ));
    }

    /**
     * Privacy and electronic communications, accessibility and complaints.
     *
     * @return string
     */
    private static function relations() {
        return self::part(__('Privacy and communications', 'seoprocookieconsent'), array(
            __('Our privacy policy explains how we handle personal information and use cookies.', 'seoprocookieconsent'),
            __('When you use this website or contact us electronically, you agree that we may reply and send you notices electronically, such as by email or on the website, and that these meet any legal requirement for them to be in writing, except where the law requires another form.', 'seoprocookieconsent'),
        )) . self::part(__('Accessibility and complaints', 'seoprocookieconsent'), array(
            __('We want everyone to be able to use this website. If something stops you using part of it, such as an accessibility problem, please tell us what happened, and we will try to fix it or provide the information another way.', 'seoprocookieconsent'),
            __('If you have a complaint or concern, please contact us first so we can try to put things right. Tell us what happened and what you would like us to do, and we will reply as soon as we can.', 'seoprocookieconsent'),
            __('If we cannot resolve it between us and you choose to try mediation, we will endeavour to take part in good faith where the concern is legitimate. Mediation is voluntary and does not affect your legal rights, including your right to go to a regulator or the courts.', 'seoprocookieconsent'),
        ));
    }

    /**
     * Your rights, general provisions, and governing law when the country
     * is known.
     *
     * @return string
     */
    private static function closing() {
        $html = self::part(__('Your rights', 'seoprocookieconsent'), array(
            __('Nothing in these terms takes away rights that applicable law gives you and that cannot be excluded, including mandatory consumer rights.', 'seoprocookieconsent'),
            __('These include rights about faulty goods, services and digital content, and any right to cancel an online purchase. Some places do not allow certain promises to be excluded or liability to be limited, so some of these terms may not apply to you.', 'seoprocookieconsent'),
        )) . self::part(__('General', 'seoprocookieconsent'), array(
            __('These terms, with the policies and any other agreement they refer to, are the whole agreement between you and us about their subject, and replace any earlier understanding about it.', 'seoprocookieconsent'),
            __('If a court or authority decides that part of these terms is invalid or unenforceable, that part is changed or removed only as far as needed, and the rest stays in force.', 'seoprocookieconsent'),
            __('If we do not enforce a term straight away, we can still enforce it later.', 'seoprocookieconsent'),
            __('You may not transfer your rights or obligations under these terms to anyone else without our written agreement. We may transfer ours to another organisation, such as a buyer of our business; we will tell you if this happens and make sure it does not reduce your rights.', 'seoprocookieconsent'),
            __('Only you and we have rights under these terms, unless they say otherwise.', 'seoprocookieconsent'),
        ));
        $country = trim((string) SEOProCookieConsent_Settings::get('terms_country'));
        if ('' === $country) {
            return $html;
        }
        return $html . self::part(__('Governing law', 'seoprocookieconsent'), array(
            sprintf(
                /* translators: %s: country where the business is based. */
                __('These terms are governed by the law of the place where the business is based (%s), and its courts can hear any dispute about them.', 'seoprocookieconsent'),
                $country
            ),
            __('If you are a consumer, you also keep the protection of the mandatory law of the country where you live, and can bring a dispute to your local courts where that law allows.', 'seoprocookieconsent'),
        ));
    }

    /**
     * Sections other plugins add for what they sell, such as a deposit and
     * balance on delivery, from the seoprocookieconsent_terms_sections
     * filter. They follow the owner's offering and returns answers, before
     * "Information on this website", "AI-assisted services" (when that
     * section is on) and "Your rights", and also follow Your own wording
     * ($own_wording true), so an add-on that the owner's wording already
     * covers can leave its section out.
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
