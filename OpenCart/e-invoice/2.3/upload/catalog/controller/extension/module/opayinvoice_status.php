<?php
$data['opayinvoice_enabled'] = false;
foreach ($results as $result) {
	if ($result['code'] == 'opayinvoice' && $this->config->get($result['code'] . '_status')) {
		$data['opayinvoice_enabled'] = true;
	}
}
?>
