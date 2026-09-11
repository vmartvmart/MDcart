<?php
namespace Opencart\Admin\Controller\Sale;
/**
 * Class Pos
 *
 * In-person point-of-sale screen. A sale always picks a warehouse to sell
 * from (any warehouse, not just the online storefront's selling warehouse)
 * and, on checkout, creates a REAL order through OpenCart's own checkout
 * pipeline (the same one the storefront and the admin's "Add Order" screen
 * use - see catalog/controller/api/order.php::confirm()) so it shows up
 * correctly under Sales > Orders, with a synthetic walk-in guest customer
 * and a synthetic Cash/Card payment method (no real payment gateway or
 * shipping module is required to be configured).
 *
 * Stock is then deducted from the chosen warehouse directly. If that
 * warehouse happens to be the store's selling warehouse, the storefront's
 * oc_product.quantity is naturally already reduced by the order itself;
 * otherwise oc_product.quantity is explicitly re-synced afterwards so a POS
 * sale from a non-selling warehouse never touches the online store's stock.
 *
 * Can be loaded using $this->load->controller('sale/pos');
 *
 * @package Opencart\Admin\Controller\Sale
 */
class Pos extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('sale/pos');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('sale/pos', 'user_token=' . $this->session->data['user_token'])
		];

		$data['autocomplete'] = $this->url->link('catalog/product.autocomplete', 'user_token=' . $this->session->data['user_token'], true);
		$data['checkout'] = $this->url->link('sale/pos.checkout', 'user_token=' . $this->session->data['user_token'], true);

		$this->load->model('catalog/warehouse');

		$data['warehouses'] = $this->model_catalog_warehouse->getWarehouses();
		$data['currency_symbol'] = $this->config->get('config_currency');

		// Every enabled currency is offered on the sale - a walk-in customer
		// can be charged in AED, Toman (IRT), USD, or whatever else an admin
		// has turned on under System > Localisation > Currencies. The order
		// itself is still always recorded/accounted in the store's base
		// currency underneath (OpenCart's normal behaviour); this only
		// changes what the cashier and the receipt/order display shows.
		$this->load->model('localisation/currency');

		$data['currencies'] = [];

		foreach ($this->model_localisation_currency->getCurrencies() as $currency) {
			if (!empty($currency['status'])) {
				$data['currencies'][] = [
					'code'          => $currency['code'],
					'title'         => $currency['title'],
					'value'         => (float)$currency['value'],
					'symbol_left'   => $currency['symbol_left'],
					'symbol_right'  => $currency['symbol_right'],
					'decimal_place' => (int)$currency['decimal_place']
				];
			}
		}

		$admin_language_code = isset($this->request->cookie['language']) ? (string)$this->request->cookie['language'] : (string)$this->config->get('config_language_admin');

		$data['currency_code'] = $this->currencyDefaultForLanguage($admin_language_code);

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('sale/pos', $data));
	}

	/**
	 * Checkout (AJAX) - build and confirm a real order from the POS cart.
	 *
	 * @return void
	 */
	public function checkout(): void {
		$this->load->language('sale/pos');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/pos')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$warehouse_id = (int)($this->request->post['warehouse_id'] ?? 0);
		$payment_method = (string)($this->request->post['payment_method'] ?? '');
		$products = (array)($this->request->post['products'] ?? []);
		$currency_code = (string)($this->request->post['currency_code'] ?? '');

		if (!$currency_code || !$this->currency->has($currency_code)) {
			$currency_code = (string)$this->config->get('config_currency');
		}

		if (!$warehouse_id) {
			$json['error'] = $this->language->get('error_warehouse');
		}

		if (!in_array($payment_method, ['cash', 'card'], true)) {
			$json['error'] = $this->language->get('error_payment_method');
		}

		if (!$products) {
			$json['error'] = $this->language->get('error_products');
		}

		if (!$json) {
			$this->load->model('catalog/warehouse');

			$warehouse_info = $this->model_catalog_warehouse->getWarehouse($warehouse_id);

			if (!$warehouse_info) {
				$json['error'] = $this->language->get('error_warehouse');
			}
		}

		if (!$json) {
			$result = $this->createOrder($warehouse_info, $payment_method, $products, $currency_code);

			if (isset($result['error'])) {
				$json['error'] = $result['error'];

				if (isset($result['stock_product_id'])) {
					$json['stock_product_id'] = $result['stock_product_id'];
				}
			} else {
				// Deduct stock from the chosen warehouse, then make sure the
				// online store's stock only ever reflects the selling warehouse.
				foreach ($products as $product) {
					$product_id = (int)($product['product_id'] ?? 0);
					$quantity = (int)($product['quantity'] ?? 0);

					if ($product_id && $quantity > 0) {
						$this->db->query("UPDATE `" . DB_PREFIX . "product_warehouse` SET `quantity` = GREATEST(0, `quantity` - " . $quantity . "), `date_modified` = NOW() WHERE `product_id` = '" . $product_id . "' AND `warehouse_id` = '" . $warehouse_id . "'");
				}
				}

				if (!$warehouse_info['is_selling_warehouse']) {
					$this->load->model('catalog/warehouse');
					$selling = $this->model_catalog_warehouse->getSellingWarehouse();

					if ($selling) {
						foreach ($products as $product) {
							$product_id = (int)($product['product_id'] ?? 0);

							if ($product_id) {
								$stock_query = $this->db->query("SELECT `quantity` FROM `" . DB_PREFIX . "product_warehouse` WHERE `product_id` = '" . $product_id . "' AND `warehouse_id` = '" . (int)$selling['warehouse_id'] . "'");

								$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity` = '" . ($stock_query->num_rows ? (int)$stock_query->row['quantity'] : 0) . "' WHERE `product_id` = '" . $product_id . "'");
							}
						}
					}
				}

				$json['success'] = $this->language->get('text_success');
				$json['order_id'] = $result['order_id'];
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Builds a real order through OpenCart's own catalog checkout pipeline,
	 * running entirely inside this one request (no dependency on any
	 * configured payment/shipping extension or API user).
	 *
	 * @param array<string, mixed>        $warehouse_info
	 * @param string                      $payment_method
	 * @param array<int, array<string, mixed>> $products
	 * @param string                      $currency_code
	 *
	 * @return array<string, mixed> {order_id} on success, {error} on failure
	 */
	private function createOrder(array $warehouse_info, string $payment_method, array $products, string $currency_code): array {
		$this->load->model('setting/store');

		$store = $this->model_setting_store->createStoreInstance(0, $this->config->get('config_language'), $this->config->get('config_currency'));

		// createStoreInstance()'s own $currency parameter is a no-op (startup/
		// currency always resolves the fresh session back to config_currency
		// since there's no cookie yet) - so the sale's chosen currency has to
		// be forced onto the session directly, same as payment_method/
		// pos_warehouse_id below. catalog/controller/api/order.php reads this
		// live when it stamps currency_code/currency_value onto the order, so
		// the receipt and Order Info page display in whatever currency the
		// cashier picked - order_product price/total stay in the store's base
		// currency underneath either way, so accounting is unaffected.
		$store->session->data['currency'] = $currency_code;

		// Walk-in guest customer.
		$store->session->data['customer'] = [
			'customer_id'       => 0,
			'customer_group_id' => (int)$this->config->get('config_customer_group_id'),
			'firstname'         => $this->language->get('text_walkin_firstname'),
			'lastname'          => $this->language->get('text_walkin_lastname'),
			'email'             => 'pos-' . time() . '@' . preg_replace('/^https?:\/\//', '', rtrim((string)$this->config->get('config_url'), '/')),
			'telephone'         => '0000000000',
			'custom_field'      => []
		];

		// Add every line to the cart via the real cart-add logic.
		foreach ($products as $product) {
			$product_id = (int)($product['product_id'] ?? 0);
			$quantity = (int)($product['quantity'] ?? 0);

			if (!$product_id || $quantity < 1) {
				continue;
			}

			$store->request->post = [
				'product_id' => $product_id,
				'quantity'   => $quantity
			];

			// Pre-order / backorder: the cashier can mark any line as a
			// pre-order (proactively, or after a stock error below) and pick
			// its delivery date. api/cart.addProduct() only honors this when
			// the product itself is admin-approved for pre-order
			// (preorder_status, set on the product's edit form) - the same
			// approval a storefront customer's self-service pre-order needs,
			// so a product only ever sells as a pre-order through either
			// channel once an admin has explicitly turned it on.
			if (!empty($product['preorder'])) {
				$store->request->post['preorder'] = 1;
				$store->request->post['preorder_delivery_date'] = (string)($product['preorder_delivery_date'] ?? '');
			}

			$store->request->get['route'] = 'api/cart';
			$add_result = $store->load->controller('api/cart.addProduct');

			if (isset($add_result['error'])) {
				$error = ['error' => is_array($add_result['error']) ? implode(' ', $add_result['error']) : $add_result['error']];

				// Not already flagged as a pre-order - let the POS screen offer
				// to sell it as one instead of just failing the whole sale.
				if (empty($product['preorder'])) {
					$error['stock_product_id'] = $product_id;
				}

				return $error;
			}
		}

		if (!$store->cart->hasProducts()) {
			return ['error' => $this->language->get('error_products')];
		}

		// Store's own address stands in for a walk-in / in-store sale.
		$address_country_id = (int)$this->config->get('config_country_id');
		$address_zone_id = (int)$this->config->get('config_zone_id');

		if (!$address_country_id) {
			return ['error' => $this->language->get('error_store_address')];
		}

		$this->load->model('localisation/country');
		$country_info = $this->model_localisation_country->getCountry($address_country_id);

		$this->load->model('localisation/zone');
		$zone_info = $this->model_localisation_zone->getZone($address_zone_id);

		$address = [
			'address_id'     => 0,
			'firstname'      => $store->session->data['customer']['firstname'],
			'lastname'       => $store->session->data['customer']['lastname'],
			'company'        => '',
			'address_1'      => (string)$this->config->get('config_address') ?: $warehouse_info['name'],
			'address_2'      => '',
			'postcode'       => '',
			'city'           => $warehouse_info['name'],
			'zone_id'        => $address_zone_id,
			'zone'           => $zone_info ? $zone_info['name'] : '',
			'zone_code'      => $zone_info ? $zone_info['code'] : '',
			'country_id'     => $address_country_id,
			'country'        => $country_info ? $country_info['name'] : '',
			'iso_code_2'     => $country_info ? $country_info['iso_code_2'] : '',
			'iso_code_3'     => $country_info ? $country_info['iso_code_3'] : '',
			'address_format' => '',
			'custom_field'   => []
		];

		$store->session->data['payment_address'] = $address;

		if ($store->cart->hasShipping()) {
			$store->session->data['shipping_address'] = $address;

			$store->session->data['shipping_method'] = [
				'name'         => $this->language->get('text_in_store'),
				'code'         => 'pos.pos',
				'cost'         => 0,
				'tax_class_id' => 0,
				'text'         => $this->language->get('text_in_store')
			];
		}

		$store->session->data['payment_method'] = [
			'name' => $payment_method === 'cash' ? $this->language->get('text_cash') : $this->language->get('text_card'),
			'code' => 'pos.' . $payment_method
		];

		// Tells the core stock-subtraction hook (catalog/model/checkout/order.php::addHistory())
		// which warehouse this sale actually came from, so the Profit/Loss report snapshots
		// the correct cost - not the online storefront's selling-warehouse cost - when the
		// sale is from a different warehouse.
		$store->session->data['pos_warehouse_id'] = (int)$warehouse_info['warehouse_id'];

		$store->request->get['route'] = 'api/order';
		$store->request->get['call'] = 'confirm';
		$complete_statuses = (array)$this->config->get('config_complete_status');
		$order_status_id = !empty($complete_statuses) ? (int)reset($complete_statuses) : (int)$this->config->get('config_order_status_id');

		$store->request->post = [
			'comment'         => sprintf($this->language->get('text_order_comment'), $warehouse_info['name']),
			'order_status_id' => $order_status_id
		];

		// Api\Order::index() has a `void` return type - it never returns its
		// result, it only JSON-encodes it into the response object (exactly
		// like a real HTTP client would receive it). So the result has to be
		// read back from there, not from this call's return value (always null).
		$store->load->controller('api/order');

		$output = json_decode($store->response->getOutput(), true);

		$store->session->destroy();

		if (!is_array($output)) {
			return ['error' => $this->language->get('error_unknown')];
		}

		if (!empty($output['error'])) {
			return ['error' => is_array($output['error']) ? implode(' ', array_map('strval', $output['error'])) : $output['error']];
		}

		if (empty($output['order_id'])) {
			return ['error' => $this->language->get('error_unknown')];
		}

		return ['order_id' => $output['order_id']];
	}

	/**
	 * Picks the currency the POS screen should default to for the currently
	 * logged-in admin's UI language - Persian defaults to Toman (IRT),
	 * everything else defaults to the store's base currency. This is only
	 * ever the pre-selected value; the cashier can always change it per
	 * sale from the currency dropdown.
	 *
	 * @param string $admin_language_code
	 *
	 * @return string
	 */
	private function currencyDefaultForLanguage(string $admin_language_code): string {
		$store_currency = (string)$this->config->get('config_currency');

		if (str_starts_with($admin_language_code, 'fa') && $this->currency->has('IRT')) {
			return 'IRT';
		}

		return $store_currency;
	}
}
