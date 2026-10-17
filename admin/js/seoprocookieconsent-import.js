/**
 * Prevent duplicate import submissions; cleanup also needs server confirmation.
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: 2026 Marcus Quinn
 * Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
 * Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
 */
(function () {
    'use strict';
    document.addEventListener('submit', function (event) {
        if (!event.target.closest('.spcc-import')) { return; }
        if (event.target.dataset.submitting) { event.preventDefault(); return; }
        event.target.dataset.submitting = '1';
        event.target.setAttribute('aria-busy', 'true');
    });
}());
