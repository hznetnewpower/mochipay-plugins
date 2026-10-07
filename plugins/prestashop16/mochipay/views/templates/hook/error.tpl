<div class="alert alert-danger">
  {foreach from=$errors item=error}<p>{$error|escape:'htmlall':'UTF-8'}</p>{/foreach}
  <p><a href="{$link->getPageLink('order', true, null, 'step=1')|escape:'htmlall':'UTF-8'}">{l s='Return to checkout' mod='mochipay'}</a></p>
</div>
