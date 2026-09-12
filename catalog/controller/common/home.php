<?php
namespace MDcart\Catalog\Controller\Common;
/**
 * Class Home
 *
 * Can be called from $this->load->controller('common/home');
 *
 * @package MDcart\Catalog\Controller\Common
 */
class Home extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('common/home');

		$description = $this->config->get('config_description');
		$language_id = $this->config->get('config_language_id');

		if (isset($description[$language_id])) {
			$this->document->setTitle($description[$language_id]['meta_title']);
			$this->document->setDescription($description[$language_id]['meta_description']);
			$this->document->setKeywords($description[$language_id]['meta_keyword']);
		}

		$data['name'] = $this->config->get('config_name');

		// Used only by the optional storefront themes' hero/banner sections (Design > Theme).
		// Kept translatable so the hero switches language along with the rest of the site.
		$data['text_hero_tagline'] = $this->language->get('text_hero_tagline');
		$data['button_hero_products'] = $this->language->get('button_hero_products');
		$data['button_hero_special'] = $this->language->get('button_hero_special');
		$data['text_whoop_stat_value'] = $this->language->get('text_whoop_stat_value');
		$data['text_whoop_stat_label'] = $this->language->get('text_whoop_stat_label');
		$data['text_whoop_banner_heading'] = $this->language->get('text_whoop_banner_heading');
		$data['text_whoop_banner_text'] = $this->language->get('text_whoop_banner_text');
		$data['text_categories_heading'] = $this->language->get('text_categories_heading');
		$data['text_new_products_heading'] = $this->language->get('text_new_products_heading');
		$data['text_ae_perk_shipping'] = $this->language->get('text_ae_perk_shipping');
		$data['text_ae_perk_returns'] = $this->language->get('text_ae_perk_returns');
		$data['text_ae_perk_protection'] = $this->language->get('text_ae_perk_protection');

		// Used only by the "stockland" and "persiantvbox" themes' category banner rows.
		$this->load->model('catalog/category');

		$categories = [];

		foreach (array_slice($this->model_catalog_category->getCategories(0), 0, 8) as $category) {
			$categories[] = [
				'name' => $category['name'],
				'href' => $this->url->link('product/category', 'path=' . $category['category_id']),
			];
		}

		$data['home_categories'] = $categories;

		// Used only by the "persiantvbox" theme's poster row (Design > Theme).
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$products = [];

		$results = $this->model_catalog_product->getProducts([
			'sort'  => 'p.date_added',
			'order' => 'DESC',
			'start' => 0,
			'limit' => 8,
		]);

		foreach ($results as $result) {
			if ($result['image']) {
				$thumb = $this->model_tool_image->resize(html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'), 200, 280);
			} else {
				$thumb = $this->model_tool_image->resize('placeholder.png', 200, 280);
			}

			$price_old = false;
			$discount = 0;

			if ((float)$result['special']) {
				$price = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				$price_old = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				$discount = (int)round((($result['price'] - $result['special']) / $result['price']) * 100);
			} elseif ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$price = false;
			}

			$products[] = [
				'name'      => $result['name'],
				'thumb'     => $thumb,
				'price'     => $price,
				'price_old' => $price_old,
				'discount'  => $discount,
				'rating'    => $result['rating'] ?? 0,
				'href'      => $this->url->link('product/product', 'product_id=' . $result['product_id']),
			];
		}

		$data['home_products'] = $products;

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/home', $data));
	}
}
