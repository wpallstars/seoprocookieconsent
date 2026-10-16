<?php
/**
 * Read-only migration adapter contract. Cleanup returns an exact inventory.
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

abstract class SEOProCookieConsent_Migrate_Source {
    const KEY = '';
    const NAME = '';
    const SLUGS = array();

    /** @return array Presence and plugin state. */
    abstract public function detect();
    /** @return array Destination setting => raw value. */
    abstract public function settings();
    /** @return array Destination line setting => string[]. */
    abstract public function scripts();
    /** @return array Pages and individually replaceable document tokens. */
    abstract public function pages();
    /** @return array Browser-readable explicit-choice format, or empty. */
    abstract public function consent_cookie();
    /** @return array Exact present site-local artifacts, never consent records. */
    abstract public function leftovers();
    /** @return string Advice only; importing never writes the source. */
    abstract public function uninstall_setting();

    /** Read stored activation, including network activation, without trusting request filters. @return bool */
    public function active() {
        $files = SEOProCookieConsent_Feature::stored_active_plugins();
        if (is_multisite()) {
            $files = array_merge($files, array_keys((array) get_site_option('active_sitewide_plugins', array())));
        }
        foreach ($files as $file) {
            if (in_array(dirname($file), static::SLUGS, true)) {
                return true;
            }
        }
        return false;
    }
}
