<?php
namespace Opencart\System\Library\Extension\Whatsapp;
/**
 * Class Whatsapp
 *
 * Minimal client for Meta's official WhatsApp Cloud API.
 * Contract verified against https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-messages/
 *
 * Only template messages are supported, because the Cloud API does not allow
 * free-form text to be sent to a customer outside an active 24h conversation
 * window — any proactive notification (new order, status change, admin alert)
 * must use a template pre-approved in Meta Business Manager.
 *
 * @package Opencart\System\Library\Extension\Whatsapp
 */
class Whatsapp {
	private const API_VERSION = 'v23.0';

	private string $phone_number_id;
	private string $access_token;

	public ?string $error = null;

	public function __construct(string $phone_number_id, string $access_token) {
		$this->phone_number_id = $phone_number_id;
		$this->access_token = $access_token;
	}

	/**
	 * Send a template message with a single body component made up of ordered text parameters.
	 *
	 * @param string        $to            E.164 or Iranian local phone number.
	 * @param string        $template_name Name of the template approved in Meta Business Manager.
	 * @param string        $language_code e.g. "fa" or "en_US".
	 * @param array<string> $parameters    Ordered {{1}}, {{2}}, ... body parameter values.
	 *
	 * @return bool
	 */
	public function sendTemplate(string $to, string $template_name, string $language_code, array $parameters = []): bool {
		$this->error = null;

		$recipient = $this->normalizeNumber($to);

		if (!$recipient) {
			$this->error = 'Invalid recipient number: ' . $to;

			return false;
		}

		$payload = [
			'messaging_product' => 'whatsapp',
			'recipient_type'    => 'individual',
			'to'                => $recipient,
			'type'              => 'template',
			'template'          => [
				'name'     => $template_name,
				'language' => ['code' => $language_code]
			]
		];

		if ($parameters) {
			$payload['template']['components'] = [
				[
					'type'       => 'body',
					'parameters' => array_map(fn ($value) => ['type' => 'text', 'text' => (string)$value], $parameters)
				]
			];
		}

		$curl = curl_init('https://graph.facebook.com/' . self::API_VERSION . '/' . $this->phone_number_id . '/messages');

		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->access_token
		]);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

		$response = curl_exec($curl);
		$curl_error = curl_error($curl);

		curl_close($curl);

		if ($response === false) {
			$this->error = 'cURL error: ' . $curl_error;

			return false;
		}

		$result = json_decode($response, true);

		if (!is_array($result) || !empty($result['error'])) {
			$this->error = is_array($result) ? ($result['error']['message'] ?? 'Unknown WhatsApp error') : 'Invalid WhatsApp response: ' . $response;

			return false;
		}

		return true;
	}

	/**
	 * Normalize an Iranian mobile number to E.164 (+98XXXXXXXXXX).
	 *
	 * @param string $number
	 *
	 * @return string|null
	 */
	private function normalizeNumber(string $number): ?string {
		$digits = preg_replace('/[^0-9]/', '', $number);

		if ($digits === '') {
			return null;
		}

		if (str_starts_with($digits, '0098')) {
			$digits = substr($digits, 2);
		}

		if (str_starts_with($digits, '98') && strlen($digits) === 12) {
			return '+' . $digits;
		}

		if (str_starts_with($digits, '0') && strlen($digits) === 11) {
			return '+98' . substr($digits, 1);
		}

		if (strlen($digits) === 10) {
			return '+98' . $digits;
		}

		return null;
	}
}
