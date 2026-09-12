<?php
namespace MDcart\Admin\Model\Extension\MDcart\Dashboard;
/**
 * Class Map
 *
 * Can be called from $this->load->model('extension/opencart/dashboard/map');
 *
 * @package MDcart\Admin\Model\Extension\MDcart\Dashboard
 */
class Map extends \MDcart\System\Engine\Model {
	/**
	 * Get Total Orders By Country
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @example
	 *
	 * $order_total = $this->model_extension_opencart_dashboard_map->getTotalOrdersByCountry();
	 */
	public function getTotalOrdersByCountry(): array {
		$implode = [];

		if (is_array($this->config->get('config_complete_status'))) {
			foreach ($this->config->get('config_complete_status') as $order_status_id) {
				$implode[] = "'" . (int)$order_status_id . "'";
			}
		}

		if ($implode) {
			$query = $this->db->query("SELECT COUNT(*) AS `total`, SUM(`o`.`total`) AS `amount`, `c`.`iso_code_2` FROM `" . DB_PREFIX . "order` `o` LEFT JOIN `" . DB_PREFIX . "country` `c` ON (`o`.`payment_country_id` = `c`.`country_id`) WHERE `o`.`order_status_id` IN(" . implode(',', $implode) . ") AND `o`.`payment_country_id` != '0' GROUP BY `o`.`payment_country_id`");

			return $query->rows;
		} else {
			return [];
		}
	}

	/**
	 * Get Total Orders By Zone
	 *
	 * @param int $country_id
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @example
	 *
	 * $order_total = $this->model_extension_opencart_dashboard_map->getTotalOrdersByZone($country_id);
	 */
	public function getTotalOrdersByZone(int $country_id): array {
		$implode = [];

		if (is_array($this->config->get('config_complete_status'))) {
			foreach ($this->config->get('config_complete_status') as $order_status_id) {
				$implode[] = "'" . (int)$order_status_id . "'";
			}
		}

		if ($implode) {
			$query = $this->db->query("SELECT COUNT(*) AS `total`, SUM(`o`.`total`) AS `amount`, `o`.`payment_zone_id` FROM `" . DB_PREFIX . "order` `o` WHERE `o`.`order_status_id` IN(" . implode(',', $implode) . ") AND `o`.`payment_country_id` = '" . (int)$country_id . "' AND `o`.`payment_zone_id` != '0' GROUP BY `o`.`payment_zone_id`");

			return $query->rows;
		} else {
			return [];
		}
	}

	/**
	 * Get Total Orders By City
	 *
	 * @param int $zone_id
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @example
	 *
	 * $order_total = $this->model_extension_opencart_dashboard_map->getTotalOrdersByCity($zone_id);
	 */
	public function getTotalOrdersByCity(int $zone_id): array {
		$implode = [];

		if (is_array($this->config->get('config_complete_status'))) {
			foreach ($this->config->get('config_complete_status') as $order_status_id) {
				$implode[] = "'" . (int)$order_status_id . "'";
			}
		}

		if ($implode) {
			$query = $this->db->query("SELECT COUNT(*) AS `total`, SUM(`o`.`total`) AS `amount`, `o`.`payment_city` FROM `" . DB_PREFIX . "order` `o` WHERE `o`.`order_status_id` IN(" . implode(',', $implode) . ") AND `o`.`payment_zone_id` = '" . (int)$zone_id . "' AND `o`.`payment_city` != '' GROUP BY `o`.`payment_city` ORDER BY `total` DESC");

			return $query->rows;
		} else {
			return [];
		}
	}
}
