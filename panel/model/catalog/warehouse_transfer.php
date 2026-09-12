<?php
namespace MDcart\Admin\Model\Catalog;
/**
 * Class WarehouseTransfer
 *
 * Inter-warehouse transfer ("حواله"): moves stock from one warehouse to
 * another through pending -> in_transit -> received (or cancelled). Stock is
 * removed from the source warehouse the moment a transfer goes in_transit,
 * and only added to the destination warehouse once it's marked received -
 * so in-transit stock is genuinely unavailable at both ends while it moves,
 * matching how a physical shipment works.
 *
 * If the destination warehouse is the store's selling warehouse, receiving a
 * transfer also nudges the affected products' live price by their per-unit
 * share of the transfer's shipping cost, so freight cost is automatically
 * reflected in what online customers see - no manual price edit needed.
 *
 * Can be loaded using $this->load->model('catalog/warehouse_transfer');
 *
 * @package MDcart\Admin\Model\Catalog
 */
class WarehouseTransfer extends \MDcart\System\Engine\Model {
	/**
	 * Add Transfer
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return int
	 */
	public function addTransfer(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse_transfer` SET `from_warehouse_id` = '" . (int)$data['from_warehouse_id'] . "', `to_warehouse_id` = '" . (int)$data['to_warehouse_id'] . "', `status` = 'pending', `presell_online` = '" . (int)!empty($data['presell_online']) . "', `shipping_cost` = '" . (float)($data['shipping_cost'] ?? 0) . "', `estimated_delivery_date` = " . ($data['estimated_delivery_date'] ? "'" . $this->db->escape((string)$data['estimated_delivery_date']) . "'" : "NULL") . ", `comment` = '" . $this->db->escape((string)($data['comment'] ?? '')) . "', `user_id` = '" . (int)($data['user_id'] ?? 0) . "', `date_added` = NOW(), `date_modified` = NOW()");

		$transfer_id = $this->db->getLastId();

		$this->setProducts($transfer_id, (int)$data['from_warehouse_id'], $data['products'] ?? []);

		$this->addHistoryRow($transfer_id, 'pending', $this->language->get('text_history_created') ?: 'Transfer created.');

		return $transfer_id;
	}

	/**
	 * Edit Transfer header + (only while still pending) product lines.
	 *
	 * @param int                  $transfer_id
	 * @param array<string, mixed> $data
	 *
	 * @return void
	 */
	public function editTransfer(int $transfer_id, array $data): void {
		$transfer_info = $this->getTransfer($transfer_id);

		if (!$transfer_info) {
			return;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "warehouse_transfer` SET `from_warehouse_id` = '" . (int)$data['from_warehouse_id'] . "', `to_warehouse_id` = '" . (int)$data['to_warehouse_id'] . "', `presell_online` = '" . (int)!empty($data['presell_online']) . "', `shipping_cost` = '" . (float)($data['shipping_cost'] ?? 0) . "', `estimated_delivery_date` = " . ($data['estimated_delivery_date'] ? "'" . $this->db->escape((string)$data['estimated_delivery_date']) . "'" : "NULL") . ", `comment` = '" . $this->db->escape((string)($data['comment'] ?? '')) . "', `date_modified` = NOW() WHERE `transfer_id` = '" . (int)$transfer_id . "'");

		if ($transfer_info['status'] === 'pending') {
			$this->setProducts($transfer_id, (int)$data['from_warehouse_id'], $data['products'] ?? []);
		}
	}

	/**
	 * Replace a pending transfer's product lines, snapshotting each line's
	 * current cost price at the source warehouse.
	 *
	 * @param int                        $transfer_id
	 * @param int                        $from_warehouse_id
	 * @param array<int, array<string, mixed>> $products
	 *
	 * @return void
	 */
	private function setProducts(int $transfer_id, int $from_warehouse_id, array $products): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "warehouse_transfer_product` WHERE `transfer_id` = '" . (int)$transfer_id . "'");

		foreach ($products as $product) {
			$product_id = (int)($product['product_id'] ?? 0);
			$quantity = (int)($product['quantity'] ?? 0);

			if (!$product_id || $quantity < 1) {
				continue;
			}

			$query = $this->db->query("SELECT `cost_price` FROM `" . DB_PREFIX . "product_warehouse` WHERE `product_id` = '" . $product_id . "' AND `warehouse_id` = '" . $from_warehouse_id . "'");

			$unit_cost_price = $query->num_rows ? (float)$query->row['cost_price'] : 0;

			$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse_transfer_product` SET `transfer_id` = '" . (int)$transfer_id . "', `product_id` = '" . $product_id . "', `quantity` = '" . $quantity . "', `unit_cost_price` = '" . $unit_cost_price . "'");
		}
	}

	/**
	 * Get Transfer
	 *
	 * @param int $transfer_id
	 *
	 * @return array<string, mixed>
	 */
	public function getTransfer(int $transfer_id): array {
		$query = $this->db->query("SELECT `t`.*, `fw`.`name` AS `from_warehouse_name`, `fw`.`currency_code` AS `from_currency_code`, `tw`.`name` AS `to_warehouse_name`, `tw`.`currency_code` AS `to_currency_code`, `tw`.`is_selling_warehouse` AS `to_is_selling_warehouse` FROM `" . DB_PREFIX . "warehouse_transfer` `t` LEFT JOIN `" . DB_PREFIX . "warehouse` `fw` ON (`fw`.`warehouse_id` = `t`.`from_warehouse_id`) LEFT JOIN `" . DB_PREFIX . "warehouse` `tw` ON (`tw`.`warehouse_id` = `t`.`to_warehouse_id`) WHERE `t`.`transfer_id` = '" . (int)$transfer_id . "'");

		return $query->row;
	}

	/**
	 * Get Transfers
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getTransfers(array $data = []): array {
		$sql = "SELECT `t`.*, `fw`.`name` AS `from_warehouse_name`, `tw`.`name` AS `to_warehouse_name` FROM `" . DB_PREFIX . "warehouse_transfer` `t` LEFT JOIN `" . DB_PREFIX . "warehouse` `fw` ON (`fw`.`warehouse_id` = `t`.`from_warehouse_id`) LEFT JOIN `" . DB_PREFIX . "warehouse` `tw` ON (`tw`.`warehouse_id` = `t`.`to_warehouse_id`)";

		$sql .= " ORDER BY `t`.`date_added` DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			$start = isset($data['start']) ? (int)$data['start'] : 0;
			$limit = isset($data['limit']) ? (int)$data['limit'] : 10;

			if ($start < 0) {
				$start = 0;
			}

			if ($limit < 1) {
				$limit = 10;
			}

			$sql .= " LIMIT " . $start . "," . $limit;
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Get Total Transfers
	 *
	 * @return int
	 */
	public function getTotalTransfers(): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "warehouse_transfer`");

		return (int)$query->row['total'];
	}

	/**
	 * Get Transfer Products
	 *
	 * @param int $transfer_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getTransferProducts(int $transfer_id): array {
		$query = $this->db->query("SELECT `tp`.*, `pd`.`name` FROM `" . DB_PREFIX . "warehouse_transfer_product` `tp` LEFT JOIN `" . DB_PREFIX . "product_description` `pd` ON (`pd`.`product_id` = `tp`.`product_id` AND `pd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "') WHERE `tp`.`transfer_id` = '" . (int)$transfer_id . "'");

		return $query->rows;
	}

	/**
	 * Get Transfer Histories
	 *
	 * @param int $transfer_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getTransferHistories(int $transfer_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "warehouse_transfer_history` WHERE `transfer_id` = '" . (int)$transfer_id . "' ORDER BY `date_added` ASC, `warehouse_transfer_history_id` ASC");

		return $query->rows;
	}

	/**
	 * Change a transfer's status, applying stock movement + landed-cost/price
	 * side effects exactly once per real transition. Also accepts a manual
	 * estimated/actual delivery date correction alongside the status change.
	 *
	 * @param int                  $transfer_id
	 * @param string               $status
	 * @param string               $comment
	 * @param array<string, mixed> $dates
	 *
	 * @return void
	 */
	public function changeStatus(int $transfer_id, string $status, string $comment = '', array $dates = []): void {
		$transfer_info = $this->getTransfer($transfer_id);

		if (!$transfer_info) {
			return;
		}

		$previous_status = $transfer_info['status'];

		if ($previous_status !== $status) {
			if ($status === 'in_transit' && $previous_status === 'pending') {
				$this->moveStock($transfer_id, (int)$transfer_info['from_warehouse_id'], -1);
			}

			if ($status === 'received' && $previous_status !== 'received') {
				$this->moveStock($transfer_id, (int)$transfer_info['to_warehouse_id'], 1);
				$this->applyLandedCost($transfer_id, $transfer_info);

				if (empty($dates['actual_delivery_date'])) {
					$dates['actual_delivery_date'] = date('Y-m-d');
				}
			}

			if ($status === 'cancelled' && $previous_status === 'in_transit') {
				// Refuse to cancel a transfer that online customers have
				// already been allowed to buy against (task #13 - "sellable
				// while in transit") and whose incoming units are still
				// committed to a live order. Cancelling would silently strand
				// those orders with no real stock ever coming to cover them.
				// The admin must resolve those orders (cancel/refund them, or
				// find replacement stock) before the transfer itself can be
				// cancelled.
				$reserved_query = $this->db->query("SELECT COALESCE(SUM(`reserved_quantity`), 0) AS `total` FROM `" . DB_PREFIX . "warehouse_transfer_product` WHERE `transfer_id` = '" . (int)$transfer_id . "'");

				if ((int)$reserved_query->row['total'] > 0) {
					throw new \Exception($this->language->get('error_cancel_reserved') ?: 'This transfer has units already sold to online customers while in transit and cannot be cancelled until those orders are resolved.');
				}

				// Goods never arrived - put the stock back where it came from.
				$this->moveStock($transfer_id, (int)$transfer_info['from_warehouse_id'], 1);
			}
		}

		$update = "`status` = '" . $this->db->escape($status) . "', `date_modified` = NOW()";

		if (isset($dates['estimated_delivery_date'])) {
			$update .= ", `estimated_delivery_date` = " . ($dates['estimated_delivery_date'] ? "'" . $this->db->escape((string)$dates['estimated_delivery_date']) . "'" : "NULL");
		}

		if (isset($dates['actual_delivery_date'])) {
			$update .= ", `actual_delivery_date` = " . ($dates['actual_delivery_date'] ? "'" . $this->db->escape((string)$dates['actual_delivery_date']) . "'" : "NULL");
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "warehouse_transfer` SET " . $update . " WHERE `transfer_id` = '" . (int)$transfer_id . "'");

		$this->addHistoryRow($transfer_id, $status, $comment);
	}

	/**
	 * Add/subtract every transfer line's quantity to/from one warehouse's stock.
	 * direction: -1 to remove (departing), +1 to add (arriving). Never lets a
	 * warehouse's stock go below 0. Keeps oc_product.quantity in sync if this
	 * warehouse happens to be the selling warehouse.
	 *
	 * @param int $transfer_id
	 * @param int $warehouse_id
	 * @param int $direction
	 *
	 * @return void
	 */
	private function moveStock(int $transfer_id, int $warehouse_id, int $direction): void {
		$lines = $this->db->query("SELECT `product_id`, `quantity` FROM `" . DB_PREFIX . "warehouse_transfer_product` WHERE `transfer_id` = '" . (int)$transfer_id . "'");

		$warehouse_query = $this->db->query("SELECT `is_selling_warehouse` FROM `" . DB_PREFIX . "warehouse` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");
		$is_selling_warehouse = $warehouse_query->num_rows && $warehouse_query->row['is_selling_warehouse'];

		foreach ($lines->rows as $line) {
			$product_id = (int)$line['product_id'];
			$delta = $direction * (int)$line['quantity'];

			$this->db->query("INSERT INTO `" . DB_PREFIX . "product_warehouse` (`product_id`, `warehouse_id`, `quantity`, `cost_price`, `date_modified`) VALUES ('" . $product_id . "', '" . (int)$warehouse_id . "', GREATEST(0, " . $delta . "), 0.0000, NOW()) ON DUPLICATE KEY UPDATE `quantity` = GREATEST(0, `quantity` + (" . $delta . ")), `date_modified` = NOW()");

			if ($is_selling_warehouse) {
				$stock_query = $this->db->query("SELECT `quantity` FROM `" . DB_PREFIX . "product_warehouse` WHERE `product_id` = '" . $product_id . "' AND `warehouse_id` = '" . (int)$warehouse_id . "'");

				$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity` = '" . (int)$stock_query->row['quantity'] . "' WHERE `product_id` = '" . $product_id . "'");
			}
		}
	}

	/**
	 * On receipt, distribute the transfer's shipping cost evenly per unit,
	 * add it on top of each line's snapshotted source cost price (converted
	 * into the destination warehouse's currency) to get the destination's new
	 * landed cost, and - if the destination is the selling warehouse - nudge
	 * the live storefront price by the freight portion so customers
	 * automatically see it, without touching the admin's own margin choice.
	 *
	 * @param int                  $transfer_id
	 * @param array<string, mixed> $transfer_info
	 *
	 * @return void
	 */
	private function applyLandedCost(int $transfer_id, array $transfer_info): void {
		$lines = $this->db->query("SELECT `product_id`, `quantity`, `unit_cost_price` FROM `" . DB_PREFIX . "warehouse_transfer_product` WHERE `transfer_id` = '" . (int)$transfer_id . "'");

		$total_quantity = 0;

		foreach ($lines->rows as $line) {
			$total_quantity += (int)$line['quantity'];
		}

		if (!$total_quantity) {
			return;
		}

		$default_currency = (string)$this->config->get('config_currency');
		$from_currency = (string)$transfer_info['from_currency_code'];
		$to_currency = (string)$transfer_info['to_currency_code'];

		$shipping_cost_default = (float)$transfer_info['shipping_cost'];
		$freight_per_unit_default = $shipping_cost_default / $total_quantity;

		$to_warehouse_id = (int)$transfer_info['to_warehouse_id'];
		$is_selling_warehouse = !empty($transfer_info['to_is_selling_warehouse']);

		foreach ($lines->rows as $line) {
			$product_id = (int)$line['product_id'];
			$quantity = (int)$line['quantity'];

			// Landed cost, stored in the destination warehouse's own currency.
			$unit_cost_default = $this->convertCurrency((float)$line['unit_cost_price'], $from_currency, $default_currency);
			$landed_cost_default = $unit_cost_default + $freight_per_unit_default;
			$landed_cost_destination_currency = $this->convertCurrency($landed_cost_default, $default_currency, $to_currency);

			$this->db->query("UPDATE `" . DB_PREFIX . "product_warehouse` SET `cost_price` = '" . (float)$landed_cost_destination_currency . "', `date_modified` = NOW() WHERE `product_id` = '" . $product_id . "' AND `warehouse_id` = '" . $to_warehouse_id . "'");

			if ($is_selling_warehouse && $freight_per_unit_default != 0) {
				$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `price` = `price` + '" . (float)$freight_per_unit_default . "', `warehouse_freight_surcharge` = `warehouse_freight_surcharge` + '" . (float)$freight_per_unit_default . "' WHERE `product_id` = '" . $product_id . "'");
			}
		}
	}

	/**
	 * Convert an amount between two currencies using the exchange rates the
	 * admin already manages under System > Localisation > Currency - this
	 * model has no access to the storefront's currency library, so it reads
	 * oc_currency directly the same way that library does internally.
	 *
	 * @param float  $amount
	 * @param string $from
	 * @param string $to
	 *
	 * @return float
	 */
	private function convertCurrency(float $amount, string $from, string $to): float {
		if (!$amount || $from === $to) {
			return $amount;
		}

		$query = $this->db->query("SELECT `code`, `value` FROM `" . DB_PREFIX . "currency` WHERE `code` IN ('" . $this->db->escape($from) . "', '" . $this->db->escape($to) . "')");

		$values = [];

		foreach ($query->rows as $row) {
			$values[$row['code']] = (float)$row['value'];
		}

		$from_value = $values[$from] ?? 1;
		$to_value = $values[$to] ?? 1;

		if (!$from_value) {
			$from_value = 1;
		}

		return $amount * ($to_value / $from_value);
	}

	/**
	 * @param int    $transfer_id
	 * @param string $status
	 * @param string $comment
	 *
	 * @return void
	 */
	private function addHistoryRow(int $transfer_id, string $status, string $comment = ''): void {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse_transfer_history` SET `transfer_id` = '" . (int)$transfer_id . "', `status` = '" . $this->db->escape($status) . "', `comment` = '" . $this->db->escape($comment) . "', `date_added` = NOW()");
	}
}
