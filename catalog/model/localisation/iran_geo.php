<?php
namespace Opencart\Catalog\Model\Localisation;
/**
 * Class Iran Geo
 *
 * Looks up Iran's official county / city / urban-district / village hierarchy
 * (imported from Iran Statistics Center administrative division data) so address
 * forms can offer a real, structured picker instead of free-text city entry.
 *
 * @package Opencart\Catalog\Model\Localisation
 */
class IranGeo extends \Opencart\System\Engine\Model {
	/**
	 * Get Counties By Zone Id
	 *
	 * @param int $zone_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getCountiesByZoneId(int $zone_id): array {
		$query = $this->db->query("SELECT `shahrestan_id`, `name` FROM `" . DB_PREFIX . "iran_shahrestan` WHERE `zone_id` = '" . (int)$zone_id . "' ORDER BY `name` ASC");

		return $query->rows;
	}

	/**
	 * Get Cities By Shahrestan Id
	 *
	 * Returns official cities/urban-districts and villages that belong to the given county.
	 *
	 * @param int $shahrestan_id
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function getCitiesByShahrestanId(int $shahrestan_id): array {
		$city_query = $this->db->query("SELECT `shahr_id`, `name`, `shahr_type` FROM `" . DB_PREFIX . "iran_shahr` WHERE `shahrestan_id` = '" . (int)$shahrestan_id . "' ORDER BY `shahr_type` ASC, `name` ASC");

		$village_query = $this->db->query("SELECT `abadi_id`, `name` FROM `" . DB_PREFIX . "iran_abadi` WHERE `shahrestan_id` = '" . (int)$shahrestan_id . "' ORDER BY `name` ASC");

		return [
			'cities'   => $city_query->rows,
			'villages' => $village_query->rows
		];
	}
}
