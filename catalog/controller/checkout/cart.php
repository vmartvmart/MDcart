<?php
namespace MDcart\Catalog\Controller\Checkout;
/**
 * Class Cart
 *
 * Can be loaded using $this->load->controller('checkout/cart');
 *
 * @package MDcart\Catalog\Controller\Checkout
 */
class Cart extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('checkout/cart');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'))
		];

		$data['list'] = $this->load->controller('checkout/cart.getList');

		$data['language'] = $this->config->get('config_language');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('checkout/cart', $data));
	}

	/**
	 * List
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('checkout/cart');

		$this->response->setOutput($this->getList());
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	public function getList(): string {
		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];

			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		if (!$this->cart->hasStock() && (!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning'))) {
			$data['error_stock'] = $this->language->get('error_stock');
		} else {
			$data['error_stock'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if ($this->config->get('config_customer_price') && !$this->customer->isLogged()) {
			$data['attention'] = sprintf($this->language->get('text_login'), $this->url->link('account/login', 'language=' . $this->config->get('config_language')), $this->url->link('account/register', 'language=' . $this->config->get('config_language')));
		} else {
			$data['attention'] = '';
		}

		if ($this->config->get('config_cart_weight')) {
			$data['weight'] = $this->weight->format($this->cart->getWeight(), $this->config->get('config_weight_class_id'), $this->language->get('decimal_point'), $this->language->get('thousand_point'));
		} else {
			$data['weight'] = '';
		}

		$data['edit'] = $this->url->link('checkout/cart.edit', 'language=' . $this->config->get('config_language'));

		// Display prices
		if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
			$price_status = true;
		} else {
			$price_status = false;
		}

		// Image
		$this->load->model('tool/image');

		// Upload
		$this->load->model('tool/upload');

		// Cart
		$data['products'] = [];

		$this->load->model('checkout/cart');

		$products = $this->model_checkout_cart->getProducts();

		foreach ($products as $product) {
			if ($product['option']) {
				foreach ($product['option'] as $key => $option) {
					if ($option['type'] != 'file') {
						$value = $option['value'];
					} else {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					}

					$product['option'][$key]['value'] = (oc_strlen($value) > 20 ? oc_substr($value, 0, 20) . '..' : $value);
				}
			}

			$subscription = '';

			if ($product['subscription'] && $price_status) {
				if ($product['subscription']['trial_status']) {
					$subscription .= sprintf($this->language->get('text_subscription_trial'), $product['subscription']['trial_price_text'], $product['subscription']['trial_cycle'], $product['subscription']['trial_frequency'], $product['subscription']['trial_duration']);
				}

				if ($product['subscription']['duration']) {
					$subscription .= sprintf($this->language->get('text_subscription_duration'), $product['subscription']['price_text'], $product['subscription']['cycle'], $product['subscription']['frequency'], $product['subscription']['duration']);
				} else {
					$subscription .= sprintf($this->language->get('text_subscription_cancel'), $product['subscription']['price_text'], $product['subscription']['cycle'], $product['subscription']['frequency']);
				}
			}

			// Delivery-timing label for split-off pre-order/transit lines (see
			// checkout/cart.php's add(), which can split a single request
			// into a normal line plus a separate delayed-delivery line for
			// whatever exceeded current stock).
			if (!empty($product['is_transit_order']) && !empty($product['transit_delivery_date'])) {
				$delivery_date = new \DateTime($product['transit_delivery_date']);

				$delivery_label = sprintf($this->language->get('text_transit_line'), $delivery_date->format($this->language->get('date_format_short')));
			} elseif (!empty($product['is_preorder']) && !empty($product['preorder_delivery_date'])) {
				$delivery_date = new \DateTime($product['preorder_delivery_date']);

				$delivery_label = sprintf($this->language->get('text_preorder_line'), $delivery_date->format($this->language->get('date_format_short')));
			} else {
				$delivery_label = '';
			}

			$data['products'][] = [
				'thumb'          => $this->model_tool_image->resize($product['image'], $this->config->get('config_image_cart_width'), $this->config->get('config_image_cart_height')),
				'subscription'   => $subscription,
				'stock'          => $product['stock_status'] ? true : !(!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning')),
				// Raw available-stock number, separate from the boolean
				// 'stock' above - used as the quantity stepper's "max".
				'stock_quantity' => (int)$product['stock'],
				'delivery_label' => $delivery_label,
				'minimum'        => !$product['minimum_status'] ? sprintf($this->language->get('error_minimum'), $product['minimum']) : 0,
				'price'          => $price_status ? $product['price_text'] : '',
				'total'          => $price_status ? $product['total_text'] : '',
				'href'           => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product['product_id']),
				'remove'         => $this->url->link('checkout/cart.remove', 'language=' . $this->config->get('config_language') . '&key=' . $product['cart_id'])
			] + $product;
		}

		$data['totals'] = [];

		$totals = [];
		$taxes = $this->cart->getTaxes();
		$total = 0;

		// Display prices
		if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
			($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

			foreach ($totals as $result) {
				$data['totals'][] = ['text' => $price_status ? $this->currency->format($result['value'], $this->session->data['currency']) : ''] + $result;
			}
		}

		$data['modules'] = [];

		// Extensions
		$this->load->model('setting/extension');

		$extensions = $this->model_setting_extension->getExtensionsByType('total');

		foreach ($extensions as $extension) {
			$result = $this->load->controller('extension/' . $extension['extension'] . '/checkout/' . $extension['code']);

			if (!$result instanceof \Exception) {
				$data['modules'][] = $result;
			}
		}

		if ($products) {
			$data['continue'] = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));
			$data['checkout'] = $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'));
		} else {
			$data['continue'] = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));
		}

		return $this->load->view('checkout/cart_list', $data);
	}

	/**
	 * Add
	 *
	 * @return void
	 */
	public function add(): void {
		$this->load->language('checkout/cart');

		$json = [];

		if (isset($this->request->post['product_id'])) {
			$product_id = (int)$this->request->post['product_id'];
		} else {
			$product_id = 0;
		}

		if (isset($this->request->post['quantity'])) {
			$quantity = (int)$this->request->post['quantity'];
		} else {
			$quantity = 1;
		}

		if (isset($this->request->post['option'])) {
			$option = array_filter((array)$this->request->post['option']);
		} else {
			$option = [];
		}

		if (isset($this->request->post['subscription_plan_id'])) {
			$subscription_plan_id = (int)$this->request->post['subscription_plan_id'];
		} else {
			$subscription_plan_id = 0;
		}

		// Product
		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if ($product_info) {
			// If variant get master product
			if ($product_info['master_id']) {
				$product_id = $product_info['master_id'];
			}

			// Only use values in the override
			if (isset($product_info['override']['variant'])) {
				$override = $product_info['override']['variant'];
			} else {
				$override = [];
			}

			// Merge variant code with options
			foreach ($product_info['variant'] as $key => $value) {
				if (array_key_exists($key, $override)) {
					$option[$key] = $value;
				}
			}

			// Validate options
			$product_options = $this->model_catalog_product->getOptions($product_id);

			foreach ($product_options as $product_option) {
				if ($product_option['required'] && empty($option[$product_option['product_option_id']])) {
					$json['error']['option_' . $product_option['product_option_id']] = sprintf($this->language->get('error_required'), $product_option['name']);
				} elseif (($product_option['type'] == 'text') && !empty($product_option['validation']) && !oc_validate_regex($option[$product_option['product_option_id']], $product_option['validation'])) {
					$json['error']['option_' . $product_option['product_option_id']] = sprintf($this->language->get('error_regex'), $product_option['name']);
				}
			}

			// Validate subscription products
			$subscriptions = $this->model_catalog_product->getSubscriptions($product_info['product_id']);

			if ($subscriptions && (!$subscription_plan_id || !in_array($subscription_plan_id, array_column($subscriptions, 'subscription_plan_id')))) {
				$json['error']['subscription'] = $this->language->get('error_subscription');
			}

			// Admin-configurable per-customer order limit (Catalog > Products
			// > Data > "Max Quantity Per Customer"). 0 = unlimited, the
			// default, so this is a no-op for every product that hasn't
			// opted in. Counts this customer's own already-CONFIRMED orders
			// (order_status_id > 0 - an abandoned/incomplete checkout never
			// reached that state, so it doesn't count against the limit)
			// plus whatever of this product is already sitting in their
			// cart, so repeatedly adding a few at a time can't bypass it.
			// A guest (not logged in) has no trackable order history, so
			// only their current cart is checked for them.
			if (!empty($product_info['max_customer_quantity'])) {
				$limit = (int)$product_info['max_customer_quantity'];

				$already_ordered = 0;

				if ($this->customer->isLogged()) {
					$order_query = $this->db->query(
						"SELECT COALESCE(SUM(`op`.`quantity`), 0) AS `total` FROM `" . DB_PREFIX . "order_product` `op`"
						. " LEFT JOIN `" . DB_PREFIX . "order` `o` ON (`o`.`order_id` = `op`.`order_id`)"
						. " WHERE `o`.`customer_id` = '" . (int)$this->customer->getId() . "' AND `op`.`product_id` = '" . (int)$product_info['product_id'] . "' AND `o`.`order_status_id` > '0'"
					);

					$already_ordered = (int)$order_query->row['total'];
				}

				$already_in_cart = 0;

				foreach ($this->cart->getProducts() as $cart_product) {
					if ($cart_product['product_id'] == $product_info['product_id']) {
						$already_in_cart += $cart_product['quantity'];
					}
				}

				$remaining = max(0, $limit - $already_ordered - $already_in_cart);

				if ($quantity > $remaining) {
					$json['error']['warning'] = sprintf($this->language->get('error_customer_quantity'), $limit, $remaining);
				}
			}
		} else {
			$json['error']['warning'] = $this->language->get('error_product');
		}

		if (!$json) {
			// Sellable while in transit (task #13) / Pre-order-backorder: only
			// ever offered client-side when the product is actually out of
			// stock - re-checked here rather than trusted from the client.
			//
			// When only *some* of the requested quantity is covered by
			// current stock, the request is split into two cart lines
			// instead of tagging the whole quantity as delayed: up to
			// $available units go in as a normal line (ships now), and only
			// the actual shortfall ($overage_quantity) becomes a separate
			// pre-order/transit line with its own delivery date. This way a
			// customer asking for more than is on hand can still see - and
			// pay for/receive - the part that's genuinely available right
			// away. Cart::add() keeps these as two distinct rows because it
			// now also matches on `override` (see its docblock).
			$available = max(0, (int)$product_info['quantity']);
			$normal_quantity = min($quantity, $available);
			$overage_quantity = $quantity - $normal_quantity;

			$want_transit = !empty($this->request->post['transit']);
			$want_preorder = !empty($this->request->post['preorder']);

			$override = [];

			if ($overage_quantity > 0 && $want_transit) {
				$transit_options = $this->model_catalog_product->getTransitAvailability($product_info['product_id']);

				// Each order line allocates against exactly ONE transfer (see
				// Model\Catalog\Product::getTransitAvailability() docblock) -
				// find the soonest-arriving transfer that alone has enough
				// spare quantity for the overage (the part not covered by
				// current stock).
				$matched_transfer = null;

				foreach ($transit_options as $option) {
					if ($option['available'] >= $overage_quantity) {
						$matched_transfer = $option;
						break;
					}
				}

				if ($matched_transfer) {
					// Force stock_status true so Cart::hasStock() (which
					// otherwise blocks checkout confirmation) treats this
					// line as fine.
					$override['stock_status'] = true;
					$override['is_transit_order'] = 1;
					$override['transit_transfer_id'] = $matched_transfer['transfer_id'];
					$override['transit_delivery_date'] = $matched_transfer['delivery_date'];
				}
			}

			if ($overage_quantity > 0 && empty($override) && $want_preorder && !empty($product_info['preorder_status'])) {
				$preorder_lead_days = !empty($product_info['preorder_lead_days']) ? (int)$product_info['preorder_lead_days'] : 7;

				// Force stock_status true so Cart::hasStock() (which otherwise
				// blocks checkout confirmation) treats this line as fine.
				$override['stock_status'] = true;
				$override['is_preorder'] = 1;
				$override['preorder_delivery_date'] = date('Y-m-d', strtotime('+' . $preorder_lead_days . ' days'));
			}

			if ($overage_quantity > 0 && empty($override)) {
				// Neither transit nor pre-order could cover the shortfall
				// (out of stock, no matching transfer, or pre-order not
				// enabled/requested) - fall back to the original all-in-one
				// behavior: the whole requested quantity goes in as a single
				// ordinary line, same as before this split existed, so
				// Cart::hasStock() still catches it at checkout the way it
				// always has.
				$normal_quantity = $quantity;
				$overage_quantity = 0;
			}

			if ($normal_quantity > 0) {
				$this->cart->add($product_info['product_id'], $normal_quantity, $option, $subscription_plan_id, []);
			}

			if ($overage_quantity > 0) {
				$this->cart->add($product_info['product_id'], $overage_quantity, $option, $subscription_plan_id, $override);
			}

			$json['success'] = sprintf($this->language->get('text_success'), $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_info['product_id']), $product_info['name'], $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language')));

			// Unset all shipping and payment methods
			unset($this->session->data['order_id']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
		} else {
			$json['redirect'] = $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_id, true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Edit
	 *
	 * @return void
	 */
	public function edit(): void {
		$this->load->language('checkout/cart');

		$json = [];

		if (isset($this->request->post['key'])) {
			$key = (int)$this->request->post['key'];
		} else {
			$key = 0;
		}

		if (isset($this->request->post['quantity'])) {
			$quantity = (int)$this->request->post['quantity'];
		} else {
			$quantity = 1;
		}

		// Handles single item update
		$this->cart->update($key, $quantity);

		if ($this->cart->hasProducts()) {
			$json['success'] = $this->language->get('text_edit');
		} else {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		unset($this->session->data['order_id']);
		unset($this->session->data['shipping_method']);
		unset($this->session->data['shipping_methods']);
		unset($this->session->data['payment_method']);
		unset($this->session->data['payment_methods']);
		unset($this->session->data['reward']);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Remove
	 *
	 * @return void
	 */
	public function remove(): void {
		$this->load->language('checkout/cart');

		$json = [];

		if (isset($this->request->get['key'])) {
			$key = (int)$this->request->get['key'];
		} else {
			$key = 0;
		}

		// Remove
		$this->cart->remove($key);

		if ($this->cart->hasProducts()) {
			$json['success'] = $this->language->get('text_remove');
		} else {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		unset($this->session->data['order_id']);
		unset($this->session->data['shipping_method']);
		unset($this->session->data['shipping_methods']);
		unset($this->session->data['payment_method']);
		unset($this->session->data['payment_methods']);
		unset($this->session->data['reward']);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
