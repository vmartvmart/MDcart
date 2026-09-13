<?php
namespace MDcart\Admin\Model\Accounting;
/**
 * Class Supplier
 *
 * Supplier ("طرف حساب") master data for Purchase Invoices. Each supplier is
 * NOT given its own row in the chart of accounts - every supplier's unpaid
 * balance is tracked as a sub-ledger of the single "Accounts Payable"
 * control account (getSupplierBalance() sums it from unpaid purchase
 * invoices), exactly like a normal accounting system's AP sub-ledger. This
 * keeps the chart of accounts clean regardless of how many suppliers exist.
 *
 * Can be loaded using $this->load->model('accounting/supplier');
 *
 * @package MDcart\Admin\Model\Accounting
 */
class Supplier extends \MDcart\System\Engine\Model {
	/**
	 * Add Supplier
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return int
	 */
	public function addSupplier(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "supplier` SET `name` = '" . $this->db->escape((string)$data['name']) . "', `telephone` = '" . $this->db->escape((string)($data['telephone'] ?? '')) . "', `email` = '" . $this->db->escape((string)($data['email'] ?? '')) . "', `address` = '" . $this->db->escape((string)($data['address'] ?? '')) . "', `tax_id` = '" . $this->db->escape((string)($data['tax_id'] ?? '')) . "', `currency_code` = '" . $this->db->escape((string)$data['currency_code']) . "', `status` = '" . (int)!empty($data['status']) . "', `sort_order` = '" . (int)($data['sort_order'] ?? 0) . "', `date_added` = NOW(), `date_modified` = NOW()");

		return (int)$this->db->getLastId();
	}

	/**
	 * Edit Supplier
	 *
	 * @param int                  $supplier_id
	 * @param array<string, mixed> $data
	 *
	 * @return void
	 */
	public function editSupplier(int $supplier_id, array $data): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "supplier` SET `name` = '" . $this->db->escape((string)$data['name']) . "', `telephone` = '" . $this->db->escape((string)($data['telephone'] ?? '')) . "', `email` = '" . $this->db->escape((string)($data['email'] ?? '')) . "', `address` = '" . $this->db->escape((string)($data['address'] ?? '')) . "', `tax_id` = '" . $this->db->escape((string)($data['tax_id'] ?? '')) . "', `currency_code` = '" . $this->db->escape((string)$data['currency_code']) . "', `status` = '" . (int)!empty($data['status']) . "', `sort_order` = '" . (int)($data['sort_order'] ?? 0) . "', `date_modified` = NOW() WHERE `supplier_id` = '" . (int)$supplier_id . "'");
	}

	/**
	 * Delete Supplier
	 *
	 * @param int $supplier_id
	 *
	 * @return void
	 */
	public function deleteSupplier(int $supplier_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "supplier` WHERE `supplier_id` = '" . (int)$supplier_id . "'");
	}

	/**
	 * @param int $supplier_id
	 *
	 * @return array<string, mixed>
	 */
	public function getSupplier(int $supplier_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "supplier` WHERE `supplier_id` = '" . (int)$supplier_id . "'");

		return $query->row;
	}

	/**
	 * @return bool whether this supplier has any purchase invoices posted against it (blocks delete)
	 */
	public function hasPurchaseInvoices(int $supplier_id): bool {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "purchase_invoice` WHERE `supplier_id` = '" . (int)$supplier_id . "'");

		return (bool)(int)$query->row['total'];
	}

	/**
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getSuppliers(array $data = []): array {
		$sql = "SELECT * FROM `" . DB_PREFIX . "supplier`";

		if (!empty($data['filter_name'])) {
			$sql .= " WHERE `name` LIKE '" . $this->db->escape((string)$data['filter_name']) . "%'";
		}

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
	 * @param array<string, mixed> $data
	 *
	 * @return int
	 */
	public function getTotalSuppliers(array $data = []): int {
		$sql = "SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "supplier`";

		if (!empty($data['filter_name'])) {
			$sql .= " WHERE `name` LIKE '" . $this->db->escape((string)$data['filter_name']) . "%'";
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * How much this store currently owes the supplier, in the SUPPLIER'S OWN currency
	 * (not converted to Rial - see the Purchase Invoice docblock for why a foreign-currency
	 * payable is kept at its original currency amount rather than being re-valued here).
	 * Sum of (total_amount - paid_amount) over every not-fully-paid invoice for this supplier.
	 *
	 * @param int $supplier_id
	 *
	 * @return float
	 */
	public function getSupplierBalance(int $supplier_id): float {
		$query = $this->db->query("SELECT SUM(`total_amount` - `paid_amount`) AS `balance` FROM `" . DB_PREFIX . "purchase_invoice` WHERE `supplier_id` = '" . (int)$supplier_id . "' AND `total_amount` > `paid_amount`");

		return (float)($query->row['balance'] ?? 0);
	}
}
