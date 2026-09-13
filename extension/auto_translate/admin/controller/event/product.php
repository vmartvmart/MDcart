<?php
namespace MDcart\Admin\Controller\Extension\AutoTranslate\Event;
/**
 * Class Product
 *
 * model/catalog/product.addProduct/after
 * model/catalog/product.editProduct/after
 *
 * Fires once, right after a product is saved in the admin. Reads the
 * product's own fa/en oc_product_description rows straight back out of the
 * database - by the time this fires, addProduct()/editProduct() have
 * already written them - and, independently for each translatable field,
 * fills in whichever language is empty from whichever language is
 * populated. A field that already has content in both languages, or is
 * empty in both, is left untouched. Both directions (fa->en and en->fa)
 * are checked in the same pass, so this handles new products (one language
 * filled in) and edits (either language edited) the same way.
 *
 * SECURITY: this only ever runs from an admin-side model event on an
 * already-authenticated, already-permission-checked save action - there is
 * no storefront/customer-facing route into this code, and the Anthropic API
 * key (read via $this->config, never logged) never leaves the server.
 *
 * @package MDcart\Admin\Controller\Extension\AutoTranslate\Event
 */
class Product extends \MDcart\System\Engine\Controller {
	private const LANGUAGE_ID_EN = 1;
	private const LANGUAGE_ID_FA = 2;

	/** @var array<int, string> */
	private const FIELDS = ['name', 'description', 'tag', 'meta_title', 'meta_description', 'meta_keyword'];

	/**
	 * model/catalog/product.addProduct/after
	 *
	 * @param string            $route
	 * @param array<int, mixed> $args
	 * @param mixed             $output the new product_id, as returned by addProduct()
	 *
	 * @return void
	 */
	public function add(string &$route, array &$args, &$output = null): void {
		$this->process((int)($output ?? 0));
	}

	/**
	 * model/catalog/product.editProduct/after
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
	 * @param int $product_id
	 *
	 * @return void
	 */
	private function process(int $product_id): void {
		if (!$product_id || !$this->config->get('other_auto_translate_status') || !$this->config->get('other_auto_translate_products_status') || !$this->config->get('other_auto_translate_api_key')) {
			return;
		}

		if (!class_exists('\MDcart\System\Library\Extension\AutoTranslate\AutoTranslate')) {
			return;
		}

		try {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_description` WHERE `product_id` = '" . $product_id . "'");

			$rows = [];

			foreach ($query->rows as $row) {
				$rows[(int)$row['language_id']] = $row;
			}

			$en = $rows[self::LANGUAGE_ID_EN] ?? null;
			$fa = $rows[self::LANGUAGE_ID_FA] ?? null;

			// Both language rows must exist (addDescription() is called once per store
			// language on every save) - if either is missing there is nothing safe to do.
			if (!$en || !$fa) {
				return;
			}

			$client = new \MDcart\System\Library\Extension\AutoTranslate\AutoTranslate(
				(string)$this->config->get('other_auto_translate_api_key'),
				(string)$this->config->get('other_auto_translate_model')
			);

			$this->fillMissing($client, 'product_id', $product_id, $fa, self::LANGUAGE_ID_FA, 'Persian (Farsi)', $en, self::LANGUAGE_ID_EN, 'English');
			$this->fillMissing($client, 'product_id', $product_id, $en, self::LANGUAGE_ID_EN, 'English', $fa, self::LANGUAGE_ID_FA, 'Persian (Farsi)');
		} catch (\Throwable $e) {
			// Never let a translation failure break a product save.
			$this->log->write('Auto Translate (product #' . $product_id . '): ' . $e->getMessage());
		}
	}

	/**
	 * Fills any of $target_row's FIELDS that are empty, by translating the corresponding,
	 * non-empty fields of $source_row. Only ever UPDATEs the specific columns that were
	 * actually empty - already-populated target fields are never touched.
	 *
	 * @param \MDcart\System\Library\Extension\AutoTranslate\AutoTranslate $client
	 * @param string                                                       $id_column
	 * @param int                                                          $id
	 * @param array<string, mixed>                                         $source_row
	 * @param int                                                          $source_language_id
	 * @param string                                                       $source_name
	 * @param array<string, mixed>                                         $target_row
	 * @param int                                                          $target_language_id
	 * @param string                                                       $target_name
	 *
	 * @return void
	 */
	private function fillMissing(\MDcart\System\Library\Extension\AutoTranslate\AutoTranslate $client, string $id_column, int $id, array $source_row, int $source_language_id, string $source_name, array $target_row, int $target_language_id, string $target_name): void {
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
			$this->log->write('Auto Translate (product #' . $id . ', ' . $source_name . ' -> ' . $target_name . '): ' . $client->error);

			return;
		}

		$set = [];

		foreach ($translated as $field => $value) {
			if (trim($value) !== '') {
				$set[] = "`" . $field . "` = '" . $this->db->escape($value) . "'";
			}
		}

		if ($set) {
			$this->db->query("UPDATE `" . DB_PREFIX . "product_description` SET " . implode(', ', $set) . " WHERE `" . $id_column . "` = '" . $id . "' AND `language_id` = '" . $target_language_id . "'");
		}
	}
}
