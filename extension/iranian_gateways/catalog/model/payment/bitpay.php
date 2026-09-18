<?php
namespace MDcart\Catalog\Model\Extension\IranianGateways\Payment;
/**
 * Class Bitpay
 *
 * Can be called from $this->load->model('extension/iranian_gateways/payment/bitpay');
 *
 * @package MDcart\Catalog\Model\Extension\IranianGateways\Payment
 */
class Bitpay extends \MDcart\System\Engine\Model {
	/**
	 * Get Methods
	 *
	 * @param array<string, mixed> $address array of data
	 *
	 * @return array<string, mixed>
	 */
	public function getMethods(array $address = []): array {
		$this->load->language('extension/iranian_gateways/payment/bitpay');

		if (!$this->config->get('payment_bitpay_api')) {
			return [];
		}

		if ($this->cart->hasSubscription()) {
			$status = false;
		} elseif (!$this->config->get('config_checkout_payment_address')) {
			$status = true;
		} elseif (!$this->config->get('payment_bitpay_geo_zone_id')) {
			$status = true;
		} else {
			$this->load->model('localisation/geo_zone');

			$results = $this->model_localisation_geo_zone->getGeoZone((int)$this->config->get('payment_bitpay_geo_zone_id'), (int)$address['country_id'], (int)$address['zone_id']);

			$status = (bool)$results;
		}

		$method_data = [];

		if ($status) {
			$option_data['bitpay'] = [
				'code' => 'bitpay.bitpay',
				'name' => $this->language->get('heading_title')
			];

			$method_data = [
				'code'       => 'bitpay',
				'name'       => $this->language->get('heading_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_bitpay_sort_order')
			];
		}

		return $method_data;
	}
}
