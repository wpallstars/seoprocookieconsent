<?php
/**
 * Shared base for documents written from the owner's answers (Terms and
 * conditions, Privacy policy): the questions under the feature's switch,
 * the shortcode and block, Your own wording, sections add-ons add, and
 * escaping every answer as plain text.
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

abstract class SEOProCookieConsent_Document extends SEOProCookieConsent_Feature {

    /** Shortcode tag, such as spcc_terms. */
    const SHORTCODE = '';

    /** Block name after "seoprocookieconsent/", such as terms. */
    const BLOCK = '';

    /**
     * Block title, description and icon.
     *
     * @return array<string,string>
     */
    abstract protected static function block();

    /**
     * The suggested wording from the answers, or '' until the answers it
     * needs are given.
     *
     * @return string
     */
    abstract protected static function suggested();

    /**
     * Sections add-ons add, from the feature's own filter, rendered with
     * sections().
     *
     * @param bool $own_wording Whether Your own wording replaces the suggestions.
     * @return string
     */
    abstract protected static function added_sections($own_wording);

    /**
     * The feature's switch, then its questions, each shown under the switch.
     * A question gives its type, label and description, and its default
     * when that is not empty.
     *
     * @param array               $toggle    The switch's settings entry.
     * @param array<string,array> $questions Setting key => type, label, description, optional default.
     * @return array<string,array>
     */
    protected static function with_questions(array $toggle, array $questions) {
        $settings = array(static::KEY => $toggle);
        foreach ($questions as $key => $question) {
            $settings[$key] = $question + array(
                'default' => '',
                'parent'  => static::KEY,
            );
        }
        return $settings;
    }

    /** Register the shortcode and block only when enabled. */
    public static function boot() {
        if (!static::enabled()) {
            return;
        }
        add_shortcode(static::SHORTCODE, array(static::class, 'render'));
        add_action('init', array(static::class, 'register_block'), 20);
    }

    /** Register the dynamic block using the shared editor preview. */
    public static function register_block() {
        SEOProCookieConsent_Setup::register_block(static::BLOCK, static::block(), array(static::class, 'render'));
    }

    /**
     * Render fresh from settings: Your own wording ({KEY}_wording) when
     * given, otherwise the suggested wording. No visitor-specific data or
     * extra assets.
     *
     * @return string
     */
    public static function render() {
        if (!static::enabled()) {
            return '';
        }
        $wording = (string) SEOProCookieConsent_Settings::get(static::KEY . '_wording');
        $html    = '' !== trim($wording) ? self::paragraphs($wording) . static::added_sections(true) : static::suggested();
        return '' === $html ? '' : '<div class="spcc-' . esc_attr(static::KEY) . '">' . $html . '</div>';
    }

    /**
     * Sections from a filter: each a heading and plain text, one paragraph
     * per line, escaped like the owner's answers. Others are left out.
     *
     * @param mixed $sections Filtered sections.
     * @return string
     */
    protected static function sections($sections) {
        $html = '';
        foreach (is_array($sections) ? $sections : array() as $section) {
            $html .= self::section($section);
        }
        return $html;
    }

    /**
     * One section from a filter, or '' unless it has a heading and text.
     *
     * @param mixed $section A filtered section.
     * @return string
     */
    private static function section($section) {
        if (!is_array($section) || !isset($section['heading'], $section['text']) || !is_string($section['heading']) || !is_string($section['text'])) {
            return '';
        }
        $heading = trim($section['heading']);
        return '' !== $heading && '' !== trim($section['text']) ? self::heading($heading) . self::paragraphs($section['text']) : '';
    }

    /**
     * A section heading.
     *
     * @param string $text Plain text.
     * @return string
     */
    protected static function heading($text) {
        return '<h2>' . esc_html($text) . '</h2>';
    }

    /**
     * A section of suggested wording: a heading, then each part in order,
     * a string as paragraphs and an array as a list.
     *
     * @param string                $heading Plain text.
     * @param array<string|string[]> $parts   Paragraphs and lists.
     * @return string
     */
    protected static function part($heading, array $parts) {
        $html = self::heading($heading);
        foreach ($parts as $part) {
            $html .= is_array($part) ? self::items($part) : self::paragraphs((string) $part);
        }
        return $html;
    }

    /**
     * Escape each nonempty item as a list item.
     *
     * @param string[] $items Plain text.
     * @return string
     */
    protected static function items(array $items) {
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
    protected static function paragraphs($text) {
        $html = '';
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: array() as $line) {
            if ('' !== trim($line)) {
                $html .= '<p>' . esc_html($line) . '</p>';
            }
        }
        return $html;
    }
}
