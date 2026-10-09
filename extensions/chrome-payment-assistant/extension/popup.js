'use strict';
const $ = id => document.getElementById(id);
let current = {}, selected, tab = 'new', busy = false;
let language = MP_I18N.normalize(chrome.i18n.getUILanguage());
function text(key) { return MP_I18N.text(key, language); }
async function call(type, data = {}) {
  let timer;
  const timeout = new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('NETWORK_ERROR')), 130000); });
  let result;
  try { result = await Promise.race([chrome.runtime.sendMessage({type, ...data}), timeout]); }
  finally { clearTimeout(timer); }
  if (!result || !result.ok) throw new Error(result?.error || 'SERVICE_ERROR');
  return result.data;
}
function message(key) { $('message').textContent = text(key); $('message').hidden = !key; }
function localize() {
  document.documentElement.lang = language;
  document.documentElement.dir = ['ar', 'fa'].includes(language) ? 'rtl' : 'ltr';
  for (const element of document.querySelectorAll('[data-i18n]')) element.textContent = text(element.dataset.i18n);
  $('language').value = language; $('language').setAttribute('aria-label', text('language')); $('qr').alt = text('qr');
}
function showTab(value) {
  tab = value;
  for (const t of ['new', 'recent', 'settings']) {
    $(t + 'Panel').hidden = t !== value; $(t + 'Tab').classList.toggle('active', t === value);
  }
}
function options(id, values, labels) {
  const select = $(id), previous = select.value; select.replaceChildren();
  for (const value of values) { const o = document.createElement('option'); o.value = value; o.textContent = labels ? labels[value] : value; select.append(o); }
  if (values.includes(previous)) select.value = previous;
}
function orderStatus(o) { return o.is_paid ? 'paid' : o.status === 'PAID' ? 'review' : ({WAITING_PAYMENT: 'waiting', PENDING: 'waiting', PAID_PARTIAL: 'partial', PARTIAL_PAYMENT: 'partial', EXPIRED: 'expired', CANCELLED: 'cancelled', PAYMENT_REVIEW: 'review'})[o.status] || 'review'; }
function badge(node, o) { node.textContent = text(orderStatus(o)); node.className = 'badge' + (o.is_paid ? ' paid' : ''); }
function share(o) {
  selected = o; showTab('new'); $('orderForm').hidden = true; $('result').hidden = false;
  $('resultAmount').textContent = o.amount + ' ' + o.currency; badge($('resultStatus'), o);
  $('resultNote').textContent = o.description || ''; $('paymentLink').value = o.payment_url;
  $('detail').href = o.detail_url; $('qrPanel').hidden = true; $('qr').removeAttribute('src');
}
function render(s) {
  current = s; language = MP_I18N.normalize(s.language || language); localize();
  $('connect').hidden = s.connected; $('workspace').hidden = !s.connected;
  if (s.error && $('message').hidden) message(MP_I18N.errors[s.error] || 'serviceError');
  if (!s.connected) return;
  $('merchant').textContent = s.profile?.name || s.merchant_name || 'MochiPay';
  $('accountStatus').textContent = text(s.profile?.status === 'ACTIVE' ? 'active' : 'subscription');
  $('notifications').checked = Boolean(s.notifications);
  $('attempt').hidden = !s.attempt;
  const canCreate = s.profile?.status === 'ACTIVE' && s.profile?.methods?.length > 0;
  $('create').disabled = !canCreate || busy;
  options('currency', s.profile?.currencies || []);
  options('method', s.profile?.methods || [], MP_I18N.labels);
  $('updated').textContent = s.updated ? text('updated') + ' ' + new Date(s.updated).toLocaleTimeString(language === 'zh' ? 'zh-CN' : language, {hour: '2-digit', minute: '2-digit'}) : '';
  $('empty').hidden = Boolean(s.orders?.length); $('orders').replaceChildren();
  for (const o of (s.orders || []).slice(0, 20)) {
    const card = document.createElement('article'); card.className = 'order';
    const head = document.createElement('div'); head.className = 'orderHead';
    const amount = document.createElement('strong'); amount.textContent = o.amount + ' ' + o.currency;
    const status = document.createElement('span'); badge(status, o); head.append(amount, status);
    const note = document.createElement('p'); note.className = 'fine'; note.textContent = o.description || o.merchant_order_id;
    const meta = document.createElement('p'); meta.className = 'fine'; meta.textContent = MP_I18N.labels[o.payment_method] || o.payment_method;
    const button = document.createElement('button'); button.type = 'button'; button.className = 'secondary'; button.textContent = text('share'); button.addEventListener('click', () => share(o));
    const detail = document.createElement('a'); detail.href = o.detail_url; detail.target = '_blank'; detail.rel = 'noopener'; detail.textContent = text('details');
    card.append(head, note, meta, button, detail); $('orders').append(card);
  }
  if (selected) { const latest = s.orders?.find(o => o.order_id === selected.order_id); if (latest) { selected = latest; badge($('resultStatus'), selected); } }
}
async function task(fn) {
  if (busy) return;
  busy = true; message(''); document.querySelectorAll('button').forEach(b => b.disabled = true);
  try { await fn(); } catch (e) { message(MP_I18N.errors[e.message] || 'serviceError'); }
  finally {
    busy = false; document.querySelectorAll('button').forEach(b => b.disabled = false);
    // Release controls before reading state; a stalled worker must not lock the form.
    call('get').then(render).catch(() => message('serviceError'));
  }
}
for (const item of MP_I18N.languages) { const option = document.createElement('option'); option.value = item[0]; option.textContent = item[1]; $('language').append(option); }
$('language').addEventListener('change', () => task(async () => { language = $('language').value; render(await call('language', {language})); }));
const connectionTab = new URLSearchParams(location.search).get('mode') === 'connect';
if (connectionTab) document.body.classList.add('connectionTab');
$('getCode').addEventListener('click', e => {
  if (connectionTab) return; // The persistent form stays open in this tab.
  e.preventDefault();
  task(() => call('openConnection'));
});
$('connectForm').addEventListener('submit', e => {
  e.preventDefault(); task(async () => { render(await call('pair', {code: $('code').value})); $('code').value = ''; message(current.connected ? '' : 'connectionRequired'); });
});
for (const t of ['new', 'recent', 'settings']) $(t + 'Tab').addEventListener('click', () => showTab(t));
$('orderForm').addEventListener('submit', e => {
  e.preventDefault(); task(async () => {
    render(await call('create', {payload: {amount: $('amount').value.trim(), currency: $('currency').value,
      payment_method: $('method').value, description: $('note').value.trim()}}));
    const o = current.orders.find(x => x.order_id === current.lastOrder); if (o) share(o);
  });
});
$('retry').addEventListener('click', () => task(async () => { render(await call('retry')); const o = current.orders.find(x => x.order_id === current.lastOrder); if (o) share(o); }));
$('refresh').addEventListener('click', () => task(async () => { render(await call('refresh')); }));
$('copy').addEventListener('click', () => { if (selected) navigator.clipboard.writeText(selected.payment_url).then(() => message('copied'), () => { $('paymentLink').focus(); $('paymentLink').select(); message('copyManual'); }); });
$('showQr').addEventListener('click', () => task(async () => { const data = await call('qr', {id: selected.order_id}); $('qr').src = data.qr; $('qrPanel').hidden = false; $('qrPanel').scrollIntoView({block: 'end'}); }));
$('another').addEventListener('click', () => { selected = undefined; $('result').hidden = true; $('orderForm').hidden = false; $('amount').value = ''; $('note').value = ''; $('amount').focus(); });
$('notifications').addEventListener('change', async () => {
  const enabled = $('notifications').checked;
  try {
    if (enabled && !(await chrome.permissions.request({permissions: ['notifications']}))) { $('notifications').checked = false; message('permissionDenied'); return; }
    render(await call('notifications', {enabled}));
    if (!enabled) await chrome.permissions.remove({permissions: ['notifications']});
  } catch (_) { message('serviceError'); }
});
$('disconnect').addEventListener('click', () => task(async () => {
  if (!confirm(text('disconnectConfirm'))) return;
  render(await call('disconnect')); selected = undefined; $('result').hidden = true; $('orderForm').hidden = false;
}));
localize();
// Reading initial state is not a payment operation and must not lock Connect.
call('get').then(s => {
  render(s); if (current.connected) return task(async () => {
    render(await call('refresh'));
    const o = current.orders.find(x => x.order_id === current.lastOrder); if (o) share(o);
  });
}).catch(() => message('serviceError'));

chrome.storage.onChanged.addListener((changes, area) => {
  if (area === 'local' && changes.assistant && !busy) call('get').then(render).catch(() => {});
});
