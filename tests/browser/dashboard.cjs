const {chromium}=require('playwright');
const fs=require('node:fs');
const assert=require('node:assert/strict');
(async()=>{
 const fixture=JSON.parse(fs.readFileSync(process.argv[2],'utf8').replace(/^\uFEFF/,''));
 assert(['localhost','127.0.0.1'].includes(new URL(fixture.url).hostname));
 const browser=await chromium.launch({channel:process.env.SE_BROWSER_CHANNEL||'chrome',headless:true});
 const output=process.env.SE_BROWSER_OUTPUT||'.tools/se013-browser';fs.mkdirSync(output,{recursive:true});
 try {
  const ctx=await browser.newContext();await ctx.addCookies([fixture.cookie]);const page=await ctx.newPage();
  const card=key=>page.locator('article').filter({has:page.locator(`#se-dashboard-title-${fixture.ids[key]}`)});
  const assertDashboard=async()=>{
   await page.goto(fixture.url);assert(await page.getByRole('heading',{name:'My Sprints',exact:true}).isVisible());
   assert.deepEqual(await page.locator('section > h2').allTextContents(),['In Progress','Available','Completed']);
   assert.equal(await page.locator('article').count(),3);assert.equal(await page.getByText('SE013 inaccessible private title').count(),0);assert.equal(await page.getByText('SE013 not launchable').count(),0);
   const html=await page.content();assert(!html.includes(fixture.urls.denied));assert(!html.includes(`se-dashboard-title-${fixture.ids.denied}`));
  };
  if(process.argv[3]==='brand') {
   await assertDashboard();await page.setViewportSize({width:390,height:1000});
   assert.equal(await page.locator('body').evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(233, 224, 240)');
   assert.equal(await page.locator('body').evaluate(el=>getComputedStyle(el).color),'rgb(25, 16, 32)');
   assert.equal(await card('active').evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(255, 250, 250)');
   assert.equal(await card('active').evaluate(el=>getComputedStyle(el).borderRadius),'24px');
   const button=card('active').getByRole('link',{name:'Continue Sprint'});
   assert.equal(await button.evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(72, 32, 112)');
   assert.equal(await button.evaluate(el=>getComputedStyle(el).color),'rgb(255, 255, 255)');
   assert.equal(await page.locator('.se-runner__logo').count(),1);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   await page.screenshot({path:`${output}/dashboard-branded-390.png`,fullPage:true});
   console.log('PASS: Dashboard uses saved primary/primary text/background/surface/text/corner/logo branding at 390px.');
  } else if(process.argv[3]==='read') {
   const anon=await browser.newPage();await anon.goto(fixture.url);assert(anon.url().includes('wp-login.php?redirect_to='));await anon.close();
   await assertDashboard();
   for(const width of [1440,390]) {
    await page.setViewportSize({width,height:1000});
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
    assert.equal(await card('active').locator('img').count(),0);assert.equal(await card('available').locator('img').count(),1);
    await card('available').locator('img').scrollIntoViewIfNeeded();
    await page.waitForFunction(id=>document.querySelector(`#se-dashboard-title-${id}`).closest('article').querySelector('img').complete&&document.querySelector(`#se-dashboard-title-${id}`).closest('article').querySelector('img').naturalWidth>0,fixture.ids.available);
    assert(await card('available').locator('img').evaluate(el=>el.complete&&el.naturalWidth>0));
    assert.equal(await card('active').locator('progress').getAttribute('value'),'50');assert(await card('active').getByText('1 of 2 complete — 50%').isVisible());
    const target=card('active').getByRole('link',{name:'Continue Sprint'});await target.focus();
    assert(await target.evaluate(el=>el===document.activeElement));assert.equal(await target.evaluate(el=>getComputedStyle(el).outlineStyle),'solid');assert.equal(await target.evaluate(el=>getComputedStyle(el).outlineWidth),'3px');
    assert((await target.boundingBox()).height>=44);
    if(width===390)assert.equal(await page.locator('.se-dashboard__grid').first().evaluate(el=>getComputedStyle(el).gridTemplateColumns.split(' ').length),1);
    if(width===1440)assert.equal(await page.locator('.se-dashboard__grid').first().evaluate(el=>getComputedStyle(el).gridTemplateColumns.split(' ').length),3);
    await page.screenshot({path:`${output}/dashboard-${width}.png`,fullPage:true});
   }
   await card('available').getByRole('link',{name:'Start Sprint',exact:true}).click();await page.getByText('Ready to start',{exact:true}).waitFor();assert.equal(page.url(),fixture.urls.available);assert(await page.getByText('Ready to start',{exact:true}).isVisible());
   await page.getByRole('link',{name:'Back to My Sprints'}).click();await assertDashboard();
   const other=await browser.newContext();await other.addCookies([fixture.other_cookie]);const second=await other.newPage();await second.goto(fixture.url);
   assert.deepEqual(await second.locator('section > h2').allTextContents(),['Available']);assert.equal(await second.locator('article').count(),3);await other.close();
   console.log('PASS: Dashboard read/Available link; auth and denied-content isolation; all three sections; image/no-image, progress, focus and responsive layout at 1440/390px.');
  } else {
   const width=Number(process.argv[4]||390);await assertDashboard();await page.setViewportSize({width,height:1000});
   await card('active').getByRole('link',{name:'Continue Sprint'}).click();await page.getByText('Step 2 of 2',{exact:true}).waitFor();assert.equal(page.url(),fixture.urls.active);assert(await page.getByText('Step 2 of 2',{exact:true}).isVisible());
   await page.getByRole('link',{name:'Save & Exit'}).click();await card('active').getByText('1 of 2 complete — 50%').waitFor();assert.equal(page.url(),fixture.url);assert(await card('active').getByText('1 of 2 complete — 50%').isVisible());
   await card('completed').getByRole('link',{name:'View Completed Sprint'}).click();await page.getByText('Sprint complete',{exact:true}).waitFor();assert(await page.getByText('Sprint complete',{exact:true}).isVisible());assert(await page.getByText('A useful step forward.').isVisible());assert(await page.getByRole('link',{name:'Your next step'}).isVisible());
   await page.getByRole('link',{name:'Back to My Sprints'}).first().click();await page.getByRole('heading',{name:'My Sprints',exact:true}).waitFor();assert.equal(page.url(),fixture.url);
   let requests=0;page.on('request',request=>{if(request.url().endsWith('/restart'))requests++;});
   const restart=card('completed').getByRole('button',{name:'Restart Sprint'});
   page.once('dialog',dialog=>dialog.dismiss());await restart.click();assert.equal(requests,0);assert(await restart.isEnabled());assert.equal(await card('completed').locator('[role=status]').textContent(),'');
   // A rejected response stays scoped and usable, then retries the existing backend.
   const endpoint=await restart.getAttribute('data-endpoint');await page.route(endpoint,route=>route.fulfill({status:503,contentType:'application/json',body:'{"success":false}'}));
   page.once('dialog',dialog=>dialog.accept());await restart.click();await page.waitForFunction(()=>!document.querySelector('[data-sprint-engine-restart]').disabled);assert(await card('completed').getByText(/restart could not be confirmed/).isVisible());await page.unroute(endpoint);
   page.once('dialog',dialog=>dialog.accept());await Promise.all([page.waitForURL(fixture.urls.completed),restart.click()]);
   await page.getByText('Step 1 of 2',{exact:true}).waitFor();assert.equal(requests,2);assert(await page.getByText('Step 1 of 2',{exact:true}).isVisible());assert(await page.getByText('0 of 2 complete — 0%',{exact:true}).isVisible());
   await page.getByRole('link',{name:'Save & Exit'}).click();await page.getByRole('heading',{name:'My Sprints',exact:true}).waitFor();assert.equal(page.url(),fixture.url);
   assert.deepEqual(await page.locator('section > h2').allTextContents(),['In Progress','Available']);
   assert(await card('completed').getByRole('link',{name:'Continue Sprint'}).isVisible());assert(await card('completed').getByText('0 of 2 complete — 0%').isVisible());
   await page.screenshot({path:`${output}/after-restart-${width}.png`,fullPage:true});
   console.log('PASS: Continue/current Step/Save & Exit, View Completed/message/CTA/return, cancelled restart with zero requests, recoverable failure/retry, confirmed restart/first Step/zero progress and reclassification.');
  }
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
