<?php

if (!defined('_PS_VERSION_'))
    exit;

class Opay extends PaymentModule
{
    # Custom variables: POST error
    private $postError = '';
    
    # Custom variables: allPay parameters
    private $allpayParams = array();
    
    # Custom variables: allPay log
    private $allpayLog = array();
    
    public function __construct()
    {
        # The value MUST be the name of the module's folder.
        $this->name = 'opay';
        
        # The type of the module, displayed in the modules list.
        $this->tab = 'payments_gateways';
        
        # The version number for the module, displayed in the modules list.
        $this->version = '1.1.0629';
        
        # The auther for the module, displayed in the modules list.
        $this->author = 'O\'Pay';
        
        # Enable BootStrap
        $this->bootstrap = true;
        
        # Calling the parent constuctor method must be done after the creation of the $this->name variable and before any use of the $this->l() translation method.
        parent::__construct();
        
        # A name for the module, which will be displayed in the back-office's modules list.
        $this->displayName = $this->l('O\'Pay Integration Payment');
        
        # A description for the module, which will be displayed in the back-office's modules
        $this->description = 'https://www.allpay.com.tw/';
        
        # A message, asking the administrator if he really does want to uninstall the module. To be used in the installation code.
        $this->confirmUninstall = $this->l('Do you want to uninstall O\'Pay payment module?');
        
        # Custom variables: allPay parameters
        $this->allpayParams = array(
            'opay_merchant_id'
            , 'opay_hash_key'
            , 'opay_hash_iv'
            , 'opay_payment_credit'
            , 'opay_payment_credit_03'
            , 'opay_payment_credit_06'
            , 'opay_payment_credit_12'
            , 'opay_payment_credit_18'
            , 'opay_payment_credit_24'
            , 'opay_payment_webatm'
            , 'opay_payment_atm'
            , 'opay_payment_cvs'
            , 'opay_payment_tenpay'
            , 'opay_payment_topupused'
        );
        
        # Custom variables: O'Pay log
        $this->allpayLog = _PS_MODULE_DIR_ . $this->module->name . '/log/return_url.log';
        if (!file_exists(dirname($this->allpayLog)))
        {
            mkdir(dirname($this->allpayLog));
        }
    }
    
    # Perform checks and actions during the module's installation process
    public function install()
    {
        # Register PrestaShop hooks
        if (!parent::install() OR !$this->registerHook('payment') or !$this->registerHook('paymentReturn'))
        {
            return false;
        }
        else
        {
            return true;
        }
    }
        
    public function uninstall()
    {
        if (!parent::uninstall() or !$this->cleanAllpayConfig())
        {
            return false;
        }
        else
        {
            return true;
        }
    }
        
    public function getContent()
    {
        $html_content = '';
        
        # Update the settings
        if (Tools::isSubmit('opay_submit'))
        {
            # Validate the POST parameters
            $this->postValidation();
            
            if (!empty($this->postError))
            {
                # Display the POST error
                $html_content .= $this->displayError($this->postError);
            }
            else
            {
                $html_content .= $this->postProcess();
            }
        }
        
        # Display the setting form
        $html_content .= $this->displayForm();
        
        return $html_content;
    }
    
    public function hookPayment($params)
    {
        if (!$this->active)
            return;
        if (!$this->checkCurrency($params['cart']))
            return;
        
        $this->smarty->assign(
            array(
                'opay_img_path' => $this->_path . 'images/'
                , 'this_path' => $this->_path
                , 'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
            )
        );
        
        return $this->display(__FILE__, 'payment.tpl');
    }
  
    public function hookPaymentReturn($params)
    {
        if (!$this->active)
        {
          return;
        }

        Tools::redirect('index.php?controller=history');
    }
    
    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module))
        {
            foreach ($currencies_module as $currency_module)
            {
                if ($currency_order->id == $currency_module['id_currency'])
                {
                    return true;
                }
            }
        }
            
        return false;
    }
    
    
    # Public custom function
    public function getPaymentsDesc()
    {
        $payments_desc = array(
            'Credit' => $this->l('Credit')
            , 'Credit_03' => $this->l('Credit Card(03 Installments)')
            , 'Credit_06' => $this->l('Credit Card(06 Installments)')
            , 'Credit_12' => $this->l('Credit Card(12 Installments)')
            , 'Credit_18' => $this->l('Credit Card(18 Installments)')
            , 'Credit_24' => $this->l('Credit Card(24 Installments)')
            , 'WebATM' => $this->l('WebATM')
            , 'ATM' => $this->l('ATM')
            , 'CVS' => $this->l('CVS')
            , 'Tenpay' => $this->l('Tenpay')
            , 'TopUpUsed' => $this->l('TopUpUsed')
        );
        
        return $payments_desc;
    }
    
    public function getPaymentDesc($payment_name)
    {
        $payments_desc = $this->getPaymentsDesc();
        
        if (!isset($payments_desc[$payment_name]))
        {
            return '';
        }
        else
        {
            return $payments_desc[$payment_name];
        }
    }
    
    public function isTestMode($allpay_merchant_id)
    {
        if ($allpay_merchant_id == '2000132' or $allpay_merchant_id == '2000214') {
            return true;
        } else {
            return false;
        }
    }
    
    public function invokeAllpayModule()
    {
        if (!class_exists('AllInOne', false))
        {
            if (!include(_PS_MODULE_DIR_ . $this->name . '/lib/AllPay.Payment.Integration.php'))
            {
                return false;
            }
        }
        
        return true;
    }
    
    public function formatOrderTotal($order_total)
    {
        return intval(round($order_total));
    }
    
    public function getCartOrderID($merchant_trade_no, $allpay_merchant_id)
    {
        $cart_order_id = $merchant_trade_no;
        if ($this->isTestMode($allpay_merchant_id))
        {
            $cart_order_id = substr($merchant_trade_no, 14);
        }
        
        return $cart_order_id;
    }
    
    public function getOrderStatusID($status_name)
    {
        $order_status = array(
            'created' => 1
            , 'succeeded' => 2
            , 'failed' => 8
        );
        
        return $order_status[$status_name];
    }
    
    public function setOrderComments($order_id, $comments)
    {
        # Set the order comments
        $message = new Message();
        $message->message = $comments;
        $message->id_order = intval($order_id);
        $message->private = 1;
        $message->add();
    }
    
    public function updateOrderStatus($order_id, $status_id, $send_mail = false)
    {
        # Update the order status
        $order_history = new OrderHistory();
        $order_history->id_order = (int)$order_id;
        $order_history->changeIdOrderState((int)$status_id, (int)$order_id);
        
        # Send a mail
        if ($send_mail)
        {
            $order_history->addWithemail();
        }
    }
    
    public function logAllpayMessage($message, $is_append)
    {
        if (!$is_append)
        {
            return file_put_contents($this->allpayLog, date('Y-m-d H:i:s') . ' - ' . $message . "\n", LOCK_EX);
        }
        else
        {
            return file_put_contents($this->allpayLog, date('Y-m-d H:i:s') . ' - ' . $message . "\n", FILE_APPEND | LOCK_EX);
        }
    }
    
    # Private custom function
    private function postValidation()
    {
        $required_fields = array(
            'opay_merchant_id' => $this->l('Merchant ID')
            , 'opay_hash_key' => $this->l('Hash Key')
            , 'opay_hash_iv' => $this->l('Hash IV')
        );
        
        foreach ($required_fields as $field_name => $field_desc)
        {
            $tmp_field_value = Tools::getValue($field_name);
            if (empty($tmp_field_value))
            {
                $this->postError = $field_desc . $this->l(' is required');
                return;
            }
        }
    }
    
    private function displayForm()
    {
        # Set the payment methods options
        $payment_methods = array();
        $payments_desc = $this->getPaymentsDesc();
        foreach ($payments_desc as $payment_name => $payment_desc)
        {
            array_push($payment_methods, array('id_option' => strtolower($payment_name), 'name' => $payment_desc));
        }
        
        # Set the configurations for generating a setting form
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => ''
                , 'image' => '../modules/opay/images/opay_setting_logo.png'
            )
            , 'input' => array(
                array(
                    'type' => 'text'
                    , 'label' => $this->l('Merchant ID')
                    , 'name' => 'opay_merchant_id'
                    , 'required' => true
                )
                , array(
                    'type' => 'text'
                    , 'label' => $this->l('Hash Key')
                    , 'name' => 'opay_hash_key'
                    , 'required' => true
                )
                , array(
                    'type' => 'text'
                    , 'label' => $this->l('Hash IV')
                    , 'name' => 'opay_hash_iv'
                    , 'required' => true
                )
                , array(
                    'type' => 'checkbox'
                    , 'label' => $this->l('Payment Methods')
                    , 'name' => 'opay_payment'
                    , 'values'  => array(
                        'query' => $payment_methods
                        , 'name'  => 'name'
                        , 'id'    => 'id_option'
                    )
                )
            )
            , 'submit' => array(
                'name' => 'opay_submit'
                , 'title' => $this->l('Save')
                , 'class' => 'button'
            )
        );
        
        $helper = new HelperForm();
        
        # Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        
        # Get the default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        
        # Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        
        # Default config
        $default = array(
            'opay_merchant_id' => '2000132',
            'opay_hash_key' => '5294y06JbISpM5x9',
            'opay_hash_iv' => 'v77hoKGq4kWxNNIS',
        );
        foreach ($default as $param_name => $param_value)
        {
            $helper->fields_value[$param_name] = $param_value;
        }

        # Load the current settings
        foreach ($this->allpayParams as $param_name)
        {
            $param_value = Configuration::get($param_name);
            if ($param_value != '') {
                $helper->fields_value[$param_name] = $param_value;
            }
        }
     
        return $helper->generateForm($fields_form);
    }
    
    private function postProcess()
    {
        # Update allPay parameters
        foreach ($this->allpayParams as $param_name)
        {
            if (!Configuration::updateValue($param_name, Tools::getValue($param_name)))
            {
                return $this->displayError($param_name . ' ' . $this->l('updated failed'));
            }
        }
        
        return $this->displayConfirmation($this->l('Settings updated'));
    }

    private function cleanAllpayConfig()
    {
        foreach ($this->allpayParams as $param_name)
        {
            if (!Configuration::deleteByName($param_name))
            {
                return false;
            }
        }
        
        return true;
    }

}
