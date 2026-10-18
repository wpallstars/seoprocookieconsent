/**
 * SEO Pro Cookie Consent: the Consent card's own parts on the settings
 * screen. A page picker for the Cookie policy page (jQuery UI autocomplete
 * over the REST search endpoint), and the banner preview: the home page in
 * a frame, reloaded after each change on the card
 * (SEOProCookieConsent_Consent::preview_panel()).
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: 2026 Marcus Quinn
 * Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
 * Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
 */
(function ($, wp, data) {
    'use strict';
    if (!$ || !data) {
        return;
    }

    var STORE = 'spccPreviewOpen';
    var SIZES = {
        desktop: { width: 1280, height: 760 },
        phone: { width: 390, height: 760 }
    };

    // Page picker ---------------------------------------------------------

    // Turns the REST title's entities (&#8217;) into text. Safe: a detached
    // <textarea> parses its content as text only, never as elements, and the
    // result is shown with .text() (renderItem below).
    function decode(text) {
        var el = document.createElement('textarea');
        el.innerHTML = text || '';
        return el.value;
    }

    // A page on this site is saved as its path, so it survives a move to
    // another domain; other addresses are kept as they are.
    function pathFor(url) {
        try {
            var page = new URL(url, data.home);
            var home = new URL(data.home);
            return page.origin === home.origin ? page.pathname + page.search + page.hash : url;
        } catch (e) {
            return url;
        }
    }

    function pagePicker() {
        var $input = $('#spcc-consent_policy_url');
        if (!$input.length || !$.fn.autocomplete || !wp || !wp.apiFetch || $input.data('spccPicker')) {
            return;
        }
        $input.data('spccPicker', true).attr('aria-autocomplete', 'list');
        $input.autocomplete({
            minLength: 0,
            delay: 250,
            classes: { 'ui-autocomplete': 'spcc-page-suggest' },
            source: function (request, respond) {
                // Anchored, no nested quantifiers: linear time, no ReDoS.
                var term = /^(https?:|\/)/.test(request.term) ? '' : request.term;
                var query = '?type=post&subtype=page&per_page=10&_fields=id,title,url&search=' + encodeURIComponent(term);
                wp.apiFetch({ path: '/wp/v2/search' + query }).then(function (pages) {
                    var items = (pages || []).map(function (page) {
                        var path = pathFor(page.url);
                        return { label: decode(page.title) || path, value: path, path: path };
                    });
                    respond(items.length ? items : [{ label: data.noPages, value: '', empty: true }]);
                }, function () {
                    respond([]);
                });
            },
            focus: function () {
                return false; // Keep what was typed while moving through the list.
            },
            select: function (event, ui) {
                if (ui.item.empty) {
                    return false;
                }
                $input.val(ui.item.value).trigger('change');
                return false;
            }
        });
        // Elements from fixed markup; the page's title and path go in as text.
        $input.autocomplete('instance')._renderItem = function (ul, item) {
            var $row = $('<div>').append($('<span class="spcc-page-suggest__title">').text(item.label));
            if (item.path) {
                $row.append($('<span class="spcc-page-suggest__path">').text(item.path));
            }
            return $('<li>').toggleClass('is-empty', !!item.empty).append($row).appendTo(ul);
        };
        $input.on('focus', function () {
            if (!$input.autocomplete('widget').is(':visible')) {
                $input.autocomplete('search', $input.val());
            }
        });
    }

    // Banner preview ------------------------------------------------------

    function previewUrl($preview) {
        var model = $preview.find('[data-spcc-preview-model]').val() || 'opt-in';
        var url = new URL($preview.attr('data-spcc-preview-src'));
        url.searchParams.set(data.arg, model);
        url.searchParams.set('spcc_t', String(Date.now())); // A fresh copy, past page caches.
        return url.toString();
    }

    function fit($preview) {
        var $viewport = $preview.find('[data-spcc-preview-viewport]');
        var size = SIZES[$preview.find('[data-spcc-preview-device]').val()] || SIZES.desktop;
        var room = $viewport.parent().width() || 0;
        if (!room) {
            return;
        }
        var scale = Math.min(1, room / size.width);
        $viewport.css({ width: Math.round(size.width * scale) + 'px', height: Math.round(size.height * scale) + 'px' });
        $viewport.find('iframe').css({ width: size.width + 'px', height: size.height + 'px', transform: 'scale(' + scale + ')' });
    }

    function load($preview) {
        if (!$preview.prop('open')) {
            return;
        }
        fit($preview);
        $preview.find('iframe').attr('src', previewUrl($preview));
    }

    function preview() {
        $('[data-spcc-preview]').each(function () {
            var $preview = $(this);
            if ($preview.data('spccPreview')) {
                return;
            }
            $preview.data('spccPreview', true);
            var timer = null;
            var later = function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    load($preview);
                }, 300);
            };
            try {
                if (window.localStorage.getItem(STORE) === '1') {
                    $preview.prop('open', true);
                }
            } catch (e) {
                // Storage blocked: the preview starts closed.
            }
            $preview.on('toggle', function () {
                try {
                    window.localStorage.setItem(STORE, $preview.prop('open') ? '1' : '0');
                } catch (e) {
                    // Storage blocked: nothing to remember.
                }
                load($preview);
            });
            $preview.on('change', '[data-spcc-preview-model], [data-spcc-preview-device]', later);
            $preview.on('click', '[data-spcc-preview-reload]', function () {
                load($preview);
            });
            $(document).on('seoprocookieconsent:setting-saved', function (event, key) {
                if (/^consent(_|$)/.test(String(key))) {
                    later();
                }
            });
            $(document).on('seoprocookieconsent:tab-shown', function () {
                fit($preview);
            });
            $(window).on('resize', function () {
                fit($preview);
            });
            load($preview);
        });
    }

    $(function () {
        pagePicker();
        preview();
        $(document).on('click', '[data-spcc-scan]', function () {
            var $button = $(this);
            var $panel = $button.closest('.spcc-panel-note');
            var $status = $panel.find('[data-spcc-scan-status]');
            $button.prop('disabled', true);
            $status.text(data.scanning);
            $.ajax({
                url: data.ajax,
                method: 'POST',
                dataType: 'json',
                timeout: 35000,
                data: { action: 'seoprocookieconsent_scan', nonce: data.nonce }
            }).done(function (response) {
                if (!response || !response.success) {
                    $status.text(data.scanFailed);
                    return;
                }
                // Server markup from this site's own admin-only, nonce-checked
                // scan: findings() escapes every value with esc_html().
                $panel.find('[data-spcc-findings]').html(response.data.html);
                $status.text(response.data.checked ? data.scanned.replace('%1$s', response.data.checked).replace('%2$s', response.data.failed) : data.scanFailed);
            }).fail(function (xhr) {
                var response = xhr.responseJSON;
                $status.text(response && response.data && response.data.message ? response.data.message : data.scanFailed);
            }).always(function () {
                $button.prop('disabled', false);
            });
        });
    });
}(window.jQuery, window.wp, window.spccConsentAdmin));
