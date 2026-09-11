<?php
namespace Opencart\Catalog\Controller\Extension\IranianGateways\Payment;
/**
 * Class PayPing
 *
 * @package Opencart\Catalog\Controller\Extension\IranianGateways\Payment
 */
class Payping extends \Opencart\System\Engine\Controller {
	private const API_PAY_URL = 'https://api.payping.ir/v2/pay';
	private const API_VERIFY_URL = 'https://api.payping.ir/v2/pay/verify';
	private const GATE_URL = 'https://api.payping.ir/v2/pay/gotoipg/';

	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/iranian_gateways/payment/payping');

		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/iranian_gateways/payment/payping', $data);
	}

	/**
	 * Confirm
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->load->language('extension/iranian_gateways/payment/payping');

		$json = [];

		$order_info = $this->validateOrder($json);

		if (!$json && $order_info) {
			$amount = $this->getTomanAmount($order_info, $json);

			if (!$json) {
				$fields = [
					'clientRefId'   => (string)$order_info['order_id'],
					'payerIdentity' => $order_info['telephone'],
					'payerName'     => trim($order_info['firstname'] . ' ' . $order_info['lastname']),
					'amount'        => $amount,
					'description'   => sprintf($this->language->get('text_order_description'), $order_info['order_id'], $this->config->get('config_name')),
					'returnUrl'     => str_replace('&amp;', '&', $this->url->link('extension/iranian_gateways/payment/payping.callback', '', true))
				];

				[$result, $http_code, $error] = $this->curlPost(self::API_PAY_URL, $fields);

				if ($error || $http_code != 200 || empty($result['code'])) {
					$json['error'] = $this->formatGatewayError($result);
				} else {
					$this->session->data['payping_code'] = $result['code'];

					$json['redirect'] = self::GATE_URL . $result['code'];
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Callback
	 *
	 * @return void
	 */
	public function callback(): void {
		$this->load->language('extension/iranian_gateways/payment/payping');

		$failure_url = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

		$ref_id = $this->request->get['refid'] ?? '';
		$client_ref_id = $this->request->get['clientrefid'] ?? '';

		if (!$ref_id || !$client_ref_id) {
			$this->response->redirect($failure_url);

			return;
		}

		$json = [];

		$order_info = $this->validateOrder($json);

		if ($json || !$order_info || (string)$order_info['order_id'] !== (string)$client_ref_id) {
			$this->response->redirect($failure_url);

			return;
		}

		if (empty($this->session->data['payping_code'])) {
			$this->response->redirect($failure_url);

			return;
		}

		$amount = $this->getTomanAmount($order_info, $json);

		if ($json) {
			$this->response->redirect($failure_url);

			return;
		}

		$fields = [
			'refId'  => $ref_id,
			'amount' => $amount
		];

		[, $http_code, $error] = $this->curlPost(self::API_VERIFY_URL, $fields);

		if ($error || $http_code < 200 || $http_code >= 300) {
			$this->response->redirect($failure_url);

			return;
		}

		unset($this->session->data['payping_code']);

		$comment = sprintf($this->language->get('text_ref_id'), $ref_id);

		$this->load->model('checkout/order');

		$this->model_checkout_order->addHistory($this->session->data['order_id'], (int)$this->config->get('payment_payping_order_status_id'), $comment, true);

		$this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true));
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

		if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'payping.payping') {
			$json['error'] = $this->language->get('error_payment_method');

			return [];
		}

		return $order_info;
	}

	/**
	 * Orders in this store are always placed in IRR - see
	 * catalog/controller/checkout/confirm.php. PayPing's API takes Toman,
	 * so this converts (1 Toman = 10 Rial).
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
		if (is_array($result)) {
			return json_encode($result, JSON_UNESCAPED_UNICODE);
		}

		return $this->language->get('error_gateway');
	}

	/**
	 * POST JSON to PayPing and decode the JSON response.
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
			'Accept: application/json',
			'Authorization: Bearer ' . $this->config->get('payment_payping_api_key')
		]);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($curl, CURLOPT_TIMEOUT, 45);

		$response = curl_exec($curl);
		$error = curl_errno($curl) !== 0;
		$http_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close($curl);

		$result = $response !== false ? json_decode($response, true) : null;

		return [$result, $http_code, $error];
	}
}
