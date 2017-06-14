<?php

class ControllerPaymentAllpay extends Controller {
	
	public function index() {
		# Set the checkout form action
		$data = array();
		$data['allpay_action'] = $this->url->link('payment/allpay/redirect', '', 'SSL');
		
		# Get the translation
		$this->load->language('payment/allpay');
		$data['allpay_text_title'] = $this->language->get('allpay_text_title');
		$data['allpay_text_payment_methods'] = $this->language->get('allpay_text_payment_methods');
		$data['allpay_text_checkout_button'] = $this->language->get('allpay_text_checkout_button');
		
		# Get the translation of payment methods
		$payment_methods = $this->config->get('allpay_payment_methods');
		$data['payment_methods'] = array();
		foreach ($payment_methods as $payment_type => $value) {
			$data['payment_methods'][$payment_type] = $this->language->get('allpay_text_' . $value);
		}
		
		# Get the template
		$config_template = $this->config->get('config_template');
		$payment_template = '';
		if (file_exists(DIR_TEMPLATE . $config_template)) {
			$payment_template = $config_template;
		} else {
			$payment_template = 'default';
		}
		$payment_template .= (strpos(VERSION, '2.2.') !== false) ? '/payment/allpay.tpl' : '/template/payment/allpay.tpl';
		
		return $this->load->view($payment_template, $data);
  }

	public function redirect() {
		try {
			# Load allPay translation
			$this->load->language('payment/allpay');
			
			# Validate the payment
			$payment_type = $this->request->post['allpay_choose_payment'];
			$this->load->model('payment/allpay');
			$is_valid = $this->model_payment_allpay->vaildatePayment($payment_type);
			if (!$is_valid) {
				throw new Exception($this->language->get('allpay_error_invalid_payment'));
			} else {
				if (!isset($this->session->data['order_id'])) {
					throw new Exception($this->language->get('allpay_error_order_id_miss'));
				} else {
					# Get the order info
					$order_id = $this->session->data['order_id'];
					$this->load->model('checkout/order');
					$order = $this->model_checkout_order->getOrder($order_id);

					
					# Generate the redirection form
					$form_html = '';
					$invoke_result = $this->model_payment_allpay->invokeAllpayModule();
					if (!$invoke_result) {
						throw new Exception($this->language->get('allpay_error_module_miss'));
					} else {
						# Set allPay parameters
						$aio = new AllInOne();
						$aio->Send['MerchantTradeNo'] = '';
						$service_url = '';
						$aio->MerchantID = $this->config->get('allpay_merchant_id');
						if ($this->model_payment_allpay->isTestMode($aio->MerchantID)) {
							$service_url = 'https://payment-stage.allpay.com.tw/Cashier/AioCheckOut/V4';
							$aio->Send['MerchantTradeNo'] = date('YmdHis');
						} else {
							$service_url = 'https://payment.allpay.com.tw/Cashier/AioCheckOut/V4';
						}
						$aio->HashKey = $this->config->get('allpay_hash_key');
						$aio->HashIV = $this->config->get('allpay_hash_iv');
						$aio->ServiceURL = $service_url;
						$aio->Send['ReturnURL'] = $this->url->link('payment/allpay/response', '', 'SSL');
						// $aio->Send['ClientBackURL'] = str_replace('&amp;', '&', $this->url->link('account/order', '', 'SSL'));
						$aio->Send['ClientBackURL'] = str_replace('&amp;', '&', $this->url->link('account/order/info', 'order_id=' . $order_id, 'SSL'));
						$aio->Send['MerchantTradeNo'] .= $order_id;
						$aio->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
						
						# Set the product info
						$aio->Send['TotalAmount'] = $this->model_payment_allpay->formatOrderTotal($order['total']);
						array_push(
							$aio->Send['Items'],
							array(
								'Name' => $this->language->get('allpay_text_item_name'),
								'Price' => $aio->Send['TotalAmount'],
								//'Currency' => $_SESSION['currency'],
								'Quantity' => 1,
								'URL' => ''
							)
						);
						# Set the trade descriptions
						$aio->Send['TradeDesc'] = 'OPay_module_opencart_1.1.0609';
						
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
							// case PaymentMethod::BARCODE:
								$aio->SendExtend['Desc_1'] = '';
								$aio->SendExtend['Desc_2'] = '';
								$aio->SendExtend['Desc_3'] = '';
								$aio->SendExtend['Desc_4'] = '';
								$aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
								break;
							// case PaymentMethod::Alipay:
							// 	$aio->SendExtend['Email'] = $order['email'];
							// 	$aio->SendExtend['PhoneNo'] = $order['telephone'];
							// 	$aio->SendExtend['UserName'] = $order['firstname'] . ' ' . $order['lastname'];
							// 	break;
							case PaymentMethod::Tenpay:
								$aio->SendExtend['ExpireTime'] = date('Y/m/d H:i:s', strtotime('+3 days'));
								break;
							case PaymentMethod::TopUpUsed:
								break;
							default:
								break;
						}
					}
					
					# Update order status and comments
					$payment_methods = $this->config->get('allpay_payment_methods');
					$order_create_status_id = 2;# processing
					$payment_desc = $this->language->get('allpay_text_' . $payment_methods[$payment_type]);
					$this->model_checkout_order->addOrderHistory($order_id, $order_create_status_id, $payment_desc, true);
				
					# Clean the cart
					$this->cart->clear();

					# Add to activity log
					$this->load->model('account/activity');
					if ($this->customer->isLogged()) {
						$activity_data = array(
							'customer_id' => $this->customer->getId(),
							'name'        => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
							'order_id'    => $order_id
						);

						$this->model_account_activity->addActivity('order_account', $activity_data);
					} else {
						$activity_data = array(
							'name'     => $this->session->data['guest']['firstname'] . ' ' . $this->session->data['guest']['lastname'],
							'order_id' => $order_id
						);

						$this->model_account_activity->addActivity('order_guest', $activity_data);
					}

					# Clean the session
					unset($this->session->data['shipping_method']);
					unset($this->session->data['shipping_methods']);
					unset($this->session->data['payment_method']);
					unset($this->session->data['payment_methods']);
					unset($this->session->data['guest']);
					unset($this->session->data['comment']);
					unset($this->session->data['order_id']);
					unset($this->session->data['coupon']);
					unset($this->session->data['reward']);
					unset($this->session->data['voucher']);
					unset($this->session->data['vouchers']);
					unset($this->session->data['totals']);
					
					# Print the redirection form
					$aio->CheckOut();
					exit;
				}
			}
		} catch (Exception $e) {
			# Process the exception
			$this->session->data['error'] = $e->getMessage();
			$checkout_url = $this->url->link('checkout/checkout', '', 'SSL');
			$this->response->redirect($checkout_url);
		}
	}
	
	public function response() {
		# Load the model and translation
		$this->load->language('payment/allpay');
		$this->load->model('payment/allpay');
		$this->load->model('checkout/order');
		
		# Set the default result message
		$result_message = '1|OK';
		$cart_order_id = null;
		$order = null;
		try {
			# Retrieve the checkout result
			$invoke_result = $this->model_payment_allpay->invokeAllpayModule();
			if (!$invoke_result) {
				throw new Exception('O\'Pay module is missing.');
			} else {
				$aio = new AllInOne();
				$aio->HashKey = $this->config->get('allpay_hash_key');
				$aio->HashIV = $this->config->get('allpay_hash_iv');
				$allpay_feedback = $aio->CheckOutFeedback();
				unset($aio);
				
				# Process allPay feedback
				if(count($allpay_feedback) < 1) {
					throw new Exception('Get O\'Pay feedback failed.');
				} else {
					# Get the cart order id
					$cart_order_id = $this->model_payment_allpay->getCartOrderID($allpay_feedback['MerchantTradeNo'], $this->config->get('allpay_merchant_id'));
				
					# Get the cart order amount
					$order = $this->model_checkout_order->getOrder($cart_order_id);
					$cart_amount = $this->model_payment_allpay->formatOrderTotal($order['total']);
					
					# Check the amounts
					$allpay_amount = $allpay_feedback['TradeAmt'];
					if ($cart_amount != $allpay_amount) {
						throw new Exception(sprintf('Order %s amount are not identical.', $cart_order_id));
					} else {
						# Set the common comments
						$comments = sprintf(
							$this->language->get('allpay_text_common_comments'),
							$allpay_feedback['PaymentType'],
							$allpay_feedback['TradeDate']
						);
						
						#  Set the getting code comments
						$return_message = $allpay_feedback['RtnMsg'];
						$return_code = $allpay_feedback['RtnCode'];
						$get_code_result_comments = sprintf(
							$this->language->get('allpay_text_get_code_result_comments'),
							$return_code,
							$return_message
						);
						
						#  Set the payment result comments
						$payment_result_comments = sprintf(
							$this->language->get('allpay_text_payment_result_comments'),
							$return_code,
							$return_message
						);
						
						# Get allPay payment method
						$type_pieces = explode('_', $allpay_feedback['PaymentType']);
						$allpay_payment_method = $type_pieces[0];
						
						# Update the order status and comments
						$fail_message = sprintf('Order %s Exception.(%s: %s)', $cart_order_id, $return_code, $return_message);
						$order_create_status_id = 2;# processing
						$paid_succeeded_status_id = 5;# Complete
						
						switch($allpay_payment_method) {
							case PaymentMethod::Credit:
							case PaymentMethod::WebATM:
							// case PaymentMethod::Alipay:
							case PaymentMethod::Tenpay:
							case PaymentMethod::TopUpUsed:
								if ($return_code != 1 and $return_code != 800) {
									throw new Exception($fail_message);
								} else {
									# Only finish the order when the status is processing 
									if ($order['order_status_id'] != $order_create_status_id) {
										# The order already paid or not in the standard procedure, do nothing
									} else {
										
										$this->model_checkout_order->addOrderHistory(
											$cart_order_id,
											$paid_succeeded_status_id,
											$payment_result_comments,
											true
										);
										
										
										// 判斷電子發票是否啟動 START
										$nInvoice_Status  = $this->config->get('allpayinvoice_status');
										if($nInvoice_Status == 1)
										{
											$this->load->model('payment/allpayinvoice');
											$nInvoice_Autoissue 	= $this->config->get('allpayinvoice_autoissue');
											$sCheck_Invoice_SDK	= $this->model_payment_allpayinvoice->check_invoice_sdk();
											if( $nInvoice_Autoissue == 1 && $sCheck_Invoice_SDK != false )
											{	
												$this->model_payment_allpayinvoice->createInvoiceNo($cart_order_id, $sCheck_Invoice_SDK);
											}
										}
										// 判斷電子發票是否啟動 END
	
									}
								}
								break;
							case PaymentMethod::ATM:
								if ($return_code != 1 and $return_code != 2 and $return_code != 800) {
									throw new Exception($fail_message);
								} else {
									if ($return_code == 2) {
										# Set the getting code result
										$comments .= sprintf(
											$this->language->get('allpay_text_atm_comments'),
											$allpay_feedback['BankCode'],
											$allpay_feedback['vAccount'],
											$allpay_feedback['ExpireDate']
										);
										$this->model_checkout_order->addOrderHistory(
											$cart_order_id,
											$order_create_status_id,
											$comments . $get_code_result_comments
										);
									} else {
										# Only finish the order when the status is processing 
										if ($order['order_status_id'] != $order_create_status_id) {
											# The order already paid or not in the standard procedure, do nothing
										} else {
											
											$this->model_checkout_order->addOrderHistory(
												$cart_order_id,
												$paid_succeeded_status_id,
												$payment_result_comments,
												true
											);
											
											// 判斷電子發票是否啟動 START
											$nInvoice_Status  = $this->config->get('allpayinvoice_status');
											if($nInvoice_Status == 1)
											{
												$this->load->model('payment/allpayinvoice');
												$nInvoice_Autoissue 	= $this->config->get('allpayinvoice_autoissue');
												$sCheck_Invoice_SDK	= $this->model_payment_allpayinvoice->check_invoice_sdk();
												if( $nInvoice_Autoissue == 1 && $sCheck_Invoice_SDK != false )
												{	
													$this->model_payment_allpayinvoice->createInvoiceNo($cart_order_id, $sCheck_Invoice_SDK);
												}
											}
											// 判斷電子發票是否啟動 END
											
										}
									}
								}
								break;
							case PaymentMethod::CVS:
								if ($return_code != 1 and $return_code != 800 and $return_code != 10100073) {
									throw new Exception($fail_message);
								} else {
									if ($return_code == 10100073) {
										$comments .= sprintf(
											$this->language->get('allpay_text_cvs_comments'),
											$allpay_feedback['PaymentNo'],
											$allpay_feedback['ExpireDate']
										);
										$this->model_checkout_order->addOrderHistory(
											$cart_order_id,
											$order_create_status_id,
											$comments . $get_code_result_comments
										);
									} else {
										# Only finish the order when the status is processing 
										if ($order['order_status_id'] != $order_create_status_id) {
											# The order already paid or not in the standard procedure, do nothing
										} else {
											$this->model_checkout_order->addOrderHistory(
												$cart_order_id,
												$paid_succeeded_status_id,
												$payment_result_comments,
												true
											);
											
											
											// 判斷電子發票是否啟動 START
											$nInvoice_Status  = $this->config->get('allpayinvoice_status');
											if($nInvoice_Status == 1)
											{
												$this->load->model('payment/allpayinvoice');
												$nInvoice_Autoissue 	= $this->config->get('allpayinvoice_autoissue');
												$sCheck_Invoice_SDK	= $this->model_payment_allpayinvoice->check_invoice_sdk();
												if( $nInvoice_Autoissue == 1 && $sCheck_Invoice_SDK != false )
												{	
													$this->model_payment_allpayinvoice->createInvoiceNo($cart_order_id, $sCheck_Invoice_SDK);
												}
											}
											// 判斷電子發票是否啟動 END
										}
									}
								}
								break;
							/* case PaymentMethod::BARCODE:
								if ($return_code != 1 and $return_code != 800 and $return_code != 10100073) {
									throw new Exception($fail_message);
								} else {
									if ($return_code == 10100073) {
										$comments .= sprintf(
											$this->language->get('allpay_text_barcode_comments'),
											$allpay_feedback['ExpireDate'],
											$allpay_feedback['Barcode1'],
											$allpay_feedback['Barcode2'],
											$allpay_feedback['Barcode3']
										);
										$this->model_checkout_order->addOrderHistory(
											$cart_order_id,
											$order_create_status_id,
											$comments . $get_code_result_comments
										);
									} else {
										# Only finish the order when the status is processing 
										if ($order['order_status_id'] != $order_create_status_id) {
											# The order already paid or not in the standard procedure, do nothing
										} else {
											$this->model_checkout_order->addOrderHistory(
												$cart_order_id,
												$paid_succeeded_status_id,
												$payment_result_comments,
												true
											);
											
											// 判斷電子發票是否啟動 START
											$nInvoice_Status  = $this->config->get('allpayinvoice_status');
											if($nInvoice_Status == 1)
											{
												$this->load->model('payment/allpayinvoice');
												$nInvoice_Autoissue 	= $this->config->get('allpayinvoice_autoissue');
												$sCheck_Invoice_SDK	= $this->model_payment_allpayinvoice->check_invoice_sdk();
												if( $nInvoice_Autoissue == 1 && $sCheck_Invoice_SDK != false )
												{	
													$this->model_payment_allpayinvoice->createInvoiceNo($cart_order_id, $sCheck_Invoice_SDK);
												}
											}
											// 判斷電子發票是否啟動 END
										}
									}
								}
								break; */
							default:
								throw new Exception(sprintf('Order %s, payment method is invalid.', $cart_order_id));
								break;
						}
					}
				}
			}
		} catch (Exception $e) {
			$error = $e->getMessage();
			if (!empty($order)) {
				$paid_failed_status_id = 10;
				$comments = sprintf($this->language->get('allpay_text_failure_comments'), $error);
				$this->model_checkout_order->addOrderHistory($cart_order_id, $paid_failed_status_id, $comments);
			}
			
			# Set the failure result
			$result_message = '0|' . $error;
		}
		# Return URL log
		$this->model_payment_allpay->logMessage('Order ' . $cart_order_id . ' process O\'Pay response result : ' . $result_message);
		
		echo $result_message;
		exit;
	}

}
