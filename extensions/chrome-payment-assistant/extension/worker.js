'use strict';
importScripts('i18n.js');
const ORIGIN = 'https://mochi.bz';
const API = ORIGIN + '/Api/Browser.ashx';
const ALARM = 'mochipay-check';
let queue = Promise.resolve();
function serial(task) { const result = queue.then(task); queue = result.catch(() => {}); return result; }
async function state() { return (await chrome.storage.local.get('assistant')).assistant || {}; }
async function save(s) { await chrome.storage.local.set({assistant: s}); }
function publicState(s) {
  const {token, seen, ...safe} = s;
  return {...safe, connected: Boolean(token)};
}
function decimalKey(value) {
  if (typeof value !== 'string' || !/^[0-9]{1,28}(?:\.[0-9]{1,8})?$/.test(value)) throw new Error('INVALID_RESPONSE');
  const [integer, fraction = ''] = value.split('.');
  return integer.replace(/^0+(?=\d)/, '') + (fraction.replace(/0+$/, '') ? '.' + fraction.replace(/0+$/, '') : '');
}
function matchesAttempt(o, a) {
  return o.merchant_order_id === 'CH' + a.request_id.slice(7).toUpperCase() &&
    o.currency === a.currency && o.payment_method === a.payment_method && decimalKey(o.amount) === decimalKey(a.amount);
}
function safeOrder(o) {
  if (!o || !/^[a-f0-9]{32}$/.test(o.order_id)) throw new Error('INVALID_RESPONSE');
  const path = ORIGIN + '/pay/' + o.order_id;
  if (o.payment_url !== path || o.detail_url !== ORIGIN + '/Merchant/OrderDetail.aspx?id=' + o.order_id ||
      typeof o.amount !== 'string' || typeof o.pay_amount !== 'string' ||
      typeof o.received_amount !== 'string' || typeof o.is_paid !== 'boolean') throw new Error('INVALID_RESPONSE');
  decimalKey(o.amount); decimalKey(o.pay_amount); decimalKey(o.received_amount);
  if (o.is_paid && (o.status !== 'PAID' || decimalKey(o.pay_amount) === '0' || decimalKey(o.received_amount) !== decimalKey(o.pay_amount)))
    throw new Error('INVALID_RESPONSE');
  return o;
}
async function request(s, url, payload) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 30000);
  try {
    const headers = {'Accept': 'application/json'};
    if (s.token) headers.Authorization = 'Bearer ' + s.token;
    if (payload) headers['Content-Type'] = 'application/json';
    const r = await fetch(url, {method: payload ? 'POST' : 'GET', headers,
      body: payload ? JSON.stringify(payload) : undefined, credentials: 'omit', cache: 'no-store',
      redirect: 'error', signal: controller.signal});
    let data; try { data = await r.json(); } catch (_) { if (r.ok) throw new Error('INVALID_RESPONSE'); data = {success: false, message: 'SERVICE_ERROR'}; }
    if (!r.ok || data.success !== true) {
      const e = new Error(typeof data.message === 'string' ? data.message : 'SERVICE_ERROR'); e.status = r.status;
      if (r.status === 401 && s.token) {
        delete s.token; s.orders = []; s.seen = {}; delete s.profile; s.error = 'CONNECTION_REQUIRED';
        await save(s); await chrome.alarms.clear(ALARM);
      }
      throw e;
    }
    return data;
  } catch (e) {
    if (e.name === 'AbortError' || e instanceof TypeError) throw new Error('NETWORK_ERROR');
    throw e;
  } finally { clearTimeout(timer); }
}
async function ensureAlarm() {
  const s = await state();
  if (s.token && !(await chrome.alarms.get(ALARM))) await chrome.alarms.create(ALARM, {periodInMinutes: 1});
  if (!s.token) await chrome.alarms.clear(ALARM);
}
async function refresh() {
  const s = await state(); if (!s.token) return publicState(s);
  const watch = (s.orders || []).filter(o => !o.is_paid && !['EXPIRED', 'CANCELLED', 'FAILED'].includes(o.status))
    .slice(0, 100).map(o => o.order_id).join(',');
  const url = API + '?action=orders&watch=' + encodeURIComponent(watch) +
    (s.attempt ? '&request_id=' + encodeURIComponent(s.attempt.request_id) : '');
  let data;
  try { data = await request(s, url); }
  catch (error) {
    const transient = error.message === 'NETWORK_ERROR' || error.status === 408 || error.status === 429 || error.status >= 500;
    if (!transient) throw error;
    s.queryFailures = (s.queryFailures || 0) + 1; s.queryPending = true;
    if (s.queryFailures < 3) { delete s.error; await save(s); return publicState(s); }
    s.error = 'NETWORK_ERROR'; await save(s); throw new Error('NETWORK_ERROR');
  }
  if (!Array.isArray(data.orders) || !data.profile || !Array.isArray(data.profile.methods) || !Array.isArray(data.profile.currencies))
    throw new Error('INVALID_RESPONSE');
  const orders = data.orders.map(safeOrder); const seen = s.seen || {}; const notify = [];
  for (const o of orders) {
    // Initial history is a baseline; notify only observed pending -> confirmed paid.
    if (s.baseline && seen[o.order_id] === false && o.is_paid) notify.push(o);
    seen[o.order_id] = o.is_paid;
    if (s.attempt && matchesAttempt(o, s.attempt)) {
      s.lastOrder = o.order_id; delete s.attempt;
    }
  }
  s.seen = Object.fromEntries(orders.map(o => [o.order_id, seen[o.order_id]]));
  s.orders = orders; s.profile = data.profile; s.baseline = true; s.updated = Date.now(); s.queryFailures = 0; delete s.queryPending; delete s.error;
  // Persist transitions before showing notices to prevent repeats on worker restarts.
  await save(s);
  if (s.notifications && chrome.notifications?.create && await chrome.permissions.contains({permissions: ['notifications']})) {
    for (const o of notify) await chrome.notifications.create('paid-' + o.order_id, {
      type: 'basic', iconUrl: 'icons/icon128.png', title: MP_I18N.text('received', s.language),
      message: o.received_amount + ' ' + o.payment_method.split('_')[0] + ' · ' + o.merchant_order_id
    });
  }
  return publicState(s);
}
async function create(payload) {
  const s = await state(); if (!s.token) throw new Error('CONNECTION_REQUIRED');
  if (!payload || !/^[0-9]{1,16}(?:\.[0-9]{1,8})?$/.test(payload.amount || '') ||
      !/[1-9]/.test(payload.amount) || !MP_I18N.methods.includes(payload.payment_method) ||
      !/^[A-Z]{2,8}$/.test(payload.currency || '') || typeof payload.description !== 'string' || payload.description.length > 500)
    throw new Error('INVALID_BROWSER_ORDER');
  s.attempt = {request_id: 'chrome:' + crypto.randomUUID().replaceAll('-', ''), amount: payload.amount,
    currency: payload.currency, payment_method: payload.payment_method, description: payload.description};
  await save(s); return sendAttempt(s);
}
async function sendAttempt(s) {
  if (!s.token || !s.attempt) throw new Error('CONNECTION_REQUIRED');
  try {
    const data = await request(s, ORIGIN + '/Api/BrowserCreate.aspx', s.attempt);
    const o = safeOrder(data.order);
    if (!matchesAttempt(o, s.attempt)) throw new Error('INVALID_RESPONSE');
    s.lastOrder = o.order_id; s.orders = [o, ...(s.orders || []).filter(x => x.order_id !== o.order_id)].slice(0, 120);
    s.seen = {...(s.seen || {}), [o.order_id]: o.is_paid}; delete s.attempt; delete s.error;
    await save(s); return publicState(s);
  } catch (e) {
    // A transport/5xx/409 result can be uncertain. Keep the exact saved payload/id.
    // Validation errors are final, except subscription/revocation: retain for recovery.
    if (e.status === 400 || e.status === 422) delete s.attempt;
    s.error = e.message; await save(s); throw e;
  }
}
async function dispatch(message) {
  let s = await state();
  switch (message.type) {
    case 'get': return publicState(s);
    case 'openConnection': {
      // Keep the connection form alive while the merchant gets a code elsewhere.
      await chrome.tabs.create({url: chrome.runtime.getURL('popup.html?mode=connect'), active: false});
      await chrome.tabs.create({url: ORIGIN + '/Merchant/BrowserAssistant.aspx'});
      return {};
    }
    case 'pair': {
      const code = (message.code || '').trim();
      if (!/^[A-Za-z0-9_-]{43}$/.test(code)) throw new Error('INVALID_CODE');
      if (s.token) throw new Error('ALREADY_CONNECTED');
      const data = await request({}, API + '?action=pair', {code});
      if (!/^[A-Za-z0-9_-]{43}$/.test(data.token || '') || !/^[a-f0-9]{32}$/.test(data.merchant_id || '')) throw new Error('INVALID_RESPONSE');
      const attempt = data.merchant_id === s.merchant_id ? s.attempt : undefined;
      s = {token: data.token, merchant_id: data.merchant_id, merchant_name: data.merchant_name,
        language: s.language, notifications: Boolean(s.notifications), attempt};
      await save(s); await ensureAlarm();
      try { return await refresh(); } catch (_) { return publicState(await state()); }
    }
    case 'refresh': return refresh();
    case 'create': return create(message.payload);
    case 'retry':
      await refresh(); s = await state();
      if (s.queryPending) return publicState(s);
      return s.attempt ? sendAttempt(s) : publicState(s);
    case 'qr': {
      if (!s.token || !(s.orders || []).some(o => o.order_id === message.id)) throw new Error('ORDER_NOT_FOUND');
      let data;
      for (let attempt = 0; attempt < 3; attempt++) {
        try { data = await request(s, API + '?action=qr&id=' + message.id); break; }
        catch (error) {
          const transient = error.message === 'NETWORK_ERROR' || error.status === 408 || error.status === 429 || error.status >= 500;
          if (!transient) throw error;
          if (attempt === 2) throw new Error('NETWORK_ERROR');
        }
      }
      if (typeof data.qr_png !== 'string' || !/^[A-Za-z0-9+/=]{1,200000}$/.test(data.qr_png)) throw new Error('INVALID_RESPONSE');
      return {qr: 'data:image/png;base64,' + data.qr_png};
    }
    case 'notifications':
      s.notifications = Boolean(message.enabled) && await chrome.permissions.contains({permissions: ['notifications']});
      await save(s); return publicState(s);
    case 'language':
      s.language = MP_I18N.normalize(message.language); await save(s); return publicState(s);
    case 'disconnect':
      if (s.token) await request(s, API + '?action=revoke', {});
      s = {language: s.language}; await save(s); await chrome.alarms.clear(ALARM); return publicState(s);
    default: throw new Error('INVALID_ACTION');
  }
}
chrome.runtime.onMessage.addListener((message, sender, respond) => {
  if (sender.id !== chrome.runtime.id || ![chrome.runtime.getURL('popup.html'), chrome.runtime.getURL('popup.html?mode=connect')].includes(sender.url)) return false;
  serial(() => dispatch(message)).then(data => respond({ok: true, data}),
    error => respond({ok: false, error: error.message || 'SERVICE_ERROR'}));
  return true;
});
chrome.alarms.onAlarm.addListener(alarm => {
  if (alarm.name === ALARM) serial(refresh).catch(async error => {
    const s = await state(); s.error = error.message; await save(s);
  });
});
chrome.runtime.onStartup.addListener(() => { serial(ensureAlarm).catch(() => {}); });
chrome.runtime.onInstalled.addListener(() => { serial(ensureAlarm).catch(() => {}); });
chrome.permissions.onRemoved.addListener(p => {
  if ((p.permissions || []).includes('notifications')) serial(async () => { const s = await state(); s.notifications = false; await save(s); });
});
let notificationClickRegistered = false;
function registerNotificationClick() {
  // Optional APIs can be absent before permission is granted. Never fail startup.
  if (notificationClickRegistered || !chrome.notifications?.onClicked) return;
  chrome.notifications.onClicked.addListener(id => {
  if (/^paid-[a-f0-9]{32}$/.test(id)) chrome.tabs.create({url: ORIGIN + '/Merchant/OrderDetail.aspx?id=' + id.slice(5)});
  });
  notificationClickRegistered = true;
}
registerNotificationClick();
chrome.permissions.onAdded.addListener(p => {
  if ((p.permissions || []).includes('notifications')) registerNotificationClick();
});
chrome.storage.local.setAccessLevel({accessLevel: 'TRUSTED_CONTEXTS'}).catch(() => {});
serial(ensureAlarm).catch(() => {});
