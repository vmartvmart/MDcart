<?php
namespace Opencart\Catalog\Controller\Extension\StockAlert\Product;
/**
 * Class StockAlert
 *
 * Powers the "Notify me when available" widget shown on out-of-stock product pages.
 *
 * @package Opencart\Catalog\Controller\Extension\StockAlert\Product
 */
class StockAlert extends \Opencart\System\Engine\Controller {
	/**
	 * Returns the widget's data so it can be included from the product controller/template.
	 *
	 * @param int $product_id
	 *
	 * @return array<string, mixed>
	 */
	public function getWidgetData(int $product_id): array {
		$data = [
			'status'    => (bool)$this->config->get('other_stock_alert_status'),
			'phone'     => (bool)($this->config->get('other_ippanel_status') || $this->config->get('other_whatsapp_status')),
			'telegram'  => '',
			'bale'      => ''
		];

		if ($this->config->get('other_telegram_status') && $this->config->get('other_telegram_bot_username')) {
			$data['telegram'] = $this->createConnectLink('telegram', $product_id, 'other_telegram_bot_username', 'https://t.me/');
		}

		if ($this->config->get('other_bale_status') && $this->config->get('other_bale_bot_username')) {
			$data['bale'] = $this->createConnectLink('bale', $product_id, 'other_bale_bot_username', 'https://ble.ir/');
		}

		return $data;
	}

	/**
	 * AJAX: subscribe a phone number for SMS and/or WhatsApp alerts (whichever channel is enabled).
	 *
	 * @return void
	 */
	public function subscribe(): void {
		$this->load->language('extension/stock_alert/product/stock_alert');

		$json = [];

		$product_id = (int)($this->request->post['product_id'] ?? 0);
		$phone = trim((string)($this->request->post['phone'] ?? ''));

		if (!$product_id || !$phone || strlen(preg_replace('/[^0-9]/', '', $phone)) < 10) {
			$json['error'] = $this->language->get('error_phone');
		}

		if (!$json && !$this->config->get('other_stock_alert_status')) {
			$json['error'] = $this->language->get('error_status');
		}

		if (!$json) {
			$customer_id = (int)$this->customer->getId();
			$added = false;

			if ($this->config->get('other_ippanel_status')) {
				$this->addSubscription($product_id, $customer_id, 'sms', $phone);
				$added = true;
			}

			if ($this->config->get('other_whatsapp_status')) {
				$this->addSubscription($product_id, $customer_id, 'whatsapp', $phone);
				$added = true;
			}

			if ($added) {
				$json['success'] = $this->language->get('text_success');
			} else {
				$json['error'] = $this->language->get('error_status');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @param int    $product_id
	 * @param int    $customer_id
	 * @param string $channel
	 * @param string $phone
	 *
	 * @return void
	 */
	private function addSubscription(int $product_id, int $customer_id, string $channel, string $phone): void {
		$exists = $this->db->query("SELECT `stock_alert_id` FROM `" . DB_PREFIX . "stock_alert` WHERE `product_id` = '" . $product_id . "' AND `channel` = '" . $this->db->escape($channel) . "' AND `phone` = '" . $this->db->escape($phone) . "' AND `notified` = '0'");

		if ($exists->row) {
			return;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "stock_alert` SET `product_id` = '" . $product_id . "', `customer_id` = '" . $customer_id . "', `channel` = '" . $this->db->escape($channel) . "', `phone` = '" . $this->db->escape($phone) . "', `status` = '1', `date_added` = NOW()");
	}

	/**
	 * @param string $channel
	 * @param int    $product_id
	 * @param string $username_key
	 * @param string $link_domain
	 *
	 * @return string
	 */
	private function createConnectLink(string $channel, int $product_id, string $username_key, string $link_domain): string {
		$query = $this->db->query("SELECT `token` FROM `" . DB_PREFIX . "stock_alert` WHERE `product_id` = '" . $product_id . "' AND `channel` = '" . $this->db->escape($channel) . "' AND `status` = '0' AND `notified` = '0' AND `customer_id` = '" . (int)$this->customer->getId() . "' ORDER BY `stock_alert_id` DESC LIMIT 1");

		if ($query->row) {
			$token = $query->row['token'];
		} else {
			$token = bin2hex(random_bytes(16));

			$this->db->query("INSERT INTO `" . DB_PREFIX . "stock_alert` SET `product_id` = '" . $product_id . "', `customer_id` = '" . (int)$this->customer->getId() . "', `channel` = '" . $this->db->escape($channel) . "', `token` = '" . $this->db->escape($token) . "', `status` = '0', `date_added` = NOW()");
		}

		return $link_domain . $this->config->get($username_key) . '?start=stock_' . $token;
	}
}
