// Browser authoring smoke on the disposable loopback site.
const {chromium} = require('playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async () => {
  const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8').replace(/^\uFEFF/, ''));
  assert(['localhost','127.0.0.1'].includes(new URL(fixture.url).hostname));
  const browser = await chromium.launch({channel:process.env.SE_BROWSER_CHANNEL || 'chrome', headless:true});
  try {
    const context = await browser.newContext({viewport:{width:1440,height:1000}});
    await context.addCookies(fixture.cookies);
    const page = await context.newPage();
    page.setDefaultTimeout(20000);
    await page.goto(fixture.url);
    const welcome = page.getByRole('button', {name:'Close', exact:true});
    await welcome.waitFor({timeout:3000}).catch(() => {});
    if (await welcome.isVisible()) await welcome.click();
    const initialPane = page.getByText('Meta Boxes', {exact:true});
    if (await initialPane.getAttribute('aria-expanded') === 'false') { await initialPane.focus(); await page.keyboard.press('Enter'); }
    await page.locator('.se-structure-locked').waitFor();
    assert.equal(await page.locator('.se-linear-manager').count(),0);
    assert((await page.locator('.se-structure-locked').innerText()).includes('Save this Sprint to start adding Steps.'));
    assert(await page.locator('.se-structure-locked button').isDisabled());
    fs.mkdirSync('.tools/se014-browser',{recursive:true});
    await page.locator('.se-structure-locked').screenshot({path:'.tools/se014-browser/locked.png'});
    fixture.sprint=Number(await page.locator('.se-structure-locked').getAttribute('data-sprint'));
    fixture.url=new URL('post.php?post='+fixture.sprint+'&action=edit',fixture.url).href;
    fs.writeFileSync(process.argv[2],JSON.stringify(fixture));
    await page.locator('#post_name').fill('se014-author-'+fixture.sprint);
    await initialPane.focus(); await page.keyboard.press('Enter');
    const title=page.locator('.editor-post-title__input');
    if(await title.count()) { await title.fill('SE-014 browser authoring'); }
    else { await page.frameLocator('iframe[name="editor-canvas"]').locator('.editor-post-title__input').fill('SE-014 browser authoring'); }
    await initialPane.focus(); await page.keyboard.press('Enter');
    const refreshRequests=[];
    page.on('request',r=>{if(r.postData()?.includes('operation=refresh')) refreshRequests.push(r);});
    // Native explicit first save. No reload/navigation before activation and Quick Add.
    await page.getByRole('button',{name:'Save draft',exact:true}).click();
    await page.locator('.se-linear-manager').waitFor();
    assert.equal(await page.locator('.se-structure-locked').count(),0);
    assert.equal(refreshRequests.length,1);
    assert.deepEqual([...new URLSearchParams(refreshRequests[0].postData()).keys()].sort(),['action','nonce','operation','sprint']);
    for (let i=1;i<=5;i++) {
      await page.locator('#se-new-step-title').fill('Acceptance Step ' + i + (i===3?' — Review a longer Step title and choose a practical action for the week ahead':''));
      await page.locator('.se-add-step').click();
      await page.waitForFunction(n => document.querySelectorAll('.se-linear-row').length === n, i);
      assert.equal(await page.locator('.se-linear-row').last().locator('.se-step-status').getAttribute('data-status'),'draft');
      assert.equal(await page.locator('.se-linear-row').last().locator('.se-step-status').innerText(),'Draft');
      assert((await page.locator('.se-publication-warning').innerText()).startsWith(i===1?'1 Step is not published.':i+' Steps are not published.'));
    }
    const publishedStep=Number(await page.locator('.se-linear-row').last().getAttribute('data-step'));
    await page.evaluate(async id=>wp.apiFetch({path:'/wp/v2/sprint_engine_step/'+id,method:'POST',data:{status:'publish'}}),publishedStep);
    await page.locator('.se-save-order').click();
    await page.locator('.se-step-status[data-status="publish"]').waitFor();
    assert.equal(await page.locator('.se-step-status[data-status="publish"]').innerText(),'Published');
    assert((await page.locator('.se-publication-warning').innerText()).startsWith('4 Steps are not published.'));
    await page.locator('.se-linear-manager').screenshot({path:'.tools/se014-browser/desktop.png'});
    await page.setViewportSize({width:600,height:1000});
    assert(await page.locator('.se-step-status').first().isVisible());
    assert(await page.locator('.se-row-actions').last().getByRole('link',{name:'Edit',exact:true}).isVisible());
    assert(await page.locator('.se-move').last().isVisible());
    assert(await page.locator('.se-boundary').last().isVisible());
    assert(await page.locator('.se-next-title').last().isVisible());
    assert(await page.locator('.se-linear-manager').evaluate(el=>el.scrollWidth<=el.clientWidth+1));
    await page.locator('.se-linear-manager').screenshot({path:'.tools/se014-browser/narrow.png'});
    await page.locator('.se-linear-row').last().screenshot({path:'.tools/se014-browser/narrow-final-row.png'});
    await page.setViewportSize({width:1440,height:1000});
    const settings=page.getByRole('button',{name:'Settings',exact:true});
    if(await settings.getAttribute('aria-expanded')==='false') await settings.click();
    await page.locator('.se-linear-row').nth(1).locator('[data-direction="up"]').click();
    assert(await page.locator('.se-remove-step').first().isDisabled());
    assert((await page.locator('.se-structure-status').innerText()).includes('Save order'));
    await page.locator('.se-save-order').click();
    await page.waitForFunction(() => document.querySelector('.se-structure-status').textContent.includes('Structure saved'));
    await page.reload();
    const pane = page.getByText('Meta Boxes', {exact:true});
    if (await pane.getAttribute('aria-expanded') === 'false') { await pane.focus(); await page.keyboard.press('Enter'); }
    assert.equal(await page.locator('.se-linear-row .se-step-title').first().innerText(), 'Acceptance Step 2');
    assert.equal(await page.locator('.se-linear-row').count(), 5);
    assert.equal(await page.locator('#_sprint_engine_estimated_duration_value').count(),1);
    assert.equal(await page.locator('#_sprint_engine_estimated_minutes').count(),0);
    assert.deepEqual(await page.locator('#_sprint_engine_estimated_duration_unit option').allTextContents(),['Minutes','Hours','Days']);
    assert.equal(await page.locator('#_sprint_engine_estimated_duration_value').getAttribute('type'),'text');
    assert.equal(await page.locator('#_sprint_engine_estimated_duration_value').getAttribute('inputmode'),'decimal');
    async function saveEstimate(value) {
      const metaPane=page.getByText('Meta Boxes',{exact:true});
      if(await metaPane.getAttribute('aria-expanded')==='false') {await metaPane.focus();await page.keyboard.press('Enter');}
      await page.locator('#_sprint_engine_estimated_duration_value').fill(value);
      await page.locator('#_sprint_engine_estimated_duration_unit').selectOption('hours');
      const saved=page.waitForResponse(r=>r.request().method()==='POST' && r.url().includes('post.php'));
      await page.getByRole('button',{name:'Save draft',exact:true}).click();
      await saved;
      await page.getByRole('button',{name:'Save draft',exact:true}).waitFor();
      await page.reload();
      const reloadedPane=page.getByText('Meta Boxes',{exact:true});
      if(await reloadedPane.getAttribute('aria-expanded')==='false') {await reloadedPane.focus();await page.keyboard.press('Enter');}
    }
    await saveEstimate('4');
    assert.equal(await page.locator('#_sprint_engine_estimated_duration_value').inputValue(),'4');
    await page.locator('#_sprint_engine_estimated_duration_value').fill('4-6');
    assert.equal(await page.locator('#_sprint_engine_estimated_duration_value').getAttribute('aria-invalid'),'true');
    assert.equal(await page.locator('#_sprint_engine_estimated_duration_value').getAttribute('aria-describedby'),'se-duration-help _sprint_engine_estimated_duration_value-error');
    assert(await page.locator('#_sprint_engine_estimated_duration_value-error').isVisible());
    assert(await page.evaluate(() => wp.data.select('core/editor').isPostSavingLocked()));
    assert(await page.getByRole('button',{name:'Save draft',exact:true}).isDisabled());
    assert.equal(await page.evaluate(async () => (await wp.apiFetch({path:'/wp/v2/sprint_engine_sprint/'+wp.data.select('core/editor').getCurrentPostId()+'?context=edit'})).meta._sprint_engine_estimated_duration_value),'4');
    assert.equal(await page.locator('#_sprint_engine_estimated_duration_unit').inputValue(),'hours');
    fs.mkdirSync('.tools/se010-2-browser',{recursive:true});
    await page.locator('#sprint_engine_structure').screenshot({path:'.tools/se010-2-browser/duration.png'});
    await page.evaluate(() => wp.data.dispatch('core/notices').createInfoNotice('Independent notice',{id:'independent-test'}));
    await page.locator('#_sprint_engine_completion_cta_label').fill('Next');
    await page.locator('#_sprint_engine_estimated_duration_value').fill('4.5');
    assert.equal(await page.locator('#_sprint_engine_estimated_duration_value').getAttribute('aria-invalid'),null);
    assert.equal(await page.locator('#_sprint_engine_estimated_duration_value').getAttribute('aria-describedby'),'se-duration-help');
    assert(await page.evaluate(() => wp.data.select('core/editor').isPostSavingLocked()));
    assert.equal(await page.evaluate(() => wp.data.select('core/notices').getNotices().filter(n=>n.id==='sprint-engine-authoring-validation').length),1);
    await page.locator('#_sprint_engine_completion_cta_label').fill('');
    await page.locator('#_sprint_engine_completion_cta_url').fill('https://example.org/next');
    assert.equal(await page.locator('#_sprint_engine_completion_cta_label').getAttribute('aria-invalid'),'true');
    await page.locator('#_sprint_engine_completion_cta_label').fill('Next');
    for (const url of ['example.org','https://','javascript:alert(1)','https://bad host']) {
      await page.locator('#_sprint_engine_completion_cta_url').fill(url);
      assert(await page.locator('#_sprint_engine_completion_cta-error').isVisible());
      assert(await page.evaluate(() => wp.data.select('core/editor').isPostSavingLocked()));
    }
    await page.locator('#_sprint_engine_completion_cta_url').fill('https://example.org/next');
    assert(!await page.evaluate(() => wp.data.select('core/editor').isPostSavingLocked()));
    assert.equal(await page.evaluate(() => wp.data.select('core/notices').getNotices().filter(n=>n.id==='sprint-engine-authoring-validation').length),0);
    assert.equal(await page.evaluate(() => wp.data.select('core/notices').getNotices().filter(n=>n.id==='independent-test').length),1);
    await saveEstimate('4.5');
    assert.equal(await page.locator('#_sprint_engine_estimated_duration_value').inputValue(),'4.5');
    assert.equal(await page.locator('[aria-invalid="true"][data-se-error]').count(),0);
    assert.equal(await page.locator('#_sprint_engine_completion_cta_url').inputValue(),'https://example.org/next');
    await saveEstimate('');
    assert.equal(await page.locator('#_sprint_engine_estimated_duration_value').inputValue(),'');
    const trash=page.locator('.se-remove-step').first();
    assert.equal(await trash.innerText(),'Trash');
    const trashStyle=await trash.evaluate(el=>{const s=getComputedStyle(el);return {border:s.borderTopWidth,background:s.backgroundColor,color:s.color};});
    assert.equal(trashStyle.border,'0px');
    assert.equal(trashStyle.background,'rgba(0, 0, 0, 0)');
    const [red,green,blue]=trashStyle.color.match(/\d+/g).map(Number);
    assert(red>green*2 && red>blue*2);
    await page.getByRole('button',{name:'Set featured image',exact:true}).waitFor();
    assert.equal(await page.locator('#se-runner-url').count(), 1);
    assert.equal(await page.locator('.se-setup-checklist').count(),1);
    assert((await page.locator('#sprint_engine_setup').innerText()).includes('usually do not need a separate Welcome Step'));
    assert.equal(await page.locator('[data-check="steps"]').getAttribute('data-complete'),'true');
    fs.mkdirSync('.tools/se009-browser',{recursive:true});
    await page.screenshot({path:'.tools/se009-browser/authoring.png',fullPage:true});
    const stepLink=await page.locator('.se-linear-row').first().getByRole('link',{name:'Edit',exact:true}).getAttribute('href');
    await page.goto(stepLink);
    await page.getByRole('button',{name:'Set featured image',exact:true}).waitFor();
    const stepPane=page.getByText('Meta Boxes',{exact:true});
    if(await stepPane.getAttribute('aria-expanded')==='false') {await stepPane.focus();await page.keyboard.press('Enter');}
    assert.equal(await page.locator('#_sprint_engine_stage_label').getAttribute('list'),'se-stage-suggestions');
    assert.equal(await page.locator('#se-stage-suggestions option').count(),10);
    assert.equal(await page.locator('#_sprint_engine_estimated_minutes').getAttribute('type'),'text');
    assert.equal(await page.locator('#_sprint_engine_estimated_minutes').getAttribute('inputmode'),'numeric');
    async function saveMinutes(value) {
      await page.locator('#_sprint_engine_estimated_minutes').fill(value);
      const saved=page.waitForResponse(r=>r.request().method()==='POST' && r.url().includes('post.php'));
      await page.getByRole('button',{name:'Save draft',exact:true}).click();
      await saved;
      await page.waitForFunction(() => !wp.data.select('core/editor').isSavingPost());
    }
    await saveMinutes('60');
    for (const value of ['60 mins','0','-1','1.5','one hour']) {
      await page.locator('#_sprint_engine_estimated_minutes').fill(value);
      assert(await page.locator('#_sprint_engine_estimated_minutes-error').isVisible());
      assert.equal(await page.locator('#_sprint_engine_estimated_minutes').getAttribute('aria-invalid'),'true');
      assert.equal(await page.locator('#_sprint_engine_estimated_minutes').getAttribute('aria-describedby'),'se-minutes-help _sprint_engine_estimated_minutes-error');
      assert(await page.getByRole('button',{name:'Save draft',exact:true}).isDisabled());
    }
    assert.equal(await page.evaluate(async () => (await wp.apiFetch({path:'/wp/v2/sprint_engine_step/'+wp.data.select('core/editor').getCurrentPostId()+'?context=edit'})).meta._sprint_engine_estimated_minutes),60);
    await page.locator('#sprint_engine_structure').screenshot({path:'.tools/se010-2-browser/minutes.png'});
    await saveMinutes('45');
    assert.equal(await page.locator('#_sprint_engine_estimated_minutes').getAttribute('aria-describedby'),'se-minutes-help');
    await page.reload();
    const minutesPane=page.getByText('Meta Boxes',{exact:true});
    if(await minutesPane.getAttribute('aria-expanded')==='false') {await minutesPane.focus();await page.keyboard.press('Enter');}
    assert.equal(await page.locator('#_sprint_engine_estimated_minutes').inputValue(),'45');
    assert.equal(await page.locator('[aria-invalid="true"][data-se-error]').count(),0);
    await saveMinutes('');
    assert.equal(await page.evaluate(async () => (await wp.apiFetch({path:'/wp/v2/sprint_engine_step/'+wp.data.select('core/editor').getCurrentPostId()+'?context=edit'})).meta._sprint_engine_estimated_minutes),0);
    await page.screenshot({path:'.tools/se009-browser/step-editor.png',fullPage:true});
    const back=page.getByRole('link',{name:'← Back to Sprint'});
    assert.equal(new URL(await back.getAttribute('href')).searchParams.get('post'),new URL(fixture.url).searchParams.get('post'));
    await back.click(); await page.waitForLoadState('load');
    const listUrl=new URL('edit.php?post_type=sprint_engine_step&sprint_engine_parent_sprint='+new URL(fixture.url).searchParams.get('post'),fixture.url).href;
    await page.goto(listUrl);
    assert.equal(await page.locator('#se-parent-sprint').inputValue(),new URL(fixture.url).searchParams.get('post'));
    assert.equal(await page.locator('#the-list tr').count(),5);
    assert.equal(await page.locator('#filter-by-date').count(),0);
    assert.equal(await page.locator('#sprint_engine_parent').innerText(),'Parent Sprint');
    assert.equal(await page.locator('#sprint_engine_stage').innerText(),'Stage');
    await page.goto(fixture.url);
    const removePane=page.getByText('Meta Boxes',{exact:true});
    if(await removePane.getAttribute('aria-expanded')==='false') {await removePane.focus();await page.keyboard.press('Enter');}
    const trashRequests=[];
    const trackTrash=r=>{if(r.postData()?.includes('operation=remove')) trashRequests.push(r);};
    page.on('request',trackTrash);
    page.once('dialog', async dialog => { assert(dialog.message().includes('restoring it will leave it unassigned')); await dialog.dismiss(); });
    await page.locator('.se-remove-step').first().click();
    assert.equal(await page.locator('.se-linear-row').count(),5);
    assert.equal(trashRequests.length,0);
    page.off('request',trackTrash);
    page.once('dialog', dialog => dialog.accept());
    const requestPromise=page.waitForRequest(r => r.url().includes('admin-ajax.php') && r.postData()?.includes('operation=remove'));
    await page.locator('.se-remove-step').first().click();
    const request=await requestPromise;
    fixture.removed=Number(new URLSearchParams(request.postData()).get('step'));
    fs.writeFileSync(process.argv[2],JSON.stringify(fixture));
    assert.deepEqual([...new URLSearchParams(request.postData()).keys()].sort(),['action','nonce','operation','revision','sprint','step']);
    await page.waitForFunction(() => document.querySelectorAll('.se-linear-row').length===4);
    assert((await page.locator('.se-structure-status').innerText()).includes('moved to Trash'));
    assert(await page.locator('#se-new-step-title').evaluate(el => el===document.activeElement));
    fs.mkdirSync('.tools/se007-browser', {recursive:true});
    await page.screenshot({path:'.tools/se007-browser/admin-authoring.png',fullPage:true});
    await page.goto(fixture.url+'&sprint_engine_test_classic=1');
    assert.equal(await page.locator('script[src*="authoring-validation.js"]').count(),1);
    await page.locator('#_sprint_engine_estimated_duration_value').fill('4-6');
    assert(await page.locator('#_sprint_engine_estimated_duration_value-error').isVisible());
    assert.equal(await page.locator('#_sprint_engine_estimated_duration_value').getAttribute('aria-invalid'),'true');
    assert.equal(await page.locator('#post').evaluate(form => form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}))),false);
    assert(await page.locator('#_sprint_engine_estimated_duration_value').evaluate(field => field === document.activeElement));
    await page.locator('#_sprint_engine_estimated_duration_value').fill('4.5');
    const classicSaved=page.waitForResponse(r=>r.request().method()==='POST' && r.url().includes('post.php'));
    await page.locator('#save-post').click();await classicSaved;
    await page.waitForLoadState('load');
    await page.goto(fixture.url+'&sprint_engine_test_classic=1');
    assert.equal(await page.locator('#_sprint_engine_estimated_duration_value').inputValue(),'4.5');
    assert.equal(await page.locator('[aria-invalid="true"][data-se-error]').count(),0);
    await page.goto(new URL('post-new.php',fixture.url).href);
    assert.equal(await page.locator('script[src*="authoring-validation.js"]').count(),0);
    await page.goto(new URL('post-new.php?post_type=page',fixture.url).href);
    assert.equal(await page.locator('script[src*="authoring-validation.js"]').count(),0);
    console.log('PASS: Gutenberg duration, minutes, CTA, save locks, correction/reload, notice isolation; Classic submit/save; Posts/Pages isolation; structure and Runner admin regression.');
    console.log('PASS: New Gutenberg Sprint locked placeholder, explicit first-save activation without reload, immediate Draft Quick Add badges/warnings, desktop and narrow Structure controls.');
  } finally { await browser.close(); }
})().catch(error => {console.error(error);process.exitCode=1;});
