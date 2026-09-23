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
    const official = await tab(), guest = await tab(), personnel = await tab();
    const password = 'Browser-test-password-42';
    const photo = async (client, selector) => client.evaluate('(() => {const bytes=Uint8Array.from(atob("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII="),c=>c.charCodeAt(0));const transfer=new DataTransfer();transfer.items.add(new File([bytes],"evidence.png",{type:"image/png"}));const input=document.querySelector(' + JSON.stringify(selector) + ');input.files=transfer.files;input.dispatchEvent(new Event("change",{bubbles:true}));})()');
    // A known valid one-pixel PNG; file type and contents are both checked server-side.
    const setPhoto = async (client, selector) => client.evaluate('(() => {const bytes=Uint8Array.from(atob("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII="),c=>c.charCodeAt(0));const transfer=new DataTransfer();transfer.items.add(new File([bytes],"evidence.png",{type:"image/png"}));const input=document.querySelector(' + JSON.stringify(selector) + ');input.files=transfer.files;input.dispatchEvent(new Event("change",{bubbles:true}));})()');
    await official.go('login.php');
    await official.auth('setup', {name: 'Browser Official', email: 'official@example.test', password, confirm_password: password});
    await official.ready('/index.php');
    check(await official.evaluate('document.querySelector("h1").textContent === "Administrative dashboard"'), 'official dashboard');
    await official.go('user-create.php');
    await official.submit('create_user', {name: 'Browser Personnel', email: 'personnel@example.test', role: 'personnel', team: 'Maintenance crew'});
    const temporary = await official.evaluate('document.getElementById("created-password").value');
    check(temporary.startsWith('MP-'), 'temporary credential shown');
    await personnel.go('login.php'); await personnel.auth('login', {email: 'personnel@example.test', password: temporary});
    await personnel.ready('/login.php', '#temporary-password');
    check(await personnel.evaluate('document.querySelector("#auth-form").dataset.action === "change_password"'), 'temporary password gate');
    await personnel.auth('change_password', {current_password: temporary, password, confirm_password: password}); await personnel.ready('/index.php');
    const staffId = await personnel.evaluate('(async()=>{const r=await fetch("api.php"); return (await r.json()).actor.id;})()');
    check(await personnel.evaluate('document.querySelector("h1").textContent === "Personnel dashboard"'), 'personnel dashboard');
    await guest.go('landing.php');
    check(await guest.evaluate('!!document.querySelector("a[href=\\"report-concern.php\\"]")'), 'public landing action');
    await guest.screenshot('landing-desktop');
    await guest.go('report-concern.php');
    check(await guest.evaluate('!document.querySelector("[name=title],[name=email],[name=password],[name=name]")'), 'anonymous form no account or title');
    await guest.fill('[name=category]', 'Street Lighting');
    check(await guest.evaluate('Array.from(document.querySelector("[name=concernType]").options).some(o=>o.value==="Light not working")'), 'dependent concern choices');
    await guest.fill('[name=concernType]', 'Light not working');
    await guest.click('[name=keyPoints][value="Near pedestrian crossing"]');
    await until(() => guest.evaluate('document.querySelectorAll("#suggestions input[value=\\"0\\"],#suggestions input[value=\\"1\\"],#suggestions input[value=\\"2\\"]").length===3'), 'three suggestions');
    check(await guest.evaluate('document.getElementById("suggestions").textContent.includes("temporary safety")'), 'key points affect suggestions');
    for (const [key,value] of Object.entries({purok:'PRIVATE-PUROK',street:'PRIVATE-STREET',exactArea:'PRIVATE-GATE'})) await guest.fill('[name=' + key + ']', value);
    await guest.click('[name=selectedSuggestion][value="1"]');
    await setPhoto(guest, '#report-photo');
    await guest.screenshot('report-desktop');
    await guest.click('#public-report button[type=submit]');
    await until(() => guest.evaluate('!document.getElementById("receipt").hidden'), 'anonymous receipt');
    const reference = await guest.evaluate('document.getElementById("receipt-reference").value');
    const trackingCode = await guest.evaluate('document.getElementById("receipt-code").value');
    check(reference.startsWith('CON-') && trackingCode.length===48, 'private receipt');
    check(await guest.evaluate('location.search === "" && localStorage.length === 0 && sessionStorage.length === 0'), 'tracking secret absent from URLs and storage');
    const detail = 'complaint.php?id=' + reference;
    await guest.go('track.php');
    await guest.fill('[name=reference]', reference); await guest.fill('[name=trackingCode]', trackingCode); await guest.click('#public-track button');
    await until(() => guest.evaluate('!document.getElementById("tracking-result").hidden'), 'track result');
    check(await guest.evaluate('document.getElementById("tracking-result").textContent.includes("Submitted") && !document.body.textContent.includes("PRIVATE")'), 'safe tracking result');
    await official.go(detail);
    check(await official.evaluate('document.body.textContent.includes("Near pedestrian crossing") && document.body.textContent.includes("PRIVATE-GATE")'), 'official sees structured report and private location');
    check(await official.evaluate('Number(document.querySelector("[data-unread-count]").textContent)>0'), 'notification badge for new report');
    await official.click('.notification-trigger');
    check(await official.evaluate('document.querySelector(".notification-menu").open && document.querySelector("[data-recent-notifications]").textContent.includes("New concern")'), 'bell dropdown shows recent event');
    await official.click('.notification-trigger');
    await official.click('form[data-action=assess] [data-accept-priority]');
    check(await official.evaluate('document.querySelector("form[data-action=assess] [name=priority]").value==="Medium"'), 'accept recommendation selects suggested priority');
    const stale = await tab(); await stale.go('login.php'); await stale.auth('login', {email:'official@example.test',password}); await stale.ready('/index.php'); await stale.go(detail);
    await official.submit('assess', {priority:'High',recommendation:'Qualified staff should inspect and repair.'});
    check(await official.evaluate('document.body.textContent.includes("Recommended personnel: Browser Personnel") && document.querySelector("[name=personnelId]").textContent.includes("0 active")'), 'assignment shows workload and recommended personnel');
    await stale.fill('#recommendation', 'Unsaved draft'); await stale.click('form[data-action=assess] button[type=submit]');
    await until(() => stale.evaluate('document.querySelector(".swal2-title")?.textContent === "This concern has changed"'), 'stale warning');
    await stale.click('.swal2-cancel');
    check(await stale.evaluate('document.getElementById("recommendation").value === "Unsaved draft" && document.querySelector("[data-version]").dataset.version === "1"'), 'conflict preserves draft');
    await official.submit('assign', {personnelId:staffId});
    check(await official.evaluate('document.body.textContent.includes("Personnel email sent")'), 'assignment mail notice');
    await personnel.go('complaints.php'); await personnel.click('.open-case-btn'); await personnel.ready('/complaint.php');
    check(await personnel.evaluate('document.querySelector("form[data-action=start] input[type=file]").required'), 'start evidence input required');
    await setPhoto(personnel, '#start-photo'); await personnel.click('form[data-action=start] [name=actions][value=Inspection]');
    await personnel.submit('start', {workStatus:'Arrived at location'});
    await setPhoto(personnel, '#note-photo'); await personnel.click('form[data-action=note] [name=actions][value=Inspection]');
    await personnel.submit('note', {workStatus:'Inspection completed'});
    await setPhoto(personnel, '#note-photo');
    await personnel.click('form[data-action=note] [name=actions][value=Repair]');
    await personnel.submit('note', {workStatus:'Repair started'});
    check(await personnel.evaluate('document.querySelectorAll(".case-timeline img[src^=\\"evidence.php\\"]").length >= 2'), 'timeline evidence served privately');
    await setPhoto(personnel, '#resolve-photo'); await personnel.click('form[data-action=resolve] [name=actions][value=Repair]');
    await personnel.submit('resolve', {}, true);
    await official.go(detail); await official.submit('verification', {}, true, 'verify');
    check(await official.evaluate('document.querySelectorAll(".evidence-comparison img").length===2 && document.querySelector(".evidence-gallery").textContent.includes("Inspection Evidence") && document.querySelector(".evidence-gallery").textContent.includes("Progress Evidence") && document.querySelector(".evidence-gallery").textContent.includes("Completion Evidence")'), 'before after and four evidence stages');
    await official.evaluate('document.getElementById("evidence").scrollIntoView({block:"start"})');
    await official.screenshot('evidence-desktop');
    await official.fill('form[data-action=edit] [name=concernType]', 'Damaged pole');
    check(await official.evaluate('document.querySelector("form[data-action=edit] [data-accept-priority]").disabled'),'changed selections prevent accepting stale priority advice');
    check(await official.evaluate('document.querySelector(".case-summary").textContent.includes("Closed")'), 'official reviews and closes');
    await official.screenshot('concern-desktop');
    for (const page of ['history.php','reports.php','solutions.php','users.php','profile.php','notifications.php']) { await official.go(page); check(await official.evaluate('!!document.querySelector("h1")'), page + ' renders'); }
    await official.click('[data-notification-row] [data-notification-read]');
    await until(() => official.evaluate('document.querySelector("[data-notification-row] [data-read-label]").textContent==="Read"'), 'single notification read');
    check(true,'mark one notification through UI');
    await official.click('.page-heading [data-notification-read-all]');
    await until(() => official.evaluate('document.querySelector("[data-unread-count]").hidden'), 'mark all badge cleared');
    check(await official.evaluate('!document.querySelector(".notification-row.unread")'),'mark all updates notification center');
    await official.screenshot('notifications-desktop');
    await official.click('[data-notification-row] [data-notification-link]');
    await official.ready('/concern.php');
    check(await official.evaluate('document.querySelector("[data-case-id]").dataset.caseId===' + JSON.stringify(reference)),'notification opens authorized concern');
    await official.go('solutions.php');
    await official.fill('form[data-action=save_rule] [name=category]', 'Street Lighting');
    await official.fill('form[data-action=save_rule] [name=concernType]', 'Light not working');
    await official.click('#load-rule');
    await until(() => official.evaluate('document.querySelector("[name=action1]").value.includes("qualified")'), 'load curated rules');
    check(true, 'solution editor loads rules');
    await official.go('user-edit.php?id=' + staffId);
    await official.submit('update_user', {name:'Updated Personnel',email:'updated@example.test'});
    check(await official.evaluate('document.body.textContent.includes("updated@example.test")'), 'staff name and email edit');
    await personnel.go('reports.php'); check(await personnel.evaluate('document.querySelector("h1").textContent === "Access denied"'), 'personnel direct URL blocked');
    await official.go('complaints.php?search=' + reference);
    const filtered = await official.evaluate('location.href');
    await official.click('.open-case-btn'); await official.ready('/complaint.php');
    let nav = await official.send('Page.getNavigationHistory'); await official.send('Page.navigateToHistoryEntry', {entryId:nav.entries[nav.currentIndex-1].id});
    await until(() => official.evaluate('location.href === ' + JSON.stringify(filtered) + ' && document.readyState === "complete"'), 'Back');
    check(true, 'Back restores filtered list');
    nav = await official.send('Page.getNavigationHistory'); await official.send('Page.navigateToHistoryEntry', {entryId:nav.entries[nav.currentIndex+1].id}); await official.ready('/complaint.php');
    check(true, 'Forward restores concern');
    await official.send('Page.reload'); await official.ready('/complaint.php'); check(true, 'refresh retains detail');
    for (const client of [guest, official, personnel]) await client.send('Emulation.setDeviceMetricsOverride', {width:390,height:844,deviceScaleFactor:1,mobile:true});
    for (const page of ['landing.php','report-concern.php','track.php']) {
      await guest.go(page); check(await guest.evaluate('document.documentElement.scrollWidth <= innerWidth'), page + ' fits mobile');
      await guest.screenshot(page.replace('.php','') + '-mobile');
    }
    await official.go('index.php');
    check(await official.evaluate('getComputedStyle(document.querySelector(".mobile-dock")).display === "grid" && document.documentElement.scrollWidth <= innerWidth'), 'mobile dashboard and navigation');
    await official.click('.mobile-dock button[data-menu]'); check(await official.evaluate('document.querySelector(".sidebar").classList.contains("mobile-open")'), 'mobile menu'); await official.click('.sidebar-scrim');
    await official.screenshot('dashboard-mobile');
    await official.go(detail); check(await official.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'mobile concern fits'); await official.screenshot('concern-mobile');
    await official.evaluate('document.getElementById("evidence").scrollIntoView({block:"start"})');
    check(await official.evaluate('getComputedStyle(document.querySelector(".evidence-comparison")).gridTemplateColumns.split(" ").length===1'),'mobile before after stacks');
    await official.screenshot('evidence-mobile');
    await official.go('notifications.php');
    check(await official.evaluate('document.documentElement.scrollWidth<=innerWidth'),'mobile notification center fits');
    await official.click('.notification-trigger');
    check(await official.evaluate('document.querySelector(".notification-dropdown").getBoundingClientRect().left>=0 && document.querySelector(".notification-dropdown").getBoundingClientRect().right<=innerWidth'),'mobile notification dropdown fits');
    await official.screenshot('notifications-mobile');
    await personnel.go('complaints.php'); check(await personnel.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'mobile work queue fits');
    check(await guest.evaluate('(async()=>{const r=await fetch("api.php");return r.status;})()') === 401, 'guest remains anonymous');
    check(await official.evaluate('(async()=>{const r=await fetch("api.php");return (await r.json()).actor.role;})()') === 'official', 'isolated official session');
    check(await personnel.evaluate('(async()=>{const r=await fetch("api.php");return (await r.json()).actor.role;})()') === 'personnel', 'isolated personnel session');
    check(errors.length === 0, 'no uncaught JavaScript errors');
    console.log('PASS: ' + checks + ' browser checks for anonymous reporting, private tracking, staff workflows, images, recommendations, navigation and responsive layout.');
    console.log('Screenshots saved under tests/tmp/.');
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
