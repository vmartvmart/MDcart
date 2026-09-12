<?php
namespace MDcart\Admin\Controller\Extension\IranianGateways\Payment;
/**
 * Class SizPay
 *
 * @package MDcart\Admin\Controller\Extension\IranianGateways\Payment
 */
class Sizpay extends \MDcart\System\Engine\Controller {
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
		$this->load->language('extension/iranian_gateways/payment/sizpay');

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
			'href' => $this->url->link('extension/iranian_gateways/payment/sizpay', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/iranian_gateways/payment/sizpay.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		$data['payment_sizpay_merchant_id'] = $this->config->get('payment_sizpay_merchant_id');
		$data['payment_sizpay_terminal_id'] = $this->config->get('payment_sizpay_terminal_id');
		$data['payment_sizpay_username'] = $this->config->get('payment_sizpay_username');
		$data['payment_sizpay_password'] = $this->config->get('payment_sizpay_password');

		// Order Status
		$data['payment_sizpay_order_status_id'] = (int)$this->config->get('payment_sizpay_order_status_id');

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		// Geo Zone
		$data['payment_sizpay_geo_zone_id'] = $this->config->get('payment_sizpay_geo_zone_id');

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['payment_sizpay_status'] = $this->config->get('payment_sizpay_status');
		$data['payment_sizpay_sort_order'] = $this->config->get('payment_sizpay_sort_order');

		$data['callback_url'] = $this->url->link('extension/iranian_gateways/payment/sizpay.callback', '', true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/iranian_gateways/payment/sizpay', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/iranian_gateways/payment/sizpay');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/iranian_gateways/payment/sizpay')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		foreach (['merchant_id', 'terminal_id', 'username', 'password'] as $field) {
			if (empty($this->request->post['payment_sizpay_' . $field])) {
				$json['error'][$field] = $this->language->get('error_' . $field);
			}
		}

		if (!$json) {
			// Setting
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_sizpay', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
