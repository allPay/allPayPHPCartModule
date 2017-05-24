<?php

class ModelPaymentAllpay extends Model {
	private $trans = array();
	
	public function getMethod($address, $total) {
		# Condition check
		$allpay_geo_zone_id = $this->config->get('allpay_geo_zone_id');
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE geo_zone_id = '" . (int)$allpay_geo_zone_id . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
		$status = false;
		if ($total <= 0) {
			$status = false;
		} elseif (!$allpay_geo_zone_id) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		# Set the payment method parameters
		$this->load->language('payment/allpay');
		$method_data = array();
		if ($status) {
			$method_data = array(
				'code' => 'allpay',
				'title' => $this->language->get('allpay_text_title'),
				'terms' => '',
				'sort_order' => $this->config->get('allpay_sort_order')
			);
		}
		
		return $method_data;
	}
	
	public function vaildatePayment($payment) {
		$payment_methods = $this->config->get('allpay_payment_methods');
		if (isset($payment_methods[$payment])) {
			return true;
		} else {
			return false;
		}
	}
	
	public function invokeAllpayModule() {
		if (!class_exists('AllInOne', false)) {
			if (!include('AllPay.Payment.Integration.php')) {
				$this->load->language('payment/allpay');
				return false;
			}
		}
		
		return true;
	}
	
	public function isTestMode($allpay_merchant_id) {
		if ($allpay_merchant_id == '2000132' or $allpay_merchant_id == '2000214') {
			return true;
		} else {
			return false;
		}
	}
	
	public function getCartOrderID($merchant_trade_no, $allpay_merchant_id) {
		$cart_order_id = $merchant_trade_no;
		if ($this->isTestMode($allpay_merchant_id)) {
			$cart_order_id = substr($merchant_trade_no, 14);
		}
		
		return $cart_order_id;
	}
	
	public function formatOrderTotal($order_total) {
		return intval(round($order_total));
	}
	
	public function getPaymentMethod($payment_type) {
		$info_pieces = explode('_', $payment_type);
		
		return $info_pieces[0];
	}

	public function logMessage($message) {
		$log = new Log('allpay_return_url.log');
		$log->write($message);
	}
	
}
