<form method="post" action="{$mochipay_action|escape:'htmlall':'UTF-8'}"><div class="mochipay-payment-choice">
  <p>{l s='Choose the cryptocurrency and network you want to pay with.' mod='mochipay'}</p>
  <label for="mochipay_payment_method">{l s='Payment currency and network' mod='mochipay'}</label>
  <select name="mochipay_payment_method" id="mochipay_payment_method" required>
    {foreach from=$mochipay_methods key=method item=label}
      <option value="{$method|escape:'htmlall':'UTF-8'}">{$label|escape:'htmlall':'UTF-8'}</option>
    {/foreach}
  </select>
</div>
<button type="submit" class="button btn btn-default">Pay with MochiPay</button></form>
