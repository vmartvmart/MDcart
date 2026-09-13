<?php
namespace MDcart\Admin\Controller\Accounting;
/**
 * Class Supplier
 *
 * Admin CRUD for suppliers ("طرف حساب" on a Purchase Invoice). Each row also
 * shows the supplier's current outstanding balance (see
 * Supplier::getSupplierBalance()) so it's immediately clear how much is
 * owed to them, without suppliers needing their own row in the chart of
 * accounts - see the model's docblock for why.
 *
 * Can be loaded using $this->load->controller('accounting/supplier');
 *
 * @package MDcart\Admin\Controller\Accounting
 */
class Supplier extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('accounting/supplier');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('accounting/supplier', 'user_token=' . $this->session->data['user_token'])
		];

		$data['add'] = $this->url->link('accounting/supplier.form', 'user_token=' . $this->session->data['user_token']);
		$data['delete'] = $this->url->link('accounting/supplier.delete', 'user_token=' . $this->session->data['user_token']);

		$data['list'] = $this->getList();

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('accounting/supplier', $data));
	}

	/**
	 * List
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('accounting/supplier');

		$this->response->setOutput($this->getList());
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	private function getList(): string {
		$data['action'] = $this->url->link('accounting/supplier.list', 'user_token=' . $this->session->data['user_token']);

		$this->load->model('accounting/supplier');

		$results = $this->model_accounting_supplier->getSuppliers();

		$data['suppliers'] = [];

		foreach ($results as $result) {
			$data['suppliers'][] = [
				'supplier_id'   => $result['supplier_id'],
				'name'          => $result['name'],
				'telephone'     => $result['telephone'],
				'currency_code' => $result['currency_code'],
				'balance'       => number_format($this->model_accounting_supplier->getSupplierBalance((int)$result['supplier_id']), 2),
				'status'        => $result['status'],
				'edit'          => $this->url->link('accounting/supplier.form', 'user_token=' . $this->session->data['user_token'] . '&supplier_id=' . $result['supplier_id'])
			];
		}

		$data['user_token'] = $this->session->data['user_token'];

		return $this->load->view('accounting/supplier_list', $data);
	}

	/**
	 * Form
	 *
	 * @return void
	 */
	public function form(): void {
		$this->load->language('accounting/supplier');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['text_form'] = !isset($this->request->get['supplier_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('accounting/supplier', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('accounting/supplier.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('accounting/supplier', 'user_token=' . $this->session->data['user_token']);

		$this->load->model('accounting/supplier');

		$supplier_info = [];

		if (isset($this->request->get['supplier_id'])) {
			$supplier_info = $this->model_accounting_supplier->getSupplier((int)$this->request->get['supplier_id']);
		}

		$data['supplier_id'] = !empty($supplier_info) ? $supplier_info['supplier_id'] : 0;
		$data['name'] = !empty($supplier_info) ? $supplier_info['name'] : '';
		$data['telephone'] = !empty($supplier_info) ? $supplier_info['telephone'] : '';
		$data['email'] = !empty($supplier_info) ? $supplier_info['email'] : '';
		$data['address'] = !empty($supplier_info) ? $supplier_info['address'] : '';
		$data['tax_id'] = !empty($supplier_info) ? $supplier_info['tax_id'] : '';
		$data['currency_code'] = !empty($supplier_info) ? $supplier_info['currency_code'] : $this->config->get('config_currency');
		$data['status'] = !empty($supplier_info) ? (bool)$supplier_info['status'] : true;
		$data['sort_order'] = !empty($supplier_info) ? $supplier_info['sort_order'] : 0;

		$this->load->model('localisation/currency');

		$data['currencies'] = $this->model_localisation_currency->getCurrencies();

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('accounting/supplier_form', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('accounting/supplier');

		$json = [];

		if (!$this->user->hasPermission('modify', 'accounting/supplier')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'supplier_id'   => 0,
			'name'          => '',
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
			$this->load->model('accounting/supplier');

			if (!$post_info['supplier_id']) {
				$json['supplier_id'] = $this->model_accounting_supplier->addSupplier($post_info);
			} else {
				$this->model_accounting_supplier->editSupplier((int)$post_info['supplier_id'], $post_info);
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
		$this->load->language('accounting/supplier');

		$json = [];

		if (isset($this->request->post['selected'])) {
			$selected = (array)$this->request->post['selected'];
		} else {
			$selected = [];
		}

		if (!$this->user->hasPermission('modify', 'accounting/supplier')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$this->load->model('accounting/supplier');

		if (!$json) {
			foreach ($selected as $supplier_id) {
				if ($this->model_accounting_supplier->hasPurchaseInvoices((int)$supplier_id)) {
					$json['error'] = $this->language->get('error_has_purchase_invoices');

					break;
				}
			}
		}

		if (!$json) {
			foreach ($selected as $supplier_id) {
				$this->model_accounting_supplier->deleteSupplier((int)$supplier_id);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
