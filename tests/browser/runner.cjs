// Run after fixture.php on a disposable loopback WordPress site.
// NODE_PATH may point to an existing Playwright installation; no runtime dependency.
const { chromium } = require('playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async () => {
  const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8').replace(/^\uFEFF/, ''));
  assert(['127.0.0.1', 'localhost'].includes(new URL(fixture.url).hostname));
  const browser = await chromium.launch({ channel: process.env.SE_BROWSER_CHANNEL || 'chrome', headless: true });
  try {
    const context = await browser.newContext();
    await context.addCookies([fixture.cookie]);
    const page = await context.newPage();
    const secondContext = await browser.newContext();
    await secondContext.addCookies([fixture.second_cookie]);
    const secondPage = await secondContext.newPage();
    const anonymous = await browser.newPage();
    await anonymous.goto(fixture.url);
    assert(anonymous.url().includes('wp-login.php?redirect_to='));
    await anonymous.close();
    // Stable local layout fixtures. These checks do not claim external media playback.
    await page.route('https://example.invalid/**', route => route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900"><rect width="1600" height="900" fill="#dceae1"/></svg>'}));
    await page.route('https://www.youtube.com/**', route => route.fulfill({contentType:'text/html',body:'<body style="background:#203630;color:white">Video layout fixture</body>'}));
    const output = process.env.SE_BROWSER_OUTPUT || '.tools/se006-browser';
    fs.mkdirSync(output, {recursive:true});
    await page.goto(fixture.url);
    await page.keyboard.press('Tab');
    assert(await page.locator('.se-runner__skip').evaluate(el => el === document.activeElement));
    await page.keyboard.press('Enter');
    assert(await page.locator('#se-runner-main').evaluate(el => el === document.activeElement));
    for (let state = 0; state <= 7; ++state) {
      await secondPage.goto(fixture.url);
      assert(await secondPage.getByText(state < 3 ? 'Ready to start' : 'Step 1 of 6', {exact:true}).isVisible());
      if (state < 7) assert.notEqual(await secondPage.evaluate(() => sprintEngineRunner.nonce), await page.evaluate(() => sprintEngineRunner.nonce));
      if (state === 2) {
        await Promise.all([secondPage.waitForNavigation(), secondPage.locator('#se-runner-action').click()]);
        assert(await secondPage.getByText('Step 1 of 6', {exact:true}).isVisible());
      }
      for (const width of [1440, 900, 390]) {
        await page.setViewportSize({width, height:900});
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Page overflow at state ${state}/${width}`);
        const banner = page.locator('.se-runner__featured-image');
        const hasBanner = state === 0 || state === 1 || state === 7;
        assert.equal(await banner.count(), hasBanner ? 1 : 0);
        if (hasBanner) {
          assert.equal(await banner.getAttribute('alt'), state === 1 ? 'Step banner' : 'Sprint banner');
          assert(await banner.evaluate(el => el.complete && el.naturalWidth > 0));
          assert.equal(await banner.evaluate(el => getComputedStyle(el).objectFit), 'cover');
          assert.equal(await banner.evaluate(el => getComputedStyle(el).borderRadius), await page.locator('.se-runner__main').evaluate(el => getComputedStyle(el).borderRadius));
          const box = await banner.boundingBox();
          assert(box.x >= 0 && box.x + box.width <= width);
          assert(Math.abs(box.width / box.height - (width <= 480 ? 16/10 : 16/7)) < .02);
        }
        if (state === 0) assert(await page.getByText('Estimated time: 1.5 hours', {exact:true}).isVisible());
        const action = page.locator('#se-runner-action');
        if (state < 7) {
          const box = await action.boundingBox();
          assert(box.height >= 44 && box.width >= 44);
          assert.equal(await action.innerText(), state === 0 ? 'Start Sprint' : state === 6 ? 'Complete Sprint' : 'Complete & Continue');
          await page.mouse.move(0, 0);
          assert.equal(await action.evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(32, 91, 72)');
          assert.equal(await action.evaluate(el => getComputedStyle(el).color), 'rgb(255, 255, 255)');
          assert.equal(await action.evaluate(el => getComputedStyle(el).appearance), 'none');
          await action.hover();
          assert.equal(await action.evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(22, 67, 51)');
          await page.mouse.move(0, 0);
          const exit = page.locator('.se-runner__utility .se-runner__button--secondary');
          assert.equal(await exit.evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(238, 244, 240)');
          await action.focus();
          assert(await action.evaluate(el => getComputedStyle(el).outlineStyle !== 'none'));
        } else {
          assert.equal(await action.innerText(), 'Restart Sprint');
          const box = await action.boundingBox();assert(box.height >= 44 && box.width >= 44);
          assert.equal(await action.evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(238, 244, 240)');
          assert(await page.getByRole('heading', {name:'Sprint complete', exact:true}).isVisible());
          assert.equal(await page.locator('progress').getAttribute('value'), '100');
        }
        if (state > 0 && state < 7) assert(await page.getByText(`Step ${state} of 6`, {exact:true}).isVisible());
        if (state === 3) {
          for (const selector of ['audio', 'video', 'iframe', 'object', '.wp-block-image', '.wp-block-table', 'pre']) {
            for (const el of await page.locator('.se-runner__content ' + selector).all()) {
              const box = await el.boundingBox();
              assert(box.x >= 0 && box.x + box.width <= width + 1, `${selector} overflows ${width}`);
            }
          }
        }
        await page.screenshot({path:`${output}/state-${state}-${width}.png`,fullPage:true});
      }
      assert.equal(await page.getByText('Continue where you left off.',{exact:true}).count(),0);
      if (state === 1) {
        let release;
        const paused = new Promise(resolve => { release = resolve; });
        const endpoint = await page.evaluate(() => sprintEngineRunner.endpoint);
        await page.route(endpoint, async route => {
          await paused;
          await route.fulfill({status:503, contentType:'application/json', body:'{"code":"fixture_failure"}'});
        });
        await page.locator('#se-runner-action').click();
        assert.equal(await page.locator('#se-runner-action').getAttribute('aria-busy'), 'true');
        assert(await page.locator('#se-runner-action').isDisabled());
        assert.equal(await page.locator('#se-runner-action').evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(97, 116, 107)');
        assert.equal(await page.locator('#se-runner-status').innerText(), 'Saving progress…');
        release();
        await page.waitForFunction(() => !document.getElementById('se-runner-action').disabled);
        assert((await page.locator('#se-runner-status').innerText()).includes('Try again'));
        assert(await page.getByText('Step 1 of 6', {exact:true}).isVisible());
        await page.screenshot({path:`${output}/recoverable-error-390.png`,fullPage:true});
        await page.unroute(endpoint);
      }
      if (state === 2) {
        await page.getByRole('link', {name:'Save & Exit',exact:true}).click();
        await page.goto(fixture.url);
        assert(await page.getByText('Step 2 of 6', {exact:true}).isVisible());
        const restarted = await chromium.launch({channel: process.env.SE_BROWSER_CHANNEL || 'chrome', headless:true});
        try {
          const resumed = await restarted.newContext({storageState:await context.storageState()});
          const resumedPage = await resumed.newPage();
          await resumedPage.goto(fixture.url);
          assert(await resumedPage.getByText('Step 2 of 6', {exact:true}).isVisible());
        } finally { await restarted.close(); }
      }
      if (state < 7) {
        await page.locator('#se-runner-action').focus();
        await Promise.all([page.waitForNavigation(), page.keyboard.press('Enter')]);
        assert.equal(page.url(), fixture.url);
      }
    }
    assert(await page.getByText('You made it.',{exact:true}).isVisible());
    const cta=page.getByRole('link',{name:'Your next step',exact:true});
    assert((await cta.getAttribute('class')).includes('se-runner__button--primary'));
    let writes=0; page.on('request',request=>{if(request.method()==='POST') writes++;});
    await cta.click(); await page.waitForURL(/se-completion-next=1/);
    await page.goto(fixture.url);
    assert(await page.getByRole('heading',{name:'Sprint complete',exact:true}).isVisible());
    assert.equal(writes,0);
    // Cancel first: no POST or busy feedback. Then restart and finish Attempt 2.
    page.once('dialog',dialog=>{assert(dialog.message().includes('previous completed attempt will be kept'));dialog.dismiss();});
    await page.getByRole('button',{name:'Restart Sprint',exact:true}).click();
    assert.equal(writes,0);assert(await page.getByRole('heading',{name:'Sprint complete',exact:true}).isVisible());
    assert.equal(await page.locator('#se-runner-status').innerText(),'');assert(!(await page.locator('#se-runner-action').isDisabled()));
    page.once('dialog',dialog=>dialog.accept());
    let restartedState;const restartEndpoint=await page.evaluate(()=>sprintEngineRunner.endpoint);
    await page.route(restartEndpoint,async route=>{const response=await route.fetch();restartedState=await response.json();await route.fulfill({response});});
    await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Restart Sprint',exact:true}).click()]);
    await page.unroute(restartEndpoint);assert.equal(restartedState.attempt_number,2);assert.equal(restartedState.progress.completed_steps,0);
    assert.equal(page.url(),fixture.url);assert(await page.getByText('Step 1 of 6',{exact:true}).isVisible());assert(await page.getByText('0 of 6 complete — 0%',{exact:true}).isVisible());
    await Promise.all([page.waitForNavigation(),page.locator('#se-runner-action').click()]);
    await page.getByRole('link',{name:'Save & Exit',exact:true}).click();await page.goto(fixture.url);
    assert(await page.getByText('Step 2 of 6',{exact:true}).isVisible());assert(await page.getByText('1 of 6 complete — 17%',{exact:true}).isVisible());
    for(let step=2;step<=6;step++) await Promise.all([page.waitForNavigation(),page.locator('#se-runner-action').click()]);
    assert(await page.getByRole('heading',{name:'Sprint complete',exact:true}).isVisible());assert(await page.getByText('You made it.',{exact:true}).isVisible());assert(await page.getByRole('link',{name:'Your next step',exact:true}).isVisible());assert(await page.getByRole('button',{name:'Restart Sprint',exact:true}).isVisible());
    for(const width of [1440,390]) {await page.setViewportSize({width,height:900});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:output+'/attempt-2-completed-'+width+'.png',fullPage:true});}
    console.log('PASS: Restart confirmation cancel/accept, Attempt 2 zero progress, canonical URL, exit/resume and completion; desktop/mobile completion.');
    console.log('PASS: Eight Runner states at 1440/900/390px; computed button styles, independent users/nonces, browser restart, keyboard journey, exit/resume, canonical URL, focus, touch targets, media containment, custom completion CTA without writes.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
