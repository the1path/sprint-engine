// SE-029 finite Gutenberg compatibility matrix. Requires blocks-fixture.php.
const {chromium, firefox, webkit} = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8').replace(/^\uFEFF/, ''));
const origin = new URL(fixture.urls.home).origin;
assert(['localhost', '127.0.0.1'].includes(new URL(origin).hostname));
const output = process.env.SE_BROWSER_OUTPUT || '.tools/se029-browser';
const widths = [1440, 768, 390, 320];
// Representative Hello Elementor reset, deliberately loaded AFTER Runner CSS.
// A competing H3 size reproduces the hierarchy failure independently of theme.
const reset = `h3 {font-size:2rem} h4 {font-size:1.8rem}
button,[type=button],[type=submit] {display:inline-block;font-weight:400;color:#c36;text-align:center;white-space:nowrap;background-color:transparent;border:1px solid #c36;padding:.5rem 1rem;font-size:1rem;border-radius:3px}
button:hover,button:focus,[type=button]:hover,[type=button]:focus {color:#fff;background-color:#c36;text-decoration:none}
button:active {background:#ff1493}`;
const toggle = '.wp-block-accordion-heading__toggle';
const report = {wordpress: fixture.wordpress, widths, engines: [], cases: [], interactions: [], notRun: []};
const css = (locator, property) => locator.evaluate((e, p) => getComputedStyle(e)[p], property);
const authoredFontSize = locator => locator.evaluate(e => {
    if (!e.style.fontSize) throw new Error('Missing authored inline font-size');
    const node = document.createElement('span'); node.style.fontSize = e.style.fontSize;
    e.append(node); const size = getComputedStyle(node).fontSize; node.remove(); return size;
});

async function settled(page) {
    // Let native Interactivity API effects/rotation settle without a replacement controller.
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
}

async function accordionState(page, button, expanded) {
    await button.waitFor();
    await page.waitForFunction(({id, expanded}) => document.getElementById(id)?.getAttribute('aria-expanded') === String(expanded), {id: await button.getAttribute('id'), expanded});
    await settled(page);
    const state = await button.evaluate(e => {
        const s = getComputedStyle(e);
        const panel = document.getElementById(e.getAttribute('aria-controls'));
        return {background: s.backgroundColor, color: s.color, border: s.borderInlineStartColor,
            hidden: Boolean(panel.hidden), itemOpen: e.closest('.wp-block-accordion-item').classList.contains('is-open')};
    });
    assert.equal(state.hidden, !expanded);
    assert.equal(state.itemOpen, expanded);
    assert.notEqual(state.background, 'rgb(204, 51, 102)', 'Theme focus pink leaked');
    assert.notEqual(state.background, 'rgb(255, 20, 147)', 'Theme active pink leaked');
    return state;
}

async function checkHeadings(page) {
    const sizes = await page.locator('#blocks-h2,#blocks-h3,#blocks-h4,#blocks-h5,#blocks-h6').evaluateAll(es => es.map(e => parseFloat(getComputedStyle(e).fontSize)));
    assert.equal(sizes.length, 5);
    sizes.slice(1).forEach((size, i) => assert(sizes[i] > size, JSON.stringify(sizes)));
    // WordPress may convert the authored 38px to a fluid clamp() on block themes.
    const authoredSize = await authoredFontSize(page.locator('#blocks-custom'));
    assert.equal(await css(page.locator('#blocks-custom'), 'fontSize'), authoredSize);
    assert.equal(await css(page.locator('#blocks-custom'), 'color'), 'rgb(102, 51, 153)');
    assert.match(await css(page.locator('#blocks-custom'), 'fontFamily'), /Georgia/i);
    assert.equal(await css(page.locator('#blocks-custom'), 'marginTop'), '13px');
    assert.equal(await css(page.locator('#blocks-custom'), 'marginBottom'), '17px');
    const preset = await page.locator('#blocks-preset').evaluate(e => ({size: getComputedStyle(e).fontSize,
        expected: getComputedStyle(e).getPropertyValue('--wp--preset--font-size--large')}));
    assert(preset.expected, 'Fixture requires WordPress large font-size preset');
    const probe = await page.locator('#blocks-preset').evaluate(e => {
        const node = document.createElement('span'); node.style.fontSize = 'var(--wp--preset--font-size--large)';
        e.append(node); const size = getComputedStyle(node).fontSize; node.remove(); return size;
    });
    assert.equal(preset.size, probe, 'Authored Gutenberg preset must win');
    for (const selector of ['#blocks-h2', '#blocks-h3', '#blocks-h4', '#blocks-h5', '#blocks-h6']) {
        assert.equal(await css(page.locator(selector), 'fontFamily'), await css(page.locator('body'), 'fontFamily'));
    }
    return sizes;
}

async function checkLayout(page, width, direction) {
    await page.evaluate(dir => document.documentElement.dir = dir, direction);
    await settled(page);
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Page overflow: ${width}/${direction}`);
    const content = await page.locator('.se-runner__content').boundingBox();
    const media = '.wp-block-button__link,.wp-block-file__button,img,audio,video,iframe,.wp-block-table,pre';
    for (const element of await page.locator('.se-runner__content').locator(media).all()) {
        if (!await element.isVisible()) continue;
        const box = await element.boundingBox();
        assert(box && box.x >= content.x - 1 && box.x + box.width <= content.x + content.width + 1, `Content overflow: ${await element.evaluate(e => e.outerHTML.slice(0, 130))} ${width}/${direction}`);
    }
    for (const button of await page.locator(toggle).all()) {
        const box = await button.boundingBox();
        assert(box.height >= 48, 'Accordion touch target');
        assert.equal(await css(button, 'whiteSpace'), 'normal');
        const title = await button.locator('.wp-block-accordion-heading__toggle-title').boundingBox();
        const icon = button.locator('.wp-block-accordion-heading__toggle-icon');
        if (!await icon.count()) continue;
        const symbol = await icon.boundingBox();
        assert(symbol.width >= 15 && symbol.x >= box.x && symbol.x + symbol.width <= box.x + box.width + 1, 'Clipped/shrunken icon');
        assert(Math.abs(symbol.y + symbol.height / 2 - (title.y + title.height / 2)) < 2, 'Icon wrapped away from title');
        const before = await button.evaluate(e => e.firstElementChild.classList.contains('wp-block-accordion-heading__toggle-icon'));
        assert.equal(symbol.x < title.x, direction === 'ltr' ? before : !before, 'Native icon side/order changed');
        assert.equal(await icon.getAttribute('aria-hidden'), 'true');
    }
}

async function checkInteractions(page) {
    if (fixture.accordion) {
        for (const kind of ['right', 'left', 'custom', 'preset', 'hidden']) {
            const group = page.locator('#blocks-accordion-' + kind);
            const button = group.locator(toggle).first();
            if (['right', 'left', 'hidden'].includes(kind)) assert.equal(await css(button, 'fontSize'), await css(page.locator('#blocks-h3'), 'fontSize'));
            const initiallyOpen = group.locator(toggle).nth(1);
            const closed = await accordionState(page, button, false);
            await accordionState(page, initiallyOpen, true);
            await button.hover();
            assert.notEqual(await css(button, 'backgroundColor'), 'rgb(204, 51, 102)');
            // Pointer click and persistent :focus, then blur; all use native JS.
            await page.mouse.down();
            const activeBackground = await css(button, 'backgroundColor');
            assert.notEqual(activeBackground, 'rgb(255, 20, 147)', 'Theme active pink leaked');
            await page.mouse.up();
            const opened = await accordionState(page, button, true);
            assert.notEqual(opened.border, closed.border, 'Expanded state needs a persistent cue');
            if (kind === 'custom') assert.equal(opened.background, 'rgba(0, 0, 0, 0)', 'Authored background remains visible when expanded');
            await page.mouse.move(0, 0);
            await button.click();
            const clickedClosed = await accordionState(page, button, false);
            await page.mouse.move(0, 0);
            // WebKit/Safari does not necessarily focus a button on pointer click.
            // Exercise the focused-closed state explicitly on that engine too.
            if (!await button.evaluate(e => document.activeElement === e)) await button.focus();
            assert.equal(await css(button, 'backgroundColor'), closed.background, 'Pointer focus must not imitate expanded state');
            assert.equal(clickedClosed.border, closed.border);
            await button.evaluate(e => e.blur());
            // Tab INTO the actual button; focus() alone does not prove keyboard modality.
            await button.evaluate(e => { const a = document.createElement('a'); a.id = 'blocks-focus-probe'; a.href = '#'; a.textContent = 'Focus probe'; e.before(a); a.focus(); });
            await page.keyboard.press('Tab');
            assert(await button.evaluate(e => document.activeElement === e && e.matches(':focus-visible')));
            assert.equal(await css(button, 'outlineStyle'), 'solid');
            assert.equal(await css(button, 'outlineWidth'), '3px');
            assert.notEqual(await css(button, 'boxShadow'), 'none');
            await page.keyboard.press('Enter');
            await accordionState(page, button, true);
            assert.equal(await css(button, 'outlineWidth'), '3px', 'Expanded focus ring');
            await page.keyboard.press('Space');
            await accordionState(page, button, false);
            report.interactions.push({case: report.current, width: page.viewportSize().width, kind,
                collapsed: closed, expanded: opened, activeBackground,
                keyboard: {visible: await button.evaluate(e => e.matches(':focus-visible')),
                    outline: await css(button, 'outline'), shadow: await css(button, 'boxShadow'),
                    fontSize: await css(button, 'fontSize'), padding: await css(button, 'padding')}});
            await page.locator('#blocks-focus-probe').evaluate(e => e.remove());
            if (kind === 'right') {
                await button.scrollIntoViewIfNeeded();
                const box = await group.boundingBox();
                const scrollY = await page.evaluate(() => window.scrollY);
                await page.screenshot({path: path.join(output, report.current + '-' + page.viewportSize().width + '-keyboard.png'), fullPage: true,
                    clip: {x: Math.max(0, box.x - 12), y: Math.max(0, box.y + scrollY - 12), width: box.width + 24, height: box.height + 24}});
            }
            if (kind === 'custom') {
                assert.equal(await css(button, 'fontSize'), await authoredFontSize(group.locator('.wp-block-accordion-heading').first()));
                assert.equal(await css(button, 'color'), 'rgb(102, 51, 153)');
                assert.equal(await css(group.locator('.wp-block-accordion-heading').first(), 'backgroundColor'), 'rgb(255, 242, 204)');
                assert.equal(await css(button, 'backgroundColor'), 'rgba(0, 0, 0, 0)');
                assert.equal(await css(button, 'paddingTop'), '11px');
                assert.equal(await css(button, 'paddingRight'), '19px');
            }
            if (kind === 'preset') assert.equal(await css(button, 'fontSize'), await css(page.locator('#blocks-preset'), 'fontSize'));
        }
    }
    if (fixture.details) {
        const details = page.locator('#blocks-details');
        const summary = details.locator('summary');
        assert.equal(await css(summary, 'display'), 'list-item');
        assert.notEqual(await css(summary, 'listStyleType'), 'none', 'Native disclosure marker removed');
        await summary.click(); assert(await details.evaluate(e => e.open));
        await summary.evaluate(e => { const a = document.createElement('a'); a.id = 'blocks-focus-probe'; a.href = '#'; a.textContent = 'Focus probe'; e.parentElement.before(a); a.focus(); });
        await page.keyboard.press('Tab');
        assert(await summary.evaluate(e => document.activeElement === e && e.matches(':focus-visible')));
        await page.keyboard.press('Space'); assert.equal(await details.evaluate(e => e.open), false);
        await page.keyboard.press('Enter'); assert(await details.evaluate(e => e.open));
        assert(await summary.evaluate(e => e.matches(':focus-visible')));
        assert.equal(await css(summary, 'outlineWidth'), '3px');
        await page.keyboard.press('Enter');
        await page.locator('#blocks-focus-probe').evaluate(e => e.remove());
    }
}

async function run() {
    fs.mkdirSync(output, {recursive: true});
    if (!fixture.accordion) report.notRun.push('Native Accordion is absent on WordPress <6.9; no polyfill is provided.');
    if (!fixture.details) report.notRun.push('Native Details is absent on WordPress <6.3; no polyfill is provided.');
    const engines = {chromium, firefox, webkit};
    for (const name of (process.env.SE_BROWSER_ENGINES || 'chromium,firefox,webkit').split(',')) {
        const browser = await engines[name].launch(name === 'chromium' ? {channel: process.env.SE_BROWSER_CHANNEL || 'chrome', headless: true} : {headless: true});
        report.engines.push({name, version: browser.version()});
        try {
            for (const theme of ['neutral', 'hello-reset']) {
                const context = await browser.newContext({viewport: {width: 1440, height: 1000}, reducedMotion: 'reduce'});
                await context.route('**/wp-content/themes/**', route => route.request().resourceType() === 'stylesheet' ? route.fulfill({contentType: 'text/css', body: ''}) : route.continue());
                await context.route('https://example.invalid/**', route => route.fulfill({contentType: 'text/html', body: '<p>Local embed fixture</p>'}));
                // No remote media or dependency on a decoding codec in this presentation sweep.
                await context.route('**/se029-fixture.*', route => route.fulfill({status: 204, body: ''}));
                await context.addCookies(fixture.member);
                for (const surface of ['welcome', 'step', 'preview']) {
                    if (surface === 'preview') await context.addCookies(fixture.admin);
                    const page = await context.newPage();
                    const errors = [];
                    const writes = [];
                    page.on('pageerror', error => errors.push(error.message));
                    page.on('request', request => { if (request.method() !== 'GET') writes.push(request.url()); });
                    const response = await page.goto(fixture.urls[surface]);
                    assert.equal(response.status(), 200, surface);
                    assert.match(response.headers()['cache-control'], /private.*no-store/);
                    await page.evaluate(() => document.fonts.ready.then(() => true));
                    if (theme === 'hello-reset') await page.addStyleTag({content: reset});
                    report.current = [name, theme, surface].join('-');
                    const shell = await page.locator('.se-runner__shell').boundingBox();
                    assert.equal(shell.width, 800, 'Existing desktop shell geometry');
                    const titles = await page.locator('.se-runner__header h1, #se-step-title').evaluateAll(es => es.map(e => ({tag: e.tagName, size: getComputedStyle(e).fontSize})));
                    assert.equal(titles[0].size, '29.6px', 'Sprint title geometry');
                    if (surface !== 'welcome') assert.equal(titles[1].size, '36px', 'Step title geometry');
                    for (const width of widths) {
                        await page.setViewportSize({width, height: 1000});
                        const headings = await checkHeadings(page);
                        for (const direction of ['ltr', 'rtl']) await checkLayout(page, width, direction);
                        if ([1440, 390].includes(width)) await page.screenshot({path: path.join(output, report.current + '-' + width + '-rtl.png'), fullPage: true});
                        await page.evaluate(() => document.documentElement.dir = 'ltr');
                        if ([1440, 390].includes(width)) await checkInteractions(page);
                        report.cases.push({engine: name, theme, surface, width, headings});
                    }
                    // At 320 CSS px, enlarge all rem/body text to 200% as an additional reflow check.
                    await page.evaluate(() => document.documentElement.style.fontSize = '200%');
                    await checkLayout(page, 320, 'ltr');
                    await checkLayout(page, 320, 'rtl');
                    await page.screenshot({path: path.join(output, report.current + '-320-text-200.png'), fullPage: true});
                    await page.evaluate(() => document.documentElement.style.fontSize = '');
                    await page.setViewportSize({width: 390, height: 1000});
                    await page.evaluate(() => document.documentElement.dir = 'ltr');
                    for (const [selector, expected] of [['#blocks-button-custom', 'rgb(255, 242, 204)'], ['.is-style-outline .wp-block-button__link', 'rgba(0, 0, 0, 0)']]) {
                        assert.equal(await css(page.locator(selector), 'backgroundColor'), expected);
                    }
                    assert.equal(await css(page.locator('#blocks-button-custom'), 'fontSize'), await authoredFontSize(page.locator('#blocks-button-custom')));
                    assert.equal(await css(page.locator('#blocks-button-custom'), 'color'), 'rgb(102, 51, 153)');
                    assert.equal(await css(page.locator('.wp-block-file__button'), 'minHeight'), '48px');
                    assert.equal(await page.locator('audio[controls],video[controls]').count(), 2);
                    assert.deepEqual(errors, [], 'Native block script errors');
                    assert.deepEqual(writes, [], 'Presentation checks must send no writes');
                    await page.close();
                }
                await context.close();
            }
            console.log('PASS: ' + name + ' headings, blocks, native states/focus, overrides, geometry, LTR/RTL and reflow on Welcome/Step/Preview.');
        } finally { await browser.close(); }
    }
    delete report.current;
    report.playwright = require('playwright/package.json').version;
    fs.writeFileSync(path.join(output, 'matrix.json'), JSON.stringify(report, null, 2));
    console.log('PASS: ' + report.cases.length + ' viewport/surface/theme/engine cases; report and screenshots in ' + output);
}

run().catch(error => { console.error(error); process.exitCode = 1; });
