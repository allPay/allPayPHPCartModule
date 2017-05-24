<form class="form-horizontal" action="<?php echo $allpay_action; ?>" method="POST" id="allpay_redirect_form">
	<fieldset>
		<legend><?php echo $allpay_text_title ?></legend>
		<div class="form-group">
			<label class="col-sm-2 control-label">
				<?php echo $allpay_text_payment_methods; ?>
			</label>
			<div class="col-sm-2">
				<select name="allpay_choose_payment" class="form-control">
					<?php foreach ($payment_methods as $payment_type => $payment_desc) { ?>
					<option value="<?php echo $payment_type; ?>"><?php echo $payment_desc; ?></option>
					<?php } ?>
				</select>
			</div>
		</div>
		<div class="buttons">
			<div class="pull-right">
				<input type="button" value="<?php echo $allpay_text_checkout_button; ?>" id="allpay_checkout_button" class="btn btn-primary" onclick="document.getElementById('allpay_redirect_form').submit();"/>
			</div>
		</div>
	</fieldset>
</form>
