'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('assets/js/runner.js','utf8');
function setup(fetch,confirm = () => true, midRestart = false) {
 let click; const attributes = {}; const button = {disabled:false,addEventListener:(_,fn)=>{click=fn;},setAttribute:(k,v)=>{attributes[k]=v;},removeAttribute:k=>{delete attributes[k];}};
 const status = {textContent:''}; const navigations=[];
 const config={endpoint:'/wp-json/sprint-engine/v1/sprints/1/start',nonce:'test',runnerUrl:'https://example.test/sprint/test/',busy:'Starting',error:'Retry or reload',attemptId:17,restartEndpoint:'/wp-json/sprint-engine/v1/sprints/1/restart',restartBusy:'Restarting'};
 const restartEvents={},dialogEvents={};
 const restart={disabled:false,addEventListener:(name,fn)=>restartEvents[name]=fn};
 const dialog={returnValue:'',open:false,showModal(){this.open=true;},addEventListener:(name,fn)=>dialogEvents[name]=fn};
 const elements={'se-runner-action':button,'se-runner-status':status,'se-runner-restart':midRestart?restart:null,'se-restart-dialog':midRestart?dialog:null};
 vm.runInNewContext(source,{document:{getElementById:id=>elements[id]||null},window:{confirm,sprintEngineRunner:config,location:{assign:url=>navigations.push(url)}},fetch});
 return {click:()=>click(),button,status,attributes,navigations,config,restart,dialog,openRestart:()=>restartEvents.click(),closeRestart:(value)=>{dialog.returnValue=value;dialog.open=false;return dialogEvents.close();},escapeRestart:()=>dialogEvents.cancel()};
}
test('In-flight duplicate clicks send only one authenticated POST and wait for confirmation',async()=>{
 let resolve; let calls=0;
 const t=setup((url,options)=>{++calls;assert.equal(options.method,'POST');assert.equal(options.credentials,'same-origin');assert.equal(options.headers['X-WP-Nonce'],'test');assert.deepEqual(JSON.parse(options.body),{expected_attempt_id:17});return new Promise(r=>{resolve=r;});});
 const pending=t.click();await t.click();assert.equal(calls,1);assert.equal(t.button.disabled,true);assert.equal(t.attributes['aria-busy'],'true');assert.equal(t.status.textContent,'Starting');assert.deepEqual(t.navigations,[]);
 resolve({ok:true,json:async()=>({success:true,state:'in_progress',runner_url:t.config.runnerUrl})});await pending;assert.deepEqual(t.navigations,[t.config.runnerUrl]);
});
for(const scenario of ['network','invalid-json','403','false-success','wrong-state','wrong-url']) {
 test(scenario+' keeps screen, reports uncertainty, and allows retry',async()=>{
  const t=setup(async()=>{if(scenario==='network'){throw Error('offline');}return {ok:scenario!=='403',json:async()=>{if(scenario==='invalid-json'){throw Error('parse');}return {success:scenario!=='false-success',state:scenario==='wrong-state'?'not_started':'in_progress',runner_url:scenario==='wrong-url'?'https://foreign.invalid/':t.config.runnerUrl};}};});
  await t.click();assert.deepEqual(t.navigations,[]);assert.equal(t.button.disabled,false);assert.equal(t.attributes['aria-busy'],undefined);assert.equal(t.status.textContent,t.config.error);await t.click();assert.equal(t.button.disabled,false);
 });
}

test('Mid-Sprint dialog opens without writes; Cancel and Escape leave controls/status unchanged',async()=>{
 let calls=0;const t=setup(()=>calls++,()=>{throw Error('Unexpected native confirmation');},true);
 t.openRestart();assert.equal(t.dialog.open,true);assert.equal(calls,0);await t.closeRestart('cancel');
 t.openRestart();t.dialog.returnValue='restart';t.escapeRestart();await t.closeRestart(t.dialog.returnValue);
 assert.equal(calls,0);assert.equal(t.button.disabled,false);assert.equal(t.restart.disabled,false);assert.equal(t.status.textContent,'');
});
test('Mid-Sprint restart binds source attempt, coordinates both buttons, rejects duplicate/competing clicks',async()=>{
 let resolve;const calls=[];const t=setup((url,options)=>{calls.push({url,options});return new Promise(r=>resolve=r);},()=>true,true);
 t.openRestart();const pending=t.closeRestart('restart');await t.click();t.openRestart();await t.closeRestart('restart');
 assert.equal(calls.length,1);assert.equal(calls[0].url,t.config.restartEndpoint);assert.deepEqual(JSON.parse(calls[0].options.body),{expected_attempt_id:17});
 assert.equal(t.button.disabled,true);assert.equal(t.restart.disabled,true);assert.equal(t.status.textContent,'Restarting');assert.equal(t.dialog.open,false);
 resolve({ok:true,json:async()=>({success:true,state:'in_progress',runner_url:t.config.runnerUrl})});await pending;assert.deepEqual(t.navigations,[t.config.runnerUrl]);
});
test('Stale mid-Sprint restart leaves current content and restores both controls for reload',async()=>{
 const t=setup(async()=>({ok:false,json:async()=>({code:'sprint_engine_write_409'})}),()=>true,true);
 t.openRestart();await t.closeRestart('restart');assert.equal(t.button.disabled,false);assert.equal(t.restart.disabled,false);assert.equal(t.status.textContent,t.config.error);assert.deepEqual(t.navigations,[]);
});
test('Completion blocks restart while pending and restores both controls on failure',async()=>{
 let resolve;const t=setup(()=>new Promise(r=>resolve=r),()=>true,true);const pending=t.click();t.openRestart();assert.equal(t.dialog.open,false);assert.equal(t.restart.disabled,true);
 resolve({ok:false,json:async()=>({})});await pending;assert.equal(t.restart.disabled,false);assert.equal(t.button.disabled,false);
});
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
