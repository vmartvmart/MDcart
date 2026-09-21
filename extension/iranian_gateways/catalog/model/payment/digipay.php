<?php
namespace MDcart\Catalog\Model\Extension\IranianGateways\Payment;
/**
 * Class Digipay
 *
 * Can be called from $this->load->model('extension/iranian_gateways/payment/digipay');
 *
 * @package MDcart\Catalog\Model\Extension\IranianGateways\Payment
 */
class Digipay extends \MDcart\System\Engine\Model {
	/**
	 * Get Methods
	 *
	 * @param array<string, mixed> $address array of data
	 *
	 * @return array<string, mixed>
	 */
	public function getMethods(array $address = []): array {
		$this->load->language('extension/iranian_gateways/payment/digipay');

		if (!$this->config->get('payment_digipay_client_id') || !$this->config->get('payment_digipay_client_secret') || !$this->config->get('payment_digipay_username') || !$this->config->get('payment_digipay_password')) {
			return [];
		}

		if ($this->cart->hasSubscription()) {
			$status = false;
		} elseif (!$this->config->get('config_checkout_payment_address')) {
			$status = true;
		} elseif (!$this->config->get('payment_digipay_geo_zone_id')) {
			$status = true;
		} else {
			$this->load->model('localisation/geo_zone');

			$results = $this->model_localisation_geo_zone->getGeoZone((int)$this->config->get('payment_digipay_geo_zone_id'), (int)$address['country_id'], (int)$address['zone_id']);

			$status = (bool)$results;
		}

		$method_data = [];

		if ($status) {
			$option_data['digipay'] = [
				'code' => 'digipay.digipay',
				'name' => $this->language->get('heading_title')
			];

			$method_data = [
				'code'       => 'digipay',
				'name'       => $this->language->get('heading_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_digipay_sort_order')
			];
		}

		return $method_data;
	}
}
