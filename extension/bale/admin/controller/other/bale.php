<?php
namespace MDcart\Admin\Controller\Extension\Bale\Other;
/**
 * Class Bale
 *
 * @package MDcart\Admin\Controller\Extension\Bale\Other
 */
class Bale extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/bale/other/bale');

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
			'href' => $this->url->link('extension/bale/other/bale', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/bale/other/bale.save', 'user_token=' . $this->session->data['user_token']);
		$data['set_webhook'] = $this->url->link('extension/bale/other/bale.setWebhook', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=other');

		foreach (['status', 'bot_token', 'bot_username', 'order_add_status', 'order_status_status', 'admin_status', 'admin_chat_id', 'admin_link_token'] as $key) {
			$data['other_bale_' . $key] = $this->config->get('other_bale_' . $key);
		}

		$data['webhook_url'] = $this->url->link('extension/bale/webhook/bale', '', true);

		$bot_username = $this->config->get('other_bale_bot_username');
		$admin_link_token = $this->config->get('other_bale_admin_link_token');

		$data['admin_connect_link'] = $bot_username ? ('https://ble.ir/' . $bot_username . '?start=admin_' . $admin_link_token) : '';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/bale/other/bale', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/bale/other/bale');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/bale/other/bale')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (!empty($this->request->post['other_bale_status']) && empty($this->request->post['other_bale_bot_token'])) {
			$json['error']['bot_token'] = $this->language->get('error_bot_token');
		}

		if (!empty($json['error'])) {
			$json['error']['warning'] = $json['error']['warning'] ?? $this->language->get('error_warning');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('other_bale', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Set Webhook
	 *
	 * @return void
	 */
	public function setWebhook(): void {
		$this->load->language('extension/bale/other/bale');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/bale/other/bale')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$token = (string)$this->config->get('other_bale_bot_token');

		if (!$json && !$token) {
			$json['error'] = $this->language->get('error_bot_token');
		}

		if (!$json) {
			$this->load->library('extension/bale/bale');

			$bale = new \MDcart\System\Library\Extension\Bale\Bale($token);

			$url = $this->url->link('extension/bale/webhook/bale', '', true);

			if ($bale->setWebhook($url)) {
				$json['success'] = $this->language->get('text_webhook_success');
			} else {
				$json['error'] = $bale->error ?: $this->language->get('error_webhook');
			}
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

		if (!$this->model_setting_event->getEventByCode('bale_order')) {
			$this->model_setting_event->addEvent([
				'code'        => 'bale_order',
				'description' => 'Sends Bale notifications on new orders and order status changes.',
				'trigger'     => 'model/checkout/order.addHistory/before',
				'action'      => 'extension/bale/event/order',
				'status'      => 1,
				'sort_order'  => 1
			]);
		}

		if (!$this->config->get('other_bale_admin_link_token')) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('other_bale', [
				'other_bale_admin_link_token' => bin2hex(random_bytes(16))
			]);
		}

		$this->load->model('setting/extension');

		if (!$this->model_setting_extension->getInstallByCode('bale')) {
			$this->model_setting_extension->addInstall([
				'extension_id'          => 0,
				'extension_download_id' => 0,
				'name'                  => 'Bale Bot Notifications',
				'description'           => 'Sends order/admin notifications through a Bale (ble.ir) messenger bot.',
				'code'                  => 'bale',
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

		$this->model_setting_event->deleteEventByCode('bale_order');
	}
}
