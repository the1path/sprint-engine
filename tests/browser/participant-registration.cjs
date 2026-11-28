/* SE-031.3: real HTTPS/browser/independent-worker checks. See validation for setup. */
'use strict';
const fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process'),https=require('node:https'),assert=require('node:assert/strict');
const {chromium}=require('playwright');
if(process.env.SE_TEST_DISPOSABLE!=='yes'||!process.env.SE_TEST_WP_ROOT||!process.env.SE_TEST_FIXTURE_JSON)throw Error('Explicit disposable site/fixture required');
const fixture=JSON.parse(fs.readFileSync(process.env.SE_TEST_FIXTURE_JSON,'utf8').replace(/^\uFEFF/,'')),f=fixture.fixtures;
const origin=new URL(f.open.url).origin,php=process.env.SE_TEST_PHP||'php',phpArgs=process.env.SE_TEST_PHP_INI?['-c',process.env.SE_TEST_PHP_INI]:[];
const control=input=>JSON.parse(cp.execFileSync(php,[...phpArgs,'tests/fixtures/participant-registration.php'],{input:JSON.stringify(input),encoding:'utf8',env:process.env,maxBuffer:16*1024*1024}));
const checks=[];function ok(test,label){assert(test,label);checks.push(label);console.log('PASS: '+label);}
const decode=s=>s.replace(/&#0*38;|&amp;/g,'&').replace(/&#0*39;|&apos;/g,"'").replace(/&quot;/g,'"').replace(/&lt;/g,'<').replace(/&gt;/g,'>');
function fields(html,hidden=false){const out={};for(const match of html.matchAll(/<input\b[^>]*>/g)){const tag=match[0];if(hidden&&!/type="hidden"/.test(tag))continue;const name=tag.match(/\bname="([^"]+)"/),value=tag.match(/\bvalue="([^"]*)"/);if(name)out[decode(name[1])]=decode(value?.[1]||'');}return out;}
function jar(){return new Map();}
function request(url,cookies=jar(),method='GET',body='',headers={}){return new Promise((resolve,reject)=>{
 const u=new URL(url),req=https.request({hostname:u.hostname,port:u.port,path:u.pathname+u.search,method,rejectUnauthorized:false,lookup:(host,opts,cb)=>opts.all?cb(null,[{address:'127.0.0.1',family:4}]):cb(null,'127.0.0.1',4),headers:{...(cookies.size?{Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')}:{}),...(body?{'Content-Type':'application/x-www-form-urlencoded','Content-Length':Buffer.byteLength(body)}:{}),...headers}},res=>{
 let html='';res.setEncoding('utf8');res.on('data',x=>html+=x);res.on('end',()=>{for(const cookie of res.headers['set-cookie']||[]){const [pair]=cookie.split(';'),at=pair.indexOf('=');cookies.set(pair.slice(0,at),pair.slice(at+1));}resolve({status:res.statusCode,headers:res.headers,html});});
 });req.on('error',reject);req.end(body);
});}
const freshEmail=()=> 'browser-'+require('node:crypto').randomBytes(9).toString('hex')+'@example.invalid';
const password="Native-'\\-🔒-correct-password";
function payload(html,email=freshEmail()){return {...fields(html),first_name:" Élodie O'Connor ",last_name:'李 أحمد',email,password};}
function post(url,cookies,data,headers={}){return request(url,cookies,'POST',new URLSearchParams(data).toString(),{Origin:origin,Referer:url,'Sec-Fetch-Site':'same-origin',...headers});}
const reset=(behavior='')=>{control({action:'config',mode:'built_in',confirm:true,disable_native:true,behavior});control({action:'reset-state'});};
async function main(){
 reset();const baseline=control({action:'inspect'}).rows;
 let c=jar(),r=await request(f.open.url,c),data=payload(r.html);
 ok(r.status===200&&/Create your account/.test(r.html)&&!r.html.includes('PRIVATE'),'Eligible guest sees branded form with no private Sprint data');
 ok(/private, no-store/.test(r.headers['cache-control'])&&/noindex/.test(r.headers['x-robots-tag']),'Guest registration is private/no-store/noindex');
 ok(Object.keys(fields(r.html)).sort().join('|')===['email','first_name','last_name','password','sprint_engine_action','sprint_engine_registration_token'].sort().join('|'),'Form has only four participant fields and two security/action values');
 ok(!r.html.includes('sprintEngineRunner')&&!r.html.includes('Start Sprint')&&!r.html.includes('wp_rest'),'Guest receives no write nonce, start action or private Runner configuration');
 const token=data.sprint_engine_registration_token;
 r=await post(f.open.url,c,data);ok(r.status===303&&r.headers.location===f.open.url,'Native registration redirects exactly to canonical Sprint');
 const user=control({action:'inspect',email:data.email,password});ok(user.user?.password_ok&&user.user.first_name==="Élodie O'Connor"&&user.user.last_name==='李 أحمد'&&user.user.roles.join()==='subscriber','Account preserves native credential, names and safe Subscriber role');
 r=await request(f.open.url,c);ok(r.status===200&&r.html.includes('Ready to start')&&r.html.includes('Start Sprint'),'Fresh native-authenticated request reaches Ready');
 ok(JSON.stringify(control({action:'inspect'}).rows)===JSON.stringify(baseline),'Registration and Ready create no attempt or Step progress');
 const replay=new Map([...c].filter(([key])=>key.startsWith('__Host-')));replay.set('__Host-sprint_engine_registration','bad');r=await post(f.open.url,replay,data);ok(r.status===403,'Replayed form without original valid browser state fails');
 reset();c=jar();r=await request(f.open.url,c);data=payload(r.html);const rawBefore=control({action:'inspect'}).users;
 for(const [label,change,headers,url] of [
  ['missing token',{sprint_engine_registration_token:''},{},f.open.url],
  ['altered action',{sprint_engine_action:'different'},{},f.open.url],
  ['cross-Sprint',{}, {},f.second.url],
  ['extra role',{role:'administrator'},{},f.open.url],
  ['extra identity',{user_id:'1'},{},f.open.url],
  ['extra redirect',{redirect_to:'https://evil.invalid/'},{},f.open.url],
  ['external Origin',{}, {Origin:'https://evil.se0313.test:8461'},f.open.url],
  ['external Referer',{}, {Referer:'https://evil.se0313.test:8461/'},f.open.url],
  ['same-site fetch metadata',{}, {'Sec-Fetch-Site':'same-site'},f.open.url],
  ['structured field',{'first_name[]':'bad',first_name:undefined},{},f.open.url],
 ]){const submitted={...data,...change};for(const key in submitted)if(submitted[key]===undefined)delete submitted[key];const response=await post(url,c,submitted,headers);ok(response.status===403&&!response.html.includes(password),'Reject '+label+' without password reflection');}
 ok(control({action:'inspect'}).users===rawBefore,'All forged requests create no account');
 r=await post(f.open.url,jar(),data);ok(r.status===403,'Valid stolen form token without browser cookie cannot register');
 const invalid=await post(f.open.url,c,{...data,first_name:'',password:'tiny'});ok(invalid.status===400&&invalid.html.includes('aria-invalid="true"')&&invalid.html.includes('Élodie')===false&&!invalid.html.includes('value="tiny"'),'Accessible input errors never echo password');
 data=payload(invalid.html);r=await post(f.open.url,c,data,{Origin:'null','Sec-Fetch-Site':''});ok(r.status===303,'No-JS/null provenance still works only with valid cookie and fresh token');
 reset();c=jar();r=await request(f.open.url,c);data=payload(r.html);const sameCookie=new Map(c);
 const concurrent=await Promise.all([post(f.open.url,c,data),post(f.open.url,sameCookie,data)]);ok(concurrent.map(x=>x.status).sort().join()==='303,409','Independent PHP workers atomically reserve duplicate token once');
 reset();const a=jar(),b=jar();const [fa,fb]=await Promise.all([request(f.open.url,a),request(f.open.url,b)]);const email=freshEmail(),before=control({action:'inspect'}).users;
 const duplicate=await Promise.all([post(f.open.url,a,payload(fa.html,email)),post(f.open.url,b,payload(fb.html,email))]);ok(duplicate.map(x=>x.status).sort().join()==='303,409'&&control({action:'inspect'}).users===before+1,'Independent workers serialize native email uniqueness');
 reset('slow');const held=path.join(process.env.SE_TEST_EVIDENCE_DIR,control({action:'inspect'}).lock_signal);if(fs.existsSync(held))fs.unlinkSync(held);const lc=jar(),rc=jar();const lf=await request(f.open.url,lc),rf=await request(f.open.url,rc);const firstRequest=post(f.open.url,lc,payload(lf.html));const deadline=Date.now()+10000;while(!fs.existsSync(held)&&Date.now()<deadline)await new Promise(resolve=>setTimeout(resolve,20));assert(fs.existsSync(held),'Worker never acquired lock');const contention=await post(f.open.url,rc,payload(rf.html));ok(contention.status===503&&(await firstRequest).status===303,'Independent worker contention fails within bounded wait; first insertion completes');ok(control({action:'inspect'}).closed,'Contention does not reopen standard native form');
 reset();c=jar();const [tab1,tab2]=[await request(f.open.url,c),await request(f.second.url,c)];ok(fields(tab1.html).sprint_engine_registration_token!==fields(tab2.html).sprint_engine_registration_token,'Multiple tabs share browser state with distinct Sprint-bound form IDs');
 r=await post(f.second.url,c,payload(tab2.html));ok(r.status===303,'Second tab can create account without first form overwriting browser state');await request(f.second.url,c);r=await post(f.open.url,c,payload(tab1.html));ok(r.status===409,'Authenticated stale tab cannot switch identity or create another account');
 for(const behavior of ['reject','suppress','mail-fail','throw-after','deny-registration','kill-before','kill-insert','unpublish-step','reconfirm']){
  reset(behavior);c=jar();r=await request(f.open.url,c);data=payload(r.html);const n=control({action:'inspect'}).users;r=await post(f.open.url,c,data);
  ok(!r.html.includes('PRIVATE')&&!r.html.includes(password),'Safe response under '+behavior);
  const after=control({action:'inspect'}),created=after.users-n;
  if(['deny-registration','kill-before','kill-insert','unpublish-step','reconfirm'].includes(behavior))ok(created===0&&[403,503].includes(r.status),'No account or auth after '+behavior);
  else ok(created===1,'Persisted account retained under '+behavior);
  if(['reject','suppress'].includes(behavior)){if(r.status===303)r=await request(f.open.url,c);ok(r.html.includes('Your account was created')&&!r.html.includes('Ready to start')&&!r.html.includes('name="password"'),'Incomplete '+behavior+' uses native recovery, never synthetic success');}
  if(behavior==='throw-after'){reset();r=await post(f.open.url,c,payload((await request(f.open.url,c)).html,data.email));ok(r.status===409,'Retry after partial account insert cannot recreate account');}
  if(behavior==='unpublish-step'){ok(!r.html.includes('name="password"'),'Fresh readiness check rejects stale Step cache and withdraws signup form');control({action:'repair-steps'});}
 }
 reset();for(const behavior of ['unknown','restricted']){control({action:'config',behavior});r=await request(f.open.url,jar());ok(r.status===403&&!r.html.includes('name="password"')&&r.html.includes('>Sign in</a>')&&r.html.includes('SAFE INFORMATION ACTION'),'Neutral '+behavior+' screen preserves sign-in and denial action');}
 reset('gate-open');const log=path.join(process.env.SE_TEST_EVIDENCE_DIR,control({action:'inspect'}).gate_log);if(fs.existsSync(log))fs.writeFileSync(log,'');r=await request(f.open.url,jar());ok(r.status===403&&r.html.includes('name="password"')&&r.html.includes('SAFE INFORMATION ACTION'),'Explicit known-open form retains exact SE-017 403 and information action');if(fs.existsSync(log))ok(fs.readFileSync(log,'utf8').trim()==='gate\ndenial','SE-017 filter and denial action run exactly once');
 reset();for(const key of ['tutorial','draft']){r=await request(f[key].url,jar());ok(!r.html.includes('name="password"')&&!r.html.includes('PRIVATE'),'No signup promotion for '+key);}
 for(const url of [origin+'/missing-registration/',origin+(new URL(f.open.url).pathname.split('/sprint/')[0])+'/sprint/does-not-exist/']){r=await request(url,jar());ok(!r.html.includes('name="password"'),'Unknown route cannot create accounts');}
 const login=origin+new URL(f.open.url).pathname.split('/sprint/')[0]+'/wp-login.php';
 for(const [method,suffix,body] of [['GET','?action=register',''],['POST','?action=login','action=register&user_login=untrusted&user_email=untrusted%40example.invalid'],['GET','?action=%72egister',''],['POST','?action=register','action=register']]){r=await request(login+suffix,jar(),method,body);ok(r.status===403&&r.html.includes('Sign in')&&!r.html.includes('name="user_email"'),'Native closure catches resolved '+method+' '+suffix);}
 r=await request(login+'?action=register&redirect_to='+encodeURIComponent(f.international.url),jar());ok(r.status===403&&r.html.includes('Return to your Sprint')&&r.html.includes(f.international.url),'Blocked native UI accepts exact eligible canonical international context');
 r=await request(login+'?action=register&redirect_to='+encodeURIComponent('https://evil.invalid/'),jar());ok(r.status===403&&!r.html.includes('Return to your Sprint')&&!r.html.includes('evil.invalid'),'Blocked native UI rejects arbitrary return destination');
 for(const action of ['login','lostpassword','logout']){r=await request(login+'?action='+action,jar());ok(!r.html.includes('Open your Sprint link to create an account')&&(action==='logout'||r.status!==403),'Native '+action+' remains independently dispatched (including native logout nonce protection)');}
 for(const mode of ['external','invitation','']){
  if(mode==='external')control({action:'config',mode,registration_url:login+'?action=register&campaign=existing',login_url:login+'?campaign=signin',confirm:false});else if(mode==='')control({action:'policy',key:'sprint_engine_participant_accounts',value:{mode:'',registration_url:'',login_url:'',disable_native:null,confirmation:''}});else control({action:'config',mode,confirm:false});
  r=await request(login+'?action=register',jar());ok(r.status===200&&r.html.includes('name="user_email"'),'Native policy follows '+(mode||'unset')+' mode');
 }
 reset();control({action:'config',disable_native:false});r=await request(login+'?action=register',jar());ok(r.status===200&&r.html.includes('name="user_email"'),'Explicit native-form opt-out remains effective');reset();
 control({action:'policy',key:'users_can_register',value:0});control({action:'policy',key:'users_can_register',value:1});r=await request(f.open.url,jar());ok(!r.html.includes('name="password"')&&!control({action:'inspect'}).operational,'Objective policy change and restoration require new confirmation');reset();
 control({action:'mfa',enabled:true});reset('mfa');c=jar();r=await request(f.international.url,c);data=payload(r.html);r=await post(f.international.url,c,data);ok(r.status===200&&/authentication code|two.factor/i.test(r.html),'Actual Two Factor plugin interrupts wp_signon into its challenge');
 const mfa=fields(r.html,true);ok(mfa.redirect_to===f.international.url,'MFA receives exact server-derived encoded-slug/subdirectory canonical return');
 const mailFile=path.join(process.env.SE_TEST_EVIDENCE_DIR,'mail/captured.jsonl'),mails=fs.readFileSync(mailFile,'utf8').trim().split('\n').map(x=>JSON.parse(x));const mail=[...mails].reverse().find(x=>/\b\d{8}\b/.test(x.message)&&JSON.stringify(x.to).includes(data.email));assert(mail,'Captured native MFA mail required');
 let pending=await request(f.international.url,c);ok(!pending.html.includes('Ready to start')&&pending.html.includes('Your account was created'),'MFA pending cannot reach Ready or recreate account');
 r=await post(login+'?action=validate_2fa',c,{...mfa,'two-factor-email-code':mail.message.match(/\b\d{8}\b/)[0]});ok([302,303].includes(r.status)&&r.headers.location===f.international.url,'Actual MFA verification returns canonical Sprint');r=await request(f.international.url,c);ok(r.status===200&&r.html.includes('Ready to start'),'Fresh request after MFA establishes expected native identity');control({action:'mfa',enabled:false});reset();

 const browser=await chromium.launch({channel:'chrome',headless:true,args:['--host-resolver-rules=MAP signup.se0313.test 127.0.0.1, MAP evil.se0313.test 127.0.0.1','--no-proxy-server']});
 try{
  const context=await browser.newContext({ignoreHTTPSErrors:true,javaScriptEnabled:false,viewport:{width:1280,height:900}}),page=await context.newPage();await page.goto(f.international.url);
  const cookies=await context.cookies(),cookie=cookies.find(x=>x.name==='__Host-sprint_engine_registration');ok(cookie?.secure&&cookie.httpOnly&&cookie.path==='/'&&cookie.sameSite==='Strict'&&cookie.domain==='signup.se0313.test','Actual browser stores Secure HttpOnly host-only Path=/ Strict cookie over TLS');
  ok(!await page.locator('input[name=password]').getAttribute('value'),'Browser never receives password value');
  const keys={first_name:'Élodie O’Connor',last_name:'李 أحمد',email:freshEmail(),password};for(const [key,value]of Object.entries(keys))await page.locator('[name='+key+']').fill(value);
  await page.getByRole('button',{name:'Create account',exact:true}).click();await page.waitForURL(f.international.url);await page.getByText('Ready to start',{exact:false}).waitFor();ok(true,'Actual no-JS browser signup preserves canonical international Runner and reaches Ready');
  await context.close();
  const injection=await browser.newContext({ignoreHTTPSErrors:true}),attacker=await injection.newPage();await attacker.goto(origin.replace('signup.','evil.')+'/');
  await attacker.evaluate(()=>{document.cookie='__Host-sprint_engine_registration=injected; Secure; Path=/; Domain=se0313.test';});const attacked=await injection.cookies(origin);ok(!attacked.some(x=>x.name==='__Host-sprint_engine_registration'),'Browser rejects sibling-domain __Host- cookie injection');
  await attacker.goto(f.open.url);const beforeCookie=(await injection.cookies()).find(x=>x.name==='__Host-sprint_engine_registration');await attacker.evaluate(()=>{document.cookie='__Host-sprint_engine_registration=injected; Secure; Path=/';});const afterCookie=(await injection.cookies()).find(x=>x.name==='__Host-sprint_engine_registration');ok(beforeCookie.value===afterCookie.value,'HttpOnly browser state resists JavaScript overwrite');
  const stolen=await attacker.locator('[name=sprint_engine_registration_token]').inputValue();await attacker.goto(origin.replace('signup.','evil.')+'/');const attackEmail=freshEmail();await attacker.evaluate(({url,token,email,password})=>{const form=document.createElement('form');form.method='post';form.action=url;for(const[name,value]of Object.entries({sprint_engine_action:'sprint_engine_register',sprint_engine_registration_token:token,first_name:'Forged',last_name:'Sibling',email,password})){const input=document.createElement('input');input.name=name;input.value=value;form.append(input);}document.body.append(form);form.submit();},{url:f.open.url,token:stolen,email:attackEmail,password});await attacker.waitForURL(f.open.url);ok(!(await control({action:'inspect',email:attackEmail})).user&&await attacker.getByText('This form could not be verified.',{exact:false}).isVisible(),'Actual sibling-origin browser POST cannot use stolen token for login CSRF');await attacker.goto(f.open.url);
  for(const [name,width,rtl,zoom]of [['desktop',1280,false,1],['mobile',360,false,1],['rtl-zoom',320,true,2]]){await attacker.setViewportSize({width,height:1100});await attacker.evaluate(({rtl,zoom})=>{document.documentElement.dir=rtl?'rtl':'ltr';document.documentElement.style.zoom=zoom;},{rtl,zoom});ok(await attacker.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'No horizontal overflow in '+name);await attacker.screenshot({path:path.join(process.env.SE_TEST_EVIDENCE_DIR,process.env.SE_TEST_LANE+'-registration-'+name+'.png'),fullPage:true});}
  const input=attacker.locator('input[name=first_name]');await input.focus();ok(await input.evaluate(x=>document.activeElement===x&&!!document.querySelector('label[for="'+x.id+'"]')),'Keyboard focus and native name label are available');await injection.close();
  const admin=await browser.newContext({ignoreHTTPSErrors:true}),adminPage=await admin.newPage(),credentials=JSON.parse(fs.readFileSync(path.join(process.env.SE_TEST_EVIDENCE_DIR,process.env.SE_TEST_LANE+'-admin.json'),'utf8'));
  await adminPage.goto(login);await adminPage.waitForLoadState('networkidle');await adminPage.locator('#user_login').fill(credentials.login);await adminPage.locator('#user_pass').fill(credentials.password);await adminPage.locator('#wp-submit').click();await adminPage.waitForURL(/wp-admin/);
  const settings=origin+new URL(f.open.url).pathname.split('/sprint/')[0]+'/wp-admin/edit.php?post_type=sprint_engine_sprint&page=sprint-engine-settings';await adminPage.goto(settings);
  ok(await adminPage.locator('[data-participant-mode="built_in"]').isVisible()&&!await adminPage.locator('[data-participant-mode="external"]').isVisible(),'Settings progressively show only selected mode');
  await adminPage.locator('input[type=radio][value=external]').check();ok(await adminPage.locator('[data-participant-mode="external"]').isVisible()&&!await adminPage.locator('[data-participant-mode="built_in"]').isVisible(),'Switching modes exposes only relevant fields');
  const registrationDestination=login+'?action=register&campaign=existing',loginDestination=login+'?campaign=signin';await adminPage.locator('#sprint-engine-registration_url').fill(registrationDestination);await adminPage.locator('#sprint-engine-login_url').fill(loginDestination);await adminPage.getByRole('button',{name:'Save participant settings',exact:true}).click();await adminPage.waitForURL(/settings-updated=true/);ok(control({action:'inspect'}).config.mode==='external'&&control({action:'inspect'}).config.registration_url===registrationDestination,'Native Settings API saves selected mode and destination');
  await adminPage.locator('input[type=radio][value=built_in]').check();await adminPage.locator('input[type=checkbox][name$="[confirmation]"]').check();await adminPage.getByRole('button',{name:'Save participant settings',exact:true}).click();await adminPage.waitForURL(/settings-updated=true/);ok(control({action:'inspect'}).operational&&control({action:'inspect'}).config.registration_url===registrationDestination,'Explicit browser confirmation activates built-in and retains inactive provider URL');await adminPage.locator('input[type=radio][value=external]').check();ok(await adminPage.locator('#sprint-engine-registration_url').inputValue()===registrationDestination,'Switching back retains saved provider value');
  await adminPage.locator('#sprint-engine-participants').screenshot({path:path.join(process.env.SE_TEST_EVIDENCE_DIR,process.env.SE_TEST_LANE+'-settings-external.png')});
  await adminPage.locator('input[type=radio][value=built_in]').check();await adminPage.locator('[data-disable-native]').uncheck();ok(await adminPage.locator('[data-native-warning]').isVisible(),'Unchecked native form has explicit warning');await admin.close();
  const nojs=await browser.newContext({ignoreHTTPSErrors:true,javaScriptEnabled:false,storageState:undefined}),nojsPage=await nojs.newPage();await nojsPage.goto(login);await nojsPage.waitForLoadState('networkidle');await nojsPage.locator('#user_login').fill(credentials.login);await nojsPage.locator('#user_pass').fill(credentials.password);await nojsPage.locator('#wp-submit').click();await nojsPage.waitForURL(/wp-admin/);await nojsPage.goto(settings);await nojsPage.locator('[data-participant-mode="external"] summary').click();ok(await nojsPage.locator('#sprint-engine-registration_url').isVisible(),'No-JS administrator can open and edit alternate mode');await nojs.close();
  control({action:'deactivate'});r=await request(login+'?action=register',jar());ok(r.status===200&&r.html.includes('name="user_email"'),'Deactivation restores native global registration policy');control({action:'activate'});ok(!control({action:'inspect'}).operational,'Reactivation retains preferences but requires fresh confirmation');reset();
 }finally{await browser.close();reset();}
 fs.writeFileSync(path.join(process.env.SE_TEST_EVIDENCE_DIR,'browser-'+process.env.SE_TEST_LANE+'-results.json'),JSON.stringify({checks:checks.length,passed:checks},null,2));
}
module.exports={origin,request,jar,fields,payload,post,control,fixture,f,password,checks,ok,reset};
if(require.main===module)main().catch(e=>{console.error(e.stack);process.exitCode=1;});
