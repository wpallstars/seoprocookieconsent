/**
 * SEO Pro Cookie Consent: printed inline in <head>, after window.spccConfig
 * and before any other script. Decides the visitor's consent model from the
 * time zone, reads their stored choice and sets Google Consent Mode
 * defaults (docs/architecture.md → Regions).
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: 2026 Marcus Quinn
 * Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
 * Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
 */
(function (w, d) {
    'use strict';
    var c = w.spccConfig || {};
    // Time zones outside Europe/ where opt-in law applies (EU outermost
    // regions, EEA, Gibraltar) or where location is unclear (Quebec shares
    // America/Toronto with Ontario, so both are asked first).
    var strictZones = ['Atlantic/Azores', 'Atlantic/Madeira', 'Atlantic/Canary', 'Atlantic/Faroe', 'Atlantic/Reykjavik',
        'Arctic/Longyearbyen', 'Africa/Ceuta', 'Asia/Nicosia', 'Asia/Famagusta', 'America/Cayenne', 'America/Guadeloupe',
        'America/Martinique', 'America/Marigot', 'America/St_Barthelemy', 'Indian/Reunion', 'Indian/Mayotte',
        'America/Toronto', 'America/Montreal', 'America/Blanc-Sablon'];

    function zone() {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        } catch (e) {
            return '';
        }
    }

    function strict(z) {
        return !z || /^(UTC|GMT|Etc\/|Europe\/)/.test(z) || strictZones.indexOf(z) !== -1;
    }

    function model() {
        if (c.model === 'opt-in' || c.model === 'opt-out' || c.model === 'quiet') {
            return c.model;
        }
        if (c.regions === 'everywhere' || strict(zone())) {
            return 'opt-in';
        }
        return c.regions === 'region_quiet' ? 'quiet' : 'opt-out';
    }

    function stored() {
        var name = (c.cookie || 'spcc_consent') + '=';
        var parts = d.cookie ? d.cookie.split('; ') : [];
        for (var i = 0; i < parts.length; i++) {
            if (parts[i].indexOf(name) === 0) {
                try {
                    var v = JSON.parse(decodeURIComponent(parts[i].slice(name.length)));
                    return v && v.v === c.version && v.c ? v : null;
                } catch (e) {
                    return null;
                }
            }
        }
        return null;
    }

    var m = model();
    // The settings screen's preview shows the banner as for a first visit.
    var choice = c.preview ? null : stored();
    // A dismissed notice from an opt-out place is not consent in an opt-in one.
    if (choice && m === 'opt-in' && choice.m !== 'opt-in') {
        choice = null;
    }
    var gpc = !!w.navigator.globalPrivacyControl;

    var spcc = w.spcc = w.spcc || {};
    spcc.config = c;
    spcc.model = m;
    spcc.choice = choice;
    spcc.gpc = gpc;

    /** Whether a category is allowed now. */
    spcc.has = function (category) {
        if (category === 'necessary') {
            return true;
        }
        if (spcc.choice) {
            return spcc.choice.c[category] === 1;
        }
        if (spcc.model === 'opt-in') {
            return false;
        }
        return !(gpc && category === 'marketing');
    };

    /** Whether a service is allowed now: its category, or it alone. */
    spcc.allows = function (service, category) {
        return spcc.has(category) || !!(spcc.choice && spcc.choice.s && spcc.choice.s.indexOf(service) !== -1);
    };

    /** Google Consent Mode v2 state for the current choice. */
    spcc.googleState = function () {
        var g = function (cat) {
            return spcc.has(cat) ? 'granted' : 'denied';
        };
        return {
            ad_storage: g('marketing'),
            ad_user_data: g('marketing'),
            ad_personalization: g('marketing'),
            analytics_storage: g('statistics'),
            functionality_storage: g('preferences'),
            personalization_storage: g('preferences'),
            security_storage: 'granted'
        };
    };

    w.dataLayer = w.dataLayer || [];
    var gtag = function () {
        w.dataLayer.push(arguments);
    };
    var state = spcc.googleState();
    state.wait_for_update = 500;
    gtag('consent', 'default', state);
    gtag('set', 'ads_data_redaction', !spcc.has('marketing'));

    // WP Consent API reads these.
    w.wp_consent_type = m === 'opt-in' ? 'optin' : 'optout';
}(window, document));
