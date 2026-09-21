<?php
namespace MDcart\Admin\Controller\Extension\IranianGateways\Payment;
/**
 * Class Digipay
 *
 * @package MDcart\Admin\Controller\Extension\IranianGateways\Payment
 */
class Digipay extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/iranian_gateways/payment/digipay');

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
			'href' => $this->url->link('extension/iranian_gateways/payment/digipay', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/iranian_gateways/payment/digipay.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		$data['payment_digipay_client_id'] = $this->config->get('payment_digipay_client_id');
		$data['payment_digipay_client_secret'] = $this->config->get('payment_digipay_client_secret');
		$data['payment_digipay_username'] = $this->config->get('payment_digipay_username');
		$data['payment_digipay_password'] = $this->config->get('payment_digipay_password');
		$data['payment_digipay_sandbox'] = $this->config->get('payment_digipay_sandbox');

		// Order Status
		$data['payment_digipay_order_status_id'] = (int)$this->config->get('payment_digipay_order_status_id');
		$data['payment_digipay_deliver_order_status_id'] = (int)$this->config->get('payment_digipay_deliver_order_status_id');

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		// Geo Zone
		$data['payment_digipay_geo_zone_id'] = $this->config->get('payment_digipay_geo_zone_id');

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['payment_digipay_status'] = $this->config->get('payment_digipay_status');
		$data['payment_digipay_sort_order'] = $this->config->get('payment_digipay_sort_order');

		$data['callback_url'] = $this->url->link('extension/iranian_gateways/payment/digipay.callback', '', true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/iranian_gateways/payment/digipay', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/iranian_gateways/payment/digipay');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/iranian_gateways/payment/digipay')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (empty($this->request->post['payment_digipay_client_id'])) {
			$json['error']['client_id'] = $this->language->get('error_client_id');
		}

		if (empty($this->request->post['payment_digipay_client_secret'])) {
			$json['error']['client_secret'] = $this->language->get('error_client_secret');
		}

		if (empty($this->request->post['payment_digipay_username'])) {
			$json['error']['username'] = $this->language->get('error_username');
		}

		if (empty($this->request->post['payment_digipay_password'])) {
			$json['error']['password'] = $this->language->get('error_password');
		}

		if (!$json) {
			// Setting
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_digipay', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
