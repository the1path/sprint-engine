const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('assets/js/authoring-validation.js', 'utf8');

function editor(blockEditor = true, fallback = false) {
  const controls = {};
  const outputs = {};
  const fields = ['_sprint_engine_estimated_duration_value','_sprint_engine_estimated_minutes','_sprint_engine_completion_cta_label','_sprint_engine_completion_cta_url','_sprint_engine_mode'].map(id => {
    const error = (id.startsWith('_sprint_engine_completion_cta_') ? '_sprint_engine_completion_cta' : id) + '-error';
    const attrs = {'aria-describedby':'normal-help'};
    if (fallback && id === '_sprint_engine_completion_cta_url') attrs['aria-invalid'] = 'true';
    const listeners = {};
    const field = {id, value:'', dataset:{seError:error}, attrs, listeners,
      getAttribute:key => attrs[key] ?? null, setAttribute:(key,value) => {attrs[key]=value;}, removeAttribute:key => {delete attrs[key];},
      addEventListener:(name,fn) => {listeners[name]=fn;}, focus:() => {field.focused=true;}};
    controls[id] = field;
    if (!outputs[error]) {
      const strong = {textContent:''};
      outputs[error] = {id:error, hidden:!fallback, querySelector:() => strong};
    }
    return field;
  });
  const locks = new Set();
  const notices = new Map([['other','Other notice']]);
  const form = {addEventListener:(name,fn) => {form[name]=fn;}};
  const config = {blockEditor,maxInteger:'9223372036854775807',duration:'Duration error',minutes:'Minutes error',cta:'CTA error',notice:'Correct fields'};
  const root = {querySelectorAll:() => fields};
  const wp = {domReady:fn => fn(), data:{dispatch:store => store === 'core/editor' ? {
    lockPostSaving:id => locks.add(id), unlockPostSaving:id => locks.delete(id)
  } : {createErrorNotice:(message,options) => notices.set(options.id,message), removeNotice:id => notices.delete(id)}}};
  vm.runInNewContext(source,{wp, window:{sprintEngineAuthoringValidation:config}, URL, document:{getElementById:id => id === 'sprint_engine_structure' ? root : id === 'post' ? form : controls[id] || outputs[id], querySelectorAll:() => []}});
  function input(id,value) {controls[id].value=value;controls[id].listeners.input();}
  return {controls,outputs,locks,notices,form,input};
}

test('duration and minutes follow PHP bounds and clear help-preserving errors', () => {
  const e=editor();
  for (const [id,invalid,valid] of [
    ['_sprint_engine_estimated_duration_value',['4-6','four','0','-1','1.234','NaN','Infinity','1000000000','4\n'],['','4','4.5','0.5','999999999.99']],
    ['_sprint_engine_estimated_minutes',['60 mins','0','-1','1.5','one hour','01','9223372036854775808','1\n'],['','1','15','60','120','9223372036854775807']]
  ]) {
    for (const value of invalid) {e.input(id,value);assert.equal(e.controls[id].attrs['aria-invalid'],'true',value);assert.equal(e.locks.size,1);assert(e.controls[id].attrs['aria-describedby'].includes('normal-help'));}
    for (const value of valid) {e.input(id,value);assert.equal(e.controls[id].attrs['aria-invalid'],undefined,value);assert.equal(e.controls[id].attrs['aria-describedby'],'normal-help');assert.equal(e.locks.size,0);}
  }
});

test('multiple errors, CTA pairs and unrelated notices remain independent', () => {
  const e=editor();
  e.input('_sprint_engine_estimated_duration_value','4-6');e.input('_sprint_engine_completion_cta_label','Next');
  e.input('_sprint_engine_estimated_duration_value','4.5');assert.equal(e.locks.size,1);
  for (const url of ['example.org','ftp://example.org','https://','https://bad host','https://bad_host','https://-bad.org','https://example.org/<b>x</b>']) {
    e.input('_sprint_engine_completion_cta_url',url);assert.equal(e.locks.size,1,url);assert.equal(e.notices.size,2);
  }
  e.input('_sprint_engine_completion_cta_url','https://example.org/next?a=1&b=2');assert.equal(e.locks.size,0);assert.equal(e.notices.size,1);assert(e.notices.has('other'));
  e.input('_sprint_engine_completion_cta_label','');assert.equal(e.locks.size,1);
  e.input('_sprint_engine_completion_cta_url','');assert.equal(e.locks.size,0);
});

test('server CTA error survives initialization and clears on interaction', () => {
  const e=editor(true,true);assert.equal(e.locks.size,1);
  e.input('_sprint_engine_completion_cta_url','');assert.equal(e.locks.size,0);
});

test('Classic submit validates, prevents invalid submission and focuses field', () => {
  const e=editor(false);let prevented=false;
  e.controls._sprint_engine_estimated_minutes.value='60 mins';e.form.submit({preventDefault:() => {prevented=true;}});
  assert(prevented);assert(e.controls._sprint_engine_estimated_minutes.focused);assert.equal(e.locks.size,0);
  e.input('_sprint_engine_estimated_minutes','45');prevented=false;e.form.submit({preventDefault:() => {prevented=true;}});assert(!prevented);
});
