<?php
namespace Opencart\Admin\Model\Tool;
/**
 * Class SystemUpdate
 *
 * Lets a deployed site check the private GitHub repository this codebase
 * is distributed from (vmartvmart/MDcart by default) for a newer commit,
 * and apply that update in place from the admin panel — no server-side
 * git or SSH access required.
 *
 * Update mechanism: downloads the branch as a zip (GitHub "zipball" API),
 * extracts it, and copies every file over the live install EXCEPT the
 * paths listed in EXCLUDE_PATHS below (which mirror the project's
 * .gitignore: environment config files and runtime/generated storage).
 * Before touching anything, it takes a full backup (database + files) so
 * a bad update can be rolled back from the same admin page.
 *
 * Limitation (by design, to keep this safe): both the update and the
 * restore only add/overwrite files. A file removed upstream (or added by
 * an update, when restoring) is not deleted locally. Good enough for an
 * incrementally evolving custom build; a destructive sync was judged too
 * risky for a tool that runs unattended against a live store.
 *
 * @package Opencart\Admin\Model\Tool
 */
class SystemUpdate extends \Opencart\System\Engine\Model {
	/**
	 * Paths (relative to DIR_OPENCART, forward slashes) that an update must
	 * never touch. Kept in sync with the repository's own .gitignore.
	 */
	private const EXCLUDE_PATHS = [
		'config.php',
		'admin/config.php',
		'system/storage/cache/',
		'system/storage/logs/',
		'system/storage/session/',
		'system/storage/download/',
		'system/storage/upload/',
		'system/storage/backup/',
		'system/storage/marketplace/',
		'system/storage/modification/',
		'system/storage/update_tmp/',
		'image/cache/',
	];

	/**
	 * Paths excluded from the pre-update/backup snapshot itself — purely
	 * ephemeral or regenerable data, plus the backup folder itself (so a
	 * backup never tries to zip itself into itself). Unlike EXCLUDE_PATHS
	 * above, this list deliberately KEEPS uploads/downloads/config, because
	 * a backup exists to restore the site exactly as it was.
	 */
	private const BACKUP_EXCLUDE_PATHS = [
		'system/storage/cache/',
		'system/storage/logs/',
		'system/storage/session/',
		'system/storage/update_tmp/',
		'system/storage/backup/',
	];

	/**
	 * Paths excluded from the storage snapshot, this time relative to
	 * DIR_STORAGE itself rather than DIR_OPENCART — storage can live
	 * completely outside the site folder (moved out for security, as
	 * OpenCart's own dashboard recommends), so it's backed up as its own
	 * zip rather than assumed to be a subfolder of the site.
	 */
	private const STORAGE_BACKUP_EXCLUDE_PATHS = [
		'cache/',
		'logs/',
		'session/',
		'update_tmp/',
		'backup/',
	];

	/**
	 * How many automatic pre-update backups to keep. Older ones are pruned
	 * after each new backup so this doesn't grow without bound.
	 */
	private const BACKUP_KEEP = 5;

	/**
	 * Persian names for every country in the reference seed data
	 * (install_starter.sql), keyed by ISO 3166-1 alpha-2 code.
	 *
	 * Why this exists: OpenCart's own default install only ever seeds an
	 * English name into `oc_country_description` — when a second admin
	 * language (Persian, here) is added on top of that, every country row
	 * for the new language just gets the same English text copied in
	 * (visible in the country edit form as two identical name fields, one
	 * per language, both showing the English name). Nothing OpenCart ships
	 * fills in real per-language country names beyond that. This is a
	 * one-time, hand-curated fix for this project's two installed
	 * languages (fa/en-gb) — see localizeCountries() below, which is what
	 * actually applies it.
	 *
	 * @var array<string, string>
	 */
	private const COUNTRY_NAME_FA = [
		'AC' => 'جزیره آسنسیون', 'AD' => 'آندورا', 'AE' => 'امارات متحده عربی', 'AF' => 'افغانستان',
		'AG' => 'آنتیگوا و باربودا', 'AI' => 'آنگویلا', 'AL' => 'آلبانی', 'AM' => 'ارمنستان',
		'AN' => 'آنتیل هلند', 'AO' => 'آنگولا', 'AQ' => 'جنوبگان', 'AR' => 'آرژانتین',
		'AS' => 'ساموآی آمریکا', 'AT' => 'اتریش', 'AU' => 'استرالیا', 'AW' => 'آروبا',
		'AX' => 'جزایر الند', 'AZ' => 'آذربایجان', 'BA' => 'بوسنی و هرزگوین', 'BB' => 'باربادوس',
		'BD' => 'بنگلادش', 'BE' => 'بلژیک', 'BF' => 'بورکینافاسو', 'BG' => 'بلغارستان',
		'BH' => 'بحرین', 'BI' => 'بوروندی', 'BJ' => 'بنین', 'BL' => 'سن بارتلمی',
		'BM' => 'برمودا', 'BN' => 'برونئی', 'BO' => 'بولیوی', 'BQ' => 'بونیر، سینت اوستاتیوس و سابا',
		'BR' => 'برزیل', 'BS' => 'باهاما', 'BT' => 'بوتان', 'BV' => 'جزیره بووه',
		'BW' => 'بوتسوانا', 'BY' => 'بلاروس', 'BZ' => 'بلیز', 'CA' => 'کانادا',
		'CC' => 'جزایر کوکوس (کیلینگ)', 'CD' => 'جمهوری دموکراتیک کنگو', 'CF' => 'جمهوری آفریقای مرکزی', 'CG' => 'کنگو',
		'CH' => 'سوئیس', 'CI' => 'ساحل عاج', 'CK' => 'جزایر کوک', 'CL' => 'شیلی',
		'CM' => 'کامرون', 'CN' => 'چین', 'CO' => 'کلمبیا', 'CR' => 'کاستاریکا',
		'CU' => 'کوبا', 'CV' => 'کیپ ورد', 'CW' => 'کوراسائو', 'CX' => 'جزیره کریسمس',
		'CY' => 'قبرس', 'CZ' => 'جمهوری چک', 'DE' => 'آلمان', 'DJ' => 'جیبوتی',
		'DK' => 'دانمارک', 'DM' => 'دومینیکا', 'DO' => 'جمهوری دومینیکن', 'DZ' => 'الجزایر',
		'EC' => 'اکوادور', 'EE' => 'استونی', 'EG' => 'مصر', 'EH' => 'صحرای غربی',
		'ER' => 'اریتره', 'ES' => 'اسپانیا', 'ET' => 'اتیوپی', 'FI' => 'فنلاند',
		'FJ' => 'فیجی', 'FK' => 'جزایر فالکلند (مالویناس)', 'FM' => 'میکرونزی', 'FO' => 'جزایر فارو',
		'FR' => 'فرانسه', 'GA' => 'گابن', 'GB' => 'بریتانیا', 'GD' => 'گرنادا',
		'GE' => 'گرجستان', 'GF' => 'گویان فرانسه', 'GG' => 'گرنزی', 'GH' => 'غنا',
		'GI' => 'جبل‌الطارق', 'GL' => 'گرینلند', 'GM' => 'گامبیا', 'GN' => 'گینه',
		'GP' => 'گوادلوپ', 'GQ' => 'گینه استوایی', 'GR' => 'یونان', 'GS' => 'جزایر جورجیای جنوبی و ساندویچ جنوبی',
		'GT' => 'گواتمالا', 'GU' => 'گوام', 'GW' => 'گینه بیسائو', 'GY' => 'گویان',
		'HK' => 'هنگ‌کنگ', 'HM' => 'جزایر هرد و مک‌دونالد', 'HN' => 'هندوراس', 'HR' => 'کرواسی',
		'HT' => 'هائیتی', 'HU' => 'مجارستان', 'IC' => 'جزایر قناری', 'ID' => 'اندونزی',
		'IE' => 'ایرلند', 'IL' => 'اسرائیل', 'IM' => 'جزیره من', 'IN' => 'هند',
		'IO' => 'قلمرو بریتانیا در اقیانوس هند', 'IQ' => 'عراق', 'IR' => 'ایران', 'IS' => 'ایسلند',
		'IT' => 'ایتالیا', 'JE' => 'جرزی', 'JM' => 'جامائیکا', 'JO' => 'اردن',
		'JP' => 'ژاپن', 'KE' => 'کنیا', 'KG' => 'قرقیزستان', 'KH' => 'کامبوج',
		'KI' => 'کیریباتی', 'KM' => 'کومور', 'KN' => 'سنت کیتس و نویس', 'KP' => 'کره شمالی',
		'KR' => 'کره جنوبی', 'KW' => 'کویت', 'KY' => 'جزایر کیمن', 'KZ' => 'قزاقستان',
		'LA' => 'لائوس', 'LB' => 'لبنان', 'LC' => 'سنت لوسیا', 'LI' => 'لیختن‌اشتاین',
		'LK' => 'سریلانکا', 'LR' => 'لیبریا', 'LS' => 'لسوتو', 'LT' => 'لیتوانی',
		'LU' => 'لوکزامبورگ', 'LV' => 'لتونی', 'LY' => 'لیبی', 'MA' => 'مراکش',
		'MC' => 'موناکو', 'MD' => 'مولداوی', 'ME' => 'مونته‌نگرو', 'MF' => 'سنت مارتین (بخش فرانسوی)',
		'MG' => 'ماداگاسکار', 'MH' => 'جزایر مارشال', 'MK' => 'مقدونیه شمالی', 'ML' => 'مالی',
		'MM' => 'میانمار', 'MN' => 'مغولستان', 'MO' => 'ماکائو', 'MP' => 'جزایر ماریانای شمالی',
		'MQ' => 'مارتینیک', 'MR' => 'موریتانی', 'MS' => 'مونتسرات', 'MT' => 'مالت',
		'MU' => 'موریس', 'MV' => 'مالدیو', 'MW' => 'مالاوی', 'MX' => 'مکزیک',
		'MY' => 'مالزی', 'MZ' => 'موزامبیک', 'NA' => 'نامیبیا', 'NC' => 'کالدونیای جدید',
		'NE' => 'نیجر', 'NF' => 'جزیره نورفولک', 'NG' => 'نیجریه', 'NI' => 'نیکاراگوئه',
		'NL' => 'هلند', 'NO' => 'نروژ', 'NP' => 'نپال', 'NR' => 'نائورو',
		'NU' => 'نیوئه', 'NZ' => 'نیوزیلند', 'OM' => 'عمان', 'PA' => 'پاناما',
		'PE' => 'پرو', 'PF' => 'پلی‌نزی فرانسه', 'PG' => 'پاپوآ گینه نو', 'PH' => 'فیلیپین',
		'PK' => 'پاکستان', 'PL' => 'لهستان', 'PM' => 'سن پیر و میکلون', 'PN' => 'پیتکرن',
		'PR' => 'پورتوریکو', 'PS' => 'فلسطین', 'PT' => 'پرتغال', 'PW' => 'پالائو',
		'PY' => 'پاراگوئه', 'QA' => 'قطر', 'RE' => 'رئونیون', 'RO' => 'رومانی',
		'RS' => 'صربستان', 'RU' => 'روسیه', 'RW' => 'رواندا', 'SA' => 'عربستان سعودی',
		'SB' => 'جزایر سلیمان', 'SC' => 'سیشل', 'SD' => 'سودان', 'SE' => 'سوئد',
		'SG' => 'سنگاپور', 'SH' => 'سنت هلنا', 'SI' => 'اسلوونی', 'SJ' => 'سوالبارد و یان ماین',
		'SK' => 'اسلواکی', 'SL' => 'سیرالئون', 'SM' => 'سان مارینو', 'SN' => 'سنگال',
		'SO' => 'سومالی', 'SR' => 'سورینام', 'SS' => 'سودان جنوبی', 'ST' => 'سائوتومه و پرینسیپ',
		'SV' => 'السالوادور', 'SY' => 'سوریه', 'SZ' => 'اسواتینی', 'TA' => 'تریستان دا کونا',
		'TC' => 'جزایر تورکس و کایکوس', 'TD' => 'چاد', 'TF' => 'سرزمین‌های جنوبی فرانسه', 'TG' => 'توگو',
		'TH' => 'تایلند', 'TJ' => 'تاجیکستان', 'TK' => 'توکلائو', 'TL' => 'تیمور شرقی',
		'TM' => 'ترکمنستان', 'TN' => 'تونس', 'TO' => 'تونگا', 'TR' => 'ترکیه',
		'TT' => 'ترینیداد و توباگو', 'TV' => 'تووالو', 'TW' => 'تایوان', 'TZ' => 'تانزانیا',
		'UA' => 'اوکراین', 'UG' => 'اوگاندا', 'UM' => 'جزایر کوچک حاشیه‌ای ایالات متحده', 'US' => 'ایالات متحده آمریکا',
		'UY' => 'اروگوئه', 'UZ' => 'ازبکستان', 'VA' => 'واتیکان', 'VC' => 'سنت وینسنت و گرنادین‌ها',
		'VE' => 'ونزوئلا', 'VG' => 'جزایر ویرجین بریتانیا', 'VI' => 'جزایر ویرجین آمریکا', 'VN' => 'ویتنام',
		'VU' => 'وانواتو', 'WF' => 'والیس و فوتونا', 'WS' => 'ساموآ', 'XK' => 'کوزوو',
		'YE' => 'یمن', 'YT' => 'مایوت', 'ZA' => 'آفریقای جنوبی', 'ZM' => 'زامبیا',
		'ZW' => 'زیمبابوه',
	];

	/**
	 * Localize Countries
	 *
	 * Applies COUNTRY_NAME_FA to every country's Persian-language row in
	 * `oc_country_description` (matched by ISO 3166-1 alpha-2 code, so it
	 * doesn't depend on country_id numbering staying stable), and — as a
	 * separate, narrower fix requested on its own — renames Iran's
	 * English-language row from the stock OpenCart seed's official ISO
	 * name ("Iran (Islamic Republic of)") to the plain "Iran" used
	 * everywhere else English country names appear on this site. Every
	 * dropdown across the storefront and admin (checkout, customer
	 * addresses, tax zones, shipping/geo zones, product/store country
	 * pickers, ...) reads from this same table, so a single update here
	 * is what actually reaches "every dropdown on the site" — there is no
	 * separate per-page copy of the country list to fix.
	 *
	 * Safe to run more than once (each row is just set to its target
	 * value again); not exposed in any menu since it's a one-time data
	 * fix, not an ongoing admin feature — see system_update.php's
	 * localizeCountries() for how it's actually triggered.
	 *
	 * @return array<string, int>
	 */
	public function localizeCountries(): array {
		$fa_language_id = 0;
		$en_language_id = 0;

		$query = $this->db->query("SELECT `language_id`, `code` FROM `" . DB_PREFIX . "language`");

		foreach ($query->rows as $row) {
			if ($row['code'] === 'fa') {
				$fa_language_id = (int)$row['language_id'];
			}

			if ($row['code'] === 'en-gb') {
				$en_language_id = (int)$row['language_id'];
			}
		}

		$updated_fa = 0;
		$updated_en = 0;

		if ($fa_language_id) {
			$countries = $this->db->query("SELECT `country_id`, `iso_code_2` FROM `" . DB_PREFIX . "country`");

			foreach ($countries->rows as $country) {
				$iso = $country['iso_code_2'];

				if (!isset(self::COUNTRY_NAME_FA[$iso])) {
					continue;
				}

				$name = self::COUNTRY_NAME_FA[$iso];

				$this->db->query(
					"UPDATE `" . DB_PREFIX . "country_description` SET `name` = '" . $this->db->escape($name) . "'"
					. " WHERE `country_id` = '" . (int)$country['country_id'] . "' AND `language_id` = '" . $fa_language_id . "'"
				);

				$updated_fa += $this->db->countAffected();
			}
		}

		if ($en_language_id) {
			$this->db->query(
				"UPDATE `" . DB_PREFIX . "country_description` `cd`"
				. " INNER JOIN `" . DB_PREFIX . "country` `c` ON (`c`.`country_id` = `cd`.`country_id`)"
				. " SET `cd`.`name` = 'Iran'"
				. " WHERE `c`.`iso_code_2` = 'IR' AND `cd`.`language_id` = '" . $en_language_id . "'"
			);

			$updated_en = $this->db->countAffected();
		}

		// The storefront caches country lookups (catalog/model/localisation/
		// country.php) — without this, a shopper's already-cached checkout
		// country list would keep showing the old names until that cache
		// entry happened to expire on its own.
		$this->clearCache();

		return [
			'updated_fa' => $updated_fa,
			'updated_en' => $updated_en,
			'fa_language_id' => $fa_language_id,
			'en_language_id' => $en_language_id,
		];
	}

	/**
	 * Get Settings
	 *
	 * @return array<string, string>
	 */
	public function getSettings(): array {
		$this->load->model('setting/setting');

		$setting = $this->model_setting_setting->getSetting('system_update');

		return [
			'repo'           => $setting['system_update_repo'] ?? '',
			'branch'         => $setting['system_update_branch'] ?? 'main',
			'token'          => $setting['system_update_token'] ?? '',
			'admin_dir'      => $setting['system_update_admin_dir'] ?? 'admin',
			'current_commit' => $setting['system_update_current_commit'] ?? '',
			'applied_at'     => $setting['system_update_applied_at'] ?? '',
		];
	}

	/**
	 * Save Settings
	 *
	 * @param string $repo
	 * @param string $branch
	 * @param string $token
	 * @param string $admin_dir The local folder name the "admin" app actually
	 *                          lives in on this install — lets a renamed
	 *                          admin folder (a common security hardening
	 *                          step) still receive future updates in place,
	 *                          instead of a fresh "admin/" folder being
	 *                          recreated alongside it.
	 *
	 * @return void
	 */
	public function saveSettings(string $repo, string $branch, string $token, string $admin_dir = 'admin'): void {
		$this->load->model('setting/setting');

		$existing = $this->model_setting_setting->getSetting('system_update');

		$data = [
			'system_update_repo'            => $repo,
			'system_update_branch'          => $branch ?: 'main',
			'system_update_token'           => $token,
			'system_update_admin_dir'       => trim($admin_dir, '/') ?: 'admin',
			'system_update_current_commit'  => $existing['system_update_current_commit'] ?? '',
			'system_update_applied_at'      => $existing['system_update_applied_at'] ?? '',
		];

		$this->model_setting_setting->editSetting('system_update', $data);

		// Keep the ".admin_dir" marker file in sync — it's how the
		// standalone "/pos" quicklink entry point at the site root (see
		// pos/index.php) knows which folder the admin app actually lives
		// in, without needing any database access of its own. Best-effort:
		// if this write fails (permissions), the marker just falls back to
		// its previous value / the "admin" default, which only matters for
		// that one shortcut URL, not for the admin panel itself.
		@file_put_contents(DIR_OPENCART . '.admin_dir', $data['system_update_admin_dir']);
	}

	/**
	 * Set Baseline
	 *
	 * Records a commit SHA as "current" without downloading anything. Used
	 * the first time this feature runs on an install whose files already
	 * match the repository (e.g. right after the initial push).
	 *
	 * @param string $sha
	 *
	 * @return void
	 */
	public function setBaseline(string $sha): void {
		$this->load->model('setting/setting');

		$this->model_setting_setting->editValue('system_update', 'system_update_current_commit', $sha);
		$this->model_setting_setting->editValue('system_update', 'system_update_applied_at', date('Y-m-d H:i:s'));
	}

	/**
	 * Get Local Version
	 *
	 * The version string of the code currently on disk, read from the
	 * VERSION file at the root of the install. This file is just another
	 * file tracked in the repository, so applying an update or restoring a
	 * backup keeps it in sync automatically — there's no separate setting
	 * to maintain or fall out of date.
	 *
	 * @return string
	 */
	public function getLocalVersion(): string {
		$file = DIR_OPENCART . 'VERSION';

		if (!is_file($file)) {
			return '';
		}

		return trim((string)file_get_contents($file));
	}

	/**
	 * Get Remote Version
	 *
	 * The version string from the VERSION file on the configured branch of
	 * the repository, fetched on its own without downloading anything else.
	 * Returns '' (not an error) when the repository has no VERSION file (or
	 * not yet on this branch), so an older repo state never blocks the rest
	 * of the check — the commit-based comparison still works either way.
	 *
	 * @param string $repo
	 * @param string $token
	 * @param string $branch
	 *
	 * @return string
	 */
	private function getRemoteVersion(string $repo, string $token, string $branch): string {
		$result = $this->githubRequest('https://api.github.com/repos/' . $repo . '/contents/VERSION?ref=' . rawurlencode($branch), $token);

		if (isset($result['error']) || !isset($result['data']['content'])) {
			return '';
		}

		return trim((string)base64_decode(str_replace("\n", '', $result['data']['content'])));
	}

	/**
	 * Get Remote Version Date
	 *
	 * When the VERSION file on the configured branch was last changed —
	 * i.e. when the version currently published in the repository was
	 * actually released, as opposed to the branch tip's commit date (which
	 * could be a later, unrelated commit that didn't touch VERSION at
	 * all). Returns '' when this can't be determined (no VERSION file on
	 * this branch, request failure, etc.) rather than treating it as a
	 * hard error — the rest of the check still works without it.
	 *
	 * @param string $repo
	 * @param string $token
	 * @param string $branch
	 *
	 * @return string
	 */
	private function getRemoteVersionDate(string $repo, string $token, string $branch): string {
		$result = $this->githubRequest('https://api.github.com/repos/' . $repo . '/commits?path=VERSION&sha=' . rawurlencode($branch) . '&per_page=1', $token);

		if (isset($result['error']) || !isset($result['data'][0]['commit']['author']['date'])) {
			return '';
		}

		return (string)$result['data'][0]['commit']['author']['date'];
	}

	/**
	 * Check For Update
	 *
	 * When a baseline is already recorded and a newer commit exists, also
	 * fetches the list of what's changing (the changelog) so the admin can
	 * see it before approving an update. Prefers the repository's own
	 * CHANGELOG.json — structured, bilingual release notes (one entry per
	 * version, each with a "fa" and an "en" string) so this list can switch
	 * language with the rest of the admin interface, the same way every
	 * other piece of text in the panel already does. Raw git commit
	 * messages are always English and often too technical for an end
	 * customer, so they're only used as a fallback when CHANGELOG.json is
	 * missing (e.g. an older repo state, or a version bumped without a
	 * changelog entry).
	 *
	 * @return array<string, mixed>
	 */
	public function checkForUpdate(): array {
		$settings = $this->getSettings();

		if (!$settings['repo'] || !$settings['token']) {
			return ['error' => 'not_configured'];
		}

		$result = $this->githubRequest('https://api.github.com/repos/' . $settings['repo'] . '/commits/' . rawurlencode($settings['branch']), $settings['token']);

		if (isset($result['error'])) {
			return $result;
		}

		$data = $result['data'];

		$response = [
			'current_commit'    => $settings['current_commit'],
			'latest_commit'     => $data['sha'] ?? '',
			'current_version'   => $this->getLocalVersion(),
			'latest_version'    => $this->getRemoteVersion($settings['repo'], $settings['token'], $settings['branch']),
			// The date the VERSION file itself was last changed on the
			// branch — i.e. when the version above was actually released —
			// not just the branch tip's commit date, which can be a later,
			// unrelated commit (a hotfix, a doc change, ...) that never
			// touched VERSION at all.
			'latest_version_date' => $this->getRemoteVersionDate($settings['repo'], $settings['token'], $settings['branch']),
			'message'         => $data['commit']['message'] ?? '',
			'date'            => $data['commit']['author']['date'] ?? '',
			'author'          => $data['commit']['author']['name'] ?? '',
			'applied_at'      => $settings['applied_at'],
			'changelog'       => [],
		];

		$response['changelog'] = $this->getReleaseNotes($settings['repo'], $settings['token'], $settings['branch'], $response['current_version']);

		if (!$response['changelog'] && $settings['current_commit'] && $settings['current_commit'] !== $response['latest_commit']) {
			$compare = $this->compareCommits($settings['repo'], $settings['token'], $settings['current_commit'], $response['latest_commit']);

			if (!isset($compare['error'])) {
				$response['changelog'] = $compare['commits'];
			}
		}

		return $response;
	}

	/**
	 * Get Release Notes
	 *
	 * Structured, bilingual changelog entries (from CHANGELOG.json on the
	 * configured branch) that are newer than $current_version — i.e. what
	 * an update from here would actually add. Each entry looks like
	 * {"version": "5.1.0", "fa": "...", "en": "..."}. Returns [] (not an
	 * error) whenever this file doesn't exist, fails to parse, or every
	 * entry in it is already applied — the caller falls back to the raw
	 * git commit log in that case, so a repo without this file yet still
	 * shows something.
	 *
	 * @param string $repo
	 * @param string $token
	 * @param string $branch
	 * @param string $current_version
	 *
	 * @return array<int, array<string, string>>
	 */
	private function getReleaseNotes(string $repo, string $token, string $branch, string $current_version): array {
		$entries = $this->getRemoteChangelog($repo, $token, $branch);

		if (!$entries) {
			return [];
		}

		$filtered = [];

		foreach ($entries as $entry) {
			// Skip anything already applied (or older). An empty local
			// VERSION file (never set) means we can't compare, so show
			// everything rather than hide it all.
			if ($current_version !== '' && version_compare($entry['version'], $current_version, '<=')) {
				continue;
			}

			$filtered[] = $entry;
		}

		usort($filtered, function (array $a, array $b): int {
			return version_compare($b['version'], $a['version']);
		});

		return $filtered;
	}

	/**
	 * Get Remote Changelog
	 *
	 * Parses CHANGELOG.json from the configured branch into a plain array
	 * of ['version' => ..., 'fa' => ..., 'en' => ...] entries. Returns []
	 * (not an error) when the file is missing or malformed, matching
	 * getRemoteVersion()'s "absence is not a hard failure" behaviour —
	 * an older repo state without this file must never block the rest of
	 * the update check.
	 *
	 * @param string $repo
	 * @param string $token
	 * @param string $branch
	 *
	 * @return array<int, array<string, string>>
	 */
	private function getRemoteChangelog(string $repo, string $token, string $branch): array {
		$result = $this->githubRequest('https://api.github.com/repos/' . $repo . '/contents/CHANGELOG.json?ref=' . rawurlencode($branch), $token);

		if (isset($result['error']) || !isset($result['data']['content'])) {
			return [];
		}

		$json = base64_decode(str_replace("\n", '', (string)$result['data']['content']));
		$decoded = json_decode((string)$json, true);

		if (!is_array($decoded)) {
			return [];
		}

		$entries = [];

		foreach ($decoded as $entry) {
			if (!is_array($entry) || empty($entry['version'])) {
				continue;
			}

			$entries[] = [
				'version' => (string)$entry['version'],
				'fa'      => (string)($entry['fa'] ?? ''),
				'en'      => (string)($entry['en'] ?? ''),
			];
		}

		return $entries;
	}

	/**
	 * Compare Commits
	 *
	 * The list of commits between $base (exclusive) and $head (inclusive),
	 * newest first — i.e. what an update from $base to $head actually
	 * contains.
	 *
	 * @param string $repo
	 * @param string $token
	 * @param string $base
	 * @param string $head
	 *
	 * @return array<string, mixed>
	 */
	public function compareCommits(string $repo, string $token, string $base, string $head): array {
		$result = $this->githubRequest('https://api.github.com/repos/' . $repo . '/compare/' . rawurlencode($base) . '...' . rawurlencode($head), $token);

		if (isset($result['error'])) {
			return $result;
		}

		$commits = [];

		foreach (($result['data']['commits'] ?? []) as $commit) {
			$commits[] = [
				'sha'     => $commit['sha'] ?? '',
				'message' => $commit['commit']['message'] ?? '',
				'author'  => $commit['commit']['author']['name'] ?? '',
				'date'    => $commit['commit']['author']['date'] ?? '',
			];
		}

		// GitHub returns oldest-first; show newest-first to match the rest of the page.
		return ['commits' => array_reverse($commits)];
	}

	/**
	 * Apply Update
	 *
	 * Takes a full backup (database + files), then downloads the configured
	 * branch as a zip, extracts it and copies its contents over
	 * DIR_OPENCART, skipping EXCLUDE_PATHS. Records the new commit as
	 * current on success. If the backup itself fails, the update is not
	 * attempted — a pre-update backup that didn't happen is not worth the
	 * risk of an update with no way back.
	 *
	 * @return array<string, mixed>
	 */
	public function applyUpdate(): array {
		$settings = $this->getSettings();

		if (!$settings['repo'] || !$settings['token']) {
			return ['error' => 'not_configured'];
		}

		if (!class_exists('ZipArchive')) {
			return ['error' => 'zip_extension'];
		}

		@set_time_limit(0);
		@ini_set('memory_limit', '512M');

		// Resolve the exact commit we're about to apply, so the recorded
		// "current_commit" always reflects the code actually written to disk.
		$commit_result = $this->githubRequest('https://api.github.com/repos/' . $settings['repo'] . '/commits/' . rawurlencode($settings['branch']), $settings['token']);

		if (isset($commit_result['error'])) {
			return $commit_result;
		}

		$sha = $commit_result['data']['sha'] ?? '';

		if (!$sha) {
			return ['error' => 'download'];
		}

		$backup = $this->createBackup('پیش از بروزرسانی به ' . substr($sha, 0, 10), $settings['current_commit'], $sha);

		if (isset($backup['error'])) {
			return $backup;
		}

		$tmp_dir = DIR_STORAGE . 'update_tmp/';
		$zip_file = $tmp_dir . 'update.zip';
		$extract_dir = $tmp_dir . 'extracted/';

		$this->prepareTmpDir($tmp_dir, $extract_dir);

		$download = $this->downloadZipball($settings['repo'], $settings['branch'], $settings['token'], $zip_file);

		if (isset($download['error'])) {
			$this->cleanupTmpDir($tmp_dir);

			$download['backup_id'] = $backup['id'];

			return $download;
		}

		$zip = new \ZipArchive();

		if ($zip->open($zip_file) !== true) {
			$this->cleanupTmpDir($tmp_dir);

			return ['error' => 'extract', 'backup_id' => $backup['id']];
		}

		if ($zip->numFiles < 1) {
			$zip->close();
			$this->cleanupTmpDir($tmp_dir);

			return ['error' => 'extract', 'backup_id' => $backup['id']];
		}

		// GitHub zipballs wrap everything in a single top-level folder,
		// e.g. "vmartvmart-MDcart-abcdef1/" — find its name so we copy from
		// inside it rather than copying that wrapper folder itself.
		$first_entry = $zip->getNameIndex(0);
		$root_folder = explode('/', $first_entry)[0];

		$extracted_ok = $zip->extractTo($extract_dir);

		$zip->close();

		if (!$extracted_ok) {
			$this->cleanupTmpDir($tmp_dir);

			return ['error' => 'extract', 'backup_id' => $backup['id']];
		}

		$source_root = $extract_dir . $root_folder . '/';

		if (!is_dir($source_root)) {
			$this->cleanupTmpDir($tmp_dir);

			return ['error' => 'extract', 'backup_id' => $backup['id']];
		}

		$write_failures = [];

		// If the admin folder has been renamed locally (a common security
		// hardening step — see admin_dir in settings), redirect anything the
		// repo ships under "admin/" to that local folder name instead, so a
		// renamed install keeps receiving updates in place rather than a
		// fresh, unused "admin/" folder being recreated next to it.
		$path_remap = [];

		if ($settings['admin_dir'] && $settings['admin_dir'] !== 'admin') {
			$path_remap['admin/'] = $settings['admin_dir'] . '/';
		}

		$this->copyRecursive($source_root, DIR_OPENCART, self::EXCLUDE_PATHS, $write_failures, $path_remap);

		$this->cleanupTmpDir($tmp_dir);

		// New code is on disk — force the framework to recompile templates
		// and drop any stale cached data instead of serving pre-update output.
		$this->clearCache();

		$this->setBaseline($sha);

		if ($write_failures) {
			return [
				'error'          => 'write',
				'applied_commit' => $sha,
				'failed_count'   => count($write_failures),
				'backup_id'      => $backup['id'],
			];
		}

		return ['applied_commit' => $sha, 'backup_id' => $backup['id']];
	}

	/**
	 * Create Backup
	 *
	 * Writes a full database dump and a zip of the site's files (everything
	 * except BACKUP_EXCLUDE_PATHS) into a new timestamped folder under
	 * system/storage/backup/system_update/, then prunes old backups beyond
	 * BACKUP_KEEP. Returns the backup's metadata, or an error if either the
	 * database dump or the files zip could not be produced.
	 *
	 * @param string $reason
	 * @param string $commit_before
	 * @param string $commit_after
	 *
	 * @return array<string, mixed>
	 */
	public function createBackup(string $reason, string $commit_before, string $commit_after): array {
		if (!class_exists('ZipArchive')) {
			return ['error' => 'zip_extension'];
		}

		@set_time_limit(0);

		$backup_id = date('Y-m-d_His');
		$dir = DIR_STORAGE . 'backup/system_update/' . $backup_id . '/';

		if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
			return ['error' => 'backup_failed'];
		}

		$db_file = $dir . 'database.sql';
		$zip_file = $dir . 'files.zip';

		$this->backupDatabase($db_file);

		if (!is_file($db_file) || !filesize($db_file)) {
			return ['error' => 'backup_failed'];
		}

		$this->zipDirectory(DIR_OPENCART, $zip_file, self::BACKUP_EXCLUDE_PATHS);

		if (!is_file($zip_file) || !filesize($zip_file)) {
			return ['error' => 'backup_failed'];
		}

		// Storage is backed up as its own zip since it can live entirely
		// outside DIR_OPENCART (moved out for security — see DIR_STORAGE).
		// Skipped only if DIR_STORAGE itself doesn't exist at all, which
		// shouldn't normally happen but is cheap to guard against.
		$storage_zip = $dir . 'storage.zip';
		$storage_size = 0;

		if (is_dir(DIR_STORAGE)) {
			$this->zipDirectory(DIR_STORAGE, $storage_zip, self::STORAGE_BACKUP_EXCLUDE_PATHS);

			if (!is_file($storage_zip) || !filesize($storage_zip)) {
				return ['error' => 'backup_failed'];
			}

			$storage_size = filesize($storage_zip);
		}

		$meta = [
			'id'             => $backup_id,
			'created_at'     => date('Y-m-d H:i:s'),
			'reason'         => $reason,
			'commit_before'  => $commit_before,
			'commit_after'   => $commit_after,
			'db_size'        => filesize($db_file),
			'files_size'     => filesize($zip_file),
			'storage_size'   => $storage_size,
		];

		file_put_contents($dir . 'meta.json', json_encode($meta));

		$this->pruneOldBackups();

		return $meta;
	}

	/**
	 * List Backups
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function listBackups(): array {
		$base = DIR_STORAGE . 'backup/system_update/';

		if (!is_dir($base)) {
			return [];
		}

		$dirs = glob($base . '*', GLOB_ONLYDIR);

		if (!$dirs) {
			return [];
		}

		rsort($dirs);

		$backups = [];

		foreach ($dirs as $dir) {
			$meta_file = $dir . '/meta.json';

			if (is_file($meta_file)) {
				$meta = json_decode((string)file_get_contents($meta_file), true);

				if ($meta) {
					$backups[] = $meta;
				}
			}
		}

		return $backups;
	}

	/**
	 * Restore Backup
	 *
	 * Restores the database (truncate + re-insert every table, same format
	 * the files were dumped in) and extracts the files zip back over
	 * DIR_OPENCART. Does not delete files that didn't exist at backup time
	 * (same "add/overwrite only" limitation as applyUpdate, for the same
	 * reason: never let an automated tool delete something on a live site).
	 *
	 * @param string $backup_id
	 *
	 * @return array<string, mixed>
	 */
	public function restoreBackup(string $backup_id): array {
		// Sanitize: a backup id must only ever be the timestamp-style
		// folder name this class itself generates — never let a path
		// come in through here unescaped.
		$backup_id = basename($backup_id);
		$dir = DIR_STORAGE . 'backup/system_update/' . $backup_id . '/';

		$db_file = $dir . 'database.sql';
		$zip_file = $dir . 'files.zip';

		if (!is_dir($dir) || !is_file($db_file) || !is_file($zip_file)) {
			return ['error' => 'backup_not_found'];
		}

		if (!class_exists('ZipArchive')) {
			return ['error' => 'zip_extension'];
		}

		@set_time_limit(0);

		$zip = new \ZipArchive();

		if ($zip->open($zip_file) !== true) {
			return ['error' => 'extract'];
		}

		$extracted_ok = $zip->extractTo(DIR_OPENCART);

		$zip->close();

		if (!$extracted_ok) {
			return ['error' => 'extract'];
		}

		// Older backups (taken before storage was backed up separately) won't
		// have this file — restoring one just leaves storage untouched rather
		// than failing.
		$storage_zip = $dir . 'storage.zip';

		if (is_file($storage_zip) && is_dir(DIR_STORAGE)) {
			$zip2 = new \ZipArchive();

			if ($zip2->open($storage_zip) === true) {
				$zip2->extractTo(DIR_STORAGE);
				$zip2->close();
			}
		}

		$this->restoreDatabase($db_file);

		$this->clearCache();

		return ['restored_id' => $backup_id];
	}

	/**
	 * Backup Database
	 *
	 * Writes every table as a single-line TRUNCATE followed by single-line
	 * INSERT statements — the same format (and escaping) OpenCart's own
	 * built-in Backup/Restore tool uses, chosen deliberately so restore can
	 * stay a simple, well-understood line-by-line replay instead of a full
	 * SQL parser.
	 *
	 * @param string $file
	 *
	 * @return void
	 */
	private function backupDatabase(string $file): void {
		$this->load->model('tool/backup');

		$handle = fopen($file, 'w');

		if (!$handle) {
			return;
		}

		$tables = $this->model_tool_backup->getTables();

		foreach ($tables as $table) {
			fwrite($handle, 'TRUNCATE TABLE `' . $table . '`;' . "\n");

			$start = 0;
			$limit = 500;

			while (true) {
				$rows = $this->model_tool_backup->getRecords($table, $start, $limit);

				if (!$rows) {
					break;
				}

				foreach ($rows as $row) {
					$fields = '';

					foreach (array_keys($row) as $key) {
						$fields .= '`' . $key . '`, ';
					}

					$values = '';

					foreach (array_values($row) as $value) {
						if ($value !== null) {
							$value = str_replace(['\\', "\x00", "\n", "\r", "\x1a", '\'', '"'], ['\\\\', '\0', '\n', '\r', '\Z', '\\\'', '\"'], $value);
							$values .= '\'' . $value . '\', ';
						} else {
							$values .= 'NULL, ';
						}
					}

					fwrite($handle, 'INSERT INTO `' . $table . '` (' . rtrim($fields, ', ') . ') VALUES (' . rtrim($values, ', ') . ');' . "\n");
				}

				if (count($rows) < $limit) {
					break;
				}

				$start += $limit;
			}

			fwrite($handle, "\n");
		}

		fclose($handle);
	}

	/**
	 * Restore Database
	 *
	 * Replays a dump written by backupDatabase(): every TRUNCATE/INSERT
	 * line is executed as-is. Matches OpenCart's own Backup/Restore tool's
	 * line-based approach.
	 *
	 * @param string $file
	 *
	 * @return void
	 */
	private function restoreDatabase(string $file): void {
		$handle = fopen($file, 'r');

		if (!$handle) {
			return;
		}

		while (!feof($handle)) {
			$line = fgets($handle, 5000000);

			if ($line === false) {
				break;
			}

			if ((substr($line, 0, 14) == 'TRUNCATE TABLE' || substr($line, 0, 11) == 'INSERT INTO') && substr($line, -2) == ";\n") {
				$this->db->query(substr($line, 0, strlen($line) - 2));
			}
		}

		fclose($handle);
	}

	/**
	 * Prune Old Backups
	 *
	 * @return void
	 */
	private function pruneOldBackups(): void {
		$base = DIR_STORAGE . 'backup/system_update/';

		if (!is_dir($base)) {
			return;
		}

		$dirs = glob($base . '*', GLOB_ONLYDIR);

		if (!$dirs) {
			return;
		}

		sort($dirs);

		$excess = count($dirs) - self::BACKUP_KEEP;

		for ($i = 0; $i < $excess; $i++) {
			$this->removeDirRecursive($dirs[$i]);
		}
	}

	/**
	 * GitHub Request
	 *
	 * @param string $url
	 * @param string $token
	 *
	 * @return array<string, mixed>
	 */
	private function githubRequest(string $url, string $token): array {
		if (!function_exists('curl_init')) {
			return ['error' => 'connection', 'detail' => 'cURL is not available on this server.'];
		}

		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: token ' . $token,
			'Accept: application/vnd.github+json',
			'X-GitHub-Api-Version: 2022-11-28',
			'User-Agent: MDcart-System-Update',
		]);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($ch);

		curl_close($ch);

		if ($response === false) {
			return ['error' => 'connection', 'detail' => $curl_error];
		}

		$data = json_decode($response, true);

		if ($http_code < 200 || $http_code >= 300) {
			return ['error' => 'api', 'detail' => is_array($data) && isset($data['message']) ? $data['message'] : ('HTTP ' . $http_code)];
		}

		return ['data' => $data];
	}

	/**
	 * Download Zipball
	 *
	 * @param string $repo
	 * @param string $branch
	 * @param string $token
	 * @param string $destination
	 *
	 * @return array<string, mixed>
	 */
	private function downloadZipball(string $repo, string $branch, string $token, string $destination): array {
		if (!function_exists('curl_init')) {
			return ['error' => 'connection', 'detail' => 'cURL is not available on this server.'];
		}

		$fp = fopen($destination, 'w');

		if (!$fp) {
			return ['error' => 'write'];
		}

		$url = 'https://api.github.com/repos/' . $repo . '/zipball/' . rawurlencode($branch);

		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_UNRESTRICTED_AUTH, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: token ' . $token,
			'Accept: application/vnd.github+json',
			'X-GitHub-Api-Version: 2022-11-28',
			'User-Agent: MDcart-System-Update',
		]);

		$success = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($ch);

		curl_close($ch);
		fclose($fp);

		if (!$success || $http_code < 200 || $http_code >= 300 || !is_file($destination) || filesize($destination) < 1) {
			return ['error' => 'download', 'detail' => $curl_error ?: ('HTTP ' . $http_code)];
		}

		return ['ok' => true];
	}

	/**
	 * Copy Recursive
	 *
	 * Copies every file under $source into $destination, skipping any
	 * relative path that starts with (or exactly matches) one of $exclude.
	 *
	 * @param string                $source
	 * @param string                $destination
	 * @param array<string>         $exclude
	 * @param array<string>         $failures
	 * @param array<string, string> $path_remap Optional map of source-relative
	 *                                          prefix => local prefix (e.g.
	 *                                          ['admin/' => 'panel/']) applied
	 *                                          only to where a file is WRITTEN
	 *                                          — $exclude is still matched
	 *                                          against the original, repo-side
	 *                                          relative path.
	 *
	 * @return void
	 */
	private function copyRecursive(string $source, string $destination, array $exclude, array &$failures, array $path_remap = []): void {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $item) {
			$relative = substr($item->getPathname(), strlen($source));
			$relative = str_replace('\\', '/', $relative);

			if ($this->matchesPath($relative, $exclude)) {
				continue;
			}

			$local_relative = $relative;

			foreach ($path_remap as $from => $to) {
				if (strpos($relative, $from) === 0) {
					$local_relative = $to . substr($relative, strlen($from));
					break;
				}
			}

			$target = $destination . $local_relative;

			if ($item->isDir()) {
				if (!is_dir($target)) {
					@mkdir($target, 0755, true);
				}
			} else {
				$target_dir = dirname($target);

				if (!is_dir($target_dir)) {
					@mkdir($target_dir, 0755, true);
				}

				if (!@copy($item->getPathname(), $target)) {
					$failures[] = $relative;
				}
			}
		}
	}

	/**
	 * Zip Directory
	 *
	 * Zips every file under $source (paths relative to $source, forward
	 * slashes, no wrapper folder) into $zip_path, skipping anything
	 * matching $exclude.
	 *
	 * @param string        $source
	 * @param string        $zip_path
	 * @param array<string> $exclude
	 *
	 * @return void
	 */
	private function zipDirectory(string $source, string $zip_path, array $exclude): void {
		$zip = new \ZipArchive();

		if ($zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $item) {
			$relative = substr($item->getPathname(), strlen($source));
			$relative = str_replace('\\', '/', $relative);

			if ($this->matchesPath($relative, $exclude)) {
				continue;
			}

			if ($item->isDir()) {
				$zip->addEmptyDir($relative);
			} else {
				$zip->addFile($item->getPathname(), $relative);
			}
		}

		$zip->close();
	}

	/**
	 * Matches Path
	 *
	 * @param string        $relative_path
	 * @param array<string> $patterns
	 *
	 * @return bool
	 */
	private function matchesPath(string $relative_path, array $patterns): bool {
		foreach ($patterns as $pattern) {
			if ($pattern === $relative_path) {
				return true;
			}

			if (str_ends_with($pattern, '/') && str_starts_with($relative_path . '/', $pattern)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Clear Cache
	 *
	 * Recursively empties DIR_CACHE (keeping the placeholder files) so
	 * compiled Twig templates and other cached output — which can live in
	 * sub-folders, not just the top level — never linger stale after an
	 * update or restore replaces the code that generated them.
	 *
	 * @return void
	 */
	private function clearCache(): void {
		if (!is_dir(DIR_CACHE)) {
			return;
		}

		$this->emptyDirRecursive(DIR_CACHE, ['index.html', '.htaccess']);
	}

	/**
	 * Prepare Tmp Dir
	 *
	 * @param string $tmp_dir
	 * @param string $extract_dir
	 *
	 * @return void
	 */
	private function prepareTmpDir(string $tmp_dir, string $extract_dir): void {
		$this->cleanupTmpDir($tmp_dir);

		@mkdir($tmp_dir, 0755, true);
		@mkdir($extract_dir, 0755, true);
	}

	/**
	 * Cleanup Tmp Dir
	 *
	 * @param string $tmp_dir
	 *
	 * @return void
	 */
	private function cleanupTmpDir(string $tmp_dir): void {
		$this->removeDirRecursive($tmp_dir);
	}

	/**
	 * Empty Dir Recursive
	 *
	 * Deletes every file/folder inside $dir except the given basenames,
	 * leaving $dir itself in place.
	 *
	 * @param string        $dir
	 * @param array<string> $keep_basenames
	 *
	 * @return void
	 */
	private function emptyDirRecursive(string $dir, array $keep_basenames = []): void {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($iterator as $item) {
			if (in_array($item->getBasename(), $keep_basenames, true)) {
				continue;
			}

			if ($item->isDir()) {
				@rmdir($item->getPathname());
			} else {
				@unlink($item->getPathname());
			}
		}
	}

	/**
	 * Remove Dir Recursive
	 *
	 * Deletes $dir and everything inside it.
	 *
	 * @param string $dir
	 *
	 * @return void
	 */
	private function removeDirRecursive(string $dir): void {
		if (!is_dir($dir)) {
			return;
		}

		$this->emptyDirRecursive($dir);

		@rmdir($dir);
	}
}
