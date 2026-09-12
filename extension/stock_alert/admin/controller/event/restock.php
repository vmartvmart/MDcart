<?php
namespace MDcart\Admin\Controller\Extension\StockAlert\Event;
/**
 * Class Restock
 *
 * model/catalog/product.editProduct/after
 *
 * Fires every time a product is saved in the admin. If it now has stock and
 * there are unnotified subscribers for it, notifies them and marks them done.
 * We deliberately don't compare against the "old" quantity: whatever was
 * subscribed and unnotified simply gets sent once the product has stock,
 * which is simpler and self-healing if a notification attempt ever fails.
 *
 * @package MDcart\Admin\Controller\Extension\StockAlert\Event
 */
class Restock extends \MDcart\System\Engine\Controller {
	/**
	 * @param string            $route
	 * @param array<int, mixed> $args
	 *
	 * @return void
	 */
	public function index(string &$route, array &$args): void {
		if (!$this->config->get('other_stock_alert_status')) {
			return;
		}

		$product_id = (int)($args[0] ?? 0);
		$data = $args[1] ?? [];
		$quantity = (int)($data['quantity'] ?? 0);

		if (!$product_id || $quantity <= 0) {
			return;
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "stock_alert` WHERE `product_id` = '" . $product_id . "' AND `notified` = '0' AND ((`channel` IN ('sms', 'whatsapp') AND `phone` != '') OR (`channel` IN ('telegram', 'bale') AND `status` = '1' AND `chat_id` != ''))");

		if (!$query->rows) {
			return;
		}

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if (!$product_info) {
			return;
		}

		$this->load->language('extension/stock_alert/event/restock');

		$product_name = $product_info['name'];
		$message = sprintf($this->language->get('text_message'), $product_name);

		foreach ($query->rows as $subscriber) {
			$sent = false;

			switch ($subscriber['channel']) {
				case 'sms':
					if ($this->config->get('other_ippanel_status') && $this->config->get('other_ippanel_api_key') && class_exists('\MDcart\System\Library\Extension\Ippanel\Ippanel')) {
						$client = new \MDcart\System\Library\Extension\Ippanel\Ippanel((string)$this->config->get('other_ippanel_api_key'), (string)$this->config->get('other_ippanel_sender'));
						$sent = $client->send($subscriber['phone'], $message);
					}

					break;

				case 'whatsapp':
					if ($this->config->get('other_whatsapp_status') && $this->config->get('other_whatsapp_template_order_add') && class_exists('\MDcart\System\Library\Extension\Whatsapp\Whatsapp')) {
						$client = new \MDcart\System\Library\Extension\Whatsapp\Whatsapp((string)$this->config->get('other_whatsapp_phone_number_id'), (string)$this->config->get('other_whatsapp_access_token'));
						$sent = $client->sendTemplate($subscriber['phone'], (string)$this->config->get('other_whatsapp_template_order_add'), (string)($this->config->get('other_whatsapp_language_code') ?: 'en_US'), [$product_name, $this->language->get('text_available')]);
					}

					break;

				case 'telegram':
					if ($this->config->get('other_telegram_status') && $this->config->get('other_telegram_bot_token') && class_exists('\MDcart\System\Library\Extension\Telegram\Telegram')) {
						$client = new \MDcart\System\Library\Extension\Telegram\Telegram((string)$this->config->get('other_telegram_bot_token'));
						$sent = $client->sendMessage($subscriber['chat_id'], $message);
					}

					break;

				case 'bale':
					if ($this->config->get('other_bale_status') && $this->config->get('other_bale_bot_token') && class_exists('\MDcart\System\Library\Extension\Bale\Bale')) {
						$client = new \MDcart\System\Library\Extension\Bale\Bale((string)$this->config->get('other_bale_bot_token'));
						$sent = $client->sendMessage($subscriber['chat_id'], $message);
					}

					break;
			}

			if ($sent) {
				$this->db->query("UPDATE `" . DB_PREFIX . "stock_alert` SET `notified` = '1' WHERE `stock_alert_id` = '" . (int)$subscriber['stock_alert_id'] . "'");
			}
		}
	}
}
