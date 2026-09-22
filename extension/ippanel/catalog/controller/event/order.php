<?php
namespace MDcart\Catalog\Controller\Extension\Ippanel\Event;
/**
 * Class Order
 *
 * Sends IPPanel SMS notifications for new orders and order status changes.
 *
 * model/checkout/order.addHistory/before
 *
 * @package MDcart\Catalog\Controller\Extension\Ippanel\Event
 */
class Order extends \MDcart\System\Engine\Controller {
	/**
	 * @param string            $route
	 * @param array<int, mixed> $args
	 *
	 * @return void
	 */
	public function index(string &$route, array &$args): void {
		if (!$this->config->get('other_ippanel_status') || !$this->config->get('other_ippanel_api_key') || !$this->config->get('other_ippanel_sender')) {
			return;
		}

		$order_id = $args[0] ?? 0;
		$order_status_id = $args[1] ?? 0;
		$notify = $args[3] ?? false;

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder((int)$order_id);

		if (!$order_info || !$order_status_id) {
			return;
		}

		$this->load->language('extension/ippanel/event/order');

		$this->load->library('extension/ippanel/ippanel');

		$ippanel = new \MDcart\System\Library\Extension\Ippanel\Ippanel(
			(string)$this->config->get('other_ippanel_api_key'),
			(string)$this->config->get('other_ippanel_sender')
		);

		// New order (status goes from 0 to something)
		if (!$order_info['order_status_id'] && $order_status_id) {
			if ($this->config->get('other_ippanel_order_add_status') && !empty($order_info['telephone'])) {
				$message = sprintf($this->language->get('text_sms_order_add'), $order_info['order_id'], $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value']));

				$ippanel->send($order_info['telephone'], $message);
			}

			if ($this->config->get('other_ippanel_admin_status')) {
				$message = sprintf($this->language->get('text_sms_order_add_admin'), $order_info['order_id'], $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value']));

				foreach ($this->getAdminTelephones() as $telephone) {
					$ippanel->send($telephone, $message);
				}
			}

			return;
		}

		// Existing order, status changed and the merchant asked to notify the customer
		if ($order_info['order_status_id'] && $order_status_id && $notify) {
			if ($this->config->get('other_ippanel_order_status_status') && !empty($order_info['telephone'])) {
				$this->load->model('localisation/order_status');

				$order_status_info = $this->model_localisation_order_status->getOrderStatus((int)$order_status_id);

				$message = sprintf($this->language->get('text_sms_order_status'), $order_info['order_id'], $order_status_info['name'] ?? '');

				$ippanel->send($order_info['telephone'], $message);
			}
		}
	}

	/**
	 * Get Admin Telephones
	 *
	 * The single global other_ippanel_admin_telephone (if still set) plus
	 * the mobile number of every staff member in the roles selected under
	 * Settings > General ("who gets notified about new orders"), as set on
	 * their own Profile page.
	 *
	 * @return array<int, string>
	 */
	private function getAdminTelephones(): array {
		$telephones = [];

		if ($this->config->get('other_ippanel_admin_telephone')) {
			$telephones[] = (string)$this->config->get('other_ippanel_admin_telephone');
		}

		$this->load->model('user/user');

		foreach ($this->model_user_user->getNotifyUsers((array)$this->config->get('config_notify_admin_group_ids')) as $notify_user) {
			if ($notify_user['notify_mobile']) {
				$telephones[] = (string)$notify_user['notify_mobile'];
			}
		}

		return array_unique($telephones);
	}
}
