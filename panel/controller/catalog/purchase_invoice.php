<?php
namespace MDcart\Admin\Controller\Catalog;
/**
 * Class PurchaseInvoice
 *
 * Admin screens for purchase invoices ("فاکتور خرید"): list, add (the only
 * way in - invoices are append-only), and a read-only view of a saved one.
 * Saving immediately increases the chosen warehouse's stock - see
 * admin/model/catalog/purchase_invoice.php.
 *
 * Can be loaded using $this->load->controller('catalog/purchase_invoice');
 *
 * @package MDcart\Admin\Controller\Catalog
 */
class PurchaseInvoice extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('catalog/purchase_invoice');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/purchase_invoice', 'user_token=' . $this->session->data['user_token'])
		];

		$data['add'] = $this->url->link('catalog/purchase_invoice.form', 'user_token=' . $this->session->data['user_token']);

		$data['list'] = $this->load->controller('catalog/purchase_invoice.getList');

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/purchase_invoice', $data));
	}

	/**
	 * List
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('catalog/purchase_invoice');

		$this->response->setOutput($this->load->controller('catalog/purchase_invoice.getList'));
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	public function getList(): string {
		$this->load->model('catalog/purchase_invoice');

		$results = $this->model_catalog_purchase_invoice->getPurchaseInvoices();

		$data['purchase_invoices'] = [];

		foreach ($results as $result) {
			$data['purchase_invoices'][] = [
				'purchase_invoice_id' => $result['purchase_invoice_id'],
				'supplier_name'       => $result['supplier_name'],
				'invoice_number'      => $result['invoice_number'],
				'warehouse_name'      => $result['warehouse_name'],
				'invoice_date'        => $result['invoice_date'],
				'date_added'          => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'view'                => $this->url->link('catalog/purchase_invoice.form', 'user_token=' . $this->session->data['user_token'] . '&purchase_invoice_id=' . $result['purchase_invoice_id'])
			];
		}

		return $this->load->view('catalog/purchase_invoice_list', $data);
	}

	/**
	 * Form - doubles as the read-only view once a purchase_invoice_id is set,
	 * since invoices are append-only.
	 *
	 * @return void
	 */
	public function form(): void {
		$this->load->language('catalog/purchase_invoice');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/purchase_invoice', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('catalog/purchase_invoice.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('catalog/purchase_invoice', 'user_token=' . $this->session->data['user_token']);
		$data['autocomplete'] = $this->url->link('catalog/product.autocomplete', 'user_token=' . $this->session->data['user_token'], true);

		$this->load->model('catalog/warehouse');

		$invoice_info = [];

		if (isset($this->request->get['purchase_invoice_id'])) {
			$this->load->model('catalog/purchase_invoice');

			$invoice_info = $this->model_catalog_purchase_invoice->getPurchaseInvoice((int)$this->request->get['purchase_invoice_id']);
		}

		$data['text_form'] = !empty($invoice_info) ? $this->language->get('text_view') : $this->language->get('text_add');
		$data['readonly'] = !empty($invoice_info);
		$data['purchase_invoice_id'] = !empty($invoice_info) ? $invoice_info['purchase_invoice_id'] : 0;
		$data['warehouse_id'] = !empty($invoice_info) ? $invoice_info['warehouse_id'] : '';
		$data['supplier_name'] = !empty($invoice_info) ? $invoice_info['supplier_name'] : '';
		$data['invoice_number'] = !empty($invoice_info) ? $invoice_info['invoice_number'] : '';
		$data['invoice_date'] = !empty($invoice_info) ? $invoice_info['invoice_date'] : date('Y-m-d');
		$data['comment'] = !empty($invoice_info) ? $invoice_info['comment'] : '';

		$data['warehouses'] = $this->model_catalog_warehouse->getWarehouses();

		$data['products'] = [];

		if (!empty($invoice_info)) {
			foreach ($this->model_catalog_purchase_invoice->getPurchaseInvoiceProducts($invoice_info['purchase_invoice_id']) as $product) {
				$data['products'][] = [
					'product_id' => $product['product_id'],
					'name'       => $product['name'],
					'quantity'   => $product['quantity'],
					'unit_cost'  => $product['unit_cost'],
					'barcode'    => $product['barcode']
				];
			}
		}

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/purchase_invoice_form', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('catalog/purchase_invoice');

		$json = [];

		if (!$this->user->hasPermission('modify', 'catalog/purchase_invoice')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'warehouse_id' => 0,
			'products'     => []
		];

		$post_info = $this->request->post + $required;

		if (!$post_info['warehouse_id']) {
			$json['error']['warning'] = $this->language->get('error_warehouse');
		}

		if (!$post_info['products']) {
			$json['error']['warning'] = $this->language->get('error_products');
		}

		if (!$json) {
			$this->load->model('catalog/purchase_invoice');

			$post_info['user_id'] = $this->user->getId();

			$json['purchase_invoice_id'] = $this->model_catalog_purchase_invoice->addPurchaseInvoice($post_info);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
