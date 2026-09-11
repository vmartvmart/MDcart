<?php
namespace Opencart\Admin\Model\Catalog;
/**
 * Class Warehouse
 *
 * Can be loaded using $this->load->model('catalog/warehouse');
 *
 * @package Opencart\Admin\Model\Catalog
 */
class Warehouse extends \Opencart\System\Engine\Model {
	/**
	 * Add Warehouse
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return int
	 */
	public function addWarehouse(array $data): int {
		if (!empty($data['is_selling_warehouse'])) {
			$this->db->query("UPDATE `" . DB_PREFIX . "warehouse` SET `is_selling_warehouse` = 0");
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse` SET `name` = '" . $this->db->escape((string)$data['name']) . "', `address` = '" . $this->db->escape((string)($data['address'] ?? '')) . "', `currency_code` = '" . $this->db->escape((string)$data['currency_code']) . "', `is_selling_warehouse` = '" . (int)!empty($data['is_selling_warehouse']) . "', `status` = '" . (int)!empty($data['status']) . "', `sort_order` = '" . (int)($data['sort_order'] ?? 0) . "', `date_added` = NOW(), `date_modified` = NOW()");

		$warehouse_id = $this->db->getLastId();

		if (!empty($data['is_selling_warehouse'])) {
			$this->syncSellingWarehouseStock();
		}

		return $warehouse_id;
	}

	/**
	 * Edit Warehouse
	 *
	 * @param int                  $warehouse_id
	 * @param array<string, mixed> $data
	 *
	 * @return void
	 */
	public function editWarehouse(int $warehouse_id, array $data): void {
		if (!empty($data['is_selling_warehouse'])) {
			$this->db->query("UPDATE `" . DB_PREFIX . "warehouse` SET `is_selling_warehouse` = 0 WHERE `warehouse_id` != '" . (int)$warehouse_id . "'");
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "warehouse` SET `name` = '" . $this->db->escape((string)$data['name']) . "', `address` = '" . $this->db->escape((string)($data['address'] ?? '')) . "', `currency_code` = '" . $this->db->escape((string)$data['currency_code']) . "', `is_selling_warehouse` = '" . (int)!empty($data['is_selling_warehouse']) . "', `status` = '" . (int)!empty($data['status']) . "', `sort_order` = '" . (int)($data['sort_order'] ?? 0) . "', `date_modified` = NOW() WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");

		$this->syncSellingWarehouseStock();
	}

	/**
	 * Delete Warehouse
	 *
	 * @param int $warehouse_id
	 *
	 * @return void
	 */
	public function deleteWarehouse(int $warehouse_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "warehouse` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "product_warehouse` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");
	}

	/**
	 * Get Warehouse
	 *
	 * @param int $warehouse_id
	 *
	 * @return array<string, mixed>
	 */
	public function getWarehouse(int $warehouse_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "warehouse` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");

		return $query->row;
	}

	/**
	 * Get the single warehouse currently marked as the storefront's selling warehouse, if any.
	 *
	 * @return array<string, mixed>
	 */
	public function getSellingWarehouse(): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "warehouse` WHERE `is_selling_warehouse` = '1' LIMIT 1");

		return $query->row;
	}

	/**
	 * Get Warehouses
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getWarehouses(array $data = []): array {
		$sql = "SELECT * FROM `" . DB_PREFIX . "warehouse`";

		$sql .= " ORDER BY `sort_order` ASC, `name` ASC";

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
	 * Get Total Warehouses
	 *
	 * @return int
	 */
	public function getTotalWarehouses(): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "warehouse`");

		return (int)$query->row['total'];
	}

	/**
	 * Get every warehouse row for one product (product_id => stock/cost per warehouse).
	 *
	 * @param int $product_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getProductWarehouses(int $product_id): array {
		$query = $this->db->query("SELECT `w`.`warehouse_id`, `w`.`name`, `w`.`currency_code`, `w`.`is_selling_warehouse`, COALESCE(`pw`.`quantity`, 0) AS `quantity`, COALESCE(`pw`.`cost_price`, 0) AS `cost_price` FROM `" . DB_PREFIX . "warehouse` `w` LEFT JOIN `" . DB_PREFIX . "product_warehouse` `pw` ON (`pw`.`warehouse_id` = `w`.`warehouse_id` AND `pw`.`product_id` = '" . (int)$product_id . "') WHERE `w`.`status` = '1' ORDER BY `w`.`sort_order` ASC, `w`.`name` ASC");

		return $query->rows;
	}

	/**
	 * Set one product's stock/cost for one warehouse (upsert). If this warehouse is the
	 * current selling warehouse, oc_product.quantity is kept in sync automatically.
	 *
	 * @param int   $product_id
	 * @param int   $warehouse_id
	 * @param int   $quantity
	 * @param float $cost_price
	 *
	 * @return void
	 */
	public function setProductWarehouseStock(int $product_id, int $warehouse_id, int $quantity, float $cost_price): void {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "product_warehouse` (`product_id`, `warehouse_id`, `quantity`, `cost_price`, `date_modified`) VALUES ('" . (int)$product_id . "', '" . (int)$warehouse_id . "', '" . (int)$quantity . "', '" . (float)$cost_price . "', NOW()) ON DUPLICATE KEY UPDATE `quantity` = '" . (int)$quantity . "', `cost_price` = '" . (float)$cost_price . "', `date_modified` = NOW()");

		$warehouse_info = $this->getWarehouse($warehouse_id);

		if ($warehouse_info && $warehouse_info['is_selling_warehouse']) {
			$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity` = '" . (int)$quantity . "' WHERE `product_id` = '" . (int)$product_id . "'");
		}
	}

	/**
	 * Re-sync oc_product.quantity for every product from whichever warehouse is
	 * currently the selling warehouse (call this after the selling warehouse changes).
	 *
	 * @return void
	 */
	public function syncSellingWarehouseStock(): void {
		$selling = $this->getSellingWarehouse();

		if (!$selling) {
			return;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "product` `p` LEFT JOIN `" . DB_PREFIX . "product_warehouse` `pw` ON (`pw`.`product_id` = `p`.`product_id` AND `pw`.`warehouse_id` = '" . (int)$selling['warehouse_id'] . "') SET `p`.`quantity` = COALESCE(`pw`.`quantity`, 0)");
	}
}
