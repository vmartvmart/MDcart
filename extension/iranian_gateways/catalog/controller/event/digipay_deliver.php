<?php
namespace MDcart\Catalog\Controller\Extension\IranianGateways\Event;
/**
 * Class DigipayDeliver
 *
 * DigiPay requires the merchant to separately confirm delivery for
 * purchases paid with its credit or buy-now-pay-later (BNPL) options
 * before that purchase is finalized/settled (see "تحویل خرید" in DigiPay's
 * documentation) - IPG and Wallet purchases need no such step. This event
 * fires it automatically the moment an order paid that way is moved to the
 * admin-configured "delivered" order status.
 *
 * Registered on the same event this store already uses for order
 * notifications (telegram_order, bale_order, etc.), which fires for BOTH
 * storefront and admin-panel order-status changes - the admin "add
 * history" action internally routes through this same catalog checkout
 * order model (see panel/controller/sale/order.php's call() method, which
 * spins up a catalog-context store instance to call api/order), so no
 * separate admin-side event is needed.
 *
 * model/checkout/order.addHistory/before
 *
 * IMPORTANT: this handler must never call
 * $this->model_checkout_order->addHistory() itself (directly or
 * indirectly) - that is the exact call this event fires around, so doing
 * so would recurse. Any record of what happened here is written with a
 * plain $this->db->query() instead.
 *
 * @package MDcart\Catalog\Controller\Extension\IranianGateways\Event
 */
class DigipayDeliver extends \MDcart\System\Engine\Controller {
	// Ticket/payment-result "type" codes (see DigiPay's documentation) -
	// deliver() is only required for these two.
	private const TYPE_CREDIT = 5;
	private const TYPE_BNPL = 13;

	/**
	 * @param string            $route
	 * @param array<int, mixed> $args
	 *
	 * @return void
	 */
	public function index(string &$route, array &$args): void {
		$deliver_status_id = (int)$this->config->get('payment_digipay_deliver_order_status_id');

		if (!$deliver_status_id) {
			return;
		}

		$new_status_id = (int)($args[1] ?? 0);

		if ($new_status_id !== $deliver_status_id) {
			return;
		}

		$order_id = (int)($args[0] ?? 0);

		if (!$order_id) {
			return;
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "digipay_purchase` WHERE `order_id` = '" . $order_id . "'");

		if (!$query->num_rows || $query->row['delivered']) {
			// No DigiPay purchase on record for this order, or already
			// reported delivered (idempotent - e.g. the order is moved
			// through this status again later).
			return;
		}

		$type = (int)$query->row['type'];

		if ($type !== self::TYPE_CREDIT && $type !== self::TYPE_BNPL) {
			return;
		}

		if (!$this->config->get('payment_digipay_client_id') || !$this->config->get('payment_digipay_client_secret') || !$this->config->get('payment_digipay_username') || !$this->config->get('payment_digipay_password')) {
			return;
		}

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($order_id);

		if (!$order_info) {
			return;
		}

		$order_products = $this->model_checkout_order->getProducts($order_id);

		$products = [];

		foreach ($order_products as $order_product) {
			$products[] = (string)$order_product['name'];
		}

		$this->load->library('extension/iranian_gateways/digipay');

		$digipay = new \MDcart\System\Library\Extension\IranianGateways\Digipay(
			(string)$this->config->get('payment_digipay_client_id'),
			(string)$this->config->get('payment_digipay_client_secret'),
			(string)$this->config->get('payment_digipay_username'),
			(string)$this->config->get('payment_digipay_password'),
			(bool)$this->config->get('payment_digipay_sandbox')
		);

		$token = $digipay->login();

		if ($token === null) {
			$this->recordAttempt($order_id, false, 'login: ' . $digipay->error);

			return;
		}

		$success = $digipay->deliver($token, (string)$query->row['tracking_code'], $type, (string)$order_id, $products, (int)round(microtime(true) * 1000));

		$this->recordAttempt($order_id, $success, $success ? null : $digipay->error);
	}

	/**
	 * @param int         $order_id
	 * @param bool        $success
	 * @param string|null $error
	 *
	 * @return void
	 */
	private function recordAttempt(int $order_id, bool $success, ?string $error): void {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "digipay_purchase` SET"
			. " `delivered` = '" . ($success ? '1' : '0') . "',"
			. " `deliver_attempts` = `deliver_attempts` + 1,"
			. " `deliver_error` = '" . $this->db->escape((string)$error) . "'"
			. " WHERE `order_id` = '" . $order_id . "'"
		);
	}
}
