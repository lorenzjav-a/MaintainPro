/* Native Chrome DevTools checks, using Node 22+ and an installed Chrome browser. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const {spawn} = require('node:child_process');
const base = process.env.BR_TEST_URL;
if (!base || !/^http:\/\/127\.0\.0\.1:\d+$/.test(base)) throw new Error('Run through tests/browser.php on its disposable local server.');
const output = path.resolve(__dirname, 'tmp');
fs.mkdirSync(output, {recursive: true});
const profile = fs.mkdtempSync(path.join(output, 'browser-profile-'));
const chrome = spawn(process.env.BR_TEST_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0', '--remote-debugging-address=127.0.0.1', '--user-data-dir=' + profile, 'about:blank'], {windowsHide: true, stdio: 'ignore'});
let socket, counter = 0, checks = 0;
const pending = new Map(), contexts = [], errors = [];
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
function check(ok, label) { if (!ok) throw new Error('FAIL: ' + label); checks++; }
async function until(test, label, timeout = 12000) {
  const end = Date.now() + timeout;
  while (Date.now() < end) { try { const result = await test(); if (result) return result; } catch {} await delay(60); }
  throw new Error('Timed out: ' + label);
}
function call(method, params = {}, sessionId) {
  return new Promise((resolve, reject) => {
    const id = ++counter;
    const timer = setTimeout(() => { pending.delete(id); reject(new Error('DevTools timeout: ' + method)); }, 15000);
    pending.set(id, {resolve, reject, timer});
    socket.send(JSON.stringify({id, method, params, ...(sessionId ? {sessionId} : {})}));
  });
}
async function tab() {
  const {browserContextId} = await call('Target.createBrowserContext');
  contexts.push(browserContextId);
  const {targetId} = await call('Target.createTarget', {url: 'about:blank', browserContextId});
  const {sessionId} = await call('Target.attachToTarget', {targetId, flatten: true});
  const send = (method, params) => call(method, params, sessionId);
  await send('Runtime.enable'); await send('Page.enable');
  await send('Emulation.setDeviceMetricsOverride', {width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false});
  const evaluate = async expression => {
    const result = await send('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true});
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
    return result.result.value;
  };
  const ready = async (pathname, selector = 'h1,h2') => until(() => evaluate('location.pathname === ' + JSON.stringify(pathname) + ' && document.readyState === "complete" && !!document.querySelector(' + JSON.stringify(selector) + ')'), pathname + ' ready');
  const go = async (url, selector) => { await send('Page.navigate', {url: base + '/' + url}); await ready(selector === '#auth-form' ? '/login.php' : '/' + url.split('?')[0], selector); };
  const fill = async (selector, value) => evaluate('(() => {const el=document.querySelector(' + JSON.stringify(selector) + '); if (!el) throw new Error("Missing field"); el.value=' + JSON.stringify(value) + '; el.dispatchEvent(new Event("input",{bubbles:true})); el.dispatchEvent(new Event("change",{bubbles:true})); return true;})()');
  const click = async selector => evaluate('(() => {const el=document.querySelector(' + JSON.stringify(selector) + '); if(!el) throw new Error("Missing control"); el.click(); return true;})()');
  const waitFor = selector => until(() => evaluate('!!document.querySelector(' + JSON.stringify(selector) + ')'), selector);
  const submit = async (action, fields = {}, confirm = false, decision = '') => {
    for (const [key, value] of Object.entries(fields)) await fill('form[data-action="' + action + '"] [name="' + key + '"]', value);
    const old = await evaluate('document.querySelector("[data-version]")?.dataset.version || ""');
    await click('form[data-action="' + action + '"] button[type="submit"]' + (decision ? '[value="' + decision + '"]' : ''));
    if (confirm) { await waitFor('.swal2-confirm'); await click('.swal2-confirm'); }
    if (action === 'create_user') await until(() => evaluate('!document.getElementById("created-account").hidden'), 'one-time account details');
    else if (action === 'submit') await ready('/complaint.php', '[data-version]');
    else if (action === 'profile') await until(() => evaluate('location.search.includes("saved=profile") && document.readyState === "complete"'), 'profile saved');
    else if (action === 'update_user') await ready('/users.php', '.alert-success');
    else await until(() => evaluate('document.readyState === "complete" && document.querySelector("[data-version]")?.dataset.version !== ' + JSON.stringify(old)), 'complaint action ' + action);
  };
  const auth = async (action, fields) => {
    for (const [key, value] of Object.entries(fields)) await fill('#auth-form [name="' + key + '"]', value);
    await click('#auth-form button[type="submit"]');
    await until(() => evaluate('document.readyState === "complete" && (!!document.querySelector(".shell") || document.querySelector("#auth-form")?.dataset.action === "change_password")'), action);
  };
  const screenshot = async name => {
    const shot = await send('Page.captureScreenshot', {format: 'png', captureBeyondViewport: false});
    fs.writeFileSync(path.join(output, name + '.png'), Buffer.from(shot.data, 'base64'));
  };
  return {send, evaluate, ready, go, fill, click, waitFor, submit, auth, screenshot, targetId};
}

(async () => {
  try {
    const portFile = path.join(profile, 'DevToolsActivePort');
    await until(() => fs.existsSync(portFile), 'headless Chrome start');
    const [port, endpoint] = fs.readFileSync(portFile, 'utf8').trim().split(/\r?\n/);
    socket = new WebSocket('ws://127.0.0.1:' + port + endpoint);
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
    socket.onmessage = event => {
      const message = JSON.parse(event.data);
      if (message.id && pending.has(message.id)) {
        const item = pending.get(message.id); pending.delete(message.id); clearTimeout(item.timer);
        if (message.error) item.reject(new Error(message.error.message)); else item.resolve(message.result);
      } else if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
    };
    const official = await tab(), resident = await tab(), personnel = await tab();
    const password = 'Browser-test-password-42';
    await official.go('login.php');
    await official.auth('setup', {name: 'Browser Official', email: 'browser-official@example.test', password, confirm_password: password});
    check(await official.evaluate('document.querySelector("h1").textContent === "Administrative dashboard"'), 'official dashboard');
    await resident.go('login.php?view=register');
    await resident.auth('register', {name: 'Browser Resident', email: 'browser-resident@example.test', password, confirm_password: password});
    check(await resident.evaluate('document.querySelector("h1").textContent === "Resident dashboard"'), 'resident registration and dashboard');
    await official.click('.nav-list a[href="users.php"]'); await official.ready('/users.php');
    await official.click('.heading-actions a[href="user-create.php"]'); await official.ready('/user-create.php');
    await official.submit('create_user', {name: 'Browser Personnel', email: 'browser-personnel@example.test', role: 'personnel', team: 'Sanitation team'});
    const temporary = await official.evaluate('document.getElementById("created-password").value');
    check(temporary.startsWith('MP-'), 'personnel creation shows generated password');
    check(await official.evaluate('getComputedStyle(document.getElementById("created-password")).fontFamily.includes("Segoe UI")'), 'temporary password matches app font');
    await personnel.go('login.php');
    await personnel.auth('login', {email: 'browser-personnel@example.test', password: temporary});
    check(await personnel.evaluate('document.querySelector("#auth-form").dataset.action === "change_password"'), 'temporary password gate');
    await personnel.go('complaints.php', '#auth-form');
    check(await personnel.evaluate('document.querySelector("#auth-form").dataset.action === "change_password"'), 'temporary account direct page restriction');
    await personnel.auth('change_password', {current_password: temporary, password, confirm_password: password});
    await personnel.ready('/index.php');
    check(await personnel.evaluate('document.querySelector("h1").textContent === "Personnel dashboard"'), 'personnel password change and dashboard');
    await official.go('users.php');
    await official.go('user-create.php');
    check(await official.evaluate('document.getElementById("created-password").value === ""'), 'temporary password not replayed on refresh');

    await resident.click('.nav-list a[href="index.php"]'); await resident.ready('/index.php');
    await resident.click('.heading-actions a[href="new-complaint.php"]'); await resident.ready('/new-complaint.php');
    await resident.evaluate('(() => {const bytes=Uint8Array.from(atob("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII="),c=>c.charCodeAt(0));const transfer=new DataTransfer();transfer.items.add(new File([bytes],"evidence.png",{type:"image/png"}));const input=document.getElementById("new-photo");input.files=transfer.files;input.dispatchEvent(new Event("change",{bubbles:true}));})()');
    await resident.waitFor('.upload-preview'); check(true, 'image preview');
    await resident.submit('submit', {title: 'Browser drainage concern', category: 'Drainage and flooding', location: 'Purok 2', description: 'Drain is blocked.', suggestion: 'Clear the drain.'});
    const detail = await resident.evaluate('location.pathname.slice(1) + location.search');
    check(await resident.evaluate('!!document.querySelector(".alert-success") && !!document.querySelector(".case-photo")'), 'submission redirects with success and evidence');
    const staleOfficial = await tab();
    await staleOfficial.go('login.php');
    await staleOfficial.auth('login', {email: 'browser-official@example.test', password});
    await staleOfficial.go(detail);
    await official.go(detail);
    await official.submit('assess', {priority: 'High', recommendation: 'Inspect and clear the blockage.', assessment: 'Site assessment.'});
    check(await official.evaluate('!!document.querySelector("form[data-action=assign]")'), 'assessment enables assignment');
    await staleOfficial.fill('#recommendation', 'Unsaved assessment draft');
    await staleOfficial.click('form[data-action="assess"] button[type="submit"]');
    await until(() => staleOfficial.evaluate('document.querySelector(".swal2-title")?.textContent === "This complaint has changed"'), 'stale form warning');
    await staleOfficial.click('.swal2-cancel');
    check(await staleOfficial.evaluate('document.getElementById("recommendation").value === "Unsaved assessment draft" && document.querySelector("[data-version]").dataset.version === "1"'), 'conflict keeps draft and original version');
    // The resident still holds the first version; API must reject a stale write.
    const stale = await resident.evaluate('(async()=>{const r=await fetch("api.php",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":document.querySelector("meta[name=csrf-token]").content},body:JSON.stringify({action:"verify",id:document.querySelector("[data-case-id]").dataset.caseId,version:1,data:{feedback:"stale"}})});return r.status;})()');
    check(stale === 409, 'stale version rejected in browser session');
    await official.submit('assign', {team: 'Sanitation team'});
    await personnel.go('complaints.php');
    await personnel.click('a.open-case-btn'); await personnel.ready('/complaint.php');
    await personnel.submit('start');
    await personnel.submit('note', {notes: 'Inspected the blockage.'});
    check(await personnel.evaluate('document.querySelector(".case-timeline").textContent.includes("Inspected the blockage.")'), 'progress update in timeline');
    await personnel.submit('resolve', {notes: 'First drain clearing completed.'}, true);
    await resident.go(detail);
    await resident.submit('verification', {feedback: 'Water still backs up.'}, true, 'reopen');
    check(await resident.evaluate('document.querySelector(".case-summary").textContent.includes("Reopened")'), 'resident reopens');
    await official.go(detail);
    await official.submit('assess', {recommendation: 'Clear downstream obstruction.'});
    await official.submit('assign', {team: 'Sanitation team'});
    await personnel.go(detail); await personnel.submit('start');
    await personnel.submit('resolve', {notes: 'Downstream drain cleared and checked.'}, true);
    await resident.go(detail); await resident.submit('verification', {feedback: 'Flow is restored.'}, true, 'verify');
    check(await resident.evaluate('document.querySelector(".case-summary").textContent.includes("Verified")'), 'resident verifies resolution');
    await resident.screenshot('complaint-desktop');
    for (const page of ['history.php', 'reports.php', 'solutions.php', 'users.php', 'profile.php']) { await official.go(page); check(await official.evaluate('!!document.querySelector("h1")'), page + ' renders in browser'); }
    await resident.go('users.php'); check(await resident.evaluate('document.querySelector("h1").textContent === "Access denied"'), 'resident direct URL denied');
    await personnel.go('reports.php'); check(await personnel.evaluate('document.querySelector("h1").textContent === "Access denied"'), 'personnel direct URL denied');
    await resident.go('profile.php');
    await resident.submit('profile', {name: 'Updated Browser Resident', current_password: password});
    check(await resident.evaluate('document.querySelector(".profile-name").textContent === "Updated Browser Resident"'), 'profile save');
    await official.go('user-create.php');
    await official.submit('create_user', {name: 'Second Browser Official', email: 'second-browser-official@example.test', role: 'official'});
    check(await official.evaluate('document.querySelector("[data-created=role]").textContent === "Barangay official" && document.getElementById("created-team").hidden'), 'create additional official without team');
    await official.go('users.php');
    await official.click('a[aria-label="Manage account for Browser Personnel"]'); await official.ready('/user-edit.php');
    await official.submit('update_user', {active: '0'}, true);
    await personnel.go('index.php', '#auth-form');
    check(await personnel.evaluate('document.querySelector("#auth-form").dataset.action === "login"'), 'deactivated personnel session revoked');
    await official.click('a[aria-label="Manage account for Browser Personnel"]'); await official.ready('/user-edit.php');
    await official.submit('update_user', {active: '1'});
    await personnel.auth('login', {email: 'browser-personnel@example.test', password}); await personnel.ready('/index.php');
    await official.go('index.php'); await official.screenshot('dashboard-desktop');
    await official.click('.nav-list a[href="complaints.php"]'); await official.ready('/complaints.php');
    await official.fill('[name="search"]', 'drainage'); await official.click('form[role="search"] button');
    await until(() => official.evaluate('location.search.includes("search=drainage") && document.readyState === "complete"'), 'filter URL');
    const filteredUrl = await official.evaluate('location.href');
    await official.send('Page.reload'); await official.ready('/complaints.php');
    check(await official.evaluate('document.querySelector("[name=search]").value === "drainage"'), 'refresh retains filter');
    await official.click('.open-case-btn'); await official.ready('/complaint.php');
    let history = await official.send('Page.getNavigationHistory');
    await official.send('Page.navigateToHistoryEntry', {entryId: history.entries[history.currentIndex - 1].id});
    await until(() => official.evaluate('location.href === ' + JSON.stringify(filteredUrl) + ' && document.readyState === "complete"'), 'browser Back');
    check(true, 'browser Back restores filtered list');
    history = await official.send('Page.getNavigationHistory');
    await official.send('Page.navigateToHistoryEntry', {entryId: history.entries[history.currentIndex + 1].id});
    await official.ready('/complaint.php'); check(true, 'browser Forward restores complaint');
    await official.go('index.php');
    const point = await official.evaluate('(()=>{const r=document.querySelector(".nav-list a[href=\\"complaints.php\\"]").getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2};})()');
    const before = (await call('Target.getTargets')).targetInfos.map(t => t.targetId);
    await official.send('Input.dispatchMouseEvent', {type: 'mousePressed', button: 'middle', clickCount: 1, ...point});
    await official.send('Input.dispatchMouseEvent', {type: 'mouseReleased', button: 'middle', clickCount: 1, ...point});
    const opened = await until(async () => (await call('Target.getTargets')).targetInfos.find(t => !before.includes(t.targetId) && t.url.includes('/complaints.php')), 'middle-click new tab');
    check(true, 'middle-click opens actual complaint page'); await call('Target.closeTarget', {targetId: opened.targetId});
    await official.send('Emulation.setDeviceMetricsOverride', {width: 390, height: 844, deviceScaleFactor: 1, mobile: true});
    await official.go('index.php');
    check(await official.evaluate('getComputedStyle(document.querySelector(".mobile-dock")).display === "grid"'), 'mobile navigation visible');
    check(await official.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'mobile dashboard fits viewport');
    await official.click('.mobile-dock a[href="complaints.php"]'); await official.ready('/complaints.php');
    check(await official.evaluate('document.querySelector(".mobile-dock a[href=\\"complaints.php\\"]").getAttribute("aria-current") === "page"'), 'mobile active link');
    await official.click('.mobile-dock button[data-menu]');
    check(await official.evaluate('document.querySelector(".sidebar").classList.contains("mobile-open")'), 'mobile More menu');
    await official.click('.sidebar-scrim');
    await official.screenshot('complaints-mobile');
    await resident.send('Emulation.setDeviceMetricsOverride', {width: 390, height: 844, deviceScaleFactor: 1, mobile: true});
    await resident.go(detail); await resident.screenshot('complaint-mobile');
    check(await resident.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'mobile complaint fits viewport');
    for (const [client, role] of [[official, 'official'], [resident, 'resident'], [personnel, 'personnel']]) {
      check(await client.evaluate('(async()=>{const r=await fetch("api.php");return (await r.json()).actor.role;})()') === role, 'isolated ' + role + ' session');
    }
    await resident.click('[data-logout]'); await resident.waitFor('.swal2-confirm'); await resident.click('.swal2-confirm'); await resident.ready('/login.php');
    check(await official.evaluate('(async()=>{const r=await fetch("api.php");return r.status;})()') === 200, 'resident logout preserves official login');
    await resident.auth('login', {email: 'browser-resident@example.test', password}); await resident.ready('/index.php');
    check(true, 'resident signs back in');
    check(errors.length === 0, 'no uncaught browser JavaScript errors');
    console.log('PASS: ' + checks + ' browser checks for role workflows, JavaScript forms, Back/Forward, refresh, new tabs, mobile layout and isolated sessions.');
    console.log('Screenshots: tests/tmp/dashboard-desktop.png, complaint-desktop.png, complaints-mobile.png, complaint-mobile.png');
  } finally {
    if (socket?.readyState === WebSocket.OPEN) {
      for (const browserContextId of contexts) await call('Target.disposeBrowserContext', {browserContextId}).catch(() => {});
      await call('Browser.close').catch(() => {}); socket.close();
    }
    chrome.kill();
    // This exact directory was created by this runner and is beneath tests/tmp.
    if (path.dirname(profile) !== output || !path.basename(profile).startsWith('browser-profile-')) throw new Error('Unsafe browser profile cleanup path.');
    await delay(500);
    fs.rmSync(profile, {recursive: true, force: true, maxRetries: 10, retryDelay: 200});
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
