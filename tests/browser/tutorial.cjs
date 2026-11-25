// SE-030 real native forms, Gutenberg reopen, private Runner and six-Step journey.
const {chromium, firefox, webkit} = require('playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8').replace(/^\uFEFF/, ''));
assert(['localhost', '127.0.0.1'].includes(new URL(fixture.home).hostname));
const output = process.env.SE_BROWSER_OUTPUT || '.tools/se030-browser';
fs.mkdirSync(output, {recursive: true});
(async () => {
    const report = [];
    let runnerUrl, sprintId;
    const engines = Object.entries({chromium, firefox, webkit}).filter(([name]) => !process.env.SE_BROWSER_ENGINES || process.env.SE_BROWSER_ENGINES.split(',').includes(name));
    assert(engines.length > 0, 'Select at least one supported browser engine');
    for (const [index, [name, engine]] of engines.entries()) {
        const browser = await engine.launch(name === 'chromium' ? {channel: process.env.SE_BROWSER_CHANNEL || 'chrome', headless: true} : {headless: true});
        try {
            const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
            await context.addCookies([fixture.admins[index].cookie, fixture.admins[index].auth_cookie]);
            const page = await context.newPage();
            const errors = []; page.on('pageerror', error => errors.push({message: error.message, stack: error.stack, url: page.url()}));
            if (index === 0) {
                await page.goto(fixture.welcome);
                assert(await page.getByRole('button', {name: 'Install Tutorial Sprint', exact: true}).isVisible());
                await page.screenshot({path: `${output}/welcome.png`, fullPage: true});
                await Promise.all([page.waitForURL('**page=sprint-engine-settings'), page.getByRole('button', {name: 'I know how to use Sprint Engine', exact: true}).click()]);
                assert.equal(await page.getByRole('link', {name: 'Open Tutorial Sprint', exact: true}).count(), 0);
                assert.equal(await page.getByRole('button', {name: 'I know how to use Sprint Engine', exact: true}).count(), 0);
                await Promise.all([page.waitForNavigation(), page.getByRole('button', {name: 'Install Tutorial Sprint', exact: true}).click()]);
            } else { await page.goto(fixture.settings); }
            // Settings loads native Media Library REST requests after document load.
            // Let them finish before refresh/navigation instead of aborting initialization.
            await page.waitForLoadState('networkidle');
            runnerUrl = await page.getByRole('link', {name: 'Open Tutorial Sprint', exact: true}).getAttribute('href');
            await page.reload();
            await page.waitForLoadState('networkidle');
            assert.equal(await page.getByRole('button', {name: 'Install Tutorial Sprint', exact: true}).count(), 0);
            await page.getByRole('link', {name: 'Edit Tutorial Sprint', exact: true}).click();
            await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId());
            sprintId = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());
            await page.waitForFunction(() => wp.data.select('core/notices').getNotices().some(n => n.id === 'sprint-engine-tutorial-admin-only'));
            await page.locator('.components-notice__content').filter({hasText: 'This is the Sprint Engine Tutorial Sprint — administrators only'}).waitFor({state: 'visible'});
            const guide = page.getByRole('dialog').filter({hasText: /Welcome to (the editor|the block editor)/});
            if (await guide.count()) { await guide.getByRole('button', {name: /^Close( dialog)?$/}).click(); await guide.waitFor({state: 'hidden'}); }
            assert.equal(await page.evaluate(() => wp.data.select('core/notices').getNotices().find(n => n.id === 'sprint-engine-tutorial-admin-only').isDismissible), false);
            assert(await page.evaluate(() => wp.data.select('core/block-editor').getBlocks().every(b => b.isValid)));
            await page.screenshot({path: `${output}/${name}-editor.png`, fullPage: true});
            await page.goto(runnerUrl);
            assert(await page.getByText('Ready to start', {exact: true}).isVisible());
            await Promise.all([page.waitForNavigation(), page.locator('#se-runner-action').click()]);
            const steps = [];
            for (let step = 1; step <= 6; ++step) {
                assert(await page.getByText(`Step ${step} of 6`, {exact: true}).isVisible());
                const content = page.locator('.se-runner__content');
                assert.deepEqual(await content.locator('h3').allTextContents(), ['Your task', 'You’re ready to continue when…']);
                assert.equal(await content.locator('ol').count(), 1);
                assert.equal(await content.locator('ol > li').count(), [3, 5, 4, 4, 5, 4][step - 1]);
                assert(await content.locator('p').first().isVisible());
                assert(await content.locator('h3 + p').isVisible());
                assert.equal(await content.locator('[style], [class*="has-"]').count(), 0, 'Tutorial must inherit normal Runner branding');
                const endpoint = await page.evaluate(() => sprintEngineRunner.endpoint);
                steps.push(Number(endpoint.match(/steps\/(\d+)/)[1]));
                const links = content.locator('a');
                if ([1, 2, 6].includes(step)) {
                    assert.equal(await links.count(), 1);
                    const link = links.first();
                    assert.equal(await link.getAttribute('target'), '_blank');
                    assert.equal(await link.getAttribute('rel'), 'noopener noreferrer');
                    assert((await link.innerText()).includes('(opens in a new tab)'));
                    const href = await link.getAttribute('href');
                    assert(href.startsWith('/') && !href.startsWith('//'));
                    const expectedPath = new URL(step === 1 ? 'wp-admin/' : step === 2 ? 'wp-admin/post-new.php?post_type=sprint_engine_sprint' : 'sprint-engine/dashboard/', fixture.home);
                    assert.equal(new URL(href, fixture.home).href, expectedPath.href);
                    const originalUrl = page.url();
                    const [opened] = await Promise.all([context.waitForEvent('page'), link.click()]);
                    await opened.waitForLoadState('load');
                    assert.equal(opened.url(), expectedPath.href);
                    assert.equal(page.url(), originalUrl, 'The tutorial tab must stay open');
                    if (step === 1) await opened.getByRole('heading', {name:'Dashboard',exact:true}).waitFor();
                    if (step === 2) {
                        await opened.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId());
                        await opened.waitForLoadState('networkidle');
                        const welcome = opened.getByRole('dialog').filter({hasText: /Welcome to (the editor|the block editor)/});
                        if (await welcome.count()) await welcome.getByRole('button', {name: /^Close( dialog)?$/}).click();
                        // Check the usable writing canvas after native welcome initialization.
                        const canvas = await opened.locator('.editor-post-title__input').count() ? opened : opened.frameLocator('iframe[name="editor-canvas"]');
                        await canvas.getByRole('textbox', {name:'Add title',exact:true}).waitFor();
                    }
                    if (step === 6) await opened.getByRole('heading', {name:'My Sprints',exact:true}).waitFor();
                    await opened.close();
                } else { assert.equal(await links.count(), 0); }
                for (const width of [1440, 768, 390, 320]) {
                    await page.setViewportSize({width, height: 1000});
                    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
                    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${name} Step ${step} overflow at ${width}`);
                    for (const item of await content.locator('ol > li').all()) {
                        const box = await item.boundingBox();
                        assert(box.x >= 0 && box.x + box.width <= width + 1, `${name} Step ${step} task list overflow at ${width}`);
                    }
                    const sizes = await page.locator('#se-step-title, .se-runner__content h3').evaluateAll(elements => elements.map(el => parseFloat(getComputedStyle(el).fontSize)));
                    assert(sizes[0] > sizes[1] && sizes[1] === sizes[2], 'Section headings must remain below the Step title');
                    assert.equal(await content.locator('h3').first().evaluate(el => getComputedStyle(el).color), await content.locator('p').first().evaluate(el => getComputedStyle(el).color));
                    const action = await page.locator('#se-runner-action').boundingBox();
                    assert(action.width >= 44 && action.height >= 44);
                    if (step === 1 && [1440, 320].includes(width)) { await page.screenshot({path: `${output}/${name}-step-${width}.png`, fullPage: true}); }
                }
                if (step === 1) {
                    await page.screenshot({path: `${output}/${name}-mobile.png`, fullPage: true});
                    await page.goto(new URL('sprint-engine/dashboard/', fixture.home).href);
                    assert(await page.getByRole('link', {name: 'Continue Sprint', exact: true}).isVisible());
                    await Promise.all([page.waitForNavigation(), page.getByRole('link', {name: 'Continue Sprint', exact: true}).click()]);
                    assert(await page.getByText('Step 1 of 6', {exact: true}).isVisible());
                }
                await page.locator('#se-runner-action').focus();
                console.log(`Checking ${name} Step ${step}`);
                await Promise.all([page.waitForNavigation(), page.locator('#se-runner-action').press('Enter')]);
            }
            assert(await page.getByText('Sprint complete', {exact: true}).isVisible());
            assert(await page.getByText("You've built your first Sprint — congratulations!", {exact:true}).isVisible());
            assert(await page.getByText('Coming soon: Sprint Engine Pro', {exact:false}).isVisible());
            page.once('dialog', dialog => dialog.accept());
            await Promise.all([page.waitForNavigation(), page.locator('#se-runner-action').click()]);
            assert(await page.getByText('Step 1 of 6', {exact: true}).isVisible());
            await page.setViewportSize({width: 1440, height: 1000});
            for (const stepId of steps) {
                const editor = new URL(`wp-admin/post.php?post=${stepId}&action=edit`, fixture.home).href;
                await page.goto(editor);
                await page.waitForFunction(id => window.wp?.data?.select('core/editor')?.getCurrentPostId() === id && wp.data.select('core/block-editor').getBlocks().length >= 5, stepId);
                const inspect = () => page.evaluate(() => {
                    const blocks = wp.data.select('core/block-editor').getBlocks();
                    const valid = items => items.every(b => b.isValid && valid(b.innerBlocks || []));
                    return {valid: valid(blocks), types: blocks.map(b => b.name), headings: blocks.filter(b => b.name === 'core/heading').map(b => ({level: b.attributes.level, text: String(b.attributes.content)}))};
                });
                const before = await inspect();
                assert(before.valid);
                assert.deepEqual(before.types.slice(0, 5), ['core/paragraph', 'core/heading', 'core/list', 'core/heading', 'core/paragraph']);
                assert.deepEqual(before.headings, [{level: 3, text: 'Your task'}, {level: 3, text: 'You’re ready to continue when…'}]);
                // Exercise the native editor's save path, then reopen the stored blocks.
                await page.evaluate(async () => {
                    const title = wp.data.select('core/editor').getEditedPostAttribute('title');
                    wp.data.dispatch('core/editor').editPost({title: `${title} — reviewed`});
                    await wp.data.dispatch('core/editor').savePost();
                    if (wp.data.select('core/editor').isEditedPostDirty()) throw new Error('Native Step save did not finish');
                });
                await page.goto(editor);
                await page.waitForFunction(id => window.wp?.data?.select('core/editor')?.getCurrentPostId() === id && wp.data.select('core/block-editor').getBlocks().length >= 5, stepId);
                assert.deepEqual(await inspect(), before);
                assert.equal(await page.getByText('This block contains unexpected or invalid content.').count(), 0);
                assert.equal(await page.evaluate(() => wp.data.select('core/notices').getNotices().filter(n => n.id === 'sprint-engine-tutorial-admin-only').length), 0);
            }

            const member = await browser.newContext(); await member.addCookies([fixture.member.cookie]);
            const denied = await member.newPage();
            const response = await denied.goto(runnerUrl); assert.equal(response.status(), 403);
            assert.equal(await denied.locator('#se-runner-action').count(), 0);
            assert(! (await denied.locator('body').innerText()).includes('Decide what your Sprint will achieve'));
            await denied.goto(new URL('sprint-engine/dashboard/', fixture.home).href);
            assert.equal(await denied.getByText('Build your first Sprint — Tutorial', {exact: true}).count(), 0);
            for (const [resource, id, action] of [['sprints', sprintId, 'start'], ['sprints', sprintId, 'restart'], ['steps', steps[0], 'complete']]) {
                const response = await member.request.post(new URL(`wp-json/sprint-engine/v1/${resource}/${id}/${action}`, fixture.home).href, {headers: {'X-WP-Nonce': fixture.member.nonce}, data: {expected_attempt_id: 1}});
                assert.equal(response.status(), 403);
            }
            const guest = await browser.newPage(); await guest.goto(runnerUrl); assert(guest.url().includes('wp-login.php'));
            assert.equal(await guest.locator('#se-runner-action').count(), 0);
            assert.deepEqual(errors, []);
            report.push({engine: name, version: browser.version(), wordpress: fixture.wordpress, steps: steps.length, widths: [1440, 768, 390, 320], outcome: 'passed'});
            console.log(`PASS: ${name} welcome/settings, Gutenberg notice/reopen, six-Step Runner, resume/restart, keyboard/responsive and guest/member denial.`);
        } finally { await browser.close(); }
    }
    fs.writeFileSync(`${output}/report.json`, JSON.stringify(report, null, 2));
})().catch(error => {console.error(error); process.exit(1);});
