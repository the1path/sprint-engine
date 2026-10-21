// SE-020: real cookie/nonce stale-tab conflict, Runner recovery, and noncontiguous history.
const {chromium} = require('playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
(async () => {
 const path = process.argv[2];
 const fixture = JSON.parse(fs.readFileSync(path, 'utf8').replace(/^\uFEFF/, ''));
 assert(['127.0.0.1', 'localhost'].includes(new URL(fixture.url).hostname));
 const php = mode => execFileSync(process.env.SE_TEST_PHP, [...JSON.parse(process.env.SE_TEST_PHP_ARGS || '[]'), 'tests/browser/step-change-recovery-fixture.php', path, mode], {encoding:'utf8'});
 const browser = await chromium.launch({channel:process.env.SE_BROWSER_CHANNEL || 'chrome', headless:true});
 try {
  const context = await browser.newContext(); await context.addCookies([fixture.cookie]);
  const page = await context.newPage(); await page.goto(fixture.url);
  assert(await page.getByRole('heading', {name:'Recovery Step B', exact:true}).isVisible());
  assert.equal(await page.locator('#se-runner-action').innerText(), 'Complete & Continue');
  const inserted = JSON.parse(php('edit')).inserted;
  const responsePromise = page.waitForResponse(r => r.url().includes('/complete') && r.request().method() === 'POST');
  await page.locator('#se-runner-action').click(); const response = await responsePromise;
  assert.equal(response.status(),409); assert.match(response.headers()['cache-control'],/no-store/);
  assert.match((await response.json()).message,/Reload/);
  assert(await page.getByRole('heading', {name:'Recovery Step B', exact:true}).isVisible());
  assert.match(await page.locator('#se-runner-status').innerText(),/reload/i);
  console.log(php('stale').trim());
  await page.reload(); assert(await page.getByRole('heading', {name:'New required Step X', exact:true}).isVisible());
  assert((await page.evaluate(() => sprintEngineRunner.endpoint)).endsWith(`/steps/${inserted}/complete`));
  assert.equal(Number(await page.evaluate(() => sprintEngineRunner.attemptId)),fixture.attempt);
  assert.equal(await page.locator('#se-runner-action').innerText(), 'Complete & Continue');
  for (const title of ['Recovery Step B', 'Recovery Step D']) {
   await Promise.all([page.waitForNavigation(),page.locator('#se-runner-action').click()]);
   assert(await page.getByRole('heading', {name:title, exact:true}).isVisible());
   assert.equal(await page.locator('#se-runner-action').innerText(), title === 'Recovery Step D' ? 'Complete Sprint' : 'Complete & Continue');
  }
  console.log(php('last-outstanding').trim());
  await page.reload();
  assert(await page.getByRole('heading', {name:'Recovery Step D', exact:true}).isVisible());
  assert.equal(await page.locator('#se-runner-action').innerText(), 'Complete Sprint');
  await Promise.all([page.waitForNavigation(),page.locator('#se-runner-action').click()]);
  assert(await page.getByRole('heading', {name:'Sprint complete', exact:true}).isVisible());
  console.log(php('verify').trim());
  console.log('PASS: Real stale browser submission conflicts, reload recovers X, and completion skips prior C without restart.');
  console.log('PASS: Intermediate Steps offer Complete & Continue; a nonterminal last-outstanding Step offers Complete Sprint and finishes the Sprint.');
 } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
