// SE-030.1: follow the tutorial to author and experience a real new Sprint via UI.
const {chromium,firefox,webkit}=require('playwright');
const fs=require('node:fs');
const assert=require('node:assert/strict');
const fixture=JSON.parse(fs.readFileSync(process.argv[2],'utf8').replace(/^\uFEFF/,''));
assert(['127.0.0.1','localhost'].includes(new URL(fixture.home).hostname));
const output=process.env.SE_BROWSER_OUTPUT||'.tools/tutorial-build';
fs.mkdirSync(output,{recursive:true});

async function meta(page,open=true) {
    const toggle=page.getByRole('button',{name:'Meta Boxes',exact:true});
    if(await toggle.count() && (await toggle.getAttribute('aria-expanded')==='true')!==open) await toggle.press('Enter');
}
async function ready(page) {
    await page.waitForFunction(()=>window.wp?.data?.select('core/editor')?.getCurrentPostId());
    // Native welcome preferences and lazy editor UI must finish before inspection.
    await page.waitForLoadState('networkidle');
    const guide=page.getByRole('dialog').filter({hasText:/Welcome to (the editor|the block editor)/});
    if(await guide.count()) {await guide.getByRole('button',{name:/^Close( dialog)?$/}).click();await guide.waitFor({state:'hidden'});}
}
async function write(page,title,body) {
    await ready(page); await meta(page,false);
    await page.locator('.editor-post-title__input, iframe[name="editor-canvas"]').first().waitFor();
    const surface=await page.locator('.editor-post-title__input').count()?page:page.frameLocator('iframe[name="editor-canvas"]');
    await surface.locator('.editor-post-title__input').waitFor();
    if(title) await surface.locator('.editor-post-title__input').fill(title);
    const empty=surface.getByRole('button',{name:'Add default block',exact:true});
    const paragraph=surface.locator('[data-type="core/paragraph"][contenteditable="true"], [data-type="core/paragraph"] [contenteditable="true"]');
    await empty.or(paragraph.first()).first().waitFor();
    if(await empty.isVisible()) await empty.click();
    await paragraph.first().fill(body);
}
async function save(page) {
    await page.getByRole('button',{name:'Save draft',exact:true}).click();
    await page.waitForFunction(()=>!wp.data.select('core/editor').isSavingPost()&&!wp.data.select('core/editor').isEditedPostDirty());
}
async function publish(page) {
    await page.getByRole('button',{name:'Publish',exact:true}).first().click();
    const confirm=page.getByRole('region',{name:'Editor publish',exact:true}).getByRole('button',{name:'Publish',exact:true});
    await confirm.or(page.getByRole('button',{name:'Switch to draft',exact:true})).first().waitFor();
    if(await confirm.count()) await confirm.click();
    await page.waitForFunction(()=>wp.data.select('core/editor').getCurrentPostAttribute('status')==='publish'&&!wp.data.select('core/editor').isSavingPost());
}
async function follow(guide,title) {
    await Promise.all([guide.waitForNavigation(),guide.getByRole('button',{name:'Complete & Continue',exact:true}).click()]);
    assert(await guide.getByRole('heading',{name:title,exact:true}).isVisible());
}
async function popup(page,context,label) {
    const [opened]=await Promise.all([context.waitForEvent('page'),page.getByRole('link',{name:label,exact:true}).click()]);
    await opened.waitForLoadState('load'); return opened;
}
(async()=>{
    const report=[];
    const engines=Object.entries({chromium,firefox,webkit}).filter(([name])=>!process.env.SE_BROWSER_ENGINES||process.env.SE_BROWSER_ENGINES.split(',').includes(name));
    assert(engines.length>0);
    for(const [index,[name,engine]] of engines.entries()) {
        const browser=await engine.launch(name==='chromium'?{channel:process.env.SE_BROWSER_CHANNEL||'chrome',headless:true}:{headless:true});
        try {
            const context=await browser.newContext({viewport:{width:1440,height:1000}});
            await context.addCookies([fixture.admins[index].cookie,fixture.admins[index].auth_cookie]);
            const guide=await context.newPage();guide.setDefaultTimeout(30000);
            await guide.goto(fixture.settings);
            const install=guide.getByRole('button',{name:'Install Tutorial Sprint',exact:true});
            if(await install.count()) await Promise.all([guide.waitForNavigation(),install.click()]);
            await guide.waitForLoadState('networkidle');
            const tutorialUrl=await guide.getByRole('link',{name:'Open Tutorial Sprint',exact:true}).getAttribute('href');
            await guide.goto(tutorialUrl);
            await Promise.all([guide.waitForNavigation(),guide.getByRole('button',{name:'Start Sprint',exact:true}).click()]);
            assert(await guide.getByRole('heading',{name:'Decide what your Sprint will achieve',exact:true}).isVisible());
            const admin=await popup(guide,context,'Open WordPress admin (opens in a new tab)');
            assert(await admin.getByRole('heading',{name:'Dashboard',exact:true}).isVisible());await admin.close();
            assert.equal(guide.url(),tutorialUrl);
            await follow(guide,'Create your Sprint and its Start screen');
            const build=await popup(guide,context,'Create a new Sprint (opens in a new tab)');
            await ready(build);
            const initiallyClosed=await build.getByRole('button',{name:'Meta Boxes',exact:true}).count()?await build.getByRole('button',{name:'Meta Boxes',exact:true}).getAttribute('aria-expanded')==='false':null;
            const title=`My weekly plan — ${name}`;
            const intro='Create a realistic plan for next week with three clear priorities. Bring your calendar and something to write with.';
            await write(build,title,intro);
            await meta(build); await build.getByRole('heading',{name:'Start screen / Sprint introduction',exact:true}).waitFor();
            await build.screenshot({path:`${output}/${name}-open-panels.png`,fullPage:true});
            await save(build);await build.reload();await ready(build);await meta(build);
            const editorUrl=build.url();
            assert(!editorUrl.includes('post-new.php'));
            await follow(guide,'Add and arrange your three Steps');
            const titles=['List what needs doing','Choose three priorities','Make time for the first priority'];
            for(const [i,t] of titles.entries()) {
                await build.getByRole('textbox',{name:'New Step title',exact:true}).fill(t);
                await build.getByRole('button',{name:'Add Step',exact:true}).click();
                await build.waitForFunction(n=>document.querySelectorAll('.se-linear-row').length===n,i+1);
            }
            assert.deepEqual(await build.locator('.se-step-status').allTextContents(),['Draft','Draft','Draft']);
            await build.getByRole('button',{name:'Save order',exact:true}).click();
            await build.getByRole('status').filter({hasText:'Structure saved.'}).waitFor();
            await build.screenshot({path:`${output}/${name}-three-drafts.png`,fullPage:true});
            await build.locator('.se-linear-row').first().getByRole('link',{name:'Edit',exact:true}).click();
            await ready(build);
            assert((await build.title()).includes(titles[0]),'Step 3 must leave the author at the first Step editor');
            await follow(guide,'Write and publish your Steps');
            const actions=[
                'Write down every commitment for next week. Check your calendar and include fixed appointments as well as flexible work. You are done when you have one usable list.',
                'Look at your list and choose three priorities that would make next week worthwhile. Mark them clearly. You are done when you can name those three priorities.',
                'Choose the first priority and reserve time for it in your calendar. Write down the first action you will take. You are done when the time and first action are clear.'
            ];
            for(let i=0;i<3;i++) {
                if(i) {await build.locator('.se-linear-row').nth(i).getByRole('link',{name:'Edit',exact:true}).click();await ready(build);}
                await write(build,null,actions[i]);await meta(build);
                await build.getByLabel('Step mode',{exact:true}).selectOption('task');
                await publish(build);await meta(build);
                await build.getByRole('link',{name:'← Back to Sprint',exact:true}).click();
                await ready(build);await meta(build);
                assert.equal(await build.locator('.se-step-status[data-status="publish"]').count(),i+1);
            }
            await follow(guide,'Finish the setup and launch your Sprint');
            const finish="You've created your weekly plan. You're ready to put it into action!";
            await build.getByRole('textbox',{name:'Completion message',exact:true}).fill(finish);
            assert.equal(await build.getByRole('textbox',{name:'CTA button label',exact:true}).inputValue(),'');
            assert.equal(await build.getByRole('textbox',{name:'CTA URL',exact:true}).inputValue(),'');
            await build.getByRole('checkbox',{name:'Launchable',exact:true}).check();
            await publish(build);await build.reload();await ready(build);await meta(build);
            assert(await build.getByRole('checkbox',{name:'Launchable',exact:true}).isChecked());
            const view=build.locator('#sprint_engine_runner_url').getByRole('link',{name:'View Runner',exact:true});
            const runnerUrl=await view.getAttribute('href');assert(runnerUrl&&runnerUrl!==tutorialUrl);
            await build.screenshot({path:`${output}/${name}-published-sprint.png`,fullPage:true});
            await follow(guide,"Experience the Sprint you've built");
            await view.click();await build.waitForLoadState('load');
            assert(await build.getByRole('heading',{name:title,exact:true}).isVisible());
            assert(await build.getByText(intro,{exact:true}).isVisible());
            await build.screenshot({path:`${output}/${name}-own-start.png`,fullPage:true});
            await Promise.all([build.waitForNavigation(),build.getByRole('button',{name:'Start Sprint',exact:true}).click()]);
            for(let i=0;i<3;i++) {
                await build.getByRole('heading',{name:titles[i],exact:true}).waitFor();
                assert(await build.getByText(actions[i],{exact:true}).isVisible());
                if(i===1) {
                    await build.getByRole('link',{name:'Save & Exit',exact:true}).click();
                    await build.getByRole('heading',{name:'My Sprints',exact:true}).waitFor();
                    const card=build.locator('article').filter({has:build.getByRole('heading',{name:title,exact:true})});
                    await card.getByRole('link',{name:'Continue Sprint',exact:true}).click();
                    await build.getByRole('heading',{name:titles[i],exact:true}).waitFor();
                }
                await Promise.all([build.waitForNavigation(),build.getByRole('button',{name:i===2?'Complete Sprint':'Complete & Continue',exact:true}).click()]);
            }
            assert(await build.getByText(finish,{exact:true}).isVisible());
            assert.equal(await build.getByText('Coming soon: Sprint Engine Pro',{exact:false}).count(),0,'Promotion belongs only to the tutorial completion');
            await build.screenshot({path:`${output}/${name}-own-completion.png`,fullPage:true});
            await Promise.all([guide.waitForNavigation(),guide.getByRole('button',{name:'Complete Sprint',exact:true}).click()]);
            assert(await guide.getByText("You've built your first Sprint — congratulations!",{exact:true}).isVisible());
            assert(await guide.getByText('Coming soon: Sprint Engine Pro',{exact:false}).isVisible());
            await guide.screenshot({path:`${output}/${name}-tutorial-completion.png`,fullPage:true});
            report.push({engine:name,version:browser.version(),wordpress:fixture.wordpress,home:fixture.home,initiallyClosed,editorUrl,runnerUrl,steps:3,optionalImageRequired:false,result:'published and experienced to completion via native UI'});
            console.log(`PASS: ${name} first-time administrator built, published, resumed and completed a real three-Step Sprint alongside the tutorial.`);
        } finally {await browser.close();}
    }
    fs.writeFileSync(`${output}/build-report.json`,JSON.stringify(report,null,2));
})().catch(error=>{console.error(error);process.exit(1);});
