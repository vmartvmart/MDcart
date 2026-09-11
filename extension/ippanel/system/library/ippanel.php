<?php
namespace Opencart\System\Library\Extension\Ippanel;
/**
 * Class Ippanel
 *
 * Minimal client for IPPanel's Edge REST API (https://edge.ippanel.com).
 * Contract verified against https://ippanelcom.github.io/Edge-Document/docs/send/webservice
 *
 * @package Opencart\System\Library\Extension\Ippanel
 */
class Ippanel {
	private const API_URL = 'https://edge.ippanel.com/v1/api/send';

	private string $api_key;
	private string $from_number;

	/**
	 * @var string|null Last error message from a failed send() call.
	 */
	public ?string $error = null;

	public function __construct(string $api_key, string $from_number) {
		$this->api_key = $api_key;
		$this->from_number = $from_number;
	}

	/**
	 * Send a single SMS.
	 *
	 * @param string $to      Recipient number, Iranian local ("09123456789") or E.164 ("+989123456789") format.
	 * @param string $message
	 *
	 * @return bool
	 */
	public function send(string $to, string $message): bool {
		$this->error = null;

		$recipient = $this->normalizeNumber($to);

		if (!$recipient) {
			$this->error = 'Invalid recipient number: ' . $to;

			return false;
		}

		$payload = [
			'sending_type' => 'webservice',
			'from_number'  => $this->from_number,
			'message'      => $message,
			'params'       => [
				'recipients' => [$recipient]
			]
		];

		$curl = curl_init(self::API_URL);

		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Authorization: ' . $this->api_key
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

		if (!is_array($result) || empty($result['meta']['status'])) {
			$this->error = is_array($result) ? ($result['meta']['message'] ?? 'Unknown IPPanel error') : 'Invalid IPPanel response: ' . $response;

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
