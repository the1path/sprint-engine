// Test the real Gutenberg control and private frontend on disposable loopback WordPress.
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
  const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8').replace(/^\uFEFF/, ''));
  const root = new URL(fixture.runner).origin;
  assert(['127.0.0.1', 'localhost'].includes(new URL(root).hostname));
  const output = process.env.SE_BROWSER_OUTPUT || '.tools/se023-browser';
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ channel: process.env.SE_BROWSER_CHANNEL || 'chrome', headless: true });
  try {
    const admin = await browser.newContext({ viewport: { width: 1440, height: 960 } });
    const second = await browser.newContext();
    const member = await browser.newContext();
    const anonymous = await browser.newContext();
    await admin.addCookies(fixture.adminCookies);
    await second.addCookies(fixture.secondCookies);
    await member.addCookies(fixture.memberCookies);
    for (const context of [admin, second]) {
      await context.route('https://example.invalid/frame', route => route.fulfill({
        contentType: 'text/html', body: '<p>Embedded preview fixture</p>',
      }));
    }

    const editor = await admin.newPage();
    editor.setDefaultTimeout(60000);
    const button = editor.getByRole('menuitem', { name: /^Preview Step/ });
    async function openEditor(url) {
      await editor.goto(url, { waitUntil: 'domcontentloaded' });
      await editor.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPost()?.id);
      const welcome = editor.getByRole('dialog').getByRole('button', { name: /^Close( dialog)?$/ }).first();
      if (await welcome.waitFor({ timeout: 1500 }).then(() => true, () => false)) await welcome.click();
      await editor.waitForFunction(() => window.wp?.plugins?.getPlugin('sprint-engine-step-preview'));
    }

    function assertPrivate(headers) {
      assert.match(headers['cache-control'], /\bprivate\b/);
      assert.match(headers['cache-control'], /\bno-store\b/);
      assert.match(headers['x-robots-tag'], /\bnoindex\b/);
      assert.equal(headers['referrer-policy'], 'no-referrer');
    }

    async function preview() {
      await editor.bringToFront();
      const useView = editor.viewportSize().width >= 782 && await editor.evaluate(() => !!wp.editor.PluginPreviewMenuItem);
      await editor.getByRole('button', { name: useView ? 'View' : 'Options', exact: true }).click();
      await button.waitFor();
      const [tab] = await Promise.all([admin.waitForEvent('page', { timeout: 60000 }), button.click({ timeout: 15000 })]);
      await tab.waitForURL(url => url.searchParams.has('sprint_engine_step_preview'));
      const document = await tab.reload();
      assert.equal(document.status(), 200);
      assertPrivate(document.headers());
      assert.equal(await tab.evaluate(() => window.opener), null);
      assert.equal(await tab.locator('meta[name=robots]').first().getAttribute('content'), 'noindex, nofollow');
      assert(await tab.getByText('Preview Mode', { exact: true }).isVisible());
      assert.equal(await tab.locator('progress, #se-runner-action, #se-runner-restart, #se-restart-dialog').count(), 0);
      assert.equal(await tab.locator('script[src*="assets/js/runner.js"]').count(), 0);
      return tab;
    }

    async function changeEditor(title, paragraph) {
      const canvas = editor.frameLocator('iframe[name="editor-canvas"]');
      const titleField = editor.locator('.editor-post-title__input');
      const field = await titleField.count() ? titleField.first() : canvas.locator('.editor-post-title__input').first();
      await field.click();
      await field.press('ControlOrMeta+a');
      await field.pressSequentially(title);
      await editor.evaluate(text => {
        const block = wp.blocks.createBlock('core/paragraph', { content: text });
        return wp.data.dispatch('core/block-editor').insertBlocks(block, 0);
      }, paragraph);
      await editor.waitForFunction(({ title, paragraph }) => {
        const state = wp.data.select('core/editor');
        return state.isEditedPostDirty() && state.getEditedPostAttribute('title') === title && state.getEditedPostContent().includes(paragraph);
      }, { title, paragraph }, { timeout: 10000 });
    }

    await openEditor(fixture.draftEditor);
    const draft = await preview();
    assert(await draft.getByRole('heading', { name: 'SE-023 unpublished Sprint' }).isVisible());
    assert(await draft.getByRole('heading', { name: 'SE-023 draft Step' }).isVisible());
    assert(await draft.getByText('Step 1 of 1', { exact: true }).isVisible());
    assert(await draft.getByText('Saved draft instructions.').isVisible());
    assert(await draft.getByText('Showing saved Step content and settings.').isVisible());
    await draft.close();
    await changeEditor('SE-023 draft edit', 'Unsaved draft paragraph.');
    const draftEdit = await preview();
    assert.equal(await draftEdit.locator('#se-step-title').innerText(), 'SE-023 draft edit');
    assert(await draftEdit.getByText('Unsaved draft paragraph.').isVisible());
    await draftEdit.close();
    console.log('PASS: Saved and edited draft previews under a draft Sprint.');

    await openEditor(fixture.publishedEditor);
    const published = await preview();
    const savedUrl = published.url();
    assert(await published.getByRole('heading', { name: 'SE-023 published Sprint' }).isVisible());
    assert(await published.getByRole('heading', { name: 'SE-023 published Step' }).isVisible());
    for (const label of ['Saved Step instructions.', 'Reflect', 'About 9 minutes', 'Step 1 of 2', 'Choose one change.', 'Put this into practice before completing this Step.']) {
      assert(await published.getByText(label, { exact: true }).isVisible(), label);
    }
    assert.equal(await published.locator('.se-runner__logo').count(), 1);
    if (fixture.proActive) {
      assert(await published.getByText('Workspace response unavailable.', { exact: true }).isVisible());
      assert.equal(await published.locator('.sprint-engine-pro-workspace, .sprint-engine-pro-workspace-save').count(), 0);
      assert.doesNotMatch(await published.locator('main').innerText(), /Private saved participant response/);
    }
    assert.equal(await published.locator('body').evaluate(el => getComputedStyle(el).getPropertyValue('--se-primary').trim()), '#125c55');
    for (const alt of ['Preview banner', 'Preview block image']) {
      await published.getByAltText(alt).last().evaluate(image => image.decode());
    }
    assert(await published.frameLocator('iframe[title="Preview embed"]').getByText('Embedded preview fixture').isVisible());
    const audio = published.locator('audio');
    assert(await audio.getAttribute('controls') !== null);
    await audio.evaluate(async player => {
      player.muted = true;
      await player.play();
      await new Promise(resolve => player.addEventListener('ended', resolve, { once: true }));
      if (!player.duration || player.error) throw Error('Native audio did not play.');
    });
    for (const width of [1440, 768, 390]) {
      await published.setViewportSize({ width, height: 960 });
      assert(await published.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
      for (const selector of ['.se-runner__featured-image', '.wp-block-image img', 'audio', 'iframe']) {
        assert(await published.locator(selector).evaluate(el => el.getBoundingClientRect().right <= innerWidth));
      }
      await published.screenshot({ path: path.join(output, `preview-${width}.png`), fullPage: true });
    }
    await published.close();
    console.log('PASS: Published Task Step uses Runner branding, metadata, blocks and media at 1440/768/390px.');

    await changeEditor('SE-023 private editor title', 'Private unsaved Step paragraph.');
    const privatePreview = await preview();
    assert(await privatePreview.getByRole('heading', { name: 'SE-023 private editor title' }).isVisible());
    assert(await privatePreview.getByText('Private unsaved Step paragraph.').isVisible());
    assert(await privatePreview.getByText('Showing autosaved title and content.', { exact: false }).isVisible());
    const privateUrl = new URL(privatePreview.url());
    const firstAutosave = privateUrl.searchParams.get('sprint_engine_preview_autosave');
    assert.match(firstAutosave, /^\d+$/);
    const savedResponse = await admin.request.get(savedUrl);
    assert.doesNotMatch(await savedResponse.text(), /Private unsaved Step paragraph/);

    const otherEditor = await second.newPage();
    otherEditor.setDefaultTimeout(60000);
    await otherEditor.goto(fixture.publishedEditor, { waitUntil: 'domcontentloaded' });
    await otherEditor.waitForFunction(() => window.sprintEngineStepPreview && window.wp?.apiFetch);
    const other = await otherEditor.evaluate(async id => {
      const result = await wp.apiFetch({
        path: `/wp/v2/sprint_engine_step/${id}/autosaves`, method: 'POST',
        data: { title: 'SE-023 second editor title', content: '<!-- wp:paragraph --><p>Other private draft.</p><!-- /wp:paragraph -->' },
      });
      return { url: sprintEngineStepPreview.url, autosave: result.id };
    }, fixture.publishedId);

    const otherUrl = new URL(other.url);
    otherUrl.searchParams.set('sprint_engine_preview_autosave', other.autosave);
    const otherOwnPreview = await second.request.get(otherUrl.href);
    assert.equal(otherOwnPreview.status(), 200);
    assert.match(await otherOwnPreview.text(), /Other private draft/);
    const otherForged = new URL(other.url);
    otherForged.searchParams.set('sprint_engine_preview_autosave', firstAutosave);
    const wrongNonce = new URL(privateUrl);
    wrongNonce.searchParams.delete('_wpnonce');
    const wrongStep = new URL(privateUrl);
    wrongStep.searchParams.set('sprint_engine_step_preview', fixture.draftId);
    const wrongAutosave = new URL(privateUrl);
    wrongAutosave.searchParams.set('sprint_engine_preview_autosave', other.autosave);
    const missingCapability = new URL(privateUrl);
    missingCapability.searchParams.set('sprint_engine_test_deny_edit', '1');
    for (const [context, url, status] of [
      [anonymous, privateUrl, 403], [member, privateUrl, 403], [second, privateUrl, 403],
      [admin, wrongNonce, 403], [admin, wrongStep, 403], [admin, wrongAutosave, 404],
      [second, otherForged, 404], [admin, missingCapability, 403],
    ]) {
      const response = await context.request.get(url.href, { maxRedirects: 0 });
      assert.equal(response.status(), status);
      assertPrivate(response.headers());
      assert.doesNotMatch(await response.text(), /Private unsaved Step paragraph|Other private draft|Saved Step instructions/);
    }
    for (const context of [anonymous, member]) {
      const endpoint = `${root}/wp-json/wp/v2/sprint_engine_step/${fixture.publishedId}`;
      assert([401, 403].includes((await context.request.get(endpoint)).status()));
      assert([401, 403].includes((await context.request.get(`${endpoint}/autosaves`)).status()));
      const publicPost = await context.request.get(`${root}/?p=${fixture.publishedId}`);
      assert.doesNotMatch(await publicPost.text(), /Saved Step instructions|Private unsaved Step paragraph/);
    }
    await privatePreview.reload();
    assert(await privatePreview.getByText('Private unsaved Step paragraph.').isVisible());
    assert.equal(await privatePreview.getByText('Other private draft.').count(), 0);
    await privatePreview.close();
    console.log('PASS: Published autosaves are private to the current administrator and exact Step.');

    await editor.setViewportSize({ width: 390, height: 960 });
    await openEditor(fixture.orphanEditor);
    const orphan = await preview();
    assert(await orphan.getByRole('heading', { level: 1, name: 'SE-023 Step without a parent' }).isVisible());
    assert(await orphan.getByText('Standalone draft instructions.').isVisible());
    assert.equal(await orphan.getByText(/^Step \d+ of \d+$/).count(), 0);
    await orphan.close();
    await editor.goto(fixture.sprintEditor, { waitUntil: 'domcontentloaded' });
    await editor.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostType() === 'sprint_engine_sprint');
    assert.equal(await editor.locator('script[src*="assets/js/step-preview.js"]').count(), 0);
    const runner = await admin.newPage();
    const memberRoute = new URL(fixture.runner);
    memberRoute.search = privateUrl.search;
    assert.equal((await runner.goto(memberRoute.href)).status(), 200);
    assert(await runner.getByText('Ready to start').isVisible());
    assert(await runner.getByRole('button', { name: 'Start Sprint', exact: true }).isVisible());
    assert.equal(await runner.getByText('Preview Mode', { exact: true }).count(), 0);
    console.log('PASS: Standalone Step preview and canonical member Runner route remain separate.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
