<?php
namespace MDcart\Catalog\Model\Setting;
/**
 * Class Startup
 *
 * Can be called using $this->load->model('setting/startup');
 *
 * @package MDcart\Catalog\Model\Setting
 */
class Startup extends \MDcart\System\Engine\Model {
	/**
	 * Get Startups
	 *
	 * Get the record of the startup records in the database.
	 *
	 * @return array<int, array<string, mixed>> startup records
	 *
	 * @example
	 *
	 * $this->load->model('setting/startup');
	 *
	 * $startups = $this->model_setting_startup->getStartups();
	 */
	public function getStartups(): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "startup` WHERE `status` = '1' ORDER BY `sort_order` ASC");

		return $query->rows;
	}
}
