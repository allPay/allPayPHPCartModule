
{capture name=path}
    {l s='Pay by O\'Pay Integration Payment' mod='opay'}
{/capture}

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if !empty($allpay_warning)}
    <div class="box cheque-box">
        <p>{$allpay_warning}</p>
    </div>
    <p class="cart_navigation clearfix" id="cart_navigation">
        <a href="{$link->getPageLink('order', true, NULL, 'step=3')|escape:'html':'UTF-8'}" class="button-exclusive btn btn-default">
            <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='opay'}
        </a>
    </p>
{else}
    <form action="{$link->getModuleLink('opay', 'payment', [], true)|escape:'html'}" method="post">
        <div class="box cheque-box">
            <img src="{$this_path_allpay}images/opay_payment_logo_02.png" alt="{l s='O\'Pay' mod='opay'}" width="100" height="60" style="float:left; margin-top:-10px; margin-right:10px;" />
            <p>
                {l s='The total amount of your order is ' mod='opay'}
                <span id="amount" class="price">{displayPrice price=$total}</span>
                {if $use_taxes == 1}
                    {l s='(tax incl.)' mod='opay'}
                {/if}
            </p>
            <p>
            {if !empty($payment_methods)}
                {l s='Payment Method : ' mod='opay'}
                <select name="payment_type">
                    {foreach from=$payment_methods key=payment_name item=payment_description}
                        <option value="{$payment_name}">{$payment_description}</option>
                    {/foreach}
                </select>
            {else}
                {l s='No available payment methods, please contact with the administrator.' mod='opay'}
            {/if}
            </p>
        </div>
        <p class="cart_navigation clearfix" id="cart_navigation">
            {if count($payment_methods) > 0}
                <button type="submit" class="button btn btn-default button-medium">
                    <span>{l s='Checkout' mod='opay'}<i class="icon-chevron-right right"></i></span>
                </button>
            {/if}
            
            <a href="{$link->getPageLink('order', true, NULL, 'step=3')|escape:'html':'UTF-8'}" class="button-exclusive btn btn-default">
                <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='opay'}
            </a>
        </p>
    </form>
{/if}
