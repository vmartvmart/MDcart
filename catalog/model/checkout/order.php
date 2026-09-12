<?php
namespace MDcart\Catalog\Model\Checkout;
/**
 * Class Order
 *
 * Can be called using $this->load->model('checkout/order');
 *
 * @package MDcart\Catalog\Model\Checkout
 */
class Order extends \MDcart\System\Engine\Model {
	/**
	 * Add Order
	 *
	 * Create a new order record in the database.
	 *
	 * @param array<string, mixed> $data array of data
	 *
	 * @return int returns the primary key of the new order record
	 *
	 * @example
	 *
	 * $order_data = [
	 *     'subscription_id'        => 1,
	 *     'invoice_prefix'         => 'INV-',
	 *     'store_id'               => 1,
	 *     'store_name'             => 'Your Store',
	 *     'store_url'              => '',
	 *     'customer_id'            => 1,
	 *     'customer_group_id'      => 1,
	 *     'firstname'              => 'John',
	 *     'lastname'               => 'Doe',
	 *     'email'                  => 'demo@opencart.com',
	 *     'telephone'              => '1234567890',
	 *     'custom_field'           => [],
	 *     'payment_address_id'     => 1,
	 *     'payment_firstname'      => 'John',
	 *     'payment_lastname'       => 'Doe',
	 *     'payment_company'        => '',
	 *     'payment_address_1'      => 'Address 1',
	 *     'payment_address_2'      => 'Address 2',
	 *     'payment_city'           => '',
	 *     'payment_postcode'       => '',
	 *     'payment_country'        => 'United Kingdom',
	 *     'payment_country_id'     => 222,
	 *     'payment_zone'           => 'Lancashire',
	 *     'payment_zone_id'        => 3563,
	 *     'payment_address_format' => '',
	 *     'payment_custom_field'   => [],
	 *     'payment_method'         => [
	 *         'name' => 'Payment Name',
	 *         'code' => 'Payment Code'
	 *      ],
	 *      'shipping_address_id'     => 1,
	 *      'shipping_firstname'      => 'John',
	 *      'shipping_lastname'       => 'Doe',
	 *      'shipping_company'        => '',
	 *      'shipping_address_1'      => 'Address 1',
	 *      'shipping_address_2'      => 'Address 2',
	 *      'shipping_city'           => '',
	 *      'shipping_postcode'       => '',
	 *      'shipping_country'        => 'United Kingdom',
	 *      'shipping_country_id'     => 222,
	 *      'shipping_zone'           => 'Lancashire',
	 *      'shipping_zone_id'        => 3563,
	 *      'shipping_address_format' => '',
	 *      'shipping_custom_field'   => [],
	 *      'shipping_method'         => [
	 *          'name' => 'Shipping Name',
	 *          'code' => 'Shipping Code'
	 *      ],
	 *      'comment'         => '',
	 *      'total'           => '0.0000',
	 *      'affiliate_id'    => 0,
	 *      'commission'      => '0.0000',
	 *      'marketing_id'    => 0,
	 *      'tracking'        => '',
	 *      'language_id'     => 1,
	 *      'language_code'   => 'en-gb',
	 *      'currency_id'     => 1,
	 *      'currency_code'   => 'USD',
	 *      'currency_value'  => '1.00000000',
	 *      'ip'              => '',
	 *      'forwarded_ip'    => '',
	 *      'user_agent'      => '',
	 *      'accept_language' => ''
	 * ];
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->addOrder($order_data);
	 */
	public function addOrder(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "order` SET `subscription_id` = '" . (int)$data['subscription_id'] . "', `invoice_prefix` = '" . $this->db->escape($data['invoice_prefix']) . "', `store_id` = '" . (int)$data['store_id'] . "', `store_name` = '" . $this->db->escape($data['store_name']) . "', `store_url` = '" . $this->db->escape($data['store_url']) . "', `customer_id` = '" . (int)$data['customer_id'] . "', `customer_group_id` = '" . (int)$data['customer_group_id'] . "', `firstname` = '" . $this->db->escape($data['firstname']) . "', `lastname` = '" . $this->db->escape($data['lastname']) . "', `email` = '" . $this->db->escape($data['email']) . "', `telephone` = '" . $this->db->escape($data['telephone']) . "', `custom_field` = '" . $this->db->escape(isset($data['custom_field']) ? json_encode($data['custom_field']) : '') . "', `payment_address_id` = '" . (int)$data['payment_address_id'] . "', `payment_firstname` = '" . $this->db->escape($data['payment_firstname']) . "', `payment_lastname` = '" . $this->db->escape($data['payment_lastname']) . "', `payment_company` = '" . $this->db->escape($data['payment_company']) . "', `payment_address_1` = '" . $this->db->escape($data['payment_address_1']) . "', `payment_address_2` = '" . $this->db->escape($data['payment_address_2']) . "', `payment_city` = '" . $this->db->escape($data['payment_city']) . "', `payment_postcode` = '" . $this->db->escape($data['payment_postcode']) . "', `payment_country` = '" . $this->db->escape($data['payment_country']) . "', `payment_country_id` = '" . (int)$data['payment_country_id'] . "', `payment_zone` = '" . $this->db->escape($data['payment_zone']) . "', `payment_zone_id` = '" . (int)$data['payment_zone_id'] . "', `payment_address_format` = '" . $this->db->escape($data['payment_address_format']) . "', `payment_custom_field` = '" . $this->db->escape(isset($data['payment_custom_field']) ? json_encode($data['payment_custom_field']) : '') . "', `payment_method` = '" . $this->db->escape($data['payment_method'] ? json_encode($data['payment_method']) : '') . "', `shipping_address_id` = '" . (int)$data['shipping_address_id'] . "', `shipping_firstname` = '" . $this->db->escape($data['shipping_firstname']) . "', `shipping_lastname` = '" . $this->db->escape($data['shipping_lastname']) . "', `shipping_company` = '" . $this->db->escape($data['shipping_company']) . "', `shipping_address_1` = '" . $this->db->escape($data['shipping_address_1']) . "', `shipping_address_2` = '" . $this->db->escape($data['shipping_address_2']) . "', `shipping_city` = '" . $this->db->escape($data['shipping_city']) . "', `shipping_postcode` = '" . $this->db->escape($data['shipping_postcode']) . "', `shipping_country` = '" . $this->db->escape($data['shipping_country']) . "', `shipping_country_id` = '" . (int)$data['shipping_country_id'] . "', `shipping_zone` = '" . $this->db->escape($data['shipping_zone']) . "', `shipping_zone_id` = '" . (int)$data['shipping_zone_id'] . "', `shipping_address_format` = '" . $this->db->escape($data['shipping_address_format']) . "', `shipping_custom_field` = '" . $this->db->escape(isset($data['shipping_custom_field']) ? json_encode($data['shipping_custom_field']) : '') . "', `shipping_method` = '" . $this->db->escape($data['shipping_method'] ? json_encode($data['shipping_method']) : '') . "', `comment` = '" . $this->db->escape($data['comment']) . "', `total` = '" . (float)$data['total'] . "', `affiliate_id` = '" . (int)$data['affiliate_id'] . "', `commission` = '" . (float)$data['commission'] . "', `marketing_id` = '" . (int)$data['marketing_id'] . "', `tracking` = '" . $this->db->escape($data['tracking']) . "', `language_id` = '" . (int)$data['language_id'] . "', `language_code` = '" . $this->db->escape($data['language_code']) . "', `currency_id` = '" . (int)$data['currency_id'] . "', `currency_code` = '" . $this->db->escape($data['currency_code']) . "', `currency_value` = '" . (float)$data['currency_value'] . "', `ip` = '" . $this->db->escape((string)$data['ip']) . "', `forwarded_ip` = '" . $this->db->escape((string)$data['forwarded_ip']) . "', `user_agent` = '" . $this->db->escape((string)$data['user_agent']) . "', `accept_language` = '" . $this->db->escape((string)$data['accept_language']) . "', `date_added` = NOW(), `date_modified` = NOW()");

		$order_id = $this->db->getLastId();

		// Products
		if (!empty($data['products'])) {
			foreach ($data['products'] as $product) {
				$this->model_checkout_order->addProduct($order_id, $product);
			}
		}

		// Totals
		if (!empty($data['totals'])) {
			foreach ($data['totals'] as $total) {
				$this->model_checkout_order->addTotal($order_id, $total);
			}
		}

		return $order_id;
	}

	/**
	 * Edit Order
	 *
	 * Edit order record in the database.
	 *
	 * @param int                  $order_id primary key of the order record
	 * @param array<string, mixed> $data     array of data
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $order_data = [
	 *     'subscription_id'        => 1,
	 *     'invoice_prefix'         => 'INV-',
	 *     'store_id'               => 1,
	 *     'store_name'             => 'Your Store',
	 *     'store_url'              => '',
	 *     'customer_id'            => 1,
	 *     'customer_group_id'      => 1,
	 *     'firstname'              => 'John',
	 *     'lastname'               => 'Doe',
	 *     'email'                  => 'demo@opencart.com',
	 *     'telephone'              => '1234567890',
	 *     'custom_field'           => [],
	 *     'payment_address_id'     => 1,
	 *     'payment_firstname'      => 'John',
	 *     'payment_lastname'       => 'Doe',
	 *     'payment_company'        => '',
	 *     'payment_address_1'      => 'Address 1',
	 *     'payment_address_2'      => 'Address 2',
	 *     'payment_city'           => '',
	 *     'payment_postcode'       => '',
	 *     'payment_country'        => 'United Kingdom',
	 *     'payment_country_id'     => 222,
	 *     'payment_zone'           => 'Lancashire',
	 *     'payment_zone_id'        => 3563,
	 *     'payment_address_format' => '',
	 *     'payment_custom_field'   => [],
	 *     'payment_method'         => [
	 *         'name' => 'Payment Name',
	 *         'code' => 'Payment Code'
	 *      ],
	 *      'shipping_address_id'     => 1,
	 *      'shipping_firstname'      => 'John',
	 *      'shipping_lastname'       => 'Doe',
	 *      'shipping_company'        => '',
	 *      'shipping_address_1'      => 'Address 1',
	 *      'shipping_address_2'      => 'Address 2',
	 *      'shipping_city'           => '',
	 *      'shipping_postcode'       => '',
	 *      'shipping_country'        => 'United Kingdom',
	 *      'shipping_country_id'     => 222,
	 *      'shipping_zone'           => 'Lancashire',
	 *      'shipping_zone_id'        => 3563,
	 *      'shipping_address_format' => '',
	 *      'shipping_custom_field'   => [],
	 *      'shipping_method'         => [
	 *          'name' => 'Shipping Name',
	 *          'code' => 'Shipping Code'
	 *      ],
	 *      'comment'         => '',
	 *      'total'           => '0.0000',
	 *      'affiliate_id'    => 0,
	 *      'commission'      => '0.0000',
	 *      'marketing_id'    => 0,
	 *      'tracking'        => '',
	 *      'language_id'     => 1,
	 *      'language_code'   => 'en-gb',
	 *      'currency_id'     => 1,
	 *      'currency_code'   => 'USD',
	 *      'currency_value'  => '1.00000000',
	 *      'ip'              => '',
	 *      'forwarded_ip'    => '',
	 *      'user_agent'      => '',
	 *      'accept_language' => ''
	 * ];
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->editOrder($order_id, $order_data);
	 */
	public function editOrder(int $order_id, array $data): void {
		$order_info = $this->getOrder($order_id);

		if ($order_info) {

			// 1. Void the order first if it has already been confirmed
			if ($order_info['order_status_id']) {
				$this->addHistory($order_id, (int)$this->config->get('config_void_status_id'), '', true);
			}

			// 2. Merge the old order data with the new data
			foreach ($order_info as $key => $value) {
				if (!isset($data[$key])) {
					$data[$key] = $value;
				}
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "order` SET `subscription_id` = '" . (int)$data['subscription_id'] . "', `invoice_prefix` = '" . $this->db->escape((string)$data['invoice_prefix']) . "', `store_id` = '" . (int)$data['store_id'] . "', `store_name` = '" . $this->db->escape((string)$data['store_name']) . "', `store_url` = '" . $this->db->escape((string)$data['store_url']) . "', `customer_id` = '" . (int)$data['customer_id'] . "', `customer_group_id` = '" . (int)$data['customer_group_id'] . "', `firstname` = '" . $this->db->escape((string)$data['firstname']) . "', `lastname` = '" . $this->db->escape((string)$data['lastname']) . "', `email` = '" . $this->db->escape((string)$data['email']) . "', `telephone` = '" . $this->db->escape((string)$data['telephone']) . "', `custom_field` = '" . $this->db->escape(json_encode($data['custom_field'])) . "', `payment_address_id` = '" . (int)$data['payment_address_id'] . "', `payment_firstname` = '" . $this->db->escape((string)$data['payment_firstname']) . "', `payment_lastname` = '" . $this->db->escape((string)$data['payment_lastname']) . "', `payment_company` = '" . $this->db->escape((string)$data['payment_company']) . "', `payment_address_1` = '" . $this->db->escape((string)$data['payment_address_1']) . "', `payment_address_2` = '" . $this->db->escape((string)$data['payment_address_2']) . "', `payment_city` = '" . $this->db->escape((string)$data['payment_city']) . "', `payment_postcode` = '" . $this->db->escape((string)$data['payment_postcode']) . "', `payment_country` = '" . $this->db->escape((string)$data['payment_country']) . "', `payment_country_id` = '" . (int)$data['payment_country_id'] . "', `payment_zone` = '" . $this->db->escape((string)$data['payment_zone']) . "', `payment_zone_id` = '" . (int)$data['payment_zone_id'] . "', `payment_address_format` = '" . $this->db->escape((string)$data['payment_address_format']) . "', `payment_custom_field` = '" . $this->db->escape(isset($data['payment_custom_field']) ? json_encode($data['payment_custom_field']) : '') . "', `payment_method` = '" . $this->db->escape($data['payment_method'] ? json_encode($data['payment_method']) : '') . "', `shipping_address_id` = '" . (int)$data['shipping_address_id'] . "', `shipping_firstname` = '" . $this->db->escape((string)$data['shipping_firstname']) . "', `shipping_lastname` = '" . $this->db->escape((string)$data['shipping_lastname']) . "', `shipping_company` = '" . $this->db->escape((string)$data['shipping_company']) . "', `shipping_address_1` = '" . $this->db->escape((string)$data['shipping_address_1']) . "', `shipping_address_2` = '" . $this->db->escape((string)$data['shipping_address_2']) . "', `shipping_city` = '" . $this->db->escape((string)$data['shipping_city']) . "', `shipping_postcode` = '" . $this->db->escape((string)$data['shipping_postcode']) . "', `shipping_country` = '" . $this->db->escape((string)$data['shipping_country']) . "', `shipping_country_id` = '" . (int)$data['shipping_country_id'] . "', `shipping_zone` = '" . $this->db->escape((string)$data['shipping_zone']) . "', `shipping_zone_id` = '" . (int)$data['shipping_zone_id'] . "', `shipping_address_format` = '" . $this->db->escape((string)$data['shipping_address_format']) . "', `shipping_custom_field` = '" . $this->db->escape(isset($data['shipping_custom_field']) ? json_encode($data['shipping_custom_field']) : '') . "', `shipping_method` = '" . $this->db->escape($data['shipping_method'] ? json_encode($data['shipping_method']) : '') . "', `comment` = '" . $this->db->escape((string)$data['comment']) . "', `total` = '" . (float)$data['total'] . "', `affiliate_id` = '" . (int)$data['affiliate_id'] . "', `commission` = '" . (float)$data['commission'] . "', `marketing_id` = '" . (int)$data['marketing_id'] . "', `tracking` = '" . $this->db->escape($data['tracking']) . "', `language_id` = '" . (int)$data['language_id'] . "', `language_code` = '" . $this->db->escape($data['language_code']) . "', `currency_id` = '" . (int)$data['currency_id'] . "', `currency_code` = '" . $this->db->escape($data['currency_code']) . "', `currency_value` = '" . (float)$data['currency_value'] . "', `date_modified` = NOW() WHERE `order_id` = '" . (int)$order_id . "'");

			// Products
			$this->model_checkout_order->deleteProducts($order_id);

			if (!empty($data['products'])) {
				foreach ($data['products'] as $product) {
					$this->model_checkout_order->addProduct($order_id, $product);
				}
			}

			// Totals
			$this->model_checkout_order->deleteTotals($order_id);

			if (!empty($data['totals'])) {
				foreach ($data['totals'] as $total) {
					$this->model_checkout_order->addTotal($order_id, $total);
				}
			}
		}
	}

	/**
	 * Edit Transaction ID
	 *
	 * Edit order transaction record in the database.
	 *
	 * @param int    $order_id       primary key of the order record
	 * @param string $transaction_id primary key of the transaction record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->editTransactionId($order_id, $transaction_id);
	 */
	public function editTransactionId(int $order_id, string $transaction_id): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET `transaction_id` = '" . $this->db->escape($transaction_id) . "' WHERE `order_id` = '" . (int)$order_id . "'");
	}

	/**
	 * Edit Order Status ID
	 *
	 * Edit order status record in the database.
	 *
	 * @param int $order_id        primary key of the order record
	 * @param int $order_status_id primary key of the order status record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->editOrderStatusId($order_id, $order_status_id);
	 */
	public function editOrderStatusId(int $order_id, int $order_status_id): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET `order_status_id` = '" . (int)$order_status_id . "' WHERE `order_id` = '" . (int)$order_id . "'");
	}

	/**
	 * Edit Comment
	 *
	 * Edit order comment record in the database.
	 *
	 * @param int    $order_id primary key of the order record
	 * @param string $comment
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->editComment($order_id, $comment);
	 */
	public function editComment(int $order_id, string $comment): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET `comment` = '" . $this->db->escape($comment) . "' WHERE `order_id` = '" . (int)$order_id . "'");
	}

	/**
	 * Delete Order
	 *
	 * Delete order record in the database.
	 *
	 * @param int $order_id primary key of the order record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->deleteOrder($order_id);
	 */
	public function deleteOrder(int $order_id): void {
		// Void the order first so it restocks products
		$this->model_checkout_order->addHistory($order_id, (int)$this->config->get('config_void_status_id'));

		$this->db->query("DELETE FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . (int)$order_id . "'");

		$this->model_checkout_order->deleteProducts($order_id);
		$this->model_checkout_order->deleteTotals($order_id);
		$this->model_checkout_order->deleteHistories($order_id);

		// Transactions
		$this->load->model('account/transaction');

		$this->model_account_transaction->deleteTransactionsByOrderId($order_id);

		// Rewards
		$this->load->model('account/reward');

		$this->model_account_reward->deleteRewardsByOrderId($order_id);
	}

	/**
	 * Get Order
	 *
	 * Get the record of the order record in the database.
	 *
	 * @param int $order_id primary key of the order record
	 *
	 * @return array<string, mixed> order record that has order ID
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $order_info = $this->model_checkout_order->getOrder($order_id);
	 */
	public function getOrder(int $order_id): array {
		$order_query = $this->db->query("SELECT *, (SELECT `os`.`name` FROM `" . DB_PREFIX . "order_status` `os` WHERE `os`.`order_status_id` = `o`.`order_status_id` AND `os`.`language_id` = `o`.`language_id`) AS `order_status` FROM `" . DB_PREFIX . "order` `o` WHERE `o`.`order_id` = '" . (int)$order_id . "'");

		if ($order_query->num_rows) {
			$order_data = $order_query->row;

			// Country
			$this->load->model('localisation/country');

			// Zone
			$this->load->model('localisation/zone');

			$order_data['custom_field'] = $order_query->row['custom_field'] ? json_decode($order_query->row['custom_field'], true) : [];

			foreach (['payment', 'shipping'] as $column) {
				$country_info = $this->model_localisation_country->getCountry($order_query->row[$column . '_country_id']);

				if ($country_info) {
					$order_data[$column . '_iso_code_2'] = $country_info['iso_code_2'];
					$order_data[$column . '_iso_code_3'] = $country_info['iso_code_3'];
				} else {
					$order_data[$column . '_iso_code_2'] = '';
					$order_data[$column . '_iso_code_3'] = '';
				}

				$zone_info = $this->model_localisation_zone->getZone($order_query->row[$column . '_zone_id']);

				if ($zone_info) {
					$order_data[$column . '_zone_code'] = $zone_info['code'];
				} else {
					$order_data[$column . '_zone_code'] = '';
				}

				$order_data[$column . '_custom_field'] = $order_query->row[$column . '_custom_field'] ? json_decode($order_query->row[$column . '_custom_field'], true) : [];

				// Payment and shipping method details
				$order_data[$column . '_method'] = json_decode($order_query->row[$column . '_method'], true);
			}

			$order_data['products'] = $this->getProducts($order_id);
			$order_data['totals'] = $this->getTotals($order_id);

			return $order_data;
		}

		return [];
	}

	/**
	 * Add Product
	 *
	 * Create a new order product record in the database.
	 *
	 * @param int                  $order_id primary key of the order record
	 * @param array<string, mixed> $data     array of data
	 *
	 * @return int returns the primary key of the new order product record
	 *
	 * @example
	 *
	 * $order_product_data = [
	 *     'product_id' => 1,
	 *     'master_id'  => 0,
	 *     'name'       => 'Product Name',
	 *     'model'      => 'Product Model',
	 *     'quantity'   => 1,
	 *     'price'      => 0.0000,
	 *     'total'      => 0.0000,
	 *     'tax'        => 0.0000,
	 *     'reward'     => 0
	 * ];
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->addProduct($order_id, $order_product_data);
	 */
	public function addProduct(int $order_id, array $data): int {
		$preorder_delivery_date = !empty($data['preorder_delivery_date']) ? "'" . $this->db->escape((string)$data['preorder_delivery_date']) . "'" : "NULL";
		$transit_delivery_date = !empty($data['transit_delivery_date']) ? "'" . $this->db->escape((string)$data['transit_delivery_date']) . "'" : "NULL";
		$transit_transfer_id = !empty($data['transit_transfer_id']) ? "'" . (int)$data['transit_transfer_id'] . "'" : "NULL";

		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_product` SET `order_id` = '" . (int)$order_id . "', `product_id` = '" . (int)$data['product_id'] . "', `master_id` = '" . (int)$data['master_id'] . "', `name` = '" . $this->db->escape($data['name']) . "', `model` = '" . $this->db->escape($data['model']) . "', `quantity` = '" . (int)$data['quantity'] . "', `price` = '" . (float)$data['price'] . "', `total` = '" . (float)$data['total'] . "', `tax` = '" . (float)$data['tax'] . "', `reward` = '" . (int)$data['reward'] . "', `is_preorder` = '" . (!empty($data['is_preorder']) ? 1 : 0) . "', `preorder_delivery_date` = " . $preorder_delivery_date . ", `is_transit_order` = '" . (!empty($data['is_transit_order']) ? 1 : 0) . "', `transit_transfer_id` = " . $transit_transfer_id . ", `transit_delivery_date` = " . $transit_delivery_date . "");

		$order_product_id = $this->db->getLastId();

		if (!empty($data['option'])) {
			foreach ($data['option'] as $option) {
				$this->model_checkout_order->addOption($order_id, $order_product_id, $option);
			}
		}

		// If subscription add details
		if (!empty($data['subscription'])) {
			$this->model_checkout_order->addSubscription($order_id, $order_product_id, $data['subscription'] + ['quantity' => $data['quantity']]);
		}

		return $order_product_id;
	}

	/**
	 * Delete Products
	 *
	 * Delete order product record in the database.
	 *
	 * @param int $order_id primary key of the order record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->deleteProducts($order_id);
	 */
	public function deleteProducts(int $order_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . (int)$order_id . "'");

		$this->deleteOptions($order_id);
		$this->deleteSubscription($order_id);
	}

	/**
	 * Get Product
	 *
	 * Get the record of the order product record in the database.
	 *
	 * @param int $order_id         primary key of the order record
	 * @param int $order_product_id primary key of the order product record
	 *
	 * @return array<int, array<string, mixed>> product record that has order ID, order product ID
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $order_product = $this->model_checkout_order->getProduct($order_id, $order_product_id);
	 */
	public function getProduct(int $order_id, int $order_product_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . (int)$order_id . "' AND `order_product_id` = '" . (int)$order_product_id . "'");

		return $query->row;
	}

	/**
	 * Get Products
	 *
	 * Get the record of the order product records in the database.
	 *
	 * @param int $order_id primary key of the order record
	 *
	 * @return array<int, array<string, mixed>> product records that have order ID
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $order_products = $this->model_checkout_order->getProducts($order_id);
	 */
	public function getProducts(int $order_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . (int)$order_id . "'");

		return $query->rows;
	}

	/**
	 * Add Option
	 *
	 * Create a new order option record in the database.
	 *
	 * @param int                  $order_id         primary key of the order record
	 * @param int                  $order_product_id primary key of the order product record
	 * @param array<string, mixed> $data             array of data
	 *
	 * @return int
	 *
	 * @example
	 *
	 * $order_option_data = [
	 *     'product_option_id'       => 1,
	 *     'product_option_value_id' => 1,
	 *     'name'                    => 'Option Name',
	 *     'value'                   => 'Option Value',
	 *     'type'                    => 'radio'
	 * ];
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->addOption($order_id, $order_product_id, $order_option_data);
	 */
	public function addOption(int $order_id, int $order_product_id, array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_option` SET `order_id` = '" . (int)$order_id . "', `order_product_id` = '" . (int)$order_product_id . "', `product_option_id` = '" . (int)$data['product_option_id'] . "', `product_option_value_id` = '" . (int)$data['product_option_value_id'] . "', `name` = '" . $this->db->escape($data['name']) . "', `value` = '" . $this->db->escape($data['value']) . "', `type` = '" . $this->db->escape($data['type']) . "'");

		return $this->db->getLastId();
	}

	/**
	 * Delete Options
	 *
	 * Delete order option records in the database.
	 *
	 * @param int $order_id primary key of the order record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->deleteOptions($order_id);
	 */
	public function deleteOptions(int $order_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_option` WHERE `order_id` = '" . (int)$order_id . "'");
	}

	/**
	 * Get Options
	 *
	 * Get the record of the order option records in the database.
	 *
	 * @param int $order_id         primary key of the order record
	 * @param int $order_product_id primary key of the order product record
	 *
	 * @return array<int, array<string, mixed>> option records that have order ID, order product ID
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $order_options = $this->model_checkout_order->getOptions($order_id, $order_product_id);
	 */
	public function getOptions(int $order_id, int $order_product_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_option` WHERE `order_id` = '" . (int)$order_id . "' AND `order_product_id` = '" . (int)$order_product_id . "'");

		return $query->rows;
	}

	/**
	 * Add Subscription
	 *
	 * Create a order subscription record in the database.
	 *
	 * @param int                  $order_id         primary key of the order record
	 * @param int                  $order_product_id primary key of the order product record
	 * @param array<string, mixed> $data             array of data
	 *
	 * @return int
	 *
	 * @example
	 *
	 * $order_subscription_data = [
	 *     'product_id'           => 1,
	 *     'subscription_plan_id' => 1,
	 *     'trial_price'          => 0.0000,
	 *     'trial_tax'            => 0.0000,
	 *     'trial_frequency'      => 'month',
	 *     'trial_cycle'          => 5,
	 *     'trial_duration'       => 1,
	 *     'trial_status'         => 1,
	 *     'price'                => 0.0000,
	 *     'tax'                  => 0.0000,
	 *     'frequency'            => 'month',
	 *     'cycle'                => 5,
	 *     'duration'             => 1
	 * ];
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->addSubscription($order_id, $order_product_id, $order_subscription_data);
	 */
	public function addSubscription(int $order_id, int $order_product_id, array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_subscription` SET `order_product_id` = '" . (int)$order_product_id . "', `order_id` = '" . (int)$order_id . "', `product_id` = '" . (int)$data['product_id'] . "', `subscription_plan_id` = '" . (int)$data['subscription_plan_id'] . "', `trial_price` = '" . (float)$data['trial_price'] . "', `trial_tax` = '" . (float)$data['trial_tax'] . "', `trial_frequency` = '" . $this->db->escape($data['trial_frequency']) . "', `trial_cycle` = '" . (int)$data['trial_cycle'] . "', `trial_duration` = '" . (int)$data['trial_duration'] . "', `trial_status` = '" . (int)$data['trial_status'] . "', `price` = '" . (float)$data['price'] . "', `tax` = '" . (float)$data['tax'] . "', `frequency` = '" . $this->db->escape($data['frequency']) . "', `cycle` = '" . (int)$data['cycle'] . "', `duration` = '" . (int)$data['duration'] . "'");

		return $this->db->getLastId();
	}

	/**
	 * Delete Subscription
	 *
	 * Delete order subscription record in the database.
	 *
	 * @param int $order_id primary key of the order record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->deleteSubscription($order_id);
	 */
	public function deleteSubscription(int $order_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_subscription` WHERE `order_id` = '" . (int)$order_id . "'");
	}

	/**
	 * Get Subscription
	 *
	 * Get the record of the order subscription record in the database.
	 *
	 * @param int $order_id         primary key of the order record
	 * @param int $order_product_id primary key of the order product record
	 *
	 * @return array<string, mixed> subscription record that have order ID, order product ID
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $order_subscription_info = $this->model_checkout_order->getSubscription($order_id, $order_product_id);
	 */
	public function getSubscription(int $order_id, int $order_product_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_subscription` WHERE `order_id` = '" . (int)$order_id . "' AND `order_product_id` = '" . (int)$order_product_id . "'");

		return $query->row;
	}

	/**
	 * Get Subscriptions
	 *
	 * Get the record of the order subscription records in the database.
	 *
	 * @param int $order_id primary key of the order record
	 *
	 * @return array<int, array<string, mixed>> subscription records that have order ID
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $results = $this->model_checkout_order->getSubscriptions($order_id);
	 */
	public function getSubscriptions(int $order_id): array {
		$query = $this->db->query("SELECT *, `os`.`price`, `os`.`tax` FROM `" . DB_PREFIX . "order_subscription` `os` LEFT JOIN `" . DB_PREFIX . "order_product` `op` ON(`os`.`order_product_id` = `op`.`order_product_id`) WHERE `os`.`order_id` = '" . (int)$order_id . "'");

		return $query->rows;
	}

	/**
	 * Get Total Orders By Subscription ID
	 *
	 * Get the total number of total orders by subscription records in the database.
	 *
	 * @param int $subscription_id primary key of the subscription record
	 *
	 * @return int total number of order that have subscription ID
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $subscription_total = $this->model_checkout_order->getTotalOrdersBySubscriptionId($subscription_id);
	 */
	public function getTotalOrdersBySubscriptionId(int $subscription_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "order` WHERE `subscription_id` = '" . (int)$subscription_id . "' AND `customer_id` = '" . (int)$this->customer->getId() . "'");

		return (int)$query->row['total'];
	}

	/**
	 * Add Total
	 *
	 * Create a new order total record in the database.
	 *
	 * @param int                  $order_id primary key of the order record
	 * @param array<string, mixed> $data     array of data
	 *
	 * @return int
	 *
	 * @example
	 *
	 * $order_total_data = [
	 *     'extension' => '',
	 *     'code'      => '',
	 *     'title'     => 'Order Total Title',
	 *     'value'     => 0.0000,
	 *     'sort_order'
	 * ];
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->addTotal($order_id, $order_total_data);
	 */
	public function addTotal(int $order_id, array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_total` SET `order_id` = '" . (int)$order_id . "', `extension` = '" . $this->db->escape($data['extension']) . "', `code` = '" . $this->db->escape($data['code']) . "', `title` = '" . $this->db->escape($data['title']) . "', `value` = '" . (float)$data['value'] . "', `sort_order` = '" . (int)$data['sort_order'] . "'");

		return $this->db->getLastId();
	}

	/**
	 * Delete Totals
	 *
	 * Delete order total records in the database.
	 *
	 * @param int $order_id primary key of the order record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->deleteTotals($order_id);
	 */
	public function deleteTotals(int $order_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_total` WHERE `order_id` = '" . (int)$order_id . "'");
	}

	/**
	 * Get Totals
	 *
	 * Get the record of the order total records in the database.
	 *
	 * @param int $order_id primary key of the order record
	 *
	 * @return array<int, array<string, mixed>> total records that have order ID
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $order_totals = $this->model_checkout_order->getTotals($order_id);
	 */
	public function getTotals(int $order_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY `sort_order` ASC");

		return $query->rows;
	}

	/**
	 * Add History
	 *
	 * Create a new order history record in the database.
	 *
	 * @param int    $order_id        primary key of the order record
	 * @param int    $order_status_id primary key of the order status record
	 * @param string $comment
	 * @param bool   $notify
	 * @param bool   $override
	 *
	 * @return int
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->addHistory($order_id, $order_status_id, $comment, $notify, $override);
	 */
	public function addHistory(int $order_id, int $order_status_id, string $comment = '', bool $notify = false, bool $override = false): int {
		$order_history_id = 0;

		$order_info = $this->model_checkout_order->getOrder($order_id);

		if ($order_info) {
			// Load subscription model
			$this->load->model('account/customer');

			$customer_info = $this->model_account_customer->getCustomer($order_info['customer_id']);

			// Fraud Detection Enable / Disable
			if ($customer_info && $customer_info['safe']) {
				$safe = true;
			} else {
				$safe = false;
			}

			// Only do the fraud check if the customer is not on the safe list and the order status is changing into the complete or process order status
			if (!$safe && !$override && in_array($order_status_id, array_merge((array)$this->config->get('config_processing_status'), (array)$this->config->get('config_complete_status')))) {
				// Anti-Fraud
				$this->load->model('setting/extension');

				$extensions = $this->model_setting_extension->getExtensionsByType('fraud');

				foreach ($extensions as $extension) {
					if ($this->config->get('fraud_' . $extension['code'] . '_status')) {
						$this->load->model('extension/' . $extension['extension'] . '/fraud/' . $extension['code']);

						$key = 'model_extension_' . $extension['extension'] . '_fraud_' . $extension['code'];

						if (isset($this->{$key}->check)) {
							$fraud_status_id = $this->{$key}->check($order_info);

							if ($fraud_status_id) {
								$order_status_id = $fraud_status_id;
							}
						}
					}
				}
			}

			// Products
			$order_products = $this->model_checkout_order->getProducts($order_id);

			// Subscriptions
			$order_subscriptions = $this->model_checkout_order->getSubscriptions($order_id);

			// Totals
			$order_totals = $this->model_checkout_order->getTotals($order_id);

			// If current order status is not processing or complete but new status is processing or complete then commence completing the order
			if (!in_array($order_info['order_status_id'], array_merge((array)$this->config->get('config_processing_status'), (array)$this->config->get('config_complete_status'))) && in_array($order_status_id, array_merge((array)$this->config->get('config_processing_status'), (array)$this->config->get('config_complete_status')))) {
				// Redeem coupon and reward points
				foreach ($order_totals as $order_total) {
					$this->load->model('extension/' . $order_total['extension'] . '/total/' . $order_total['code']);

					$key = 'model_extension_' . $order_total['extension'] . '_total_' . $order_total['code'];

					if (isset($this->{$key}->confirm)) {
						// Confirm coupon and reward points
						$fraud_status_id = $this->{$key}->confirm($order_info, $order_total);

						// If the balance on the coupon and reward points is not enough to cover the transaction or has already been used then the fraud order status is returned.
						if ($fraud_status_id) {
							$order_status_id = $fraud_status_id;
						}
					}
				}

				foreach ($order_products as $order_product) {
					// Stock subtraction
					$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity` = (`quantity` - " . (int)$order_product['quantity'] . ") WHERE `product_id` = '" . (int)$order_product['product_id'] . "' AND `subtract` = '1'");

					// Stock subtraction from master product
					if ($order_product['master_id']) {
						$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity` = (`quantity` - " . (int)$order_product['quantity'] . ") WHERE `product_id` = '" . (int)$order_product['master_id'] . "' AND `subtract` = '1'");
					}

					$order_options = $this->model_checkout_order->getOptions($order_id, $order_product['order_product_id']);

					foreach ($order_options as $order_option) {
						$this->db->query("UPDATE `" . DB_PREFIX . "product_option_value` SET `quantity` = (`quantity` - " . (int)$order_product['quantity'] . ") WHERE `product_option_value_id` = '" . (int)$order_option['product_option_value_id'] . "' AND `subtract` = '1'");
					}

					// Sellable-while-in-transit (task #13): this line was sold
					// against a specific incoming transfer's spare capacity
					// rather than against real on-hand stock, so reserve that
					// capacity now (mirrors the real stock subtraction above)
					// instead of touching product.quantity at all.
					if (!empty($order_product['is_transit_order']) && !empty($order_product['transit_transfer_id'])) {
						$this->db->query("UPDATE `" . DB_PREFIX . "warehouse_transfer_product` SET `reserved_quantity` = (`reserved_quantity` + " . (int)$order_product['quantity'] . ") WHERE `transfer_id` = '" . (int)$order_product['transit_transfer_id'] . "' AND `product_id` = '" . (int)$order_product['product_id'] . "'");
					}
				}

				// Snapshot cost of goods sold for the Profit/Loss report, at the
				// exact moment stock is deducted. A POS sale records which
				// warehouse it sold from on the session (pos_warehouse_id);
				// a normal storefront order has no such session value and
				// falls back to the store's designated selling warehouse.
				if ($this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "order_product_cost'")->num_rows) {
					$pos_warehouse_id = (int)($this->session->data['pos_warehouse_id'] ?? 0);

					if ($pos_warehouse_id) {
						$cost_warehouse_id = $pos_warehouse_id;
					} else {
						$warehouse_query = $this->db->query("SELECT `warehouse_id` FROM `" . DB_PREFIX . "warehouse` WHERE `is_selling_warehouse` = '1'");
						$cost_warehouse_id = $warehouse_query->num_rows ? (int)$warehouse_query->row['warehouse_id'] : 0;
					}

					if ($cost_warehouse_id) {
						// product_warehouse.cost_price is entered and stored in the
						// warehouse's OWN purchase currency (e.g. a warehouse whose
						// currency_code is AED holds its cost prices in AED - this
						// matches what an admin naturally types into the product's
						// Warehouses tab, and what a Purchase Invoice records). COGS/
						// accounting/profit-loss, however, always deal in the store's
						// base currency (config_currency) alongside order revenue,
						// which MDcart itself always stores in the base currency.
						// So convert once, right here, at the exact moment cost is
						// snapshotted - everything downstream (order_product_cost,
						// the accounting COGS journal line, the Profit/Loss report)
						// can then trust unit_cost is already base-currency.
						$store_currency = (string)$this->config->get('config_currency');

						$warehouse_currency_query = $this->db->query("SELECT `currency_code` FROM `" . DB_PREFIX . "warehouse` WHERE `warehouse_id` = '" . $cost_warehouse_id . "'");
						$warehouse_currency = $warehouse_currency_query->num_rows ? (string)$warehouse_currency_query->row['currency_code'] : $store_currency;

						foreach ($order_products as $order_product) {
							$cost_query = $this->db->query("SELECT `cost_price` FROM `" . DB_PREFIX . "product_warehouse` WHERE `product_id` = '" . (int)$order_product['product_id'] . "' AND `warehouse_id` = '" . $cost_warehouse_id . "'");

							$unit_cost_raw = $cost_query->num_rows ? (float)$cost_query->row['cost_price'] : 0;

							if ($unit_cost_raw && $warehouse_currency && $warehouse_currency !== $store_currency && $this->currency->has($warehouse_currency) && $this->currency->has($store_currency)) {
								$unit_cost = $this->currency->convert($unit_cost_raw, $warehouse_currency, $store_currency);
							} else {
								$unit_cost = $unit_cost_raw;
							}

							$this->db->query("INSERT INTO `" . DB_PREFIX . "order_product_cost` SET `order_id` = '" . (int)$order_id . "', `order_product_id` = '" . (int)$order_product['order_product_id'] . "', `product_id` = '" . (int)$order_product['product_id'] . "', `warehouse_id` = '" . $cost_warehouse_id . "', `quantity` = '" . (int)$order_product['quantity'] . "', `unit_cost` = '" . $unit_cost . "', `date_added` = NOW() ON DUPLICATE KEY UPDATE `warehouse_id` = VALUES(`warehouse_id`), `quantity` = VALUES(`quantity`), `unit_cost` = VALUES(`unit_cost`)");
						}
					}
				}

				// Automatic double-entry bookkeeping postings for this sale (only
				// if the accounting module - install-accounting-schema.php - has
				// been installed). Two journals: one crediting Sales/Shipping/Tax
				// (read straight from this order's own totals, so it always nets
				// to the order's grand total) against whichever bank/cash account
				// is mapped to this payment method, and one for Cost of Goods
				// Sold against Inventory, using the unit costs captured just above.
				// Never auto-reverses on cancellation - use Accounting > Journal
				// Entries to void a specific order's postings by hand if needed.
				if ($this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "journal'")->num_rows) {
					// getOrder() above already json_decode()s payment_method into an
					// array - decoding it again here would cast the array to the
					// string "Array" and silently fail, so just use it as-is.
					$payment_method_data = $order_info['payment_method'] ?? null;
					$payment_code = is_array($payment_method_data) ? (string)($payment_method_data['code'] ?? '') : '';

					if ($payment_code === 'pos.cash') {
						$bank_role = 'pos_cash';
					} elseif ($payment_code === 'pos.card') {
						$bank_role = 'pos_card';
					} else {
						$bank_role = 'website_sales';
					}

					$bank_query = $this->db->query("SELECT `account_id` FROM `" . DB_PREFIX . "bank_account` WHERE `default_role` = '" . $this->db->escape($bank_role) . "' AND `status` = '1'");
					$bank_account_id = $bank_query->num_rows ? (int)$bank_query->row['account_id'] : 0;

					if ($bank_account_id) {
						$account_cache = [];

						$get_account_id = function (string $code) use (&$account_cache) {
							if (isset($account_cache[$code])) {
								return $account_cache[$code];
							}

							$query = $this->db->query("SELECT `account_id` FROM `" . DB_PREFIX . "account` WHERE `code` = '" . $this->db->escape($code) . "'");

							$account_cache[$code] = $query->num_rows ? (int)$query->row['account_id'] : 0;

							return $account_cache[$code];
						};

						$code_map = [
							'sub_total' => '4100',
							'shipping'  => '4200',
							'tax'       => '2200'
						];

						$revenue_lines = [];

						foreach ($order_totals as $order_total) {
							if ($order_total['code'] === 'total') {
								continue;
							}

							$value = (float)$order_total['value'];

							if ($value == 0.0) {
								continue;
							}

							$account_code = $code_map[$order_total['code']] ?? ($value < 0 ? '4900' : '4910');
							$revenue_account_id = $get_account_id($account_code);

							if (!$revenue_account_id) {
								continue;
							}

							if ($value >= 0) {
								$revenue_lines[] = ['account_id' => $revenue_account_id, 'debit' => 0, 'credit' => $value, 'description' => $order_total['title']];
							} else {
								$revenue_lines[] = ['account_id' => $revenue_account_id, 'debit' => abs($value), 'credit' => 0, 'description' => $order_total['title']];
							}
						}

						if ($revenue_lines) {
							$order_total_value = (float)$order_info['total'];

							$lines = array_merge([['account_id' => $bank_account_id, 'debit' => $order_total_value, 'credit' => 0, 'description' => 'Sale receipt']], $revenue_lines);

							$this->db->query("INSERT INTO `" . DB_PREFIX . "journal` SET `journal_number` = '', `reference_type` = 'order_sale', `reference_id` = '" . (int)$order_id . "', `description` = '" . $this->db->escape('Sale - Order #' . $order_id) . "', `date_added` = NOW(), `user_id` = '0', `status` = 'posted', `reversal_of` = '0'");

							$journal_id = $this->db->getLastId();

							// Use the journal's own id for the number (JE-000042) - avoids
							// the race condition of a separate "MAX(journal_id)+1" lookup
							// that the admin-side model uses for manually-created entries.
							$this->db->query("UPDATE `" . DB_PREFIX . "journal` SET `journal_number` = 'JE-" . str_pad((string)$journal_id, 6, '0', STR_PAD_LEFT) . "' WHERE `journal_id` = '" . $journal_id . "'");

							foreach ($lines as $line) {
								$this->db->query("INSERT INTO `" . DB_PREFIX . "journal_line` SET `journal_id` = '" . $journal_id . "', `account_id` = '" . (int)$line['account_id'] . "', `debit` = '" . (float)$line['debit'] . "', `credit` = '" . (float)$line['credit'] . "', `description` = '" . $this->db->escape((string)$line['description']) . "'");
							}
						}

						// Cost of Goods Sold vs Inventory, using the unit costs
						// captured into order_product_cost just above.
						$cogs_account_id = $get_account_id('5100');
						$inventory_account_id = $get_account_id('1300');

						if ($cogs_account_id && $inventory_account_id) {
							$total_cogs = 0.0;

							foreach ($order_products as $order_product) {
								$cost_query = $this->db->query("SELECT `unit_cost` FROM `" . DB_PREFIX . "order_product_cost` WHERE `order_product_id` = '" . (int)$order_product['order_product_id'] . "'");

								if ($cost_query->num_rows) {
									$total_cogs += (float)$cost_query->row['unit_cost'] * (int)$order_product['quantity'];
								}
							}

							if ($total_cogs > 0) {
								$this->db->query("INSERT INTO `" . DB_PREFIX . "journal` SET `journal_number` = '', `reference_type` = 'order_cogs', `reference_id` = '" . (int)$order_id . "', `description` = '" . $this->db->escape('COGS - Order #' . $order_id) . "', `date_added` = NOW(), `user_id` = '0', `status` = 'posted', `reversal_of` = '0'");

								$cogs_journal_id = $this->db->getLastId();

								$this->db->query("UPDATE `" . DB_PREFIX . "journal` SET `journal_number` = 'JE-" . str_pad((string)$cogs_journal_id, 6, '0', STR_PAD_LEFT) . "' WHERE `journal_id` = '" . $cogs_journal_id . "'");

								$this->db->query("INSERT INTO `" . DB_PREFIX . "journal_line` SET `journal_id` = '" . $cogs_journal_id . "', `account_id` = '" . $cogs_account_id . "', `debit` = '" . $total_cogs . "', `credit` = '0', `description` = 'Cost of goods sold'");
								$this->db->query("INSERT INTO `" . DB_PREFIX . "journal_line` SET `journal_id` = '" . $cogs_journal_id . "', `account_id` = '" . $inventory_account_id . "', `debit` = '0', `credit` = '" . $total_cogs . "', `description` = 'Inventory reduction'");
							}
						}
					}
				}
			}

			// If order status becomes complete status
			if (!in_array($order_info['order_status_id'], (array)$this->config->get('config_complete_status')) && in_array($order_status_id, (array)$this->config->get('config_complete_status'))) {
				// Affiliate add commission if complete status
				if ($order_info['affiliate_id'] && $this->config->get('config_affiliate_auto')) {
					// Add commission if sale is linked to affiliate referral.
					$this->load->model('account/transaction');

					if (!$this->model_account_transaction->getTotalTransactionsByOrderId($order_id)) {
						$this->model_account_transaction->addTransaction($order_info['affiliate_id'], $order_id, $this->language->get('text_order_id') . ' #' . $order_id, (float)$order_info['commission']);
					}
				}

				// Add subscription
				$this->load->model('checkout/subscription');

				foreach ($order_subscriptions as $key => $order_subscription) {
					$subscription_product_data = [];

					foreach ($order_subscriptions as $subscription) {
						if ($subscription['subscription_plan_id'] == $order_subscription['subscription_plan_id']) {
							$subscription_product_data[] = [
								'option'      => $this->model_checkout_order->getOptions($order_id, $order_subscription['order_product_id']),
								'trial_price' => $order_subscription['trial_price'],
								'trial_tax'   => $order_subscription['trial_tax'],
								'price'       => $order_subscription['price'],
								'tax'         => $order_subscription['tax']
							] + $order_subscription;

							unset($order_subscriptions[$key]);
						}
					}

					$subscription_data = [
						'trial_price'          => array_sum(array_column($subscription_product_data, 'trial_price')),
						'trial_tax'            => array_sum(array_column($subscription_product_data, 'trial_tax')),
						'price'                => array_sum(array_column($subscription_product_data, 'price')),
						'tax'                  => array_sum(array_column($subscription_product_data, 'tax')),
						'subscription_product' => $subscription_product_data,
						'language'             => $order_info['language_code'],
						'currency_code'        => $order_info['currency_code'],
						'currency_value'       => $order_info['currency_value']
					] + $order_info + $order_subscription;

					$subscription_info = $this->model_checkout_subscription->getProductByOrderProductId($order_id, $order_subscription['order_product_id']);

					if (!$subscription_info) {
						$subscription_id = $this->model_checkout_subscription->addSubscription($subscription_data);
					} else {
						$this->model_checkout_subscription->editSubscription($subscription_info['subscription_id'], $subscription_data);

						$subscription_id = $subscription_info['subscription_id'];
					}

					// Add history and set active subscription
					$this->model_checkout_subscription->addHistory($subscription_id, (int)$this->config->get('config_subscription_active_status_id'));
				}
			}

			// If old order status is the processing or complete status but new status is not then commence restock, and remove coupon and reward history
			if (in_array($order_info['order_status_id'], array_merge((array)$this->config->get('config_processing_status'), (array)$this->config->get('config_complete_status'))) && !in_array($order_status_id, array_merge((array)$this->config->get('config_processing_status'), (array)$this->config->get('config_complete_status')))) {
				// Restock
				foreach ($order_products as $order_product) {
					$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity` = (`quantity` + " . (int)$order_product['quantity'] . ") WHERE `product_id` = '" . (int)$order_product['product_id'] . "' AND `subtract` = '1'");

					// Restock the master product stock level if product is a variant
					if ($order_product['master_id']) {
						$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity` = (`quantity` + " . (int)$order_product['quantity'] . ") WHERE `product_id` = '" . (int)$order_product['master_id'] . "' AND `subtract` = '1'");
					}

					$order_options = $this->model_checkout_order->getOptions($order_id, $order_product['order_product_id']);

					foreach ($order_options as $order_option) {
						$this->db->query("UPDATE `" . DB_PREFIX . "product_option_value` SET `quantity` = (`quantity` + " . (int)$order_product['quantity'] . ") WHERE `product_option_value_id` = '" . (int)$order_option['product_option_value_id'] . "' AND `subtract` = '1'");
					}

					// Sellable-while-in-transit (task #13): free up the
					// capacity this line had reserved against its transfer,
					// mirroring the real stock restock above.
					if (!empty($order_product['is_transit_order']) && !empty($order_product['transit_transfer_id'])) {
						$this->db->query("UPDATE `" . DB_PREFIX . "warehouse_transfer_product` SET `reserved_quantity` = GREATEST(0, `reserved_quantity` - " . (int)$order_product['quantity'] . ") WHERE `transfer_id` = '" . (int)$order_product['transit_transfer_id'] . "' AND `product_id` = '" . (int)$order_product['product_id'] . "'");
					}
				}

				// Remove coupon and reward points history
				foreach ($order_totals as $order_total) {
					$this->load->model('extension/' . $order_total['extension'] . '/total/' . $order_total['code']);

					$key = 'model_extension_' . $order_total['extension'] . '_total_' . $order_total['code'];

					if (isset($this->{$key}->unconfirm)) {
						$this->{$key}->unconfirm($order_info);
					}
				}
			}

			// If order status is no longer complete status
			if (in_array($order_info['order_status_id'], (array)$this->config->get('config_complete_status')) && !in_array($order_status_id, (array)$this->config->get('config_complete_status'))) {
				// Suspend subscription
				$this->load->model('checkout/subscription');

				foreach ($order_products as $order_product) {
					// Subscription status set to suspend
					$subscription_info = $this->model_checkout_subscription->getProductByOrderProductId($order_id, $order_product['order_product_id']);

					if ($subscription_info) {
						// Add history and set suspended subscription
						$this->model_checkout_subscription->addHistory($subscription_info['subscription_id'], (int)$this->config->get('config_subscription_suspended_status_id'));
					}
				}

				// Affiliate remove commission.
				if ($order_info['affiliate_id']) {
					$this->load->model('account/transaction');

					$this->model_account_transaction->deleteTransactions($order_info['customer_id'], $order_id);
				}
			}

			// Update the DB with the new statuses
			$this->model_checkout_order->editOrderStatusId($order_id, $order_status_id);

			$this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET `order_id` = '" . (int)$order_id . "', `order_status_id` = '" . (int)$order_status_id . "', `notify` = '" . (int)$notify . "', `comment` = '" . $this->db->escape($comment) . "', `date_added` = NOW()");

			$order_history_id = $this->db->getLastId();

			$this->cache->delete('product');
		}

		return $order_history_id;
	}

	/**
	 * Delete Order Histories
	 *
	 * Delete order history records in the database.
	 *
	 * @param int $order_id primary key of the order record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('checkout/order');
	 *
	 * $this->model_checkout_order->deleteHistories($order_id);
	 */
	public function deleteHistories(int $order_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_history` WHERE `order_id` = '" . (int)$order_id . "'");
	}
}
