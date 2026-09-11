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
