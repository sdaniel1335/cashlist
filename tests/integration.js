const assert = require('node:assert/strict');
const fs = require('node:fs');
const cp = require('node:child_process');
const path = require('node:path');
const {request, chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const maria = process.env.CASH_TEST_MYSQL;
const php = process.env.CASH_TEST_PHP;
const database = process.env.CASH_TEST_DATABASE;
const base_url = process.env.CASH_TEST_URL || 'http://127.0.0.1:8096';
assert.ok(maria && php && database && /_test$/.test(database), 'Provide CASH_TEST_MYSQL, CASH_TEST_PHP, and a dedicated CASH_TEST_DATABASE ending in _test');
function sql(statement) { return cp.execFileSync(maria, ['--host=127.0.0.1','--port='+(process.env.CASH_TEST_PORT || '33369'),'--user='+(process.env.CASH_TEST_USER || 'root'),'--password='+(process.env.CASH_TEST_PASSWORD || ''),'--batch','--skip-column-names',database,'-e', statement], {encoding:'utf8'}).trim(); }
function token(html, name='_csrf') { const found = html.match(new RegExp('name="'+name+'" value="([^"]*)"')); assert.ok(found, name); return found[1]; }
(async () => {
  assert.equal(sql('SELECT COUNT(*) FROM users'), '0', 'Use a fresh, dedicated test database with install.sql imported, without DB_PREFIX');
  fs.mkdirSync('.cashlist-test', {recursive:true});
  const hash = cp.execFileSync(php, ['-r', 'echo password_hash("test_pass_123", PASSWORD_BCRYPT);'], {encoding:'utf8'}).trim();
  sql("INSERT INTO users(id,username,password_hash,created_at) VALUES (1,'cash_test','"+hash+"',NOW()),(2,'other_test','"+hash+"',NOW())");
  const api = await request.newContext({baseURL:base_url});
  let response = await api.get('/'); assert.match(response.url(), /login$/);
  let html = await response.text();
  response = await api.post('/login', {form:{_csrf:token(html),username:'cash_test',password:'test_pass_123'}});
  assert.equal(response.status(),200); html=await response.text(); assert.match(html,/Összes pénz/);
  async function post(route, form) { const page=await api.get(route==='/transaction/delete'?'/':route); const result=await api.post(route,{form:{_csrf:token(await page.text()),...form}}); assert.equal(result.status(),200); return await result.text(); }
  await post('/accounts',{name:'OTP',currency:'HUF',is_cash:'0'});
  await post('/accounts',{name:'Pénztárca',currency:'HUF',is_cash:'1'});
  await post('/accounts',{name:'USD zseb',currency:'USD',is_cash:'1'});
  sql("INSERT INTO cash_accounts(user_id,name,currency,is_cash) VALUES (2,'Private','HUF',0)");
  let sequence=0;
  async function transaction(overrides={}) { sequence++; return post('/transaction',{id:'0',kind:'income',booked_on:'2026-11-13',category:'P',account_id:'1',amount:'1000',original_amount:'',original_currency:'HUF',note:'Test',request_token:sequence.toString(16).padStart(64,'0'),...overrides}); }
  html=await transaction({kind:'opening',amount:'100000'}); assert.match(html,/Tétel elmentve/);
  await transaction({kind:'expense',amount:'7400',original_amount:'20',original_currency:'USD',note:'USD vásárlás'});
  await transaction({kind:'income',amount:'50000',category:'W',booked_on:'2026-12-01'});
  await transaction({kind:'transfer',amount:'10000',target_account_id:'2',target_category:'P'});
  assert.equal(sql('SELECT SUM(amount) FROM cash_entries WHERE account_id=1 AND transaction_id IN (SELECT id FROM cash_transactions WHERE booked_on<=\'2026-11-13\')'),'82600.00');
  assert.equal(sql('SELECT SUM(amount) FROM cash_entries WHERE account_id=2'),'10000.00');
  await transaction({kind:'transfer',amount:'2000',target_account_id:'1',target_category:'B'});
  await transaction({account_id:'3',amount:'100',main_amount:'37000',original_currency:'USD'});
  assert.equal(sql('SELECT SUM(main_amount) FROM cash_entries WHERE transaction_id IN (SELECT id FROM cash_transactions WHERE kind=\'transfer\')'),'0.00');
  html=await (await api.get('/?date=2026-11-13')).text(); assert.match(html,/129 600 HUF/); assert.match(html,/Tervezett/);
  html=await (await api.get('/?date=2026-12-01')).text(); assert.match(html,/179 600 HUF/);
  const count=sql('SELECT COUNT(*) FROM cash_transactions');
  html=await transaction({account_id:'4'}); assert.match(html,/nem található/); assert.equal(sql('SELECT COUNT(*) FROM cash_transactions'),count);
  html=await transaction({kind:'transfer',target_account_id:'3',target_category:'P',target_amount:'-1'}); assert.match(html,/legfeljebb/); assert.equal(sql('SELECT COUNT(*) FROM cash_transactions'),count);
  html=await transaction({request_token:'1'.padStart(64,'0')}); assert.equal(sql('SELECT COUNT(*) FROM cash_transactions'),count);
  html=await post('/settings',{main_currency:'EUR'}); assert.match(html,/első tétel előtt/);
  let edit=await (await api.get('/transaction?id=2')).text();
  html=await post('/transaction',{id:'2',revision:'0',kind:'expense',booked_on:'2026-11-13',category:'P',account_id:'1',amount:'7500',original_amount:'20',original_currency:'USD',note:'Edited'});
  assert.match(html,/Tétel elmentve/); assert.equal(sql('SELECT amount FROM cash_entries WHERE transaction_id=2'),'-7500.00');
  html=await post('/transaction',{id:'2',revision:'0',kind:'expense',booked_on:'2026-11-13',category:'P',account_id:'1',amount:'7600',original_amount:'20',original_currency:'USD'});
  assert.match(html,/közben megváltozott/); assert.equal(sql('SELECT amount FROM cash_entries WHERE transaction_id=2'),'-7500.00');
  html=await post('/transaction/delete',{id:'4',revision:'0'}); assert.equal(sql('SELECT COUNT(*) FROM cash_entries WHERE transaction_id=4'),'0');
  const rejected=await api.post('/transaction',{form:{kind:'income'}}); assert.equal(rejected.status(),400);
  const second=await request.newContext({baseURL:base_url});
  let login=await (await second.get('/login')).text();
  await second.post('/login',{form:{_csrf:token(login),username:'other_test',password:'test_pass_123'}});
  assert.equal((await second.get('/transaction?id=2')).status(),404);
  console.log('PASS: login, accounts, opening, foreign expense, future date balances, transfer, B/P move, foreign account, ownership, rollback, duplicate submit, currency lock, edit conflict, cascade delete, CSRF.');
  const browser=await chromium.launch({executablePath:process.env.CASH_TEST_CHROME,headless:true});
  const context=await browser.newContext(); await context.addCookies((await api.storageState()).cookies);
  const page=await context.newPage(); const errors=[]; page.on('pageerror',e=>errors.push(e.message));
  for (const width of [1280,390]) {
    await page.setViewportSize({width,height:1000});
    for (const route of ['/','/accounts','/settings','/transaction','/transaction?id=2']) {
      await page.goto(base_url+route);
      assert.equal(await page.locator('body').evaluate(e=>e.scrollWidth<=innerWidth),true,`overflow ${route} ${width}`);
      assert.equal(await page.locator('body').innerText().then(t=>/Fatal error|Warning:|Deprecated:/.test(t)),false);
    }
    await page.goto(base_url+'/?date=2026-11-13');
    await page.screenshot({path:'.cashlist-test/overview-'+width+'.png',fullPage:true});
  }
  await page.goto(base_url+'/transaction');
  await page.locator('#kind').selectOption('transfer'); assert.equal(await page.locator('.transfer-fields').isVisible(),true);
  await page.locator('#account_id').selectOption('3'); assert.equal(await page.locator('.conversion-field').isVisible(),true);
  assert.deepEqual(errors,[]);
  console.log('PASS: desktop/mobile pages, no horizontal overflow, no PHP/JS errors, dynamic transfer/FX form.');
  await browser.close(); await api.dispose(); await second.dispose();
})().catch(e=>{console.error(e);process.exit(1)});

