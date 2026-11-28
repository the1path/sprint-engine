'use strict';
const fs=require('node:fs'),path=require('node:path');
const {origin,f,request,jar,post,payload,control,checks,ok,reset}=require('./participant-registration.cjs');
const mailsFor=email=>fs.readFileSync(path.join(process.env.SE_TEST_EVIDENCE_DIR,'mail/captured.jsonl'),'utf8').trim().split('\n').filter(Boolean).map(line=>JSON.parse(line)).filter(mail=>mail.message.includes(email)||JSON.stringify(mail.to).includes(email));
async function main(){
 control({action:'pro-classifier',enabled:true});reset();let c=jar(),r=await request(f.open.url,c),data=payload(r.html);const before=control({action:'inspect'}).users;
 r=await post(f.open.url,c,{...data,first_name:'a'.repeat(20000)});ok(r.status===403&&!r.html.includes(data.password),'Oversized form body is rejected without password reflection');
 r=await request(f.open.url,c,'POST',JSON.stringify(data),{'Content-Type':'application/json'});ok(r.status===403,'JSON cannot bypass native form field/body allowlist');
 r=await request(f.open.url,c,'PUT',new URLSearchParams(data).toString(),{'Content-Type':'application/x-www-form-urlencoded'});ok(r.status===405&&!r.html.includes('name="password"'),'Non-form write method has no registration processing');
 ok(control({action:'inspect'}).users===before,'Malformed bodies/methods create no accounts');
 c=jar();r=await request(f.open.url,c);data=payload(r.html);const originalJar=new Map(c);r=await post(f.open.url,c,data);ok(r.status===303,'Successful notification case creates through native flow');
 let mail=mailsFor(data.email);ok(mail.length===1&&JSON.stringify(mail[0].to).includes('admin@example.invalid')&&!JSON.stringify(mail[0].to).includes(data.email),'Exactly one initial native admin notification and no participant setup/welcome email');await request(f.open.url,c);await post(f.open.url,c,data);ok(mailsFor(data.email).length===1,'Refresh/stale resubmission does not repeat native notification');
 const count=control({action:'inspect'}).users;reset();r=await post(f.open.url,originalJar,{...data,email:'replay-after-reconfirm-'+Date.now()+'@example.invalid'});ok(r.status===403&&control({action:'inspect'}).users===count,'Used token cannot create a different account after replay-state cleanup and operator reconfirmation');
 reset('reject');c=jar();r=await request(f.open.url,c);data=payload(r.html);r=await post(f.open.url,c,data);await request(f.open.url,c);ok(r.html.includes('Your account was created')&&mailsFor(data.email).length===1,'Persisted account with denied authentication retains one notification only');
 const rows=JSON.stringify(control({action:'inspect'}).rows);control({action:'config',behavior:''});const login=origin+new URL(f.open.url).pathname.split('/sprint/')[0]+'/wp-login.php';await request(login,c);await post(login,c,{log:f.member.email,pwd:f.member.password,'wp-submit':'Log In',redirect_to:f.open.url,testcookie:'1'});r=await request(f.open.url,c);ok(r.status===409&&!r.html.includes('Ready to start')&&!r.html.includes('PRIVATE'),'Actual different native identity cannot satisfy pending expected-account receipt');ok(rows===JSON.stringify(control({action:'inspect'}).rows),'Identity mismatch is checked before any member resume/progress operation');
 reset();fs.writeFileSync(path.join(process.env.SE_TEST_EVIDENCE_DIR,'boundaries-'+process.env.SE_TEST_LANE+'-results.json'),JSON.stringify({checks:checks.length,passed:checks},null,2));
}
main().catch(error=>{console.error(error.stack);process.exitCode=1});
