<?php

class OpayPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;
    public $display_column_right = false;
    public $allpay_warning = '';
    
    # See FrontController::initContent()
    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;
        if (!$this->module->checkCurrency($cart))
        {
            Tools::redirect('index.php?controller=order');
        }
        
        # Get the available payment methods
        $allpay_payments = $this->module->getPaymentsDesc();
        $payment_methods = array();
        foreach ($allpay_payments as $payment_name => $payment_desc)
        {
            if (Configuration::get('opay_payment_' . strtolower($payment_name)) == 'on')
            {
                $payment_methods[$payment_name] = $payment_desc;
            }
        }
        
        # Check the product number in the cart
        $cart_product_number = $cart->nbProducts();
        if ($cart_product_number < 1)
        {
            $this->allpay_warning = $this->module->l('Your shopping cart is empty.', 'payment');
        }
        
        # Format the error message
        if (!empty($this->allpay_warning))
        {
            $this->allpay_warning = Tools::displayError($this->allpay_warning);
        }
        
        # Set PrestaShop Smarty parameters
        $this->context->smarty->assign(array(
            'total' => $cart->getOrderTotal(true, Cart::BOTH)
            , 'isoCode' => $this->context->language->iso_code
            , 'payment_methods' => $payment_methods
            , 'this_path_allpay' => $this->module->getPathUri()
            , 'this_path' => $this->module->getPathUri()
            ,    'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
            , 'allpay_warning' => $this->allpay_warning
        ));
        
        # Display the template
        $this->setTemplate('payment_execution.tpl');
    }
    
    public function postProcess()
    {
        # Validate the payment module
        $authorized = false;
        foreach (Module::getPaymentModules() as $module)
        {
            if ($module['name'] == 'opay')
            {
                $authorized = true;
                break;
            }
        }
        
        if (!$authorized)
        {
            $this->allpay_warning = $this->module->l('This payment module is not available.', 'payment');
        }
        else
        {
            $payment_type = Tools::getValue('payment_type');
            if ($payment_type)
            {
                # Check the cart info
                $cart = $this->context->cart;
                if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
                {
                    Tools::redirect(__PS_BASE_URI__.'order.php?step=1');
                }
                
                try
                {
                    # Validate the payment type
                    $chosen_payment_desc = $this->module->getPaymentDesc($payment_type);
                    if (empty($chosen_payment_desc))
                    {
                        throw new Exception($this->module->l('this payment method is not available.', 'payment'));
                    }
                    else
                    {
                        # Include the O'Pay integration class
                        $invoke_result = $this->module->invokeAllpayModule();
                        if (!$invoke_result)
                        {
                            throw new Exception($this->module->l('O\'Pay module is missing.', 'payment'));
                        }
                        else
                        {
                            # Get the customer object
                            $customer = new Customer($this->context->cart->id_customer);
                            if (!Validate::isLoadedObject($customer))
                            {
                                Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');
                            }
              
                            # Get the order id
                            $cart_id = (int)$cart->id;
                            
                            # Set O'Pay parameters
                            $aio = new AllInOne();
                            $aio->Send['MerchantTradeNo'] = '';
                            $aio->MerchantID = Configuration::get('opay_merchant_id');
                            if ($this->module->isTestMode($aio->MerchantID))
                            {
                                $service_url = 'https://payment-stage.allpay.com.tw/Cashier/AioCheckOut/V4';
                                $aio->Send['MerchantTradeNo'] = date('YmdHis');
                            } else {
                                $service_url = 'https://payment.allpay.com.tw/Cashier/AioCheckOut/V4';
                            }
                            $aio->HashKey = Configuration::get('opay_hash_key');
                            $aio->HashIV = Configuration::get('opay_hash_iv');
                            $aio->ServiceURL = $service_url;
                            $aio->EncryptType = EncryptType::ENC_SHA256;
                            $aio->Send['ReturnURL'] = $this->context->link->getModuleLink('opay','response', array());
                            $aio->Send['ClientBackURL'] = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . '/index.php?controller=order-confirmation&id_cart=' . $cart_id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key;
                            $aio->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
                            
                            # Get the currency object
                            $currency = $this->context->currency;
                            
                            # Set the product info
                            $order_total = $cart->getOrderTotal(true, Cart::BOTH);
                            $aio->Send['TotalAmount'] = $this->module->formatOrderTotal($order_total);
                            array_push(
                                $aio->Send['Items'],
                                array(
                                    'Name' => $this->module->l('A Package Of Online Goods', 'payment'),
                                    'Price' => $aio->Send['TotalAmount'],
                                    'Currency' => $currency->iso_code,
                                    'Quantity' => 1,
                                    'URL' => ''
                                )
                            );
                            
                            # Set the trade description
                            $aio->Send['TradeDesc'] = 'opay_module_prestashop_1.1.0629';
                            
                            # Get the chosen payment and installment
                            $type_pieces = explode('_', $payment_type);
                            $aio->Send['ChoosePayment'] = $type_pieces[0];
                            $choose_installment = 0;
                            if (isset($type_pieces[1])) {
                                $choose_installment = $type_pieces[1];
                            }
                            
                            # Set the extend information
                            switch ($aio->Send['ChoosePayment']) {
                                case PaymentMethod::Credit:
                                    # Do not support UnionPay
                                    $aio->SendExtend['UnionPay'] = false;
                                    
                                    # Credit installment parameters
                                    if (!empty($choose_installment)) {
                                        $aio->SendExtend['CreditInstallment'] = $choose_installment;
                                        $aio->SendExtend['InstallmentAmount'] = $aio->Send['TotalAmount'];
                                        $aio->SendExtend['Redeem'] = false;
                                    }
                                    break;
                                case PaymentMethod::WebATM:
                                    break;
                                case PaymentMethod::ATM:
                                    $aio->SendExtend['ExpireDate'] = 3;
                                    $aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
                                    break;
                                case PaymentMethod::CVS:
                                    $aio->SendExtend['Desc_1'] = '';
                                    $aio->SendExtend['Desc_2'] = '';
                                    $aio->SendExtend['Desc_3'] = '';
                                    $aio->SendExtend['Desc_4'] = '';
                                    $aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
                                    break;
                                case PaymentMethod::Tenpay:
                                    $aio->SendExtend['ExpireTime'] = date('Y/m/d H:i:s', strtotime('+3 days'));
                                    break;
                                case PaymentMethod::TopUpUsed:
                                    break;
                                default:
                                    throw new Exception($this->module->l('this payment method is not available.', 'payment'));
                                    break;
                            }
                            
                            # Create an order
                            $order_status_id = $this->module->getOrderStatusID('created');# Preparation in progress
                            $this->module->validateOrder($cart_id, $order_status_id, $order_total, $this->module->displayName, $chosen_payment_desc, array(), (int)$currency->id, false, $customer->secure_key);
                            
                            # Get the order id
                            $order = new Order($cart_id);
                            $order_id = Order::getOrderByCartId($cart_id);
                            $aio->Send['MerchantTradeNo'] .= (int)$order_id;
                            
                            # Get the redirect html
                            $aio->CheckOut();
                        }
                    }
                }
                catch(Exception $e)
                {
                    $this->allpay_warning = sprintf($this->module->l('Payment failure, %s', 'payment'), $e->getMessage());
                }
            }
        }
    }
}
