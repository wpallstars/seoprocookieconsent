<?php
/**
 * Consent banner and Cookie settings dialog. Hidden until the browser
 * script decides to show them (assets/js/spcc.js).
 *
 * Printed by SEOProCookieConsent_Consent::banner().
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

$seoprocookieconsent_categories = SEOProCookieConsent_Services::categories();
$seoprocookieconsent_policy     = SEOProCookieConsent_Consent::policy_url();
?>
<section id="spcc-banner" class="spcc spcc-banner spcc-banner--<?php echo esc_attr(SEOProCookieConsent_Consent::position()); ?>" aria-label="<?php esc_attr_e('Cookie consent', 'seoprocookieconsent'); ?>" hidden>
    <div class="spcc-banner__inner">
        <p class="spcc-banner__text">
            <span data-spcc-message></span>
            <?php if ('' !== $seoprocookieconsent_policy) : ?>
                <a class="spcc-banner__policy" href="<?php echo esc_url($seoprocookieconsent_policy); ?>"><?php esc_html_e('Cookie policy', 'seoprocookieconsent'); ?></a>
            <?php endif; ?>
        </p>
        <div class="spcc-banner__actions" data-spcc-optin>
            <button type="button" class="spcc-btn spcc-btn--quiet" data-spcc-action="settings"><?php esc_html_e('Settings', 'seoprocookieconsent'); ?></button>
            <button type="button" class="spcc-btn" data-spcc-action="reject"><?php esc_html_e('Reject all', 'seoprocookieconsent'); ?></button>
            <button type="button" class="spcc-btn" data-spcc-action="accept"><?php esc_html_e('Accept all', 'seoprocookieconsent'); ?></button>
        </div>
        <div class="spcc-banner__actions" data-spcc-optout hidden>
            <button type="button" class="spcc-btn spcc-btn--quiet" data-spcc-action="settings"><?php esc_html_e('Settings', 'seoprocookieconsent'); ?></button>
            <button type="button" class="spcc-btn" data-spcc-action="ok"><?php esc_html_e('OK', 'seoprocookieconsent'); ?></button>
        </div>
    </div>
</section>
<dialog id="spcc-dialog" class="spcc spcc-dialog" aria-labelledby="spcc-dialog-title" aria-describedby="spcc-dialog-intro">
    <form method="dialog" class="spcc-dialog__form">
        <div class="spcc-dialog__head">
            <h2 id="spcc-dialog-title" class="spcc-dialog__title"><?php esc_html_e('Cookie settings', 'seoprocookieconsent'); ?></h2>
            <button type="submit" value="close" class="spcc-dialog__close" aria-label="<?php esc_attr_e('Close', 'seoprocookieconsent'); ?>">&times;</button>
        </div>
        <p id="spcc-dialog-intro" class="spcc-dialog__intro">
            <?php esc_html_e('Choose which cookies to allow. You can change this at any time from the Cookie settings link.', 'seoprocookieconsent'); ?>
            <?php if ('' !== $seoprocookieconsent_policy) : ?>
                <a href="<?php echo esc_url($seoprocookieconsent_policy); ?>"><?php esc_html_e('Cookie policy', 'seoprocookieconsent'); ?></a>
            <?php endif; ?>
        </p>
        <ul class="spcc-dialog__list">
            <?php foreach ($seoprocookieconsent_categories as $seoprocookieconsent_id => $seoprocookieconsent_category) : ?>
                <?php $seoprocookieconsent_necessary = 'necessary' === $seoprocookieconsent_id; ?>
                <li class="spcc-dialog__category" data-spcc-category-row="<?php echo esc_attr($seoprocookieconsent_id); ?>"<?php echo $seoprocookieconsent_necessary ? '' : ' hidden'; ?>>
                    <label class="spcc-switch">
                        <input type="checkbox" role="switch" name="<?php echo esc_attr($seoprocookieconsent_id); ?>" value="1" aria-describedby="spcc-desc-<?php echo esc_attr($seoprocookieconsent_id); ?>"<?php echo $seoprocookieconsent_necessary ? ' checked disabled' : ''; ?> />
                        <span class="spcc-switch__label"><?php echo esc_html($seoprocookieconsent_category['label']); ?></span>
                    </label>
                    <p id="spcc-desc-<?php echo esc_attr($seoprocookieconsent_id); ?>" class="spcc-dialog__desc">
                        <?php echo esc_html($seoprocookieconsent_category['description']); ?>
                        <span class="spcc-dialog__services" data-spcc-services="<?php echo esc_attr($seoprocookieconsent_id); ?>"></span>
                    </p>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="spcc-dialog__actions">
            <button type="submit" value="reject" class="spcc-btn"><?php esc_html_e('Reject all', 'seoprocookieconsent'); ?></button>
            <button type="submit" value="save" class="spcc-btn spcc-btn--quiet"><?php esc_html_e('Save choices', 'seoprocookieconsent'); ?></button>
            <button type="submit" value="accept" class="spcc-btn"><?php esc_html_e('Accept all', 'seoprocookieconsent'); ?></button>
        </div>
    </form>
</dialog>
