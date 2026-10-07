const {chromium} = require('playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async()=>{
 const fixture=JSON.parse(fs.readFileSync(process.argv[2],'utf8').replace(/^\uFEFF/,''));
 assert(['localhost','127.0.0.1'].includes(new URL(fixture.url).hostname));
 const {variables}=require('../../assets/js/runner-settings.js');
 for(const model of fixture.models) assert.deepEqual(variables(model.config,{square:'0',subtle:'0.5rem',soft:'1rem',rounded:'1.5rem'}),model.variables);
 const browser=await chromium.launch({channel:process.env.SE_BROWSER_CHANNEL||'chrome',headless:true});
 try {
 const context=await browser.newContext({viewport:{width:1440,height:1000}}); await context.addCookies(fixture.cookies);
 const page=await context.newPage(); const runner=await context.newPage();
 const errors=[]; page.on('pageerror',error=>errors.push(error.message));
 async function styles(target,selector) { return target.locator(selector).evaluate(el=>{const s=getComputedStyle(el);return {background:s.backgroundColor,color:s.color,radius:s.borderRadius,accent:s.accentColor};}); }
 await runner.goto(fixture.runner);
 assert.equal((await styles(runner,'body')).background,'rgb(243, 246, 245)');
 assert.equal((await styles(runner,'body')).color,'rgb(32, 54, 48)');
 assert.equal((await styles(runner,'.se-runner__main')).background,'rgb(255, 255, 255)');
 assert.equal((await styles(runner,'.se-runner__main')).radius,'16px');
 assert.equal((await styles(runner,'.se-runner__button--primary')).background,'rgb(32, 91, 72)');
 assert.equal((await styles(runner,'progress')).accent,'rgb(39, 106, 85)');
 assert.equal(await runner.locator('.se-runner__logo').count(),0);
 await page.goto(fixture.url); await page.locator('#se-branding-preview').waitFor();
 // Missing save/reset nonce rejected by real WordPress handlers.
 let response=await context.request.post(new URL('admin-post.php',fixture.url).href,{form:{action:'sprint_engine_reset_branding'}}); assert.equal(response.status(),403);
 response=await context.request.post(new URL('options.php',fixture.url).href,{form:{option_page:'sprint_engine_branding',action:'update','sprint_engine_runner_branding[primary]':'#abcdef'}}); assert.equal(response.status(),403);
 async function color(key,value) { const input=page.locator('#se-'+key); if(!await input.isVisible()) await input.locator('xpath=ancestor::div[contains(@class,"wp-picker-container")]').locator('.wp-color-result').click(); await input.fill(value); }
 for(const primary of ['#482070','#ffffcc']) {
   await color('primary',primary);
   await color('background','#e9e0f0');
   await color('surface','#fffafa');
   await color('text','#191020');
   await color('muted','#554460');
   await page.locator('#se-corner-style').selectOption('rounded');
   const preview=await styles(page,'#se-branding-preview .se-runner__button--primary');
   assert.equal(preview.color,primary==='#482070'?'rgb(255, 255, 255)':'rgb(0, 0, 0)');
   assert.equal((await styles(page,'#se-branding-preview .se-runner__main')).radius,'24px');
   await Promise.all([page.waitForURL(/settings-updated=true/),page.getByRole('button',{name:'Save Changes',exact:true}).click()]);
   await runner.reload();
   assert.deepEqual(await styles(runner,'.se-runner__button--primary'),preview);
   assert.equal((await styles(runner,'body')).background,'rgb(233, 224, 240)');
   assert.equal((await styles(runner,'.se-runner__main')).background,'rgb(255, 250, 250)');
   for(const width of [1440,390]) {
     await runner.setViewportSize({width,height:1000});
     assert(await runner.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
     await runner.locator('.se-runner__button--primary').focus();
     assert.notEqual(await runner.locator('.se-runner__button--primary').evaluate(el=>getComputedStyle(el).boxShadow),'none');
   }
 }
 await color('primary','#205b48');
 await color('primary_text','#abcdef');
 assert.equal((await styles(page,'#se-branding-preview .se-runner__button--primary')).color,'rgb(171, 205, 239)');
 await page.getByRole('button',{name:'Save Changes',exact:true}).click(); await page.waitForLoadState('load');
 await runner.reload(); assert.equal((await styles(runner,'.se-runner__button--primary')).color,'rgb(171, 205, 239)');
 await color('primary_text','#205b48');
 assert(await page.locator('#se-contrast-warning').isVisible());
 await page.getByRole('button',{name:'Save Changes',exact:true}).click(); await page.waitForLoadState('load');
 assert.equal(await page.locator('#se-primary_text').inputValue(),'#205b48');
 await color('primary_text','#205b48');
 await page.locator('#se-primary_text').locator('xpath=ancestor::div[contains(@class,"wp-picker-container")]').locator('.wp-picker-clear').click();
 assert.equal(await page.locator('#se-primary_text').inputValue(),'');
 assert.equal((await styles(page,'#se-branding-preview .se-runner__button--primary')).color,'rgb(255, 255, 255)');
 await page.getByRole('button',{name:'Save Changes',exact:true}).click(); await page.waitForLoadState('load');
 await runner.reload(); assert.equal((await styles(runner,'.se-runner__button--primary')).color,'rgb(255, 255, 255)');
 await page.locator('#se-choose-logo').click();
 await page.getByRole('tab',{name:'Media Library',exact:true}).click();
 const tile=page.locator('.attachments [data-id="'+fixture.logo+'"]'); await tile.waitFor(); await tile.click();
 await page.getByRole('button',{name:'Use logo',exact:true}).click();
 assert.equal(await page.locator('#se-preview-logo img').count(),1);
 await page.getByRole('button',{name:'Save Changes',exact:true}).click(); await page.waitForLoadState('load');
 await runner.reload(); assert.equal(await runner.locator('.se-runner__logo').count(),1);
 assert.equal(await runner.locator('.se-runner__logo').getAttribute('alt'),'Example organisation');
 await runner.locator('.se-runner__logo').scrollIntoViewIfNeeded();
 await runner.waitForFunction(() => {
   const logo = document.querySelector('.se-runner__logo');
   return logo && logo.complete && logo.naturalWidth > 0;
 });
 assert(await runner.locator('.se-runner__logo').evaluate(el=>el.getBoundingClientRect().width<=180));
 const savedPrimary=await page.locator('#se-primary').inputValue();
 const savedCorner=await page.locator('#se-corner-style').inputValue();
 // Simulate malformed submissions without the preview repairing the input first.
 await page.locator('#se-primary').evaluate(el=>{el.value='bad';});
 await page.locator('#se-logo-id').evaluate(el=>{el.value='not-an-id';});
 await page.locator('#se-corner-style').evaluate(el=>{el.add(new Option('Invalid','invalid'));el.value='invalid';});
 await page.getByRole('button',{name:'Save Changes',exact:true}).click(); await page.waitForLoadState('load');
 assert.equal(await page.locator('#se-primary').inputValue(),savedPrimary);
 assert.equal(await page.locator('#se-logo-id').inputValue(),String(fixture.logo));
 assert.equal(await page.locator('#se-corner-style').inputValue(),savedCorner);
 for(const message of ['six-digit hex colour','existing image attachment','Corner style must']) {
   assert(await page.locator('.notice-error').filter({hasText:message}).isVisible());
 }
 await runner.reload(); assert.equal(await runner.locator('.se-runner__logo').count(),1);
 fs.mkdirSync('.tools/se008-browser',{recursive:true});
 await page.screenshot({path:'.tools/se008-browser/settings.png',fullPage:true});
 await runner.screenshot({path:'.tools/se008-browser/mobile.png',fullPage:true});
 await page.locator('#se-remove-logo').click(); assert.equal(await page.locator('#se-preview-logo img').count(),0);
 await page.getByRole('button',{name:'Save Changes',exact:true}).click(); await page.waitForLoadState('load');
 assert.equal(await page.locator('#se-logo-id').inputValue(),'0');
 await runner.reload(); assert.equal(await runner.locator('.se-runner__logo').count(),0);
 await color('text','#fffafa'); assert(await page.locator('#se-contrast-warning').isVisible());
 await page.getByRole('button',{name:'Reset to defaults',exact:true}).click(); await page.waitForLoadState('load');
 await runner.reload(); assert.equal((await styles(runner,'body')).background,'rgb(243, 246, 245)');
 assert.equal((await styles(runner,'.se-runner__main')).radius,'16px'); assert.equal(await runner.locator('.se-runner__logo').count(),0);
 const anon=await browser.newContext(); response=await anon.request.post(new URL('admin-post.php',fixture.url).href,{form:{action:'sprint_engine_reset_branding'}}); assert(response.status()>=400);
 assert.deepEqual(errors,[]);
 console.log('PASS: Default computed styles, dark/light branded Runner, live colours/corners/media preview, save/reset nonce, logo, mobile, focus, contrast warning.');
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
