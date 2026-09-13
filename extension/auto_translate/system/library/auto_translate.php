<?php
namespace MDcart\System\Library\Extension\AutoTranslate;
/**
 * Class AutoTranslate
 *
 * Minimal client for Anthropic's Claude Messages API (https://api.anthropic.com/v1/messages),
 * used to translate product/category text fields between Persian and English.
 *
 * Deliberately raw cURL with no SDK/Composer dependency, matching every other
 * extension library in this project (Ippanel, Telegram, Bale, Whatsapp).
 *
 * SECURITY: this class never logs, echoes, or returns the API key anywhere -
 * $this->error is built only from the HTTP status and the API's own JSON
 * error body, never from the request we sent. Callers must do the same:
 * never print the request payload or headers.
 *
 * @package MDcart\System\Library\Extension\AutoTranslate
 */
class AutoTranslate {
	private const API_URL = 'https://api.anthropic.com/v1/messages';
	private const API_VERSION = '2023-06-01';
	private const DEFAULT_MODEL = 'claude-haiku-4-5-20251001';

	private string $api_key;
	private string $model;

	/**
	 * @var string|null Last error message from a failed translateFields() call. Never contains the API key.
	 */
	public ?string $error = null;

	/**
	 * @param string $api_key
	 * @param string $model   Anthropic model id, e.g. "claude-haiku-4-5-20251001". Falls back to DEFAULT_MODEL when empty.
	 */
	public function __construct(string $api_key, string $model = '') {
		$this->api_key = $api_key;
		$this->model = $model !== '' ? $model : self::DEFAULT_MODEL;
	}

	/**
	 * Translate a flat set of named text fields as a single batched request, preserving any HTML markup.
	 *
	 * Only non-empty fields are sent (nothing to translate from an empty field), and the
	 * returned array always has the same keys as the (filtered) input - any key the model
	 * fails to return is filled back in with its original, untranslated value rather than
	 * silently dropped.
	 *
	 * @param array<string, string> $fields      field name => source text, e.g. ['name' => '...', 'description' => '<p>...</p>']
	 * @param string                $source_name human-readable source language name, e.g. "Persian (Farsi)"
	 * @param string                $target_name human-readable target language name, e.g. "English"
	 * @param int                   $max_chars   truncate each field to this many characters before sending (cost guard)
	 *
	 * @return array<string, string>|null translated fields keyed the same as the filtered $fields, or null on failure (see $this->error)
	 */
	public function translateFields(array $fields, string $source_name, string $target_name, int $max_chars = 20000): ?array {
		$this->error = null;

		$fields = array_map(static function ($value) use ($max_chars) {
			$value = (string)$value;

			return $max_chars > 0 ? mb_substr($value, 0, $max_chars) : $value;
		}, array_filter($fields, static fn ($value) => trim((string)$value) !== ''));

		if (!$fields) {
			return [];
		}

		$system = 'You are a professional e-commerce localization translator for an online store. '
			. 'Translate the string values of the given JSON object from ' . $source_name . ' to ' . $target_name . '. '
			. 'Keep exactly the same JSON keys. Preserve any HTML tags exactly as-is, translating only the text nodes inside them. '
			. 'Write natural, concise storefront/marketing copy - do not translate word-for-word. '
			. 'For a "tag" or "meta_keyword" field (a comma-separated list of search keywords), return an equivalent comma-separated keyword list in the target language rather than a literal translation. '
			. 'Never add, remove, or rename keys, and never add commentary. Respond with ONLY the raw JSON object - no markdown code fences, no explanation before or after it.';

		$payload = [
			'model'      => $this->model,
			'max_tokens' => 4096,
			'system'     => $system,
			'messages'   => [
				['role' => 'user', 'content' => (string)json_encode($fields, JSON_UNESCAPED_UNICODE)],
			],
		];

		$curl = curl_init(self::API_URL);

		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'x-api-key: ' . $this->api_key,
			'anthropic-version: ' . self::API_VERSION,
		]);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 60);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

		$response = curl_exec($curl);
		$curl_error = curl_error($curl);
		$http_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close($curl);

		if ($response === false) {
			$this->error = 'cURL error: ' . $curl_error;

			return null;
		}

		$result = json_decode($response, true);

		if ($http_code !== 200 || !is_array($result)) {
			$this->error = 'Anthropic API error (HTTP ' . $http_code . '): ' . (is_array($result) ? (string)($result['error']['message'] ?? 'unknown') : 'invalid response');

			return null;
		}

		$text = (string)($result['content'][0]['text'] ?? '');

		// Be lenient in case the model wraps the JSON in a code fence despite instructions.
		if (preg_match('/\{.*\}/s', $text, $matches)) {
			$text = $matches[0];
		}

		$translated = json_decode($text, true);

		if (!is_array($translated)) {
			$this->error = 'Could not parse the translation response as JSON';

			return null;
		}

		$output = [];

		foreach ($fields as $key => $value) {
			$output[$key] = is_string($translated[$key] ?? null) ? $translated[$key] : $value;
		}

		return $output;
	}
}
