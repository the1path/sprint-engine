'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('assets/js/dashboard.js','utf8');
function setup(fetch, confirm = () => true) {
  let click;
  const navigations = [];
  const cards = [1,2].map(id => {
    const status = { textContent:'' }, attrs = {};
    const button = {disabled:false,dataset:{endpoint:`https://example.test/wp-json/sprint-engine/v1/sprints/${id}/restart`,runnerUrl:`https://example.test/sprint/test-${id}/`},setAttribute:(k,v)=>attrs[k]=v,removeAttribute:k=>delete attrs[k],closest:()=>({querySelector:()=>status})};
    return {button,status,attrs};
  });
  const config = {nonce:'current-nonce',confirm:'Keep history?',busy:'Restarting',error:'Try again'};
  vm.runInNewContext(source, {URL,document:{addEventListener:(_,fn)=>click=fn},window:{sprintEngineDashboard:config,confirm,location:{href:'https://example.test/sprint-engine/dashboard/',origin:'https://example.test',assign:url=>navigations.push(url)}},fetch});
  return {cards,navigations,config,click:(index=0)=>click({target:{closest:()=>cards[index].button}})};
}
test('Cancel causes no request, pending state, status message or navigation',async()=>{
  let calls=0; const t=setup(()=>calls++,()=>false); await t.click();
  assert.equal(calls,0); assert.equal(t.cards[0].button.disabled,false); assert.equal(t.cards[0].status.textContent,''); assert.deepEqual(t.cards[0].attrs,{}); assert.deepEqual(t.navigations,[]);
});
test('Multiple cards scope pending feedback and authenticated requests; duplicate click is ignored',async()=>{
  let resolve; const calls=[]; const t=setup((url,options)=>{calls.push({url,options}); return new Promise(r=>resolve=r);});
  const pending=t.click(1); await t.click(1);
  assert.equal(calls.length,1); assert.equal(calls[0].url,t.cards[1].button.dataset.endpoint); assert.equal(calls[0].options.method,'POST'); assert.equal(calls[0].options.credentials,'same-origin'); assert.equal(calls[0].options.headers['X-WP-Nonce'],'current-nonce'); assert.equal(calls[0].options.body,undefined);
  assert.equal(t.cards[1].button.disabled,true); assert.equal(t.cards[1].attrs['aria-busy'],'true'); assert.equal(t.cards[0].button.disabled,false); assert.equal(t.cards[0].status.textContent,'');
  resolve({ok:true,json:async()=>({success:true,state:'in_progress',runner_url:t.cards[1].button.dataset.runnerUrl})}); await pending; assert.deepEqual(t.navigations,[t.cards[1].button.dataset.runnerUrl]);
});
for (const scenario of ['network','bad-json','403','false-success','completed','wrong-url','foreign-endpoint','foreign-runner']) {
  test(`${scenario} cannot navigate and restores the clicked control`,async()=>{
    let calls=0;
    const t=setup(async()=>{calls++; if(scenario==='network')throw Error(); return {ok:scenario!=='403',json:async()=>{if(scenario==='bad-json')throw Error(); return {success:scenario!=='false-success',state:scenario==='completed'?'completed':'in_progress',runner_url:scenario==='wrong-url'?'https://foreign.test/':t.cards[0].button.dataset.runnerUrl};}};});
    if(scenario==='foreign-endpoint')t.cards[0].button.dataset.endpoint='https://foreign.test/restart';
    if(scenario==='foreign-runner')t.cards[0].button.dataset.runnerUrl='https://foreign.test/sprint/';
    await t.click(); assert.deepEqual(t.navigations,[]); assert.equal(t.cards[0].button.disabled,false); assert.equal(t.cards[0].attrs['aria-busy'],undefined); assert.equal(t.cards[0].status.textContent,t.config.error); assert.equal(t.cards[1].status.textContent,'');
    if(scenario.startsWith('foreign-'))assert.equal(calls,0);
  });
}
test('A failed request can be retried after another explicit confirmation',async()=>{
  let calls=0,confirmed=0;
  const t=setup(async()=>({ok:++calls>1,json:async()=>({success:true,state:'in_progress',runner_url:t.cards[0].button.dataset.runnerUrl})}),()=>{confirmed++;return true;});
  await t.click(); await t.click(); assert.equal(confirmed,2); assert.deepEqual(t.navigations,[t.cards[0].button.dataset.runnerUrl]);
});
