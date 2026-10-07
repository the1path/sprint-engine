const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('assets/js/structure-admin.js','utf8');

function lifecycle(withEditor=true) {
  const requests=[]; const callbacks=new Set();
  const state={saving:false,autosaving:false,success:false,status:'auto-draft',id:42};
  let replaced=false, error='';
  const empty={each(){},on(){return this;},toArray(){return [];}};
  const placeholder={attr:key=>key==='data-sprint'?'42':'nonce',replaceWith(){replaced=true;},find(){return {addClass(){return this;},text(value){error=value;}};}};
  const replacement={find(){return {sortable(){},each(){},text(){}};}};
  function $(value) {
    if(typeof value==='function') {value();return;}
    if(value==='.se-structure-locked') return {each:fn=>fn.call(placeholder)};
    if(value===placeholder) return placeholder;
    if(value==='<fragment>') return replacement;
    return empty;
  }
  $.ajax=options=>{const req={options,done(fn){this.success=fn;return this;},fail(fn){this.failure=fn;return this;}};requests.push(req);return req;};
  const editor={isSavingPost:()=>state.saving,isAutosavingPost:()=>state.autosaving,didPostSaveRequestSucceed:()=>state.success,getCurrentPostAttribute:()=>state.status,getCurrentPostId:()=>state.id};
  const data={select:()=>editor,subscribe:fn=>{callbacks.add(fn);return ()=>callbacks.delete(fn);}};
  const window=withEditor?{wp:{data}}:{};
  vm.runInNewContext(source,{jQuery:$,window,document:{},sprintEngineStructure:{url:'/ajax',refreshError:'Save again to retry'}});
  const update=values=>{Object.assign(state,values);[...callbacks].forEach(fn=>fn());};
  return {requests,update,get replaced(){return replaced;},get error(){return error;},callbacks};
}
test('first explicit successful save requests one read-only fragment and unsubscribes after activation',()=>{
  const e=lifecycle();assert.equal(e.requests.length,0);
  e.update({saving:true}); e.update({saving:false,success:true,status:'draft'});
  assert.equal(e.requests.length,1);
  assert.equal(e.requests[0].options.data.operation,'refresh');
  assert.deepEqual(Object.keys(e.requests[0].options.data).sort(),['action','nonce','operation','sprint']);
  e.update({});assert.equal(e.requests.length,1);
  e.requests[0].success({success:true,data:{html:'<fragment>'}});
  assert(e.replaced);assert.equal(e.callbacks.size,0);
});
test('autosaving and failed explicit saves never activate structure',()=>{
  const e=lifecycle();
  e.update({saving:true,autosaving:true});e.update({saving:false,autosaving:false,success:true,status:'draft'});
  assert.equal(e.requests.length,0);
  e.update({saving:true});e.update({saving:false,success:false});assert.equal(e.requests.length,0);
  e.update({saving:true});e.update({saving:false,success:true});assert.equal(e.requests.length,1);
});
test('saved state alone, auto-draft status and a foreign editor ID cannot activate',()=>{
  const e=lifecycle();e.update({success:true});assert.equal(e.requests.length,0);
  e.update({saving:true});e.update({saving:false});assert.equal(e.requests.length,0);
  e.update({saving:true});e.update({saving:false,status:'draft',id:99});assert.equal(e.requests.length,0);
});
test('failed fragment reports recovery, avoids polling and retries on a later explicit save',()=>{
  const e=lifecycle();e.update({saving:true});e.update({saving:false,success:true,status:'draft'});
  e.requests[0].failure();assert(!e.replaced);assert.equal(e.error,'Save again to retry');
  e.update({});assert.equal(e.requests.length,1);
  e.update({saving:true});e.update({saving:false});assert.equal(e.requests.length,2);
});
test('invalid fragment envelope retains the locked placeholder',()=>{
  const e=lifecycle();e.update({saving:true});e.update({saving:false,success:true,status:'draft'});
  e.requests[0].success({success:false});assert(!e.replaced);assert(e.error);
});
test('Classic Editor without wp.data leaves native save behaviour intact',()=>{
  const e=lifecycle(false);assert.equal(e.requests.length,0);assert.equal(e.callbacks.size,0);
});
