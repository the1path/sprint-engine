'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('assets/js/runner.js','utf8');
function setup(fetch,confirm = () => true) {
 let click; const attributes = {}; const button = {disabled:false,addEventListener:(_,fn)=>{click=fn;},setAttribute:(k,v)=>{attributes[k]=v;},removeAttribute:k=>{delete attributes[k];}};
 const status = {textContent:''}; const navigations=[];
 const config={endpoint:'/wp-json/sprint-engine/v1/sprints/1/start',nonce:'test',runnerUrl:'https://example.test/sprint/test/',busy:'Starting',error:'Retry or reload'};
 vm.runInNewContext(source,{document:{getElementById:id=>id==='se-runner-action'?button:status},window:{confirm,sprintEngineRunner:config,location:{assign:url=>navigations.push(url)}},fetch});
 return {click:()=>click(),button,status,attributes,navigations,config};
}
test('In-flight duplicate clicks send only one authenticated POST and wait for confirmation',async()=>{
 let resolve; let calls=0;
 const t=setup((url,options)=>{++calls;assert.equal(options.method,'POST');assert.equal(options.credentials,'same-origin');assert.equal(options.headers['X-WP-Nonce'],'test');return new Promise(r=>{resolve=r;});});
 const pending=t.click();await t.click();assert.equal(calls,1);assert.equal(t.button.disabled,true);assert.equal(t.attributes['aria-busy'],'true');assert.equal(t.status.textContent,'Starting');assert.deepEqual(t.navigations,[]);
 resolve({ok:true,json:async()=>({success:true,state:'in_progress',runner_url:t.config.runnerUrl})});await pending;assert.deepEqual(t.navigations,[t.config.runnerUrl]);
});
for(const scenario of ['network','invalid-json','403','false-success','wrong-state','wrong-url']) {
 test(scenario+' keeps screen, reports uncertainty, and allows retry',async()=>{
  const t=setup(async()=>{if(scenario==='network'){throw Error('offline');}return {ok:scenario!=='403',json:async()=>{if(scenario==='invalid-json'){throw Error('parse');}return {success:scenario!=='false-success',state:scenario==='wrong-state'?'not_started':'in_progress',runner_url:scenario==='wrong-url'?'https://foreign.invalid/':t.config.runnerUrl};}};});
  await t.click();assert.deepEqual(t.navigations,[]);assert.equal(t.button.disabled,false);assert.equal(t.attributes['aria-busy'],undefined);assert.equal(t.status.textContent,t.config.error);await t.click();assert.equal(t.button.disabled,false);
 });
}
test('Final completion uses the same canonical URL',async()=>{const t=setup(async()=>({ok:true,json:async()=>({success:true,state:'completed',runner_url:t.config.runnerUrl})}));await t.click();assert.deepEqual(t.navigations,[t.config.runnerUrl]);});

 test('Restart cancellation sends no request and leaves idle controls', async()=>{
 let calls=0; const t=setup(()=>{calls++;},()=>false); t.config.confirm='Keep history?';
 await t.click(); assert.equal(calls,0);assert.equal(t.button.disabled,false);assert.equal(t.status.textContent,'');assert.equal(t.attributes['aria-busy'],undefined);
 });
 test('Restart confirms, retries a failure, and validates canonical navigation',async()=>{
 let calls=0, confirmations=0;
 const t=setup(async()=>({ok:++calls>1,json:async()=>({success:true,state:'in_progress',runner_url:t.config.runnerUrl})}),()=>{confirmations++;return true;});
 t.config.confirm='Keep history?';await t.click();assert.equal(t.button.disabled,false);assert.deepEqual(t.navigations,[]);await t.click();assert.equal(confirmations,2);assert.deepEqual(t.navigations,[t.config.runnerUrl]);
 });
