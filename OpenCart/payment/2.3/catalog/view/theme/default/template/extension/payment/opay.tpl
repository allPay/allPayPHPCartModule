<form class="form-horizontal" action="<?php echo $opay_action; ?>" method="POST" id="opay_redirect_form">
	<fieldset>
		<legend><?php echo $opay_text_title ?></legend>
		<div class="form-group">
			<label class="col-sm-2 control-label">
				<?php echo $opay_text_payment_methods; ?>
			</label>
			<div class="col-sm-4">
				<select name="opay_choose_payment" class="form-control">
					<?php foreach ($payment_methods as $payment_type => $payment_desc) { ?>
					<option value="<?php echo $payment_type; ?>"><?php echo $payment_desc; ?></option>
					<?php } ?>
				</select>
			</div>
		</div>
		<div class="buttons">
			<div class="pull-right">
				<input type="button" value="<?php echo $opay_text_checkout_button; ?>" id="opay_checkout_button" class="btn btn-primary" onclick="document.getElementById('opay_redirect_form').submit();"/>
			</div>
		</div>
	</fieldset>
</form>
