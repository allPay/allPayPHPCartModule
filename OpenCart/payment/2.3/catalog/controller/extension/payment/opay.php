<?php

class ControllerExtensionPaymentOpay extends Controller {
    private $prefix = 'opay_';
    private $model_name = 'opay';
    private $model_path = 'extension/payment/opay';
    public function index() {
        // Set the checkout form action
        $data[$this->prefix . 'action'] = $this->url->link($this->model_path . '/redirect', '', true);

        // Get the translations
        $this->load->language($this->model_path);
        $translation_names = array(
            $this->prefix . 'text_title',
            $this->prefix . 'text_payment_methods',
            $this->prefix . 'text_checkout_button',
        );
        foreach ($translation_names as $name) {
            $data[$name] = $this->language->get($name);
        }
        
        // Get the translation of payment methods
        $payment_methods = $this->config->get($this->prefix . 'payment_methods');
        foreach ($payment_methods as $payment_type => $value) {
            $data['payment_methods'][$payment_type] = $this->language->get($this->prefix . 'text_' . $value);
        }
        
        // Get the template
        $config_template = $this->config->get('config_template');
        if (file_exists(DIR_TEMPLATE . $config_template)) {
            $payment_template = $config_template;
        } else {
            $payment_template = 'default';
        }
        $payment_template .= '/extension/payment/' . $this->model_name . '.tpl';
        
        return $this->load->view($payment_template, $data);
    }

    public function redirect() {
        try {
            // Load translation
            $this->load->language($this->model_path);

            // Load model
            $this->load->model($this->model_path);
            $this->model_extension_payment_opay->loadLibrary();
            $payment_methods = $this->config->get($this->prefix . 'payment_methods');
            $payment_type = $this->request->post[$this->prefix . 'choose_payment'];
            $helper = $this->model_extension_payment_opay->getHelper();

            // Validate choose payment
            if (!isset($payment_methods[$payment_type])) {
                throw new Exception($this->language->get($this->prefix . 'error_invalid_payment'));
            }

            // Validate the order id
            if (isset($this->session->data['order_id']) === false) {
                throw new Exception($this->language->get($this->prefix . 'error_order_id_miss'));
            }

            // Get the order info
            $order_id = $this->session->data['order_id'];
            $this->load->model('checkout/order');
            $order = $this->model_checkout_order->getOrder($order_id);
            $order_total = $order['total'];
            
            // Update order status and comments
            $comment = $this->language->get($this->prefix . 'text_' . $payment_methods[$payment_type]);
            $status_id = $this->config->get($this->prefix . 'create_status');
            $this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment, false, false);
        
            // Clear the cart
            $this->cart->clear();

            // Add to activity log
            $this->load->model('account/activity');
            if ($this->customer->isLogged()) {
                $activity_data = array(
                    'customer_id' => $this->customer->getId(),
                    'name'        => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
                    'order_id'    => $order_id
                );
                $this->model_account_activity->addActivity('order_account', $activity_data);
            } else {
                $guest = $this->session->data['guest'];
                $activity_data = array(
                    'name'     => $guest['firstname'] . ' ' . $guest['lastname'],
                    'order_id' => $order_id
                );
                $this->model_account_activity->addActivity('order_guest', $activity_data);
            }

            // Clean the session
            $session_list = array(
                'shipping_method',
                'shipping_methods',
                'payment_method',
                'payment_methods',
                'guest',
                'comment',
                'order_id',
                'coupon',
                'reward',
                'voucher',
                'vouchers',
                'totals',
            );
            foreach ($session_list as $name) {
                unset($this->session->data[$name]);
            }

            // Checkout
            $helper_data = array(
                'choosePayment' => $payment_type,
                'hashKey' => $this->config->get($this->prefix . 'hash_key'),
                'hashIv' => $this->config->get($this->prefix . 'hash_iv'),
                'returnUrl' => $this->url->link($this->model_path . '/response', '', true),
                'clientBackUrl' =>$this->url->link('account/order/info', 'order_id=' . $order_id, true),
                'orderId' => $order_id,
                'total' => $order_total,
                'itemName' => $this->language->get($this->prefix . 'text_item_name'),
                'version' => $this->prefix . 'module_opencart_1.0.0710',
            );
            $helper->checkout($helper_data);
        } catch (Exception $e) {
            // Process the exception
            $this->session->data['error'] = $e->getMessage();
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
    }

    public function response() {
        // Load the model and translation
        $this->load->language($this->model_path);
        $this->load->model($this->model_path);
        $this->load->model('checkout/order');
        $this->model_extension_payment_opay->loadLibrary();
        $helper = $this->model_extension_payment_opay->getHelper();
        
        // Set the default result message
        $result_message = '1|OK';
        $order_id = null;
        $order = null;
        try {
            // Get valid feedback
            $helper_data = array(
                'hashKey' => $this->config->get($this->prefix . 'hash_key'),
                'hashIv' => $this->config->get($this->prefix . 'hash_iv'),
            );
            $feedback = $helper->getValidFeedback($helper_data);
            unset($helper_data);

            $order_id = $helper->getOrderId($feedback['MerchantTradeNo']);

            // Get the cart order info
            $order = $this->model_checkout_order->getOrder($order_id);
            $order_status_id = $order['order_status_id'];
            $create_status_id = $this->config->get($this->prefix . 'create_status');
            $order_total = $order['total'];
            
            // Check the amounts
            if (!$helper->validAmount($feedback['TradeAmt'], $order_total)) {
                throw new Exception($helper->getAmountError($order_id));
            }

            // Get the response status
            $helper_data = array(
                'validStatus' => ($helper->toInt($order_status_id) === $helper->toInt($create_status_id)),
                'orderId' => $order_id,
            );
            $response_status = $helper->getResponseStatus($feedback, $helper_data);
            unset($helper_data);

            // Update the order status
            switch($response_status) {
                // Paid
                case 1:
                    $status_id = $this->config->get($this->prefix . 'success_status');
                    $pattern = $this->language->get($this->prefix . 'text_payment_result_comment');
                    $comment = $helper->getPaymentSuccessComment($pattern, $feedback);
                    $this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment, true);
                    unset($status_id, $pattern, $comment);

                    // Check E-Invoice model
                    $opay_invoice_status = $this->config->get('opayinvoice_status');
                    $ecpay_invoice_status = $this->config->get('ecpayinvoice_status');

                    // Get E-Invoice model name
                    $invoice_prefix = '';
                    if ($opay_invoice_status === '1' and is_null($ecpay_invoice_status) === true) {
                        $invoice_prefix = 'opay';
                    }
                    if ($ecpay_invoice_status === '1' and is_null($opay_invoice_status) === true) {
                        $invoice_prefix = 'ecpay';
                    }
                    
                    // E-Invoice auto issuel
                    if ($invoice_prefix !== '') {
                        $invoice_model_name = 'model_extension_payment_' . $invoice_prefix . 'invoice';
                        $this->load->model('extension/payment/' . $invoice_prefix . 'invoice');
                        $invoice_autoissue = $this->config->get($invoice_prefix . 'invoice_autoissue');
                        $valid_invoice_sdk = $this->$invoice_model_name->check_invoice_sdk();
                        if($invoice_autoissue === '1' and $valid_invoice_sdk != false) {    
                            $this->$invoice_model_name->createInvoiceNo($order_id, $valid_invoice_sdk);
                        }
                    }
                    break;
                // ATM get code
                case 2:
                    $status_id = $order_status_id;
                    $pattern = $this->language->get($this->prefix . 'text_atm_comment');
                    $comment = $helper->getObtainingCodeComment($pattern, $feedback);
                    $this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment);
                    unset($status_id, $pattern, $comment);
                    break;
                // Barcode/CVS get code
                case 3:
                    $status_id = $order_status_id;
                    $pattern = $this->language->get($this->prefix . 'text_barcode_comment');
                    $comment = $helper->getObtainingCodeComment($pattern, $feedback);
                    $this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment);
                    unset($status_id, $pattern, $comment);
                    break;
                default:
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
            if (!is_null($order_id)) {
                $status_id = $this->config->get($this->prefix . 'failed_status');
                $pattern = $this->language->get($this->prefix . 'text_failure_comment');
                $comment = $helper->getFailedComment($pattern, $error);
                $this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment);
                unset($status_id, $pattern, $comment);
            }
            
            // Set the failure result
            $result_message = '0|' . $error;
        }
        
        echo $result_message;
        exit;
    }
}
