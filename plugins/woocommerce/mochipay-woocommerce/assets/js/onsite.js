/* MochiPay buyer-language UI build 75. No API, amount or payment-state changes. */
(function(global){'use strict';
var catalogs={"es":{"Complete your payment":"Completa tu pago","Close payment dialog":"Cerrar ventana de pago","Payment details":"Detalles de pago","Payment address QR code":"Código QR de la dirección de pago","Copy Amount":"Copiar importe","Copy Address":"Copiar dirección","Payment address":"Dirección de pago","Copied":"Copiado","QR unavailable. Copy the address instead.":"QR no disponible. Copia la dirección.","Network":"Red","Check payment status":"Consultar estado del pago","Closing the dialog keeps your payment. Do not pay twice.":"Cerrar conserva tu pago. No pagues dos veces.","Closing this dialog keeps your existing payment. Do not pay twice.":"Cerrar conserva tu pago existente. No pagues dos veces.","Connection failed. Your order is saved. Please check again.":"Conexión fallida. Pedido guardado; vuelve a comprobar.","Connection failed. Your order is kept; please check again.":"Conexión fallida. Pedido conservado; vuelve a comprobar.","Do not send another payment. Contact the store to check this order.":"No vuelvas a pagar. Contacta con la tienda para revisar el pedido.","Do not send another payment. Contact the store to review this order.":"No vuelvas a pagar. Contacta con la tienda para revisar el pedido.","Loading payment details…":"Cargando detalles de pago…","Loading payment…":"Cargando pago…","Payment confirmed. Opening your order…":"Pago confirmado. Abriendo tu pedido…","Payment requires review:":"El pago requiere revisión:","Please select and copy manually.":"Selecciona y copia manualmente.","Select and copy the address manually.":"Selecciona y copia la dirección manualmente.","QR code contains the address only. Enter the exact amount shown.":"El QR solo contiene la dirección. Introduce el importe exacto mostrado.","Reopen payment dialog":"Volver a abrir el pago","Unable to verify payment details":"No se pudieron verificar los detalles","Unable to verify payment.":"No se pudo verificar el pago.","Use only the asset and network shown. Send the exact amount. Network fees must not reduce the received amount.":"Usa solo el activo y red indicados. Envía el importe exacto; las comisiones no deben reducir lo recibido.","Use only the asset and network shown above. Send the exact amount; network fees must not reduce the received amount.":"Usa solo el activo y red de arriba. Envía el importe exacto; las comisiones no deben reducir lo recibido.","Waiting for payment":"Esperando pago","Payment detected; waiting for confirmation…":"Pago detectado; esperando confirmación…","Payment reference:":"Referencia de pago:","Order total:":"Total del pedido:","Order #":"Pedido #","Expires at (MochiPay server time):":"Vence (hora del servidor MochiPay):","Includes a small payment identification adjustment.":"Incluye un pequeño ajuste para identificar el pago."},"pt-br":{"Complete your payment":"Conclua seu pagamento","Close payment dialog":"Fechar janela de pagamento","Payment details":"Detalhes do pagamento","Payment address QR code":"QR code do endereço de pagamento","Copy Amount":"Copiar valor","Copy Address":"Copiar endereço","Payment address":"Endereço de pagamento","Copied":"Copiado","QR unavailable. Copy the address instead.":"QR indisponível. Copie o endereço.","Network":"Rede","Check payment status":"Consultar estado do pagamento","Closing the dialog keeps your payment. Do not pay twice.":"Fechar mantém seu pagamento. Não pague duas vezes.","Closing this dialog keeps your existing payment. Do not pay twice.":"Fechar mantém seu pagamento existente. Não pague duas vezes.","Connection failed. Your order is saved. Please check again.":"Conexão falhou. Pedido salvo; verifique novamente.","Connection failed. Your order is kept; please check again.":"Conexão falhou. Pedido mantido; verifique novamente.","Do not send another payment. Contact the store to check this order.":"Não pague novamente. Contate a loja para conferir o pedido.","Do not send another payment. Contact the store to review this order.":"Não pague novamente. Contate a loja para revisar o pedido.","Loading payment details…":"Carregando detalhes do pagamento…","Loading payment…":"Carregando pagamento…","Payment confirmed. Opening your order…":"Pagamento confirmado. Abrindo seu pedido…","Payment requires review:":"O pagamento exige revisão:","Please select and copy manually.":"Selecione e copie manualmente.","Select and copy the address manually.":"Selecione e copie o endereço manualmente.","QR code contains the address only. Enter the exact amount shown.":"O QR contém só o endereço. Informe o valor exato mostrado.","Reopen payment dialog":"Reabrir pagamento","Unable to verify payment details":"Não foi possível verificar os detalhes","Unable to verify payment.":"Não foi possível verificar o pagamento.","Use only the asset and network shown. Send the exact amount. Network fees must not reduce the received amount.":"Use apenas o ativo e a rede indicados. Envie o valor exato; taxas não devem reduzir o recebido.","Use only the asset and network shown above. Send the exact amount; network fees must not reduce the received amount.":"Use apenas o ativo e a rede acima. Envie o valor exato; taxas não devem reduzir o recebido.","Waiting for payment":"Aguardando pagamento","Payment detected; waiting for confirmation…":"Pagamento detectado; aguardando confirmação…","Payment reference:":"Referência do pagamento:","Order total:":"Total do pedido:","Order #":"Pedido #","Expires at (MochiPay server time):":"Expira (horário do servidor MochiPay):","Includes a small payment identification adjustment.":"Inclui um pequeno ajuste para identificar o pagamento."},"fr":{"Complete your payment":"Finalisez votre paiement","Close payment dialog":"Fermer la fenêtre de paiement","Payment details":"Détails du paiement","Payment address QR code":"Code QR de l’adresse de paiement","Copy Amount":"Copier le montant","Copy Address":"Copier l’adresse","Payment address":"Adresse de paiement","Copied":"Copié","QR unavailable. Copy the address instead.":"QR indisponible. Copiez l’adresse.","Network":"Réseau","Check payment status":"Vérifier le paiement","Closing the dialog keeps your payment. Do not pay twice.":"Fermer conserve votre paiement. Ne payez pas deux fois.","Closing this dialog keeps your existing payment. Do not pay twice.":"Fermer conserve votre paiement existant. Ne payez pas deux fois.","Connection failed. Your order is saved. Please check again.":"Connexion échouée. Commande enregistrée ; vérifiez à nouveau.","Connection failed. Your order is kept; please check again.":"Connexion échouée. Commande conservée ; vérifiez à nouveau.","Do not send another payment. Contact the store to check this order.":"Ne payez pas à nouveau. Contactez la boutique pour vérifier la commande.","Do not send another payment. Contact the store to review this order.":"Ne payez pas à nouveau. Contactez la boutique pour vérifier la commande.","Loading payment details…":"Chargement des détails de paiement…","Loading payment…":"Chargement du paiement…","Payment confirmed. Opening your order…":"Paiement confirmé. Ouverture de la commande…","Payment requires review:":"Le paiement nécessite une vérification :","Please select and copy manually.":"Sélectionnez et copiez manuellement.","Select and copy the address manually.":"Sélectionnez et copiez l’adresse manuellement.","QR code contains the address only. Enter the exact amount shown.":"Le QR contient uniquement l’adresse. Saisissez le montant exact affiché.","Reopen payment dialog":"Rouvrir le paiement","Unable to verify payment details":"Impossible de vérifier les détails","Unable to verify payment.":"Impossible de vérifier le paiement.","Use only the asset and network shown. Send the exact amount. Network fees must not reduce the received amount.":"Utilisez uniquement l’actif et le réseau indiqués. Envoyez le montant exact ; les frais ne doivent pas réduire le reçu.","Use only the asset and network shown above. Send the exact amount; network fees must not reduce the received amount.":"Utilisez uniquement l’actif et le réseau ci-dessus. Envoyez le montant exact ; les frais ne doivent pas réduire le reçu.","Waiting for payment":"En attente de paiement","Payment detected; waiting for confirmation…":"Paiement détecté ; confirmation attendue…","Payment reference:":"Référence du paiement :","Order total:":"Total de commande :","Order #":"Commande #","Expires at (MochiPay server time):":"Expire (heure du serveur MochiPay) :","Includes a small payment identification adjustment.":"Comprend un petit ajustement d’identification du paiement."},"de":{"Complete your payment":"Zahlung abschließen","Close payment dialog":"Zahlungsfenster schließen","Payment details":"Zahlungsdetails","Payment address QR code":"QR-Code der Zahlungsadresse","Copy Amount":"Betrag kopieren","Copy Address":"Adresse kopieren","Payment address":"Zahlungsadresse","Copied":"Kopiert","QR unavailable. Copy the address instead.":"QR nicht verfügbar. Adresse kopieren.","Network":"Netzwerk","Check payment status":"Zahlungsstatus prüfen","Closing the dialog keeps your payment. Do not pay twice.":"Schließen behält die Zahlung bei. Nicht doppelt zahlen.","Closing this dialog keeps your existing payment. Do not pay twice.":"Schließen behält die bestehende Zahlung bei. Nicht doppelt zahlen.","Connection failed. Your order is saved. Please check again.":"Verbindung fehlgeschlagen. Bestellung gespeichert; erneut prüfen.","Connection failed. Your order is kept; please check again.":"Verbindung fehlgeschlagen. Bestellung bleibt erhalten; erneut prüfen.","Do not send another payment. Contact the store to check this order.":"Keine weitere Zahlung senden. Shop zur Prüfung kontaktieren.","Do not send another payment. Contact the store to review this order.":"Keine weitere Zahlung senden. Shop zur Prüfung kontaktieren.","Loading payment details…":"Zahlungsdetails werden geladen…","Loading payment…":"Zahlung wird geladen…","Payment confirmed. Opening your order…":"Zahlung bestätigt. Bestellung wird geöffnet…","Payment requires review:":"Zahlung muss geprüft werden:","Please select and copy manually.":"Manuell auswählen und kopieren.","Select and copy the address manually.":"Adresse manuell auswählen und kopieren.","QR code contains the address only. Enter the exact amount shown.":"QR-Code enthält nur die Adresse. Exakten angezeigten Betrag eingeben.","Reopen payment dialog":"Zahlungsdialog erneut öffnen","Unable to verify payment details":"Zahlungsdetails nicht prüfbar","Unable to verify payment.":"Zahlung nicht prüfbar.","Use only the asset and network shown. Send the exact amount. Network fees must not reduce the received amount.":"Nur angezeigtes Asset und Netzwerk nutzen. Exakten Betrag senden; Gebühren dürfen den Eingang nicht verringern.","Use only the asset and network shown above. Send the exact amount; network fees must not reduce the received amount.":"Nur oben angezeigtes Asset und Netzwerk nutzen. Exakten Betrag senden; Gebühren dürfen Eingang nicht verringern.","Waiting for payment":"Warten auf Zahlung","Payment detected; waiting for confirmation…":"Zahlung erkannt; Bestätigung ausstehend…","Payment reference:":"Zahlungsreferenz:","Order total:":"Bestellsumme:","Order #":"Bestellung #","Expires at (MochiPay server time):":"Ablauf (MochiPay-Serverzeit):","Includes a small payment identification adjustment.":"Enthält eine kleine Anpassung zur Zahlungszuordnung."},"zh":{"Complete your payment":"完成付款","Close payment dialog":"关闭付款对话框","Payment details":"付款详情","Payment address QR code":"付款地址二维码","Copy Amount":"复制数量","Copy Address":"复制地址","Payment address":"付款地址","Copied":"已复制","QR unavailable. Copy the address instead.":"二维码不可用，请复制地址。","Network":"网络","Check payment status":"查询付款状态","Closing the dialog keeps your payment. Do not pay twice.":"关闭窗口会保留付款订单，请勿重复付款。","Closing this dialog keeps your existing payment. Do not pay twice.":"关闭窗口会保留现有付款订单，请勿重复付款。","Connection failed. Your order is saved. Please check again.":"连接失败，订单已保存，请重新查询。","Connection failed. Your order is kept; please check again.":"连接失败，订单已保留，请重新查询。","Do not send another payment. Contact the store to check this order.":"请勿再次付款，请联系商城核查订单。","Do not send another payment. Contact the store to review this order.":"请勿再次付款，请联系商城核查订单。","Loading payment details…":"正在加载付款详情…","Loading payment…":"正在加载付款…","Payment confirmed. Opening your order…":"付款已确认，正在打开订单…","Payment requires review:":"付款需要核查：","Please select and copy manually.":"请手动选中并复制。","Select and copy the address manually.":"请手动选中并复制地址。","QR code contains the address only. Enter the exact amount shown.":"二维码仅包含地址，请填写显示的精确付款数量。","Reopen payment dialog":"重新打开付款窗口","Unable to verify payment details":"无法核实付款详情","Unable to verify payment.":"无法核实付款。","Use only the asset and network shown. Send the exact amount. Network fees must not reduce the received amount.":"仅使用显示的币种和网络，发送精确数量。网络手续费不得扣减实际到账数量。","Use only the asset and network shown above. Send the exact amount; network fees must not reduce the received amount.":"仅使用上方显示的币种和网络，发送精确数量。网络手续费不得扣减实际到账数量。","Waiting for payment":"等待付款","Payment detected; waiting for confirmation…":"已检测到付款，等待确认…","Payment reference:":"付款编号：","Order total:":"订单总额：","Order #":"订单 #","Expires at (MochiPay server time):":"到期时间（MochiPay 服务器时间）：","Includes a small payment identification adjustment.":"包含少量用于识别订单的付款数量调整。"}},codes=['en','zh','es','pt-br','fr','de'],names=['English','中文','Español','Português (BR)','Français','Deutsch'];
function normalize(value){value=(value||'en').toLowerCase();if(value==='pt'||value==='pt_br')value='pt-br';if(value==='zh-cn'||value==='zh-hans')value='zh';return codes.indexOf(value)>=0?value:'en';}
function table(language){var source=catalogs[language]||{},out={};Object.keys(source).forEach(function(k){out[k.toLowerCase()]=source[k];});return out;}
function mount(root){
 if(!root||root.hasAttribute('data-mp-native-language'))return;
 root.setAttribute('data-mp-native-language','75');
 var language=normalize(new URLSearchParams(global.location.search).get('lang')),lookup=table(language),records=new WeakMap(),attributes=new WeakMap();
 var control=document.createElement('label');control.setAttribute('data-native-language-control','true');control.style.cssText='display:flex;justify-content:flex-end;max-width:100%;padding:4px 28px 8px 0';
 var select=document.createElement('select');select.setAttribute('aria-label','Language');select.style.cssText='font:inherit;font-size:14px;max-width:100%;color:inherit;background:transparent;border:1px solid #94a3b8;border-radius:4px;padding:5px 8px';
 codes.forEach(function(code,i){var option=document.createElement('option');option.value=code;option.textContent=names[i];select.appendChild(option);});select.value=language;control.appendChild(select);root.insertBefore(control,root.firstChild);
 function phrase(text){var key=text.trim().replace(/\s+/g,' ').toLowerCase();return Object.prototype.hasOwnProperty.call(lookup,key)?lookup[key]:null;}
 function t(text){
  if(language==='en')return text;
  var translated=phrase(text);if(translated!==null)return text.replace(/\S[\s\S]*\S|\S/,function(){return translated;});
  var prefixes=['Payment requires review:','Order total:','Network:','Expires at (MochiPay server time):','Payment reference:','Order #'];
  for(var i=0;i<prefixes.length;i++)if(text.indexOf(prefixes[i])===0){var label=phrase(prefixes[i]);if(label===null&&prefixes[i].slice(-1)===':'){label=phrase(prefixes[i].slice(0,-1));if(label!==null)label+=':';}
   if(label!==null){var rest=text.slice(prefixes[i].length),suffix='Includes a small payment identification adjustment.';if(rest.indexOf(suffix)>=0){var addition=phrase(suffix);if(addition!==null)rest=rest.replace(suffix,function(){return addition;});}return label+rest;}}
  return text;
 }
 function refresh(){
  // Disconnect while writing, so translation never triggers another polling or translation loop.
  observer.disconnect();root.lang=language==='zh'?'zh-CN':language==='pt-br'?'pt-BR':language;
  var walker=document.createTreeWalker(root,NodeFilter.SHOW_TEXT),node;
  while((node=walker.nextNode())){
   if(!node.parentElement||node.parentElement.closest('[data-native-language-control],pre,code,script,style,textarea,select'))continue;
   var previous=records.get(node),raw=previous&&node.nodeValue===previous.output?previous.raw:node.nodeValue,output=t(raw);
   records.set(node,{raw:raw,output:output});if(node.nodeValue!==output)node.nodeValue=output;
  }
  root.querySelectorAll('[aria-label],[title]').forEach(function(element){if(element.closest('[data-native-language-control]'))return;var previous=attributes.get(element)||{};
   ['aria-label','title'].forEach(function(name){if(!element.hasAttribute(name))return;var current=element.getAttribute(name),old=previous[name],raw=old&&current===old.output?old.raw:current,output=t(raw);previous[name]={raw:raw,output:output};if(current!==output)element.setAttribute(name,output);});attributes.set(element,previous);});
  observer.observe(root,{subtree:true,childList:true,characterData:true,attributes:true,attributeFilter:['aria-label','title']});
 }
 var observer=new MutationObserver(refresh);select.addEventListener('change',function(){language=normalize(select.value);lookup=table(language);refresh();});refresh();
}
global.MochiPayNativeLanguage={mount:mount,version:'75'};
})(window);

(function ($) {
    'use strict';
    var config = window.MochiPayOnsiteConfig;
    if (!config) { return; }
    var current, timer, pending = false, serial = 0, failures = 0, modal, lastFocus;
    function node(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text !== undefined) { n.textContent = text; }
        return n;
    }
    function build() {
        if (modal) { return; }
        modal = node('div', 'mp-overlay'); modal.hidden = true;
        var card = node('section', 'mp-dialog');
        card.setAttribute('role', 'dialog'); card.setAttribute('aria-modal', 'true');
        card.setAttribute('aria-labelledby', 'mp-heading'); card.tabIndex = -1;
        var close = node('button', 'mp-close', '×'); close.type = 'button';
        close.setAttribute('aria-label', 'Close payment dialog');
        close.addEventListener('click', hide); card.appendChild(close);
        card.appendChild(node('div', 'mp-brand', 'MOCHIPAY'));
        var heading = node('h2', '', 'Complete your payment'); heading.id = 'mp-heading'; card.appendChild(heading);
        var status = node('p', 'mp-status', 'Loading payment…');
        status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite'); card.appendChild(status);
        var details = node('div', 'mp-details'); details.hidden = true;
        details.appendChild(node('div', 'mp-pay-amount'));
        details.appendChild(node('p', 'mp-order-total'));
        details.appendChild(node('p', 'mp-network'));
        var qr = node('div', 'mp-qr'); qr.setAttribute('aria-label', 'Payment address QR code'); details.appendChild(qr);
        details.appendChild(node('p', 'mp-qr-caption', 'QR code contains the address only. Enter the exact amount shown.'));
        var amountBtn = node('button', 'mp-copy-amount', 'Copy amount'); amountBtn.type = 'button'; details.appendChild(amountBtn);
        details.appendChild(node('label', 'mp-address-label', 'Payment address'));
        var address = node('input', 'mp-address'); address.readOnly = true; address.setAttribute('aria-label', 'Payment address'); details.appendChild(address);
        var addressBtn = node('button', 'mp-copy-address', 'Copy address'); addressBtn.type = 'button'; details.appendChild(addressBtn);
        var copyNote = node('span', 'mp-copy-note'); copyNote.setAttribute('role', 'status'); details.appendChild(copyNote);
        details.appendChild(node('p', 'mp-warning', 'Use only the asset and network shown above. Send the exact amount; network fees must not reduce the received amount.'));
        details.appendChild(node('p', 'mp-expiry')); details.appendChild(node('p', 'mp-reference'));
        card.appendChild(details);
        var message = node('p', 'mp-error'); message.setAttribute('role', 'alert'); card.appendChild(message);
        var refresh = node('button', 'mp-refresh', 'Check payment status'); refresh.type = 'button';
        refresh.addEventListener('click', poll); card.appendChild(refresh);
        card.appendChild(node('p', 'mp-footnote', 'Closing this dialog keeps your existing payment. Do not pay twice.'));
        var scroll = node('div', 'mp-dialog-scroll'); scroll.setAttribute('role', 'region'); scroll.setAttribute('aria-label', 'Payment details'); scroll.tabIndex = 0; Array.prototype.slice.call(card.childNodes).forEach(function (child) { if (child !== close) { scroll.appendChild(child); } }); card.appendChild(scroll);
        modal.appendChild(card); document.body.appendChild(modal); window.MochiPayNativeLanguage.mount(card);
        addressBtn.addEventListener('click', function () { copy(address.value); });
        amountBtn.addEventListener('click', function () { copy(amountBtn.dataset.amount || ''); });
        document.addEventListener('keydown', function (e) {
            if (modal.hidden) { return; }
            if (e.key === 'Escape') { hide(); }
            if (e.key === 'Tab') {
                var buttons = Array.prototype.filter.call(card.querySelectorAll('button,input,a,select,[tabindex="0"]'), function (n) { return !n.disabled && n.getClientRects().length; });
                var first = buttons[0], last = buttons[buttons.length - 1];
                if (e.shiftKey && (document.activeElement === first || document.activeElement === card)) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && (document.activeElement === last || document.activeElement === card)) { e.preventDefault(); first.focus(); }
            }
        });
    }
    function el(cls) { return modal.querySelector('.' + cls); }
    function copy(text) {
        if (!text) { return; }
        function fallback() {
            var input = node('textarea'); input.value = text; input.style.position = 'fixed';
            modal.appendChild(input); input.select();
            var ok = false; try { ok = document.execCommand('copy'); } catch (e) { /* manual copy */ }
            input.remove(); el('mp-copy-note').textContent = ok ? 'Copied' : 'Select and copy the address manually.';
        }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () { el('mp-copy-note').textContent = 'Copied'; }, fallback);
        } else { fallback(); }
    }
    function parse(url) {
        try {
            var u = new URL(url, window.location.href);
            if (u.origin !== window.location.origin || !/^\d+$/.test(u.searchParams.get('mochipay_pay') || '') || !u.searchParams.get('key')) { return null; }
            return {orderId: Number(u.searchParams.get('mochipay_pay')), key: u.searchParams.get('key'), url: u.href};
        } catch (e) { return null; }
    }
    function open(value) {
        if (!value || !value.orderId || !value.key) { return; }
        clearTimeout(timer); serial++; pending = false; failures = 0; current = value; build();
        lastFocus = document.activeElement; modal.hidden = false; document.body.classList.add('mp-modal-open');
        el('mp-details').hidden = true; el('mp-error').textContent = ''; el('mp-status').textContent = 'Loading payment…';
        el('mp-dialog').focus();
        if (!document.querySelector('.mp-reopen')) {
            var reopen = node('button', 'button mp-reopen', 'Reopen payment dialog'); reopen.type = 'button';
            document.querySelector('form.checkout') ? document.querySelector('form.checkout').insertAdjacentElement('afterend', reopen) : document.body.appendChild(reopen);
        }
        // Move the address bar to the same order payment link. Refresh resumes this payment.
        if (value.url) { window.history.replaceState(null, '', value.url); }
        poll();
    }
    function hide() {
        modal.hidden = true; document.body.classList.remove('mp-modal-open'); clearTimeout(timer);
        serial++; pending = false;
        if (lastFocus && lastFocus.isConnected) { lastFocus.focus(); }
    }
    function render(data) {
        var status = data.status;
        if (status === 'PAID') {
            el('mp-details').hidden = true; el('mp-status').textContent = 'Payment confirmed. Opening your order…';
            var redirect = new URL(data.redirect, window.location.href);
            if (redirect.origin === window.location.origin) { window.location.assign(redirect.href); }
            return false;
        }
        var blocked = ['EXPIRED', 'CANCELLED', 'CANCELED', 'UNDERPAID', 'OVERPAID'].indexOf(status) >= 0;
        el('mp-details').hidden = blocked;
        el('mp-status').textContent = blocked ? 'Payment requires review: ' + status :
            status === 'CONFIRMING' ? 'Payment detected; waiting for confirmation…' : 'Waiting for payment';
        if (blocked) {
            el('mp-error').textContent = 'Do not send another payment. Contact the store to check this order.';
            return true; // Continue checking for a server-approved manual confirmation.
        }
        el('mp-pay-amount').textContent = data.payAmount + ' ' + data.asset;
        el('mp-order-total').textContent = 'Order total: ' + data.amount + ' ' + data.currency;
        el('mp-network').textContent = 'Network: ' + data.network;
        if (data.delta && data.delta !== '0') { el('mp-order-total').textContent += ' · Includes a small payment identification adjustment.'; }
        el('mp-address').value = data.address; el('mp-copy-amount').dataset.amount = data.payAmount;
        el('mp-expiry').textContent = 'Expires at (MochiPay server time): ' + data.expiresAt;
        el('mp-reference').textContent = 'Order #' + data.orderNumber + ' · ' + data.reference;
        var qr = el('mp-qr');
        if (qr.dataset.address !== data.address) {
            qr.textContent = ''; qr.dataset.address = data.address;
            try { new window.QRCode(qr, {text: data.address, width: 200, height: 200, correctLevel: window.QRCode.CorrectLevel.M}); }
            catch (e) { qr.dataset.address = ''; qr.textContent = 'QR unavailable. Copy the address instead.'; }
            qr.removeAttribute('title');
        }
        return true;
    }
    function poll() {
        if (!current || pending || !modal || modal.hidden) { return; }
        clearTimeout(timer); pending = true; var requestSerial = serial;
        el('mp-refresh').disabled = true;
        $.ajax({url: config.ajaxUrl, method: 'POST', timeout: 40000, dataType: 'json', data: {
            action: 'mochipay_payment', nonce: config.nonce, order_id: current.orderId, key: current.key
        }}).done(function (response) {
            if (serial !== requestSerial) { return; }
            if (!response.success) { throwError(response.data && response.data.message); return; }
            failures = 0; el('mp-error').textContent = '';
            if (render(response.data)) { timer = setTimeout(poll, 12000); }
        }).fail(function (xhr) {
            if (serial !== requestSerial) { return; }
            var error = xhr.responseJSON;
            throwError(error && error.data && error.data.message);
        }).always(function () {
            if (serial === requestSerial) { pending = false; el('mp-refresh').disabled = false; }
        });
    }
    function throwError(message) {
        failures++;
        el('mp-details').hidden = true;
        el('mp-status').textContent = 'Unable to verify payment details';
        el('mp-error').textContent = message || 'Connection failed. Your order is kept; please check again.';
        if (failures < 5) { timer = setTimeout(poll, Math.min(60000, failures * 12000)); }
    }
    // Official legacy checkout success event can prevent the default redirect.
    $('form.checkout').on('checkout_place_order_success.mochipay', function (event, result) {
        var launch = parse(result && result.redirect);
        if (!launch) { return undefined; }
        var checkout = $('form.checkout').removeClass('processing');
        if (typeof checkout.unblock === 'function') { checkout.unblock(); }
        $('form.checkout :submit').prop('disabled', true); // Order is now created; reopen rather than resubmit.
        open(launch); return false;
    });
    $(document).on('click', '.mp-reopen', function () { open(current || window.MochiPayOnsiteLaunch); });
    $(function () { if (window.MochiPayOnsiteLaunch) { open(window.MochiPayOnsiteLaunch); } });
})(jQuery);
