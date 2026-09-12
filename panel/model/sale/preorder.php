<?php
namespace MDcart\Admin\Model\Sale;
/**
 * Class Preorder
 *
 * Every order line ever sold as a pre-order / backorder (POS: cashier marked
 * it manually, any time, or after a stock warning; storefront: only on
 * products the admin turned "Allow Pre-Order When Out Of Stock" on) shows up
 * here so an admin can review it, adjust its delivery date, or mark it
 * fulfilled once the stock actually arrives and the customer has been given
 * their item.
 *
 * Can be loaded using $this->load->model('sale/preorder');
 *
 * @package MDcart\Admin\Model\Sale
 */
class Preorder extends \MDcart\System\Engine\Model {
	/**
	 * Get Preorders
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getPreorders(array $data = []): array {
		$sql = "SELECT `op`.`order_product_id`, `op`.`order_id`, `op`.`product_id`, `op`.`name`, `op`.`quantity`, `op`.`preorder_delivery_date`, `o`.`firstname`, `o`.`lastname`, `o`.`email`, `o`.`date_added`, `o`.`order_status_id`, (SELECT `os`.`name` FROM `" . DB_PREFIX . "order_status` `os` WHERE `os`.`order_status_id` = `o`.`order_status_id` AND `os`.`language_id` = `o`.`language_id`) AS `order_status` FROM `" . DB_PREFIX . "order_product` `op` LEFT JOIN `" . DB_PREFIX . "order` `o` ON (`op`.`order_id` = `o`.`order_id`) WHERE `op`.`is_preorder` = '1'";

		$sql .= " ORDER BY `op`.`preorder_delivery_date` IS NULL, `op`.`preorder_delivery_date` ASC, `op`.`order_product_id` DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			$start = isset($data['start']) ? (int)$data['start'] : 0;
			$limit = isset($data['limit']) ? (int)$data['limit'] : 20;

			if ($start < 0) {
				$start = 0;
			}

			if ($limit < 1) {
				$limit = 20;
			}

			$sql .= " LIMIT " . $start . "," . $limit;
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Get Total Preorders
	 *
	 * @return int
	 */
	public function getTotalPreorders(): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "order_product` WHERE `is_preorder` = '1'");

		return (int)$query->row['total'];
	}

	/**
	 * Edit Delivery Date
	 *
	 * @param int    $order_product_id
	 * @param string $delivery_date    'YYYY-MM-DD', or '' to clear it
	 *
	 * @return void
	 */
	public function editDeliveryDate(int $order_product_id, string $delivery_date): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "order_product` SET `preorder_delivery_date` = " . ($delivery_date ? "'" . $this->db->escape($delivery_date) . "'" : "NULL") . " WHERE `order_product_id` = '" . $order_product_id . "' AND `is_preorder` = '1'");
	}

	/**
	 * Mark Fulfilled
	 *
	 * Clears the pre-order flag - the stock arrived and/or the customer
	 * already has their item, so this line drops off the pending list. The
	 * order itself, and its line, are of course untouched otherwise.
	 *
	 * @param int $order_product_id
	 *
	 * @return void
	 */
	public function markFulfilled(int $order_product_id): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "order_product` SET `is_preorder` = '0' WHERE `order_product_id` = '" . $order_product_id . "'");
	}
}
