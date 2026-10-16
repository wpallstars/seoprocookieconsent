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

    if (!c.dynamic || !w.MutationObserver) {
        return;
    }

    var services = c.services || {};
    var ids = Object.keys(services);
    var set = w.Element.prototype.setAttribute;
    var remove = w.Element.prototype.removeAttribute;
    var insert = w.Node.prototype.insertBefore;
    var append = w.Node.prototype.appendChild;
    var dynamicPage = spcc.dynamicPage = {};
    var noticed = new WeakMap();
    var matched = new WeakMap();
    var ran = spcc.ran = {};
    var changed = false;

    function each(nodes, fn) {
        Array.prototype.forEach.call(nodes, fn);
    }

    function contains(text, patterns) {
        return patterns.some(function (pattern) {
            return text.indexOf(pattern) !== -1;
        });
    }

    function classify(src) {
        var text = src.toLowerCase();
        if (!text || contains(text, c.allow || [])) {
            return '';
        }
        var extra = Object.keys(c.block || {});
        for (var i = 0; i < extra.length; i++) {
            if (text.indexOf(extra[i]) !== -1) {
                return 'custom:' + c.block[extra[i]];
            }
        }
        for (var j = 0; j < ids.length; j++) {
            var id = ids[j];
            if (contains(text, services[id].patterns || [])) {
                return (c.skip || []).indexOf(id) === -1 ? id : '';
            }
        }
        return '';
    }

    // Runs BEFORE insertion or a connected element's src setter. Observers
    // alone cannot prevent a request (or a script executing synchronously).
    function hold(el, src) {
        if (!src || !el || (el.tagName !== 'SCRIPT' && el.tagName !== 'IFRAME') || el.hasAttribute('data-spcc-ignore')) {
            return false;
        }
        var type = (el.getAttribute('type') || '').trim().toLowerCase();
        var held = el.hasAttribute('data-spcc-src');
        if (el.tagName === 'SCRIPT' && !held && /^(application\/(ld\+json|json)|text\/(plain|template|x-template|html)|importmap|speculationrules)$/.test(type)) {
            return false;
        }
        var id = classify(src);
        var category = services[id] ? services[id].category : id.slice(7);
        if (!id) {
            matched.delete(el);
            return false;
        }
        matched.set(el, { id: id, category: category });
        if (spcc.allows(id, category)) {
            return false;
        }
        set.call(el, 'data-spcc-service', id);
        set.call(el, 'data-spcc-category', category);
        set.call(el, 'data-spcc-src', src);
        if (el.tagName === 'SCRIPT') {
            if (type && !held) {
                set.call(el, 'data-spcc-type', el.getAttribute('type'));
            }
            set.call(el, 'type', 'text/plain');
        }
        remove.call(el, 'src');
        return true;
    }

    function scan(node) {
        if (!node || (node.nodeType !== 1 && node.nodeType !== 11)) {
            return;
        }
        if (node.nodeType === 1) {
            hold(node, node.getAttribute('src') || '');
        }
        each(node.querySelectorAll('script[src], iframe[src]'), function (el) {
            hold(el, el.getAttribute('src') || '');
        });
    }

    // Keep native return values, node identity, listeners and attributes.
    ['appendChild', 'insertBefore', 'replaceChild'].forEach(function (name) {
        var native = w.Node.prototype[name];
        w.Node.prototype[name] = function (node) {
            scan(node);
            return native.apply(this, arguments);
        };
    });
    [w.Element.prototype, w.Document.prototype, w.DocumentFragment.prototype].forEach(function (proto) {
        ['append', 'prepend', 'replaceChildren', 'before', 'after', 'replaceWith'].forEach(function (name) {
            var native = proto[name];
            if (!native) {
                return;
            }
            proto[name] = function () {
                each(arguments, scan);
                return native.apply(this, arguments);
            };
        });
    });

    w.Element.prototype.setAttribute = function (name, value) {
        if (String(name).toLowerCase() === 'src' && hold(this, String(value))) {
            return;
        }
        return set.apply(this, arguments);
    };
    [w.HTMLScriptElement.prototype, w.HTMLIFrameElement.prototype].forEach(function (proto) {
        var descriptor = Object.getOwnPropertyDescriptor(proto, 'src');
        if (!descriptor || !descriptor.configurable) {
            return;
        }
        Object.defineProperty(proto, 'src', {
            configurable: descriptor.configurable,
            enumerable: descriptor.enumerable,
            get: descriptor.get,
            set: function (value) {
                if (!hold(this, String(value))) {
                    descriptor.set.call(this, value);
                }
            }
        });
    });

    // Parse markup in an inert template, not a live container: iframe
    // navigation starts during innerHTML/insertAdjacentHTML otherwise.
    var html = Object.getOwnPropertyDescriptor(w.Element.prototype, 'innerHTML');
    function safeHTML(value) {
        if (typeof value !== 'string' || !/<(?:script|iframe)\b/i.test(value)) {
            return value;
        }
        var template = d.createElement('template');
        html.set.call(template, value);
        scan(template.content);
        return html.get.call(template);
    }
    ['innerHTML', 'outerHTML'].forEach(function (name) {
        var descriptor = Object.getOwnPropertyDescriptor(w.Element.prototype, name);
        Object.defineProperty(w.Element.prototype, name, {
            configurable: descriptor.configurable,
            enumerable: descriptor.enumerable,
            get: descriptor.get,
            set: function (value) {
                descriptor.set.call(this, safeHTML(value));
            }
        });
    });
    var adjacent = w.Element.prototype.insertAdjacentHTML;
    w.Element.prototype.insertAdjacentHTML = function (position, value) {
        return adjacent.call(this, position, safeHTML(value));
    };

    function placeholder(frame) {
        if (!frame.parentNode) {
            return;
        }
        var id = frame.getAttribute('data-spcc-service');
        var category = frame.getAttribute('data-spcc-category');
        var previous = frame.closest('.spcc-embed');
        if (previous) {
            if (previous.getAttribute('data-spcc-embed') === id) {
                return;
            }
            insert.call(previous.parentNode, frame, previous);
            previous.remove();
        }
        var box = d.createElement('div');
        box.className = 'spcc-embed';
        set.call(box, 'data-spcc-embed', id);
        set.call(box, 'data-spcc-category', category);
        var width = parseInt(frame.getAttribute('width'), 10);
        var height = parseInt(frame.getAttribute('height'), 10);
        if (width > 0 && height > 0) {
            box.style.aspectRatio = width + '/' + height;
            box.style.maxWidth = width + 'px';
        }
        var notice = d.createElement('div');
        notice.className = 'spcc-embed__notice';
        var text = d.createElement('p');
        text.textContent = c.text.embed.replace('%1$s', services[id] ? services[id].name : c.text.content)
            .replace('%2$s', c.labels[category] || category);
        append.call(notice, text);
        var actions = d.createElement('p');
        actions.className = 'spcc-embed__actions';
        ['allow-service', 'settings'].forEach(function (action) {
            var button = d.createElement('button');
            button.type = 'button';
            button.className = action === 'settings' ? 'spcc-embed__settings' : 'spcc-embed__load';
            button.textContent = action === 'settings' ? c.text.settings : c.text.load;
            set.call(button, 'data-spcc-action', action);
            set.call(button, 'data-spcc-service', id);
            append.call(actions, button);
        });
        append.call(notice, actions);
        insert.call(frame.parentNode, box, frame);
        append.call(box, frame);
        append.call(box, notice);
    }

    function added(node) {
        scan(node);
        if (!node || node.nodeType !== 1 || !node.isConnected) {
            return;
        }
        function note(el) {
            var match = matched.get(el);
            var id = match ? match.id : el.getAttribute('data-spcc-service');
            var category = match ? match.category : el.getAttribute('data-spcc-category');
            if (id && noticed.get(el) !== id) {
                noticed.set(el, id);
                dynamicPage[id] = category;
                if (spcc.allows(id, category)) {
                    ran[category] = 1;
                }
                changed = true;
            }
        }
        if (node.matches('script, iframe')) {
            note(node);
        }
        each(node.querySelectorAll('script, iframe'), note);
        if (node.matches('iframe[data-spcc-src]')) {
            placeholder(node);
        }
        each(node.querySelectorAll('iframe[data-spcc-src]'), placeholder);
    }
    var observer = new w.MutationObserver(function (records) {
        records.forEach(function (record) {
            if (record.type === 'attributes') {
                added(record.target);
            } else {
                each(record.addedNodes, added);
            }
        });
        if (changed && typeof spcc.refresh === 'function') {
            changed = false;
            spcc.refresh();
        }
    });
    observer.observe(d.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['src', 'data-spcc-src'] });
}(window, document));
