// Run completion-followup-fixture.php first; rerun with no-extension after removing its callbacks.
const {chromium} = require('playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async () => {
  const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8').replace(/^\uFEFF/, ''));
  assert(['127.0.0.1', 'localhost'].includes(new URL(fixture.url).hostname));
  const extended = process.argv[3] !== 'no-extension';
  const output = process.env.SE_BROWSER_OUTPUT || '.tools/se018-browser';
  fs.mkdirSync(output, {recursive:true});
  const browser = await chromium.launch({channel:process.env.SE_BROWSER_CHANNEL || 'chrome', headless:true});
  try {
    for (const member of ['user', 'other']) {
      const context = await browser.newContext();
      await context.addCookies([fixture[member === 'user' ? 'cookie' : 'other_cookie']]);
      const page = await context.newPage();
      let writes = 0;
      page.on('request', request => { if (request.method() !== 'GET') ++writes; });
      for (const width of [1440,390]) {
        await page.setViewportSize({width,height:900});
        const target = new URL(fixture.urls.completed);
        target.searchParams.set('user_id', fixture[member === 'user' ? 'other' : 'user']);
        target.searchParams.set('attempt_id', fixture.completed[member === 'user' ? 'other' : 'user'].attempt_id);
        target.searchParams.set('sprint_id', fixture.ids.denied);
        const response = await page.goto(target.href);
        assert.equal(response.status(),200);
        assert(response.headers()['cache-control'].includes('private, no-store'));
        const completion = page.locator('.se-runner__completion');
        const controls = completion.locator('a,button');
        const expected = extended
          ? ['Your next step','Existing completion action','Restart Sprint','Back to My Sprints','Recommended next Sprint']
          : ['Your next step','Restart Sprint','Back to My Sprints'];
        assert.deepEqual(await controls.allTextContents(), expected);
        for (const selector of ['.runner_completed_actions','.runner_completed_followup_actions']) {
          assert.equal(await completion.locator(selector).count(),extended ? 1 : 0);
          if (extended) assert.deepEqual(JSON.parse(await completion.locator(selector).getAttribute('data-context')),fixture.completed[member]);
        }
        // Actual Tab navigation must follow creator CTA, old hook, Core controls, new hook.
        await controls.first().focus();
        for (let index=0;index<expected.length;index++) {
          assert(await controls.nth(index).evaluate(el => el === document.activeElement));
          assert(await controls.nth(index).isVisible());
          assert(await controls.nth(index).evaluate(el => getComputedStyle(el).outlineStyle !== 'none'));
          if (index+1<expected.length) await page.keyboard.press('Tab');
        }
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
        if (extended) {
          const back = await completion.getByRole('link',{name:'Back to My Sprints',exact:true}).boundingBox();
          const followup = await completion.locator('.runner_completed_followup_actions').boundingBox();
          assert(followup.y >= back.y + back.height);
        }
        await page.screenshot({path:`${output}/completion-${extended ? 'extended' : 'core'}-${member}-${width}.png`,fullPage:true});
      }
      for (const key of ['available','active','denied','unavailable']) {
        await page.goto(fixture.urls[key]);
        assert.equal(await page.locator('.runner_completed_actions,.runner_completed_followup_actions').count(),0);
      }
      assert.equal(writes,0);
      await context.close();
    }
    const anonymous = await browser.newPage();
    await anonymous.goto(fixture.urls.completed);
    assert(anonymous.url().includes('wp-login.php?redirect_to='));
    assert.equal(await anonymous.locator('.runner_completed_actions,.runner_completed_followup_actions').count(),0);
    console.log(`PASS: SE-018 ${extended ? 'both hooks' : 'no-extension'} actual Runner at 1440/390px, control order, keyboard focus, visible outlines, containment, member isolation, exclusion and zero HTTP writes.`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
