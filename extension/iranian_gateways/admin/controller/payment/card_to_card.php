<?php
namespace MDcart\Admin\Controller\Extension\IranianGateways\Payment;
/**
 * Class Card To Card
 *
 * Manual bank-card-transfer payment method. Admin keeps a list of cards and
 * marks one of them "active" - that is the card shown to customers at
 * checkout. The customer transfers manually and confirms; the order is put
 * into a "pending verification" status for the admin to confirm by hand.
 *
 * @package MDcart\Admin\Controller\Extension\IranianGateways\Payment
 */
class CardToCard extends \MDcart\System\Engine\Controller {
	/**
	 * @var array<string, string>
	 */
	private array $error = [];

	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/iranian_gateways/payment/card_to_card');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/iranian_gateways/payment/card_to_card', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/iranian_gateways/payment/card_to_card.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		// Language
		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['payment_card_to_card_instruction'] = [];

		foreach ($data['languages'] as $language) {
			$data['payment_card_to_card_instruction'][$language['language_id']] = $this->config->get('payment_card_to_card_instruction_' . $language['language_id']);
		}

		// Cards
		$data['payment_card_to_card_cards'] = $this->config->get('payment_card_to_card_cards') ?: [];
		$data['payment_card_to_card_active'] = $this->config->get('payment_card_to_card_active');

		// Order Status
		$data['payment_card_to_card_order_status_id'] = (int)$this->config->get('payment_card_to_card_order_status_id');

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		// Geo Zone
		$data['payment_card_to_card_geo_zone_id'] = $this->config->get('payment_card_to_card_geo_zone_id');

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['payment_card_to_card_status'] = $this->config->get('payment_card_to_card_status');
		$data['payment_card_to_card_sort_order'] = $this->config->get('payment_card_to_card_sort_order');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/iranian_gateways/payment/card_to_card', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/iranian_gateways/payment/card_to_card');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/iranian_gateways/payment/card_to_card')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$cards = $this->request->post['payment_card_to_card_cards'] ?? [];

		foreach ($cards as $uid => $card) {
			$digits = preg_replace('/\D/', '', (string)($card['card_number'] ?? ''));

			if (oc_strlen($digits) != 16) {
				$json['error']['card_number_' . $uid] = $this->language->get('error_card_number');
			}

			if (empty($card['holder_name'])) {
				$json['error']['holder_name_' . $uid] = $this->language->get('error_holder_name');
			}
		}

		if (!$cards) {
			$json['error']['warning'] = $this->language->get('error_no_cards');
		}

		$active = $this->request->post['payment_card_to_card_active'] ?? '';

		if ($cards && (!$active || !isset($cards[$active]))) {
			$json['error']['warning'] = $this->language->get('error_active_card');
		}

		if (!$json) {
			// Setting
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_card_to_card', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
