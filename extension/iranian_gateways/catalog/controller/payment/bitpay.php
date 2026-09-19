<?php
namespace MDcart\Catalog\Controller\Extension\IranianGateways\Payment;
/**
 * Class Bitpay
 *
 * @package MDcart\Catalog\Controller\Extension\IranianGateways\Payment
 */
class Bitpay extends \MDcart\System\Engine\Controller {
	private const API_SEND_URL = 'https://bitpay.ir/payment/gateway-send';
	private const API_VERIFY_URL = 'https://bitpay.ir/payment/gateway-result-second';
	private const GATE_URL_PREFIX = 'https://bitpay.ir/payment/gateway-';
	private const GATE_URL_SUFFIX = '-get';

	// BitPay's own test/sandbox environment - same shape as production, just
	// a different URL prefix ("payment-test" instead of "payment"), per
	// BitPay's official test-environment documentation.
	private const API_SEND_URL_SANDBOX = 'https://bitpay.ir/payment-test/gateway-send';
	private const API_VERIFY_URL_SANDBOX = 'https://bitpay.ir/payment-test/gateway-result-second';
	private const GATE_URL_PREFIX_SANDBOX = 'https://bitpay.ir/payment-test/gateway-';

	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/iranian_gateways/payment/bitpay');

		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/iranian_gateways/payment/bitpay', $data);
	}

	/**
	 * Confirm
	 *
	 * Called by the customer to start the BitPay payment - sends a purchase
	 * request and returns the URL to redirect the browser to.
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->load->language('extension/iranian_gateways/payment/bitpay');

		$json = [];

		$order_info = $this->validateOrder($json);

		if (!$json && $order_info) {
			$amount = $this->getRialAmount($order_info, $json);

			if (!$json) {
				$sandbox = (bool)$this->config->get('payment_bitpay_sandbox');

				$fields = [
					'api'         => $this->config->get('payment_bitpay_api'),
					'amount'      => $amount,
					'redirect'    => str_replace('&amp;', '&', $this->url->link('extension/iranian_gateways/payment/bitpay.callback', '', true)),
					'factorId'    => (string)$order_info['order_id'],
					'name'        => trim($order_info['firstname'] . ' ' . $order_info['lastname']),
					'email'       => $order_info['email'],
					'description' => sprintf($this->language->get('text_order_description'), $order_info['order_id'], $this->config->get('config_name'))
				];

				[$id_get, $error] = $this->requestIdGet($fields, $sandbox);

				if ($error || $id_get === null) {
					$json['error'] = $this->language->get('error_gateway');
				} else {
					$this->session->data['bitpay_id_get'] = $id_get;

					$gate_prefix = $sandbox ? self::GATE_URL_PREFIX_SANDBOX : self::GATE_URL_PREFIX;

					$json['redirect'] = $gate_prefix . $id_get . self::GATE_URL_SUFFIX;
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Callback
	 *
	 * BitPay's own site could not be fetched directly during development, so
	 * this follows the request/response shape documented by well-established
	 * third-party open-source integrations (cross-checked across two
	 * independent implementations) - the customer is expected back here
	 * carrying trans_id and id_get, read from either GET or POST
	 * defensively, matching the pattern used for IDPay in this store.
	 *
	 * @return void
	 */
	public function callback(): void {
		$this->load->language('extension/iranian_gateways/payment/bitpay');

		$sandbox = (bool)$this->config->get('payment_bitpay_sandbox');

		$failure_url = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

		$trans_id = $this->requestParam('trans_id');
		$id_get = $this->requestParam('id_get');

		if (!$trans_id || !$id_get) {
			$this->redirectFailure($failure_url, $sandbox, 'missing_params');

			return;
		}

		$json = [];

		$order_info = $this->validateOrder($json);

		if ($json || !$order_info) {
			$this->redirectFailure($failure_url, $sandbox, 'order_invalid:' . ($json['error'] ?? ''));

			return;
		}

		if (empty($this->session->data['bitpay_id_get']) || (string)$this->session->data['bitpay_id_get'] !== (string)$id_get) {
			$this->redirectFailure($failure_url, $sandbox, 'id_get_mismatch:session=' . ($this->session->data['bitpay_id_get'] ?? 'unset') . ';got=' . $id_get);

			return;
		}

		$fields = [
			'api'      => $this->config->get('payment_bitpay_api'),
			'id_get'   => $id_get,
			'trans_id' => $trans_id,
			'json'     => 1
		];

		[$result, $error] = $this->curlPostForm($sandbox ? self::API_VERIFY_URL_SANDBOX : self::API_VERIFY_URL, $fields);

		$status = $result['status'] ?? null;

		if ($error || ($status != 1 && $status != 11)) {
			$this->redirectFailure($failure_url, $sandbox, 'verify_failed:status=' . var_export($status, true) . ';curl_error=' . ($error ? '1' : '0') . ';raw=' . substr((string)json_encode($result), 0, 200));

			return;
		}

		$amount = $this->getRialAmount($order_info, $json);

		if ($json || (isset($result['amount']) && (int)$result['amount'] !== $amount)) {
			$this->redirectFailure($failure_url, $sandbox, 'amount_mismatch:expected=' . $amount . ';got=' . ($result['amount'] ?? 'null'));

			return;
		}

		unset($this->session->data['bitpay_id_get']);

		$comment = sprintf($this->language->get('text_track_id'), $trans_id);

		$this->load->model('checkout/order');

		$this->model_checkout_order->addHistory($this->session->data['order_id'], (int)$this->config->get('payment_bitpay_order_status_id'), $comment, true);

		$this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true));
	}

	/**
	 * Redirect to the failure page. In sandbox mode only, a short debug
	 * reason is appended as a query string so a developer testing the
	 * gateway can see which check actually failed - never appended in
	 * production, so real customers never see this.
	 *
	 * @param string $failure_url
	 * @param bool   $sandbox
	 * @param string $reason
	 *
	 * @return void
	 */
	private function redirectFailure(string $failure_url, bool $sandbox, string $reason): void {
		if ($sandbox) {
			$failure_url .= (str_contains($failure_url, '?') ? '&' : '?') . 'bitpay_debug=' . rawurlencode($reason);
		}

		$this->response->redirect($failure_url);
	}

	/**
	 * Send the purchase request to BitPay and pull out the id_get value.
	 * BitPay's gateway-send endpoint has been documented (via third-party
	 * libraries) as replying with either a bare positive numeric string
	 * (the id_get itself) or a JSON object containing an "IDGet" field, so
	 * both shapes are handled defensively.
	 *
	 * @param array<string, mixed> $fields
	 * @param bool                 $sandbox
	 *
	 * @return array{0: string|null, 1: bool}
	 */
	private function requestIdGet(array $fields, bool $sandbox = false): array {
		[$response, $error] = $this->curlPostForm($sandbox ? self::API_SEND_URL_SANDBOX : self::API_SEND_URL, $fields, false);

		if ($error || $response === null) {
			return [null, true];
		}

		$response = trim((string)$response);

		if (is_numeric($response) && (int)$response > 0) {
			return [$response, false];
		}

		$decoded = json_decode($response, true);

		if (is_array($decoded) && !empty($decoded['IDGet'])) {
			return [(string)$decoded['IDGet'], false];
		}

		return [null, true];
	}

	/**
	 * Read a field from POST first, then GET.
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

		if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'bitpay.bitpay') {
			$json['error'] = $this->language->get('error_payment_method');

			return [];
		}

		return $order_info;
	}

	/**
	 * Orders in this store are always placed in IRR - see
	 * catalog/controller/checkout/confirm.php. BitPay's API takes amounts
	 * in Rial directly, so no conversion is needed here (unlike Aghaye
	 * Pardakht, which needs Toman).
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

	/**
	 * POST form-urlencoded fields to BitPay. When $decode_json is true the
	 * response is JSON-decoded before being returned; otherwise the raw
	 * response body is returned so the caller can inspect its shape itself
	 * (used by requestIdGet(), whose response may be a bare number rather
	 * than JSON).
	 *
	 * @param string               $url
	 * @param array<string, mixed> $fields
	 * @param bool                 $decode_json
	 *
	 * @return array{0: mixed, 1: bool}
	 */
	private function curlPostForm(string $url, array $fields, bool $decode_json = true): array {
		$curl = curl_init($url);

		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($fields));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);

		$response = curl_exec($curl);
		$error = curl_errno($curl) !== 0;

		curl_close($curl);

		if ($response === false) {
			return [null, true];
		}

		if (!$decode_json) {
			return [$response, $error];
		}

		$result = json_decode($response, true);

		return [$result, $error || $result === null];
	}
}
