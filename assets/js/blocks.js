/**
 * SEO Pro Cookie Consent: editor side of the plugin's server-rendered
 * blocks (SEOProCookieConsent_Setup::register_block()). Each block shows
 * its live output in the editor.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: 2026 Marcus Quinn
 * Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
 * Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
 */
(function (wp, blocks) {
    'use strict';
    if (!wp || !wp.blocks || !wp.element || !wp.serverSideRender) {
        return;
    }
    var el = wp.element.createElement;
    var useBlockProps = wp.blockEditor && wp.blockEditor.useBlockProps;
    (blocks || []).forEach(function (block) {
        if (wp.blocks.getBlockType(block.name)) {
            return;
        }
        wp.blocks.registerBlockType(block.name, {
            apiVersion: 2,
            title: block.title,
            description: block.description,
            icon: block.icon,
            category: 'widgets',
            supports: { html: false },
            edit: function () {
                var props = useBlockProps ? useBlockProps() : {};
                return el('div', props, el(wp.serverSideRender, { block: block.name }));
            },
            save: function () {
                return null;
            }
        });
    });
}(window.wp, window.spccBlocks));
