<?php
namespace MDcart\Admin\Controller\Sale;
/**
 * Class Preorder
 *
 * "Sale > Pre-Orders" - a queue of every order line currently sold as a
 * pre-order / backorder, from either channel:
 *
 *  - POS: the cashier marked the line pre-order (any time, or after a stock
 *    warning) and picked a delivery date on the spot - using POS already
 *    represents admin approval, so this works for any product.
 *  - Storefront: only for products the admin explicitly enabled under
 *    Catalog > Products > (edit) > Data > "Allow Pre-Order When Out Of
 *    Stock"; the delivery date is auto-computed from that product's lead
 *    time at the moment of the order.
 *
 * From here an admin can review every pending one, correct/finalise its
 * delivery date (satisfies "or with admin approval, at a delivery time the
 * admin specifies" for the storefront case too, after the fact), or mark it
 * fulfilled once the stock has arrived and the customer has their item.
 *
 * Can be loaded using $this->load->controller('sale/preorder');
 *
 * @package MDcart\Admin\Controller\Sale
 */
class Preorder extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('sale/preorder');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('sale/preorder', 'user_token=' . $this->session->data['user_token'])
		];

		$this->load->model('sale/preorder');

		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = 20;

		$results = $this->model_sale_preorder->getPreorders(['start' => ($page - 1) * $limit, 'limit' => $limit]);
		$total = $this->model_sale_preorder->getTotalPreorders();

		$data['preorders'] = [];

		foreach ($results as $result) {
			$data['preorders'][] = [
				'order_product_id'       => $result['order_product_id'],
				'order_id'                => $result['order_id'],
				'name'                    => $result['name'],
				'quantity'                => $result['quantity'],
				'customer'                => trim($result['firstname'] . ' ' . $result['lastname']),
				'order_status'            => $result['order_status'] ?: $this->language->get('text_status_pending'),
				'date_added'              => $result['date_added'] ? date($this->language->get('date_format_short'), strtotime($result['date_added'])) : '',
				'preorder_delivery_date'  => $result['preorder_delivery_date'] ?: '',
				'view'                    => $this->url->link('sale/order.info', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $result['order_id'])
			];
		}

		$data['pages'] = [];

		$pages = (int)ceil($total / $limit);

		for ($i = 1; $i <= $pages; $i++) {
			$data['pages'][] = [
				'text' => (string)$i,
				'href' => $this->url->link('sale/preorder', 'user_token=' . $this->session->data['user_token'] . '&page=' . $i)
			];
		}

		$data['save'] = $this->url->link('sale/preorder.save', 'user_token=' . $this->session->data['user_token'], true);
		$data['fulfill'] = $this->url->link('sale/preorder.fulfill', 'user_token=' . $this->session->data['user_token'], true);

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('sale/preorder_list', $data));
	}

	/**
	 * Save Delivery Date (AJAX)
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('sale/preorder');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/preorder')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$order_product_id = (int)($this->request->post['order_product_id'] ?? 0);
		$delivery_date = (string)($this->request->post['preorder_delivery_date'] ?? '');

		if (!$json && !$order_product_id) {
			$json['error'] = $this->language->get('error_unknown');
		}

		if (!$json) {
			$this->load->model('sale/preorder');
			$this->model_sale_preorder->editDeliveryDate($order_product_id, $delivery_date);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Mark Fulfilled (AJAX)
	 *
	 * @return void
	 */
	public function fulfill(): void {
		$this->load->language('sale/preorder');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/preorder')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$order_product_id = (int)($this->request->post['order_product_id'] ?? 0);

		if (!$json && !$order_product_id) {
			$json['error'] = $this->language->get('error_unknown');
		}

		if (!$json) {
			$this->load->model('sale/preorder');
			$this->model_sale_preorder->markFulfilled($order_product_id);

			$json['success'] = $this->language->get('text_success_fulfilled');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
