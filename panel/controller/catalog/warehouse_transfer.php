<?php
namespace MDcart\Admin\Controller\Catalog;
/**
 * Class WarehouseTransfer
 *
 * Inter-warehouse transfer ("حواله") admin screens: list, add/edit, and a
 * status-change action that moves stock and (on receipt) applies landed
 * freight cost - see admin/model/catalog/warehouse_transfer.php for the
 * actual stock/price logic.
 *
 * Can be loaded using $this->load->controller('catalog/warehouse_transfer');
 *
 * @package MDcart\Admin\Controller\Catalog
 */
class WarehouseTransfer extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('catalog/warehouse_transfer');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/warehouse_transfer', 'user_token=' . $this->session->data['user_token'])
		];

		$data['add'] = $this->url->link('catalog/warehouse_transfer.form', 'user_token=' . $this->session->data['user_token']);

		$data['list'] = $this->load->controller('catalog/warehouse_transfer.getList');

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/warehouse_transfer', $data));
	}

	/**
	 * List
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('catalog/warehouse_transfer');

		$this->response->setOutput($this->load->controller('catalog/warehouse_transfer.getList'));
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	public function getList(): string {
		$this->load->model('catalog/warehouse_transfer');

		$results = $this->model_catalog_warehouse_transfer->getTransfers();

		$data['transfers'] = [];

		foreach ($results as $result) {
			$data['transfers'][] = [
				'transfer_id'             => $result['transfer_id'],
				'from_warehouse_name'     => $result['from_warehouse_name'],
				'to_warehouse_name'       => $result['to_warehouse_name'],
				'status'                  => $result['status'],
				'status_text'             => $this->language->get('text_status_' . $result['status']),
				'estimated_delivery_date' => $result['estimated_delivery_date'],
				'date_added'              => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'edit'                    => $this->url->link('catalog/warehouse_transfer.form', 'user_token=' . $this->session->data['user_token'] . '&transfer_id=' . $result['transfer_id'])
			];
		}

		return $this->load->view('catalog/warehouse_transfer_list', $data);
	}

	/**
	 * Form
	 *
	 * @return void
	 */
	public function form(): void {
		$this->load->language('catalog/warehouse_transfer');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['text_form'] = !isset($this->request->get['transfer_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/warehouse_transfer', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('catalog/warehouse_transfer.save', 'user_token=' . $this->session->data['user_token']);
		$data['status_save'] = $this->url->link('catalog/warehouse_transfer.status', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('catalog/warehouse_transfer', 'user_token=' . $this->session->data['user_token']);
		$data['autocomplete'] = $this->url->link('catalog/product.autocomplete', 'user_token=' . $this->session->data['user_token'], true);

		$this->load->model('catalog/warehouse_transfer');
		$this->load->model('catalog/warehouse');

		$transfer_info = [];

		if (isset($this->request->get['transfer_id'])) {
			$transfer_info = $this->model_catalog_warehouse_transfer->getTransfer((int)$this->request->get['transfer_id']);
		}

		$data['transfer_id'] = !empty($transfer_info) ? $transfer_info['transfer_id'] : 0;
		$data['from_warehouse_id'] = !empty($transfer_info) ? $transfer_info['from_warehouse_id'] : '';
		$data['to_warehouse_id'] = !empty($transfer_info) ? $transfer_info['to_warehouse_id'] : '';
		$data['shipping_cost'] = !empty($transfer_info) ? $transfer_info['shipping_cost'] : '0.0000';
		$data['estimated_delivery_date'] = !empty($transfer_info) ? $transfer_info['estimated_delivery_date'] : '';
		$data['actual_delivery_date'] = !empty($transfer_info) ? $transfer_info['actual_delivery_date'] : '';
		$data['comment'] = !empty($transfer_info) ? $transfer_info['comment'] : '';
		$data['status'] = !empty($transfer_info) ? $transfer_info['status'] : 'pending';
		$data['editable'] = empty($transfer_info) || $transfer_info['status'] === 'pending';

		// "Sellable while in transit" (task #13): unlike from/to warehouse and
		// the product lines, this can still be toggled once a transfer is
		// in_transit (that's realistically when an admin decides to open it
		// up for online pre-sale) - only pending/in_transit make sense, since
		// received/cancelled transfers no longer represent incoming stock.
		$data['presell_online'] = !empty($transfer_info) ? (bool)$transfer_info['presell_online'] : false;
		$data['presell_editable'] = empty($transfer_info) || in_array($transfer_info['status'], ['pending', 'in_transit']);
		$data['presell_only_matters_for_selling_warehouse'] = !empty($transfer_info) && empty($transfer_info['to_is_selling_warehouse']);

		$data['currency_symbol'] = $this->config->get('config_currency');

		$data['warehouses'] = $this->model_catalog_warehouse->getWarehouses();

		$data['products'] = [];

		if (!empty($transfer_info)) {
			foreach ($this->model_catalog_warehouse_transfer->getTransferProducts($transfer_info['transfer_id']) as $product) {
				$data['products'][] = [
					'product_id'        => $product['product_id'],
					'name'              => $product['name'],
					'quantity'          => $product['quantity'],
					'reserved_quantity' => (int)($product['reserved_quantity'] ?? 0)
				];
			}
		}

		$data['statuses'] = ['pending', 'in_transit', 'received', 'cancelled'];

		$data['histories'] = [];

		if (!empty($transfer_info)) {
			foreach ($this->model_catalog_warehouse_transfer->getTransferHistories($transfer_info['transfer_id']) as $history) {
				$data['histories'][] = [
					'status_text' => $this->language->get('text_status_' . $history['status']),
					'comment'     => $history['comment'],
					'date_added'  => date($this->language->get('date_format_short') . ' H:i', strtotime($history['date_added']))
				];
			}
		}

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/warehouse_transfer_form', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('catalog/warehouse_transfer');

		$json = [];

		if (!$this->user->hasPermission('modify', 'catalog/warehouse_transfer')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'transfer_id'       => 0,
			'from_warehouse_id' => 0,
			'to_warehouse_id'   => 0,
			'presell_online'    => 0,
			'products'          => []
		];

		$post_info = $this->request->post + $required;

		// The warehouse/product lines lock in the UI (disabled selects don't
		// submit at all) once a transfer leaves "pending", so only enforce
		// and use them for a brand new transfer or one still pending -
		// editing a transfer past that point only touches ETA/comment.
		$this->load->model('catalog/warehouse_transfer');

		$existing_transfer = $post_info['transfer_id'] ? $this->model_catalog_warehouse_transfer->getTransfer((int)$post_info['transfer_id']) : [];
		$locked = !empty($existing_transfer) && $existing_transfer['status'] !== 'pending';

		if (!$locked) {
			if (!$post_info['from_warehouse_id'] || !$post_info['to_warehouse_id']) {
				$json['error']['warning'] = $this->language->get('error_warehouse');
			} elseif ((int)$post_info['from_warehouse_id'] === (int)$post_info['to_warehouse_id']) {
				$json['error']['warning'] = $this->language->get('error_same_warehouse');
			}

			if (!$post_info['products']) {
				$json['error']['warning'] = $this->language->get('error_products');
			}
		}

		if (!$json) {
			if (!$post_info['transfer_id']) {
				$post_info['user_id'] = $this->user->getId();
				$json['transfer_id'] = $this->model_catalog_warehouse_transfer->addTransfer($post_info);
			} elseif ($locked) {
				// Only ETA/comment can change once a transfer is past pending.
				$post_info['from_warehouse_id'] = $existing_transfer['from_warehouse_id'];
				$post_info['to_warehouse_id'] = $existing_transfer['to_warehouse_id'];
				$post_info['shipping_cost'] = $existing_transfer['shipping_cost'];
				$this->model_catalog_warehouse_transfer->editTransfer((int)$post_info['transfer_id'], $post_info);
			} else {
				$this->model_catalog_warehouse_transfer->editTransfer((int)$post_info['transfer_id'], $post_info);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Status (AJAX) - change a transfer's status, with optional manual date
	 * correction, at any time (even "backwards", per business decision).
	 *
	 * @return void
	 */
	public function status(): void {
		$this->load->language('catalog/warehouse_transfer');

		$json = [];

		if (!$this->user->hasPermission('modify', 'catalog/warehouse_transfer')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$transfer_id = (int)($this->request->post['transfer_id'] ?? 0);
		$status = (string)($this->request->post['status'] ?? '');
		$comment = (string)($this->request->post['comment'] ?? '');

		if (!in_array($status, ['pending', 'in_transit', 'received', 'cancelled'], true)) {
			$json['error'] = $this->language->get('error_status');
		}

		if (!$json) {
			$this->load->model('catalog/warehouse_transfer');

			$dates = [];

			if (isset($this->request->post['estimated_delivery_date'])) {
				$dates['estimated_delivery_date'] = (string)$this->request->post['estimated_delivery_date'];
			}

			if (isset($this->request->post['actual_delivery_date'])) {
				$dates['actual_delivery_date'] = (string)$this->request->post['actual_delivery_date'];
			}

			try {
				$this->model_catalog_warehouse_transfer->changeStatus($transfer_id, $status, $comment, $dates);

				$json['success'] = $this->language->get('text_success');
			} catch (\Throwable $e) {
				$json['error'] = $e->getMessage();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
