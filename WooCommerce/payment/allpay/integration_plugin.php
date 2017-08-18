<?php
	/**
	 * @copyright  Copyright © 2017 O'Pay Electronic Payment Co., Ltd.(https://www.allpay.com.tw)
	 * @version 1.1.0801
	 *
	 * Plugin Name: allPay Payment
	 * Plugin URI: https://www.allpay.com.tw/
	 * Description: allPay Integration Payment Gateway for WooCommerce
	 * Version: 1.1.0801
	 * Author: O'Pay Electronic Payment Co., Ltd.
	 * Author URI: https://www.allpay.com.tw
	 */

	add_action('plugins_loaded', 'allpay_integration_plugin_init', 0);
	
	function allpay_integration_plugin_init() {
    	# Make sure WooCommerce is setted.
	    if (!class_exists('WC_Payment_Gateway')) {
	        return;
	    }

	    class WC_Gateway_Allpay extends WC_Payment_Gateway {
			var $allpay_test_mode;
			var $allpay_merchant_id;
			var $allpay_hash_key;
			var $allpay_hash_iv;
			var $allpay_choose_payment;
			var $allpay_payment_methods;
			var $allpay_domain;
			
			public function __construct() {
				# Load the translation
				$this->allpay_domain = 'allpay';
				load_plugin_textdomain($this->allpay_domain, false, '/allpay/translation');
				
				# Initialize construct properties
				$this->id = 'allpay';
				
				# Title of the payment method shown on the admin page
				$this->method_title = $this->tran('allPay');
			
				# If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image
				$this->icon = apply_filters('woocommerce_allpay_icon', plugins_url('images/icon.png', __FILE__));
				
				# Bool. Can be set to true if you want payment fields to show on the checkout (if doing a direct integration)
				$this->has_fields = true;
				
				# Load the form fields
				$this->init_form_fields();
				
				# Load the administrator settings
				$this->init_settings();
				$this->title = $this->get_option('title');
				$this->description = $this->get_option('description');
				$this->allpay_test_mode = $this->get_option('allpay_test_mode');
				$this->allpay_merchant_id = $this->get_option('allpay_merchant_id');
				$this->allpay_hash_key = $this->get_option('allpay_hash_key');
				$this->allpay_hash_iv = $this->get_option('allpay_hash_iv');
				$this->allpay_payment_methods = $this->get_option('allpay_payment_methods');
				
				# Register a action to save administrator settings
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
				
				# Register a action to redirect to allPay payment center
				add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
				
				# Register a action to process the callback
				add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'receive_response'));
			}
			
			/**
			 * Initialise Gateway Settings Form Fields
			 */
			public function init_form_fields () {
				$this->form_fields = array(
					'enabled' => array(
						'title' => $this->tran('Enable/Disable'),
						'type' => 'checkbox',
						'label' => $this->tran('Enable'),
						'default' => 'no'
					),
					'title' => array(
						'title' => $this->tran('Title'),
						'type' => 'text',
						'description' => $this->tran('This controls the title which the user sees during checkout.'),
						'default' => $this->tran('allPay')
					),
					'description' => array(
						'title' => $this->tran('Description'),
						'type' => 'textarea',
						'description' => $this->tran('This controls the description which the user sees during checkout.')
					),
					'allpay_test_mode' => array(
						'title' => $this->tran('Test Mode'),
						'label' => $this->tran('Enable'),
						'type' => 'checkbox',
						'description' => $this->tran('Test order will add date as prefix.'),
						'default' => 'no'
					),
					'allpay_merchant_id' => array(
						'title' => $this->tran('Merchant ID'),
						'type' => 'text',
						'default' => '2000132'
					),
					'allpay_hash_key' => array(
						'title' => $this->tran('Hash Key'),
						'type' => 'text',
						'default' => '5294y06JbISpM5x9'
					),
					'allpay_hash_iv' => array(
						'title' => $this->tran('Hash IV'),
						'type' => 'text',
						'default' => 'v77hoKGq4kWxNNIS'
					),
					'allpay_payment_methods' => array(
						'title' => $this->tran('Payment Method'),
						'type' => 'multiselect',
						'description' => $this->tran('Press CTRL and the right button on the mouse to select multi payments.'),
						'options' => array(
							'Credit' => $this->get_payment_desc('Credit'),
							'Credit_3' => $this->get_payment_desc('Credit_3'),
							'Credit_6' => $this->get_payment_desc('Credit_6'),
							'Credit_12' => $this->get_payment_desc('Credit_12'),
							'Credit_18' => $this->get_payment_desc('Credit_18'),
							'Credit_24' => $this->get_payment_desc('Credit_24'),
							'WebATM' => $this->get_payment_desc('WebATM'),
							'ATM' => $this->get_payment_desc('ATM'),
							'CVS' => $this->get_payment_desc('CVS'),
							'Alipay' => $this->get_payment_desc('Alipay'),
							'Tenpay' => $this->get_payment_desc('Tenpay'),
							'TopUpUsed' => $this->get_payment_desc('TopUpUsed')
						)
					)
				);
			}
			
			/**
			 * Set the admin title and description
			 */
			public function admin_options() {
				echo $this->add_next_line('  <h3>' . $this->tran('allPay Integration Payments') . '</h3>');
				echo $this->add_next_line('  <p>' . $this->tran('allPay is the most popular payment gateway for online shopping in Taiwan') . '</p>');
				echo $this->add_next_line('  <table class="form-table">');
				
				# Generate the HTML For the settings form.
				$this->generate_settings_html();
				echo $this->add_next_line('  </table>');
			}
			
			/**
			 * Display the form when chooses allPay payment
			 */
			public function payment_fields() {
				if (!empty($this->description)) {
					echo $this->add_next_line($this->description . '<br /><br />');
				}
				echo $this->tran('Payment Method') . ' : ';
				echo $this->add_next_line('<select name="allpay_choose_payment">');
				foreach ($this->allpay_payment_methods as $payment_method) {
					echo $this->add_next_line('  <option value="' . $payment_method . '">');
					echo $this->add_next_line('    ' . $this->get_payment_desc($payment_method));
					echo $this->add_next_line('  </option>');
				}
				echo $this->add_next_line('</select>');
			}
			
			/**
			 * Check the payment method and the chosen payment
			 */
			public function validate_fields() {
				$choose_payment = $_POST['allpay_choose_payment'];
				$payment_desc = $this->get_payment_desc($choose_payment);
				if ($_POST['payment_method'] == $this->id && !empty($payment_desc)) {
					$this->allpay_choose_payment = $choose_payment;
					return true;
				} else {
					$this->allPay_add_error($this->tran('Invalid payment method.'));
					return false;
				}
			}
			
			/**
			 * Process the payment
			 */
			public function process_payment($order_id) {
				# Update order status
				$order = new WC_Order($order_id);
				$order->update_status('pending', $this->tran('Awaiting allPay payment'));
				
				# Set the allPay payment type to the order note
				$order->add_order_note($this->allpay_choose_payment, true);
				
				return array(
					'result' => 'success',
					'redirect' => $order->get_checkout_payment_url(true)
				);
			}
			
			/**
			 * Redirect to allPay
			 */
			public function receipt_page($order_id) {
				# Clean the cart
				global $woocommerce;
				$woocommerce->cart->empty_cart();
				$order = new WC_Order($order_id);
				
				try {
					$this->invoke_allpay_module();
					$aio = new AllInOne();
					$aio->Send['MerchantTradeNo'] = '';
					$service_url = '';
					if ($this->allpay_test_mode == 'yes') {
						$service_url = 'https://payment-stage.allpay.com.tw/Cashier/AioCheckOut';
						$aio->Send['MerchantTradeNo'] = date('YmdHis');
					} else {
						$service_url = 'https://payment.allpay.com.tw/Cashier/AioCheckOut';
					}
					$aio->MerchantID = $this->allpay_merchant_id;
					$aio->HashKey = $this->allpay_hash_key;
					$aio->HashIV = $this->allpay_hash_iv;
					$aio->ServiceURL = $service_url;
					$aio->Send['ReturnURL'] = add_query_arg('wc-api', 'WC_Gateway_Allpay', home_url('/'));
					$aio->Send['ClientBackURL'] = home_url('?page_id=' . get_option('woocommerce_myaccount_page_id') . '&view-order=' . $order->id);;
					$aio->Send['MerchantTradeNo'] .= $order->id;
					$aio->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
					
					# Set the product info
					$aio->Send['TotalAmount'] = $order->get_total();
					array_push(
						$aio->Send['Items'],
						array(
							'Name' => '網路商品一批',
							'Price' => $aio->Send['TotalAmount'],
							'Currency' => $order->get_order_currency(),
							'Quantity' => 1
						)
					);
					
					$aio->Send['TradeDesc'] = 'allPay_module_woocommerce_1.1.0801';
					
					# Get the chosen payment and installment
					$notes = $order->get_customer_order_notes();
					$choose_payment = '';
					$choose_installment = '';
					if (isset($notes[0])) {
						list($choose_payment, $choose_installment) = explode('_', $notes[0]->comment_content);
					}
					$aio->Send['ChoosePayment'] = $choose_payment;
					
					# Set the extend information
					switch ($aio->Send['ChoosePayment']) {
						case 'Credit':
							# Do not support UnionPay
							$aio->SendExtend['UnionPay'] = false;
							
							# Credit installment parameters
							if (!empty($choose_installment)) {
								$aio->SendExtend['CreditInstallment'] = $choose_installment;
								$aio->SendExtend['InstallmentAmount'] = $aio->Send['TotalAmount'];
								$aio->SendExtend['Redeem'] = false;
							}
							break;
						case 'WebATM':
							break;
						case 'ATM':
							$aio->SendExtend['ExpireDate'] = 3;
							$aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
							break;
						case 'CVS':
							$aio->SendExtend['Desc_1'] = '';
							$aio->SendExtend['Desc_2'] = '';
							$aio->SendExtend['Desc_3'] = '';
							$aio->SendExtend['Desc_4'] = '';
							$aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
							break;
						case 'Alipay':
							$aio->SendExtend['Email'] = $order->billing_email;
							$aio->SendExtend['PhoneNo'] = $order->billing_phone;
							$aio->SendExtend['UserName'] = $order->billing_first_name . ' ' . $order->billing_last_name;
							break;
						case 'Tenpay':
							$aio->SendExtend['ExpireTime'] = date('Y/m/d H:i:s', strtotime('+3 days'));
							break;
						case 'TopUpUsed':
							break;
						default:
							throw new Exception($this->tran('Invalid payment method.'));
							break;
					}
					$aio->CheckOut();
					exit;
				} catch(Exception $e) {
					$this->allPay_add_error($e->getMessage());
				}
			}
			
			/**
			 * Process the callback
			 */
			public function receive_response() {
				$result_msg = '1|OK';
				$order = null;
				try {
					# Retrieve the check out result
					$this->invoke_allpay_module();
					$aio = new AllInOne();
					$aio->HashKey = $this->allpay_hash_key;
					$aio->HashIV = $this->allpay_hash_iv;
					$aio->MerchantID = $this->allpay_merchant_id;
					$allpay_feedback = $aio->CheckOutFeedback();
					unset($aio);
					if(count($allpay_feedback) < 1) {
						throw new Exception('Get allPay feedback failed.');
					} else {
						# Get the cart order id
						$cart_order_id = $allpay_feedback['MerchantTradeNo'];
						if ($this->allpay_test_mode == 'yes') {
							$cart_order_id = substr($allpay_feedback['MerchantTradeNo'], 14);
						}
						
						# Get the cart order amount
						$order = new WC_Order($cart_order_id);
						$cart_amount = $order->get_total();
						
						# Check the amounts
						$allpay_amount = $allpay_feedback['TradeAmt'];
						if (round($cart_amount) != $allpay_amount) {
							throw new Exception('Order ' . $cart_order_id . ' amount are not identical.');
						}
						else
						{
							# Set the common comments
							$comments = sprintf(
								$this->tran('Payment Method : %s<br />Trade Time : %s<br />'),
								$allpay_feedback['PaymentType'],
								$allpay_feedback['TradeDate']
							);
							
							# Set the getting code comments
							$return_code = $allpay_feedback['RtnCode'];
							$return_message = $allpay_feedback['RtnMsg'];
							$get_code_result_comments = sprintf(
								$this->tran('Getting Code Result : (%s)%s'),
								$return_code,
								$return_message
							);
							
							# Set the payment result comments
							$payment_result_comments = sprintf(
								$this->tran('Payment Result : (%s)%s'),
								$return_code,
								$return_message
							);
							
							# Set the fail message
							$fail_message = sprintf('Order %s Exception.(%s: %s)', $cart_order_id, $return_code, $return_message);
							
							# Get allPay payment method
							$allpay_payment_method = $this->get_payment_method($allpay_feedback['PaymentType']);
							
							# Set the order comments
							switch($allpay_payment_method) {
								case PaymentMethod::Credit:
								case PaymentMethod::WebATM:
								case PaymentMethod::Alipay:
								case PaymentMethod::Tenpay:
								case PaymentMethod::TopUpUsed:
									if ($return_code != 1 and $return_code != 800) {
										throw new Exception($fail_msg);
									} else {
										if (!$this->is_order_complete($order)) {
											$this->confirm_order($order, $payment_result_comments);
										} else {
											# The order already paid or not in the standard procedure, do nothing
										}
									}
									break;
								case PaymentMethod::ATM:
									if ($return_code != 1 and $return_code != 2 and $return_code != 800) {
										throw new Exception($fail_msg);
									} else {
										if ($return_code == 2) {
											# Set the getting code result
											$comments .= $this->get_order_comments($allpay_feedback);
											$comments .= $get_code_result_comments;
											$order->add_order_note($comments);
										} else {
											if (!$this->is_order_complete($order)) {
												$this->confirm_order($order, $payment_result_comments);
											} else {
												# The order already paid or not in the standard procedure, do nothing
											}
										}
									}
									break;
								case PaymentMethod::CVS:
									if ($return_code != 1 and $return_code != 800 and $return_code != 10100073) {
										throw new Exception($fail_msg);
									} else {
										if ($return_code == 10100073) {
											# Set the getting code result
											$comments .= $this->get_order_comments($allpay_feedback);
											$comments .= $get_code_result_comments;
											$order->add_order_note($comments);
										} else {
											if (!$this->is_order_complete($order)) {
												$this->confirm_order($order, $payment_result_comments);
											} else {
												# The order already paid or not in the standard procedure, do nothing
											}
										}
									}
									break;
								default:
									throw new Exception('Invalid payment method of the order ' . $cart_order_id . '.');
									break;
							}
						}
					}
				} catch (Exception $e) {
					$error = $e->getMessage();
					if (!empty($order)) {
						$comments .= sprintf($this->tran('Faild To Pay<br />Error : %s<br />'), $error);
						$order->update_status('failed', $comments);
					}
					
					# Set the failure result
					$result_msg = '0|' . $error;
				}
				echo $result_msg;
				exit;
			}
			
			
			# Custom function
			
			/**
			 * Translate the content
			 * @param  string   translate target
			 * @return string   translate result
			 */
			private function tran($content) {
				return __($content, $this->allpay_domain);
			}
			
			/**
			 * Get the payment method description
			 * @param  string   payment name
			 * @return string   payment method description
			 */
			private function get_payment_desc($payment_name) {
				$payment_desc = array(
					'Credit' => $this->tran('Credit'),
					'Credit_3' => $this->tran('Credit(3 Installments)'),
					'Credit_6' => $this->tran('Credit(6 Installments)'),
					'Credit_12' => $this->tran('Credit(12 Installments)'),
					'Credit_18' => $this->tran('Credit(18 Installments)'),
					'Credit_24' => $this->tran('Credit(24 Installments)'),
					'WebATM' => $this->tran('WEB-ATM'),
					'ATM' => $this->tran('ATM'),
					'CVS' => $this->tran('CVS'),
					'Alipay' => $this->tran('Alipay'),
					'Tenpay' => $this->tran('Tenpay'),
					'TopUpUsed' => $this->tran('TopUpUsed')
				);
				
				return $payment_desc[$payment_name];
			}
			
			/**
			 * Add a next line character
			 * @param  string   content
			 * @return string   content with next line character
			 */
			private function add_next_line($content) {
				return $content . "\n";
			}
			
			/**
			 * Invoke allPay module
			 */
			private function invoke_allpay_module() {
				if (!class_exists('AllInOne')) {
					if (!require(plugin_dir_path(__FILE__) . '/lib/AllPay.Payment.Integration.php')) {
						throw new Exception($this->tran('allPay module missed.'));
					}
				}
			}
			
			/**
			 * Format the version description
			 * @param  string   version string
			 * @return string   version description
			 */
			private function format_version_desc($version) {
				return str_replace('.', '_', $version);
			}
			
			/**
			 * Add a WooCommerce error message
			 * @param  string   error message
			 */
			private function allPay_add_error($error_message) {
				wc_add_notice($error_message, 'error');
			}
			
			/**
			 * Check if the order status is complete
			 * @param  object   order
			 * @return boolean  is the order complete
			 */
			private function is_order_complete($order) {
                $status = '';
                $status = (method_exists($Order,'get_status') == true )? $order->get_status(): $order->status;

				if ($status == 'pending') {
					return false;
				} else {
					return true;
				}
			}
			
			/**
			 * Get the payment method from the payment_type
			 * @param  string   payment type
			 * @return string   payment method
			 */
			private function get_payment_method($payment_type) {
				$info_pieces = explode('_', $payment_type);
				
				return $info_pieces[0];
			}
			
			/**
			 * Get the order comments
			 * @param  array    allPay feedback
			 * @return string   order comments
			 */
			function get_order_comments($allpay_feedback)
			{
				$comments = array(
					'ATM' => 
						sprintf(
						  $this->tran('Bank Code : %s<br />Virtual Account : %s<br />Payment Deadline : %s<br />'),
							$allpay_feedback['BankCode'],
							$allpay_feedback['vAccount'],
							$allpay_feedback['ExpireDate']
						),
					'CVS' => 
						sprintf(
							$this->tran('Trade Code : %s<br />Payment Deadline : %s<br />'),
							$allpay_feedback['PaymentNo'],
							$allpay_feedback['ExpireDate']
						)
				);
				$payment_method = $this->get_payment_method($allpay_feedback['PaymentType']);
				
				return $comments[$payment_method];
			}
			
			/**
			 * Complete the order and add the comments
			 * @param  object   order
			 */
			function confirm_order($order, $comments) {
				$order->add_order_note($comments, true);
				$order->payment_complete();

				// call invoice model
				$invoice_active_ecpay = 0 ;
				$invoice_active_allpay = 0 ;

				$active_plugins = (array) get_option( 'active_plugins', array() );

				$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

				foreach($active_plugins as $key => $value)
				{
					if ( (strpos($value,'/woocommerce-ecpayinvoice.php') !== false))
					{
						$invoice_active_ecpay = 1;
					}

					if ( (strpos($value,'/woocommerce-allpayinvoice.php') !== false))
					{
						$invoice_active_allpay = 1;
					}
				}

				if($invoice_active_ecpay == 0 && $invoice_active_allpay == 1) // allpay
				{
					if( is_file( get_home_path().'/wp-content/plugins/allpay_invoice/woocommerce-allpayinvoice.php') )
					{
						$aConfig_Invoice = get_option('wc_allpayinvoice_active_model') ;

						if(isset($aConfig_Invoice) && $aConfig_Invoice['wc_allpay_invoice_enabled'] == 'enable' && $aConfig_Invoice['wc_allpay_invoice_auto'] == 'auto' )
						{
							do_action('allpay_auto_invoice', $order->id, $ecpay_feedback['SimulatePaid']);
						}
					}
				}
				elseif($invoice_active_ecpay == 1 && $invoice_active_allpay == 0) //ecpay
				{
					if( is_file( get_home_path().'/wp-content/plugins/ecpay_invoice/woocommerce-ecpayinvoice.php') )
					{
						$aConfig_Invoice = get_option('wc_ecpayinvoice_active_model') ;

						if(isset($aConfig_Invoice) && $aConfig_Invoice['wc_ecpay_invoice_enabled'] == 'enable' && $aConfig_Invoice['wc_ecpay_invoice_auto'] == 'auto' )
						{
							do_action('ecpay_auto_invoice', $order->id, $ecpay_feedback['SimulatePaid']);
						}
					}
				}
			}
	    }

	    /**
	     * Add the Gateway Plugin to WooCommerce
	     * */
	    function woocommerce_add_allpay_plugin($methods) {
	        $methods[] = 'WC_Gateway_Allpay';
					
	        return $methods;
	    }

	    add_filter('woocommerce_payment_gateways', 'woocommerce_add_allpay_plugin');
	}
?>