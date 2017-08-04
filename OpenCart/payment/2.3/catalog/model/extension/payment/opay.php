<?php

class ModelExtensionPaymentOpay extends Model {
    private $prefix = 'opay_';
    private $model_name = 'opay';
    private $model_path = 'extension/payment/opay';
	private $trans = array();
	private $libraryList = array('OpayCartLibrary.php');
	
	public function getMethod($address, $total) {
        // Condition check
        $opay_geo_zone_id = $this->config->get($this->prefix . 'geo_zone_id');
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE geo_zone_id = '" . (int)$opay_geo_zone_id . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
        $status = false;
        if ($total <= 0) {
            $status = false;
        } elseif (!$opay_geo_zone_id) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }
        
        // Set the payment method parameters
        $this->load->language($this->model_path);
        $method_data = array();
        if ($status) {
            $method_data = array(
                'code' => $this->model_name,
                'title' => $this->language->get($this->prefix . 'text_title'),
                'terms' => '',
                'sort_order' => $this->config->get($this->prefix . 'sort_order')
            );
        }
        return $method_data;
    }
	
	public function loadLibrary() {
		foreach ($this->libraryList as $path) {
			include_once($path);
		}
	}

    public function getHelper() {
        $merchant_id = $this->config->get($this->prefix . 'merchant_id');
        return new OpayCartLibrary(array('merchantId' => $merchant_id));
    }
}
