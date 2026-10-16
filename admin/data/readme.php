<?php
/**
 * Read Me content for the SEO Pro Cookie Consent admin tab (from README.md).
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: 2026 Marcus Quinn
 * Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
 * Provenance: wpas-e4502af0f07e8a92. Copies, even of part, keep these lines.
 *
 * @package SEOProCookieConsent
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * README.md contents, with a short fallback if the file is missing.
 *
 * @return array{title:string,content:string}
 */
function seoprocookieconsent_get_readme_content() {
    $readme_path = SEOPROCOOKIECONSENT_DIR . 'README.md';

    if (is_readable($readme_path)) {
        $content = (string) file_get_contents($readme_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
    } else {
        $content = "# SEO Pro Cookie Consent\n\nVersion: " . SEOPROCOOKIECONSENT_VERSION;
    }

    return array(
        'title'   => __('Read Me', 'seoprocookieconsent'),
        'content' => $content,
    );
}
