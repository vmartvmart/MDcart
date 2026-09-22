<?php
namespace MDcart\Catalog\Controller\Extension\Bale\Event;
/**
 * Class Order
 *
 * Sends Bale notifications for new orders and order status changes.
 *
 * model/checkout/order.addHistory/before
 *
 * @package MDcart\Catalog\Controller\Extension\Bale\Event
 */
class Order extends \MDcart\System\Engine\Controller {
	/**
	 * @param string            $route
	 * @param array<int, mixed> $args
	 *
	 * @return void
	 */
	public function index(string &$route, array &$args): void {
		if (!$this->config->get('other_bale_status') || !$this->config->get('other_bale_bot_token')) {
			return;
		}

		$order_id = (int)($args[0] ?? 0);
		$order_status_id = $args[1] ?? 0;
		$notify = $args[3] ?? false;

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($order_id);

		if (!$order_info || !$order_status_id) {
			return;
		}

		$this->load->language('extension/bale/event/order');
		$this->load->library('extension/bale/bale');

		$bale = new \MDcart\System\Library\Extension\Bale\Bale((string)$this->config->get('other_bale_bot_token'));

		// New order
		if (!$order_info['order_status_id'] && $order_status_id) {
			$total = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value']);

			if ($this->config->get('other_bale_order_add_status')) {
				$chat_id = $this->getLinkedChatId($order_id);

				if ($chat_id) {
					$bale->sendMessage($chat_id, sprintf($this->language->get('text_order_add'), $order_info['order_id'], $total));
				}
			}

			if ($this->config->get('other_bale_admin_status')) {
				$message = sprintf($this->language->get('text_order_add_admin'), $order_info['order_id'], $total);

				foreach ($this->getAdminChatIds() as $chat_id) {
					$bale->sendMessage($chat_id, $message);
				}
			}

			return;
		}

		// Status change
		if ($order_info['order_status_id'] && $order_status_id && $notify && $this->config->get('other_bale_order_status_status')) {
			$chat_id = $this->getLinkedChatId($order_id);

			if ($chat_id) {
				$this->load->model('localisation/order_status');

				$order_status_info = $this->model_localisation_order_status->getOrderStatus((int)$order_status_id);

				$bale->sendMessage($chat_id, sprintf($this->language->get('text_order_status'), $order_info['order_id'], $order_status_info['name'] ?? ''));
			}
		}
	}

	/**
	 * @param int $order_id
	 *
	 * @return string
	 */
	private function getLinkedChatId(int $order_id): string {
		$query = $this->db->query("SELECT `chat_id` FROM `" . DB_PREFIX . "notify_link` WHERE `channel` = 'bale' AND `purpose` = 'order' AND `order_id` = '" . (int)$order_id . "' AND `status` = '1' ORDER BY `link_id` DESC LIMIT 1");

		return $query->row ? (string)$query->row['chat_id'] : '';
	}

	/**
	 * Get Admin Chat Ids
	 *
	 * The single global other_bale_admin_chat_id (if still set) plus
	 * every chat ID that staff members in the roles selected under
	 * Settings > General ("who gets notified about new orders") have set
	 * on their own Profile page.
	 *
	 * @return array<int, string>
	 */
	private function getAdminChatIds(): array {
		$chat_ids = [];

		if ($this->config->get('other_bale_admin_chat_id')) {
			$chat_ids[] = (string)$this->config->get('other_bale_admin_chat_id');
		}

		$this->load->model('user/user');

		foreach ($this->model_user_user->getNotifyUsers((array)$this->config->get('config_notify_admin_group_ids')) as $notify_user) {
			if ($notify_user['notify_bale_chat_id']) {
				$chat_ids[] = (string)$notify_user['notify_bale_chat_id'];
			}
		}

		return array_unique($chat_ids);
	}
}
