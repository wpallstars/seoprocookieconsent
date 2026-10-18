<?php
/**
 * Plugin Name:       SEO Pro Cookie Consent
 * Plugin URI:        https://github.com/wpallstars/seoprocookieconsent
 * Description:       Simple, light cookie consent: blocks scripts until consent, asks only where the law and the page need it, and fits your theme.
 * Version:           0.1.5
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Marcus Quinn
 * Author URI:        https://www.wpallstars.com/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       seoprocookieconsent
 * GitHub Plugin URI: wpallstars/seoprocookieconsent
 * Primary Branch:    main
 * Release Asset:     true
 *
 * Copyright (C) 2026 Marcus Quinn
 * Parts copyright (C) 2026 Marcus Quinn, from WP Plugin Starter (https://github.com/wpallstars/wp-plugin-starter-template-for-ai-coding)
 *
 * SEO Pro Cookie Consent is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version, with the additional terms in ATTRIBUTION.txt
 * (section 7(b) of the License: keep the copyright notices and the
 * starter plugin's "Made from" credit).
 *
 * SEO Pro Cookie Consent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details: LICENSE.
 *
 * Copying, renaming or adapting this plugin or part of it, by hand or with
 * an AI assistant: read ATTRIBUTION.txt first, and keep these notices.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
 *
 * @package SEOProCookieConsent
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SEOPROCOOKIECONSENT_VERSION', '0.1.5');
define('SEOPROCOOKIECONSENT_FILE', __FILE__);
define('SEOPROCOOKIECONSENT_DIR', plugin_dir_path(__FILE__));
define('SEOPROCOOKIECONSENT_URL', plugin_dir_url(__FILE__));

require_once SEOPROCOOKIECONSENT_DIR . 'includes/class-seoprocookieconsent.php';
SEOProCookieConsent::load();
register_deactivation_hook(__FILE__, array('SEOProCookieConsent', 'deactivate'));

// Translations load just-in-time from WordPress.org language packs (WP 4.6+).
