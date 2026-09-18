<?php
namespace MDcart\Admin\Controller\Extension\IranianGateways\Payment;
/**
 * Class Aqayepardakht
 *
 * @package MDcart\Admin\Controller\Extension\IranianGateways\Payment
 */
class Aqayepardakht extends \MDcart\System\Engine\Controller {
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
		$this->load->language('extension/iranian_gateways/payment/aqayepardakht');

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
			'href' => $this->url->link('extension/iranian_gateways/payment/aqayepardakht', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/iranian_gateways/payment/aqayepardakht.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		$data['payment_aqayepardakht_pin'] = $this->config->get('payment_aqayepardakht_pin');
		$data['payment_aqayepardakht_sandbox'] = $this->config->get('payment_aqayepardakht_sandbox');

		// Order Status
		$data['payment_aqayepardakht_order_status_id'] = (int)$this->config->get('payment_aqayepardakht_order_status_id');

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		// Geo Zone
		$data['payment_aqayepardakht_geo_zone_id'] = $this->config->get('payment_aqayepardakht_geo_zone_id');

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['payment_aqayepardakht_status'] = $this->config->get('payment_aqayepardakht_status');
		$data['payment_aqayepardakht_sort_order'] = $this->config->get('payment_aqayepardakht_sort_order');

		$data['callback_url'] = $this->url->link('extension/iranian_gateways/payment/aqayepardakht.callback', '', true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/iranian_gateways/payment/aqayepardakht', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/iranian_gateways/payment/aqayepardakht');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/iranian_gateways/payment/aqayepardakht')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (empty($this->request->post['payment_aqayepardakht_pin'])) {
			$json['error']['pin'] = $this->language->get('error_pin');
		}

		if (!$json) {
			// Setting
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_aqayepardakht', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
