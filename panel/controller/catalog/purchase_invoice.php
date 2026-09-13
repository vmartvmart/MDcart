<?php
namespace MDcart\Admin\Controller\Catalog;
/**
 * Class PurchaseInvoice
 *
 * Admin screens for purchase invoices ("فاکتور خرید"): list, add (the only
 * way in - invoices are append-only), and a read-only view of a saved one.
 * Saving immediately increases the chosen warehouse's stock and, when the
 * accounting module is installed, posts a balanced journal entry - see
 * admin/model/catalog/purchase_invoice.php. A credit/combined invoice can
 * later be settled (partially or fully) from its view screen via the
 * pay() action, which posts a second journal entry and records the
 * payment in oc_purchase_invoice_payment.
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
			$outstanding = (float)$result['total_amount'] - (float)$result['paid_amount'];

			$data['purchase_invoices'][] = [
				'purchase_invoice_id' => $result['purchase_invoice_id'],
				'supplier_name'       => $result['supplier_name'],
				'invoice_number'      => $result['invoice_number'],
				'warehouse_name'      => $result['warehouse_name'],
				'invoice_date'        => $result['invoice_date'],
				'payment_type'        => $result['payment_type'],
				'outstanding'         => $outstanding > 0.0001 ? $this->currency->format($outstanding, $result['currency_code'] ?: $this->config->get('config_currency')) : '',
				'date_added'          => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'view'                => $this->url->link('catalog/purchase_invoice.form', 'user_token=' . $this->session->data['user_token'] . '&purchase_invoice_id=' . $result['purchase_invoice_id'])
			];
		}

		return $this->load->view('catalog/purchase_invoice_list', $data);
	}

	/**
	 * Form - doubles as the read-only view once a purchase_invoice_id is set,
	 * since invoices are append-only (only settling a payment is still
	 * possible from this screen, via the pay() action below).
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
		$data['pay'] = $this->url->link('catalog/purchase_invoice.pay', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('catalog/purchase_invoice', 'user_token=' . $this->session->data['user_token']);
		$data['autocomplete'] = $this->url->link('catalog/product.autocomplete', 'user_token=' . $this->session->data['user_token'], true);

		$this->load->model('catalog/warehouse');
		$this->load->model('accounting/supplier');
		$this->load->model('accounting/bank_account');

		$invoice_info = [];

		if (isset($this->request->get['purchase_invoice_id'])) {
			$this->load->model('catalog/purchase_invoice');

			$invoice_info = $this->model_catalog_purchase_invoice->getPurchaseInvoice((int)$this->request->get['purchase_invoice_id']);
		}

		$data['text_form'] = !empty($invoice_info) ? $this->language->get('text_view') : $this->language->get('text_add');
		$data['readonly'] = !empty($invoice_info);
		$data['purchase_invoice_id'] = !empty($invoice_info) ? $invoice_info['purchase_invoice_id'] : 0;
		$data['warehouse_id'] = !empty($invoice_info) ? $invoice_info['warehouse_id'] : '';
		$data['supplier_id'] = !empty($invoice_info) ? $invoice_info['supplier_id'] : '';
		$data['supplier_name'] = !empty($invoice_info) ? $invoice_info['supplier_name'] : '';
		$data['invoice_number'] = !empty($invoice_info) ? $invoice_info['invoice_number'] : '';
		$data['invoice_date'] = !empty($invoice_info) ? $invoice_info['invoice_date'] : date('Y-m-d');
		$data['comment'] = !empty($invoice_info) ? $invoice_info['comment'] : '';
		$data['payment_type'] = !empty($invoice_info) ? $invoice_info['payment_type'] : 'credit';
		$data['bank_account_id'] = !empty($invoice_info) ? $invoice_info['bank_account_id'] : '';
		$data['currency_code'] = !empty($invoice_info) ? $invoice_info['currency_code'] : '';

		$total_amount = !empty($invoice_info) ? (float)$invoice_info['total_amount'] : 0.0;
		$paid_amount = !empty($invoice_info) ? (float)$invoice_info['paid_amount'] : 0.0;
		$outstanding = round($total_amount - $paid_amount, 4);

		$data['total_amount'] = $total_amount;
		$data['paid_amount'] = $paid_amount;
		$data['outstanding'] = $outstanding;
		$data['outstanding_formatted'] = !empty($invoice_info) ? $this->currency->format($outstanding, $invoice_info['currency_code'] ?: $this->config->get('config_currency')) : '';

		$data['warehouses'] = $this->model_catalog_warehouse->getWarehouses();
		$data['suppliers'] = $this->model_accounting_supplier->getSuppliers();

		$data['bank_accounts'] = [];

		foreach ($this->model_accounting_bank_account->getBankAccounts() as $bank_account) {
			if ($bank_account['status']) {
				$data['bank_accounts'][] = $bank_account;
			}
		}

		$data['can_pay'] = !empty($invoice_info) && $outstanding > 0.0001 && $this->user->hasPermission('modify', 'catalog/purchase_invoice');

		$data['payments'] = [];

		if (!empty($invoice_info)) {
			$this->load->model('catalog/purchase_invoice');

			foreach ($this->model_catalog_purchase_invoice->getPurchaseInvoicePayments($invoice_info['purchase_invoice_id']) as $payment) {
				$data['payments'][] = [
					'amount'            => $this->currency->format((float)$payment['amount'], $invoice_info['currency_code'] ?: $this->config->get('config_currency')),
					'bank_account_name' => $payment['bank_account_name'],
					'date_added'        => date($this->language->get('date_format_short'), strtotime($payment['date_added']))
				];
			}
		}

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
			'warehouse_id'  => 0,
			'products'      => [],
			'payment_type'  => 'credit',
			'bank_account_id' => 0,
			'paid_amount'   => 0
		];

		$post_info = $this->request->post + $required;

		if (!$post_info['warehouse_id']) {
			$json['error']['warning'] = $this->language->get('error_warehouse');
		}

		if (!$post_info['products']) {
			$json['error']['warning'] = $this->language->get('error_products');
		}

		if (!in_array($post_info['payment_type'], ['cash', 'bank', 'credit', 'combined'], true)) {
			$json['error']['warning'] = $this->language->get('error_payment_type');
		}

		if (in_array($post_info['payment_type'], ['cash', 'bank', 'combined'], true) && !$post_info['bank_account_id']) {
			$json['error']['warning'] = $this->language->get('error_bank_account');
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

	/**
	 * Pay - settles part or all of a credit/combined invoice's outstanding
	 * balance from its view screen.
	 *
	 * @return void
	 */
	public function pay(): void {
		$this->load->language('catalog/purchase_invoice');

		$json = [];

		if (!$this->user->hasPermission('modify', 'catalog/purchase_invoice')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'purchase_invoice_id' => 0,
			'bank_account_id'     => 0,
			'amount'              => 0
		];

		$post_info = $this->request->post + $required;

		if (!$post_info['purchase_invoice_id']) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (!$post_info['bank_account_id']) {
			$json['error']['warning'] = $this->language->get('error_bank_account');
		}

		if ((float)$post_info['amount'] <= 0) {
			$json['error']['warning'] = $this->language->get('error_amount');
		}

		if (!$json) {
			$this->load->model('catalog/purchase_invoice');

			$post_info['user_id'] = $this->user->getId();

			$purchase_invoice_payment_id = $this->model_catalog_purchase_invoice->addPurchaseInvoicePayment($post_info);

			if ($purchase_invoice_payment_id) {
				$json['success'] = $this->language->get('text_payment_success');
			} else {
				$json['error']['warning'] = $this->language->get('error_amount');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
