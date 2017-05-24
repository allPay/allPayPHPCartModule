<?php

class ControllerPaymentAllpayTopupused extends Controller {

    private $paymentSubfix = 'topupused';
    private $error = array();

    public function index() {
        $this->load->language('payment/allpay_payment');
        $this->load->model('setting/setting');
        $this->load->model('localisation/language');
        $this->load->model('localisation/geo_zone');
        $this->load->model('localisation/order_status');

        $languages = $this->model_localisation_language->getLanguages();

        $this->data['languages'] = $languages;
        $this->data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        $this->data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $this->document->setTitle($this->language->get('heading_' . $this->paymentSubfix . '_title'));

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('allpay_' . $this->paymentSubfix . '', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'));
        }

        $this->data['heading_title'] = $this->language->get('heading_' . $this->paymentSubfix . '_title');

        $this->data['text_enabled'] = $this->language->get('text_enabled');
        $this->data['text_disabled'] = $this->language->get('text_disabled');
        $this->data['text_all_zones'] = $this->language->get('text_all_zones');

        $this->data['entry_subfix'] = $this->paymentSubfix;
        $this->data['entry_bank'] = $this->language->get('heading_' . $this->paymentSubfix . '_title');
        $this->data['entry_test_mode'] = $this->language->get('entry_test_mode');
		$this->data['entry_test_fix'] = $this->language->get('entry_test_fix');
        $this->data['entry_merchant_id'] = $this->language->get('entry_merchant_id');
        $this->data['entry_hash_key'] = $this->language->get('entry_hash_key');
        $this->data['entry_hash_iv'] = $this->language->get('entry_hash_iv');
        $this->data['entry_order_status'] = $this->language->get('entry_order_status');
        $this->data['entry_order_finish_status'] = $this->language->get('entry_order_finish_status');
        $this->data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
        $this->data['entry_status'] = $this->language->get('entry_status');
        $this->data['entry_sort_order'] = $this->language->get('entry_sort_order');

        $this->data['button_save'] = $this->language->get('button_save');
        $this->data['button_cancel'] = $this->language->get('button_cancel');

        $this->data['tab_general'] = $this->language->get('tab_general');

        if (isset($this->error['warning'])) {
            $this->data['error_warning'] = $this->error['warning'];
        } else {
            $this->data['error_warning'] = '';
        }
        if (isset($this->error['warning2'])) {
            $this->data['error_warning2'] = $this->error['warning2'];
        } else {
            $this->data['error_warning2'] = '';
        }
        if (isset($this->error['warning3'])) {
            $this->data['error_warning3'] = $this->error['warning3'];
        } else {
            $this->data['error_warning3'] = '';
        }
        if (isset($this->error['warning4'])) {
            $this->data['error_warning4'] = $this->error['warning4'];
        } else {
            $this->data['error_warning4'] = '';
        }
		if (isset($this->error['warning5'])) {
            $this->data['error_warning5'] = $this->error['warning5'];
        } else {
            $this->data['error_warning5'] = '';
        }

        foreach ($languages as $language) {
            if (isset($this->error['bank_' . $language['language_id']])) {
                $this->data['error_bank_' . $language['language_id']] = $this->error['bank_' . $language['language_id']];
            } else {
                $this->data['error_bank_' . $language['language_id']] = '';
            }
        }

        $this->data['breadcrumbs'] = array();
        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => false
        );
        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_payment'),
            'href' => $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );
        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('payment/allpay_' . $this->paymentSubfix . '', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );

        $this->data['action'] = $this->url->link('payment/allpay_' . $this->paymentSubfix . '', 'token=' . $this->session->data['token'], 'SSL');
        $this->data['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL');

        foreach ($languages as $language) {
            if (isset($this->request->post['allpay_' . $this->paymentSubfix . '_description_' . $language['language_id']])) {
                $this->data['allpay_payment_description_' . $language['language_id']] = $this->request->post['allpay_' . $this->paymentSubfix . '_description_' . $language['language_id']];
            } else {
                $this->data['allpay_payment_description_' . $language['language_id']] = $this->config->get('allpay_' . $this->paymentSubfix . '_description_' . $language['language_id']);
            }
        }

        if (isset($this->request->post['allpay_' . $this->paymentSubfix . '_order_status_id'])) {
            $this->data['allpay_order_status_id'] = $this->request->post['allpay_' . $this->paymentSubfix . '_order_status_id'];
        } else {
            $this->data['allpay_order_status_id'] = $this->config->get('allpay_' . $this->paymentSubfix . '_order_status_id');
        }
        if (isset($this->request->post['allpay_' . $this->paymentSubfix . '_order_finish_status_id'])) {
            $this->data['allpay_order_finish_status_id'] = $this->request->post['allpay_' . $this->paymentSubfix . '_order_finish_status_id'];
        } else {
            $this->data['allpay_order_finish_status_id'] = $this->config->get('allpay_' . $this->paymentSubfix . '_order_finish_status_id');
        }
        if (isset($this->request->post['allpay_' . $this->paymentSubfix . '_geo_zone_id'])) {
            $this->data['allpay_geo_zone_id'] = $this->request->post['allpay_' . $this->paymentSubfix . '_geo_zone_id'];
        } else {
            $this->data['allpay_geo_zone_id'] = $this->config->get('allpay_' . $this->paymentSubfix . '_geo_zone_id');
        }
        if (isset($this->request->post['allpay_' . $this->paymentSubfix . '_status'])) {
            $this->data['allpay_status'] = $this->request->post['allpay_' . $this->paymentSubfix . '_status'];
        } else {
            $this->data['allpay_status'] = $this->config->get('allpay_' . $this->paymentSubfix . '_status');
        }
        if (isset($this->request->post['allpay_' . $this->paymentSubfix . '_sort_order'])) {
            $this->data['allpay_sort_order'] = $this->request->post['allpay_' . $this->paymentSubfix . '_sort_order'];
        } else {
            $this->data['allpay_sort_order'] = $this->config->get('allpay_' . $this->paymentSubfix . '_sort_order');
        }
        if (isset($this->request->post['allpay_' . $this->paymentSubfix . '_test_mode'])) {
            $this->data['allpay_test_mode'] = $this->request->post['allpay_' . $this->paymentSubfix . '_test_mode'];
        } else {
            $this->data['allpay_test_mode'] = $this->config->get('allpay_' . $this->paymentSubfix . '_test_mode');
        }
		if (isset($this->request->post['allpay_' . $this->paymentSubfix . '_test_fix'])) {
            $this->data['allpay_test_fix'] = $this->request->post['allpay_' . $this->paymentSubfix . '_test_fix'];
        } else {
            $this->data['allpay_test_fix'] = $this->config->get('allpay_' . $this->paymentSubfix . '_test_fix');
        }
        if (isset($this->request->post['allpay_' . $this->paymentSubfix . '_merchant_id'])) {
            $this->data['allpay_merchant_id'] = $this->request->post['allpay_' . $this->paymentSubfix . '_merchant_id'];
        } else {
            $this->data['allpay_merchant_id'] = $this->config->get('allpay_' . $this->paymentSubfix . '_merchant_id');
        }
        if (isset($this->request->post['allpay_' . $this->paymentSubfix . '_hash_key'])) {
            $this->data['allpay_hash_key'] = $this->request->post['allpay_' . $this->paymentSubfix . '_hash_key'];
        } else {
            $this->data['allpay_hash_key'] = $this->config->get('allpay_' . $this->paymentSubfix . '_hash_key');
        }
        if (isset($this->request->post['allpay_' . $this->paymentSubfix . '_hash_iv'])) {
            $this->data['allpay_hash_iv'] = $this->request->post['allpay_' . $this->paymentSubfix . '_hash_iv'];
        } else {
            $this->data['allpay_hash_iv'] = $this->config->get('allpay_' . $this->paymentSubfix . '_hash_iv');
        }

        $this->template = 'payment/allpay_payment.tpl';
        $this->children = array(
            'common/header',
            'common/footer',
        );

        $this->response->setOutput($this->render());
    }

    private function validate() {
        if (!$this->user->hasPermission('modify', 'payment/allpay_' . $this->paymentSubfix . '')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        if (!$this->request->post['allpay_' . $this->paymentSubfix . '_merchant_id']) {
            $this->error['warning2'] = $this->language->get('error_merchant_id');
        }
        if (!$this->request->post['allpay_' . $this->paymentSubfix . '_hash_key']) {
            $this->error['warning3'] = $this->language->get('error_hash_key');
        }
        if (!$this->request->post['allpay_' . $this->paymentSubfix . '_hash_iv']) {
            $this->error['warning4'] = $this->language->get('error_hash_iv');
        }
		if ($this->request->post['allpay_' . $this->paymentSubfix . '_test_mode'] == "1") {
			if (!$this->request->post['allpay_' . $this->paymentSubfix . '_test_fix']) {
				$this->error['warning5'] = $this->language->get('error_test_fix');
			}			
			else if(!preg_match("/^[A-Za-z0-9]+$/", $this->request->post['allpay_' . $this->paymentSubfix . '_test_fix'])) {
				$this->error['warning5'] = $this->language->get('error_test_fix2');
			}			  
		}
		
        if (!$this->error) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

}
