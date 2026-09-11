<?php
namespace Opencart\Catalog\Controller\Extension\IranianGateways\Payment;
/**
 * Class Card To Card
 *
 * @package Opencart\Catalog\Controller\Extension\IranianGateways\Payment
 */
class CardToCard extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/iranian_gateways/payment/card_to_card');

		$cards = $this->config->get('payment_card_to_card_cards') ?: [];
		$active = $this->config->get('payment_card_to_card_active');

		$card = $cards[$active] ?? [];

		$data['card_number'] = $this->formatCardNumber((string)($card['card_number'] ?? ''));
		$data['holder_name'] = $card['holder_name'] ?? '';
		$data['bank_name'] = $card['bank_name'] ?? '';
		$data['instruction'] = nl2br((string)$this->config->get('payment_card_to_card_instruction_' . $this->config->get('config_language_id')));
		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/iranian_gateways/payment/card_to_card', $data);
	}

	/**
	 * Confirm
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->load->language('extension/iranian_gateways/payment/card_to_card');

		$json = [];

		if (!isset($this->session->data['order_id'])) {
			$json['error'] = $this->language->get('error_order');
		}

		if (!$json && isset($this->session->data['order_id'])) {
			$this->load->model('checkout/order');

			$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

			if (!$order_info) {
				$json['redirect'] = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

				unset($this->session->data['order_id']);
			}
		}

		if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'card_to_card.card_to_card') {
			$json['error'] = $this->language->get('error_payment_method');
		}

		if (!$json) {
			$cards = $this->config->get('payment_card_to_card_cards') ?: [];
			$active = $this->config->get('payment_card_to_card_active');
			$card = $cards[$active] ?? [];

			$note = isset($this->request->post['note']) ? trim(strip_tags((string)$this->request->post['note'])) : '';

			$comment  = $this->language->get('text_instruction') . "\n\n";
			$comment .= sprintf($this->language->get('text_paid_to'), $this->formatCardNumber((string)($card['card_number'] ?? '')), $card['holder_name'] ?? '') . "\n\n";

			if ($note !== '') {
				$comment .= $this->language->get('text_customer_note') . ': ' . $note . "\n\n";
			}

			$comment .= $this->language->get('text_payment');

			$this->load->model('checkout/order');

			$this->model_checkout_order->addHistory($this->session->data['order_id'], (int)$this->config->get('payment_card_to_card_order_status_id'), $comment, true);

			$json['redirect'] = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Format a 16 digit card number as four groups of four digits.
	 *
	 * @param string $card_number
	 *
	 * @return string
	 */
	private function formatCardNumber(string $card_number): string {
		$digits = preg_replace('/\D/', '', $card_number);

		return trim(chunk_split((string)$digits, 4, ' '));
	}
}
