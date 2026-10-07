<div class="form-group">
  <p><?php echo $text_description; ?></p>
  <label for="mochipay-payment-method"><?php echo $text_payment_method; ?></label>
  <select id="mochipay-payment-method" class="form-control"><?php foreach ($methods as $code => $label) { ?><option value="<?php echo $code; ?>"><?php echo $label; ?></option><?php } ?></select>
</div>
<div class="buttons"><div class="pull-right"><button type="button" id="button-confirm-mochipay" class="btn btn-primary">Confirm Order</button></div></div>
<script type="text/javascript">
$('#button-confirm-mochipay').on('click', function () {
  $.ajax({url: '<?php echo $action; ?>', type: 'post', dataType: 'json', data: {mochipay_payment_method: $('#mochipay-payment-method').val()}, beforeSend: function(){ $('#button-confirm-mochipay').prop('disabled', true); }, complete: function(){ $('#button-confirm-mochipay').prop('disabled', false); }, success: function(json){ if (json.error) alert(json.error); if (json.redirect) location = json.redirect; }, error: function(xhr){ alert(xhr.responseText || 'MochiPay request failed.'); }});
});
</script>
