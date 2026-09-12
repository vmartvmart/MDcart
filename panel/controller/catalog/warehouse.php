<?php
namespace MDcart\Admin\Controller\Catalog;
/**
 * Class Warehouse
 *
 * Admin CRUD for warehouses. Exactly one warehouse can be marked as the
 * "selling warehouse" - only its stock/cost feeds the online storefront
 * (oc_product.quantity/price). All other warehouses are only used by the
 * Warehouse Transfer and POS screens.
 *
 * Can be loaded using $this->load->controller('catalog/warehouse');
 *
 * @package MDcart\Admin\Controller\Catalog
 */
class Warehouse extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('catalog/warehouse');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/warehouse', 'user_token=' . $this->session->data['user_token'])
		];

		$data['add'] = $this->url->link('catalog/warehouse.form', 'user_token=' . $this->session->data['user_token']);
		$data['delete'] = $this->url->link('catalog/warehouse.delete', 'user_token=' . $this->session->data['user_token']);

		$data['list'] = $this->load->controller('catalog/warehouse.getList');

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/warehouse', $data));
	}

	/**
	 * List
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('catalog/warehouse');

		$this->response->setOutput($this->load->controller('catalog/warehouse.getList'));
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	public function getList(): string {
		$data['action'] = $this->url->link('catalog/warehouse.list', 'user_token=' . $this->session->data['user_token']);

		$this->load->model('catalog/warehouse');

		$results = $this->model_catalog_warehouse->getWarehouses();

		$data['warehouses'] = [];

		foreach ($results as $result) {
			$data['warehouses'][] = [
				'warehouse_id'         => $result['warehouse_id'],
				'name'                 => $result['name'],
				'currency_code'        => $result['currency_code'],
				'is_selling_warehouse' => $result['is_selling_warehouse'],
				'status'               => $result['status'],
				'edit'                 => $this->url->link('catalog/warehouse.form', 'user_token=' . $this->session->data['user_token'] . '&warehouse_id=' . $result['warehouse_id'])
			];
		}

		$data['user_token'] = $this->session->data['user_token'];

		return $this->load->view('catalog/warehouse_list', $data);
	}

	/**
	 * Form
	 *
	 * @return void
	 */
	public function form(): void {
		$this->load->language('catalog/warehouse');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['text_form'] = !isset($this->request->get['warehouse_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/warehouse', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('catalog/warehouse.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('catalog/warehouse', 'user_token=' . $this->session->data['user_token']);

		$this->load->model('catalog/warehouse');

		$warehouse_info = [];

		if (isset($this->request->get['warehouse_id'])) {
			$warehouse_info = $this->model_catalog_warehouse->getWarehouse((int)$this->request->get['warehouse_id']);
		}

		$data['warehouse_id'] = !empty($warehouse_info) ? $warehouse_info['warehouse_id'] : 0;
		$data['name'] = !empty($warehouse_info) ? $warehouse_info['name'] : '';
		$data['address'] = !empty($warehouse_info) ? $warehouse_info['address'] : '';
		$data['currency_code'] = !empty($warehouse_info) ? $warehouse_info['currency_code'] : $this->config->get('config_currency');
		$data['is_selling_warehouse'] = !empty($warehouse_info) ? (bool)$warehouse_info['is_selling_warehouse'] : false;
		$data['status'] = !empty($warehouse_info) ? (bool)$warehouse_info['status'] : true;
		$data['sort_order'] = !empty($warehouse_info) ? $warehouse_info['sort_order'] : 0;

		// Currency choices - reuses the store's own Localisation > Currency list,
		// so the admin fully controls which currencies exist and their exchange
		// rate to the store's default currency.
		$this->load->model('localisation/currency');

		$data['currencies'] = $this->model_localisation_currency->getCurrencies();

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/warehouse_form', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('catalog/warehouse');

		$json = [];

		if (!$this->user->hasPermission('modify', 'catalog/warehouse')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'warehouse_id' => 0,
			'name'         => '',
			'address'      => '',
			'currency_code' => ''
		];

		$post_info = $this->request->post + $required;

		if (!oc_validate_length($post_info['name'], 1, 128)) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		if (!$post_info['currency_code']) {
			$json['error']['currency_code'] = $this->language->get('error_currency');
		}

		if (!$json) {
			$this->load->model('catalog/warehouse');

			if (!$post_info['warehouse_id']) {
				$json['warehouse_id'] = $this->model_catalog_warehouse->addWarehouse($post_info);
			} else {
				$this->model_catalog_warehouse->editWarehouse((int)$post_info['warehouse_id'], $post_info);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Delete
	 *
	 * @return void
	 */
	public function delete(): void {
		$this->load->language('catalog/warehouse');

		$json = [];

		if (isset($this->request->post['selected'])) {
			$selected = (array)$this->request->post['selected'];
		} else {
			$selected = [];
		}

		if (!$this->user->hasPermission('modify', 'catalog/warehouse')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('catalog/warehouse');

			foreach ($selected as $warehouse_id) {
				$warehouse_info = $this->model_catalog_warehouse->getWarehouse((int)$warehouse_id);

				if ($warehouse_info && $warehouse_info['is_selling_warehouse']) {
					$json['error'] = $this->language->get('error_selling_warehouse');

					break;
				}
			}
		}

		if (!$json) {
			foreach ($selected as $warehouse_id) {
				$this->model_catalog_warehouse->deleteWarehouse((int)$warehouse_id);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
