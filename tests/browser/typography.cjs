const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8').replace(/^\uFEFF/, ''));
const origin = new URL(fixture.urls.home).origin;
assert(['localhost', '127.0.0.1'].includes(new URL(origin).hostname));
const output = process.env.SE_BROWSER_OUTPUT || '.tools/se024-browser';
const manual = {poppins: 'Poppins', inter: 'Inter', roboto: 'Roboto', 'open-sans': 'Open Sans', lato: 'Lato'};
const widths = [1440, 768, 390];

async function family(page, selector) {
    return page.locator(selector).first().evaluate(element => getComputedStyle(element).fontFamily);
}

async function renderedFonts(page, selector) {
    await page.evaluate(() => document.fonts.ready.then(() => true));
    const session = await page.context().newCDPSession(page);
    try {
        await session.send('DOM.enable');
        await session.send('CSS.enable');
        const {root} = await session.send('DOM.getDocument');
        const {nodeId} = await session.send('DOM.querySelector', {nodeId: root.nodeId, selector});
        const {fonts} = await session.send('CSS.getPlatformFontsForNode', {nodeId});
        return fonts.filter(font => font.glyphCount).map(font => ({name: font.familyName, custom: font.isCustomFont}));
    } finally {
        await session.detach();
    }
}

async function checkLayout(page, controls, width) {
    await page.setViewportSize({width, height: 1000});
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Horizontal overflow at ' + width + ' on ' + page.url());
    for (const control of await page.locator(controls).all()) {
        if (!await control.isVisible()) continue;
        const box = await control.boundingBox();
        assert(box && box.x >= 0 && box.x + box.width <= width + 1 && box.height >= 48, 'Clipped or undersized control at ' + width + ' on ' + page.url());
    }
}

async function run() {
    fs.mkdirSync(output, {recursive: true});
    const browser = await chromium.launch({channel: process.env.SE_BROWSER_CHANNEL || 'chrome', headless: true});
    const thirdPartyFonts = [];
    const observeFonts = context => context.on('request', request => {
        const url = new URL(request.url());
        if (/(^|\.)fonts\.(googleapis|gstatic)\.com$/.test(url.hostname) ||
            (request.resourceType() === 'font' && url.origin !== origin)) thirdPartyFonts.push(url.href);
    });
    try {
        const admin = await browser.newContext({viewport: {width: 1440, height: 1000}});
        const member = await browser.newContext({viewport: {width: 1440, height: 1000}});
        for (const [context, cookies] of [[admin, fixture.admin], [member, fixture.member]]) {
            observeFonts(context);
            await context.addCookies(cookies);
        }
        const settings = await admin.newPage();
        const settingsFontRequests = [];
        settings.on('request', request => {
            if (request.url().includes('/sprint-engine/assets/fonts/')) settingsFontRequests.push(request.url());
        });
        await settings.goto(fixture.urls.settings);
        const select = settings.getByLabel('Font', {exact: true});
        const previewRoot = '#se-branding-preview';
        const previewHeading = previewRoot + ' h3';
        assert.equal(await select.inputValue(), 'website');
        const system = await select.locator('[value="system"]').getAttribute('data-font-family');
        const website = await select.locator('[value="website"]').getAttribute('data-font-family');
        if (process.argv[3] === 'classic') {
            assert.equal(website, system);
            assert.equal(await family(settings, previewHeading), system);
            const welcome = await member.newPage();
            await welcome.goto(fixture.urls.welcome);
            assert.equal(await family(welcome, 'body.se-runner h2'), system);
            const home = await member.newPage();
            await home.goto(fixture.urls.home);
            assert.equal(await home.locator('#sprint-engine-runner-css, #sprint-engine-dashboard-css').count(), 0);
            assert.deepEqual(settingsFontRequests, []);
            assert.deepEqual(thirdPartyFonts, []);
            console.log('PASS: Classic theme without declared body typography falls back to system in Runner and live preview.');
            return;
        }

        assert.equal(await settings.locator('#se-primary').inputValue(), '#482070');
        assert.equal(await settings.locator('#se-corner-style').inputValue(), 'rounded');
        assert.match(website, /"Sprint Engine Website"/);
        const systemAdmin = await family(settings, '.wrap h1');
        assert.equal(await family(settings, previewHeading), website);
        const siteFaces = await renderedFonts(settings, previewHeading);
        assert(siteFaces.some(face => face.custom && face.name.includes(fixture.websiteFont)), JSON.stringify(siteFaces));
        assert.deepEqual(settingsFontRequests, [], 'Website mode must not request bundled fonts.');

        const editor = await admin.newPage();
        await editor.goto(fixture.urls.editor);
        await editor.waitForFunction(() => window.sprintEngineStepPreview?.url);
        const previewUrl = await editor.evaluate(() => window.sprintEngineStepPreview.url);
        const surfaces = await Promise.all([
            ['welcome', member, fixture.urls.welcome, 'body.se-runner--not_started', '.se-runner__intro', 'button.se-runner__button'],
            ['step', member, fixture.urls.active, 'body.se-runner--in_progress', '#typography-plain', 'button.se-runner__button'],
            ['completion', member, fixture.urls.complete, 'body.se-runner--completed', '.se-runner__label', 'button.se-runner__button'],
            ['dashboard', member, fixture.urls.dashboard, 'body.se-dashboard', '.se-dashboard__label', 'button.se-dashboard__button'],
            ['step-preview', admin, previewUrl, 'body.se-runner--preview', '#typography-plain', '.se-runner__button'],
        ].map(async ([name, context, url, root, body, controls]) => ({name, page: await context.newPage(), url, root, body, controls})));

        async function checkSurfaces(expected, expectedFont, screenshot = false) {
            // Visit sequentially so resume and fixture logging remain deterministic.
            for (const {name, page, url, root, body, controls} of surfaces) {
                const response = await page.goto(url);
                assert.equal(response.status(), 200, name);
                assert.match(response.headers()['cache-control'], /private.*no-store/);
                assert.equal(await family(page, root), expected);
                assert.equal(await family(page, root + ' h1'), expected);
                assert.equal(await family(page, body), expected);
                if (await page.locator(controls).count()) assert.equal(await family(page, controls), expected);
                if (expectedFont) {
                    const fonts = await renderedFonts(page, root + ' h1');
                    assert(fonts.some(font => font.custom && font.name.includes(expectedFont)), name + ' ' + JSON.stringify(fonts));
                }
                if (name === 'step' || name === 'step-preview') {
                    const presetFamily = await page.locator('#typography-preset').evaluate((element, preset) => {
                        const styles = getComputedStyle(element);
                        return {resolved: styles.fontFamily, preset: styles.getPropertyValue('--wp--preset--font-family--' + preset)};
                    }, fixture.preset);
                    assert.equal(presetFamily.resolved.replace(/['"\s]/g, ''), presetFamily.preset.replace(/['"\s]/g, ''));
                    assert.match(await family(page, '#typography-authored-link'), /Georgia, serif/i);
                }
                for (const width of widths) {
                    await checkLayout(page, controls, width);
                    if (screenshot) await page.screenshot({path: path.join(output, name + '-' + width + '.png'), fullPage: true});
                }
                await page.setViewportSize({width: widths[0], height: 1000});
            }
        }

        async function save() {
            await Promise.all([
                settings.waitForURL(/settings-updated=true/),
                settings.getByRole('button', {name: 'Save Changes', exact: true}).click(),
            ]);
        }
        await checkSurfaces(website, fixture.websiteFont);

        await select.selectOption('system');
        assert.equal(await family(settings, previewHeading), system);
        assert.deepEqual(settingsFontRequests, []);
        await save();
        assert.equal(await select.inputValue(), 'system');
        await checkSurfaces(system);
        const platformSystem = await renderedFonts(surfaces[0].page, 'body h1');
        assert(platformSystem.length && platformSystem.every(font => !font.custom));

        for (const [choice, font] of Object.entries(manual)) {
            settingsFontRequests.length = 0;
            await select.selectOption(choice);
            const expected = '"Sprint Engine ' + font + '", ' + system;
            assert.equal(await family(settings, previewHeading), expected);
            assert.equal(await family(settings, previewRoot + ' .se-runner__button--primary'), expected);
            const fonts = await renderedFonts(settings, previewHeading);
            assert(fonts.some(face => face.custom && face.name.includes(font)), JSON.stringify(fonts));
            assert(settingsFontRequests.some(url => url.includes('/' + choice + '-latin-')));
            assert(settingsFontRequests.every(url => url.includes('/' + choice + '-latin-')));
            await save();
            assert.equal(await select.inputValue(), choice);
            await checkSurfaces(expected, font, choice === 'poppins');
            assert.equal(await family(settings, '.wrap h1'), systemAdmin);
            if (choice === 'poppins') {
                for (const width of widths) {
                    await checkLayout(settings, previewRoot + ' button', width);
                    await settings.evaluate(() => window.scrollTo(0, 0));
                    await settings.screenshot({path: path.join(output, 'branding-' + width + '.png'), fullPage: true});
                }
                await settings.setViewportSize({width: widths[0], height: 1000});
            }
        }

        await select.evaluate(element => {
            element.add(new Option('Invalid', 'x;}body{display:none'));
            element.value = 'x;}body{display:none';
        });
        await save();
        assert.equal(await select.inputValue(), 'lato');
        assert(await settings.locator('.notice-error').filter({hasText: 'Choose a supported font.'}).isVisible());
        const nonce = await settings.locator('#se-branding-form [name="_wpnonce"]').inputValue();
        const form = {action: 'update', option_page: 'sprint_engine_branding', 'sprint_engine_runner_branding[font]': 'system'};
        const optionsUrl = new URL('options.php', fixture.urls.settings).href;
        assert.equal((await member.request.post(optionsUrl, {form: {...form, _wpnonce: nonce}})).status(), 403);
        assert.equal((await admin.request.post(optionsUrl, {form})).status(), 403);
        await settings.reload();
        assert.equal(await select.inputValue(), 'lato');

        const blocked = await browser.newContext();
        await blocked.addCookies(fixture.member);
        observeFonts(blocked);
        let blockedFonts = 0;
        await blocked.route('**/*', route => {
            if (route.request().resourceType() !== 'font') return route.continue();
            blockedFonts++;
            return route.abort();
        });
        const blockedWelcome = await blocked.newPage();
        await blockedWelcome.goto(fixture.urls.welcome);
        assert.deepEqual(await renderedFonts(blockedWelcome, 'body h1'), platformSystem);
        assert(blockedFonts > 0);

        await Promise.all([
            settings.waitForURL(/settings-updated=true/),
            settings.getByRole('button', {name: 'Reset to defaults', exact: true}).click(),
        ]);
        assert.equal(await select.inputValue(), 'website');
        assert.equal(await settings.locator('#se-primary').inputValue(), '#205b48');
        assert.equal(await settings.locator('#se-corner-style').inputValue(), 'soft');
        assert.equal(await family(settings, previewHeading), website);
        const manualBlocks = blockedFonts;
        await blockedWelcome.reload();
        assert.deepEqual(await renderedFonts(blockedWelcome, 'body h1'), platformSystem);
        assert(blockedFonts > manualBlocks, 'Website mode attempted the local face.');
        assert.deepEqual(thirdPartyFonts, []);
        console.log('PASS: Website, system and five loaded manual fonts on all Core surfaces at 1440, 768 and 390 px; Gutenberg overrides, scoped preview, invalid and denied saves, reset, blocked fonts and privacy.');
    } finally {
        await browser.close();
    }
}

run().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
