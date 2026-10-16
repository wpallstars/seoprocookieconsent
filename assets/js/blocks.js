/**
 * SEO Pro Cookie Consent: editor side of the plugin's server-rendered
 * blocks (SEOProCookieConsent_Setup::register_block()). Each block shows
 * its live output in the editor, with its settings in the sidebar.
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
    var editor = wp.blockEditor || {};
    var components = wp.components || {};
    var useBlockProps = editor.useBlockProps;

    // One sidebar control per setting: a switch, a choice or text.
    function control(spec, attributes, setAttributes) {
        var common = {
            key: spec.key,
            label: spec.label,
            __nextHasNoMarginBottom: true
        };
        var set = function (value) {
            var change = {};
            change[spec.key] = value;
            setAttributes(change);
        };
        if (spec.type === 'boolean' && components.ToggleControl) {
            return el(components.ToggleControl, Object.assign(common, { checked: !!attributes[spec.key], onChange: set }));
        }
        if (spec.type === 'select' && components.SelectControl) {
            return el(components.SelectControl, Object.assign(common, { value: attributes[spec.key], options: spec.options, onChange: set, __next40pxDefaultSize: true }));
        }
        if (components.TextControl) {
            return el(components.TextControl, Object.assign(common, { value: attributes[spec.key] || '', placeholder: spec.placeholder, onChange: set, __next40pxDefaultSize: true }));
        }
        return null;
    }

    function sidebar(block, props) {
        if (!block.controls || !block.controls.length || !editor.InspectorControls || !components.PanelBody) {
            return null;
        }
        return el(editor.InspectorControls, null,
            el(components.PanelBody, { title: block.panel },
                el('div', { className: 'spcc-block-settings', style: { display: 'grid', gap: '16px' } },
                    block.controls.map(function (spec) {
                        return control(spec, props.attributes, props.setAttributes);
                    })
                )
            )
        );
    }

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
            attributes: block.attributes || {},
            supports: { html: false },
            edit: function (props) {
                var blockProps = useBlockProps ? useBlockProps() : {};
                return el(wp.element.Fragment, null,
                    sidebar(block, props),
                    el('div', blockProps, el(wp.serverSideRender, { block: block.name, attributes: props.attributes }))
                );
            },
            save: function () {
                return null;
            }
        });
    });
}(window.wp, window.spccBlocks));
