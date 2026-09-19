<?php
namespace MDcart\Admin\Controller\Extension\IranianGateways\Payment;
/**
 * Class Bitpay
 *
 * @package MDcart\Admin\Controller\Extension\IranianGateways\Payment
 */
class Bitpay extends \MDcart\System\Engine\Controller {
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
		$this->load->language('extension/iranian_gateways/payment/bitpay');

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
			'href' => $this->url->link('extension/iranian_gateways/payment/bitpay', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/iranian_gateways/payment/bitpay.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		$data['payment_bitpay_api'] = $this->config->get('payment_bitpay_api');
		$data['payment_bitpay_sandbox'] = $this->config->get('payment_bitpay_sandbox');

		// Order Status
		$data['payment_bitpay_order_status_id'] = (int)$this->config->get('payment_bitpay_order_status_id');

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		// Geo Zone
		$data['payment_bitpay_geo_zone_id'] = $this->config->get('payment_bitpay_geo_zone_id');

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['payment_bitpay_status'] = $this->config->get('payment_bitpay_status');
		$data['payment_bitpay_sort_order'] = $this->config->get('payment_bitpay_sort_order');

		$data['callback_url'] = $this->url->link('extension/iranian_gateways/payment/bitpay.callback', '', true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/iranian_gateways/payment/bitpay', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/iranian_gateways/payment/bitpay');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/iranian_gateways/payment/bitpay')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (empty($this->request->post['payment_bitpay_api'])) {
			$json['error']['api'] = $this->language->get('error_api');
		}

		if (!$json) {
			// Setting
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_bitpay', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
