<?php
namespace Opencart\Catalog\Model\Extension\IranianGateways\Payment;
/**
 * Class IDPay
 *
 * Can be called from $this->load->model('extension/iranian_gateways/payment/idpay');
 *
 * @package Opencart\Catalog\Model\Extension\IranianGateways\Payment
 */
class Idpay extends \Opencart\System\Engine\Model {
	/**
	 * Get Methods
	 *
	 * @param array<string, mixed> $address array of data
	 *
	 * @return array<string, mixed>
	 */
	public function getMethods(array $address = []): array {
		$this->load->language('extension/iranian_gateways/payment/idpay');

		if (!$this->config->get('payment_idpay_api_key')) {
			return [];
		}

		if ($this->cart->hasSubscription()) {
			$status = false;
		} elseif (!$this->config->get('config_checkout_payment_address')) {
			$status = true;
		} elseif (!$this->config->get('payment_idpay_geo_zone_id')) {
			$status = true;
		} else {
			$this->load->model('localisation/geo_zone');

			$results = $this->model_localisation_geo_zone->getGeoZone((int)$this->config->get('payment_idpay_geo_zone_id'), (int)$address['country_id'], (int)$address['zone_id']);

			$status = (bool)$results;
		}

		$method_data = [];

		if ($status) {
			$option_data['idpay'] = [
				'code' => 'idpay.idpay',
				'name' => $this->language->get('heading_title')
			];

			$method_data = [
				'code'       => 'idpay',
				'name'       => $this->language->get('heading_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_idpay_sort_order')
			];
		}

		return $method_data;
	}
}
