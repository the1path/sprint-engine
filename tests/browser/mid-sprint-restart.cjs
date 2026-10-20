// Run against a fresh disposable fixture from mid-sprint-restart-fixture.php.
const {chromium} = require('playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async()=>{
 const fixture=JSON.parse(fs.readFileSync(process.argv[2],'utf8').replace(/^\uFEFF/,''));
 assert(['127.0.0.1','localhost'].includes(new URL(fixture.url).hostname));
 const browser=await chromium.launch({channel:process.env.SE_BROWSER_CHANNEL||'chrome',headless:true});
 try {
  const context=await browser.newContext();await context.addCookies([fixture.cookie]);
  const page=await context.newPage();const old=await context.newPage();
  const output=process.env.SE_BROWSER_OUTPUT||'.tools/se019-browser';fs.mkdirSync(output,{recursive:true});
  await page.goto(fixture.url);assert.equal(await page.locator('#se-runner-restart').count(),0);
  await Promise.all([page.waitForNavigation(),page.locator('#se-runner-action').click()]);
  await old.goto(fixture.url);
  const source=await old.evaluate(()=>sprintEngineRunner.attemptId);
  let writes=0;page.on('request',r=>{if(r.method()==='POST')writes++;});
  for(const width of [1440,390]) {
   await page.setViewportSize({width,height:900});await page.locator('#se-runner-restart').click();
   const dialog=page.getByRole('dialog');assert(await dialog.isVisible());
   assert(await dialog.getByRole('button',{name:'Cancel',exact:true}).evaluate(el=>el===document.activeElement));
   assert((await dialog.innerText()).includes('Your current run and previously saved work will be preserved in History'));
   assert((await dialog.innerText()).includes('Any unsaved responses will be lost.'));
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   const rect=await dialog.boundingBox();assert(rect.x>=0&&rect.x+rect.width<=width);
   await page.screenshot({path:`${output}/restart-dialog-${width}.png`,fullPage:true});
   await dialog.getByRole('button',{name:'Cancel',exact:true}).click();assert.equal(writes,0);
   await page.locator('#se-runner-restart').click();await page.keyboard.press('Escape');assert(!(await dialog.isVisible()));assert.equal(writes,0);
   assert(await page.locator('#se-runner-restart').evaluate(el=>el===document.activeElement));
  }
  // Preserve a genuine completed source Step, while an older tab still displays Step 1.
  await Promise.all([page.waitForNavigation(),page.locator('#se-runner-action').click()]);
  assert(await page.getByText('Step 2 of 6',{exact:true}).isVisible());
  const confirmRestart=async()=>{
   await page.locator('#se-runner-restart').click();
   await Promise.all([page.waitForNavigation(),page.getByRole('dialog').getByRole('button',{name:'Restart',exact:true}).click()]);
   assert.equal(page.url(),fixture.url);assert(await page.getByText('Step 1 of 6',{exact:true}).isVisible());
   assert(await page.getByText('0 of 6 complete — 0%',{exact:true}).isVisible());
  };
  await confirmRestart();assert.equal(writes,2);
  const replacement=await page.evaluate(()=>sprintEngineRunner.attemptId);assert.notEqual(source,replacement);
  // Real same-Step stale completion must return 409; both server and browser retain state.
  const staleResponse=old.waitForResponse(r=>r.url().endsWith('/complete')&&r.request().method()==='POST');
  await old.locator('#se-runner-action').click();assert.equal((await staleResponse).status(),409);
  await old.getByText(/The request could not be confirmed/).waitFor();assert.equal(await old.evaluate(()=>sprintEngineRunner.attemptId),source);
  await page.reload();assert(await page.getByText('0 of 6 complete — 0%',{exact:true}).isVisible());
  // An old restart also conflicts without creating Attempt 3.
  const staleRestart=old.waitForResponse(r=>r.url().endsWith('/restart')&&r.request().method()==='POST');
  await old.locator('#se-runner-restart').click();await old.getByRole('dialog').getByRole('button',{name:'Restart',exact:true}).click();assert.equal((await staleRestart).status(),409);
  await page.reload();assert.equal(await page.evaluate(()=>sprintEngineRunner.attemptId),replacement);
  // Retry a network/server error after a fresh confirmation using the same source identity.
  const endpoint=await page.evaluate(()=>sprintEngineRunner.restartEndpoint);
  await page.route(endpoint,r=>r.fulfill({status:503,contentType:'application/json',body:'{}'}));
  await page.locator('#se-runner-restart').click();await page.getByRole('dialog').getByRole('button',{name:'Restart',exact:true}).click();
  await page.getByText(/The request could not be confirmed/).waitFor();assert(await page.locator('#se-runner-restart').isEnabled());assert(await page.locator('#se-runner-action').isEnabled());
  await page.unroute(endpoint);await confirmRestart();
  await page.getByRole('link',{name:'Save & Exit',exact:true}).click();assert(page.url().includes('/sprint-engine/dashboard/'));
  await page.goto(fixture.url);assert(await page.getByText('Step 1 of 6',{exact:true}).isVisible());
  for(let i=0;i<6;i++)await Promise.all([page.waitForNavigation(),page.locator('#se-runner-action').click()]);
  assert(await page.getByRole('heading',{name:'Sprint complete',exact:true}).isVisible());assert.equal(await page.locator('#se-runner-restart').count(),0);
  assert.equal(await page.getByRole('button',{name:'Restart Sprint',exact:true}).count(),1);
  console.log('PASS: Desktop/mobile dialog layout and focus; Cancel/Escape zero writes; mid-Sprint restart, same-Step stale completion/restart conflicts, failure/retry, exit/resume and completion.');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
