<?php
namespace MDcart\Admin\Controller\Extension\StockAlert\Other;
/**
 * Class StockAlert
 *
 * @package MDcart\Admin\Controller\Extension\StockAlert\Other
 */
class StockAlert extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/stock_alert/other/stock_alert');

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
			'href' => $this->url->link('extension/stock_alert/other/stock_alert', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/stock_alert/other/stock_alert.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=other');

		$data['other_stock_alert_status'] = $this->config->get('other_stock_alert_status');

		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "stock_alert` WHERE `notified` = '0'");

		$data['text_pending'] = sprintf($this->language->get('text_pending'), (int)$query->row['total']);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/stock_alert/other/stock_alert', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/stock_alert/other/stock_alert');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/stock_alert/other/stock_alert')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('other_stock_alert', $this->request->post);

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

		if (!$this->model_setting_event->getEventByCode('stock_alert_restock')) {
			$this->model_setting_event->addEvent([
				'code'        => 'stock_alert_restock',
				'description' => 'Notifies subscribed customers when a product is restocked.',
				'trigger'     => 'model/catalog/product.editProduct/after',
				'action'      => 'extension/stock_alert/event/restock',
				'status'      => 1,
				'sort_order'  => 1
			]);
		}

		$this->load->model('setting/extension');

		if (!$this->model_setting_extension->getInstallByCode('stock_alert')) {
			$this->model_setting_extension->addInstall([
				'extension_id'          => 0,
				'extension_download_id' => 0,
				'name'                  => 'Back in Stock Alerts',
				'description'           => 'Lets customers ask to be notified (SMS/WhatsApp/Telegram/Bale) when an out-of-stock product becomes available again.',
				'code'                  => 'stock_alert',
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

		$this->model_setting_event->deleteEventByCode('stock_alert_restock');
	}
}
