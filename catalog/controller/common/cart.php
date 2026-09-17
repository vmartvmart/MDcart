<?php
namespace MDcart\Catalog\Controller\Common;
/**
 * Class Cart
 *
 * Can be called from $this->load->controller('common/cart');
 *
 * @package MDcart\Catalog\Controller\Common
 */
class Cart extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('common/cart');

		$totals = [];
		$taxes = $this->cart->getTaxes();
		$total = 0;

		// Cart
		$this->load->model('checkout/cart');

		// Image
		$this->load->model('tool/image');

		// Upload
		$this->load->model('tool/upload');

		// Display prices
		if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
			($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

			$price_status = true;
		} else {
			$price_status = false;
		}

		$data['text_items'] = sprintf($this->language->get('text_items'), $this->cart->countProducts(), $this->currency->format($total, $this->session->data['currency']));

		// Carried separately (not parsed back out of text_items, which is a
		// full localized sentence) so the header's cart icon badge - see
		// header.twig and common.js's ocSyncCartBadge() - has a plain
		// number to read. This partial is what #cart (both the header's
		// black mini-cart bar and, via ocReloadPreservingDropdown(), every
		// other page's post-change refresh) always reloads into, so the
		// badge stays in sync with zero extra requests.
		$data['cart_total'] = $this->cart->countProducts();

		// Products
		$data['products'] = [];

		$products = $this->model_checkout_cart->getProducts();

		foreach ($products as $product) {
			if ($product['option']) {
				foreach ($product['option'] as $key => $option) {
					if ($option['type'] != 'file') {
						$value = $option['value'];
					} else {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					}

					$product['option'][$key]['value'] = (oc_strlen($value) > 20 ? oc_substr($value, 0, 20) . '..' : $value);
				}
			}

			$subscription = '';

			if ($product['subscription'] && $price_status) {
				if ($product['subscription']['duration']) {
					$subscription .= sprintf($this->language->get('text_subscription_duration'), $product['subscription']['price_text'], $product['subscription']['cycle'], $product['subscription']['frequency'], $product['subscription']['duration']);
				} else {
					$subscription .= sprintf($this->language->get('text_subscription_cancel'), $product['subscription']['price_text'], $product['subscription']['cycle'], $product['subscription']['frequency']);
				}
			}

			// Delivery-timing label for split-off pre-order/transit lines (see
			// checkout/cart.php's add(), which can split a single request
			// into a normal line plus a separate delayed-delivery line for
			// whatever exceeded current stock).
			if (!empty($product['is_transit_order']) && !empty($product['transit_delivery_date'])) {
				$delivery_date = new \DateTime($product['transit_delivery_date']);

				$delivery_label = sprintf($this->language->get('text_transit_line'), $delivery_date->format($this->language->get('date_format_short')));
			} elseif (!empty($product['is_preorder']) && !empty($product['preorder_delivery_date'])) {
				$delivery_date = new \DateTime($product['preorder_delivery_date']);

				$delivery_label = sprintf($this->language->get('text_preorder_line'), $delivery_date->format($this->language->get('date_format_short')));
			} else {
				$delivery_label = '';
			}

			$data['products'][] = [
				'thumb'          => $this->model_tool_image->resize($product['image'], $this->config->get('config_image_cart_width'), $this->config->get('config_image_cart_height')),
				'subscription'   => $subscription,
				'delivery_label' => $delivery_label,
				'price'          => $price_status ? $product['price_text'] : '',
				'total'          => $price_status ? $product['total_text'] : '',
				'href'           => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product['product_id'])
			] + $product;
		}

		// Totals
		$data['totals'] = [];

		foreach ($totals as $total) {
			$data['totals'][] = ['text' => $this->currency->format($total['value'], $this->session->data['currency'])] + $total;
		}

		$data['list'] = $this->url->link('common/cart.info', 'language=' . $this->config->get('config_language'));
		$data['remove'] = $this->url->link('common/cart.remove', 'language=' . $this->config->get('config_language'));
		$data['edit'] = $this->url->link('checkout/cart.edit', 'language=' . $this->config->get('config_language'));

		$data['cart'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'));
		$data['checkout'] = $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'));

		return $this->load->view('common/cart', $data);
	}

	/**
	 * Info
	 *
	 * @return void
	 */
	public function info(): void {
		$this->response->setOutput($this->index());
	}

	/**
	 * Remove Product
	 *
	 * @return void
	 */
	public function remove(): void {
		$this->load->language('checkout/cart');

		$json = [];

		if (isset($this->request->post['key'])) {
			$key = (int)$this->request->post['key'];
		} else {
			$key = 0;
		}

		if (!$this->cart->has($key)) {
			$json['error'] = $this->language->get('error_product');
		}

		if (!$json) {
			$this->cart->remove($key);

			$json['success'] = $this->language->get('text_remove');

			unset($this->session->data['order_id']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['reward']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
