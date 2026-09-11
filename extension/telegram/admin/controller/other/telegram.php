<?php
namespace Opencart\Admin\Controller\Extension\Telegram\Other;
/**
 * Class Telegram
 *
 * @package Opencart\Admin\Controller\Extension\Telegram\Other
 */
class Telegram extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/telegram/other/telegram');

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
			'href' => $this->url->link('extension/telegram/other/telegram', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/telegram/other/telegram.save', 'user_token=' . $this->session->data['user_token']);
		$data['set_webhook'] = $this->url->link('extension/telegram/other/telegram.setWebhook', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=other');

		foreach (['status', 'bot_token', 'bot_username', 'order_add_status', 'order_status_status', 'admin_status', 'admin_chat_id', 'admin_link_token'] as $key) {
			$data['other_telegram_' . $key] = $this->config->get('other_telegram_' . $key);
		}

		$data['webhook_url'] = $this->url->link('extension/telegram/webhook/telegram', '', true);

		$bot_username = $this->config->get('other_telegram_bot_username');
		$admin_link_token = $this->config->get('other_telegram_admin_link_token');

		$data['admin_connect_link'] = $bot_username ? ('https://t.me/' . $bot_username . '?start=admin_' . $admin_link_token) : '';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/telegram/other/telegram', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/telegram/other/telegram');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/telegram/other/telegram')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (!empty($this->request->post['other_telegram_status']) && empty($this->request->post['other_telegram_bot_token'])) {
			$json['error']['bot_token'] = $this->language->get('error_bot_token');
		}

		if (!empty($json['error'])) {
			$json['error']['warning'] = $json['error']['warning'] ?? $this->language->get('error_warning');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('other_telegram', $this->request->post);

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
		$this->load->language('extension/telegram/other/telegram');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/telegram/other/telegram')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$token = (string)$this->config->get('other_telegram_bot_token');

		if (!$json && !$token) {
			$json['error'] = $this->language->get('error_bot_token');
		}

		if (!$json) {
			$this->load->library('extension/telegram/telegram');

			$telegram = new \Opencart\System\Library\Extension\Telegram\Telegram($token);

			$url = $this->url->link('extension/telegram/webhook/telegram', '', true);

			if ($telegram->setWebhook($url)) {
				$json['success'] = $this->language->get('text_webhook_success');
			} else {
				$json['error'] = $telegram->error ?: $this->language->get('error_webhook');
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

		if (!$this->model_setting_event->getEventByCode('telegram_order')) {
			$this->model_setting_event->addEvent([
				'code'        => 'telegram_order',
				'description' => 'Sends Telegram notifications on new orders and order status changes.',
				'trigger'     => 'model/checkout/order.addHistory/before',
				'action'      => 'extension/telegram/event/order',
				'status'      => 1,
				'sort_order'  => 1
			]);
		}

		if (!$this->config->get('other_telegram_admin_link_token')) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('other_telegram', [
				'other_telegram_admin_link_token' => bin2hex(random_bytes(16))
			]);
		}

		$this->load->model('setting/extension');

		if (!$this->model_setting_extension->getInstallByCode('telegram')) {
			$this->model_setting_extension->addInstall([
				'extension_id'          => 0,
				'extension_download_id' => 0,
				'name'                  => 'Telegram Bot Notifications',
				'description'           => 'Sends order/admin notifications through a Telegram bot (api.telegram.org).',
				'code'                  => 'telegram',
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

		$this->model_setting_event->deleteEventByCode('telegram_order');
	}
}
