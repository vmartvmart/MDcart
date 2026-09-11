<?php
namespace Opencart\Catalog\Controller\Extension\Bale\Webhook;
/**
 * Class Bale
 *
 * Public endpoint Bale POSTs Update objects to (registered via setWebhook).
 *
 * @package Opencart\Catalog\Controller\Extension\Bale\Webhook
 */
class Bale extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$body = file_get_contents('php://input');
		$update = json_decode((string)$body, true);

		$this->response->addHeader('Content-Type: application/json');

		if (!is_array($update)) {
			$this->response->setOutput(json_encode(['ok' => true]));

			return;
		}

		$message = $update['message'] ?? null;
		$chat_id = $message['chat']['id'] ?? null;
		$text = trim((string)($message['text'] ?? ''));

		if ($chat_id !== null && str_starts_with($text, '/start ')) {
			$payload = trim(substr($text, 7));

			if (str_starts_with($payload, 'admin_')) {
				$this->linkAdmin(substr($payload, 6), (string)$chat_id);
			} elseif (str_starts_with($payload, 'order_')) {
				$this->linkOrder(substr($payload, 6), (string)$chat_id);
			} elseif (str_starts_with($payload, 'stock_')) {
				$this->linkStockAlert(substr($payload, 6), (string)$chat_id);
			}
		}

		$this->response->setOutput(json_encode(['ok' => true]));
	}

	/**
	 * @param string $token
	 * @param string $chat_id
	 *
	 * @return void
	 */
	private function linkAdmin(string $token, string $chat_id): void {
		if (!$token || $token !== (string)$this->config->get('other_bale_admin_link_token')) {
			return;
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = 'other_bale' AND `key` = 'other_bale_admin_chat_id'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = '0', `code` = 'other_bale', `key` = 'other_bale_admin_chat_id', `value` = '" . $this->db->escape($chat_id) . "', `serialized` = '0'");

		$this->load->library('extension/bale/bale');

		$bale = new \Opencart\System\Library\Extension\Bale\Bale((string)$this->config->get('other_bale_bot_token'));
		$bale->sendMessage($chat_id, 'حساب مدیر با موفقیت به فروشگاه متصل شد. از این پس اعلان سفارش‌های جدید به همین چت ارسال می‌شود.');
	}

	/**
	 * @param string $token
	 * @param string $chat_id
	 *
	 * @return void
	 */
	private function linkOrder(string $token, string $chat_id): void {
		if (!$token) {
			return;
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "notify_link` WHERE `channel` = 'bale' AND `purpose` = 'order' AND `token` = '" . $this->db->escape($token) . "'");

		if (!$query->row) {
			return;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "notify_link` SET `chat_id` = '" . $this->db->escape($chat_id) . "', `status` = '1' WHERE `link_id` = '" . (int)$query->row['link_id'] . "'");

		// Also remember this customer's chat_id at the account level (not just this order),
		// so marketing broadcasts can reach them later without a fresh per-order link.
		if ((int)$query->row['customer_id'] > 0) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "notify_link` WHERE `channel` = 'bale' AND `purpose` = 'customer' AND `order_id` = '" . (int)$query->row['customer_id'] . "'");
			$this->db->query("INSERT INTO `" . DB_PREFIX . "notify_link` SET `channel` = 'bale', `token` = '" . $this->db->escape(bin2hex(random_bytes(16))) . "', `order_id` = '" . (int)$query->row['customer_id'] . "', `customer_id` = '" . (int)$query->row['customer_id'] . "', `purpose` = 'customer', `chat_id` = '" . $this->db->escape($chat_id) . "', `status` = '1', `date_added` = NOW()");
		}

		$this->load->library('extension/bale/bale');

		$bale = new \Opencart\System\Library\Extension\Bale\Bale((string)$this->config->get('other_bale_bot_token'));
		$bale->sendMessage($chat_id, sprintf('اتصال شما با موفقیت انجام شد. بروزرسانی‌های سفارش #%s از این طریق ارسال خواهد شد.', $query->row['order_id']));
	}

	/**
	 * @param string $token
	 * @param string $chat_id
	 *
	 * @return void
	 */
	private function linkStockAlert(string $token, string $chat_id): void {
		if (!$token) {
			return;
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "stock_alert` WHERE `channel` = 'bale' AND `token` = '" . $this->db->escape($token) . "'");

		if (!$query->row) {
			return;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "stock_alert` SET `chat_id` = '" . $this->db->escape($chat_id) . "', `status` = '1' WHERE `stock_alert_id` = '" . (int)$query->row['stock_alert_id'] . "'");

		$this->load->library('extension/bale/bale');

		$bale = new \Opencart\System\Library\Extension\Bale\Bale((string)$this->config->get('other_bale_bot_token'));
		$bale->sendMessage($chat_id, 'اتصال شما با موفقیت انجام شد. به محض موجود شدن این محصول، از این طریق مطلع خواهید شد.');
	}
}
