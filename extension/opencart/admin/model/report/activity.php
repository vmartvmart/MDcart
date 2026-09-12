<?php
namespace MDcart\Admin\Model\Extension\MDcart\Report;
/**
 * Class Activity
 *
 * Can be called from $this->load->model('extension/opencart/report/activity');
 *
 * @package MDcart\Admin\Model\Extension\MDcart\Report
 */
class Activity extends \MDcart\System\Engine\Model {
	/**
	 * Get Activities
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @example
	 *
	 * $results = $this->model_extension_opencart_report_activity->getActivities();
	 */
	public function getActivities(): array {
		$query = $this->db->query("SELECT `key`, `data`, `date_added` FROM `" . DB_PREFIX . "customer_activity` ORDER BY `customer_activity_id` DESC LIMIT 0,5");

		return $query->rows;
	}
}
