<?php
namespace Opencart\Catalog\Controller\Extension\Whatsapp\Event;
/**
 * Class Order
 *
 * Sends WhatsApp Cloud API template notifications for new orders and order
 * status changes. No customer opt-in/linking is needed here, unlike the
 * Telegram/Bale bots, because WhatsApp addresses by phone number directly.
 *
 * model/checkout/order.addHistory/before
 *
 * @package Opencart\Catalog\Controller\Extension\Whatsapp\Event
 */
class Order extends \Opencart\System\Engine\Controller {
	/**
	 * @param string            $route
	 * @param array<int, mixed> $args
	 *
	 * @return void
	 */
	public function index(string &$route, array &$args): void {
		if (!$this->config->get('other_whatsapp_status') || !$this->config->get('other_whatsapp_phone_number_id') || !$this->config->get('other_whatsapp_access_token')) {
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

		$this->load->library('extension/whatsapp/whatsapp');

		$whatsapp = new \Opencart\System\Library\Extension\Whatsapp\Whatsapp(
			(string)$this->config->get('other_whatsapp_phone_number_id'),
			(string)$this->config->get('other_whatsapp_access_token')
		);

		$language_code = (string)($this->config->get('other_whatsapp_language_code') ?: 'en_US');

		// New order
		if (!$order_info['order_status_id'] && $order_status_id) {
			$total = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value']);
			$template = (string)$this->config->get('other_whatsapp_template_order_add');

			if ($template && $this->config->get('other_whatsapp_order_add_status') && !empty($order_info['telephone'])) {
				$whatsapp->sendTemplate($order_info['telephone'], $template, $language_code, [$order_info['order_id'], $total]);
			}

			if ($template && $this->config->get('other_whatsapp_admin_status') && $this->config->get('other_whatsapp_admin_telephone')) {
				$whatsapp->sendTemplate((string)$this->config->get('other_whatsapp_admin_telephone'), $template, $language_code, [$order_info['order_id'], $total]);
			}

			return;
		}

		// Status change
		if ($order_info['order_status_id'] && $order_status_id && $notify && $this->config->get('other_whatsapp_order_status_status') && !empty($order_info['telephone'])) {
			$template = (string)$this->config->get('other_whatsapp_template_order_status');

			if ($template) {
				$this->load->model('localisation/order_status');

				$order_status_info = $this->model_localisation_order_status->getOrderStatus((int)$order_status_id);

				$whatsapp->sendTemplate($order_info['telephone'], $template, $language_code, [$order_info['order_id'], $order_status_info['name'] ?? '']);
			}
		}
	}
}
