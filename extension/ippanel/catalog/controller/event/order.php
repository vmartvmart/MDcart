<?php
namespace Opencart\Catalog\Controller\Extension\Ippanel\Event;
/**
 * Class Order
 *
 * Sends IPPanel SMS notifications for new orders and order status changes.
 *
 * model/checkout/order.addHistory/before
 *
 * @package Opencart\Catalog\Controller\Extension\Ippanel\Event
 */
class Order extends \Opencart\System\Engine\Controller {
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

		$ippanel = new \Opencart\System\Library\Extension\Ippanel\Ippanel(
			(string)$this->config->get('other_ippanel_api_key'),
			(string)$this->config->get('other_ippanel_sender')
		);

		// New order (status goes from 0 to something)
		if (!$order_info['order_status_id'] && $order_status_id) {
			if ($this->config->get('other_ippanel_order_add_status') && !empty($order_info['telephone'])) {
				$message = sprintf($this->language->get('text_sms_order_add'), $order_info['order_id'], $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value']));

				$ippanel->send($order_info['telephone'], $message);
			}

			if ($this->config->get('other_ippanel_admin_status') && $this->config->get('other_ippanel_admin_telephone')) {
				$message = sprintf($this->language->get('text_sms_order_add_admin'), $order_info['order_id'], $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value']));

				$ippanel->send((string)$this->config->get('other_ippanel_admin_telephone'), $message);
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
}
