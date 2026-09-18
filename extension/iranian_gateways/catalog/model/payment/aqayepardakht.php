<?php
namespace MDcart\Catalog\Model\Extension\IranianGateways\Payment;
/**
 * Class Aqayepardakht
 *
 * Can be called from $this->load->model('extension/iranian_gateways/payment/aqayepardakht');
 *
 * @package MDcart\Catalog\Model\Extension\IranianGateways\Payment
 */
class Aqayepardakht extends \MDcart\System\Engine\Model {
	/**
	 * Get Methods
	 *
	 * @param array<string, mixed> $address array of data
	 *
	 * @return array<string, mixed>
	 */
	public function getMethods(array $address = []): array {
		$this->load->language('extension/iranian_gateways/payment/aqayepardakht');

		if (!$this->config->get('payment_aqayepardakht_pin')) {
			return [];
		}

		if ($this->cart->hasSubscription()) {
			$status = false;
		} elseif (!$this->config->get('config_checkout_payment_address')) {
			$status = true;
		} elseif (!$this->config->get('payment_aqayepardakht_geo_zone_id')) {
			$status = true;
		} else {
			$this->load->model('localisation/geo_zone');

			$results = $this->model_localisation_geo_zone->getGeoZone((int)$this->config->get('payment_aqayepardakht_geo_zone_id'), (int)$address['country_id'], (int)$address['zone_id']);

			$status = (bool)$results;
		}

		$method_data = [];

		if ($status) {
			$option_data['aqayepardakht'] = [
				'code' => 'aqayepardakht.aqayepardakht',
				'name' => $this->language->get('heading_title')
			];

			$method_data = [
				'code'       => 'aqayepardakht',
				'name'       => $this->language->get('heading_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_aqayepardakht_sort_order')
			];
		}

		return $method_data;
	}
}
