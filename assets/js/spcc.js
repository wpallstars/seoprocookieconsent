/**
 * SEO Pro Cookie Consent: banner, Cookie settings, the consent cookie and
 * releasing what the server held back (docs/architecture.md). Needs the
 * head script (spcc-head.js), which sets window.spcc.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: 2026 Marcus Quinn
 * Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
 * Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
 */
(function (w, d) {
    'use strict';
    var spcc = w.spcc;
    if (!spcc || !spcc.config) {
        return;
    }
    var c = spcc.config;
    var categories = c.categories || [];
    var banner = d.getElementById('spcc-banner');
    var dialog = d.getElementById('spcc-dialog');
    var reopen = d.querySelector('.spcc-reopen');
    var ran = {};
    var opener = null;

    function each(list, fn) {
        Array.prototype.forEach.call(list, fn);
    }

    // Services held back on this page: id => category.
    var page = {};
    each(d.querySelectorAll('script[type="text/plain"][data-spcc-service], .spcc-embed[data-spcc-embed]'), function (el) {
        page[el.getAttribute('data-spcc-service') || el.getAttribute('data-spcc-embed')] = el.getAttribute('data-spcc-category');
    });
    var held = Object.keys(page).length > 0;

    function serviceName(id) {
        return c.services && c.services[id] ? c.services[id].name : '';
    }

    function list(names) {
        var lang = d.documentElement.lang || undefined;
        if (names.length > 4) {
            names = names.slice(0, 3).concat([c.text.other]);
        }
        try {
            return new Intl.ListFormat(lang, { style: 'long', type: 'conjunction' }).format(names);
        } catch (e) {
            return names.join(', ');
        }
    }

    // Release -----------------------------------------------------------

    function strip(el) {
        each(['data-spcc-src', 'data-spcc-category', 'data-spcc-service', 'data-spcc-type'], function (a) {
            el.removeAttribute(a);
        });
    }

    // Re-create held-back scripts in order, waiting for external scripts
    // that were not async so inline code that needs them runs after them.
    function run(queue, i) {
        if (i >= queue.length) {
            return;
        }
        var old = queue[i];
        var s = d.createElement('script');
        each(old.attributes, function (a) {
            if (a.name !== 'type' && a.name.indexOf('data-spcc-') !== 0) {
                s.setAttribute(a.name, a.value);
            }
        });
        var type = old.getAttribute('data-spcc-type');
        if (type) {
            s.type = type;
        }
        ran[old.getAttribute('data-spcc-category')] = 1;
        var src = old.getAttribute('src');
        var wait = src && !old.hasAttribute('async');
        if (src) {
            s.async = old.hasAttribute('async');
            if (wait) {
                s.onload = s.onerror = function () {
                    run(queue, i + 1);
                };
            }
        } else {
            s.text = old.text;
        }
        old.parentNode.replaceChild(s, old);
        if (!wait) {
            run(queue, i + 1);
        }
    }

    function release() {
        var queue = [];
        each(d.querySelectorAll('script[type="text/plain"][data-spcc-category]'), function (old) {
            if (spcc.allows(old.getAttribute('data-spcc-service'), old.getAttribute('data-spcc-category'))) {
                queue.push(old);
            }
        });
        run(queue, 0);
        each(d.querySelectorAll('.spcc-embed[data-spcc-embed]'), function (box) {
            var category = box.getAttribute('data-spcc-category');
            if (!spcc.allows(box.getAttribute('data-spcc-embed'), category)) {
                return;
            }
            var frame = box.querySelector('iframe[data-spcc-src]');
            if (frame) {
                frame.setAttribute('src', frame.getAttribute('data-spcc-src'));
                strip(frame);
                box.parentNode.insertBefore(frame, box);
            }
            box.parentNode.removeChild(box);
            ran[category] = 1;
        });
    }

    // Consent -----------------------------------------------------------

    function current() {
        var cats = {};
        each(categories, function (cat) {
            cats[cat] = spcc.has(cat) ? 1 : 0;
        });
        return cats;
    }

    function deleteCookies(category) {
        var names = [];
        Object.keys(c.services || {}).forEach(function (id) {
            if (c.services[id].category === category) {
                names = names.concat(c.services[id].cookies || []);
            }
        });
        if (!names.length || !d.cookie) {
            return;
        }
        var host = w.location.hostname;
        var domains = ['', host, '.' + host, '.' + host.split('.').slice(-2).join('.')];
        d.cookie.split('; ').forEach(function (pair) {
            var name = pair.split('=')[0];
            var match = names.some(function (n) {
                return n.slice(-1) === '*' ? name.indexOf(n.slice(0, -1)) === 0 : name === n;
            });
            if (match) {
                domains.forEach(function (domain) {
                    d.cookie = name + '=; Max-Age=0; Path=/' + (domain ? '; Domain=' + domain : '');
                });
            }
        });
    }

    function signal() {
        var cats = current();
        if (typeof w.gtag === 'function') {
            w.gtag('consent', 'update', spcc.googleState());
        } else {
            w.dataLayer = w.dataLayer || [];
            (function () {
                w.dataLayer.push(arguments);
            }('consent', 'update', spcc.googleState()));
        }
        w.dataLayer.push({ event: 'spcc_consent_update', spcc_categories: cats });
        if (typeof w.wp_set_consent === 'function') {
            w.wp_set_consent('functional', 'allow');
            w.wp_set_consent('preferences', cats.preferences ? 'allow' : 'deny');
            w.wp_set_consent('statistics', cats.statistics ? 'allow' : 'deny');
            w.wp_set_consent('statistics-anonymous', cats.statistics ? 'allow' : 'deny');
            w.wp_set_consent('marketing', cats.marketing ? 'allow' : 'deny');
        }
        var detail = { categories: cats, services: spcc.choice && spcc.choice.s ? spcc.choice.s.slice() : [], model: spcc.model };
        var event;
        try {
            event = new CustomEvent('spcc:consent', { detail: detail });
        } catch (e) {
            event = d.createEvent('CustomEvent');
            event.initCustomEvent('spcc:consent', false, false, detail);
        }
        d.dispatchEvent(event);
    }

    /**
     * Store a choice and act on it.
     *
     * @param {Object}  cats     category => 0/1.
     * @param {Array}   services Services allowed one at a time.
     * @param {string}  mode     "opt-in" for a real choice, "opt-out" for a dismissed notice.
     * @param {boolean} pending  True when only one service was allowed (Load): the banner's question is still open.
     */
    function save(cats, services, mode, pending) {
        var before = current();
        var beforeServices = keptServices();
        spcc.choice = { v: c.version, c: cats, s: services || [], m: mode, t: Math.floor(Date.now() / 1000) };
        if (pending) {
            spcc.choice.p = 1;
        }
        var cookie = (c.cookie || 'spcc_consent') + '=' + encodeURIComponent(JSON.stringify(spcc.choice))
            + '; Max-Age=' + (Math.max(1, c.days || 365) * 86400) + '; Path=/; SameSite=Lax' + (c.secure ? '; Secure' : '');
        d.cookie = cookie;
        if (!pending) {
            hideBanner();
        }
        signal();
        var reload = false;
        each(categories, function (cat) {
            if (before[cat] && !cats[cat]) {
                deleteCookies(cat);
                // Scripts that already ran keep running: start the page again.
                reload = reload || !!ran[cat];
            }
        });
        // A service allowed on its own (Load) and now taken back.
        beforeServices.forEach(function (id) {
            var service = c.services && c.services[id];
            if (service && spcc.choice.s.indexOf(id) === -1 && !cats[service.category]) {
                reload = reload || !!ran[service.category];
            }
        });
        if (reload) {
            w.location.reload();
            return;
        }
        release();
        showReopen();
    }

    function all(value) {
        var cats = {};
        each(categories, function (cat) {
            cats[cat] = value;
        });
        return cats;
    }

    function keptServices() {
        return spcc.choice && spcc.choice.s ? spcc.choice.s.slice() : [];
    }

    // Banner ------------------------------------------------------------

    function showBanner() {
        if (!banner) {
            return;
        }
        var optIn = spcc.model === 'opt-in';
        var names = Object.keys(page).map(serviceName).filter(Boolean);
        var text = c.text.message || (names.length ? (optIn ? c.text.optIn : c.text.optOut).replace('%s', list(names)) : '');
        banner.querySelector('[data-spcc-message]').textContent = text;
        banner.querySelector('[data-spcc-optin]').hidden = !optIn;
        banner.querySelector('[data-spcc-optout]').hidden = optIn;
        banner.hidden = false;
        d.documentElement.classList.add('spcc-banner-open');
        if (reopen) {
            reopen.hidden = true;
        }
    }

    function hideBanner() {
        if (banner && !banner.hidden) {
            // Keep keyboard users in place: focus left with the banner.
            var inBanner = banner.contains(d.activeElement);
            banner.hidden = true;
            d.documentElement.classList.remove('spcc-banner-open');
            if (inBanner) {
                d.body.setAttribute('tabindex', '-1');
                d.body.focus({ preventScroll: true });
                d.body.removeAttribute('tabindex');
            }
        }
    }

    function showReopen() {
        if (reopen && (!banner || banner.hidden) && (spcc.choice || (spcc.model === 'quiet' && held))) {
            reopen.hidden = false;
        }
    }

    // Cookie settings dialog ---------------------------------------------

    function open() {
        if (!dialog) {
            return;
        }
        // Rows for categories used on the site or on this page.
        var used = {};
        (c.seen || []).concat(Object.keys(page)).forEach(function (id) {
            var service = c.services[id];
            if (service) {
                used[service.category] = used[service.category] || [];
                if (used[service.category].indexOf(service.name) === -1) {
                    used[service.category].push(service.name);
                }
            }
        });
        each(categories, function (cat) {
            var row = dialog.querySelector('[data-spcc-category-row="' + cat + '"]');
            if (!row) {
                return;
            }
            row.hidden = !used[cat];
            row.querySelector('input').checked = spcc.has(cat);
            row.querySelector('[data-spcc-services]').textContent = used[cat] ? ' ' + c.text.usedBy.replace('%s', used[cat].join(', ')) : '';
        });
        opener = d.activeElement;
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
    }
    spcc.open = open;

    function close(action) {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
        if (action === 'accept') {
            save(all(1), keptServices(), 'opt-in');
        } else if (action === 'reject') {
            save(all(0), [], 'opt-in');
        } else if (action === 'save') {
            var cats = {};
            each(categories, function (cat) {
                var input = dialog.querySelector('input[name="' + cat + '"]');
                cats[cat] = input && input.checked ? 1 : 0;
            });
            save(cats, keptServices(), 'opt-in');
        }
        if (opener && typeof opener.focus === 'function' && d.contains(opener) && !opener.closest('[hidden]')) {
            opener.focus();
        }
    }

    if (dialog) {
        var form = dialog.querySelector('form');
        var last = '';
        form.addEventListener('click', function (e) {
            var b = e.target.closest('button[type="submit"]');
            last = b ? b.value : last;
        });
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            close(e.submitter ? e.submitter.value : last);
        });
        dialog.addEventListener('click', function (e) {
            // A click on the backdrop closes without saving.
            if (e.target === dialog) {
                close('close');
            }
        });
    }

    // Actions -----------------------------------------------------------

    d.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('[data-spcc-action], a[href$="#cookie-settings"]') : null;
        if (!t || (dialog && dialog.contains(t))) {
            return;
        }
        var action = t.getAttribute('data-spcc-action') || 'settings';
        e.preventDefault();
        if (action === 'settings') {
            open();
        } else if (action === 'accept') {
            save(all(1), keptServices(), 'opt-in');
        } else if (action === 'reject') {
            save(all(0), [], 'opt-in');
        } else if (action === 'ok') {
            save(current(), keptServices(), spcc.choice ? spcc.choice.m : 'opt-out');
        } else if (action === 'allow-service') {
            var services = keptServices();
            var id = t.getAttribute('data-spcc-service');
            if (id && services.indexOf(id) === -1) {
                services.push(id);
            }
            // Allowing one service answers nothing else: an open banner stays.
            save(current(), services, spcc.choice ? spcc.choice.m : (spcc.model === 'opt-in' ? 'opt-in' : 'opt-out'), !spcc.choice || !!spcc.choice.p);
        }
    });

    // Start -------------------------------------------------------------

    if (w.location.hash === '#cookie-settings') {
        open();
    }
    release();
    if ((!spcc.choice || spcc.choice.p) && held && spcc.model !== 'quiet') {
        showBanner();
    } else {
        showReopen();
    }
    if (spcc.choice || spcc.model !== 'opt-in') {
        signal();
    }
}(window, document));
