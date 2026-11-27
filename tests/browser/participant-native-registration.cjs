// Test-only closure hook is in participant-access-fixture.php, never shipping Core.
const { request } = require('playwright');
const fs = require('node:fs'), cp = require('node:child_process'), path = require('node:path'), assert = require('node:assert/strict');
const file = process.argv[2], f = JSON.parse(fs.readFileSync(file, 'utf8').replace(/^\uFEFF/, ''));
assert(['127.0.0.1', 'localhost'].includes(new URL(f.home).hostname));
const results = [], observations = [];
const ok = (test, name) => { assert(test, name); results.push(name); console.log('PASS: ' + name); };
function control(cmd, value) { const r = cp.spawnSync(process.env.SE_TEST_PHP || 'php', [path.join(__dirname, 'participant-access-fixture.php'), file, cmd, value || ''], { encoding: 'utf8' }); assert.equal(r.status, 0, r.stderr); return r.stdout ? JSON.parse(r.stdout) : null; }
(async () => {
 const c = await request.newContext(), admin = await request.newContext({ storageState: { cookies: f.administrator.cookies.map(v => ({ name: v.name, value: v.value, domain: new URL(v.url).hostname, path: '/', expires: -1, httpOnly: true, secure: false, sameSite: 'Lax' })), origins: [] } });
 try {
 const login = new URL('wp-login.php', f.home).href;
 control('mode', 'built_in'); control('closure', 'off');
 let r = await c.get(login + '?action=register'); ok(r.status() === 200 && (await r.text()).includes('id="registerform"'), 'Shipping checked/pending preference leaves native GET registration open');
 const count = control('inspect').users;
 const form = { user_login: 'native_' + Date.now(), user_email: 'native-' + Date.now() + '@example.invalid' };
 r = await c.post(login + '?action=register', { form, maxRedirects: 0 });
 ok(r.status() === 302 && control('inspect').users === count + 1, 'Shipping pending preference also leaves native POST account creation open');
 control('closure', 'on'); const before = control('inspect').users;
 for (const [method, query, body] of [
  ['GET', '?action=register', null], ['POST', '?action=register', form], ['POST', '', { ...form, action: 'register' }],
  ['POST', '?action=login', { ...form, action: 'register' }], ['GET', '?action=%72egister', null],
  ['POST', '?action=register&interim-login=1', form], ['GET', '?action=register&reauth=1', null]
 ]) {
  r = method === 'POST' ? await c.post(login + query, { form: body, maxRedirects: 0 }) : await c.get(login + query, { maxRedirects: 0 });
  ok(r.status() === 403 && (await r.text()).includes('DISPOSABLE NATIVE CLOSURE') && control('inspect').users === before, 'Test-only hook blocks native ' + method + ' dispatch ' + query);
 }
 r = await c.get(login + '?action=register&redirect_to=' + encodeURIComponent(f.routes.open.url));
 ok((await r.text()).includes('Return to original Sprint') && (await r.text()).includes('redirect_to='), 'Blocked native form can preserve a validated original Sprint');
 r = await c.get(login + '?action=register&redirect_to=https://outside.invalid/');
 ok(!(await r.text()).includes('outside.invalid') && !(await r.text()).includes('Return to original Sprint'), 'Blocked route rejects arbitrary returns without inventing Sprint context');
 for (const query of ['', '?action=login', '?action=lostpassword', '?action=rp', '?action=logout', '?action=register&key=invalid', '?action=register&checkemail=registered', '?action=Register']) {
  r = await c.get(login + query, { maxRedirects: 0 });
  ok(!(await r.text()).includes('DISPOSABLE NATIVE CLOSURE') && control('inspect').users === before, 'Test-only closure leaves native resolved action unchanged: ' + query);
 }
 r = await c.post(login + '?action=register', { form: { action: 'login' }, maxRedirects: 0 });
 ok(!(await r.text()).includes('DISPOSABLE NATIVE CLOSURE') && control('inspect').users === before, 'Native POST-over-GET dispatch remains native when resolved action is login');
 r = await c.get(login + '?action[]=register', { maxRedirects: 0 });
 ok(control('inspect').users === before, 'Malformed action array never creates an account'); observations.push({ malformed_action_array_status: r.status(), explanation: 'WP 6.0 does not type-check this native parameter; any upstream failure is recorded, not patched here.' });
 r = await c.get(login); ok((await r.text()).includes('Register'), 'Native Register link remains visible; test closure supplies accessible guidance');
 for (const mode of ['external', 'invitation']) {
  control('mode', mode); r = await c.get(login + '?action=register');
  ok(!(await r.text()).includes('DISPOSABLE NATIVE CLOSURE'), 'Test closure inactive after explicit switch to ' + mode);
 }
 control('mode', 'built_in'); control('flag', '0'); r = await c.get(login + '?action=register');
 ok(!(await r.text()).includes('DISPOSABLE NATIVE CLOSURE'), 'Invalid global registration permission restores native policy response'); control('flag', '1');
 control('unsafe_role', 'on'); r = await c.get(login + '?action=register');
 ok(!(await r.text()).includes('DISPOSABLE NATIVE CLOSURE'), 'Unsafe role invalidation leaves registration control to native policy'); control('unsafe_role', 'off');
 const saved = control('inspect').settings; control('settings_raw', JSON.stringify({ ...saved, disable_native: false })); r = await c.get(login + '?action=register');
 ok(!(await r.text()).includes('DISPOSABLE NATIVE CLOSURE'), 'Unchecked native closure preference restores native form'); control('settings_raw', JSON.stringify(saved));
 r = await admin.get(new URL('wp-admin/user-new.php', f.home).href); const html = await r.text();
 const nonce = html.match(/name="_wpnonce_create-user" value="([^"]+)"/); assert(nonce, 'Native admin create-user nonce');
 r = await admin.post(new URL('wp-admin/user-new.php', f.home).href, { form: { action: 'createuser', '_wpnonce_create-user': nonce[1], user_login: 'admin_created_' + Date.now(), email: 'admin-created-' + Date.now() + '@example.invalid', pass1: 'Disposable-Only-31!A', pass2: 'Disposable-Only-31!A', role: 'subscriber', createuser: 'Add New User' }, maxRedirects: 0 });
 ok(r.status() === 302 && control('inspect').users === before + 1, 'Actual native administrator account creation unaffected by test-only closure');
 control('closure', 'off'); r = await c.get(login + '?action=register'); ok(r.status() === 200 && (await r.text()).includes('id="registerform"'), 'Removing test readiness reopens native form without changing global registration flag');
 fs.writeFileSync(file + '.native-results.json', JSON.stringify({ checks: results.length, passed: results, observations }, null, 2));
 } finally { control('closure', 'off'); await c.dispose(); await admin.dispose(); }
})().catch(e => { console.error(e.message.split('\nCall log:')[0]); process.exitCode = 1; });
