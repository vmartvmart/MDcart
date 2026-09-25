<?php
namespace MDcart\Catalog\Controller\Extension\IranianGateways\Payment;
/**
 * Class Digipay
 *
 * DigiPay UPG (Unified Payment Gateway) checkout flow. A single ticket
 * (type=11) is requested with no preferredGateway restriction, so DigiPay's
 * own hosted page shows the customer whichever payment instruments (bank
 * card / IPG, DigiPay wallet, buy-now-pay-later / installment credit) the
 * merchant account has enabled - this store does not need to build its own
 * instrument picker. See extension/iranian_gateways/system/library/digipay.php
 * for the underlying API client and its scope notes (no refund/reverse).
 *
 * @package MDcart\Catalog\Controller\Extension\IranianGateways\Payment
 */
class Digipay extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/iranian_gateways/payment/digipay');

		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/iranian_gateways/payment/digipay', $data);
	}

	/**
	 * Confirm
	 *
	 * Called by the customer to start the DigiPay payment - logs in, opens a
	 * purchase ticket, and returns the URL to redirect the browser to.
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->load->language('extension/iranian_gateways/payment/digipay');

		$json = [];

		$order_info = $this->validateOrder($json);

		if (!$json && $order_info) {
			$amount = $this->getRialAmount($order_info, $json);

			$cell_number = '';

			// DigiPay - unlike every other gateway in this store - requires a
			// valid Iranian mobile number to open a ticket. If the customer's
			// account has none on file (or it's not a real mobile number),
			// DigiPay's API just rejects the ticket with a generic error that
			// gives the customer no idea why - catch it here instead with a
			// specific, actionable message.
			if (!$json) {
				$cell_number = $this->normalizeCellNumber((string)$order_info['telephone']);

				if (!preg_match('/^09\d{9}$/', $cell_number)) {
					$json['error'] = $this->language->get('error_telephone');
				}
			}

			if (!$json) {
				$digipay = $this->getClient();

				$token = $digipay->login();

				if ($token === null) {
					error_log('MDcart digipay.confirm: login failed - ' . $digipay->error);

					$json['error'] = $this->language->get('error_gateway');

					if ($this->config->get('payment_digipay_sandbox')) {
						$json['error'] .= ' [digipay_debug: login:' . substr((string)$digipay->error, 0, 200) . ']';
					}
				} else {
					// A fresh id per attempt (rather than reusing the order
					// id) so a customer who retries after a failed/abandoned
					// attempt gets a new providerId instead of possibly
					// colliding with an old one DigiPay still has on file.
					$provider_id = $order_info['order_id'] . '-' . substr(uniqid(), -8);

					$callback_url = str_replace('&amp;', '&', $this->url->link('extension/iranian_gateways/payment/digipay.callback', '', true));

					$result = $digipay->createTicket($token, $cell_number, $amount, $provider_id, $callback_url);

					if (!empty($result['error']) || empty($result['redirect_url'])) {
						error_log('MDcart digipay.confirm: createTicket failed - ' . ($result['error'] ?? 'no redirect_url returned'));

						$json['error'] = $this->language->get('error_gateway');

						if ($this->config->get('payment_digipay_sandbox')) {
							$json['error'] .= ' [digipay_debug: ticket:' . substr((string)($result['error'] ?? ''), 0, 200) . ']';
						}
					} else {
						$this->session->data['digipay_provider_id'] = $provider_id;

						$json['redirect'] = $result['redirect_url'];
					}
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Callback
	 *
	 * DigiPay POSTs the payment result directly to this URL (see "نتیجه
	 * پرداخت" in DigiPay's documentation) once the customer finishes on
	 * DigiPay's page. A successful result must still be verified server-side
	 * before the order is marked as paid.
	 *
	 * @return void
	 */
	public function callback(): void {
		$this->load->language('extension/iranian_gateways/payment/digipay');

		$sandbox = (bool)$this->config->get('payment_digipay_sandbox');

		$failure_url = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

		$provider_id = $this->requestParam('providerId');
		$tracking_code = $this->requestParam('trackingCode');
		$result_status = $this->requestParam('result');
		$type = (int)($this->requestParam('type') ?? -1);

		if (!$provider_id || !$tracking_code || $type < 0) {
			$this->redirectFailure($failure_url, $sandbox, 'missing_params');

			return;
		}

		$json = [];

		$order_info = $this->validateOrder($json);

		if ($json || !$order_info) {
			$this->redirectFailure($failure_url, $sandbox, 'order_invalid:' . ($json['error'] ?? ''));

			return;
		}

		if (empty($this->session->data['digipay_provider_id']) || (string)$this->session->data['digipay_provider_id'] !== (string)$provider_id) {
			$this->redirectFailure($failure_url, $sandbox, 'provider_id_mismatch:session=' . ($this->session->data['digipay_provider_id'] ?? 'unset') . ';got=' . $provider_id);

			return;
		}

		if ($result_status !== 'SUCCESS') {
			$this->redirectFailure($failure_url, $sandbox, 'result_not_success:' . $result_status);

			return;
		}

		$digipay = $this->getClient();

		$token = $digipay->login();

		if ($token === null) {
			$this->redirectFailure($failure_url, $sandbox, 'verify_login_failed:' . $digipay->error);

			return;
		}

		$verify = $digipay->verify($token, $tracking_code, $provider_id, $type);

		if (empty($verify['success'])) {
			$this->redirectFailure($failure_url, $sandbox, 'verify_failed:' . $digipay->error);

			return;
		}

		$amount = $this->getRialAmount($order_info, $json);

		if ($json || (int)$verify['amount'] !== $amount) {
			$this->redirectFailure($failure_url, $sandbox, 'amount_mismatch:expected=' . $amount . ';got=' . ($verify['amount'] ?? 'null'));

			return;
		}

		unset($this->session->data['digipay_provider_id']);

		// Store the tracking info now, while we have it, so the deliver
		// step (extension/iranian_gateways's order-status event, for
		// CREDIT/BNPL purchases only) can find it later without needing
		// anything from this now-gone checkout session.
		$this->db->query(
			"INSERT INTO `" . DB_PREFIX . "digipay_purchase` SET"
			. " `order_id` = '" . (int)$order_info['order_id'] . "',"
			. " `provider_id` = '" . $this->db->escape($provider_id) . "',"
			. " `tracking_code` = '" . $this->db->escape($tracking_code) . "',"
			. " `type` = '" . (int)$type . "',"
			. " `delivered` = '0',"
			. " `date_added` = NOW()"
			. " ON DUPLICATE KEY UPDATE `provider_id` = '" . $this->db->escape($provider_id) . "', `tracking_code` = '" . $this->db->escape($tracking_code) . "', `type` = '" . (int)$type . "', `delivered` = '0', `deliver_attempts` = '0', `deliver_error` = NULL, `date_added` = NOW()"
		);

		$comment = sprintf($this->language->get('text_track_id'), $tracking_code);

		$this->load->model('checkout/order');

		$this->model_checkout_order->addHistory($this->session->data['order_id'], (int)$this->config->get('payment_digipay_order_status_id'), $comment, true);

		$this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true));
	}

	/**
	 * Redirect to the failure page. The reason is always written to the PHP
	 * error log (so a failure can be diagnosed later even outside sandbox
	 * mode - the callback route has no other way to surface anything to an
	 * admin, since the customer's browser is the only thing "watching" this
	 * request). In sandbox mode only, the same short debug reason is also
	 * appended as a query string, matching the pattern already used by
	 * BitPay's diagnostics in this store.
	 *
	 * @param string $failure_url
	 * @param bool   $sandbox
	 * @param string $reason
	 *
	 * @return void
	 */
	private function redirectFailure(string $failure_url, bool $sandbox, string $reason): void {
		error_log('MDcart digipay.callback: ' . $reason);

		if ($sandbox) {
			$failure_url .= (str_contains($failure_url, '?') ? '&' : '?') . 'digipay_debug=' . rawurlencode($reason);
		}

		$this->response->redirect($failure_url);
	}

	/**
	 * @return \MDcart\System\Library\Extension\IranianGateways\Digipay
	 */
	private function getClient(): \MDcart\System\Library\Extension\IranianGateways\Digipay {
		// Deliberately NOT using $this->load->library(...) here - the
		// framework's Loader::library()/Factory::library() always
		// instantiates the class with the args it was given (none, if any
		// weren't explicitly passed through), and Digipay's constructor
		// below requires 4 mandatory arguments - calling load->library()
		// first (as this used to) fatals immediately with "Too few
		// arguments... at least 4 expected", before this method ever
		// reaches its own, correctly-parameterized `new` below. Referencing
		// the class by its fully-qualified name is enough to trigger PHP's
		// own autoloader - no separate load->library() call is needed.
		return new \MDcart\System\Library\Extension\IranianGateways\Digipay(
			(string)$this->config->get('payment_digipay_client_id'),
			(string)$this->config->get('payment_digipay_client_secret'),
			(string)$this->config->get('payment_digipay_username'),
			(string)$this->config->get('payment_digipay_password'),
			(bool)$this->config->get('payment_digipay_sandbox')
		);
	}

	/**
	 * DigiPay expects an Iranian mobile number starting with 0
	 * (e.g. 09123456789). Best-effort normalization from whatever format
	 * the customer entered at registration (may already start with 0, with
	 * +98, or with 98) - not a full validator, matching the light-touch
	 * level of phone handling already used elsewhere in this store.
	 *
	 * @param string $telephone
	 *
	 * @return string
	 */
	private function normalizeCellNumber(string $telephone): string {
		// Convert Persian/Arabic-Indic digits (common on Iranian keyboards)
		// to ASCII first - \D would otherwise strip them out entirely,
		// silently turning a real-looking number into an empty/garbled one.
		// See oc_latin_digits()'s docblock for how this was found.
		$digits = preg_replace('/\D/', '', oc_latin_digits($telephone)) ?? '';

		if (str_starts_with($digits, '0098')) {
			$digits = substr($digits, 4);
		} elseif (str_starts_with($digits, '98') && strlen($digits) > 10) {
			$digits = substr($digits, 2);
		}

		if ($digits !== '' && !str_starts_with($digits, '0')) {
			$digits = '0' . $digits;
		}

		return $digits;
	}

	/**
	 * Read a field from POST first, then GET - DigiPay's own examples POST
	 * the result, but this is defensive in case of a GET redirect instead.
	 *
	 * @param string $key
	 *
	 * @return string|null
	 */
	private function requestParam(string $key): ?string {
		if (isset($this->request->post[$key]) && $this->request->post[$key] !== '') {
			return (string)$this->request->post[$key];
		}

		if (isset($this->request->get[$key]) && $this->request->get[$key] !== '') {
			return (string)$this->request->get[$key];
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $json
	 *
	 * @return array<string, mixed>
	 */
	private function validateOrder(array &$json): array {
		if (!isset($this->session->data['order_id'])) {
			$json['error'] = $this->language->get('error_order');

			return [];
		}

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		if (!$order_info) {
			unset($this->session->data['order_id']);

			$json['error'] = $this->language->get('error_order');

			return [];
		}

		if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'digipay.digipay') {
			$json['error'] = $this->language->get('error_payment_method');

			return [];
		}

		return $order_info;
	}

	/**
	 * Orders in this store are always placed in IRR - see
	 * catalog/controller/checkout/confirm.php. DigiPay's documented examples
	 * use amounts on the same scale as this store's Rial totals, so no unit
	 * conversion is applied here (matching BitPay's own handling in this
	 * codebase) - if DigiPay's account for this merchant actually expects
	 * Toman, this is the one place to change.
	 *
	 * @param array<string, mixed> $order_info
	 * @param array<string, mixed> $json
	 *
	 * @return int
	 */
	private function getRialAmount(array $order_info, array &$json): int {
		if ($order_info['currency_code'] !== 'IRR') {
			$json['error'] = $this->language->get('error_currency');

			return 0;
		}

		return (int)round($order_info['total'] * $order_info['currency_value']);
	}
}
