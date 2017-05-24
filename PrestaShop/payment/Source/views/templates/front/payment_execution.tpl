
{capture name=path}
	{l s='Pay by allPay Integration Payment' mod='allpay'}
{/capture}

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if !empty($allpay_warning)}
	<div class="box cheque-box">
		<p>{$allpay_warning}</p>
	</div>
	<p class="cart_navigation clearfix" id="cart_navigation">
		<a href="{$link->getPageLink('order', true, NULL, 'step=3')|escape:'html':'UTF-8'}" class="button-exclusive btn btn-default">
			<i class="icon-chevron-left"></i>{l s='Other payment methods' mod='allpay'}
		</a>
	</p>
{else}
	<form action="{$link->getModuleLink('allpay', 'payment', [], true)|escape:'html'}" method="post">
		<div class="box cheque-box">
			<p>
				<img src="{$this_path_allpay}images/allpay_payment_logo.png" alt="{l s='allPay' mod='allpay'}" width="148" height="52" style="float:left;" />
			</p>
			<p>
				{l s='The total amount of your order is ' mod='allpay'}
				<span id="amount" class="price">{displayPrice price=$total}</span>
				{if $use_taxes == 1}
					{l s='(tax incl.)' mod='allpay'}
				{/if}
			</p>
			<p>
			{if !empty($payment_methods)}
				{l s='Payment Method : ' mod='allpay'}
				<select name="payment_type">
					{foreach from=$payment_methods key=payment_name item=payment_description}
						<option value="{$payment_name}">{$payment_description}</option>
					{/foreach}
				</select>
			{else}
				{l s='No available payment methods, please contact with the administrator.' mod='allpay'}
			{/if}
			</p>
		</div>
		<p class="cart_navigation clearfix" id="cart_navigation">
			{if count($payment_methods) > 0}
				<button type="submit" class="button btn btn-default button-medium">
					<span>{l s='Checkout' mod='allpay'}<i class="icon-chevron-right right"></i></span>
				</button>
			{/if}
			
			<a href="{$link->getPageLink('order', true, NULL, 'step=3')|escape:'html':'UTF-8'}" class="button-exclusive btn btn-default">
				<i class="icon-chevron-left"></i>{l s='Other payment methods' mod='allpay'}
			</a>
		</p>
	</form>
{/if}
