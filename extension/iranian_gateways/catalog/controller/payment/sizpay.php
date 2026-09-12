<?php
namespace MDcart\Catalog\Controller\Extension\IranianGateways\Payment;
/**
 * Class SizPay
 *
 * SizPay only exposes a SOAP web service (KimiaIPGRouteService.asmx) and the
 * PHP soap extension is not available on this server, so this talks to it
 * directly over HTTP with hand-built SOAP 1.1 envelopes instead of
 * SoapClient. Field names/order and the SOAPAction values below come from
 * SizPay's published WSDL (https://rt.sizpay.ir/KimiaIPGRouteService.asmx?WSDL).
 *
 * Unlike the other gateways here, SizPay's payment page is reached by
 * POSTing the token to it (not a simple GET redirect), so confirm() sends
 * the browser to this extension's own gateway() action, which renders a
 * tiny auto-submitting form.
 *
 * @package MDcart\Catalog\Controller\Extension\IranianGateways\Payment
 */
class Sizpay extends \MDcart\System\Engine\Controller {
	private const SOAP_URL = 'https://rt.sizpay.ir/KimiaIPGRouteService.asmx';
	private const SOAP_NAMESPACE = 'https://rt.sizpay.com/';
	private const GATE_URL = 'https://rt.sizpay.ir/Route/Payment';

	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/iranian_gateways/payment/sizpay');

		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/iranian_gateways/payment/sizpay', $data);
	}

	/**
	 * Confirm
	 *
	 * Requests a token from SizPay and sends the browser to this
	 * extension's own gateway() action to auto-submit it.
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->load->language('extension/iranian_gateways/payment/sizpay');

		$json = [];

		$order_info = $this->validateOrder($json);

		if (!$json && $order_info) {
			$amount = $this->getRialAmount($order_info, $json);

			if (!$json) {
				$merchant_id = (string)$this->config->get('payment_sizpay_merchant_id');
				$terminal_id = (string)$this->config->get('payment_sizpay_terminal_id');
				$username = (string)$this->config->get('payment_sizpay_username');
				$password = (string)$this->config->get('payment_sizpay_password');

				$return_url = str_replace('&amp;', '&', $this->url->link('extension/iranian_gateways/payment/sizpay.callback', '', true));

				$body = '<GetToken xmlns="' . self::SOAP_NAMESPACE . '">'
					. '<GenerateTokenData>'
					. '<UserName>' . $this->xmlEscape($username) . '</UserName>'
					. '<Password>' . $this->xmlEscape($password) . '</Password>'
					. '<MerchantID>' . $this->xmlEscape($merchant_id) . '</MerchantID>'
					. '<TerminalID>' . $this->xmlEscape($terminal_id) . '</TerminalID>'
					. '<ReturnURL>' . $this->xmlEscape($return_url) . '</ReturnURL>'
					. '<OrderID>' . $this->xmlEscape((string)$order_info['order_id']) . '</OrderID>'
					. '<Amount>' . $amount . '</Amount>'
					. '<InvoiceNo>' . $this->xmlEscape((string)$order_info['order_id']) . '</InvoiceNo>'
					. '<AppExtraInf>'
					. '<PayerNm>' . $this->xmlEscape(trim($order_info['firstname'] . ' ' . $order_info['lastname'])) . '</PayerNm>'
					. '<PayerMobile>' . $this->xmlEscape((string)$order_info['telephone']) . '</PayerMobile>'
					. '<PayerEmail>' . $this->xmlEscape((string)$order_info['email']) . '</PayerEmail>'
					. '<Descr>' . $this->xmlEscape(sprintf($this->language->get('text_order_description'), $order_info['order_id'], $this->config->get('config_name'))) . '</Descr>'
					. '<PayTitleID>0</PayTitleID>'
					. '<PayerIP>' . $this->xmlEscape(oc_get_ip()) . '</PayerIP>'
					. '</AppExtraInf>'
					. '</GenerateTokenData>'
					. '</GetToken>';

				$dom = $this->soapCall(self::SOAP_NAMESPACE . 'GetToken', $body);

				$res_cod = $dom ? $this->tagValue($dom, 'ResCod') : null;
				$token = $dom ? $this->tagValue($dom, 'Token') : null;

				if ($dom === null || $res_cod === null || ($res_cod !== '0' && $res_cod !== '00') || !$token) {
					$json['error'] = $this->formatGatewayError($dom);
				} else {
					$this->session->data['sizpay_token'] = $token;

					$json['redirect'] = $this->url->link('extension/iranian_gateways/payment/sizpay.gateway', 'language=' . $this->config->get('config_language'), true);
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Gateway
	 *
	 * Renders a self-submitting form that POSTs the token to SizPay, since
	 * SizPay's payment page must be reached by POST, not a GET redirect.
	 *
	 * @return void
	 */
	public function gateway(): void {
		$this->load->language('extension/iranian_gateways/payment/sizpay');

		if (!isset($this->session->data['order_id']) || empty($this->session->data['sizpay_token']) || !isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'sizpay.sizpay') {
			$this->response->redirect($this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true));

			return;
		}

		$data['action'] = self::GATE_URL;
		$data['fields'] = [
			'MerchantID' => (string)$this->config->get('payment_sizpay_merchant_id'),
			'TerminalID' => (string)$this->config->get('payment_sizpay_terminal_id'),
			'UserName'   => (string)$this->config->get('payment_sizpay_username'),
			'Password'   => (string)$this->config->get('payment_sizpay_password'),
			'Token'      => (string)$this->session->data['sizpay_token']
		];

		$this->response->setOutput($this->load->view('extension/iranian_gateways/payment/sizpay_gateway', $data));
	}

	/**
	 * Callback
	 *
	 * @return void
	 */
	public function callback(): void {
		$this->load->language('extension/iranian_gateways/payment/sizpay');

		$failure_url = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

		$res_cod = $this->requestParam('ResCod');

		if ($res_cod === null || ($res_cod !== '0' && $res_cod !== '00')) {
			$this->response->redirect($failure_url);

			return;
		}

		$json = [];

		$order_info = $this->validateOrder($json);

		if ($json || !$order_info) {
			$this->response->redirect($failure_url);

			return;
		}

		$token = $this->session->data['sizpay_token'] ?? '';

		if (!$token) {
			$this->response->redirect($failure_url);

			return;
		}

		$merchant_id = (string)$this->config->get('payment_sizpay_merchant_id');
		$terminal_id = (string)$this->config->get('payment_sizpay_terminal_id');
		$username = (string)$this->config->get('payment_sizpay_username');
		$password = (string)$this->config->get('payment_sizpay_password');

		$body = '<Confirm2 xmlns="' . self::SOAP_NAMESPACE . '">'
			. '<MerchantID>' . $this->xmlEscape($merchant_id) . '</MerchantID>'
			. '<TerminalID>' . $this->xmlEscape($terminal_id) . '</TerminalID>'
			. '<Token>' . $this->xmlEscape($token) . '</Token>'
			. '<UserName>' . $this->xmlEscape($username) . '</UserName>'
			. '<Password>' . $this->xmlEscape($password) . '</Password>'
			. '</Confirm2>';

		$dom = $this->soapCall(self::SOAP_NAMESPACE . 'Confirm2', $body);
		$result_string = $dom ? $this->tagValue($dom, 'Confirm2Result') : null;
		$result = $result_string ? json_decode($result_string, true) : null;

		$confirm_res_cod = is_array($result) ? (string)($result['ResCod'] ?? '') : null;

		if (!is_array($result) || ($confirm_res_cod !== '0' && $confirm_res_cod !== '00')) {
			$this->response->redirect($failure_url);

			return;
		}

		$amount = $this->getRialAmount($order_info, $json);

		if ($json || (int)($result['Amount'] ?? 0) !== $amount || (string)($result['OrderID'] ?? '') !== (string)$order_info['order_id']) {
			$this->response->redirect($failure_url);

			return;
		}

		unset($this->session->data['sizpay_token']);

		$comment = sprintf($this->language->get('text_ref_no'), $result['RefNo'] ?? '', $result['TraceNo'] ?? '');

		$this->load->model('checkout/order');

		$this->model_checkout_order->addHistory($this->session->data['order_id'], (int)$this->config->get('payment_sizpay_order_status_id'), $comment, true);

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

		if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'sizpay.sizpay') {
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
	 * @param \DOMDocument|null $dom
	 *
	 * @return string
	 */
	private function formatGatewayError($dom): string {
		if ($dom) {
			$message = $this->tagValue($dom, 'Message');

			if ($message) {
				return $message;
			}
		}

		return $this->language->get('error_gateway');
	}

	/**
	 * @param string $value
	 *
	 * @return string
	 */
	private function xmlEscape(string $value): string {
		return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}

	/**
	 * First value of a tag, matched by local name regardless of namespace
	 * prefix (the prefixes are stripped before parsing - see soapCall()).
	 *
	 * @param \DOMDocument $dom
	 * @param string       $tag
	 *
	 * @return string|null
	 */
	private function tagValue(\DOMDocument $dom, string $tag): ?string {
		$nodes = $dom->getElementsByTagName($tag);

		return $nodes->length ? $nodes->item(0)->nodeValue : null;
	}

	/**
	 * POST a SOAP 1.1 envelope to SizPay and parse the XML response.
	 *
	 * @param string $action    SOAPAction header value
	 * @param string $body_xml  XML to place inside <soap:Body>
	 *
	 * @return \DOMDocument|null
	 */
	private function soapCall(string $action, string $body_xml): ?\DOMDocument {
		$envelope = '<?xml version="1.0" encoding="utf-8"?>'
			. '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
			. '<soap:Body>' . $body_xml . '</soap:Body>'
			. '</soap:Envelope>';

		$curl = curl_init(self::SOAP_URL);

		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $envelope);
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Content-Type: text/xml; charset=utf-8',
			'SOAPAction: "' . $action . '"'
		]);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);

		$response = curl_exec($curl);
		$error = curl_errno($curl) !== 0;

		curl_close($curl);

		if ($error || $response === false) {
			return null;
		}

		// Strip namespace prefixes so tag lookups don't depend on which
		// prefix (if any) the server happened to use.
		$response = preg_replace('/(<\/?)[a-zA-Z0-9]+:/', '$1', $response);

		$dom = new \DOMDocument();

		$previous = libxml_use_internal_errors(true);
		$loaded = $dom->loadXML($response);
		libxml_use_internal_errors($previous);

		return $loaded ? $dom : null;
	}
}
