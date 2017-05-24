<?php

class ControllerPaymentAllpay extends Controller {
	
	private $error = array();
	private $require_settings = array('merchant_id', 'hash_key', 'hash_iv');

	public function index() {
		# Load the translation file
		$this->load->language('payment/allpay');
		
		# Set the title
		$heading_title = $this->language->get('heading_title');
		$this->document->setTitle($heading_title);
		$data['heading_title'] = $heading_title;
		
		# Load the Setting
		$this->load->model('setting/setting');
		
		# Process the saving setting
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			# Save the setting
			$this->model_setting_setting->editSetting('allpay', $this->request->post);
			
			# Define the success message
			$this->session->data['success'] = $this->language->get('allpay_text_success');
			
			# Back to the payment list
			$this->response->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'));
		}
		
		# Get the translation
		$data['allpay_text_status'] = $this->language->get('allpay_text_status');
		$data['allpay_text_enabled'] = $this->language->get('allpay_text_enabled');
		$data['allpay_text_disabled'] = $this->language->get('allpay_text_disabled');
		$data['allpay_text_merchant_id'] = $this->language->get('allpay_text_merchant_id');
		$data['allpay_text_hash_key'] = $this->language->get('allpay_text_hash_key');
		$data['allpay_text_hash_iv'] = $this->language->get('allpay_text_hash_iv');
		$data['allpay_text_payment_methods'] = $this->language->get('allpay_text_payment_methods');
		$data['allpay_text_credit'] = $this->language->get('allpay_text_credit');
		$data['allpay_text_credit_3'] = $this->language->get('allpay_text_credit_3');
		$data['allpay_text_credit_6'] = $this->language->get('allpay_text_credit_6');
		$data['allpay_text_credit_12'] = $this->language->get('allpay_text_credit_12');
		$data['allpay_text_credit_18'] = $this->language->get('allpay_text_credit_18');
		$data['allpay_text_credit_24'] = $this->language->get('allpay_text_credit_24');
		$data['allpay_text_webatm'] = $this->language->get('allpay_text_webatm');
		$data['allpay_text_atm'] = $this->language->get('allpay_text_atm');
		$data['allpay_text_cvs'] = $this->language->get('allpay_text_cvs');
		// $data['allpay_text_barcode'] = $this->language->get('allpay_text_barcode');
		$data['allpay_text_alipay'] = $this->language->get('allpay_text_alipay');
		$data['allpay_text_tenpay'] = $this->language->get('allpay_text_tenpay');
		$data['allpay_text_topupused'] = $this->language->get('allpay_text_topupused');
		$data['allpay_text_geo_zone'] = $this->language->get('allpay_text_geo_zone');
		$data['allpay_text_all_zones'] = $this->language->get('allpay_text_all_zones');
		$data['allpay_text_sort_order'] = $this->language->get('allpay_text_sort_order');
		
		# Get the error
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		
		# Get the error of the require fields
		foreach ($this->require_settings as $setting_name) {
			$tmp_error_name = 'allpay_error_' . $setting_name;
			if(isset($this->error[$tmp_error_name])) {
				$data[$tmp_error_name] = $this->error[$tmp_error_name];
			} else {
				$data[$tmp_error_name] = '';
			}
		}
		
		# Set the breadcrumbs
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('allpay_text_home'),
			'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL')
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('allpay_text_payment'),
			'href' => $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL')
		);
		$data['breadcrumbs'][] = array(
			'text' => $heading_title,
			'href' => $this->url->link('payment/allpay', 'token=' . $this->session->data['token'], 'SSL')
		);
		
		# Set the form action
		$data['allapy_action'] = $this->url->link('payment/allpay', 'token=' . $this->session->data['token'], 'SSL');
		
		# Set the cancel button
		$data['allpay_cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL');
		
		# Get allPay setting
		$allpay_settings = array(
			'status',
			'merchant_id',
			'hash_key',
			'hash_iv',
			'payment_methods',
			'geo_zone_id',
			'sort_order'
		);
		foreach ($allpay_settings as $setting_name) {
			$tmp_setting_name = 'allpay_' . $setting_name;
			if (isset($this->request->post[$tmp_setting_name])) {
				$data[$tmp_setting_name] = $this->request->post[$tmp_setting_name];
			} else {
				$data[$tmp_setting_name] = $this->config->get($tmp_setting_name);
			}
		}
		
		# test
		if (empty($data['allpay_merchant_id'])) {$data['allpay_merchant_id'] = '2000132';}
		if (empty($data['allpay_hash_key'])) {$data['allpay_hash_key'] = '5294y06JbISpM5x9';}
		if (empty($data['allpay_hash_iv'])) {$data['allpay_hash_iv'] = 'v77hoKGq4kWxNNIS';}
		
		# Get the geo zone
		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
		
		# View's setting
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('payment/allpay.tpl', $data));
	}
	
	protected function validate() {
		if (!$this->user->hasPermission('modify', 'payment/allpay')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		
		foreach ($this->require_settings as $setting_name) {
			if (!$this->request->post['allpay_' . $setting_name]) {
				$this->error['allpay_error_' . $setting_name] = $this->language->get('allpay_error_' . $setting_name);
			}
		}
		
		return !$this->error; 
	}
}
