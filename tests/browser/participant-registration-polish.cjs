'use strict';
const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto'),{chromium}=require('playwright');
const {request,jar,payload,post,control,f,password,checks,ok,reset}=require('./participant-registration.cjs');
const lane=process.env.SE_TEST_LANE,root=process.env.SE_TEST_EVIDENCE_DIR;
async function main(){
 control({action:'pro',order:'off'});control({action:'pro-classifier',enabled:false});reset();
 const baseline=JSON.stringify(control({action:'inspect'}).rows),first='Parallel'+crypto.randomBytes(6).toString('hex'),last='Participant';
 const a=jar(),b=jar(),forms=await Promise.all([request(f.open.url,a),request(f.open.url,b)]),data=forms.map(form=>({...payload(form.html),first_name:first,last_name:last}));
 const concurrent=await Promise.all([post(f.open.url,a,data[0]),post(f.open.url,b,data[1])]);const users=data.map(d=>control({action:'inspect',email:d.email}).user);
 ok(concurrent.every(r=>r.status===303)&&users.map(u=>u.login).sort().join('|')===[first.toLowerCase()+'.participant',first.toLowerCase()+'.participant.2'].sort().join('|'),'Independent workers serialize same-name distinct-email logins into unique readable candidates');
 ok(users[0].id!==users[1].id&&users.every(u=>u.first_name===first&&u.last_name===last&&u.roles.join()==='subscriber'),'Concurrent readable usernames preserve exact separate native profiles and safe roles');
 ok(JSON.stringify(control({action:'inspect'}).rows)===baseline,'Same-name registration creates no attempt or Step progress');
 reset();const response=await request(f.open.url);ok(response.html.includes('registration.js')&&!response.html.includes('sprintEngineRunner'),'Only eligible form receives visibility script, without member write configuration');
 const unknown=await request(new URL('/unavailable-polish-route/',f.open.url).href);ok(!unknown.html.includes('assets/js/registration.js'),'Unrelated/unknown page receives no registration script');
 const browser=await chromium.launch({channel:'chrome',headless:true,args:['--host-resolver-rules=MAP signup.se0313.test 127.0.0.1','--no-proxy-server']});
 try{
  const context=await browser.newContext({ignoreHTTPSErrors:true,viewport:{width:1280,height:1100}}),page=await context.newPage();await page.goto(f.open.url);await page.waitForLoadState('networkidle');
  ok((await page.locator('.se-runner__intro').first().innerText())==='Create an account to get started, save your progress and return whenever you’re ready.','Approved neutral introductory copy is rendered');
  const firstField=page.locator('[name=first_name]'),lastField=page.locator('[name=last_name]'),pw=page.locator('[name=password]'),toggle=page.getByRole('button',{name:'Show password',exact:true});
  const before=await firstField.boundingBox(),after=await lastField.boundingBox();ok(Math.abs(before.y-after.y)<1&&before.x<after.x,'Desktop name fields share a row in natural DOM order');await page.screenshot({path:path.join(root,'polish-'+lane+'-desktop.png'),fullPage:true});
  await pw.fill(password);await pw.focus();await page.keyboard.press('Tab');ok(await toggle.evaluate(el=>el===document.activeElement),'Password visibility action participates in native keyboard focus order');
  const network=[];page.on('request',r=>network.push(r.method()+' '+r.url()));await page.keyboard.press('Space');
  ok(await pw.getAttribute('type')==='text'&&await page.getByRole('button',{name:'Hide password',exact:true}).getAttribute('aria-controls')==='se-registration-password'&&await pw.inputValue()===password,'Space shows exact password locally and updates accessible action label');
  ok(await page.getByRole('button',{name:'Hide password',exact:true}).evaluate(el=>el===document.activeElement)&&network.length===0,'Showing password preserves focus and makes no request');
  await page.keyboard.press('Enter');ok(await pw.getAttribute('type')==='password'&&await page.getByRole('button',{name:'Show password',exact:true}).getAttribute('aria-label')==='Show password','Enter hides password and restores accessible action label');
  const gap=await page.evaluate(()=>{const form=document.querySelector('.se-runner__registration'),next=form.nextElementSibling;return next.getBoundingClientRect().top-form.getBoundingClientRect().bottom;});ok(gap>=20,'Create account and existing-account sign-in have modest breathing room');
  for(const [width,rtl,zoom,long]of [[390,false,1,false],[320,false,1,false],[1280,true,1,false],[320,true,2,true]]){
   await page.setViewportSize({width,height:1100});await page.evaluate(({rtl,zoom,long})=>{document.documentElement.dir=rtl?'rtl':'ltr';document.documentElement.style.zoom=zoom;if(long){document.querySelectorAll('.se-runner__registration label').forEach(el=>el.textContent+=' — International participant details '.repeat(3));const button=document.querySelector('[data-password-toggle]');button.textContent='Show password '.repeat(7);}}, {rtl,zoom,long});
   const names=await page.locator('.se-runner__registration-names').evaluate(el=>[...el.querySelectorAll('input')].map(i=>({x:i.getBoundingClientRect().x,y:i.getBoundingClientRect().y})));
   ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)&&(width===1280?Math.abs(names[0].y-names[1].y)<1&&names[0].x>names[1].x:names[1].y>names[0].y),'Responsive name/password controls reflow at '+width+'px '+(rtl?'RTL':'LTR')+' zoom '+zoom+(long?' with long labels':''));
   await pw.fill('');await page.screenshot({path:path.join(root,'polish-'+lane+'-'+width+'-'+(rtl?'rtl':'ltr')+'-'+zoom+'.png'),fullPage:true});
  }
  await page.setViewportSize({width:1280,height:1100});await page.goto(f.open.url);await firstField.fill('Synthetic');await lastField.fill('Participant');await page.locator('[name=email]').fill('polish-'+crypto.randomBytes(8).toString('hex')+'@example.invalid');await pw.fill('tiny');
  await page.getByRole('button',{name:'Create account',exact:true}).click();await page.waitForLoadState('load');await page.locator('input[aria-invalid=true]').first().waitFor();await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
  ok(await pw.inputValue()===''&&await pw.getAttribute('type')==='password'&&await firstField.inputValue()==='Synthetic'&&!((await page.content()).includes('value="tiny"')),'Error recovery preserves safe names and resets password value/visibility without reflection');
  ok(await page.evaluate(()=>document.activeElement?.getAttribute('aria-label')==='Check your details'),'Native error summary retains autofocus after layout/visibility enhancement');await page.screenshot({path:path.join(root,'polish-'+lane+'-errors.png'),fullPage:true});
  await pw.fill(password);await page.getByRole('button',{name:'Show password',exact:true}).click();await page.getByRole('button',{name:'Create account',exact:true}).click();await page.waitForURL(f.open.url);await page.getByText('Ready to start',{exact:true}).waitFor();
  ok(page.url()===f.open.url&&JSON.stringify(control({action:'inspect'}).rows)===baseline,'Corrected enhanced form returns exactly to canonical Ready without automatic Start');
  const nojs=await browser.newContext({ignoreHTTPSErrors:true,javaScriptEnabled:false}),plain=await nojs.newPage();await plain.goto(f.open.url);
  ok(await plain.locator('[name=password]').getAttribute('type')==='password'&&!await plain.locator('[data-password-toggle]').isVisible(),'No-JS form retains ordinary password field with no unusable visibility action');await nojs.close();
  control({action:'config',mode:'built_in',confirm:false});
  const admin=await browser.newContext({ignoreHTTPSErrors:true}),ap=await admin.newPage(),credentials=JSON.parse(fs.readFileSync(path.join(root,lane+'-admin.json'),'utf8')),base=new URL(f.open.url).pathname.split('/sprint/')[0],origin=new URL(f.open.url).origin;
  await ap.goto(origin+base+'/wp-login.php');await ap.waitForLoadState('networkidle');await ap.locator('#user_login').fill(credentials.login);await ap.locator('#user_pass').fill(credentials.password);await ap.locator('#wp-submit').click();await ap.waitForURL(/wp-admin/);const settings=origin+base+'/wp-admin/edit.php?post_type=sprint_engine_sprint&page=sprint-engine-settings';await ap.goto(settings);
  const group=ap.getByRole('group',{name:'Confirm public account creation',exact:true}),confirm=group.locator('input[type=checkbox]');ok(await group.isVisible()&&!await confirm.isChecked()&&await confirm.getAttribute('aria-describedby')==='sprint-engine-confirmation-help'&&!control({action:'inspect'}).operational,'Prominent native confirmation group remains explicitly unchecked, described and unconfirmed');
  await ap.locator('#sprint-engine-participants').screenshot({path:path.join(root,'polish-'+lane+'-confirmation.png')});
  const adminNoJs=await browser.newContext({ignoreHTTPSErrors:true,javaScriptEnabled:false,storageState:await admin.storageState()}),np=await adminNoJs.newPage();await np.goto(settings);ok(await np.getByRole('group',{name:'Confirm public account creation',exact:true}).isVisible()&&!await np.getByRole('group',{name:'Confirm public account creation',exact:true}).locator('input[type=checkbox]').isChecked(),'No-JS administrator sees the same explicit unconfirmed security group');await adminNoJs.close();await admin.close();
 }finally{await browser.close();reset();}
 fs.writeFileSync(path.join(root,'polish-browser-'+lane+'.json'),JSON.stringify({checks:checks.length,passed:checks},null,2));
}
main().catch(e=>{console.error(e.stack);process.exitCode=1});
