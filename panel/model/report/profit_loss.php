<?php
namespace MDcart\Admin\Model\Report;
/**
 * Class ProfitLoss
 *
 * Reads the order_product_cost table (populated by a small hook in
 * catalog/model/checkout/order.php::addHistory(), at the exact moment an
 * order's stock is deducted) and matches each line's cost against its
 * sale price (oc_order_product.total) to produce a day-by-day, and
 * overall, profit/loss breakdown. Access to this whole report is gated
 * by the report/profit_loss permission - see admin/controller/common/
 * column_left.php and the grant-profit-loss-permission.php install script.
 *
 * Can be loaded using $this->load->model('report/profit_loss');
 *
 * @package MDcart\Admin\Model\Report
 */
class ProfitLoss extends \MDcart\System\Engine\Model {
	/**
	 * Whether the underlying order_product_cost table exists yet (it is
	 * created by install-warehouse-schema-v4.php, which may not have been
	 * run yet on an install that only has the earlier warehouse/POS/
	 * purchase-invoice migrations applied).
	 *
	 * @return bool
	 */
	public function isInstalled(): bool {
		$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "order_product_cost'");

		return (bool)$query->num_rows;
	}

	/**
	 * Get Profit Loss (day-by-day breakdown)
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getProfitLoss(array $data = []): array {
		$sql = "SELECT DATE(`opc`.`date_added`) AS `date`, COUNT(DISTINCT `opc`.`order_id`) AS `order_count`, SUM(`op`.`total`) AS `revenue`, SUM(`opc`.`unit_cost` * `opc`.`quantity`) AS `cost` FROM `" . DB_PREFIX . "order_product_cost` `opc` LEFT JOIN `" . DB_PREFIX . "order_product` `op` ON (`opc`.`order_product_id` = `op`.`order_product_id`)";

		$sql .= $this->buildWhere($data);

		$sql .= " GROUP BY DATE(`opc`.`date_added`) ORDER BY DATE(`opc`.`date_added`) DESC";

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

		$rows = [];

		foreach ($query->rows as $row) {
			$revenue = (float)$row['revenue'];
			$cost = (float)$row['cost'];

			$rows[] = [
				'date'        => $row['date'],
				'order_count' => (int)$row['order_count'],
				'revenue'     => $revenue,
				'cost'        => $cost,
				'profit'      => $revenue - $cost,
				'margin'      => $revenue > 0 ? (($revenue - $cost) / $revenue) * 100 : 0
			];
		}

		return $rows;
	}

	/**
	 * Get Total Profit Loss Days (for pagination)
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return int
	 */
	public function getTotalProfitLossDays(array $data = []): int {
		$sql = "SELECT COUNT(DISTINCT DATE(`opc`.`date_added`)) AS `total` FROM `" . DB_PREFIX . "order_product_cost` `opc` LEFT JOIN `" . DB_PREFIX . "order_product` `op` ON (`opc`.`order_product_id` = `op`.`order_product_id`)";

		$sql .= $this->buildWhere($data);

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * Get Profit Loss Summary (overall totals for the current filter)
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<string, mixed>
	 */
	public function getProfitLossSummary(array $data = []): array {
		$sql = "SELECT COUNT(DISTINCT `opc`.`order_id`) AS `order_count`, SUM(`op`.`total`) AS `revenue`, SUM(`opc`.`unit_cost` * `opc`.`quantity`) AS `cost` FROM `" . DB_PREFIX . "order_product_cost` `opc` LEFT JOIN `" . DB_PREFIX . "order_product` `op` ON (`opc`.`order_product_id` = `op`.`order_product_id`)";

		$sql .= $this->buildWhere($data);

		$query = $this->db->query($sql);

		$revenue = $query->num_rows ? (float)$query->row['revenue'] : 0;
		$cost = $query->num_rows ? (float)$query->row['cost'] : 0;

		return [
			'order_count' => $query->num_rows ? (int)$query->row['order_count'] : 0,
			'revenue'     => $revenue,
			'cost'        => $cost,
			'profit'      => $revenue - $cost,
			'margin'      => $revenue > 0 ? (($revenue - $cost) / $revenue) * 100 : 0
		];
	}

	/**
	 * Get Profit Loss By Warehouse (breakdown for the current filter)
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getProfitLossByWarehouse(array $data = []): array {
		$sql = "SELECT `opc`.`warehouse_id`, `w`.`name` AS `warehouse_name`, COUNT(DISTINCT `opc`.`order_id`) AS `order_count`, SUM(`op`.`total`) AS `revenue`, SUM(`opc`.`unit_cost` * `opc`.`quantity`) AS `cost` FROM `" . DB_PREFIX . "order_product_cost` `opc` LEFT JOIN `" . DB_PREFIX . "order_product` `op` ON (`opc`.`order_product_id` = `op`.`order_product_id`) LEFT JOIN `" . DB_PREFIX . "warehouse` `w` ON (`opc`.`warehouse_id` = `w`.`warehouse_id`)";

		$sql .= $this->buildWhere($data);

		$sql .= " GROUP BY `opc`.`warehouse_id` ORDER BY `revenue` DESC";

		$query = $this->db->query($sql);

		$rows = [];

		foreach ($query->rows as $row) {
			$revenue = (float)$row['revenue'];
			$cost = (float)$row['cost'];

			$rows[] = [
				'warehouse_id'   => (int)$row['warehouse_id'],
				'warehouse_name' => $row['warehouse_name'] ?: '',
				'order_count'    => (int)$row['order_count'],
				'revenue'        => $revenue,
				'cost'           => $cost,
				'profit'         => $revenue - $cost,
				'margin'         => $revenue > 0 ? (($revenue - $cost) / $revenue) * 100 : 0
			];
		}

		return $rows;
	}

	/**
	 * @param array<string, mixed> $data
	 *
	 * @return string
	 */
	private function buildWhere(array $data): string {
		$sql = " WHERE 1=1";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(`opc`.`date_added`) >= '" . $this->db->escape((string)$data['filter_date_start']) . "'";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(`opc`.`date_added`) <= '" . $this->db->escape((string)$data['filter_date_end']) . "'";
		}

		if (!empty($data['filter_warehouse_id'])) {
			$sql .= " AND `opc`.`warehouse_id` = '" . (int)$data['filter_warehouse_id'] . "'";
		}

		return $sql;
	}
}
