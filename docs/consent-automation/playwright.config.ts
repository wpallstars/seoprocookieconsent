// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Marcus Quinn
// Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
// Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
// Uses the upstream checkout's installed runner, not a plugin dependency.
export default {
    testDir: '.',
    testMatch: 'verify.spec.ts',
    outputDir: process.env.SPCC_TEST_OUTPUT || require('node:path').join(require('node:os').tmpdir(), 'spcc-consent-automation-results'),
    workers: 1,
    timeout: 60000,
    reporter: 'list',
    use: { browserName: 'chromium', viewport: { width: 1440, height: 900 } },
};
