<?php
namespace MDcart\Catalog\Controller\Extension\Torob\Feed;
/**
 * Class Torob
 *
 * Product extraction endpoint for Torob's crawler (torob.com), mirroring the
 * JSON contract used by Torob's official "Products Extractor" plugin
 * (https://wordpress.org/plugins/products-extractor-for-woocommerce/) so the
 * same crawler/API integration that platform offers for WooCommerce can be
 * pointed at this MDcart store instead.
 *
 * URL to give Torob: index.php?route=extension/torob/feed/torob
 *
 * @package MDcart\Catalog\Controller\Extension\Torob\Feed
 */
class Torob extends \MDcart\System\Engine\Controller {
	/**
	 * Torob's Ed25519 public key (base64-encoded, 32 bytes) used to verify the
	 * JWT sent by Torob's crawler in the X-Torob-Token header.
	 */
	private const TOROB_PUBLIC_KEY = 't6Mu4T0pBORY11W+QeM35UsmLO3vsf+6yKpFDEImFk0=';

	private const API_VERSION = 'opencart_torob_products_v1';

	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		if (!$this->config->get('feed_torob_status')) {
			$this->response->addHeader('HTTP/1.1 404 Not Found');

			return;
		}

		$auth_error = $this->authenticate();

		if ($auth_error) {
			$this->response->addHeader('HTTP/1.1 401 Unauthorized');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(['error' => $auth_error]));

			return;
		}

		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('tool/image');

		$product_ids = $this->getParam('products');
		$limit = (int)$this->getParam('limit');
		$page = (int)$this->getParam('page');

		if ($limit < 1) {
			$limit = 100;
		}

		if ($page < 1) {
			$page = 1;
		}

		$data = [];

		if ($product_ids !== null && $product_ids !== '') {
			$data['products'] = [];

			foreach (explode(',', $product_ids) as $product_id) {
				$product_id = (int)trim($product_id);

				if (!$product_id) {
					continue;
				}

				$product_info = $this->model_catalog_product->getProduct($product_id);

				if ($product_info) {
					$data['products'][] = $this->getProductValues($product_info);
				}
			}
		} else {
			$total = $this->model_catalog_product->getTotalProducts();

			$results = $this->model_catalog_product->getProducts([
				'start' => ($page - 1) * $limit,
				'limit' => $limit
			]);

			$data['count'] = $total;
			$data['current_page'] = $page;
			$data['max_pages'] = $limit > 0 ? (int)ceil($total / $limit) : 1;
			$data['products'] = [];

			foreach ($results as $product_info) {
				$data['products'][] = $this->getProductValues($product_info);
			}
		}

		$data['api_version'] = self::API_VERSION;

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($data));
	}

	/**
	 * Read a parameter from POST first (Torob's crawler uses POST), falling
	 * back to GET so the endpoint is also easy to test from a browser.
	 *
	 * @param string $key
	 *
	 * @return string|null
	 */
	private function getParam(string $key): ?string {
		if (isset($this->request->post[$key]) && $this->request->post[$key] !== '') {
			return (string)$this->request->post[$key];
		}

		if (isset($this->request->get[$key]) && $this->request->get[$key] !== '') {
			return (string)$this->request->get[$key];
		}

		return null;
	}

	/**
	 * Build the Torob product payload for a single MDcart product row.
	 *
	 * Field names/shape match Torob's product-extraction contract:
	 * title, subtitle, parent_id, page_unique, availability, current_price,
	 * old_price, category_name, image_link, image_links, page_url,
	 * short_desc, spec, date_added, date_updated, product_type, guarantee.
	 *
	 * @param array<string, mixed> $product_info
	 *
	 * @return array<string, mixed>
	 */
	private function getProductValues(array $product_info): array {
		$product_id = (int)$product_info['product_id'];

		$price = $this->tax->calculate((float)$product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax'));

		if (!empty($product_info['special']) || $product_info['special'] === '0') {
			$special = $product_info['special'] !== '' ? (float)$product_info['special'] : null;
		} else {
			$special = null;
		}

		if ($special !== null) {
			$current_price = $this->tax->calculate($special, $product_info['tax_class_id'], $this->config->get('config_tax'));
		} else {
			$current_price = $price;
		}

		$categories = $this->model_catalog_product->getCategories($product_id);
		$category_name = '';

		if ($categories) {
			$last_category = end($categories);
			$category_info = $this->model_catalog_category->getCategory((int)$last_category['category_id']);

			if ($category_info) {
				$category_name = $this->decode($category_info['name']);
			}
		}

		$image_links = [];

		if (!empty($product_info['image'])) {
			$image_links[] = $this->model_tool_image->resize($product_info['image'], (int)$this->config->get('config_image_popup_width'), (int)$this->config->get('config_image_popup_height'));
		}

		foreach ($this->model_catalog_product->getImages($product_id) as $additional_image) {
			$link = $this->model_tool_image->resize($additional_image['image'], (int)$this->config->get('config_image_popup_width'), (int)$this->config->get('config_image_popup_height'));

			if (!in_array($link, $image_links, true)) {
				$image_links[] = $link;
			}
		}

		$spec = [];

		foreach ($this->model_catalog_product->getAttributes($product_id) as $attribute_group) {
			foreach ($attribute_group['attribute'] as $attribute) {
				if ($attribute['text'] !== '') {
					$spec[$this->decode($attribute['name'])] = $this->decode(strip_tags((string)$attribute['text']));
				}
			}
		}

		if (!empty($product_info['model']) && !isset($spec['شناسه کالا'])) {
			$spec['شناسه کالا'] = $this->decode($product_info['model']);
		}

		$guarantee = '';

		foreach (['گارانتی', 'گارانتی محصول', 'ضمانت', 'guarantee', 'warranty'] as $guarantee_key) {
			if (!empty($spec[$guarantee_key])) {
				$guarantee = $spec[$guarantee_key];

				break;
			}
		}

		return [
			'title'         => $this->decode($product_info['name']),
			'subtitle'      => '',
			'parent_id'     => 0,
			'page_unique'   => (string)$product_id,
			'availability'  => ((int)$product_info['quantity'] > 0) ? 'instock' : 'outofstock',
			'current_price' => $this->normalizePrice($current_price),
			'old_price'     => $this->normalizePrice($price),
			'category_name' => $category_name,
			'image_link'    => $image_links[0] ?? null,
			'image_links'   => $image_links,
			'page_url'      => str_replace('&amp;', '&', $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_id)),
			'short_desc'    => oc_substr(trim(strip_tags(html_entity_decode((string)$product_info['description'], ENT_QUOTES, 'UTF-8'))), 0, 300),
			'spec'          => $spec ? [$spec] : [],
			'date_added'    => $product_info['date_added'] ? date(DATE_ATOM, strtotime($product_info['date_added'])) : null,
			'date_updated'  => $product_info['date_modified'] ? date(DATE_ATOM, strtotime($product_info['date_modified'])) : null,
			'product_type'  => 'simple',
			'guarantee'     => $guarantee
		];
	}

	/**
	 * MDcart stores product name/model/attribute/category text HTML-encoded
	 * (ready to drop straight into a page). Decode it back to plain text for
	 * a JSON API consumer.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private function decode(string $text): string {
		return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Normalize a price to an integer string when it has no fractional part,
	 * otherwise a fixed 2 decimal string.
	 *
	 * @param float $price
	 *
	 * @return string
	 */
	private function normalizePrice(float $price): string {
		if (abs($price - round($price)) < 0.000001) {
			return (string)(int)round($price);
		}

		return number_format($price, 2, '.', '');
	}

	/**
	 * Validate the X-Torob-Token / X-Torob-Token-Version headers sent by
	 * Torob's crawler.
	 *
	 * @return array<string, string>|null Error details, or null if valid.
	 */
	private function authenticate(): ?array {
		$token = $this->request->server['HTTP_X_TOROB_TOKEN'] ?? '';
		$token_version = $this->request->server['HTTP_X_TOROB_TOKEN_VERSION'] ?? '';

		if ($token === '') {
			return ['code' => 'missing_token', 'message' => 'X-Torob-Token header is required'];
		}

		if ($token_version === '') {
			return ['code' => 'missing_token', 'message' => 'X-Torob-Token-Version header is required'];
		}

		if ($token_version !== '1') {
			return ['code' => 'unsupported_token_version', 'message' => 'Only token version 1 (JWT) is supported'];
		}

		return $this->validateJwt($token);
	}

	/**
	 * Verify an EdDSA (Ed25519) JWT locally using Torob's public key.
	 *
	 * @param string $token
	 *
	 * @return array<string, string>|null Error details, or null if valid.
	 */
	private function validateJwt(string $token): ?array {
		$parts = explode('.', $token);

		if (count($parts) !== 3) {
			return ['code' => 'invalid_token', 'message' => 'Malformed token'];
		}

		[$header_b64, $payload_b64, $signature_b64] = $parts;

		$header = json_decode((string)$this->base64UrlDecode($header_b64), true);
		$payload = json_decode((string)$this->base64UrlDecode($payload_b64), true);
		$signature = $this->base64UrlDecode($signature_b64);

		if (!is_array($header) || !is_array($payload) || $signature === false) {
			return ['code' => 'invalid_token', 'message' => 'Malformed token'];
		}

		if (($header['alg'] ?? '') !== 'EdDSA') {
			return ['code' => 'invalid_token', 'message' => 'Unsupported token algorithm'];
		}

		$public_key = base64_decode(self::TOROB_PUBLIC_KEY);

		if (!sodium_crypto_sign_verify_detached($signature, $header_b64 . '.' . $payload_b64, $public_key)) {
			return ['code' => 'invalid_token', 'message' => 'Signature verification failed'];
		}

		$now = time();

		if (isset($payload['exp']) && $now >= (int)$payload['exp']) {
			return ['code' => 'token_expired', 'message' => 'Token has expired'];
		}

		if (isset($payload['nbf']) && $now < (int)$payload['nbf']) {
			return ['code' => 'token_nbf', 'message' => 'Token is not yet valid'];
		}

		$expected_audience = $this->getSiteDomain();
		$actual_audience = $payload['aud'] ?? null;

		if (!is_string($actual_audience) || $actual_audience !== $expected_audience) {
			return ['code' => 'token_invalid_aud', 'message' => 'Invalid audience'];
		}

		return null;
	}

	/**
	 * Get the hostname (with port if non-standard) this store is expected to
	 * be reached at, used to validate the JWT "aud" claim.
	 *
	 * @return string
	 */
	private function getSiteDomain(): string {
		$url = (string)$this->config->get('config_url');

		if ($url === '') {
			$url = HTTP_SERVER;
		}

		$host = (string)parse_url($url, PHP_URL_HOST);
		$port = parse_url($url, PHP_URL_PORT);

		if ($port && !in_array($port, [80, 443], true)) {
			$host .= ':' . $port;
		}

		return $host;
	}

	/**
	 * Base64url decode.
	 *
	 * @param string $data
	 *
	 * @return string|false
	 */
	private function base64UrlDecode(string $data) {
		$remainder = strlen($data) % 4;

		if ($remainder) {
			$data .= str_repeat('=', 4 - $remainder);
		}

		return base64_decode(strtr($data, '-_', '+/'));
	}
}
