const {test}=require('node:test');
const assert=require('node:assert/strict');
const {foreground,luminance,variables}=require('../../assets/js/runner-settings.js');
const defaults={primary:'#205b48',background:'#f3f6f5',surface:'#ffffff',text:'#203630',muted:'#50645d',corner_style:'soft'};
const radii={square:'0',subtle:'0.5rem',soft:'1rem',rounded:'1.5rem'};
test('optional primary text changes only foreground; clearing restores automatic',()=>{
 const automatic=variables({...defaults,primary_text:''},radii);
 assert.deepEqual(automatic,variables(defaults,radii));
 assert.deepEqual(variables({...defaults,primary_text:'#abcdef'},radii),{...automatic,'--se-on-primary':'#abcdef'});
 assert.deepEqual(variables({...defaults,primary_text:'red;bad'},radii),automatic);
});
test('preview baseline matches SE-007',()=>{
 const vars=variables(defaults,radii);
 assert.equal(vars['--se-hover'],'#164333'); assert.equal(vars['--se-accent'],'#276a55');
 assert.equal(vars['--se-radius'],'1rem'); assert.equal(vars['--se-control-radius'],'0.5rem');
});
test('button and hover foreground remains readable across RGB samples',()=>{
 for(let r=0;r<=255;r+=17) for(let g=0;g<=255;g+=17) for(let b=0;b<=255;b+=17){
 const primary='#'+[r,g,b].map(v=>v.toString(16).padStart(2,'0')).join('');
 const vars=variables({...defaults,primary},radii);
 for(const bg of [primary,vars['--se-hover']]) {
 const a=luminance(bg),z=luminance(foreground(primary));
 assert((Math.max(a,z)+0.05)/(Math.min(a,z)+0.05)>=4.5,primary+' / '+bg);
 }
 }
});
test('controlled corner mappings and primary value preserved',()=>{
 for(const corner_style of Object.keys(radii)){
 const vars=variables({...defaults,primary:'#ffffcc',corner_style},radii);
 assert.equal(vars['--se-primary'],'#ffffcc'); assert.equal(vars['--se-on-primary'],'#000000');
 assert.equal(vars['--se-radius'],radii[corner_style]);
 }
});
