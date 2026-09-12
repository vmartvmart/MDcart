<?php
namespace MDcart\Catalog\Controller\Extension\IranianGateways\Payment;
/**
 * Class IDPay
 *
 * @package MDcart\Catalog\Controller\Extension\IranianGateways\Payment
 */
class Idpay extends \MDcart\System\Engine\Controller {
	private const API_URL = 'https://api.idpay.ir/v1.1/payment';
	private const API_VERIFY_URL = 'https://api.idpay.ir/v1.1/payment/verify';

	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/iranian_gateways/payment/idpay');

		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/iranian_gateways/payment/idpay', $data);
	}

	/**
	 * Confirm
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->load->language('extension/iranian_gateways/payment/idpay');

		$json = [];

		$order_info = $this->validateOrder($json);

		if (!$json && $order_info) {
			$amount = $this->getRialAmount($order_info, $json);

			if (!$json) {
				$fields = [
					'order_id' => (string)$order_info['order_id'],
					'amount'   => $amount,
					'name'     => trim($order_info['firstname'] . ' ' . $order_info['lastname']),
					'phone'    => $order_info['telephone'],
					'mail'     => $order_info['email'],
					'desc'     => sprintf($this->language->get('text_order_description'), $order_info['order_id'], $this->config->get('config_name')),
					'callback' => str_replace('&amp;', '&', $this->url->link('extension/iranian_gateways/payment/idpay.callback', '', true))
				];

				[$result, $http_code, $error] = $this->curlPost(self::API_URL, $fields);

				if ($error || $http_code != 201 || empty($result['id']) || empty($result['link'])) {
					$json['error'] = $this->formatGatewayError($result);
				} else {
					$this->session->data['idpay_id'] = $result['id'];

					$json['redirect'] = $result['link'];
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Callback
	 *
	 * IDPay sends the customer back here, either via GET or POST depending
	 * on the merchant's IDPay panel configuration - read both.
	 *
	 * @return void
	 */
	public function callback(): void {
		$this->load->language('extension/iranian_gateways/payment/idpay');

		$failure_url = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

		$status = $this->requestParam('status');
		$id = $this->requestParam('id');
		$order_id = $this->requestParam('order_id');

		if ($status === null || !$id || !$order_id) {
			$this->response->redirect($failure_url);

			return;
		}

		$json = [];

		$order_info = $this->validateOrder($json);

		if ($json || !$order_info || (string)$order_info['order_id'] !== (string)$order_id) {
			$this->response->redirect($failure_url);

			return;
		}

		if (empty($this->session->data['idpay_id']) || $this->session->data['idpay_id'] !== $id) {
			$this->response->redirect($failure_url);

			return;
		}

		$fields = [
			'id'       => $id,
			'order_id' => (string)$order_id
		];

		[$result, $http_code, $error] = $this->curlPost(self::API_VERIFY_URL, $fields);

		if ($error || $http_code != 200 || empty($result['status']) || (int)$result['status'] < 100) {
			$this->response->redirect($failure_url);

			return;
		}

		$amount = $this->getRialAmount($order_info, $json);

		if ($json || (int)($result['amount'] ?? 0) !== $amount) {
			$this->response->redirect($failure_url);

			return;
		}

		unset($this->session->data['idpay_id']);

		$comment = sprintf($this->language->get('text_track_id'), $result['track_id'] ?? '');

		$this->load->model('checkout/order');

		$this->model_checkout_order->addHistory($this->session->data['order_id'], (int)$this->config->get('payment_idpay_order_status_id'), $comment, true);

		$this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true));
	}

	/**
	 * Read a field from POST first, then GET - IDPay can be configured to
	 * return either way.
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

		if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'idpay.idpay') {
			$json['error'] = $this->language->get('error_payment_method');

			return [];
		}

		return $order_info;
	}

	/**
	 * Orders in this store are always placed in IRR - see
	 * catalog/controller/checkout/confirm.php.
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
	 * @param array<string, mixed>|null $result
	 *
	 * @return string
	 */
	private function formatGatewayError($result): string {
		if (is_array($result) && !empty($result['error_message'])) {
			return $result['error_message'];
		}

		return $this->language->get('error_gateway');
	}

	/**
	 * POST JSON to IDPay and decode the JSON response.
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
			'Content-Type: application/json',
			'X-API-KEY: ' . $this->config->get('payment_idpay_api_key'),
			'X-SANDBOX: ' . ($this->config->get('payment_idpay_sandbox') ? '1' : '0')
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
