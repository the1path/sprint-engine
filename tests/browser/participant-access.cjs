const { chromium } = require('playwright');
const fs = require('node:fs');
const cp = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const manifest = process.argv[2];
const f = JSON.parse(fs.readFileSync(manifest, 'utf8').replace(/^\uFEFF/, ''));
assert(['localhost', '127.0.0.1'].includes(new URL(f.home).hostname));
const results = [];
const check = (value, name) => { assert(value, name); results.push(name); console.log('PASS: ' + name); };
function control(command, value) {
 const r = cp.spawnSync(process.env.SE_TEST_PHP || 'php', [path.join(__dirname, 'participant-access-fixture.php'), manifest, command, value || ''], { encoding: 'utf8' });
 assert.equal(r.status, 0, r.stderr); return r.stdout ? JSON.parse(r.stdout) : null;
}
const option = 'sprint_engine_participant_accounts';
(async () => {
 const browser = await chromium.launch({ channel: process.env.SE_BROWSER_CHANNEL || 'chrome', headless: true });
 try {
 const admin = await browser.newContext({ viewport: { width: 1440, height: 1000 }, javaScriptEnabled: false }); await admin.addCookies(f.administrator.cookies);
 const member = await browser.newContext(); await member.addCookies(f.subscriber.cookies);
 const guest = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 1000 } });
 const page = await admin.newPage(), runner = await guest.newPage();
 await page.goto(f.settings);
 check(await page.locator('#sprint-engine-participants-form').isVisible(), 'Participant settings are a native no-JS form');
 const statusBefore = control('inspect');
 const form = page.locator('#sprint-engine-participants-form');
 const nonce = await form.locator('[name="_wpnonce"]').inputValue();
 const values = { option_page: 'sprint_engine_participants', action: 'update', _wpnonce: nonce, [`${option}[mode]`]: 'invitation', [`${option}[registration_url]`]: '', [`${option}[login_url]`]: '', [`${option}[disable_native]`]: '0' };
 let r = await admin.request.post(new URL('wp-admin/options.php', f.home).href, { form: { ...values, _wpnonce: 'invalid' } });
 check(r.status() === 403 && control('inspect').settings.mode === '', 'Invalid Settings API nonce cannot change configuration');
 r = await member.request.post(new URL('wp-admin/options.php', f.home).href, { form: values });
 check(r.status() === 403 && control('inspect').settings.mode === '', 'Valid admin nonce cannot bypass member capabilities');
 r = await admin.request.get(new URL('wp-admin/options.php?' + new URLSearchParams(values), f.home).href);
 check(r.status() === 403 && control('inspect').settings.mode === '', 'GET with a form nonce cannot submit participant settings');
 async function save(mode, disable = true, registration = f.registration, login = '') {
  await page.goto(f.settings); await page.locator(`[name="${option}[mode]"][value="${mode}"]`).check();
  await page.locator('#sprint-engine-registration_url').fill(registration); await page.locator('#sprint-engine-login_url').fill(login);
  if (!(await page.locator('#sprint-engine-participants details').evaluate(el => el.open))) await page.locator('#sprint-engine-participants summary').click();
  const box = page.locator(`[type="checkbox"][name="${option}[disable_native]"]`); if (disable) await box.check(); else await box.uncheck();
  await page.getByRole('button', { name: 'Save participant settings', exact: true }).click(); await page.waitForLoadState('load');
 }
 await save('built_in');
 check(control('inspect').settings.disable_native === true && await page.getByText('Preference saved:', { exact: false }).first().isVisible(), 'Deliberate built-in save records preference and pending status');
 if (process.env.SE_TEST_SCREENSHOTS === 'yes') { fs.mkdirSync('docs/validation/images/se-031-2', { recursive: true }); await page.locator('#sprint-engine-participants').screenshot({ path: 'docs/validation/images/se-031-2/settings-pending.png' }); }
 r = await guest.request.get(new URL('wp-login.php?action=register', f.home).href);
 check(r.status() === 200 && (await r.text()).includes('name="user_login"'), 'Checked preference leaves native registration operational in this slice');
 await save('built_in', false); await page.reload();
 check(!(await page.locator(`[type="checkbox"][name="${option}[disable_native]"]`).isChecked()) && (await page.locator('#sprint-engine-participants').innerText()).includes('not protected'), 'Unchecked choice persists with inline warning');
 await save('invitation', false); await save('built_in', false);
 check(control('inspect').settings.disable_native === false, 'Mode round-trip preserves explicit native opt-out');
 await save('external', false);
 await runner.goto(f.routes.open.url + '?redirect_to=https://outside.invalid&user_id=1');
 let html = await runner.content();
 check(await runner.getByRole('link', { name: 'Create account', exact: true }).isVisible() && !html.includes('PRIVATE') && !html.includes('sprintEngineRunner') && !html.includes('Start Sprint'), 'Open external guest has functional handoff and no private/member write data');
 let href = await runner.getByRole('link', { name: 'Create account', exact: true }).getAttribute('href');
 check(new URL(href).searchParams.get('redirect_to') === f.routes.open.url && !href.includes('outside.invalid'), 'Request-supplied redirect cannot replace canonical provider return');
 await runner.getByRole('link', { name: 'Create account', exact: true }).click();
 await runner.waitForLoadState('load');
 check((await runner.content()).includes('Provider fixture deliberately ignores'), 'Actual external page receives hint without Core forcing provider redirect');
 await runner.goto(f.routes.open.url); check(await runner.getByRole('link', { name: 'Return to this Sprint', exact: true }).isVisible(), 'Original Sprint fallback remains available when provider ignores hint');
 r = await guest.request.get(f.routes.open.url);
 check(r.headers()['cache-control'].includes('private') && r.headers()['cache-control'].includes('no-store') && r.headers()['x-robots-tag'] === 'noindex, nofollow', 'Dynamic guest responses private/no-store/noindex');
 for (const kind of ['restricted', 'unknown']) {
  await runner.goto(f.routes[kind].url);
  check(!(await runner.getByRole('link', { name: 'Create account', exact: true }).count()) && await runner.getByRole('link', { name: 'Sign in to check access', exact: true }).isVisible(), kind + ' offers neutral sign-in without entitlement promise');
 }
 for (const kind of ['draft', 'password', 'unlaunchable', 'broken']) {
  r = await guest.request.get(f.routes[kind].url, { maxRedirects: 0 });
  check(r.status() === 404 && !(await r.text()).includes('Create account') && !(await r.text()).includes('PRIVATE'), kind + ' stays unavailable without disclosure');
 }
 for (const kind of ['tutorial', 'corrupt']) {
  r = await guest.request.get(f.routes[kind].url, { maxRedirects: 0 });
  check(r.status() === 302 && new URL(r.headers().location).pathname.endsWith('/wp-login.php'), kind + ' keeps native admin sign-in');
 }
 control('page', 'draft'); await runner.goto(f.routes.open.url);
 check(!(await runner.getByRole('link', { name: 'Create account', exact: true }).count()), 'Changing provider page to draft immediately suppresses signup'); control('page', 'publish');
 await page.goto(f.settings); await page.locator('#sprint-engine-registration_url').fill('https://outside.invalid/register'); await page.getByRole('button', { name: 'Save participant settings', exact: true }).click();
 check(control('inspect').settings.registration_url === f.registration && await page.getByText('Previous participant settings were kept.', { exact: false }).isVisible(), 'Invalid whole Settings API submission retains valid configuration');
 const valid = control('inspect').settings;
 control('settings_raw', JSON.stringify({ ...valid, login_url: 'https://outside.invalid/login' })); await runner.goto(f.routes.open.url);
 href = await runner.getByRole('link', { name: 'Sign in', exact: true }).getAttribute('href');
 check(new URL(href).pathname.endsWith('/wp-login.php') && await runner.getByRole('link', { name: 'Create account', exact: true }).count(), 'Invalidated optional login falls back natively without losing valid signup'); control('settings_raw', JSON.stringify(valid));
 // Native sign-in and recovery are real WordPress pages, not custom Core forms.
 const fresh = await browser.newContext({ javaScriptEnabled: false }); const native = await fresh.newPage();
 await native.goto(new URL('wp-login.php?redirect_to=' + encodeURIComponent(f.routes.open.url), f.home).href);
 await native.locator('#user_login').fill(f.subscriber.email); await native.locator('#user_pass').fill(f.subscriber.password); await native.locator('#wp-submit').click(); await native.waitForLoadState('load');
 check(native.url() === f.routes.open.url && (await native.content()).includes('Ready to start'), 'Native email login returns to exact Sprint on fresh authenticated request');
 await runner.goto(f.routes.open.url); await runner.getByRole('link', { name: 'Forgotten your password?', exact: true }).click();
 check(await runner.locator('#lostpasswordform').isVisible(), 'Recovery handoff renders native reset request form');
 const after = control('inspect'); check(JSON.stringify(statusBefore.attempts) === JSON.stringify(after.attempts) && statusBefore.users === after.users, 'Settings and all guest/login GETs create no account or attempt');
 // Branding reset and tutorial controls use separate stores/forms.
 await page.goto(f.settings); const stored = control('inspect').settings;
 await page.getByRole('button', { name: 'Reset to defaults', exact: true }).click(); await page.waitForLoadState('load');
 check(JSON.stringify(stored) === JSON.stringify(control('inspect').settings), 'Branding reset preserves participant configuration');
 await page.locator('#se-primary').fill('#482070'); await page.locator('#se-branding-font').selectOption('lato');
 await page.getByRole('button', { name: 'Save Changes', exact: true }).click(); await page.waitForLoadState('load');
 await runner.goto(f.routes.open.url);
 check(await runner.getByRole('link', { name: 'Sign in', exact: true }).evaluate(el => getComputedStyle(el).backgroundColor === 'rgb(72, 32, 112)' && getComputedStyle(el).fontFamily.includes('Lato')), 'Guest sign-in inherits saved Runner colour and typography');
 await page.getByRole('button', { name: 'Reset to defaults', exact: true }).click(); await page.waitForLoadState('load');
 await runner.goto(f.routes.open.url);
 await runner.keyboard.press('Tab'); check(await runner.locator('.se-runner__skip').evaluate(el => el === document.activeElement), 'Keyboard Tab reaches skip link without JavaScript');
 await runner.keyboard.press('Tab'); await runner.keyboard.press('Tab');
 check(await runner.getByRole('link', { name: 'Sign in', exact: true }).evaluate(el => el === document.activeElement && getComputedStyle(el).outlineStyle !== 'none'), 'Keyboard Tab reaches sign-in with visible focus');
 const shots = path.join('docs', 'validation', 'images', 'se-031-2'); fs.mkdirSync(shots, { recursive: true });
 if (process.env.SE_TEST_SCREENSHOTS === 'yes') {
  await page.locator('#sprint-engine-participants').screenshot({ path: path.join(shots, 'settings.png') });
 }
 for (const width of [320, 390, 1440]) {
  await runner.setViewportSize({ width, height: 1000 });
  check(await runner.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No horizontal overflow at ' + width + 'px');
  await runner.getByRole('link', { name: 'Sign in', exact: true }).focus();
  check(await runner.evaluate(() => document.activeElement.tagName === 'A'), 'Keyboard focus reaches sign-in at ' + width + 'px');
  if (process.env.SE_TEST_SCREENSHOTS === 'yes') await runner.screenshot({ path: path.join(shots, 'guest-' + width + '.png'), fullPage: true });
 }
 // DOM-based 400% text enlargement and a fixture translation/RTL stress case.
 await runner.evaluate(() => { const style = document.createElement('style'); style.textContent = 'body.se-runner { font-size: 400%; }'; document.head.appendChild(style); }); await runner.setViewportSize({ width: 320, height: 1000 });
 check(await runner.evaluate(() => document.documentElement.scrollWidth <= innerWidth), '400% text enlargement wraps at 320px');
 control('rtl', 'on'); await runner.reload();
 check(await runner.locator('html').getAttribute('dir') === 'rtl' && await runner.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Long translated RTL labels wrap without overflow');
 if (process.env.SE_TEST_SCREENSHOTS === 'yes') await runner.screenshot({ path: path.join(shots, 'guest-rtl.png'), fullPage: true }); control('rtl', 'off');
 await fresh.close(); await admin.close(); await member.close(); await guest.close();
 fs.writeFileSync(manifest + '.browser-results.json', JSON.stringify({ checks: results.length, passed: results }, null, 2));
 console.log('Completed ' + results.length + ' browser checks.');
 } finally { await browser.close(); }
})().catch(error => { console.error(error.message.split('\nCall log:')[0]); process.exitCode = 1; });
