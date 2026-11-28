'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('assets/js/registration.js','utf8');
function run(complete=true,labels=true){
 const events={},pageEvents={},attrs={};let text='',type='password';
 const input={get type(){return type;},set type(v){assert(['password','text'].includes(v));type=v;},get value(){throw Error('Visibility must never read a password');},set value(v){throw Error('Visibility must never replace a password');}};
 const button={hidden:true,dataset:labels?{showLabel:'Afficher le mot de passe',hideLabel:'Masquer le mot de passe'}:{},set textContent(v){text=v;},get textContent(){return text;},setAttribute(k,v){attrs[k]=v;},addEventListener(k,f){events[k]=f;}};
 const form={querySelector(s){return s==='[data-password-toggle]'?button:(complete?input:null);},addEventListener(k,f){events[k]=f;}};
 const context={document:{querySelectorAll(s){assert.equal(s,'.se-runner__registration');return [form];}},window:{addEventListener(k,f){pageEvents[k]=f;}},fetch(){throw Error('Visibility must never send requests');},localStorage:new Proxy({},{get(){throw Error('Visibility must never store credentials');}})};
 vm.runInNewContext(source,context);return {input,button,events,pageEvents,attrs};
}
test('Visibility preserves native password values and exposes only local labeled action',()=>{
 const r=run();assert.equal(r.button.hidden,false);assert.equal(r.input.type,'password');assert.equal(r.attrs['aria-label'],'Afficher le mot de passe');
 r.events.click();assert.equal(r.input.type,'text');assert.equal(r.button.textContent,'Masquer le mot de passe');assert.equal(r.attrs['aria-label'],r.button.textContent);
 r.events.click();assert.equal(r.input.type,'password');assert.equal(r.button.textContent,'Afficher le mot de passe');
});
test('Submit and page exit restore hidden state without copying or retaining credentials',()=>{
 const r=run();r.events.click();r.events.submit();assert.equal(r.input.type,'password');r.events.click();r.pageEvents.pagehide();assert.equal(r.input.type,'password');assert.equal(r.attrs['aria-label'],'Afficher le mot de passe');
});
test('Incomplete forms or missing translated labels keep the optional action hidden',()=>{
 assert.equal(run(false).button.hidden,true);assert.equal(run(true,false).button.hidden,true);
});
test('Pages without a registration form have no side effects',()=>{
 vm.runInNewContext(source,{document:{querySelectorAll(){return [];}},window:new Proxy({},{get(){throw Error('Unexpected global listener');}})});
});
