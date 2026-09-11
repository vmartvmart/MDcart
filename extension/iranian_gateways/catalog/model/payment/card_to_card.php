<?php
namespace Opencart\Catalog\Model\Extension\IranianGateways\Payment;
/**
 * Class Card To Card
 *
 * Can be called from $this->load->model('extension/iranian_gateways/payment/card_to_card');
 *
 * @package Opencart\Catalog\Model\Extension\IranianGateways\Payment
 */
class CardToCard extends \Opencart\System\Engine\Model {
	/**
	 * Get Methods
	 *
	 * @param array<string, mixed> $address array of data
	 *
	 * @return array<string, mixed>
	 */
	public function getMethods(array $address = []): array {
		$this->load->language('extension/iranian_gateways/payment/card_to_card');

		$cards = $this->config->get('payment_card_to_card_cards') ?: [];
		$active = $this->config->get('payment_card_to_card_active');

		if (!$cards || !$active || !isset($cards[$active])) {
			return [];
		}

		if ($this->cart->hasSubscription()) {
			$status = false;
		} elseif (!$this->config->get('config_checkout_payment_address')) {
			$status = true;
		} elseif (!$this->config->get('payment_card_to_card_geo_zone_id')) {
			$status = true;
		} else {
			$this->load->model('localisation/geo_zone');

			$results = $this->model_localisation_geo_zone->getGeoZone((int)$this->config->get('payment_card_to_card_geo_zone_id'), (int)$address['country_id'], (int)$address['zone_id']);

			$status = (bool)$results;
		}

		$method_data = [];

		if ($status) {
			$option_data['card_to_card'] = [
				'code' => 'card_to_card.card_to_card',
				'name' => $this->language->get('heading_title')
			];

			$method_data = [
				'code'       => 'card_to_card',
				'name'       => $this->language->get('heading_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_card_to_card_sort_order')
			];
		}

		return $method_data;
	}
}
