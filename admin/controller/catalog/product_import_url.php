<?php
namespace Opencart\Admin\Controller\Catalog;
/**
 * Class ProductImportUrl
 *
 * Admin UI for importing a product draft from an external product page URL
 * (Amazon, Digikala, Virgin Megastore, or any other storefront that
 * publishes Open Graph / Schema.org metadata). Ported from the equivalent
 * Bagisto "Import Product from URL" feature - see
 * Opencart\Admin\Model\Catalog\ProductImportUrl for the actual extraction/
 * import logic.
 *
 * Can be loaded using $this->load->controller('catalog/product_import_url');
 *
 * @package Opencart\Admin\Controller\Catalog
 */
class ProductImportUrl extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('catalog/product_import_url');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']),
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_product'),
			'href' => $this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token']),
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/product_import_url', 'user_token=' . $this->session->data['user_token']),
		];

		$data['save'] = $this->url->link('catalog/product_import_url.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token']);
		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/product_import_url', $data));
	}

	/**
	 * Save (AJAX) - runs the import for a single submitted URL.
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('catalog/product_import_url');

		$json = [];

		if (!$this->user->hasPermission('modify', 'catalog/product_import_url')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$url = (string)($this->request->post['url'] ?? '');

		if (!$json && $url === '') {
			$json['error'] = $this->language->get('error_url_required');
		}

		if (!$json) {
			$this->load->model('catalog/product_import_url');

			try {
				$result = $this->model_catalog_product_import_url->importFromUrl($url);

				$json['success'] = $this->language->get('text_success');
				$json['product_id'] = $result['product_id'];
				$json['edit'] = $this->url->link('catalog/product.form', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $result['product_id']);
				$json['warnings'] = $result['warnings'];
				$json['imported_video_paths'] = $result['imported_video_paths'];
			} catch (\Throwable $e) {
				$json['error'] = $e->getMessage();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
