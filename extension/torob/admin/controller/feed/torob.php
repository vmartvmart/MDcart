<?php
namespace Opencart\Admin\Controller\Extension\Torob\Feed;
/**
 * Class Torob
 *
 * @package Opencart\Admin\Controller\Extension\Torob\Feed
 */
class Torob extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/torob/feed/torob');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/torob/feed/torob', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/torob/feed/torob.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed');

		$data['feed_torob_status'] = $this->config->get('feed_torob_status');

		$data['feed_url'] = $this->url->link('extension/torob/feed/torob', '', true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/torob/feed/torob', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/torob/feed/torob');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/torob/feed/torob')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			// Setting
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('feed_torob', $this->request->post);

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
		$this->load->model('setting/setting');

		$this->model_setting_setting->editSetting('feed_torob', ['feed_torob_status' => 1]);
	}

	/**
	 * Uninstall
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$this->load->model('setting/setting');

		$this->model_setting_setting->deleteSetting('feed_torob');
	}
}
