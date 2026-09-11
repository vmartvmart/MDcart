<?php
namespace Opencart\Admin\Controller\Extension\Whatsapp\Other;
/**
 * Class Whatsapp
 *
 * @package Opencart\Admin\Controller\Extension\Whatsapp\Other
 */
class Whatsapp extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/whatsapp/other/whatsapp');

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
			'href' => $this->url->link('extension/whatsapp/other/whatsapp', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/whatsapp/other/whatsapp.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=other');

		foreach (['status', 'phone_number_id', 'access_token', 'language_code', 'template_order_add', 'template_order_status', 'order_add_status', 'order_status_status', 'admin_status', 'admin_telephone'] as $key) {
			$data['other_whatsapp_' . $key] = $this->config->get('other_whatsapp_' . $key);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/whatsapp/other/whatsapp', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/whatsapp/other/whatsapp');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/whatsapp/other/whatsapp')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (!empty($this->request->post['other_whatsapp_status'])) {
			if (empty($this->request->post['other_whatsapp_phone_number_id'])) {
				$json['error']['phone_number_id'] = $this->language->get('error_phone_number_id');
			}

			if (empty($this->request->post['other_whatsapp_access_token'])) {
				$json['error']['access_token'] = $this->language->get('error_access_token');
			}
		}

		if (!empty($json['error'])) {
			$json['error']['warning'] = $json['error']['warning'] ?? $this->language->get('error_warning');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('other_whatsapp', $this->request->post);

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

		if (!$this->model_setting_event->getEventByCode('whatsapp_order')) {
			$this->model_setting_event->addEvent([
				'code'        => 'whatsapp_order',
				'description' => 'Sends WhatsApp notifications on new orders and order status changes.',
				'trigger'     => 'model/checkout/order.addHistory/before',
				'action'      => 'extension/whatsapp/event/order',
				'status'      => 1,
				'sort_order'  => 1
			]);
		}

		$this->load->model('setting/extension');

		if (!$this->model_setting_extension->getInstallByCode('whatsapp')) {
			$this->model_setting_extension->addInstall([
				'extension_id'          => 0,
				'extension_download_id' => 0,
				'name'                  => 'WhatsApp Cloud API Notifications',
				'description'           => 'Sends order/admin notifications through Meta\'s official WhatsApp Cloud API using pre-approved message templates.',
				'code'                  => 'whatsapp',
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

		$this->model_setting_event->deleteEventByCode('whatsapp_order');
	}
}
