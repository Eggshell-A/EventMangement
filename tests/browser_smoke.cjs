// Optional browser smoke test against the isolated integration-test server.
const { chromium } = require('playwright');

(async () => {
  const [base, eventId, screenshot] = process.argv.slice(2);
  const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  try {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => {
      if (/Content Security Policy|Refused to/.test(message.text())) errors.push(message.text());
    });
    await page.addInitScript(() => { window.print = () => { window.__printed = true; }; });
    const response = await page.goto(`${base}/poster.php?id=${eventId}`, { waitUntil: 'networkidle' });
    if (response.status() !== 200) throw new Error('Poster did not load');
    const image = page.locator('.poster-qr');
    await image.waitFor();
    await page.waitForFunction(() => document.querySelector('.poster-qr').naturalWidth > 0);
    await page.locator('#print-poster').click();
    if (!await page.evaluate(() => window.__printed === true)) throw new Error('Print handler did not run');
    if (!await page.evaluate(() => getComputedStyle(document.querySelector('body')).margin === '0px')) throw new Error('Stylesheet did not load');
    if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)) throw new Error('Poster overflows mobile viewport');
    if (errors.length) throw new Error(errors.join('\n'));
    if (screenshot) await page.screenshot({ path: screenshot, fullPage: true });
    console.log('PASS browser poster/QR/CSP/print smoke');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exit(1); });
