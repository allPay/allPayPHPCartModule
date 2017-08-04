<?php

class ControllerExtensionPaymentOpay extends Controller {
    
    private $error = array();
    private $model_name = 'opay';
    private $prefix = 'opay_';
    private $model_path = 'extension/payment/opay';

    public function index() {
        // Load the translation file
        $this->load->language($this->model_path);
        
        // Set the title
        $heading_title = $this->language->get('heading_title');
        $this->document->setTitle($heading_title);
        
        // Load the Setting
        $this->load->model('setting/setting');

        // Token
        $token = $this->session->data['token'];
        
        // Process the saving setting
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            // Save the setting
            $this->model_setting_setting->editSetting($this->model_name, $this->request->post);
            
            // Define the success message
            $this->session->data['success'] = $this->language->get($this->prefix . 'text_success');
            
            // Back to the payment list
            $this->response->redirect($this->url->link('extension/extension', 'token=' . $token . '&type=payment', true));
        }
        
        // Get the translations
        $data['heading_title'] = $heading_title;
        $data['text_all_zones'] = $this->language->get('text_all_zones');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        $translation_names = array(
            $this->prefix . 'text_enabled',
            $this->prefix . 'text_disabled',
            $this->prefix . 'text_credit',
            $this->prefix . 'text_credit_3',
            $this->prefix . 'text_credit_6',
            $this->prefix . 'text_credit_12',
            $this->prefix . 'text_credit_18',
            $this->prefix . 'text_credit_24',
            $this->prefix . 'text_webatm',
            $this->prefix . 'text_atm',
            $this->prefix . 'text_cvs',
            $this->prefix . 'text_tenpay',
            $this->prefix . 'text_topupused',

            $this->prefix . 'entry_status',
            $this->prefix . 'entry_merchant_id',
            $this->prefix . 'entry_hash_key',
            $this->prefix . 'entry_hash_iv',
            $this->prefix . 'entry_payment_methods',
            $this->prefix . 'entry_create_status',
            $this->prefix . 'entry_success_status',
            $this->prefix . 'entry_failed_status',
            $this->prefix . 'entry_geo_zone',
            $this->prefix . 'entry_sort_order',
        );
        foreach ($translation_names as $name) {
            $data[$name] = $this->language->get($name);
        }
        
        // Get the errors
        if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
        $error_fields = array(
            $this->prefix . 'merchant_id',
            $this->prefix . 'hash_key',
            $this->prefix . 'hash_iv'
        );
        foreach ($error_fields as $name) {
        	$replaced = str_replace($this->prefix, '', $name);
            $error_name = $this->prefix . 'error_' . $replaced;
            if(isset($this->error[$name])) {
                $data[$error_name] = $this->error[$name];
            } else {
                $data[$error_name] = '';
            }
        }
        
        // Set the breadcrumbs
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'token=' . $token, true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get($this->prefix . 'text_extension'),
            'href' => $this->url->link('extension/extension', 'token=' . $token . '&type=payment', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $heading_title,
            'href' => $this->url->link($this->model_path, 'token=' . $token, true)
        );
        
        // Set the form action
        $data[$this->prefix . 'action'] = $this->url->link($this->model_path, 'token=' . $token, true);
        
        // Set the cancel button
        $data[$this->prefix . 'cancel'] = $this->url->link('extension/extension', 'token=' . $token . '&type=payment', true);
        
        // Get the setting
        $settings = array(
            $this->prefix . 'status',
            $this->prefix . 'merchant_id',
            $this->prefix . 'hash_key',
            $this->prefix . 'hash_iv',
            $this->prefix . 'payment_methods',
            $this->prefix . 'create_status',
            $this->prefix . 'success_status',
            $this->prefix . 'failed_status',
            $this->prefix . 'geo_zone_id',
            $this->prefix . 'sort_order'
        );
        foreach ($settings as $name) {
            if (isset($this->request->post[$name])) {
                $data[$name] = $this->request->post[$name];
            } else {
                $data[$name] = $this->config->get($name);
            }
        }
        
        // Default value
        $default_config = array(
            $this->prefix . 'merchant_id' => '2000132',
            $this->prefix . 'hash_key' => '5294y06JbISpM5x9',
            $this->prefix . 'hash_iv' => 'v77hoKGq4kWxNNIS',
            $this->prefix . 'create_status' => 1,
            $this->prefix . 'success_status' => 15,
        );
        foreach ($default_config as $name => $value) {
            if (is_null($data[$name])) {
                $data[$name] = $value;
            }
        }
        
        // Get the order statuses
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        // Get the geo zones
        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        
        // View's setting
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view($this->model_path, $data));
    }
    
    protected function validate() {
        // Premission validate
        if (!$this->user->hasPermission('modify', $this->model_path)) {
            $this->error['warning'] = $this->language->get($this->prefix . 'error_permission');
        }
        
        // Required fields validate
        $require_fields = array(
            $this->prefix . 'merchant_id',
            $this->prefix . 'hash_key',
            $this->prefix . 'hash_iv'
        );
        foreach ($require_fields as $name) {
            if (!$this->request->post[$name]) {
            	$replaced = str_replace($this->prefix, '', $name);
                $this->error[$name] = $this->language->get($this->prefix . 'error_' . $replaced);
            }
        }
        
        return !$this->error; 
    }
}
