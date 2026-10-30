/* Exercise the real 403 response, recovery GET and mobile layout against a local *_test DB. */
const {chromium}=require('playwright');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs');const assert=require('node:assert/strict');
const fixture=v=>JSON.parse(execFileSync('php',[__dirname+'/db_fixture.php'],{input:JSON.stringify(v),encoding:'utf8'}));
fixture({sql:'SELECT 1'});
const url=process.env.EVENTLINK_TEST_URL||'http://127.0.0.1:8080';
const password=process.env.EVENTLINK_DEMO_PASSWORD||'FairboardDemo!2026';
const dir=__dirname+'/../test-results';fs.mkdirSync(dir,{recursive:true});
let passed=0;const check=(ok,label)=>{assert.ok(ok,label);passed++;console.log('PASS',label);};
(async()=>{
 const launch={headless:true,args:['--no-sandbox','--disable-dev-shm-usage']};
 if(process.env.CHROMIUM_EXECUTABLE){launch.executablePath=process.env.CHROMIUM_EXECUTABLE;launch.args.push('--no-zygote','--single-process','--use-gl=angle','--use-angle=swiftshader','--enable-unsafe-swiftshader');}
 const browser=await chromium.launch(launch);const context=await browser.newContext({viewport:{width:1280,height:1000}});const page=await context.newPage();const errors=[];
 page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='error'&&!m.text().includes('403 (Forbidden)'))errors.push(m.text());});
 async function rejectForm(expired=false){
  await page.goto(url+'/login.php');await page.locator('#email').fill('private-form-marker@example.test');await page.locator('#password').fill('Private-form-marker!2026');
  if(expired)await context.clearCookies();else await page.locator('input[name=csrf_token]').evaluate(el=>el.value='invalid-form-token');
  const [response]=await Promise.all([page.waitForNavigation({waitUntil:'load'}),page.getByRole('button',{name:'Log in',exact:true}).click()]);
  return response;
 }
 const response=await rejectForm();check(response.status()===403,'invalid CSRF retains HTTP 403');
 check(response.headers()['cache-control'].includes('no-store'),'error response cannot be cached');
 check(response.headers()['content-security-policy'].includes("script-src 'self'"),'strict CSP preserved');
 check(await page.title()==="Let's refresh your form · Fairboard",'clear error-page title');
 check(await page.getByRole('heading',{name:'Your form needs a fresh start.'}).isVisible(),'recovery headline visible');
 check(await page.locator('body').evaluate(el=>getComputedStyle(el).backgroundColor)==='rgb(16, 16, 19)','brand stylesheet loaded');
 check(await page.locator('.recovery-visual img').evaluate(img=>img.complete&&img.naturalWidth>0),'recovery illustration loads');
 check(!/private-form-marker|Private-form-marker|invalid-form-token/.test(await page.content()),'submitted email, password and token are not redisplayed');
 check(await page.locator('form,script').count()===0,'error page has no repost form or script dependency');
 check(await page.locator('[data-recovery-link]').getAttribute('href')===url+'/login.php','primary action is a clean login URL');
 await page.screenshot({path:dir+'/form-recovery-desktop.png',fullPage:true});
 await page.locator('[data-recovery-link]').focus();check(await page.locator('[data-recovery-link]').evaluate(el=>getComputedStyle(el).outlineStyle!=='none'),'keyboard focus is visible');
 for(const width of [390,320]){
  await page.setViewportSize({width,height:844});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'no horizontal overflow at '+width);
  check(await page.locator('[data-recovery-link]').isVisible(),'primary recovery action visible at '+width);
  await page.locator('summary').click();check(await page.getByText(/Allow cookies for this site/).isVisible(),'native troubleshooting disclosure at '+width);await page.locator('summary').click();
  await page.screenshot({path:dir+'/form-recovery-mobile-'+width+'.png',fullPage:true});
 }
 await page.setViewportSize({width:1280,height:1000});
 const [request]=await Promise.all([page.waitForRequest(r=>r.isNavigationRequest()&&r.url()===url+'/login.php'),page.locator('[data-recovery-link]').click()]);
 check(request.method()==='GET'&&request.postData()===null,'recovery navigates with GET and never resends credentials');
 await page.waitForURL(url+'/login.php');check(await page.locator('#email').inputValue()===''&&await page.locator('#password').inputValue()==='','fresh login form starts empty');
 await page.locator('#email').fill('user@fairboard.test');await page.locator('#password').fill(password);await page.getByRole('button',{name:'Log in',exact:true}).click();await page.waitForURL(/index\.php$/);
 check(await page.getByRole('link',{name:'My tickets',exact:true}).isVisible(),'normal sign-in succeeds after recovery');
 await page.locator('form[action="logout.php"] button').click();await page.waitForURL(/index\.php$/);
 check((await rejectForm(true)).status()===403,'a genuinely lost session shows the same safe recovery');
 await page.locator('[data-recovery-link]').click();await page.locator('#email').waitFor();check(await page.locator('input[name=csrf_token]').getAttribute('value')!==null,'lost session can obtain a fresh protected form');
 check(errors.length===0,'no JavaScript, CSS or CSP errors');
 fs.writeFileSync(dir+'/form-recovery-browser.json',JSON.stringify({passed,errors},null,2));await browser.close();console.log('FORM RECOVERY BROWSER PASSED',passed);
})().catch(e=>{console.error(e);process.exit(1);});
