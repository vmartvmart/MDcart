<?php
/**
 * Other
 *
 * @param int $length
 *
 * @return string
 */
function oc_token(int $length = 32): string {
	return substr(bin2hex(random_bytes($length)), 0, $length);
}

/** @return string */
function oc_get_ip(): string {
	$headers = [
		'HTTP_CF_CONNECTING_IP', // CloudFlare
		'HTTP_X_FORWARDED_FOR',  // AWS LB and other reverse-proxies
		'HTTP_X_REAL_IP',
		'HTTP_X_CLIENT_IP',
		'HTTP_CLIENT_IP',
		'HTTP_X_CLUSTER_CLIENT_IP',
	];

	foreach ($headers as $header) {
		if (!array_key_exists($header, $_SERVER)) {
			continue;
		}

		$ip = trim(explode(',', $_SERVER[$header])[0]);

		if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
			return $ip;
		}
	}

	return $_SERVER['REMOTE_ADDR'] ?? '';
}

// Sting functions

/**
 * @param string $string
 *
 * @return int
 */
function oc_strlen(string $string): int {
	return mb_strlen($string);
}

/**
 * @param string $string
 * @param string $needle
 * @param int    $offset
 *
 * @return false|int
 */
function oc_strpos(string $string, string $needle, int $offset = 0) {
	return mb_strpos($string, $needle, $offset);
}

/**
 * @param string $string
 * @param string $needle
 * @param int    $offset
 *
 * @return false|int
 */
function oc_strrpos(string $string, string $needle, int $offset = 0) {
	return mb_strrpos($string, $needle, $offset);
}

/**
 * @param string $string
 * @param int    $offset
 * @param ?int   $length
 *
 * @return string
 */
function oc_substr(string $string, int $offset, ?int $length = null): string {
	return mb_substr($string, $offset, $length);
}

/**
 * @param string $string
 *
 * @return string
 */
function oc_strtoupper(string $string): string {
	return mb_strtoupper($string);
}

/**
 * @param string $string
 *
 * @return string
 */
function oc_strtolower(string $string): string {
	return mb_strtolower($string);
}

/**
 * @param string $pattern
 * @param int    $flags
 *
 * @return array<int, string>
 */
function oc_glob(string $pattern, int $flags = 0): array {
	if (strpos($pattern, '{') === false) {
		$result = glob($pattern, $flags);

		return is_array($result) ? $result : [];
	}

	$matches = [];
	if (preg_match('/\{([^}]+)\}/', $pattern, $m)) {
		$options = explode(',', $m[1]);
		foreach ($options as $opt) {
			$newPattern = str_replace($m[0], $opt, $pattern);
			// Now safe because oc_glob always returns an array
			$matches = array_merge($matches, oc_glob($newPattern, $flags));
		}
	}

	$matches = array_unique($matches);
	sort($matches);

	return $matches;
}

// Jalali (Persian/Shamsi) date functions

/**
 * Convert a Gregorian date to its Jalali (Persian/Shamsi) equivalent.
 *
 * @param int $g_y
 * @param int $g_m
 * @param int $g_d
 *
 * @return array<int, int> [year, month, day]
 */
function oc_gregorian_to_jalali(int $g_y, int $g_m, int $g_d): array {
	$g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
	$j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

	$gy = $g_y - 1600;
	$gm = $g_m - 1;
	$gd = $g_d - 1;

	$g_day_no = 365 * $gy + intdiv($gy + 3, 4) - intdiv($gy + 99, 100) + intdiv($gy + 399, 400);

	for ($i = 0; $i < $gm; $i++) {
		$g_day_no += $g_days_in_month[$i];
	}

	if ($gm > 1 && (($g_y % 4 == 0 && $g_y % 100 != 0) || ($g_y % 400 == 0))) {
		$g_day_no++;
	}

	$g_day_no += $gd;

	$j_day_no = $g_day_no - 79;

	$j_np = intdiv($j_day_no, 12053);
	$j_day_no %= 12053;

	$jy = 979 + 33 * $j_np + 4 * intdiv($j_day_no, 1461);
	$j_day_no %= 1461;

	if ($j_day_no >= 366) {
		$jy += intdiv($j_day_no - 1, 365);
		$j_day_no = ($j_day_no - 1) % 365;
	}

	$i = 0;

	for (; $i < 11 && $j_day_no >= $j_days_in_month[$i]; $i++) {
		$j_day_no -= $j_days_in_month[$i];
	}

	$jm = $i + 1;
	$jd = $j_day_no + 1;

	return [$jy, $jm, $jd];
}

/**
 * Format a timestamp using the Jalali (Persian/Shamsi) calendar.
 *
 * Behaves like PHP's date(), except the year/month/day/weekday tokens are
 * converted to their Jalali equivalent. Time tokens (H, i, s, a, A, ...) and
 * any other character are passed through to the native date() function.
 *
 * @param string   $format
 * @param int|null $timestamp
 *
 * @return string
 */
function oc_persian_digits(string $string): string {
	return strtr($string, [
		'0' => '۰',
		'1' => '۱',
		'2' => '۲',
		'3' => '۳',
		'4' => '۴',
		'5' => '۵',
		'6' => '۶',
		'7' => '۷',
		'8' => '۸',
		'9' => '۹'
	]);
}

/**
 * The reverse of oc_persian_digits() - converts Persian (۰-۹) and
 * Arabic-Indic (٠-٩) digit characters to plain ASCII digits (0-9).
 *
 * Iranian customers very commonly type numeric input (mobile numbers,
 * card numbers, etc.) on a Persian-language keyboard, which produces one
 * of these digit forms instead of plain ASCII ones - visually identical
 * to a "normal" number, but PHP's \d/\D (and any string digit check) only
 * recognize ASCII 0-9, so raw input must be converted through this first.
 * Confirmed 2026-09 as the root cause of DigiPay silently rejecting a
 * customer-entered mobile number that "looked" valid - see
 * extension/iranian_gateways/catalog/controller/payment/digipay.php's
 * normalizeCellNumber() and catalog/model/account/customer.php's
 * normalizeTelephone(), both of which now call this first.
 *
 * @param string $string
 *
 * @return string
 */
function oc_latin_digits(string $string): string {
	return strtr($string, [
		'۰' => '0',
		'۱' => '1',
		'۲' => '2',
		'۳' => '3',
		'۴' => '4',
		'۵' => '5',
		'۶' => '6',
		'۷' => '7',
		'۸' => '8',
		'۹' => '9',
		'٠' => '0',
		'١' => '1',
		'٢' => '2',
		'٣' => '3',
		'٤' => '4',
		'٥' => '5',
		'٦' => '6',
		'٧' => '7',
		'٨' => '8',
		'٩' => '9'
	]);
}

function oc_jdate(string $format, ?int $timestamp = null): string {
	if ($timestamp === null) {
		$timestamp = time();
	}

	[$jy, $jm, $jd] = oc_gregorian_to_jalali((int)date('Y', $timestamp), (int)date('n', $timestamp), (int)date('j', $timestamp));

	$j_month_names = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'امرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
	$j_day_names = ['شنبه', 'یک‌شنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];

	// Jalali week starts on Saturday, PHP's date('w') is 0 (Sun) - 6 (Sat)
	$j_weekday = ((int)date('w', $timestamp) + 1) % 7;

	$result = '';
	$length = strlen($format);

	for ($i = 0; $i < $length; $i++) {
		$char = $format[$i];

		if ($char == '\\' && $i < $length - 1) {
			$result .= $format[++$i];
			continue;
		}

		switch ($char) {
			case 'Y':
				$result .= (string)$jy;
				break;
			case 'y':
				$result .= substr((string)$jy, -2);
				break;
			case 'm':
				$result .= str_pad((string)$jm, 2, '0', STR_PAD_LEFT);
				break;
			case 'n':
				$result .= (string)$jm;
				break;
			case 'd':
				$result .= str_pad((string)$jd, 2, '0', STR_PAD_LEFT);
				break;
			case 'j':
				$result .= (string)$jd;
				break;
			case 'F':
			case 'M':
				$result .= $j_month_names[$jm - 1];
				break;
			case 'l':
			case 'D':
				$result .= $j_day_names[$j_weekday];
				break;
			case 'N':
				$result .= (string)($j_weekday + 1);
				break;
			case 'w':
				$result .= (string)$j_weekday;
				break;
			default:
				$result .= date($char, $timestamp);
		}
	}

	return oc_persian_digits($result);
}

/**
 * Oc Hex To Rgb
 *
 * Converts a "#rrggbb" (or shorthand "#rgb") color into an "r, g, b" string,
 * the format Bootstrap's own *-rgb custom properties expect (they're
 * consumed as e.g. rgba(var(--bs-primary-rgb), 0.5)). Used by the Design >
 * Colors admin/store color overrides — see catalog/controller/common/header.php
 * and panel/controller/common/header.php.
 *
 * @param string $hex
 *
 * @return string
 */
function oc_hex_to_rgb(string $hex): string {
	$hex = ltrim($hex, '#');

	if (strlen($hex) === 3) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
		return '0, 0, 0';
	}

	return implode(', ', [
		hexdec(substr($hex, 0, 2)),
		hexdec(substr($hex, 2, 2)),
		hexdec(substr($hex, 4, 2)),
	]);
}

/**
 * Oc Color Shade
 *
 * Darkens (negative $percent) or lightens (positive $percent) a "#rrggbb"
 * color by moving each channel that percentage of the way toward black or
 * white respectively. Used to derive hover/active button shades from a
 * single admin-picked "primary" color, the same way a Bootstrap theme
 * build normally would at compile time — see oc_hex_to_rgb()'s docblock.
 *
 * @param string $hex
 * @param float  $percent -100 to 100
 *
 * @return string
 */
function oc_color_shade(string $hex, float $percent): string {
	$hex = ltrim($hex, '#');

	if (strlen($hex) === 3) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
		return '#' . $hex;
	}

	$percent = max(-100, min(100, $percent)) / 100;

	$channels = [];

	foreach ([substr($hex, 0, 2), substr($hex, 2, 2), substr($hex, 4, 2)] as $part) {
		$value = hexdec($part);
		$target = $percent < 0 ? 0 : 255;
		$value = (int)round($value + ($target - $value) * abs($percent));
		$channels[] = str_pad(dechex(max(0, min(255, $value))), 2, '0', STR_PAD_LEFT);
	}

	return '#' . implode('', $channels);
}

/**
 * Oc Validate Hex Color
 *
 * @param string $value
 *
 * @return bool
 */
function oc_validate_hex_color(string $value): bool {
	return (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $value);
}
