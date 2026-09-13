<?php
namespace MDcart\Admin\Controller\Extension\AutoTranslate\Event;
/**
 * Class Category
 *
 * model/catalog/category.addCategory/after
 * model/catalog/category.editCategory/after
 *
 * Same fa<->en auto-fill behaviour as Product (see that class's docblock),
 * applied to oc_category_description instead (which has no "tag" field).
 *
 * @package MDcart\Admin\Controller\Extension\AutoTranslate\Event
 */
class Category extends \MDcart\System\Engine\Controller {
	private const LANGUAGE_ID_EN = 1;
	private const LANGUAGE_ID_FA = 2;

	/** @var array<int, string> */
	private const FIELDS = ['name', 'description', 'meta_title', 'meta_description', 'meta_keyword'];

	/**
	 * model/catalog/category.addCategory/after
	 *
	 * @param string            $route
	 * @param array<int, mixed> $args
	 * @param mixed             $output the new category_id, as returned by addCategory()
	 *
	 * @return void
	 */
	public function add(string &$route, array &$args, &$output = null): void {
		$this->process((int)($output ?? 0));
	}

	/**
	 * model/catalog/category.editCategory/after
	 *
	 * @param string            $route
	 * @param array<int, mixed> $args
	 * @param mixed             $output
	 *
	 * @return void
	 */
	public function edit(string &$route, array &$args, &$output = null): void {
		$this->process((int)($args[0] ?? 0));
	}

	/**
	 * @param int $category_id
	 *
	 * @return void
	 */
	private function process(int $category_id): void {
		if (!$category_id || !$this->config->get('other_auto_translate_status') || !$this->config->get('other_auto_translate_categories_status') || !$this->config->get('other_auto_translate_api_key')) {
			return;
		}

		if (!class_exists('\MDcart\System\Library\Extension\AutoTranslate\AutoTranslate')) {
			return;
		}

		try {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_description` WHERE `category_id` = '" . $category_id . "'");

			$rows = [];

			foreach ($query->rows as $row) {
				$rows[(int)$row['language_id']] = $row;
			}

			$en = $rows[self::LANGUAGE_ID_EN] ?? null;
			$fa = $rows[self::LANGUAGE_ID_FA] ?? null;

			if (!$en || !$fa) {
				return;
			}

			$client = new \MDcart\System\Library\Extension\AutoTranslate\AutoTranslate(
				(string)$this->config->get('other_auto_translate_api_key'),
				(string)$this->config->get('other_auto_translate_model')
			);

			$this->fillMissing($client, 'category_id', $category_id, $fa, 'Persian (Farsi)', $en, self::LANGUAGE_ID_EN, 'English');
			$this->fillMissing($client, 'category_id', $category_id, $en, 'English', $fa, self::LANGUAGE_ID_FA, 'Persian (Farsi)');
		} catch (\Throwable $e) {
			$this->log->write('Auto Translate (category #' . $category_id . '): ' . $e->getMessage());
		}
	}

	/**
	 * @param \MDcart\System\Library\Extension\AutoTranslate\AutoTranslate $client
	 * @param string                                                       $id_column
	 * @param int                                                          $id
	 * @param array<string, mixed>                                         $source_row
	 * @param string                                                       $source_name
	 * @param array<string, mixed>                                         $target_row
	 * @param int                                                          $target_language_id
	 * @param string                                                       $target_name
	 *
	 * @return void
	 */
	private function fillMissing(\MDcart\System\Library\Extension\AutoTranslate\AutoTranslate $client, string $id_column, int $id, array $source_row, string $source_name, array $target_row, int $target_language_id, string $target_name): void {
		$to_translate = [];

		foreach (self::FIELDS as $field) {
			if (trim((string)($target_row[$field] ?? '')) === '' && trim((string)($source_row[$field] ?? '')) !== '') {
				$to_translate[$field] = (string)$source_row[$field];
			}
		}

		if (!$to_translate) {
			return;
		}

		$translated = $client->translateFields($to_translate, $source_name, $target_name);

		if ($translated === null) {
			$this->log->write('Auto Translate (category #' . $id . ', ' . $source_name . ' -> ' . $target_name . '): ' . $client->error);

			return;
		}

		$set = [];

		foreach ($translated as $field => $value) {
			if (trim($value) !== '') {
				$set[] = "`" . $field . "` = '" . $this->db->escape($value) . "'";
			}
		}

		if ($set) {
			$this->db->query("UPDATE `" . DB_PREFIX . "category_description` SET " . implode(', ', $set) . " WHERE `" . $id_column . "` = '" . $id . "' AND `language_id` = '" . $target_language_id . "'");
		}
	}
}
