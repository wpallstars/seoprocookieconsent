// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Marcus Quinn
// Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
// Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
// Run with the upstream autoconsent Playwright CLI; see README.md here.
// Development only, never shipped (.distignore leaves out docs/). The
// require() paths are the developer's own upstream checkouts, set in the
// environment below, so the non-literal requires load no outside input.
const path = require('node:path');
const root = process.env.AUTOCONSENT_ROOT;
const comRoot = process.env.CONSENTOMATIC_ROOT;
if (!root || !comRoot || !process.env.SPCC_TEST_URL) {
    throw new Error('Set AUTOCONSENT_ROOT, CONSENTOMATIC_ROOT and SPCC_TEST_URL');
}
const { test, expect } = require(path.join(root, 'node_modules/@playwright/test'));
const rule = require('./autoconsent.json');
const comRule = require('./consent-o-matic.json');

// Feed the exact local draft to the unmodified upstream harness in memory.
// Both formats are used by that harness: full for opt-in, compact for opt-out.
const { encodeRules } = require(path.join(root, 'lib/encoding'));
const full = require(path.join(root, 'rules/rules.json'));
full.autoconsent.splice(0, full.autoconsent.length, rule);
Object.assign(require(path.join(root, 'rules/compact-rules.json')), encodeRules([rule], null));
const generateCMPTests = require(path.join(root, 'playwright/runner')).default;

test('drafts match upstream schemas', () => {
    const Ajv = require(path.join(root, 'node_modules/ajv')).default;
    const generator = require(path.join(root, 'node_modules/ts-json-schema-generator'));
    const schema = generator.createGenerator({
        path: path.join(root, 'lib/rules.ts'), type: 'AutoConsentCMPRule',
    }).createSchema('AutoConsentCMPRule');
    const ajv = new Ajv({ allowUnionTypes: true, strict: false });
    const validate = ajv.compile(schema);
    expect(validate(rule), JSON.stringify(validate.errors)).toBe(true);
    const validateCom = ajv.compile(require(path.join(comRoot, 'rules.schema.json')));
    expect(validateCom(comRule), JSON.stringify(validateCom.errors)).toBe(true);
});

const beacons = new WeakMap();
test.beforeEach(async ({ page }) => {
    const records = [];
    beacons.set(page, records);
    page.on('response', response => {
        if (response.url().includes('/seoprocookieconsent/v1/records') && response.request().method() === 'POST') {
            records.push(response.status());
        }
    });
});

async function choice(page) {
    const cookie = (await page.context().cookies()).find(c => c.name === 'spcc_consent');
    expect(cookie).toBeTruthy();
    return JSON.parse(decodeURIComponent(cookie.value));
}

async function verifyChoice(page, expected) {
    await expect.poll(async () => (await choice(page)).c).toEqual(expected);
    const saved = await choice(page);
    expect(saved.m).toBe('opt-in');
    expect(saved.p).toBeUndefined();
    expect(saved.s).toEqual([]);
    expect(saved.id).toMatch(/^[a-f0-9]{32}$/);
    await expect.poll(() => beacons.get(page).length).toBe(1);
    expect(beacons.get(page)[0]).toBe(204);
    await expect(page.locator('#spcc-banner')).toBeHidden();
    await expect(page.locator('#spcc-dialog')).not.toHaveAttribute('open');
    await page.reload();
    await expect(page.locator('#spcc-banner')).toBeHidden();
    expect((await choice(page)).c).toEqual(expected);
    // The four page-local inline markers run only for allowed categories.
    const ran = await page.evaluate(() => Object.fromEntries(
        ['preferences', 'statistics', 'marketing', 'embeds'].map(cat => [cat, window['gh45_' + cat] || 0])
    ));
    expect(ran).toEqual(expected);
    expect(beacons.get(page).length).toBe(1); // Reload is not another choice.
}

test.describe('upstream autoconsent harness', () => {
    test.afterEach(async ({ page }, info) => {
        const value = info.title.includes('optIn') ? 1 : 0;
        await verifyChoice(page, { preferences: value, statistics: value, marketing: value, embeds: value });
    });
    generateCMPTests(rule.name, [process.env.SPCC_TEST_URL]);
});

// Bundle the real Consent-O-Matic engine with its own detectors and actions.
// No hand-written action interpreter, mocked CMP, or extension installation.
const comBundle = require(path.join(root, 'node_modules/esbuild')).buildSync({
    stdin: { contents: `import Engine from './Extension/ConsentEngine.js'; window.GH45Engine = Engine;`, resolveDir: comRoot },
    bundle: true, write: false, format: 'iife', platform: 'browser',
}).outputFiles[0].text;

for (const [name, values] of Object.entries({
    reject: { A: false, B: false, E: false, F: false },
    accept: { A: true, B: true, E: true, F: true },
    partial: { A: true, B: false, E: true, F: false },
})) {
    test('Consent-O-Matic ' + name, async ({ page }) => {
        await page.goto(process.env.SPCC_TEST_URL);
        await expect(page.locator('#spcc-banner')).toBeVisible();
        await page.addScriptTag({ content: comBundle });
        await page.evaluate(({ config, values }) => {
            const Engine = window.GH45Engine;
            // Disable only the extension progress overlay, not detection/actions.
            Engine.debugValues = { skipHideMethod: true };
            Engine.singleton = new Engine(config, values, result => { window.gh45Result = result; });
            Engine.singleton.handleMutations([]);
        }, { config: comRule, values });
        await expect.poll(() => page.evaluate(() => window.gh45Result?.handled)).toBe(true);
        await verifyChoice(page, {
            preferences: Number(values.A), statistics: Number(values.B),
            embeds: Number(values.E), marketing: Number(values.F),
        });
    });
}
