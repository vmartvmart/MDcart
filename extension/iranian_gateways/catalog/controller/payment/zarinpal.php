<?php
namespace Opencart\Catalog\Controller\Extension\IranianGateways\Payment;
/**
 * Class Zarinpal
 *
 * @package Opencart\Catalog\Controller\Extension\IranianGateways\Payment
 */
class Zarinpal extends \Opencart\System\Engine\Controller {
	private const API_REQUEST_URL = 'https://api.zarinpal.com/pg/v4/payment/request.json';
	private const API_VERIFY_URL = 'https://api.zarinpal.com/pg/v4/payment/verify.json';
	private const GATE_URL = 'https://www.zarinpal.com/pg/StartPay/';

	private const API_REQUEST_URL_SANDBOX = 'https://sandbox.zarinpal.com/pg/v4/payment/request.json';
	private const API_VERIFY_URL_SANDBOX = 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json';
	private const GATE_URL_SANDBOX = 'https://sandbox.zarinpal.com/pg/StartPay/';

	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/iranian_gateways/payment/zarinpal');

		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/iranian_gateways/payment/zarinpal', $data);
	}

	/**
	 * Confirm
	 *
	 * Called by the customer to start the ZarinPal payment - sends a payment
	 * request to ZarinPal and returns the URL to redirect the browser to.
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->load->language('extension/iranian_gateways/payment/zarinpal');

		$json = [];

		$order_info = $this->validateOrder($json);

		if (!$json && $order_info) {
			$amount = $this->getRialAmount($order_info, $json);

			if (!$json) {
				$sandbox = (bool)$this->config->get('payment_zarinpal_sandbox');

				$fields = [
					'merchant_id'  => $this->config->get('payment_zarinpal_merchant_id'),
					'amount'       => $amount,
					'currency'     => 'IRR',
					'description'  => sprintf($this->language->get('text_order_description'), $order_info['order_id'], $this->config->get('config_name')),
					'callback_url' => str_replace('&amp;', '&', $this->url->link('extension/iranian_gateways/payment/zarinpal.callback', '', true)),
					'metadata'     => array_filter([
						'mobile' => $order_info['telephone'],
						'email'  => $order_info['email'],
						'order_id' => (string)$order_info['order_id']
					])
				];

				[$result, $error] = $this->curlPost($sandbox ? self::API_REQUEST_URL_SANDBOX : self::API_REQUEST_URL, $fields);

				if ($error) {
					$json['error'] = $this->language->get('error_connection');
				} elseif (empty($result['data']['code']) || $result['data']['code'] != 100) {
					$json['error'] = $this->formatGatewayError($result);
				} else {
					$this->session->data['zarinpal_authority'] = $result['data']['authority'];

					$gate_url = $sandbox ? self::GATE_URL_SANDBOX : self::GATE_URL;

					$json['redirect'] = $gate_url . $result['data']['authority'];
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Callback
	 *
	 * ZarinPal redirects the customer's browser back here after payment.
	 *
	 * @return void
	 */
	public function callback(): void {
		$this->load->language('extension/iranian_gateways/payment/zarinpal');

		$failure_url = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

		$status = $this->request->get['Status'] ?? '';
		$authority = $this->request->get['Authority'] ?? '';

		if ($status !== 'OK' || !$authority) {
			$this->response->redirect($failure_url);

			return;
		}

		$json = [];

		$order_info = $this->validateOrder($json);

		if ($json || !$order_info) {
			$this->response->redirect($failure_url);

			return;
		}

		if (empty($this->session->data['zarinpal_authority']) || $this->session->data['zarinpal_authority'] !== $authority) {
			$this->response->redirect($failure_url);

			return;
		}

		$amount = $this->getRialAmount($order_info, $json);

		if ($json) {
			$this->response->redirect($failure_url);

			return;
		}

		$sandbox = (bool)$this->config->get('payment_zarinpal_sandbox');

		$fields = [
			'merchant_id' => $this->config->get('payment_zarinpal_merchant_id'),
			'amount'      => $amount,
			'authority'   => $authority
		];

		[$result, $error] = $this->curlPost($sandbox ? self::API_VERIFY_URL_SANDBOX : self::API_VERIFY_URL, $fields);

		$code = $result['data']['code'] ?? null;

		if ($error || ($code != 100 && $code != 101)) {
			$this->response->redirect($failure_url);

			return;
		}

		unset($this->session->data['zarinpal_authority']);

		$comment = sprintf($this->language->get('text_ref_id'), $result['data']['ref_id'] ?? '');

		$this->load->model('checkout/order');

		$this->model_checkout_order->addHistory($this->session->data['order_id'], (int)$this->config->get('payment_zarinpal_order_status_id'), $comment, true);

		$this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true));
	}

	/**
	 * Validate the session order and payment method, matching the pattern
	 * used by the other payment extensions in this store.
	 *
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

		if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'zarinpal.zarinpal') {
			$json['error'] = $this->language->get('error_payment_method');

			return [];
		}

		return $order_info;
	}

	/**
	 * Get the order amount in Rial.
	 *
	 * Orders in this store are always placed in IRR (see
	 * catalog/controller/checkout/confirm.php) so that payment gateways -
	 * which only accept Iranian Rial - always get the correct amount
	 * regardless of which currency the customer was browsing in.
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
		$errors = $result['errors'] ?? null;

		if (is_array($errors) && isset($errors['message'])) {
			return $errors['message'];
		}

		if (is_array($errors) && $errors) {
			$first = reset($errors);

			if (is_array($first) && isset($first['message'])) {
				return $first['message'];
			}
		}

		return $this->language->get('error_gateway');
	}

	/**
	 * POST JSON to ZarinPal and decode the JSON response.
	 *
	 * @param string               $url
	 * @param array<string, mixed> $fields
	 *
	 * @return array{0: array<string, mixed>|null, 1: bool}
	 */
	private function curlPost(string $url, array $fields): array {
		$curl = curl_init($url);

		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($fields));
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Accept: application/json'
		]);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);

		$response = curl_exec($curl);
		$error = curl_errno($curl) !== 0;

		curl_close($curl);

		$result = $response !== false ? json_decode($response, true) : null;

		return [$result, $error || $result === null];
	}
}
