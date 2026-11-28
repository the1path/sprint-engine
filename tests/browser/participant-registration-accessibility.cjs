'use strict';
const fs=require('node:fs'),path=require('node:path'),{chromium}=require('playwright');
const {f,control,checks,ok,reset}=require('./participant-registration.cjs');
async function main(){
 control({action:'pro-classifier',enabled:true});reset();
 const browser=await chromium.launch({channel:'chrome',headless:true,args:['--host-resolver-rules=MAP signup.se0313.test 127.0.0.1','--no-proxy-server']});
 try{
  const context=await browser.newContext({ignoreHTTPSErrors:true,viewport:{width:360,height:900}}),page=await context.newPage();await page.goto(f.open.url);
  ok(await page.locator('input[name=password]').getAttribute('autocomplete')==='new-password'&&!await page.locator('input[name=password]').getAttribute('maxlength'),'Native password-manager/paste field has no truncating maxlength');
  const ratios=await page.evaluate(()=>{
   const luminance=color=>{const rgb=color.match(/\d+(?:\.\d+)?/g).slice(0,3).map(Number).map(n=>{n/=255;return n<=.04045?n/12.92:((n+.055)/1.055)**2.4});return rgb[0]*.2126+rgb[1]*.7152+rgb[2]*.0722;};
   const contrast=(a,b)=>{a=luminance(a);b=luminance(b);return(Math.max(a,b)+.05)/(Math.min(a,b)+.05)};
   const input=getComputedStyle(document.querySelector('input[name=email]')),button=getComputedStyle(document.querySelector('button[type=submit]'));return{input:contrast(input.color,input.backgroundColor),boundary:contrast(input.borderTopColor,input.backgroundColor),button:contrast(button.color,button.backgroundColor)};
  });ok(ratios.input>=4.5&&ratios.boundary>=3&&ratios.button>=4.5,'Actual computed default field text, boundary and primary button meet contrast thresholds');
  const before=control({action:'inspect'}).users;await page.locator('[name=first_name]').fill('<b>A</b>');await page.locator('[name=last_name]').fill('李 أحمد');await page.locator('[name=email]').fill('accessible-'+Date.now()+'@example.invalid');await page.locator('[name=password]').fill('tiny');await page.getByRole('button',{name:'Create account',exact:true}).click();await page.locator('input[aria-invalid=true]').first().waitFor();
  await page.waitForLoadState('load');await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
  ok(await page.evaluate(()=>document.activeElement?.getAttribute('aria-label')==='Check your details'),'Native postback autofocus reaches error summary without custom JS');
  ok(await page.locator('[role=alert]').isVisible()&&await page.locator('[name=last_name]').inputValue()==='李 أحمد'&&await page.locator('[name=password]').inputValue()===''&&control({action:'inspect'}).users===before,'Accessible server errors preserve safe names, clear password and create no account');
  await page.screenshot({path:path.join(process.env.SE_TEST_EVIDENCE_DIR,'registration-errors.png'),fullPage:true});
  await page.locator('.se-runner__error a[href="#se-registration-first_name"]').click();ok(await page.locator('[name=first_name]').evaluate(input=>document.activeElement===input),'Error summary anchor moves keyboard focus to the named field');
  await page.evaluate(()=>{for(const label of document.querySelectorAll('.se-runner__registration label'))label.textContent+=' — Long translated label '.repeat(10);document.documentElement.dir='rtl';document.documentElement.style.zoom='2';});
  ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Long simulated labels, RTL and 200% CSS zoom retain narrow reflow');await page.screenshot({path:path.join(process.env.SE_TEST_EVIDENCE_DIR,'registration-long-rtl.png'),fullPage:true});
 }finally{await browser.close();}
 fs.writeFileSync(path.join(process.env.SE_TEST_EVIDENCE_DIR,'accessibility-results.json'),JSON.stringify({checks:checks.length,passed:checks,physical_assistive_technology:false,actual_password_manager:false,actual_browser_400_percent:false},null,2));
}
main().catch(error=>{console.error(error.stack);process.exitCode=1});
