<?php
namespace Opencart\Admin\Model\Catalog;
/**
 * Class PurchaseInvoice
 *
 * A purchase invoice ("فاکتور خرید") is an append-only record: it is the
 * normal way stock and cost price enter a warehouse (rather than typing
 * numbers directly into the product's Warehouses tab). Confirming one
 * immediately increases the chosen warehouse's stock and folds the new cost
 * into a running weighted-average cost price, in that warehouse's own
 * currency. If the warehouse happens to be the selling warehouse,
 * oc_product.quantity is kept in sync automatically.
 *
 * Can be loaded using $this->load->model('catalog/purchase_invoice');
 *
 * @package Opencart\Admin\Model\Catalog
 */
class PurchaseInvoice extends \Opencart\System\Engine\Model {
	/**
	 * Add Purchase Invoice - applies the stock/cost increase immediately.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return int
	 */
	public function addPurchaseInvoice(array $data): int {
		$warehouse_id = (int)$data['warehouse_id'];

		$this->db->query("INSERT INTO `" . DB_PREFIX . "purchase_invoice` SET `warehouse_id` = '" . $warehouse_id . "', `supplier_name` = '" . $this->db->escape((string)($data['supplier_name'] ?? '')) . "', `invoice_number` = '" . $this->db->escape((string)($data['invoice_number'] ?? '')) . "', `invoice_date` = " . (!empty($data['invoice_date']) ? "'" . $this->db->escape((string)$data['invoice_date']) . "'" : "NULL") . ", `comment` = '" . $this->db->escape((string)($data['comment'] ?? '')) . "', `user_id` = '" . (int)($data['user_id'] ?? 0) . "', `date_added` = NOW()");

		$purchase_invoice_id = $this->db->getLastId();

		$warehouse_query = $this->db->query("SELECT `is_selling_warehouse` FROM `" . DB_PREFIX . "warehouse` WHERE `warehouse_id` = '" . $warehouse_id . "'");
		$is_selling_warehouse = $warehouse_query->num_rows && $warehouse_query->row['is_selling_warehouse'];

		foreach ((array)($data['products'] ?? []) as $product) {
			$product_id = (int)($product['product_id'] ?? 0);
			$quantity = (int)($product['quantity'] ?? 0);
			$unit_cost = (float)($product['unit_cost'] ?? 0);
			$barcode = (string)($product['barcode'] ?? '');

			if (!$product_id || $quantity < 1) {
				continue;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "purchase_invoice_product` SET `purchase_invoice_id` = '" . (int)$purchase_invoice_id . "', `product_id` = '" . $product_id . "', `quantity` = '" . $quantity . "', `unit_cost` = '" . $unit_cost . "', `barcode` = '" . $this->db->escape($barcode) . "'");

			// Weighted-average cost: fold this purchase in with whatever stock/cost
			// this warehouse already has for the product.
			$existing_query = $this->db->query("SELECT `quantity`, `cost_price` FROM `" . DB_PREFIX . "product_warehouse` WHERE `product_id` = '" . $product_id . "' AND `warehouse_id` = '" . $warehouse_id . "'");

			$existing_quantity = $existing_query->num_rows ? (int)$existing_query->row['quantity'] : 0;
			$existing_cost = $existing_query->num_rows ? (float)$existing_query->row['cost_price'] : 0;

			$new_quantity = $existing_quantity + $quantity;
			$new_cost = $new_quantity > 0 ? (($existing_quantity * $existing_cost) + ($quantity * $unit_cost)) / $new_quantity : $unit_cost;

			$this->db->query("INSERT INTO `" . DB_PREFIX . "product_warehouse` (`product_id`, `warehouse_id`, `quantity`, `cost_price`, `date_modified`) VALUES ('" . $product_id . "', '" . $warehouse_id . "', '" . $new_quantity . "', '" . $new_cost . "', NOW()) ON DUPLICATE KEY UPDATE `quantity` = '" . $new_quantity . "', `cost_price` = '" . $new_cost . "', `date_modified` = NOW()");

			if ($is_selling_warehouse) {
				$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity` = '" . $new_quantity . "' WHERE `product_id` = '" . $product_id . "'");
			}
		}

		return $purchase_invoice_id;
	}

	/**
	 * Get Purchase Invoice
	 *
	 * @param int $purchase_invoice_id
	 *
	 * @return array<string, mixed>
	 */
	public function getPurchaseInvoice(int $purchase_invoice_id): array {
		$query = $this->db->query("SELECT `pi`.*, `w`.`name` AS `warehouse_name`, `w`.`currency_code` FROM `" . DB_PREFIX . "purchase_invoice` `pi` LEFT JOIN `" . DB_PREFIX . "warehouse` `w` ON (`w`.`warehouse_id` = `pi`.`warehouse_id`) WHERE `pi`.`purchase_invoice_id` = '" . (int)$purchase_invoice_id . "'");

		return $query->row;
	}

	/**
	 * Get Purchase Invoices
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getPurchaseInvoices(array $data = []): array {
		$sql = "SELECT `pi`.*, `w`.`name` AS `warehouse_name` FROM `" . DB_PREFIX . "purchase_invoice` `pi` LEFT JOIN `" . DB_PREFIX . "warehouse` `w` ON (`w`.`warehouse_id` = `pi`.`warehouse_id`) ORDER BY `pi`.`date_added` DESC";

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
	 * Get Total Purchase Invoices
	 *
	 * @return int
	 */
	public function getTotalPurchaseInvoices(): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "purchase_invoice`");

		return (int)$query->row['total'];
	}

	/**
	 * Get Purchase Invoice Products
	 *
	 * @param int $purchase_invoice_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getPurchaseInvoiceProducts(int $purchase_invoice_id): array {
		$query = $this->db->query("SELECT `pip`.*, `pd`.`name` FROM `" . DB_PREFIX . "purchase_invoice_product` `pip` LEFT JOIN `" . DB_PREFIX . "product_description` `pd` ON (`pd`.`product_id` = `pip`.`product_id` AND `pd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "') WHERE `pip`.`purchase_invoice_id` = '" . (int)$purchase_invoice_id . "'");

		return $query->rows;
	}
}
