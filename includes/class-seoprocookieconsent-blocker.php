<?php
/**
 * Holds back known services' scripts and iframes in front-end HTML until
 * the visitor's browser releases them (docs/architecture.md → Holding back).
 *
 * The HTML is the same for every visitor, so page caches keep working.
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

final class SEOProCookieConsent_Blocker {

    /** Script types that are data, not code: never held back. */
    const DATA_TYPES = array('application/ld+json', 'application/json', 'text/template', 'text/x-template', 'text/html', 'importmap', 'speculationrules', 'text/plain');

    /** Services found on the last page processed. @var string[] */
    private $found = array();

    /** Unrecognised third-party script hosts on the last page. @var string[] */
    private $hosts = array();

    /** Text never held back (lower case). @var string[] */
    private $allow;

    /** Extra text to hold back: lower-case text => category. @var array<string,string> */
    private $block;

    /** Service IDs never held back. @var string[] */
    private $skip;

    /** Site host, to tell third-party hosts apart. @var string */
    private $home_host;

    /**
     * Set up a blocker.
     *
     * @param string[]             $allow Text never held back.
     * @param array<string,string> $block Extra text to hold back => category.
     * @param string[]             $skip  Service IDs never held back.
     */
    public function __construct(array $allow = array(), array $block = array(), array $skip = array()) {
        $this->allow     = array_values(array_filter(array_map('strtolower', array_map('trim', $allow))));
        $this->block     = $block;
        $this->skip      = $skip;
        $this->home_host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
    }

    /**
     * Parse the Also hold back setting: one per line, "text" (marketing)
     * or "category: text".
     *
     * @param string $lines Setting value.
     * @return array<string,string> lower-case text => category
     */
    public static function parse_block_lines($lines) {
        $categories = SEOProCookieConsent_Services::optional_categories();
        $block      = array();
        foreach (preg_split('/[\r\n]+/', (string) $lines) ?: array() as $line) {
            $line     = trim($line);
            $category = 'marketing';
            if (preg_match('/^([a-z]+)\s*:\s*(.+)$/i', $line, $m) && in_array(strtolower($m[1]), $categories, true)) {
                $category = strtolower($m[1]);
                $line     = trim($m[2]);
            }
            if ('' !== $line) {
                $block[strtolower($line)] = $category;
            }
        }
        return $block;
    }

    /**
     * Hold back known services in a page.
     *
     * @param string $html Page HTML.
     * @return string
     */
    public function process($html) {
        $this->found = array();
        $this->hosts = array();
        if (!is_string($html) || '' === $html || (false === stripos($html, '<script') && false === stripos($html, '<iframe'))) {
            return $html;
        }
        // Not a full page (a fragment, JSON sent as text/html): leave it.
        if (false === stripos($html, '<html') && false === stripos($html, '<body')) {
            return $html;
        }

        $out = preg_replace_callback('#<script\b([^>]*)>(.*?)</script\s*>#is', array($this, 'script'), $html);
        if (null === $out) {
            return $html; // Backtrack limit on a huge page: leave it as it was.
        }
        $html = $out;
        $out  = preg_replace_callback('#<iframe\b[^>]*>.*?</iframe\s*>#is', array($this, 'iframe'), $html);
        return null === $out ? $html : $out;
    }

    /**
     * Services found on the last page processed.
     *
     * @return string[]
     */
    public function found() {
        return array_values(array_unique($this->found));
    }

    /**
     * Unrecognised third-party script hosts on the last page.
     *
     * @return string[]
     */
    public function hosts() {
        return array_values(array_unique($this->hosts));
    }

    /**
     * The service (or "custom:category") a text belongs to, or ''.
     *
     * @param string $text Address or inline code.
     * @return string
     */
    private function classify($text) {
        $lower = strtolower($text);
        foreach ($this->allow as $allow) {
            if (false !== strpos($lower, $allow)) {
                return '';
            }
        }
        foreach ($this->block as $needle => $category) {
            if (false !== strpos($lower, $needle)) {
                return 'custom:' . $category;
            }
        }
        $service = SEOProCookieConsent_Services::match($lower);
        return in_array($service, $this->skip, true) ? '' : $service;
    }

    /**
     * Category of a classified service.
     *
     * @param string $service Service ID or "custom:category".
     * @return string
     */
    private static function category_of($service) {
        if (0 === strpos($service, 'custom:')) {
            return substr($service, 7);
        }
        $data = SEOProCookieConsent_Services::get($service);
        return null === $data ? 'marketing' : $data['category'];
    }

    /**
     * Hold back one script if it belongs to a service.
     *
     * @param array $m Regex match: whole tag, attributes, inline code.
     * @return string
     */
    private function script(array $m) {
        // The whole element: the tag processor matches a script only with
        // its closing tag.
        $tags = new WP_HTML_Tag_Processor($m[0]);
        if (!$tags->next_tag(array('tag_name' => 'script')) || null !== $tags->get_attribute('data-spcc-ignore') || null !== $tags->get_attribute('data-spcc-category')) {
            return $m[0];
        }
        $type = $tags->get_attribute('type');
        $type = is_string($type) ? strtolower(trim($type)) : '';
        if (in_array($type, self::DATA_TYPES, true)) {
            return $m[0];
        }
        $src     = $tags->get_attribute('src');
        $src     = is_string($src) ? $src : '';
        $service = $this->classify('' !== $src ? $src : $m[2]);
        if ('' === $service) {
            $this->note_host($src);
            return $m[0];
        }

        $this->found[] = $service;
        if ('' !== $type) {
            $tags->set_attribute('data-spcc-type', $type);
        }
        $tags->set_attribute('type', 'text/plain');
        $tags->set_attribute('data-spcc-category', self::category_of($service));
        $tags->set_attribute('data-spcc-service', $service);
        return $tags->get_updated_html();
    }

    /**
     * Hold back one iframe if it belongs to a service, wrapped in a
     * placeholder with a notice and a Load button.
     *
     * @param array $m Regex match: whole element.
     * @return string
     */
    private function iframe(array $m) {
        $tags = new WP_HTML_Tag_Processor($m[0]);
        if (!$tags->next_tag(array('tag_name' => 'iframe')) || null !== $tags->get_attribute('data-spcc-ignore') || null !== $tags->get_attribute('data-spcc-src')) {
            return $m[0];
        }
        $src = $tags->get_attribute('src');
        $src = is_string($src) ? $src : '';
        if ('' === $src) {
            return $m[0];
        }
        $service = $this->classify($src);
        if ('' === $service) {
            return $m[0];
        }
        $this->found[] = $service;
        $category      = self::category_of($service);

        $tags->set_attribute('data-spcc-src', $src);
        $tags->remove_attribute('src');
        $tags->set_attribute('data-spcc-category', $category);
        $tags->set_attribute('data-spcc-service', $service);

        $style = self::size($tags->get_attribute('width'), $tags->get_attribute('height'));
        $image = SEOProCookieConsent_Thumbnails::url($service, $src);

        return sprintf(
            '<div class="spcc-embed" data-spcc-embed="%1$s" data-spcc-category="%2$s"%3$s>%6$s%4$s%5$s</div>',
            esc_attr($service),
            esc_attr($category),
            '' !== $style ? ' style="' . esc_attr($style) . '"' : '',
            $tags->get_updated_html(),
            self::notice($service, $category),
            '' !== $image ? '<img class="spcc-embed__thumbnail" src="' . esc_url($image) . '" alt="" loading="lazy" decoding="async" />' : ''
        );
    }

    /**
     * The placeholder's inline size from the iframe's width and height
     * attributes. Pixels on both keep the embed's shape up to its width;
     * otherwise each is copied on its own, so `width="100%" height="100%"`
     * fills the same container (Kadence's Google Maps block) instead of
     * reading as 100 pixels. A pixel height keeps compact players compact,
     * with one button row (64px) as the floor instead of the stylesheet's
     * 180px. Spcc-head.js size() does the same.
     *
     * @param mixed $width  Width attribute.
     * @param mixed $height Height attribute.
     * @return string CSS declarations, or "".
     */
    private static function size($width, $height) {
        $pixels  = '/^\s*(\d{1,5})(?:px)?\s*$/D';
        $percent = '/^\s*(\d{1,3}(?:\.\d{1,2})?)%\s*$/D';
        $width   = is_string($width) ? $width : '';
        $height  = is_string($height) ? $height : '';
        $w       = preg_match($pixels, $width, $wm) ? (int) $wm[1] : 0;
        $h       = preg_match($pixels, $height, $hm) ? (int) $hm[1] : 0;
        if ($w > 0 && $h > 0) {
            return sprintf('aspect-ratio:%d/%d;max-width:%dpx;min-height:64px', $w, $h, $w);
        }
        $style = array();
        if ($w > 0) {
            $style[] = sprintf('max-width:%dpx', $w);
        } elseif (preg_match($percent, $width, $wm)) {
            $style[] = 'width:' . $wm[1] . '%';
        }
        if ($h > 0) {
            $style[] = sprintf('height:%dpx;min-height:64px', $h);
        } elseif (preg_match($percent, $height, $hm)) {
            $style[] = 'height:' . $hm[1] . '%';
        }
        return implode(';', $style);
    }

    /**
     * The placeholder notice for a held-back embed.
     *
     * @param string $service  Service ID or "custom:category".
     * @param string $category Category.
     * @return string
     */
    private static function notice($service, $category) {
        $data       = SEOProCookieConsent_Services::get($service);
        $categories = SEOProCookieConsent_Services::categories();
        $name       = null === $data ? __('This content', 'seoprocookieconsent') : $data['name'];
        $what       = null !== $data && !empty($data['embed']) ? $data['embed'] : __('content', 'seoprocookieconsent');
        $label      = isset($categories[$category]) ? $categories[$category]['label'] : $category;

        /* translators: 1: service name, such as YouTube; 2: cookie category, such as Embedded content. */
        $text = sprintf(__('%1$s can set cookies. It loads when you allow it, or allow %2$s in Cookie settings.', 'seoprocookieconsent'), $name, $label);
        /* translators: %s: what is embedded, such as video or map. */
        $button = sprintf(__('Load %s', 'seoprocookieconsent'), $what);

        return sprintf(
            '<div class="spcc-embed__notice"><p>%1$s</p><p class="spcc-embed__actions"><button type="button" class="spcc-embed__load" data-spcc-action="allow-service" data-spcc-service="%2$s">%3$s</button> <button type="button" class="spcc-embed__settings" data-spcc-action="settings">%4$s</button></p></div>',
            esc_html($text),
            esc_attr($service),
            esc_html($button),
            esc_html__('Cookie settings', 'seoprocookieconsent')
        );
    }

    /**
     * Remember an unrecognised third-party script host.
     *
     * @param string $src Script address.
     */
    private function note_host($src) {
        // Known services let through on purpose are not unrecognised.
        if ('' === $src || '' !== SEOProCookieConsent_Services::match($src)) {
            return;
        }
        $host = strtolower((string) wp_parse_url(0 === strpos($src, '//') ? 'https:' . $src : $src, PHP_URL_HOST));
        if ('' === $host || $host === $this->home_host || self::same_site($host, $this->home_host) || self::well_known_safe($host)) {
            return;
        }
        $this->hosts[] = $host;
    }

    /**
     * Whether two hosts share a registrable domain (roughly: the last two
     * labels), such as cdn.example.com and www.example.com.
     *
     * @param string $a Host.
     * @param string $b Host.
     * @return bool
     */
    private static function same_site($a, $b) {
        $tail = function ($host) {
            return implode('.', array_slice(explode('.', $host), -2));
        };
        return '' !== $b && $tail($a) === $tail($b);
    }

    /**
     * Hosts needed for security or payments, or WordPress's own: not worth
     * listing as unrecognised. Library CDNs stay listed: they still receive
     * visitors' IP addresses, so the owner should see them.
     *
     * @param string $host Host.
     * @return bool
     */
    private static function well_known_safe($host) {
        // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- host names compared with scripts already on the page (reCAPTCHA, Turnstile); nothing is loaded from them.
        $safe = array('wp.com', 'wordpress.com', 'gstatic.com', 'recaptcha.net', 'challenges.cloudflare.com', 'js.stripe.com', 'paypal.com', 'paypalobjects.com', 'www.google.com');
        foreach ($safe as $domain) {
            if ($host === $domain || substr($host, -strlen('.' . $domain)) === '.' . $domain) {
                return true;
            }
        }
        return false;
    }
}
