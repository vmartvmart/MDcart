<?php
namespace MDcart\Admin\Model\Catalog;

/**
 * Class ProductImportUrl
 *
 * Imports a product draft from an arbitrary external product page URL
 * (Amazon, Virgin Megastore, Digikala, or any other storefront) by reading
 * the page's Open Graph / Schema.org (JSON-LD) metadata - the same metadata
 * most e-commerce sites already publish for link previews / SEO, so this
 * works across many stores without a site-specific scraper. Storefronts
 * that render everything client-side with JavaScript (no usable metadata in
 * the plain server-fetched HTML) are additionally retried through a real,
 * invisible local Chrome instance (see fetchHtmlViaHeadlessChrome()) before
 * giving up.
 *
 * This is a port of the equivalent Bagisto feature (Webkul\Admin\Services\
 * ProductUrlImportService) onto OpenCart 4.1's model/data layer. The HTML
 * extraction heuristics (container-scoping, srcset parsing, generic spec
 * extraction, price parsing) are framework-agnostic and were ported
 * near-verbatim; everything that touches how a product/attribute/image is
 * actually persisted was rewritten against OpenCart's schema (oc_product,
 * oc_attribute[_group], oc_product_image) since it has no EAV system like
 * Bagisto's.
 *
 * Besides title/description/images/price, this also makes a best-effort
 * attempt at technical specifications (turned into real OpenCart catalog
 * attributes, reused across imports). Product *videos* are downloaded when
 * found but - unlike Bagisto - stock OpenCart 4.1 has no product-video
 * gallery field at all, so they're saved to disk and their paths are
 * returned to the admin (see 'imported_video_paths') rather than silently
 * dropped or attached anywhere.
 *
 * The created product is always a DRAFT (status disabled) so an admin must
 * review, correct, and enable it before it appears on the storefront -
 * automatic extraction from arbitrary third-party pages is best-effort and
 * can be wrong (price/currency, wrong image picked, missing spec, etc.).
 *
 * Can be loaded using $this->load->model('catalog/product_import_url');
 *
 * @package MDcart\Admin\Model\Catalog
 */
class ProductImportUrl extends \MDcart\System\Engine\Model {

	/**
	 * Import a product from an external URL. Returns
	 * ['product_id' => int, 'warnings' => string[], 'imported_video_paths' => string[]].
	 *
	 * @throws \Exception
	 */
	public function importFromUrl(string $url): array {
		// Fetching the page + downloading several images/videos sequentially
		// (and, on JS-rendered sites, rendering the page in a real Chrome
		// instance) can easily take longer than PHP's default
		// max_execution_time, which aborts the request mid-way. Extend it
		// for the duration of this import only (falls back silently if the
		// function is disabled).
		@set_time_limit(150);
		@ignore_user_abort(true);

		$this->guardAgainstUnsafeUrl($url);

		$html = $this->fetchHtml($url);

		$data = $this->extract($html, $url);

		if (empty($data['title']) || $this->looksLikeJsRenderedShell($data, $html)) {
			// Some storefronts (Digikala among them) render literally
			// everything client-side with JavaScript - the HTML a plain
			// server-side fetch gets back is a near-empty app shell with
			// nothing usable in it. Before giving up, try again by
			// actually rendering the page in a real, invisible local
			// Chrome instance so those sites work too.
			$renderedHtml = $this->fetchHtmlViaHeadlessChrome($url);

			if ($renderedHtml) {
				$renderedData = $this->extract($renderedHtml, $url);

				if (!empty($renderedData['title']) && !$this->looksLikeJsRenderedShell($renderedData, $renderedHtml)) {
					$html = $renderedHtml;
					$data = $renderedData;
				}
			}
		}

		if (empty($data['title'])) {
			throw new \Exception('Could not find any product data on that page.');
		}

		if ($this->looksLikeJsRenderedShell($data, $html)) {
			throw new \Exception('This page appears to be rendered entirely by JavaScript and no usable data could be extracted, even after trying a headless-browser fetch.');
		}

		// Reject only when there is *positive* evidence this is a listing/
		// category/search page - NOT simply because the page lacks JSON-LD
		// Product schema or a price (plenty of legitimate single-product
		// pages, Amazon among them, publish neither).
		if (!$data['is_confident_product'] && ($data['is_confident_listing'] || $this->urlLooksLikeListing($url))) {
			throw new \Exception('That URL looks like a category/listing/search page, not a single product page.');
		}

		$this->load->model('catalog/product');

		// Model\Catalog\Product::editProduct() unconditionally calls
		// $this->model_design_seo_url->deleteSeoUrlsByKeyValue(...) (to clear
		// old SEO keywords before re-adding any) without loading that model
		// itself - it normally "just works" only because the stock admin
		// controller/catalog/product.php::save() action happens to load
		// design/seo_url earlier for its own purposes. We call editProduct()
		// directly with no controller in between, so without this the import
		// fails after the draft product is already created, with "Could not
		// call registry key model_design_seo_url!".
		$this->load->model('design/seo_url');

		$productId = $this->createDraftProduct();

		try {
			$imageResult = $this->downloadImages($data['images'] ?? []);
			$videoResult = $this->downloadVideos($data['videos'] ?? []);

			$updateData = $this->buildProductData($data, $imageResult['paths']);

			$specWarnings = [];
			$updateData['product_attribute'] = $this->resolveSpecificationAttributes(
				$data['specifications'] ?? [],
				$specWarnings
			);

			$this->model_catalog_product->editProduct($productId, $updateData);
		} catch (\Throwable $e) {
			// Something about finishing the product (most commonly an image
			// that downloaded fine but turned out not to be a real,
			// decodable image) failed after the bare draft product was
			// already created. Don't leave that empty orphan product
			// behind - clean it up and surface a clear error.
			$this->deleteOrphanProduct($productId);

			throw new \Exception('Import failed while saving the product: ' . $e->getMessage());
		}

		return [
			'product_id' => $productId,
			'warnings' => array_merge($imageResult['warnings'] ?? [], $specWarnings),
			'imported_video_paths' => $videoResult['paths'] ?? [],
		];
	}

	/**
	 * Whether the fetched HTML looks like a client-side-only JS app shell
	 * rather than real page content.
	 */
	protected function looksLikeJsRenderedShell(array $data, string $html): bool {
		return !$data['is_confident_product']
			&& empty($data['description'])
			&& empty($data['images'])
			&& $data['price'] === null
			&& strlen($html) < 50000;
	}

	/**
	 * Best-effort cleanup of the bare draft product created at the start of
	 * importFromUrl() when a later step fails.
	 */
	protected function deleteOrphanProduct(int $productId): void {
		try {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "product` WHERE `product_id` = '" . (int)$productId . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "product_description` WHERE `product_id` = '" . (int)$productId . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "product_image` WHERE `product_id` = '" . (int)$productId . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "product_attribute` WHERE `product_id` = '" . (int)$productId . "'");
		} catch (\Throwable $e) {
			// Cleanup is best-effort - don't let a failure here mask the
			// original error.
		}
	}

	/**
	 * Basic SSRF guard: only allow http(s) URLs pointing at a public host.
	 *
	 * @throws \Exception
	 */
	protected function guardAgainstUnsafeUrl(string $url): void {
		$parts = parse_url($url);

		if (empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https']) || empty($parts['host'])) {
			throw new \Exception('That does not look like a valid http(s) URL.');
		}

		$host = $parts['host'];

		if ($host === 'localhost' || str_ends_with($host, '.local')) {
			throw new \Exception('Importing from a local address is not allowed.');
		}

		$ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

		if (filter_var($ip, FILTER_VALIDATE_IP) && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			throw new \Exception('Importing from a private/internal address is not allowed.');
		}
	}

	/**
	 * Locate a CA bundle for cURL's SSL verification. Some (especially
	 * Windows/XAMPP) PHP installs ship with cURL but without curl.cainfo
	 * configured in php.ini, which makes every HTTPS request fail with
	 * "unable to get local issuer certificate".
	 */
	protected function resolveCaBundlePath() {
		$iniCainfo = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');

		if ($iniCainfo && file_exists($iniCainfo)) {
			return $iniCainfo;
		}

		foreach ([
			'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt',
			'C:\\xampp\\php\\extras\\ssl\\cacert.pem',
			'/etc/ssl/certs/ca-certificates.crt',
		] as $candidate) {
			if (file_exists($candidate)) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * A header set that mimics a real Chrome navigation closely enough to
	 * get past basic bot-protection (Akamai, etc.) on sites that check for
	 * more than just a plausible User-Agent string.
	 */
	protected function browserLikeHeaders(string $url): array {
		$host = parse_url($url, PHP_URL_HOST) ?: '';
		$origin = $host ? (parse_url($url, PHP_URL_SCHEME) ?: 'https') . '://' . $host : 'https://www.google.com/';

		return [
			'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
			'Accept-Language: en-US,en;q=0.9,fa;q=0.8',
			'sec-ch-ua: "Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
			'sec-ch-ua-mobile: ?0',
			'sec-ch-ua-platform: "Windows"',
			'sec-fetch-dest: document',
			'sec-fetch-mode: navigate',
			'sec-fetch-site: none',
			'sec-fetch-user: ?1',
			'upgrade-insecure-requests: 1',
			'Referer: ' . $origin,
		];
	}

	protected function newCurlHandle(string $url, array $extraHeaders = []) {
		$ch = curl_init($url);

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_ENCODING => '',
			CURLOPT_HTTPHEADER => array_merge($this->browserLikeHeaders($url), $extraHeaders),
		]);

		$caBundle = $this->resolveCaBundlePath();

		if ($caBundle) {
			curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
		} else {
			// No CA bundle could be located on this machine - fall back to
			// not verifying rather than making every single import fail
			// with a low-level SSL error. This mirrors a real risk
			// (man-in-the-middle) but the import result is always a
			// disabled draft an admin reviews before publishing, and the
			// alternative is the feature not working at all on a typical
			// XAMPP/Windows box.
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		}

		return $ch;
	}

	protected function fetchHtml(string $url): string {
		$ch = $this->newCurlHandle($url);

		$body = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($body === false || $error) {
			throw new \Exception('Could not fetch that URL: ' . $error);
		}

		if ($status >= 400) {
			throw new \Exception('That URL returned an error (HTTP ' . $status . ').');
		}

		return (string)$body;
	}

	/**
	 * Locate a local Chrome/Chromium executable to drive headlessly.
	 */
	protected function resolveChromePath(): ?string {
		foreach ([
			'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
			'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
			'C:\\Program Files\\Chromium\\Application\\chrome.exe',
			'/usr/bin/google-chrome',
			'/usr/bin/google-chrome-stable',
			'/usr/bin/chromium-browser',
			'/usr/bin/chromium',
		] as $candidate) {
			if (is_file($candidate)) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Render a page in a real, invisible (headless) local Chrome instance
	 * and return the fully JS-executed DOM as HTML - lets fully
	 * client-side-rendered storefronts (Digikala and similar) work, since a
	 * plain HTTP fetch only ever sees their empty pre-JS app shell.
	 *
	 * Best-effort: returns null (never throws) if Chrome isn't installed or
	 * the process fails for any reason.
	 */
	protected function fetchHtmlViaHeadlessChrome(string $url): ?string {
		if (!function_exists('proc_open')) {
			return null;
		}

		$chromePath = $this->resolveChromePath();

		if (!$chromePath) {
			return null;
		}

		$profileDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oc-import-chrome-' . bin2hex(random_bytes(6));

		$command = [
			$chromePath,
			'--headless=new',
			'--disable-gpu',
			'--no-sandbox',
			'--disable-extensions',
			'--disable-dev-shm-usage',
			'--hide-scrollbars',
			'--mute-audio',
			// Several storefronts detect the default headless fingerprint
			// (navigator.webdriver, a tiny default window size, missing
			// Accept-Language, etc.) and quietly serve a stripped-down page
			// in response. These flags close the most common detection
			// gaps without a full stealth-plugin dependency.
			'--disable-blink-features=AutomationControlled',
			'--window-size=1366,900',
			'--lang=en-US,en',
			// Virtual time, not wall-clock - gives the page's own JS time
			// to hydrate/fetch data before Chrome dumps the DOM.
			'--virtual-time-budget=15000',
			'--user-data-dir=' . $profileDir,
			'--dump-dom',
			$url,
		];

		$nullDevice = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'NUL' : '/dev/null';

		$descriptorSpec = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['file', $nullDevice, 'w'],
		];

		try {
			// 'bypass_shell' matters on Windows: without it, proc_open
			// wraps the command in `cmd /c`, which treats `&` (extremely
			// common in product URL query strings) as a command separator
			// and mangles the URL.
			$process = @proc_open($command, $descriptorSpec, $pipes, null, null, ['bypass_shell' => true]);
		} catch (\Throwable $e) {
			$process = false;
		}

		if (!is_resource($process)) {
			$this->removeDirectoryRecursively($profileDir);

			return null;
		}

		fclose($pipes[0]);

		$html = stream_get_contents($pipes[1]);

		fclose($pipes[1]);

		$exitCode = proc_close($process);

		$this->removeDirectoryRecursively($profileDir);

		if ($exitCode !== 0 || empty($html)) {
			return null;
		}

		return $html;
	}

	protected function removeDirectoryRecursively(string $dir): void {
		if (!is_dir($dir)) {
			return;
		}

		try {
			$items = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ($items as $item) {
				$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
			}

			@rmdir($dir);
		} catch (\Throwable $e) {
			// Leftover temp profile directories are harmless clutter.
		}
	}

	/**
	 * Extract title/description/images/price/currency/specifications/videos
	 * from HTML, preferring Schema.org JSON-LD (richer, structured) and
	 * falling back to Open Graph meta tags, then bare <title>.
	 */
	protected function extract(string $html, string $baseUrl): array {
		libxml_use_internal_errors(true);
		$dom = new \DOMDocument();
		$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
		libxml_clear_errors();
		$xpath = new \DOMXPath($dom);

		// Scopes the weaker, heuristic fallbacks below (generic image/spec/
		// price scraping) to roughly "the product page's own content"
		// instead of the whole document - otherwise those fallbacks pick up
		// unrelated images/text from navigation, "related products"
		// carousels, footers, etc. just as easily as the real product.
		$container = $this->locateProductContentContainer($dom);

		$result = [
			'title' => null,
			'description' => null,
			'images' => [],
			'videos' => [],
			'specifications' => [],
			'price' => null,
			'currency' => null,
			'is_confident_product' => false,
			'is_confident_listing' => false,
		];

		// 1. JSON-LD Product schema.
		foreach ($xpath->query('//script[@type="application/ld+json"]') as $node) {
			$decoded = json_decode($node->textContent, true);

			if (json_last_error() !== JSON_ERROR_NONE) {
				continue;
			}

			if ($this->findListingSignal($decoded)) {
				$result['is_confident_listing'] = true;
			}

			$product = $this->findProductNode($decoded);

			if (!$product) {
				continue;
			}

			$result['is_confident_product'] = true;

			$result['title'] = $result['title'] ?: ($product['name'] ?? null);
			$result['description'] = $result['description'] ?: ($product['description'] ?? null);

			if (!empty($product['image'])) {
				$result['images'] = array_merge($result['images'], $this->normalizeImageField($product['image']));
			}

			if (!empty($product['video'])) {
				$result['videos'] = array_merge($result['videos'], $this->normalizeVideoField($product['video']));
			}

			if (!empty($product['additionalProperty'])) {
				$result['specifications'] = array_merge(
					$result['specifications'],
					$this->normalizeAdditionalProperties($product['additionalProperty'])
				);
			}

			$offers = $product['offers'] ?? null;
			$offer = is_array($offers) && isset($offers[0]) ? $offers[0] : $offers;

			if (is_array($offer)) {
				$result['price'] = $result['price'] ?? ($offer['price'] ?? null);
				$result['currency'] = $result['currency'] ?? ($offer['priceCurrency'] ?? null);
			}

			break;
		}

		// 2. Open Graph fallback.
		if ($this->metaContent($xpath, 'og:type') === 'product') {
			$result['is_confident_product'] = true;
		}

		$result['title'] = $result['title'] ?: $this->metaContent($xpath, 'og:title');
		$result['description'] = $result['description'] ?: $this->metaContent($xpath, 'og:description');

		foreach ($xpath->query('//meta[@property="og:image"]') as $node) {
			$content = $node->getAttribute('content');
			if ($content) {
				$result['images'][] = $content;
			}
		}

		foreach ($xpath->query('//meta[@property="og:video" or @property="og:video:url" or @property="og:video:secure_url"]') as $node) {
			$content = $node->getAttribute('content');
			if ($content) {
				$result['videos'][] = $content;
			}
		}

		if ($result['price'] === null) {
			$result['price'] = $this->metaContent($xpath, 'product:price:amount') ?: $this->metaContent($xpath, 'og:price:amount');
		}

		if ($result['currency'] === null) {
			$result['currency'] = $this->metaContent($xpath, 'product:price:currency') ?: $this->metaContent($xpath, 'og:price:currency');
		}

		if ($result['price'] === null) {
			$priceGuess = $this->extractPriceFromHtml($xpath, $container);

			if ($priceGuess) {
				$result['price'] = $priceGuess['price'];
				$result['currency'] = $priceGuess['currency'];
			}
		}

		// 3. Bare <title> tag fallback.
		if (empty($result['title'])) {
			$titleNodes = $dom->getElementsByTagName('title');
			if ($titleNodes->length) {
				$result['title'] = trim($titleNodes->item(0)->textContent);
			}
		}

		// 4. <video>/<source> tags - direct, downloadable video files only.
		foreach ($xpath->query('//video[@src]') as $node) {
			$result['videos'][] = $node->getAttribute('src');
		}

		foreach ($xpath->query('//video/source[@src]') as $node) {
			$result['videos'][] = $node->getAttribute('src');
		}

		// 4b. Neither JSON-LD nor Open Graph gave us any images - fall back
		// to the actual gallery markup, scoped to $container so this
		// doesn't sweep up header logos, "related products" carousels,
		// footer badges, etc.
		if (empty($result['images'])) {
			foreach ($xpath->query('.//picture/source[@srcset]', $container) as $node) {
				if ($node->parentNode instanceof \DOMElement && $this->looksLikeIconImage($node->parentNode)) {
					continue;
				}

				$firstUrl = $this->firstUrlFromSrcset($node->getAttribute('srcset'));

				if ($firstUrl) {
					$result['images'][] = $firstUrl;
				}
			}
		}

		if (empty($result['images'])) {
			foreach ($xpath->query('.//img[@src]', $container) as $node) {
				if ($this->looksLikeIconImage($node)) {
					continue;
				}

				$src = $node->getAttribute('src');

				if ($src && !str_starts_with($src, 'data:')) {
					$result['images'][] = $src;
				}
			}
		}

		// 5. Generic HTML specification fallback - only used to top up
		// when JSON-LD didn't already give us a decent number of specs.
		if (count($result['specifications']) < 3) {
			$result['specifications'] = array_merge($result['specifications'], $this->extractSpecsFromHtml($xpath, $container));
		}

		if (count($result['specifications']) < 3) {
			$result['specifications'] = array_merge($result['specifications'], $this->extractLabelValuePairsFromHtml($xpath, $container));
		}

		// Resolve relative image/video URLs to absolute, dedupe, cap.
		$result['images'] = array_values(array_unique(array_map(
			fn($src) => $this->resolveUrl($src, $baseUrl),
			array_filter($result['images'])
		)));
		$result['images'] = array_slice($result['images'], 0, 6);

		$result['videos'] = array_values(array_unique(array_map(
			fn($src) => $this->resolveUrl($src, $baseUrl),
			array_filter($result['videos'])
		)));
		$result['videos'] = array_slice($result['videos'], 0, 2);

		$result['specifications'] = array_slice($this->dedupeSpecifications($result['specifications']), 0, 25);

		if (is_string($result['price'])) {
			$normalized = preg_replace('/[^0-9.]/', '', $result['price']);
			$result['price'] = $normalized !== '' ? (float)$normalized : null;
		}

		// Sanity cap: a genuinely broken extraction (a mis-parsed offer
		// object, a matched "price"-classed element that was actually a
		// review count / phone number / SKU, etc.) can occasionally produce
		// an absurdly large number rather than a real price. Rather than
		// saving that (it previously silently clamped to a DB column's max
		// value on the Bagisto version of this feature - a real bug found
		// and left unresolved there), treat anything above a generous
		// ceiling as "not found" instead.
		if ($result['price'] !== null && ($result['price'] <= 0 || $result['price'] > 100000000)) {
			$result['price'] = null;
			$result['currency'] = null;
		}

		return $result;
	}

	protected function findProductNode($decoded): ?array {
		if (!is_array($decoded)) {
			return null;
		}

		if (array_is_list($decoded)) {
			foreach ($decoded as $node) {
				if ($found = $this->findProductNode($node)) {
					return $found;
				}
			}

			return null;
		}

		if (!empty($decoded['@graph'])) {
			return $this->findProductNode($decoded['@graph']);
		}

		$type = $decoded['@type'] ?? null;
		$types = is_array($type) ? $type : [$type];

		if (in_array('Product', $types, true)) {
			return $decoded;
		}

		return null;
	}

	protected function findListingSignal($decoded): bool {
		if (!is_array($decoded)) {
			return false;
		}

		if (array_is_list($decoded)) {
			foreach ($decoded as $node) {
				if ($this->findListingSignal($node)) {
					return true;
				}
			}

			return false;
		}

		if (!empty($decoded['@graph']) && $this->findListingSignal($decoded['@graph'])) {
			return true;
		}

		$type = $decoded['@type'] ?? null;
		$types = is_array($type) ? $type : [$type];

		foreach (['ItemList', 'CollectionPage', 'SearchResultsPage', 'OfferCatalog'] as $listingType) {
			if (in_array($listingType, $types, true)) {
				return true;
			}
		}

		return false;
	}

	protected function urlLooksLikeListing(string $url): bool {
		$path = parse_url($url, PHP_URL_PATH) ?: '';
		$segments = array_filter(explode('/', $path), fn($s) => $s !== '');

		$listingSegments = [
			'c', 's', 'category', 'categories', 'collection', 'collections',
			'search', 'list', 'catalogsearch', 'product-category', 'shop-by',
		];

		foreach ($segments as $segment) {
			if (in_array(strtolower($segment), $listingSegments, true)) {
				return true;
			}
		}

		return false;
	}

	protected function normalizeImageField($image): array {
		if (is_string($image)) {
			return [$image];
		}

		if (is_array($image)) {
			if (isset($image['url'])) {
				return [$image['url']];
			}

			$urls = [];
			foreach ($image as $item) {
				$urls = array_merge($urls, $this->normalizeImageField($item));
			}

			return $urls;
		}

		return [];
	}

	protected function normalizeVideoField($video): array {
		if (is_string($video)) {
			return [$video];
		}

		if (!is_array($video)) {
			return [];
		}

		if (array_is_list($video)) {
			$urls = [];
			foreach ($video as $item) {
				$urls = array_merge($urls, $this->normalizeVideoField($item));
			}

			return $urls;
		}

		if (!empty($video['contentUrl']) && is_string($video['contentUrl'])) {
			return [$video['contentUrl']];
		}

		return [];
	}

	protected function normalizeAdditionalProperties($properties): array {
		if (!is_array($properties)) {
			return [];
		}

		if (!array_is_list($properties)) {
			$properties = [$properties];
		}

		$specs = [];

		foreach ($properties as $prop) {
			if (!is_array($prop)) {
				continue;
			}

			$name = trim((string)($prop['name'] ?? ''));
			$value = $prop['value'] ?? null;
			$value = is_array($value) ? (string)($value['name'] ?? '') : (string)$value;
			$value = trim($value);

			if ($name === '' || $value === '') {
				continue;
			}

			$specs[] = ['name' => $name, 'value' => $value];
		}

		return $specs;
	}

	protected function extractSpecsFromHtml(\DOMXPath $xpath, ?\DOMElement $container = null): array {
		$specs = [];

		foreach ($xpath->query('.//table//tr', $container) as $row) {
			if (count($specs) >= 40) {
				break;
			}

			$ths = $xpath->query('.//th', $row);
			$tds = $xpath->query('.//td', $row);

			if ($ths->length >= 1 && $tds->length >= 1) {
				$name = trim($ths->item(0)->textContent);
				$value = trim($tds->item($tds->length - 1)->textContent);
			} elseif ($tds->length === 2) {
				$name = trim($tds->item(0)->textContent);
				$value = trim($tds->item(1)->textContent);
			} else {
				continue;
			}

			$this->pushSpecIfPlausible($specs, $name, $value);
		}

		foreach ($xpath->query('.//dl', $container) as $dl) {
			if (count($specs) >= 40) {
				break;
			}

			$terms = $xpath->query('./dt', $dl);
			$definitions = $xpath->query('./dd', $dl);

			$count = min($terms->length, $definitions->length);

			for ($i = 0; $i < $count; $i++) {
				$this->pushSpecIfPlausible($specs, trim($terms->item($i)->textContent), trim($definitions->item($i)->textContent));
			}
		}

		return $specs;
	}

	protected function hasExactlyTwoElementChildren(\DOMXPath $xpath, \DOMNode $node): bool {
		return $node instanceof \DOMElement && $xpath->query('*', $node)->length === 2;
	}

	protected function extractLabelValuePairsFromHtml(\DOMXPath $xpath, ?\DOMElement $container = null): array {
		$rowsByParent = [];

		foreach ($xpath->query('.//div[count(*) = 2] | .//li[count(*) = 2]', $container) as $node) {
			if (count($rowsByParent, COUNT_RECURSIVE) > 600) {
				break;
			}

			$children = $xpath->query('*', $node);

			if ($children->length !== 2) {
				continue;
			}

			// Skip when either child is ITSELF a 2-element-child node -
			// that means $node is a wrapping ancestor one level above the
			// real label/value row, not the row itself.
			if ($this->hasExactlyTwoElementChildren($xpath, $children->item(0)) || $this->hasExactlyTwoElementChildren($xpath, $children->item(1))) {
				continue;
			}

			$name = trim(preg_replace('/\s+/u', ' ', $children->item(0)->textContent) ?? '');
			$value = trim(preg_replace('/\s+/u', ' ', $children->item(1)->textContent) ?? '');

			if ($name === '' || $value === '' || mb_strlen($name) > 60 || mb_strlen($value) > 300 || $name === $value) {
				continue;
			}

			$parent = $node->parentNode;
			$parentKey = $parent ? spl_object_id($parent) : 0;

			$rowsByParent[$parentKey][] = ['name' => $name, 'value' => $value];
		}

		$specs = [];

		foreach ($rowsByParent as $rows) {
			if (count($rows) < 3) {
				continue;
			}

			foreach ($rows as $row) {
				if (count($specs) >= 40) {
					break 2;
				}

				$this->pushSpecIfPlausible($specs, $row['name'], $row['value']);
			}
		}

		return $specs;
	}

	protected function extractPriceFromHtml(\DOMXPath $xpath, ?\DOMElement $container = null): ?array {
		$nodes = $xpath->query(
			'.//*[
				(contains(@data-testid, "price") or contains(@data-testid, "Price")
					or contains(@class, "price") or contains(@class, "Price"))
				and not(contains(@data-testid, "range")) and not(contains(@class, "range"))
				and not(contains(@data-testid, "filter")) and not(contains(@class, "filter"))
				and not(contains(@data-testid, "slider")) and not(contains(@class, "slider"))
			]',
			$container
		);

		foreach ($nodes as $node) {
			$rawText = trim($node->textContent);

			if ($rawText === '' || mb_strlen($rawText) > 40) {
				continue;
			}

			$normalizedText = $this->normalizePersianDigits($rawText);

			if (!preg_match('/\d[\d,.\s]{1,14}\d|\d{3,15}/', $normalizedText, $m)) {
				continue;
			}

			$price = $this->parsePriceToken($m[0]);

			if ($price === null) {
				continue;
			}

			return [
				'price' => $price,
				'currency' => $normalizedText !== $rawText ? 'IRT' : $this->guessCurrencyCode($rawText),
			];
		}

		return null;
	}

	/**
	 * Turns a scraped number-ish substring - "111.13", "1,234.56",
	 * "65,490,000", "1.234,56" - into a plain float, correctly telling a
	 * decimal separator apart from a thousands separator instead of just
	 * stripping every non-digit character (which silently inflates a price
	 * like "AED 111.13" into 11113).
	 */
	protected function parsePriceToken(string $token): ?float {
		$token = preg_replace('/\s+/', '', trim($token));

		if ($token === '') {
			return null;
		}

		$lastComma = strrpos($token, ',');
		$lastDot = strrpos($token, '.');

		if ($lastComma !== false && $lastDot !== false) {
			if ($lastDot > $lastComma) {
				$token = str_replace(',', '', $token);
			} else {
				$token = str_replace('.', '', $token);
				$token = substr_replace($token, '.', strrpos($token, ','), 1);
			}
		} elseif ($lastComma !== false) {
			$decimalDigits = strlen($token) - $lastComma - 1;
			$token = ($decimalDigits >= 1 && $decimalDigits <= 2)
				? substr_replace($token, '.', $lastComma, 1)
				: str_replace(',', '', $token);
		} elseif ($lastDot !== false) {
			$decimalDigits = strlen($token) - $lastDot - 1;
			if ($decimalDigits > 2) {
				$token = str_replace('.', '', $token);
			}
		}

		$digitsOnly = preg_replace('/[^0-9]/', '', $token);

		if ($digitsOnly === '' || strlen($digitsOnly) < 3 || strlen($digitsOnly) > 15) {
			return null;
		}

		$value = (float)$token;

		// Same sanity cap applied in extract() - a "price"-classed element
		// whose visible text is actually a review count, phone number, or
		// similar large unrelated number should be treated as not-found
		// rather than saved as a wildly wrong price.
		if ($value <= 0 || $value > 100000000) {
			return null;
		}

		return $value;
	}

	protected function guessCurrencyCode(string $text): ?string {
		if (preg_match('/(?<![A-Za-z])([A-Z]{3})(?=[0-9]|\s|$)/', $text, $m)) {
			return $m[1];
		}

		$symbols = ['$' => 'USD', '€' => 'EUR', '£' => 'GBP', '₹' => 'INR', '¥' => 'JPY'];

		foreach ($symbols as $symbol => $code) {
			if (str_contains($text, $symbol)) {
				return $code;
			}
		}

		return null;
	}

	protected function normalizePersianDigits(string $value): string {
		static $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
		static $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
		static $ascii = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

		return str_replace($arabic, $ascii, str_replace($persian, $ascii, $value));
	}

	protected function pickProductHeading(\DOMNodeList $headings): ?\DOMElement {
		$best = null;
		$bestLength = -1;

		foreach ($headings as $heading) {
			if (!$heading instanceof \DOMElement) {
				continue;
			}

			$class = strtolower($heading->getAttribute('class'));
			$style = strtolower($heading->getAttribute('style'));

			if (
				str_contains($class, 'sr-only')
				|| str_contains($class, 'visually-hidden')
				|| str_contains($class, 'visuallyhidden')
				|| str_contains($class, 'screen-reader')
				|| str_contains($style, 'display:none')
				|| str_contains($style, 'display: none')
				|| str_contains($style, 'visibility:hidden')
				|| str_contains($style, 'visibility: hidden')
			) {
				continue;
			}

			$text = trim(preg_replace('/\s+/u', ' ', $heading->textContent) ?? '');
			$length = mb_strlen($text);

			if ($length > $bestLength) {
				$best = $heading;
				$bestLength = $length;
			}
		}

		return $best ?? ($headings->length ? $headings->item(0) : null);
	}

	protected function locateProductContentContainer(\DOMDocument $dom): ?\DOMElement {
		$headings = $dom->getElementsByTagName('h1');

		if (!$headings->length) {
			return null;
		}

		$heading = $this->pickProductHeading($headings);

		if (!$heading) {
			return null;
		}

		$ancestor = $heading->parentNode;
		$depth = 0;

		while ($ancestor instanceof \DOMElement && $depth < 12) {
			$textLength = mb_strlen(trim(preg_replace('/\s+/u', ' ', $ancestor->textContent) ?? ''));

			if ($this->countRealContentImages($ancestor) >= 1 && $textLength >= 200) {
				return $ancestor;
			}

			$ancestor = $ancestor->parentNode;
			$depth++;
		}

		return null;
	}

	protected function countRealContentImages(\DOMElement $element): int {
		$count = 0;

		foreach ($element->getElementsByTagName('img') as $img) {
			if (!$this->looksLikeIconImage($img)) {
				$count++;
			}
		}

		return $count;
	}

	protected function looksLikeIconImage(\DOMElement $element): bool {
		$img = $element->tagName === 'img' ? $element : $element->getElementsByTagName('img')->item(0);

		if (!$img instanceof \DOMElement) {
			$img = $element;
		}

		$width = (int)$img->getAttribute('width');
		$height = (int)$img->getAttribute('height');

		return ($width > 0 && $width <= 40) || ($height > 0 && $height <= 40);
	}

	protected function pushSpecIfPlausible(array &$specs, string $name, string $value): void {
		if (
			$name === ''
			|| $value === ''
			|| mb_strlen($name) < 2
			|| mb_strlen($name) > 60
			|| mb_strlen($value) > 300
			|| $name === $value
			|| !preg_match('/[\p{L}\p{N}]/u', $name)
		) {
			return;
		}

		$specs[] = ['name' => $name, 'value' => $value];
	}

	protected function dedupeSpecifications(array $specs): array {
		$seen = [];
		$deduped = [];

		foreach ($specs as $spec) {
			$key = mb_strtolower(trim($spec['name']));

			if ($key === '' || isset($seen[$key])) {
				continue;
			}

			$seen[$key] = true;
			$deduped[] = $spec;
		}

		return $deduped;
	}

	protected function metaContent(\DOMXPath $xpath, string $property): ?string {
		$nodes = $xpath->query("//meta[@property=\"{$property}\"]");

		if ($nodes->length) {
			return $nodes->item(0)->getAttribute('content') ?: null;
		}

		$nodes = $xpath->query("//meta[@name=\"{$property}\"]");

		return $nodes->length ? ($nodes->item(0)->getAttribute('content') ?: null) : null;
	}

	protected function firstUrlFromSrcset(string $srcset): string {
		$srcset = trim($srcset);

		if ($srcset === '') {
			return '';
		}

		$candidates = preg_split('/,\s+/', $srcset) ?: [$srcset];
		$firstCandidate = trim($candidates[0]);

		return trim(strtok($firstCandidate, " \t\n"));
	}

	protected function resolveUrl(string $src, string $baseUrl): string {
		if (preg_match('#^https?://#i', $src)) {
			return $src;
		}

		$base = parse_url($baseUrl);
		$scheme = $base['scheme'] ?? 'https';
		$host = $base['host'] ?? '';
		$port = isset($base['port']) ? ':' . $base['port'] : '';

		if (str_starts_with($src, '//')) {
			return $scheme . ':' . $src;
		}

		if (str_starts_with($src, '/')) {
			return "{$scheme}://{$host}{$port}{$src}";
		}

		$basePath = isset($base['path']) ? preg_replace('#/[^/]*$#', '/', $base['path']) : '/';

		return "{$scheme}://{$host}{$port}{$basePath}{$src}";
	}

	/**
	 * Download up to a handful of images into image/catalog/imported/.
	 * Returns ['paths' => string[] (relative to DIR_IMAGE), 'warnings' => string[]].
	 */
	protected function downloadImages(array $urls): array {
		$paths = [];
		$warnings = [];

		$targetDir = DIR_IMAGE . 'catalog/imported/';

		if (!is_dir($targetDir)) {
			@mkdir($targetDir, 0755, true);
		}

		$deadline = microtime(true) + 90;

		foreach ($urls as $url) {
			if (microtime(true) > $deadline) {
				$warnings[] = 'Stopped downloading images after 90s - some may be missing.';
				break;
			}

			try {
				$ch = $this->newCurlHandle($url);
				curl_setopt($ch, CURLOPT_TIMEOUT, 10);

				$body = curl_exec($ch);
				$contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close($ch);

				if ($body === false || $httpCode >= 400) {
					continue;
				}

				if (!str_starts_with($contentType, 'image/') || str_contains($contentType, 'svg')) {
					// SVGs are vector markup, not something we rasterize -
					// skip them outright.
					continue;
				}

				if (strlen($body) < 200) {
					continue;
				}

				$extension = match (true) {
					str_contains($contentType, 'png') => 'png',
					str_contains($contentType, 'webp') => 'webp',
					str_contains($contentType, 'gif') => 'gif',
					str_contains($contentType, 'avif') => 'avif',
					default => 'jpg',
				};

				$tmpPath = tempnam(sys_get_temp_dir(), 'ocimgimport') . '.' . $extension;
				file_put_contents($tmpPath, $body);

				// Some sites report a plausible image/* content-type for
				// bytes that aren't actually a decodable image (placeholder
				// pixels, broken CDN responses, formats GD doesn't
				// support). Decode-check it here so a single bad image is
				// just skipped instead of corrupting the catalog.
				if (@getimagesize($tmpPath) === false) {
					@unlink($tmpPath);
					continue;
				}

				$filename = bin2hex(random_bytes(10)) . '.' . $extension;

				if (@rename($tmpPath, $targetDir . $filename)) {
					$paths[] = 'catalog/imported/' . $filename;
				} else {
					@unlink($tmpPath);
				}
			} catch (\Throwable $e) {
				continue;
			}
		}

		return ['paths' => $paths, 'warnings' => $warnings];
	}

	/**
	 * Download up to a couple of directly-downloadable product video files
	 * (mp4/webm/ogg). Stock OpenCart has no product-video gallery field, so
	 * these are saved to disk under image/catalog/imported/videos/ and
	 * their paths are surfaced to the admin rather than attached anywhere.
	 */
	protected function downloadVideos(array $urls): array {
		$paths = [];

		$targetDir = DIR_IMAGE . 'catalog/imported/videos/';

		if (!is_dir($targetDir)) {
			@mkdir($targetDir, 0755, true);
		}

		$deadline = microtime(true) + 40;

		foreach (array_slice($urls, 0, 2) as $url) {
			if (microtime(true) > $deadline) {
				break;
			}

			try {
				$ch = $this->newCurlHandle($url);
				curl_setopt($ch, CURLOPT_TIMEOUT, 20);

				$body = curl_exec($ch);
				$contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
				curl_close($ch);

				if ($body === false || !str_starts_with($contentType, 'video/')) {
					continue;
				}

				if (strlen($body) > 25 * 1024 * 1024 || strlen($body) < 1000) {
					continue;
				}

				$extension = match (true) {
					str_contains($contentType, 'webm') => 'webm',
					str_contains($contentType, 'ogg') => 'ogv',
					default => 'mp4',
				};

				$filename = bin2hex(random_bytes(10)) . '.' . $extension;

				if (file_put_contents($targetDir . $filename, $body) !== false) {
					$paths[] = 'catalog/imported/videos/' . $filename;
				}
			} catch (\Throwable $e) {
				continue;
			}
		}

		return ['paths' => $paths];
	}

	protected function createDraftProduct(): int {
		$this->load->model('catalog/product');

		return $this->model_catalog_product->addProduct([
			'master_id' => 0,
			'model' => $this->generateUniqueModel(),
			'location' => '',
			'variant' => [],
			'override' => [],
			'quantity' => 1,
			'minimum' => 1,
			'subtract' => 1,
			'stock_status_id' => $this->resolveDefaultId('stock_status', 'stock_status_id'),
			'date_available' => date('Y-m-d'),
			'manufacturer_id' => 0,
			'shipping' => 1,
			'price' => 0,
			'points' => 0,
			'weight' => 0,
			'weight_class_id' => $this->resolveDefaultId('weight_class', 'weight_class_id'),
			'length' => 0,
			'width' => 0,
			'height' => 0,
			'length_class_id' => $this->resolveDefaultId('length_class', 'length_class_id'),
			'status' => 0,
			'tax_class_id' => 0,
			'sort_order' => 0,
			'image' => '',
			'product_description' => [
				$this->getDefaultLanguageId() => [
					'name' => 'Untitled imported product',
					'description' => '',
					'tag' => '',
					'meta_title' => 'Untitled imported product',
					'meta_description' => '',
					'meta_keyword' => '',
				],
			],
		]);
	}

	/**
	 * Picks any existing id from a lookup table (weight_class, length_class,
	 * stock_status) rather than hardcoding one that may not exist on a
	 * given install's seed data.
	 */
	protected function resolveDefaultId(string $table, string $column): int {
		$query = $this->db->query("SELECT `" . $column . "` FROM `" . DB_PREFIX . $table . "` ORDER BY `" . $column . "` ASC LIMIT 1");

		return $query->num_rows ? (int)$query->row[$column] : 0;
	}

	protected function getDefaultLanguageId(): int {
		$configLanguageId = (int)$this->config->get('config_language_id');

		if ($configLanguageId) {
			return $configLanguageId;
		}

		$query = $this->db->query("SELECT `language_id` FROM `" . DB_PREFIX . "language` ORDER BY `language_id` ASC LIMIT 1");

		return $query->num_rows ? (int)$query->row['language_id'] : 1;
	}

	protected function generateUniqueModel(): string {
		do {
			$model = 'import-' . strtolower(bin2hex(random_bytes(5)));

			$query = $this->db->query("SELECT `product_id` FROM `" . DB_PREFIX . "product` WHERE `model` = '" . $this->db->escape($model) . "'");
		} while ($query->num_rows);

		return $model;
	}

	protected function buildProductData(array $data, array $imagePaths): array {
		$title = trim($data['title']);
		$price = $data['price'];
		$currency = $data['currency'] ? strtoupper($data['currency']) : null;

		$shortDescription = trim(strip_tags((string)($data['description'] ?? '')));
		$shortDescription = mb_substr($shortDescription, 0, 480);

		$priceNote = '';

		if ($price === null) {
			$priceNote = '[Import note: no price could be found on the source page - please set one.]';
		} elseif ($currency && !in_array($currency, ['USD'], true) && $currency !== null) {
			// This catalog's default currency isn't known from inside the
			// model - always leave an explicit note of the *source* price/
			// currency so the admin can convert/verify it manually rather
			// than silently trusting a number that may be in the wrong
			// currency entirely.
			$priceNote = "[Import note: source page listed this at approximately {$price} {$currency} - verify/convert the price below.]";
		}

		$description = $title !== '' ? '<p>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>' : '';

		if ($data['description']) {
			$description = '<p>' . nl2br(htmlspecialchars(trim((string)$data['description']), ENT_QUOTES, 'UTF-8')) . '</p>';
		}

		$metaDescription = $priceNote ? ($priceNote . ' ' . $shortDescription) : $shortDescription;

		$languageId = $this->getDefaultLanguageId();

		$productImages = [];
		foreach (array_slice($imagePaths, 1) as $index => $path) {
			$productImages[] = ['image' => $path, 'sort_order' => $index];
		}

		return [
			'model' => $this->generateUniqueModel(),
			'location' => '',
			'variant' => [],
			'override' => [],
			'quantity' => 1,
			'minimum' => 1,
			'subtract' => 1,
			'stock_status_id' => $this->resolveDefaultId('stock_status', 'stock_status_id'),
			'date_available' => date('Y-m-d'),
			'manufacturer_id' => 0,
			'shipping' => 1,
			'price' => $price ?: 0,
			'points' => 0,
			'weight' => 0,
			'weight_class_id' => $this->resolveDefaultId('weight_class', 'weight_class_id'),
			'length' => 0,
			'width' => 0,
			'height' => 0,
			'length_class_id' => $this->resolveDefaultId('length_class', 'length_class_id'),
			'status' => 0,
			'tax_class_id' => 0,
			'sort_order' => 0,
			'image' => $imagePaths[0] ?? '',
			'product_description' => [
				$languageId => [
					'name' => mb_substr($title, 0, 250),
					'description' => $description,
					'tag' => '',
					'meta_title' => mb_substr($title, 0, 250),
					'meta_description' => mb_substr($metaDescription, 0, 250),
					'meta_keyword' => '',
				],
			],
			'product_image' => $productImages,
		];
	}

	/**
	 * Turns extracted (name, value) specification pairs into real OpenCart
	 * catalog attributes - creating them (and a dedicated "Imported
	 * Specifications" attribute group) the first time a given spec name is
	 * seen, and reusing the same attribute on every later import that
	 * scrapes a spec with the same name. Returns the array shape
	 * addProduct()/editProduct() expects under 'product_attribute'.
	 *
	 * Deliberately NOT stored as a plain text block in the description:
	 * real attributes are filterable/comparable and show up properly on
	 * the product's Attribute tab, matching how every other attribute in
	 * this catalog works. The trade-off - importing from many different
	 * sites over time will keep growing this attribute list - is the same
	 * one accepted for the Bagisto version of this feature.
	 *
	 * Best-effort throughout: a failure resolving/saving one particular
	 * spec is skipped (and recorded in &$warnings) rather than failing the
	 * whole import.
	 */
	protected function resolveSpecificationAttributes(array $specifications, array &$warnings): array {
		if (empty($specifications)) {
			return [];
		}

		$languageId = $this->getDefaultLanguageId();

		try {
			$groupId = $this->findOrCreateSpecificationsGroup();
		} catch (\Throwable $e) {
			$warnings[] = 'Could not create/find the "Imported Specifications" attribute group - specs were not saved.';

			return [];
		}

		$productAttributes = [];

		foreach (array_slice($specifications, 0, 25) as $spec) {
			try {
				$attributeId = $this->findOrCreateSpecAttribute($groupId, $spec['name'], $languageId);

				$productAttributes[] = [
					'attribute_id' => $attributeId,
					'product_attribute_description' => [
						$languageId => ['text' => mb_substr($spec['value'], 0, 490)],
					],
				];
			} catch (\Throwable $e) {
				$warnings[] = 'Skipped spec "' . $spec['name'] . '" (' . $e->getMessage() . ').';

				continue;
			}
		}

		return $productAttributes;
	}

	protected function findOrCreateSpecificationsGroup(): int {
		$name = 'Imported Specifications';
		$languageId = $this->getDefaultLanguageId();

		$query = $this->db->query(
			"SELECT `attribute_group_id` FROM `" . DB_PREFIX . "attribute_group_description` "
			. "WHERE `language_id` = '" . (int)$languageId . "' AND `name` = '" . $this->db->escape($name) . "' LIMIT 1"
		);

		if ($query->num_rows) {
			return (int)$query->row['attribute_group_id'];
		}

		$this->load->model('catalog/attribute_group');

		return $this->model_catalog_attribute_group->addAttributeGroup([
			'attribute_group_description' => [
				$languageId => ['name' => $name],
			],
			'sort_order' => 0,
		]);
	}

	/**
	 * Finds an existing attribute (scoped to our own "Imported
	 * Specifications" group, so this never collides with or reuses an
	 * unrelated store attribute that happens to share a name) whose
	 * translated name matches case/whitespace-insensitively, or creates a
	 * new one.
	 */
	protected function findOrCreateSpecAttribute(int $groupId, string $name, int $languageId): int {
		$query = $this->db->query(
			"SELECT `a`.`attribute_id` FROM `" . DB_PREFIX . "attribute` `a` "
			. "JOIN `" . DB_PREFIX . "attribute_description` `ad` ON (`ad`.`attribute_id` = `a`.`attribute_id`) "
			. "WHERE `a`.`attribute_group_id` = '" . (int)$groupId . "' "
			. "AND `ad`.`language_id` = '" . (int)$languageId . "' "
			. "AND LOWER(TRIM(`ad`.`name`)) = '" . $this->db->escape(mb_strtolower(trim($name))) . "' LIMIT 1"
		);

		if ($query->num_rows) {
			return (int)$query->row['attribute_id'];
		}

		$this->load->model('catalog/attribute');

		return $this->model_catalog_attribute->addAttribute([
			'attribute_group_id' => $groupId,
			'sort_order' => 0,
			'attribute_description' => [
				$languageId => ['name' => mb_substr(trim($name), 0, 64)],
			],
		]);
	}
}
