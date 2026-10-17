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
    var ran = spcc.ran = spcc.ran || {};
    var queued = new WeakSet();
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
        return c.services && c.services[id] ? c.services[id].name : c.text.content;
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
        if (!old.isConnected || !spcc.allows(old.getAttribute('data-spcc-service'), old.getAttribute('data-spcc-category'))) {
            queued.delete(old);
            run(queue, i + 1);
            return;
        }
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
        var src = old.getAttribute('data-spcc-src') || old.getAttribute('src');
        if (src) {
            s.setAttribute('src', src);
        }
        var wait = src && !old.hasAttribute('async');
        if (src) {
            s.async = old.hasAttribute('async');
            if (old.hasAttribute('data-spcc-src')) {
                // Runtime loaders attach listeners to the original node.
                // Relay completion from the executable replacement to it.
                ['load', 'error'].forEach(function (name) {
                    s.addEventListener(name, function () {
                        old.dispatchEvent(new w.Event(name));
                    });
                });
            }
            if (wait) {
                s.onload = s.onerror = function () {
                    run(queue, i + 1);
                };
            }
        } else {
            s.text = old.text;
        }
        old.parentNode.replaceChild(s, old);
        queued.delete(old);
        if (!wait) {
            run(queue, i + 1);
        }
    }

    function release() {
        var queue = [];
        each(d.querySelectorAll('script[type="text/plain"][data-spcc-category]'), function (old) {
            if (!queued.has(old) && spcc.allows(old.getAttribute('data-spcc-service'), old.getAttribute('data-spcc-category'))) {
                queued.add(old);
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
                var src = frame.getAttribute('data-spcc-src');
                strip(frame);
                frame.setAttribute('src', src);
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
        if (c.preview) {
            return; // The settings screen's preview: nothing is saved or loaded.
        }
        var before = current();
        var beforeServices = keptServices();
        var recordId = spcc.choice && spcc.choice.id;
        spcc.choice = { v: c.version, c: cats, s: services || [], m: mode, t: Math.floor(Date.now() / 1000) };
        if (c.records && w.crypto && w.crypto.getRandomValues) {
            try {
                if (!recordId || !/^[a-f0-9]{32}$/.test(recordId)) {
                    var bytes = new Uint8Array(16);
                    w.crypto.getRandomValues(bytes);
                    recordId = Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
                }
                spcc.choice.id = recordId;
                spcc.choice.policy = c.policy;
                spcc.choice.snapshot = c.snapshot || '';
            } catch (e) {
                // Optional recording must never stop a local consent choice.
            }
        }
        if (pending) {
            spcc.choice.p = 1;
        }
        var cookie = (c.cookie || 'spcc_consent') + '=' + encodeURIComponent(JSON.stringify(spcc.choice))
            + '; Max-Age=' + (Math.max(1, c.days || 365) * 86400) + '; Path=/; SameSite=Lax' + (c.secure ? '; Secure' : '');
        d.cookie = cookie;
        if (c.records && spcc.choice.id && w.navigator.sendBeacon) {
            // Best effort: never delay consent or a withdrawal's page reload.
            try {
                w.navigator.sendBeacon(c.records, new Blob([JSON.stringify(spcc.choice)], { type: 'application/json' }));
            } catch (e) {
                // A blocked transport cannot change the visitor's choice.
            }
        }
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
        if (!names.length && c.preview) {
            names = (c.seen || []).map(serviceName).filter(Boolean);
            names = names.length ? names : (c.previewNames || []);
        }
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

    var askNote = dialog ? dialog.querySelector('[data-spcc-ask]') : null;
    var record = dialog ? dialog.querySelector('[data-spcc-record]') : null;

    function copyId() {
        var input = record.querySelector('input');
        var status = record.querySelector('[data-spcc-copy-status]');
        status.textContent = '';
        function fallback() {
            input.focus();
            input.select();
            var copied = false;
            try {
                copied = d.execCommand('copy');
            } catch (e) {
                // Leave the ID selected for a manual copy.
            }
            status.textContent = status.getAttribute(copied ? 'data-copied' : 'data-failed');
        }
        if (w.navigator.clipboard && w.navigator.clipboard.writeText) {
            w.navigator.clipboard.writeText(input.value).then(function () {
                status.textContent = status.getAttribute('data-copied');
            }, fallback);
        } else {
            fallback();
        }
    }
    if (record) {
        record.querySelector('[data-spcc-copy]').addEventListener('click', copyId);
    }

    function open() {
        if (!dialog) {
            return;
        }
        if (askNote) {
            askNote.hidden = true;
        }
        if (record) {
            var id = spcc.choice && spcc.choice.id;
            var showId = c.records && !c.preview && typeof id === 'string' && /^[a-f0-9]{32}$/.test(id);
            record.hidden = !showId;
            record.querySelector('input').value = showId ? id : '';
            record.querySelector('[data-spcc-copy-status]').textContent = '';
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

    /**
     * A visitor asked for something (a video lightbox, a map) that needs a
     * category they have not allowed: open Cookie settings and say so.
     * Nothing is ticked for them; allowing it releases what was held.
     *
     * @param {string} id       Service ID.
     * @param {string} category Its category.
     */
    spcc.ask = function (id, category) {
        if (c.preview || !dialog || dialog.open || spcc.allows(id, category)) {
            return;
        }
        page[id] = category;
        held = true;
        open();
        if (askNote && c.text.ask) {
            askNote.textContent = c.text.ask.replace('%1$s', serviceName(id)).replace('%2$s', c.labels[category] || category);
            askNote.hidden = false;
        }
    };

    // Players cache their script, so a second click on the same video link
    // holds nothing new: ask again for a service a click already needed.
    var videoLinks = {
        youtube: /(youtube(-nocookie)?\.com\/(watch|embed|shorts)|youtu\.be\/)/i,
        vimeo: /vimeo\.com\/(video\/)?\d/i
    };
    function askForLink(e) {
        var link = e.isTrusted && e.target.closest ? e.target.closest('a[href]') : null;
        var wanted = spcc.wanted || {};
        if (!link || (dialog && dialog.contains(link)) || (banner && banner.contains(link))) {
            return;
        }
        Object.keys(videoLinks).forEach(function (id) {
            if (wanted[id] && videoLinks[id].test(link.href)) {
                w.setTimeout(function () {
                    spcc.ask(id, wanted[id]);
                }, 0);
            }
        });
    }
    // Capture: lightboxes often stop the click before it bubbles.
    d.addEventListener('click', askForLink, true);

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

    if (c.preview) {
        // The settings screen's preview: the banner only, links stay put.
        d.addEventListener('click', function (e) {
            var a = e.target.closest ? e.target.closest('a[href]') : null;
            if (a && !a.matches('[href$="#cookie-settings"]')) {
                e.preventDefault();
            }
        }, true);
        showBanner();
        return;
    }
    // The head observer also catches lightboxes on pages initially empty of
    // services. Reuse the same release, banner and dialog state, not a second UI.
    spcc.refresh = function () {
        Object.keys(spcc.dynamicPage || {}).forEach(function (id) {
            page[id] = spcc.dynamicPage[id];
        });
        held = Object.keys(page).length > 0;
        release();
        if ((!spcc.choice || spcc.choice.p) && held && spcc.model !== 'quiet') {
            showBanner();
        } else {
            showReopen();
        }
    };

    if (w.location.hash === '#cookie-settings') {
        open();
    }
    spcc.refresh();
    if (spcc.choice || spcc.model !== 'opt-in') {
        signal();
    }
}(window, document));
