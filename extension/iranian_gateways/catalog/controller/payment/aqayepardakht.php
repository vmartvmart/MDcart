<?php
namespace MDcart\Catalog\Controller\Extension\IranianGateways\Payment;
/**
 * Class Aqayepardakht
 *
 * @package MDcart\Catalog\Controller\Extension\IranianGateways\Payment
 */
class Aqayepardakht extends \MDcart\System\Engine\Controller {
	private const API_CREATE_URL = 'https://panel.aqayepardakht.ir/api/v2/create';
	private const API_VERIFY_URL = 'https://panel.aqayepardakht.ir/api/v2/verify';
	private const GATE_URL = 'https://panel.aqayepardakht.ir/startpay/';
	private const GATE_URL_SANDBOX = 'https://panel.aqayepardakht.ir/startpay/sandbox/';

	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/iranian_gateways/payment/aqayepardakht');

		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/iranian_gateways/payment/aqayepardakht', $data);
	}

	/**
	 * Confirm
	 *
	 * Called by the customer to start the Aghaye Pardakht payment - sends a
	 * create request and returns the URL to redirect the browser to.
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->load->language('extension/iranian_gateways/payment/aqayepardakht');

		$json = [];

		$order_info = $this->validateOrder($json);

		if (!$json && $order_info) {
			$amount = $this->getTomanAmount($order_info, $json);

			if (!$json) {
				$fields = [
					'pin'      => $this->config->get('payment_aqayepardakht_pin'),
					'amount'   => $amount,
					'callback' => str_replace('&amp;', '&', $this->url->link('extension/iranian_gateways/payment/aqayepardakht.callback', '', true))
				];

				$optional = [
					'invoice_id'  => (string)$order_info['order_id'],
					'mobile'      => $order_info['telephone'],
					'email'       => $order_info['email'],
					'description' => sprintf($this->language->get('text_order_description'), $order_info['order_id'], $this->config->get('config_name'))
				];

				foreach ($optional as $key => $value) {
					if ($value !== null && $value !== '') {
						$fields[$key] = $value;
					}
				}

				[$result, $http_code, $error] = $this->curlPost(self::API_CREATE_URL, $fields);

				if ($error || $http_code != 200 || empty($result['status']) || $result['status'] !== 'success' || empty($result['transid'])) {
					$json['error'] = $this->formatGatewayError($result);
				} else {
					$this->session->data['aqayepardakht_transid'] = $result['transid'];

					$sandbox = (bool)$this->config->get('payment_aqayepardakht_sandbox');

					$gate_url = $sandbox ? self::GATE_URL_SANDBOX : self::GATE_URL;

					$json['redirect'] = $gate_url . $result['transid'];
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Callback
	 *
	 * Aghaye Pardakht sends the customer back here via POST with transid
	 * and status ("1" for success, "0" for failure) - read both GET and
	 * POST defensively, matching the pattern used for IDPay in this store.
	 *
	 * @return void
	 */
	public function callback(): void {
		$this->load->language('extension/iranian_gateways/payment/aqayepardakht');

		$failure_url = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

		$status = $this->requestParam('status');
		$transid = $this->requestParam('transid');

		if ($status === null || !$transid) {
			$this->response->redirect($failure_url);

			return;
		}

		$json = [];

		$order_info = $this->validateOrder($json);

		if ($json || !$order_info) {
			$this->response->redirect($failure_url);

			return;
		}

		if (empty($this->session->data['aqayepardakht_transid']) || (string)$this->session->data['aqayepardakht_transid'] !== $transid) {
			$this->response->redirect($failure_url);

			return;
		}

		if ($status !== '1') {
			unset($this->session->data['aqayepardakht_transid']);

			$this->response->redirect($failure_url);

			return;
		}

		$amount = $this->getTomanAmount($order_info, $json);

		if ($json) {
			$this->response->redirect($failure_url);

			return;
		}

		$fields = [
			'pin'     => $this->config->get('payment_aqayepardakht_pin'),
			'amount'  => $amount,
			'transid' => $transid
		];

		[$result, $http_code, $error] = $this->curlPost(self::API_VERIFY_URL, $fields);

		if ($error || $http_code != 200 || empty($result['status']) || $result['status'] !== 'success' || (string)($result['code'] ?? '') !== '1') {
			$this->response->redirect($failure_url);

			return;
		}

		unset($this->session->data['aqayepardakht_transid']);

		$comment = sprintf($this->language->get('text_track_id'), $transid);

		$this->load->model('checkout/order');

		$this->model_checkout_order->addHistory($this->session->data['order_id'], (int)$this->config->get('payment_aqayepardakht_order_status_id'), $comment, true);

		$this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true));
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

		if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'aqayepardakht.aqayepardakht') {
			$json['error'] = $this->language->get('error_payment_method');

			return [];
		}

		return $order_info;
	}

	/**
	 * Orders in this store are always placed in IRR - see
	 * catalog/controller/checkout/confirm.php. Aghaye Pardakht's API takes
	 * amounts in Toman (1 Toman = 10 Rial), so the Rial total is converted
	 * down before it is sent.
	 *
	 * @param array<string, mixed> $order_info
	 * @param array<string, mixed> $json
	 *
	 * @return int
	 */
	private function getTomanAmount(array $order_info, array &$json): int {
		if ($order_info['currency_code'] !== 'IRR') {
			$json['error'] = $this->language->get('error_currency');

			return 0;
		}

		$rial = (int)round($order_info['total'] * $order_info['currency_value']);

		return (int)round($rial / 10);
	}

	/**
	 * @param array<string, mixed>|null $result
	 *
	 * @return string
	 */
	private function formatGatewayError($result): string {
		if (is_array($result) && !empty($result['code'])) {
			return sprintf($this->language->get('error_gateway_code'), $result['code']);
		}

		return $this->language->get('error_gateway');
	}

	/**
	 * POST JSON to Aghaye Pardakht and decode the JSON response.
	 *
	 * @param string               $url
	 * @param array<string, mixed> $fields
	 *
	 * @return array{0: array<string, mixed>|null, 1: int, 2: bool}
	 */
	private function curlPost(string $url, array $fields): array {
		$curl = curl_init($url);

		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($fields));
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json'
		]);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);

		$response = curl_exec($curl);
		$error = curl_errno($curl) !== 0;
		$http_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close($curl);

		$result = $response !== false ? json_decode($response, true) : null;

		return [$result, $http_code, $error];
	}
}
