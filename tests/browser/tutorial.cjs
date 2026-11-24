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
    for (const [index, [name, engine]] of Object.entries({chromium, firefox, webkit}).entries()) {
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
            runnerUrl = await page.getByRole('link', {name: 'Open Tutorial Sprint', exact: true}).getAttribute('href');
            await page.reload();
            assert.equal(await page.getByRole('button', {name: 'Install Tutorial Sprint', exact: true}).count(), 0);
            await page.getByRole('link', {name: 'Edit Tutorial Sprint', exact: true}).click();
            await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId());
            sprintId = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());
            await page.waitForFunction(() => wp.data.select('core/notices').getNotices().some(n => n.id === 'sprint-engine-tutorial-admin-only'));
            await page.locator('.components-notice__content').filter({hasText: 'This is the Sprint Engine Tutorial Sprint — administrators only'}).waitFor({state: 'visible'});
            const guide = page.getByRole('dialog').filter({hasText: 'Welcome to the editor'});
            if (await guide.count()) { await guide.getByRole('button', {name: 'Close', exact: true}).click(); }
            assert.equal(await page.evaluate(() => wp.data.select('core/notices').getNotices().find(n => n.id === 'sprint-engine-tutorial-admin-only').isDismissible), false);
            assert(await page.evaluate(() => wp.data.select('core/block-editor').getBlocks().every(b => b.isValid)));
            await page.screenshot({path: `${output}/${name}-editor.png`, fullPage: true});
            await page.goto(runnerUrl);
            assert(await page.getByText('Ready to start', {exact: true}).isVisible());
            await Promise.all([page.waitForNavigation(), page.locator('#se-runner-action').click()]);
            const steps = [];
            for (let step = 1; step <= 6; ++step) {
                assert(await page.getByText(`Step ${step} of 6`, {exact: true}).isVisible());
                assert(await page.getByText(step === 6 ? 'Before completing this tutorial:' : 'Before continuing:', {exact: false}).isVisible());
                const endpoint = await page.evaluate(() => sprintEngineRunner.endpoint);
                steps.push(Number(endpoint.match(/steps\/(\d+)/)[1]));
                for (const width of [1440, 768, 390, 320]) {
                    await page.setViewportSize({width, height: 1000});
                    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${name} Step ${step} overflow at ${width}`);
                    const action = await page.locator('#se-runner-action').boundingBox();
                    assert(action.width >= 44 && action.height >= 44);
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
            page.once('dialog', dialog => dialog.accept());
            await Promise.all([page.waitForNavigation(), page.locator('#se-runner-action').click()]);
            assert(await page.getByText('Step 1 of 6', {exact: true}).isVisible());
            await page.setViewportSize({width: 1440, height: 1000});
            await page.goto(new URL(`wp-admin/post.php?post=${steps[0]}&action=edit`, fixture.home).href);
            await page.waitForFunction(() => window.wp?.data?.select('core/block-editor')?.getBlocks().length > 0);
            assert(await page.evaluate(() => wp.data.select('core/block-editor').getBlocks().every(b => b.isValid)));
            assert.equal(await page.getByText('This block contains unexpected or invalid content.').count(), 0);
            assert.equal(await page.evaluate(() => wp.data.select('core/notices').getNotices().filter(n => n.id === 'sprint-engine-tutorial-admin-only').length), 0);

            const member = await browser.newContext(); await member.addCookies([fixture.member.cookie]);
            const denied = await member.newPage();
            const response = await denied.goto(runnerUrl); assert.equal(response.status(), 403);
            assert.equal(await denied.locator('#se-runner-action').count(), 0);
            assert(! (await denied.locator('body').innerText()).includes('Choose one useful outcome'));
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
