<?php
namespace Opencart\System\Library\Extension\Telegram;
/**
 * Class Telegram
 *
 * Minimal client for the Telegram Bot API (https://core.telegram.org/bots/api).
 *
 * @package Opencart\System\Library\Extension\Telegram
 */
class Telegram {
	private string $token;

	public ?string $error = null;

	public function __construct(string $token) {
		$this->token = $token;
	}

	/**
	 * Send a text message to a chat.
	 *
	 * @param string|int $chat_id
	 * @param string     $text
	 *
	 * @return bool
	 */
	public function sendMessage($chat_id, string $text): bool {
		$result = $this->call('sendMessage', [
			'chat_id'    => $chat_id,
			'text'       => $text,
			'parse_mode' => 'HTML'
		]);

		return $result !== null;
	}

	/**
	 * Register (or clear, with an empty $url) the webhook URL Telegram will POST updates to.
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	public function setWebhook(string $url): bool {
		return $this->call('setWebhook', ['url' => $url]) !== null;
	}

	/**
	 * @param string               $method
	 * @param array<string, mixed> $params
	 *
	 * @return array<string, mixed>|null
	 */
	private function call(string $method, array $params): ?array {
		$this->error = null;

		$curl = curl_init('https://api.telegram.org/bot' . $this->token . '/' . $method);

		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
		curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

		$response = curl_exec($curl);
		$curl_error = curl_error($curl);

		curl_close($curl);

		if ($response === false) {
			$this->error = 'cURL error: ' . $curl_error;

			return null;
		}

		$result = json_decode($response, true);

		if (!is_array($result) || empty($result['ok'])) {
			$this->error = is_array($result) ? ($result['description'] ?? 'Unknown Telegram error') : 'Invalid Telegram response: ' . $response;

			return null;
		}

		return $result['result'] ?? [];
	}
}
