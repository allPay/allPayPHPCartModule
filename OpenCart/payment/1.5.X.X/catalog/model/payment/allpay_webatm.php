<?php 
class ModelPaymentAllpayWebatm extends Model {

    private $paymentSubfix = 'webatm';

    public function getMethod($address, $total) {
        $this->load->language('payment/allpay_payment');
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int) $this->config->get('allpay_' . $this->paymentSubfix . '_geo_zone_id') . "' AND country_id = '" . (int) $address['country_id'] . "' AND (zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')");

        if ($this->config->get('allpay_' . $this->paymentSubfix . '_total') > $total) {
            $status = false;
        } elseif (!$this->config->get('allpay_' . $this->paymentSubfix . '_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }
        $method_data = array();

        if ($status) {
            $method_data = array(
                'code' => 'allpay_' . $this->paymentSubfix,
                'title' => $this->language->get('text_' . $this->paymentSubfix . '_title'),
                'sort_order' => $this->config->get('allpay_' . $this->paymentSubfix . '_sort_order')
            );
        }
        return $method_data;
    }

}
