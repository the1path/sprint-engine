'use strict';
const fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process');
const {origin,request,jar,payload,post,control,f,password,checks,ok,reset}=require('./participant-registration.cjs');
const args=process.env.SE_TEST_PHP_INI?['-c',process.env.SE_TEST_PHP_INI]:[];
const snapshot=()=>JSON.parse(cp.execFileSync(process.env.SE_TEST_PHP||'php',[...args,'tests/fixtures/participant-registration-pro.php','snapshot'],{encoding:'utf8',env:process.env}));
async function main(){
 control({action:'pro',order:'core-first'});control({action:'pro-policy'});
 cp.execFileSync(process.env.SE_TEST_PHP||'php',[...args,'tests/fixtures/participant-registration-pro.php','setup'],{env:process.env});
 for(const order of ['core-first','pro-first']){
  control({action:'pro',order});control({action:'pro-classifier',enabled:false});reset();const before=snapshot();
  let response=await request(f.open.url,jar());ok(response.status===200&&!response.html.includes('name="password"')&&response.html.includes('>Sign in</a>')&&response.html.includes('registration gates=1 denials=0'),order+' old Pro has neutral missing-classifier fallback with one public-gate call');
  response=await request(f.second.url,jar());ok(response.status===403&&!response.html.includes('PRIVATE')&&!response.html.includes('name="password"')&&response.html.includes('registration gates=1 denials=1'),order+' native Pro restriction retains 403 and one denial action');
  control({action:'pro-classifier',enabled:true});let c=jar();response=await request(f.open.url,c);ok(response.status===200&&response.html.includes('name="password"')&&response.html.includes('registration gates=1 denials=0'),order+' explicit test-only classifier offers eligible registration without changing gate');
  const data=payload(response.html);response=await post(f.open.url,c,data);ok(response.status===303&&response.headers.location===f.open.url,order+' classified registration uses native sign-on');response=await request(f.open.url,c);ok(response.status===200&&response.html.includes('Ready to start'),order+' fresh identity still passes actual Pro member access');
  response=await request(f.second.url,c);ok(response.status===403&&response.html.includes('registration gates=0 denials=1'),order+' new account gets no restricted entitlement');
  const login=origin+new URL(f.open.url).pathname.split('/sprint/')[0]+'/wp-login.php';const existing=jar();await request(login,existing);response=await post(login,existing,{log:f.member.email,pwd:f.member.password,'wp-submit':'Log In',redirect_to:f.open.url,testcookie:'1'});ok([302,303].includes(response.status),order+' existing member still signs in natively by email');
  response=await request(f.open.url,existing);ok(response.status===200&&response.html.includes('Ready to start'),order+' existing member Runner Ready unchanged');
  ok(JSON.stringify(snapshot())===JSON.stringify(before),order+' exact Core/Pro rows, retained Workspace/Notes/history/resources/objects and existing identity remain unchanged');
 }
 control({action:'pro-classifier',enabled:false});fs.writeFileSync(path.join(process.env.SE_TEST_EVIDENCE_DIR,'pro-compatibility-results.json'),JSON.stringify({checks:checks.length,passed:checks},null,2));
}
main().catch(error=>{console.error(error.stack);process.exitCode=1});
