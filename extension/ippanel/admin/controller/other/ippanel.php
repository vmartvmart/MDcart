<?php
namespace Opencart\Admin\Controller\Extension\Ippanel\Other;
/**
 * Class Ippanel
 *
 * @package Opencart\Admin\Controller\Extension\Ippanel\Other
 */
class Ippanel extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/ippanel/other/ippanel');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=other')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/ippanel/other/ippanel', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/ippanel/other/ippanel.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=other');

		foreach (['status', 'api_key', 'sender', 'order_add_status', 'order_status_status', 'admin_status', 'admin_telephone'] as $key) {
			$data['other_ippanel_' . $key] = $this->config->get('other_ippanel_' . $key);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/ippanel/other/ippanel', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/ippanel/other/ippanel');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/ippanel/other/ippanel')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (!empty($this->request->post['other_ippanel_status']) && empty($this->request->post['other_ippanel_api_key'])) {
			$json['error']['api_key'] = $this->language->get('error_api_key');
		}

		if (!empty($this->request->post['other_ippanel_status']) && empty($this->request->post['other_ippanel_sender'])) {
			$json['error']['sender'] = $this->language->get('error_sender');
		}

		if (!empty($json['error'])) {
			$json['error']['warning'] = $json['error']['warning'] ?? $this->language->get('error_warning');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('other_ippanel', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Install
	 *
	 * @return void
	 */
	public function install(): void {
		$this->load->model('setting/event');

		if (!$this->model_setting_event->getEventByCode('ippanel_order')) {
			$this->model_setting_event->addEvent([
				'code'        => 'ippanel_order',
				'description' => 'Sends IPPanel SMS notifications on new orders and order status changes.',
				'trigger'     => 'model/checkout/order.addHistory/before',
				'action'      => 'extension/ippanel/event/order',
				'status'      => 1,
				'sort_order'  => 1
			]);
		}

		// Register this as an installed extension package so its classes keep
		// autoloading on every future admin/catalog request (see startup/extension.php).
		$this->load->model('setting/extension');

		if (!$this->model_setting_extension->getInstallByCode('ippanel')) {
			$this->model_setting_extension->addInstall([
				'extension_id'          => 0,
				'extension_download_id' => 0,
				'name'                  => 'IPPanel SMS',
				'description'           => 'Sends order/customer SMS notifications through the IPPanel (edge.ippanel.com) REST API.',
				'code'                  => 'ippanel',
				'version'               => '1.0',
				'author'                => '',
				'link'                  => ''
			]);
		}
	}

	/**
	 * Uninstall
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode('ippanel_order');
	}
}
