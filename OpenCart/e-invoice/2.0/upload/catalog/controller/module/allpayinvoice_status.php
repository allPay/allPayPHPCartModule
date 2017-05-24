<?php
$data['allpayinvoice_enabled'] = false;
foreach ($results as $result) {
	if ($result['code'] == 'allpayinvoice' && $this->config->get($result['code'] . '_status')) {
		$data['allpayinvoice_enabled'] = true;
	}
}
?>
