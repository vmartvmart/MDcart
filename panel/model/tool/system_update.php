<?php
namespace MDcart\Admin\Model\Tool;
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
 * @package MDcart\Admin\Model\Tool
 */
class SystemUpdate extends \MDcart\System\Engine\Model {
	/**
	 * Paths (relative to MCART_ROOT, forward slashes) that an update must
	 * never touch. Kept in sync with the repository's own .gitignore.
	 */
	private const EXCLUDE_PATHS = [
		'config.php',
		'panel/config.php',
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
	 * DIR_STORAGE itself rather than MCART_ROOT — storage can live
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
	 * Path (under DIR_STORAGE, NOT inside update_tmp/) of the small JSON
	 * file that records apply-update progress in real time, so a separate,
	 * fast polling request can report live status to the admin while the
	 * one long-running request that's actually doing the work (backup +
	 * download + extract + copy) is still in flight. Deliberately kept
	 * outside update_tmp/ — that directory is wiped and recreated partway
	 * through applyUpdate() (see prepareTmpDir()/cleanupTmpDir()), which
	 * would otherwise delete this file out from under a request reading it.
	 * Session storage here is the "db" engine (system/library/session/db.php)
	 * with no row locking, so the concurrent poll never has to wait behind
	 * the long request either — this file is the only coordination needed
	 * between the two.
	 */
	private const PROGRESS_FILE = 'system_update_progress.json';

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
	 * languages (fa/us, formerly fa/en-gb before the English language code
	 * was renamed) — see localizeCountries() below, which is what
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

			// Match both the current code ("us") and the old pre-rename
			// code ("en-gb") so this still works on installs that haven't
			// applied the English-language code rename yet.
			if ($row['code'] === 'us' || $row['code'] === 'en-gb') {
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
	 * Get Migrations
	 *
	 * Every one-time database change a past code update has needed, keyed
	 * by a short, permanent, never-reused identifier. runMigrations() (see
	 * below) runs each of these exactly once per install, automatically,
	 * as the last step of applyUpdate() — so a feature that needs a small
	 * DB change (a new permission, a settings tweak, ...) just adds an
	 * entry here instead of asking the admin to run SQL by hand through
	 * phpMyAdmin. That matters because installs are free to choose their
	 * own table prefix at setup (this one uses "mc_", not the "oc_" from
	 * OpenCart's own examples) — a hand-run SQL script has to hardcode one
	 * prefix and guess wrong on any install that picked another, whereas
	 * everything here goes through $this->db with DB_PREFIX like the rest
	 * of the codebase, so it's correct on every install automatically.
	 *
	 * Each closure must be safe to run more than once (defense in depth —
	 * runMigrations() already skips a key it has recorded as applied, but
	 * a closure shouldn't corrupt anything if that record is ever lost or
	 * reset) and must not throw for an already-satisfied condition.
	 *
	 * @return array<string, callable>
	 */
	private function getMigrations(): array {
		return [
			// Design > Colors (design/color) was added after this project's
			// install_starter.sql seed had already been written into
			// existing sites, so every install that predates that feature
			// needs its Administrator group's permission blob patched to
			// include the new route — otherwise the menu item silently
			// never appears (see column_left.php's hasPermission() gate)
			// and saving the page 403s even if reached directly by URL.
			'design_color_permission' => function (): void {
				$this->grantAdministratorPermissions(['design/color']);
			},

			// Multi-warehouse (catalog/warehouse, catalog/warehouse_transfer),
			// purchase invoices (catalog/purchase_invoice), the in-person POS
			// screen (sale/pos), and the accounting module (accounting/*) were
			// all built and wired into the menu (column_left.php) well before
			// this specific install's user-group permissions were last synced
			// from install_starter.sql, so every one of these pages has been
			// sitting fully working but completely invisible — same root
			// cause as design/color above, just for a whole batch of pages
			// at once instead of one.
			'warehouse_pos_accounting_permissions' => function (): void {
				$this->grantAdministratorPermissions([
					'catalog/warehouse',
					'catalog/warehouse_transfer',
					'catalog/purchase_invoice',
					'sale/pos',
					'accounting/account',
					'accounting/bank_account',
					'accounting/journal',
					'accounting/report',
					'accounting/exchange_rate',
				]);
			},

			// The IPPanel SMS, Bale, Telegram, WhatsApp Cloud API, and
			// "back in stock" notification extensions, plus the Torob feed
			// and Iranian payment gateways (ZarinPal, PayPing, IDPay,
			// SizPay, card-to-card), are all fully built — each with its
			// own admin settings page for entering that install's own API
			// key/bot token — but OpenCart only shows an extension's
			// settings page (and only fires its notification event) once
			// three seed rows exist: an `extension` row (what it provides),
			// an `extension_install` row (the installed "package" entry
			// shown in Extensions > Installer), and — for the four order
			// notifiers plus restock alerts — an `event` row wiring it to
			// actually fire. An install whose oc_extension/oc_extension_install/
			// oc_event tables predate any of these ships the code but never
			// gets any of the three rows, so the extension is unreachable
			// even after this migration grants menu permissions elsewhere.
			// Every check below is by the extension's own natural key
			// (there's no unique index on these columns), so this is safe
			// to run on an install that already has some or all of them.
			'notification_and_payment_extensions' => function (): void {
				$this->grantAdministratorPermissions([
					'extension/iranian_gateways/payment/card_to_card',
					'extension/iranian_gateways/payment/idpay',
					'extension/iranian_gateways/payment/payping',
					'extension/iranian_gateways/payment/sizpay',
					'extension/iranian_gateways/payment/zarinpal',
					'extension/torob/feed/torob',
					'extension/ippanel/other/ippanel',
					'extension/bale/other/bale',
					'extension/stock_alert/other/stock_alert',
					'extension/telegram/other/telegram',
					'extension/whatsapp/other/whatsapp',
				]);

				$extensions = [
					['iranian_gateways', 'payment', 'card_to_card'],
					['iranian_gateways', 'payment', 'idpay'],
					['iranian_gateways', 'payment', 'payping'],
					['iranian_gateways', 'payment', 'sizpay'],
					['iranian_gateways', 'payment', 'zarinpal'],
					['torob', 'feed', 'torob'],
					['ippanel', 'other', 'ippanel'],
					['bale', 'other', 'bale'],
					['stock_alert', 'other', 'stock_alert'],
					['telegram', 'other', 'telegram'],
					['whatsapp', 'other', 'whatsapp'],
				];

				foreach ($extensions as [$extension, $type, $code]) {
					$query = $this->db->query(
						"SELECT `extension_id` FROM `" . DB_PREFIX . "extension`"
						. " WHERE `extension` = '" . $this->db->escape($extension) . "' AND `type` = '" . $this->db->escape($type) . "' AND `code` = '" . $this->db->escape($code) . "'"
					);

					if (!$query->num_rows) {
						$this->db->query(
							"INSERT INTO `" . DB_PREFIX . "extension` SET `extension` = '" . $this->db->escape($extension) . "', `type` = '" . $this->db->escape($type) . "', `code` = '" . $this->db->escape($code) . "'"
						);
					}
				}

				// (name, description, code, version, status)
				$installs = [
					['Torob Product Feed', 'Authenticated JSON product feed for Torob\'s crawler (torob.com), mirroring the contract used by Torob\'s official WooCommerce plugin.', 'torob', '1.0', 1],
					['Iranian Payment Gateways', 'ZarinPal, PayPing, IDPay, SizPay and card-to-card payment methods for Iranian stores.', 'iranian_gateways', '1.0', 1],
					['IPPanel SMS', 'Sends order/customer SMS notifications through the IPPanel (edge.ippanel.com) REST API.', 'ippanel', '1.0', 0],
					['Bale Bot Notifications', 'Sends order/admin notifications through a Bale (ble.ir) messenger bot.', 'bale', '1.0', 0],
					['Back in Stock Alerts', 'Lets customers ask to be notified (SMS/WhatsApp/Telegram/Bale) when an out-of-stock product becomes available again.', 'stock_alert', '1.0', 0],
					['Telegram Bot Notifications', 'Sends order/admin notifications through a Telegram bot (api.telegram.org).', 'telegram', '1.0', 0],
					['WhatsApp Cloud API Notifications', 'Sends order/admin notifications through Meta\'s official WhatsApp Cloud API using pre-approved message templates.', 'whatsapp', '1.0', 0],
				];

				foreach ($installs as [$name, $description, $code, $version, $status]) {
					$query = $this->db->query("SELECT `extension_install_id` FROM `" . DB_PREFIX . "extension_install` WHERE `code` = '" . $this->db->escape($code) . "'");

					if (!$query->num_rows) {
						$this->db->query(
							"INSERT INTO `" . DB_PREFIX . "extension_install` SET `extension_id` = '0', `extension_download_id` = '0',"
							. " `name` = '" . $this->db->escape($name) . "', `description` = '" . $this->db->escape($description) . "',"
							. " `code` = '" . $this->db->escape($code) . "', `version` = '" . $this->db->escape($version) . "',"
							. " `author` = '', `link` = '', `status` = '" . (int)$status . "', `date_added` = NOW()"
						);
					}
				}

				// (code, description, trigger, action)
				$events = [
					['ippanel_order', 'Sends IPPanel SMS notifications on new orders and order status changes.', 'model/checkout/order.addHistory/before', 'extension/ippanel/event/order'],
					['bale_order', 'Sends Bale notifications on new orders and order status changes.', 'model/checkout/order.addHistory/before', 'extension/bale/event/order'],
					['stock_alert_restock', 'Notifies subscribed customers when a product is restocked.', 'model/catalog/product.editProduct/after', 'extension/stock_alert/event/restock'],
					['telegram_order', 'Sends Telegram notifications on new orders and order status changes.', 'model/checkout/order.addHistory/before', 'extension/telegram/event/order'],
					['whatsapp_order', 'Sends WhatsApp notifications on new orders and order status changes.', 'model/checkout/order.addHistory/before', 'extension/whatsapp/event/order'],
				];

				foreach ($events as [$code, $description, $trigger, $action]) {
					$query = $this->db->query("SELECT `event_id` FROM `" . DB_PREFIX . "event` WHERE `code` = '" . $this->db->escape($code) . "'");

					if (!$query->num_rows) {
						$this->db->query(
							"INSERT INTO `" . DB_PREFIX . "event` SET `code` = '" . $this->db->escape($code) . "', `description` = '" . $this->db->escape($description) . "',"
							. " `trigger` = '" . $this->db->escape($trigger) . "', `action` = '" . $this->db->escape($action) . "', `status` = '1', `sort_order` = '1'"
						);
					}
				}
			},

			// Persian's display name in the language list changed from the
			// English gloss "Persian" to its own native name "فارسی" (matching
			// English's own name already being "English" rather than a
			// translation) after install_starter.sql's seed had already been
			// written into existing sites, so an install that seeded its
			// oc_language table before that change still shows "Persian".
			'persian_language_name' => function (): void {
				$this->db->query("UPDATE `" . DB_PREFIX . "language` SET `name` = 'فارسی' WHERE `code` = 'fa' AND `name` = 'Persian'");
			},

			// The 'stock_alert_restock' row inserted by notification_and_payment_extensions
			// above (and by install_starter.sql, on any install that ran it before this fix)
			// carried a wrong trigger string with a spurious "admin/" prefix that never
			// matches anything the framework actually fires (see Event::trigger() /
			// Loader::callback() — the fired string is always just 'model/' . <the route
			// passed to $this->load->model()> . '/after', with no "admin/" segment), so the
			// back-in-stock notification silently never sent on any install that already
			// has this row. This retroactively corrects the already-inserted data; new
			// installs get the correct string directly (see install_starter.sql and the
			// migration above).
			'fix_stock_alert_trigger' => function (): void {
				$this->db->query("UPDATE `" . DB_PREFIX . "event` SET `trigger` = 'model/catalog/product.editProduct/after' WHERE `code` = 'stock_alert_restock' AND `trigger` != 'model/catalog/product.editProduct/after'");
			},

			// Auto-translation (extension/auto_translate): fills in a product/category's
			// empty fa/en name, description, tags and SEO meta fields by translating from
			// whichever language was actually entered, via the Anthropic Claude API — same
			// "grant permission + seed extension/extension_install/event rows" pattern as
			// notification_and_payment_extensions above, for the same reason (this is a
			// brand new feature, so every already-deployed install predates its seed data
			// by definition).
			// New Suppliers screen (accounting/supplier) - needs its own table (new
			// feature, so no install has it yet) plus the Administrator permission,
			// same CREATE TABLE IF NOT EXISTS approach runMigrations() itself uses
			// for its own tracking table, so this is safe whether or not
			// install_starter.sql's copy of this table already ran on this install.
			'supplier_table_and_permission' => function (): void {
				$this->db->query(
					"CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "supplier` ("
					. "`supplier_id` int(11) NOT NULL AUTO_INCREMENT,"
					. "`name` varchar(128) NOT NULL,"
					. "`telephone` varchar(32) NOT NULL DEFAULT '',"
					. "`email` varchar(96) NOT NULL DEFAULT '',"
					. "`address` varchar(255) NOT NULL DEFAULT '',"
					. "`tax_id` varchar(64) NOT NULL DEFAULT '',"
					. "`currency_code` varchar(3) NOT NULL DEFAULT '',"
					. "`status` tinyint(1) NOT NULL DEFAULT 1,"
					. "`sort_order` int(11) NOT NULL DEFAULT 0,"
					. "`date_added` datetime NOT NULL,"
					. "`date_modified` datetime NOT NULL,"
					. "PRIMARY KEY (`supplier_id`)"
					. ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
				);

				$this->grantAdministratorPermissions(['accounting/supplier']);
			},

			'auto_translate_extension' => function (): void {
				$this->grantAdministratorPermissions(['extension/auto_translate/other/auto_translate']);

				$query = $this->db->query(
					"SELECT `extension_id` FROM `" . DB_PREFIX . "extension`"
					. " WHERE `extension` = 'auto_translate' AND `type` = 'other' AND `code` = 'auto_translate'"
				);

				if (!$query->num_rows) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "extension` SET `extension` = 'auto_translate', `type` = 'other', `code` = 'auto_translate'");
				}

				$query = $this->db->query("SELECT `extension_install_id` FROM `" . DB_PREFIX . "extension_install` WHERE `code` = 'auto_translate'");

				if (!$query->num_rows) {
					$this->db->query(
						"INSERT INTO `" . DB_PREFIX . "extension_install` SET `extension_id` = '0', `extension_download_id` = '0',"
						. " `name` = 'Auto Translate (fa <-> en)', `description` = '" . $this->db->escape('Automatically fills in a product or category\'s empty Persian/English name, description, tags and SEO meta fields by translating from whichever language was actually entered, using the Anthropic Claude API.') . "',"
						. " `code` = 'auto_translate', `version` = '1.0', `author` = '', `link` = '', `status` = '0', `date_added` = NOW()"
					);
				}

				// (code, description, trigger, action)
				$events = [
					['auto_translate_product_add', 'Auto-translates a product\'s empty fa/en fields from the other language on save.', 'model/catalog/product.addProduct/after', 'extension/auto_translate/event/product.add'],
					['auto_translate_product_edit', 'Auto-translates a product\'s empty fa/en fields from the other language on save.', 'model/catalog/product.editProduct/after', 'extension/auto_translate/event/product.edit'],
					['auto_translate_category_add', 'Auto-translates a category\'s empty fa/en fields from the other language on save.', 'model/catalog/category.addCategory/after', 'extension/auto_translate/event/category.add'],
					['auto_translate_category_edit', 'Auto-translates a category\'s empty fa/en fields from the other language on save.', 'model/catalog/category.editCategory/after', 'extension/auto_translate/event/category.edit'],
				];

				foreach ($events as [$code, $description, $trigger, $action]) {
					$query = $this->db->query("SELECT `event_id` FROM `" . DB_PREFIX . "event` WHERE `code` = '" . $this->db->escape($code) . "'");

					if (!$query->num_rows) {
						$this->db->query(
							"INSERT INTO `" . DB_PREFIX . "event` SET `code` = '" . $this->db->escape($code) . "', `description` = '" . $this->db->escape($description) . "',"
							. " `trigger` = '" . $this->db->escape($trigger) . "', `action` = '" . $this->db->escape($action) . "', `status` = '1', `sort_order` = '1'"
						);
					}
				}
			},

			// Purchase Invoice gains the real financial side the user asked
			// for: a Supplier (supplier_id, already covered by
			// supplier_table_and_permission above), a currency/exchange
			// rate, a payment type, and a paid_amount - plus a new
			// oc_purchase_invoice_payment table for settling a
			// credit/combined invoice later, and a new "Foreign Exchange
			// Gain/Loss" (5900) system account to absorb the rate
			// difference between the invoice date and the settlement date.
			// CREATE TABLE IF NOT EXISTS (below) works on every MySQL, but
			// idempotent ADD COLUMN/ADD KEY do not - see columnExists()'s
			// docblock. Each column here is keyed by name so it can be
			// checked individually before adding it.
			'purchase_invoice_accounting_extension' => function (): void {
				$columns = [
					'supplier_id' => '`supplier_id` int(11) NOT NULL DEFAULT 0 AFTER `warehouse_id`',
					'currency_code' => '`currency_code` varchar(3) NOT NULL DEFAULT \'\' AFTER `supplier_name`',
					'exchange_rate' => '`exchange_rate` decimal(15,6) NOT NULL DEFAULT 1.000000 AFTER `currency_code`',
					'total_amount' => '`total_amount` decimal(15,4) NOT NULL DEFAULT 0.0000 AFTER `exchange_rate`',
					'payment_type' => '`payment_type` varchar(20) NOT NULL DEFAULT \'credit\' AFTER `total_amount`',
					'bank_account_id' => '`bank_account_id` int(11) NOT NULL DEFAULT 0 AFTER `payment_type`',
					'paid_amount' => '`paid_amount` decimal(15,4) NOT NULL DEFAULT 0.0000 AFTER `bank_account_id`',
				];

				foreach ($columns as $name => $definition) {
					if (!$this->columnExists('purchase_invoice', $name)) {
						$this->db->query("ALTER TABLE `" . DB_PREFIX . "purchase_invoice` ADD COLUMN " . $definition);
					}
				}

				if (!$this->keyExists('purchase_invoice', 'supplier_id')) {
					$this->db->query("ALTER TABLE `" . DB_PREFIX . "purchase_invoice` ADD KEY `supplier_id` (`supplier_id`)");
				}

				// Any invoice saved before this migration has no total_amount
				// on its header row - backfill it once from its line items so
				// existing invoices show a correct outstanding balance instead
				// of appearing fully paid (0 total - 0 paid = 0 owed).
				$this->db->query(
					"UPDATE `" . DB_PREFIX . "purchase_invoice` `pi`"
					. " LEFT JOIN (SELECT `purchase_invoice_id`, SUM(`quantity` * `unit_cost`) AS `sum_total` FROM `" . DB_PREFIX . "purchase_invoice_product` GROUP BY `purchase_invoice_id`) `t` ON (`t`.`purchase_invoice_id` = `pi`.`purchase_invoice_id`)"
					. " SET `pi`.`total_amount` = COALESCE(`t`.`sum_total`, 0), `pi`.`paid_amount` = COALESCE(`t`.`sum_total`, 0)"
					. " WHERE `pi`.`total_amount` = 0 AND `pi`.`payment_type` = 'credit'"
				);

				$this->db->query(
					"CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "purchase_invoice_payment` ("
					. "`purchase_invoice_payment_id` int(11) NOT NULL AUTO_INCREMENT,"
					. "`purchase_invoice_id` int(11) NOT NULL,"
					. "`bank_account_id` int(11) NOT NULL DEFAULT 0,"
					. "`amount` decimal(15,4) NOT NULL DEFAULT 0.0000,"
					. "`exchange_rate` decimal(15,6) NOT NULL DEFAULT 1.000000,"
					. "`journal_id` int(11) NOT NULL DEFAULT 0,"
					. "`user_id` int(11) NOT NULL DEFAULT 0,"
					. "`date_added` datetime NOT NULL,"
					. "PRIMARY KEY (`purchase_invoice_payment_id`),"
					. "KEY `purchase_invoice_id` (`purchase_invoice_id`)"
					. ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
				);

				if ($this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "account'")->num_rows) {
					$query = $this->db->query("SELECT `account_id` FROM `" . DB_PREFIX . "account` WHERE `code` = '5900'");

					if (!$query->num_rows) {
						$parent_query = $this->db->query("SELECT `account_id` FROM `" . DB_PREFIX . "account` WHERE `code` = '5000'");
						$parent_id = $parent_query->num_rows ? (int)$parent_query->row['account_id'] : 0;

						$this->db->query("INSERT INTO `" . DB_PREFIX . "account` SET `parent_id` = '" . $parent_id . "', `code` = '5900', `name` = 'Foreign Exchange Gain/Loss', `type` = 'expense', `is_system` = '1', `is_bank_cash` = '0', `status` = '1', `date_added` = NOW()");
					}
				}
			},
			// `oc_currency`.`value` used to be `double(15,8)` - specifying
			// decimal places on a DOUBLE column makes MariaDB actually
			// round every stored value to 8 places after the point,
			// instead of keeping full floating-point precision. That's
			// harmless while config_currency (the pricing anchor - see
			// event/currency.php) is USD, since every other currency's
			// value then stays close to 1 and 8 decimals is plenty. But
			// the moment the anchor becomes a large-magnitude currency
			// like Rial (millions per USD), every other currency's value
			// becomes a very small fraction (e.g. ~0.0000004), and 8
			// decimal places leaves only 1-2 significant digits -
			// silently wrecking every multi-currency conversion in the
			// store. Widening to a plain, unconstrained `double` keeps
			// full IEEE precision regardless of how large or small the
			// anchor currency's magnitude is, so switching the base
			// currency (see the repeg logic in event/currency.php) is
			// actually safe to do.
			'currency_value_precision' => function (): void {
				$this->db->query("ALTER TABLE `" . DB_PREFIX . "currency` MODIFY `value` double DEFAULT NULL");
			},

			// oc_currency.title was a single column shared by every admin
			// language and the whole storefront, so a currency's name could
			// never actually change with the active language - a Persian
			// admin and an English customer both saw whatever text was
			// typed in once (e.g. "درهم امارات" everywhere, even under the
			// English UI). This adds a proper per-language description
			// table (mirrors how oc_country/oc_country_description already
			// work - see panel/model/localisation/currency.php's class
			// docblock) and backfills it for every currency this install
			// already has.
			//
			// The Persian description is backfilled from whatever is
			// currently in `title` - unchanged, since that text already
			// displays correctly for the Persian admin/storefront today.
			// The English description is backfilled from a lookup of
			// standard ISO 4217 English names (self::CURRENCY_ENGLISH_NAMES)
			// when the currency's code is a recognised one; otherwise it
			// falls back to the same unchanged text as Persian, exactly
			// like a currency added by hand later would, until the admin
			// edits it via Localisation > Currencies to type in a proper
			// English title.
			'currency_description_table' => function (): void {
				$this->db->query(
					"CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "currency_description` ("
					. "`currency_id` int(11) NOT NULL,"
					. "`language_id` int(11) NOT NULL,"
					. "`title` varchar(32) DEFAULT NULL,"
					. "PRIMARY KEY (`currency_id`,`language_id`)"
					. ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
				);

				$languages = $this->db->query("SELECT `language_id`, `code` FROM `" . DB_PREFIX . "language`")->rows;

				$currencies = $this->db->query("SELECT `currency_id`, `title`, `code` FROM `" . DB_PREFIX . "currency`")->rows;

				foreach ($currencies as $currency) {
					$existing = $this->db->query("SELECT `language_id` FROM `" . DB_PREFIX . "currency_description` WHERE `currency_id` = '" . (int)$currency['currency_id'] . "'")->rows;

					$existing_language_ids = array_column($existing, 'language_id');

					foreach ($languages as $language) {
						if (in_array($language['language_id'], $existing_language_ids)) {
							continue;
						}

						$is_english = in_array($language['code'], ['us', 'en', 'en-gb'], true);

						$title = ($is_english && isset(self::CURRENCY_ENGLISH_NAMES[$currency['code']]))
							? self::CURRENCY_ENGLISH_NAMES[$currency['code']]
							: (string)$currency['title'];

						$this->db->query("INSERT INTO `" . DB_PREFIX . "currency_description` SET `currency_id` = '" . (int)$currency['currency_id'] . "', `language_id` = '" . (int)$language['language_id'] . "', `title` = '" . $this->db->escape($title) . "'");
					}
				}

				$this->cache->delete('currency');
			},

			// currency_description_table (above) made the currency NAME
			// per-language, but the SYMBOL (symbol_left/symbol_right on
			// `currency`) is still one language-agnostic field. For most
			// currencies that's fine - $, €, £, ﷼, د.إ are the same glyph
			// in any language - but the Iranian Toman's "symbol" is
			// actually just the Persian word تومان, so it kept showing up
			// even on the English storefront (reported live 2026-09-17).
			// Adds the same kind of per-language override columns to
			// `currency_description`, and backfills an English "Toman" for
			// the one currency that actually needs it - everything else is
			// left alone (NULL override = falls back to the base table's
			// symbol, unchanged).
			'currency_symbol_per_language' => function (): void {
				if (!$this->columnExists('currency_description', 'symbol_left')) {
					$this->db->query("ALTER TABLE `" . DB_PREFIX . "currency_description` ADD COLUMN `symbol_left` varchar(32) DEFAULT NULL AFTER `title`");
				}

				if (!$this->columnExists('currency_description', 'symbol_right')) {
					$this->db->query("ALTER TABLE `" . DB_PREFIX . "currency_description` ADD COLUMN `symbol_right` varchar(32) DEFAULT NULL AFTER `symbol_left`");
				}

				$currency = $this->db->query("SELECT `currency_id`, `symbol_left`, `symbol_right` FROM `" . DB_PREFIX . "currency` WHERE `code` = 'IRT'")->row;

				if ($currency) {
					$languages = $this->db->query("SELECT `language_id`, `code` FROM `" . DB_PREFIX . "language` WHERE `code` IN ('us', 'en', 'en-gb')")->rows;

					foreach ($languages as $language) {
						$existing = $this->db->query("SELECT `symbol_left`, `symbol_right` FROM `" . DB_PREFIX . "currency_description` WHERE `currency_id` = '" . (int)$currency['currency_id'] . "' AND `language_id` = '" . (int)$language['language_id'] . "'")->row;

						if ($existing === false) {
							// No description row for this language yet (the
							// currency_description_table migration above
							// should already have created one for every
							// currency/language pair, but guard anyway
							// rather than assume).
							continue;
						}

						if (!empty($existing['symbol_left']) || !empty($existing['symbol_right'])) {
							continue; // already has an override - don't clobber an admin's own edit
						}

						if ($currency['symbol_left'] !== '') {
							$this->db->query("UPDATE `" . DB_PREFIX . "currency_description` SET `symbol_left` = 'Toman' WHERE `currency_id` = '" . (int)$currency['currency_id'] . "' AND `language_id` = '" . (int)$language['language_id'] . "'");
						} elseif ($currency['symbol_right'] !== '') {
							$this->db->query("UPDATE `" . DB_PREFIX . "currency_description` SET `symbol_right` = 'Toman' WHERE `currency_id` = '" . (int)$currency['currency_id'] . "' AND `language_id` = '" . (int)$language['language_id'] . "'");
						}
					}
				}

				$this->cache->delete('currency');
			},

			// Admin-configurable per-customer order limit (e.g. "max 2 of
			// this product per customer, ever"), enforced in
			// catalog/controller/checkout/cart.php's add() by summing a
			// logged-in customer's own already-confirmed orders plus what's
			// currently in their cart. 0 (the default) means unlimited, so
			// this is a no-op for every existing product until an admin
			// opts a specific product into a limit.
			'product_max_customer_quantity' => function (): void {
				if (!$this->columnExists('product', 'max_customer_quantity')) {
					$this->db->query("ALTER TABLE `" . DB_PREFIX . "product` ADD COLUMN `max_customer_quantity` int(11) DEFAULT 0 AFTER `minimum`");
				}
			},

			// Aghaye Pardakht (aqayepardakht.ir) and BitPay (bitpay.ir) are two
			// more gateways added to the existing `iranian_gateways` extension
			// package - same "grant permission + seed extension row" pattern as
			// notification_and_payment_extensions above, but only a NEW
			// migration key (that migration has almost certainly already run on
			// this install, so its own array literal cannot be edited after the
			// fact - see runMigrations()'s per-key skip). No new
			// `extension_install` row is needed: the `iranian_gateways` package
			// row already exists from notification_and_payment_extensions, and
			// both new gateways ship inside that same package directory.
			'aqayepardakht_bitpay_extensions' => function (): void {
				$this->grantAdministratorPermissions([
					'extension/iranian_gateways/payment/aqayepardakht',
					'extension/iranian_gateways/payment/bitpay',
				]);

				$extensions = [
					['iranian_gateways', 'payment', 'aqayepardakht'],
					['iranian_gateways', 'payment', 'bitpay'],
				];

				foreach ($extensions as [$extension, $type, $code]) {
					$query = $this->db->query(
						"SELECT `extension_id` FROM `" . DB_PREFIX . "extension`"
						. " WHERE `extension` = '" . $this->db->escape($extension) . "' AND `type` = '" . $this->db->escape($type) . "' AND `code` = '" . $this->db->escape($code) . "'"
					);

					if (!$query->num_rows) {
						$this->db->query(
							"INSERT INTO `" . DB_PREFIX . "extension` SET `extension` = '" . $this->db->escape($extension) . "', `type` = '" . $this->db->escape($type) . "', `code` = '" . $this->db->escape($code) . "'"
						);
					}
				}

				// Refresh the package's Extensions > Installer description so
				// it lists all seven gateways now, not just the original five -
				// this install's row was already inserted by
				// notification_and_payment_extensions above with the old text.
				$this->db->query(
					"UPDATE `" . DB_PREFIX . "extension_install` SET `description` = '" . $this->db->escape('ZarinPal, PayPing, IDPay, SizPay, card-to-card, Aghaye Pardakht and BitPay payment methods for Iranian stores.') . "' WHERE `code` = 'iranian_gateways'"
				);
			},

			// Registers the new `payment_fee` total extension (Extensions >
			// Totals > Payment Method Fee/Discount): lets the admin add a
			// surcharge or discount to the order total based on which
			// payment method the customer picked. Same "grant permission +
			// seed extension/extension_install rows" pattern as
			// notification_and_payment_extensions above. Left disabled
			// (`total_payment_fee_status` is simply never set here, so
			// getTotals() skips it) until the admin visits the settings
			// page and turns it on - installing this update must never
			// silently change anyone's order totals.
			'payment_fee_extension' => function (): void {
				$this->grantAdministratorPermissions([
					'extension/payment_fee/total/payment_fee',
				]);

				$query = $this->db->query(
					"SELECT `extension_id` FROM `" . DB_PREFIX . "extension`"
					. " WHERE `extension` = 'payment_fee' AND `type` = 'total' AND `code` = 'payment_fee'"
				);

				if (!$query->num_rows) {
					$this->db->query(
						"INSERT INTO `" . DB_PREFIX . "extension` SET `extension` = 'payment_fee', `type` = 'total', `code` = 'payment_fee'"
					);
				}

				$query = $this->db->query("SELECT `extension_install_id` FROM `" . DB_PREFIX . "extension_install` WHERE `code` = 'payment_fee'");

				if (!$query->num_rows) {
					$this->db->query(
						"INSERT INTO `" . DB_PREFIX . "extension_install` SET `extension_id` = '0', `extension_download_id` = '0',"
						. " `name` = 'Payment Method Fee/Discount', `description` = '" . $this->db->escape('Adds a surcharge or gives a discount on the order total based on which payment method the customer chooses (e.g. +10% for one gateway, -5% for another).') . "',"
						. " `code` = 'payment_fee', `version` = '1.0', `author` = '', `link` = '', `status` = '1', `date_added` = NOW()"
					);
				}
			},

			// Adds DigiPay as an eighth gateway in the existing
			// `iranian_gateways` package - same "grant permission + seed
			// extension row" pattern as aqayepardakht_bitpay_extensions
			// above. DigiPay also needs its own small table (tracking info
			// needed later to report credit/BNPL purchases as delivered -
			// see extension/iranian_gateways/catalog/controller/event/digipay_deliver.php)
			// and an `oc_event` row so that event actually runs; both use
			// the same "CREATE TABLE IF NOT EXISTS" / SELECT-then-INSERT
			// safe-to-rerun style already used elsewhere in this file.
			'digipay_extension' => function (): void {
				$this->grantAdministratorPermissions([
					'extension/iranian_gateways/payment/digipay',
				]);

				$query = $this->db->query(
					"SELECT `extension_id` FROM `" . DB_PREFIX . "extension`"
					. " WHERE `extension` = 'iranian_gateways' AND `type` = 'payment' AND `code` = 'digipay'"
				);

				if (!$query->num_rows) {
					$this->db->query(
						"INSERT INTO `" . DB_PREFIX . "extension` SET `extension` = 'iranian_gateways', `type` = 'payment', `code` = 'digipay'"
					);
				}

				$this->db->query(
					"UPDATE `" . DB_PREFIX . "extension_install` SET `description` = '" . $this->db->escape('ZarinPal, PayPing, IDPay, SizPay, card-to-card, Aghaye Pardakht, BitPay and DigiPay payment methods for Iranian stores.') . "' WHERE `code` = 'iranian_gateways'"
				);

				$this->db->query(
					"CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "digipay_purchase` ("
					. "`order_id` int(11) NOT NULL,"
					. "`provider_id` varchar(64) NOT NULL DEFAULT '',"
					. "`tracking_code` varchar(64) NOT NULL DEFAULT '',"
					. "`type` int(11) NOT NULL DEFAULT 0,"
					. "`delivered` tinyint(1) NOT NULL DEFAULT 0,"
					. "`deliver_attempts` int(11) NOT NULL DEFAULT 0,"
					. "`deliver_error` text,"
					. "`date_added` datetime NOT NULL,"
					. "PRIMARY KEY (`order_id`)"
					. ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
				);

				$query = $this->db->query("SELECT `event_id` FROM `" . DB_PREFIX . "event` WHERE `code` = 'digipay_deliver'");

				if (!$query->num_rows) {
					$this->db->query(
						"INSERT INTO `" . DB_PREFIX . "event` SET `code` = 'digipay_deliver', `description` = '" . $this->db->escape('Reports a DigiPay credit/BNPL purchase as delivered once its order reaches the configured status.') . "',"
						. " `trigger` = 'model/checkout/order.addHistory/before', `action` = 'extension/iranian_gateways/event/digipay_deliver', `status` = '1', `sort_order` = '1'"
					);
				}
			},

			// Settings > General's trust badges field moved from one
			// freeform textarea (config_trust_badges_html, where several
			// badges' codes were pasted concatenated together with no
			// name of their own) to a repeatable name+HTML list
			// (config_trust_badges, an array keyed by a generated uid -
			// see panel/view/template/setting/setting.twig). For every
			// store that had a non-empty old textarea value and has not
			// already been migrated, this seeds the new list with a
			// single entry carrying the OLD value over verbatim (under a
			// generic placeholder name), so none of the store's existing
			// badge codes are lost. `value` is copied as-is (it is
			// already Request::clean()-encoded exactly like a normal
			// posted field, same as every other row in this table) and
			// re-wrapped as a serialized array under the new key.
			'trust_badges_list' => function (): void {
				$query = $this->db->query(
					"SELECT `store_id`, `value` FROM `" . DB_PREFIX . "setting`"
					. " WHERE `code` = 'config' AND `key` = 'config_trust_badges_html' AND `value` != ''"
				);

				foreach ($query->rows as $result) {
					$existing = $this->db->query(
						"SELECT `setting_id` FROM `" . DB_PREFIX . "setting`"
						. " WHERE `store_id` = '" . (int)$result['store_id'] . "' AND `code` = 'config' AND `key` = 'config_trust_badges'"
					);

					if ($existing->num_rows) {
						continue;
					}

					$badges = [
						'badge_legacy' => [
							'name' => 'نمادهای قبلی',
							'html' => $result['value'],
						],
					];

					$this->db->query(
						"INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = '" . (int)$result['store_id'] . "', `code` = 'config', `key` = 'config_trust_badges', `value` = '" . $this->db->escape(json_encode($badges)) . "', `serialized` = '1'"
					);
				}
			},
		];
	}

	/**
	 * Standard English (ISO 4217-style) currency names, used only to
	 * backfill the English row of `oc_currency_description` for a
	 * recognised currency code during the currency_description_table
	 * migration above - see its comment for why. IRT (Toman) isn't a real
	 * ISO 4217 code, but is included since this project uses it for Iran's
	 * everyday unit alongside the official Rial.
	 */
	private const CURRENCY_ENGLISH_NAMES = [
		'USD' => 'US Dollar',
		'EUR' => 'Euro',
		'GBP' => 'Pound Sterling',
		'AED' => 'United Arab Emirates Dirham',
		'IRR' => 'Iranian Rial',
		'IRT' => 'Iranian Toman',
		'HKD' => 'Hong Kong Dollar',
		'INR' => 'Indian Rupee',
		'RUB' => 'Russian Ruble',
		'CNY' => 'Chinese Yuan Renminbi',
		'AUD' => 'Australian Dollar',
		'CAD' => 'Canadian Dollar',
		'CHF' => 'Swiss Franc',
		'JPY' => 'Japanese Yen',
		'TRY' => 'Turkish Lira',
		'SAR' => 'Saudi Riyal',
		'QAR' => 'Qatari Riyal',
		'KWD' => 'Kuwaiti Dinar',
		'OMR' => 'Omani Rial',
		'BHD' => 'Bahraini Dinar',
		'IQD' => 'Iraqi Dinar',
		'AFN' => 'Afghan Afghani',
		'PKR' => 'Pakistani Rupee',
		'SEK' => 'Swedish Krona',
		'NOK' => 'Norwegian Krone',
		'DKK' => 'Danish Krone',
		'PLN' => 'Polish Zloty',
		'CZK' => 'Czech Koruna',
		'HUF' => 'Hungarian Forint',
		'RON' => 'Romanian Leu',
		'ZAR' => 'South African Rand',
		'BRL' => 'Brazilian Real',
		'MXN' => 'Mexican Peso',
		'SGD' => 'Singapore Dollar',
		'MYR' => 'Malaysian Ringgit',
		'THB' => 'Thai Baht',
		'IDR' => 'Indonesian Rupiah',
		'PHP' => 'Philippine Peso',
		'VND' => 'Vietnamese Dong',
		'KRW' => 'South Korean Won',
		'NZD' => 'New Zealand Dollar',
		'ILS' => 'Israeli New Shekel',
		'EGP' => 'Egyptian Pound',
	];

	/**
	 * Grant Administrator Permissions
	 *
	 * Adds each given route to the Administrator group's (user_group_id 1)
	 * `access` and `modify` permission arrays, if not already present.
	 * Shared by any migration whose fix is "this page/route already exists
	 * and works, it's just invisible because this install's permission
	 * blob predates it" — see design_color_permission and
	 * warehouse_pos_accounting_permissions above for two examples of
	 * exactly that situation.
	 *
	 * @param string[] $routes
	 *
	 * @return void
	 */
	private function grantAdministratorPermissions(array $routes): void {
		$query = $this->db->query("SELECT `user_group_id`, `permission` FROM `" . DB_PREFIX . "user_group` WHERE `user_group_id` = '1'");

		if (!$query->num_rows) {
			return;
		}

		$permission = json_decode((string)$query->row['permission'], true);

		if (!is_array($permission)) {
			$permission = [];
		}

		if (!isset($permission['access']) || !is_array($permission['access'])) {
			$permission['access'] = [];
		}

		if (!isset($permission['modify']) || !is_array($permission['modify'])) {
			$permission['modify'] = [];
		}

		$changed = false;

		foreach ($routes as $route) {
			if (!in_array($route, $permission['access'], true)) {
				$permission['access'][] = $route;

				$changed = true;
			}

			if (!in_array($route, $permission['modify'], true)) {
				$permission['modify'][] = $route;

				$changed = true;
			}
		}

		if ($changed) {
			$this->db->query("UPDATE `" . DB_PREFIX . "user_group` SET `permission` = '" . $this->db->escape(json_encode($permission)) . "' WHERE `user_group_id` = '1'");
		}
	}

	/**
	 * Column Exists
	 *
	 * `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` (MySQL 8.0.29+ only) is a
	 * syntax error on older MySQL still found on some hosts (confirmed live
	 * on this project's own production host, 2026-09-17 - see the
	 * `product_max_customer_quantity` incident write-up). Any migration
	 * that needs to add a column idempotently must check first with this
	 * instead of relying on that clause.
	 */
	private function columnExists(string $table, string $column): bool {
		$query = $this->db->query(
			"SELECT `COLUMN_NAME` FROM `information_schema`.`COLUMNS`"
			. " WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '" . $this->db->escape(DB_PREFIX . $table) . "'"
			. " AND `COLUMN_NAME` = '" . $this->db->escape($column) . "'"
		);

		return (bool)$query->num_rows;
	}

	/**
	 * Key Exists
	 *
	 * Same reasoning as columnExists() above, for `ADD KEY IF NOT EXISTS`
	 * (also unsupported on older MySQL).
	 */
	private function keyExists(string $table, string $key): bool {
		$query = $this->db->query(
			"SELECT `INDEX_NAME` FROM `information_schema`.`STATISTICS`"
			. " WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '" . $this->db->escape(DB_PREFIX . $table) . "'"
			. " AND `INDEX_NAME` = '" . $this->db->escape($key) . "'"
		);

		return (bool)$query->num_rows;
	}

	/**
	 * Run Migrations
	 *
	 * Applies every migration from getMigrations() that hasn't already run
	 * on this install, in declaration order, then records each one so it's
	 * never re-applied. Provisions its own tracking table on first use
	 * (CREATE TABLE IF NOT EXISTS), so there's no separate schema step —
	 * a fresh install and a decade-old one both just work the first time
	 * applyUpdate() runs after this method itself ships to them.
	 *
	 * One migration's failure doesn't block the others or the update
	 * itself (an update that already copied new code should still finish
	 * rather than get stuck retrying a data fix), but it's also not
	 * recorded as applied, so it's retried on the very next update.
	 *
	 * Public (not just called at the end of applyUpdate()) so an install
	 * that receives code changes some other way than this page's own
	 * "download and apply" flow — e.g. a patch file applied directly on
	 * the server, which never runs applyUpdate() or its migrations at all
	 * — has a way to run any pending migrations on demand instead of
	 * silently carrying a schema/permission mismatch until the next full
	 * update happens to go through this page. See tool/system_update.
	 * migrate() (the controller action this backs) and its confirm-dialog
	 * text for why this exists as its own button.
	 *
	 * @return array{applied: string[], failed: array<string, string>}
	 */
	public function runMigrations(): array {
		$this->db->query(
			"CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "migration` ("
			. "`migration_id` int(11) NOT NULL AUTO_INCREMENT,"
			. "`key` varchar(191) NOT NULL,"
			. "`applied_at` datetime NOT NULL,"
			. "PRIMARY KEY (`migration_id`),"
			. "UNIQUE KEY `key` (`key`)"
			. ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);

		$applied = [];
		$failed = [];

		foreach ($this->getMigrations() as $key => $callback) {
			$query = $this->db->query("SELECT `migration_id` FROM `" . DB_PREFIX . "migration` WHERE `key` = '" . $this->db->escape($key) . "'");

			if ($query->num_rows) {
				continue;
			}

			try {
				$callback();
			} catch (\Throwable $e) {
				$failed[$key] = $e->getMessage();

				continue;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "migration` SET `key` = '" . $this->db->escape($key) . "', `applied_at` = NOW()");

			$applied[] = $key;
		}

		return [
			'applied' => $applied,
			'failed'  => $failed,
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
		@file_put_contents(MCART_ROOT . '.admin_dir', $data['system_update_admin_dir']);
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
		$file = MCART_ROOT . 'VERSION';

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
	 * Write Progress
	 *
	 * Overwrites the small progress file with the current step and percent
	 * complete. Best-effort only (@-suppressed): a failed write here must
	 * never break the actual update, it just means the progress bar stops
	 * updating for that run — the apply request itself still finishes
	 * normally and reports success/failure the usual way.
	 *
	 * @param string $step
	 * @param int    $percent
	 *
	 * @return void
	 */
	private function writeProgress(string $step, int $percent): void {
		if (!is_dir(DIR_STORAGE)) {
			return;
		}

		@file_put_contents(DIR_STORAGE . self::PROGRESS_FILE, json_encode([
			'step'    => $step,
			'percent' => $percent,
			'time'    => time(),
		]));
	}

	/**
	 * Get Progress
	 *
	 * Read-only status for the polling endpoint. Always returns something
	 * usable — an "idle" step when no update has ever run, the file is
	 * unreadable, or a read happens to land on a half-written file (an
	 * overwrite here is not atomic, but the file is only ever a few dozen
	 * bytes, so a torn read is rare and self-corrects on the very next
	 * poll a second later).
	 *
	 * @return array<string, mixed>
	 */
	public function getProgress(): array {
		$file = DIR_STORAGE . self::PROGRESS_FILE;

		if (!is_file($file)) {
			return ['step' => 'idle', 'percent' => 0];
		}

		$data = json_decode((string)@file_get_contents($file), true);

		if (!is_array($data) || !isset($data['step'])) {
			return ['step' => 'idle', 'percent' => 0];
		}

		return $data;
	}

	/**
	 * Apply Update
	 *
	 * Takes a full backup (database + files), then downloads the configured
	 * branch as a zip, extracts it and copies its contents over
	 * MCART_ROOT, skipping EXCLUDE_PATHS. Records the new commit as
	 * current on success. If the backup itself fails, the update is not
	 * attempted — a pre-update backup that didn't happen is not worth the
	 * risk of an update with no way back.
	 *
	 * Writes progress to self::PROGRESS_FILE at each stage (see
	 * writeProgress()) so the admin page can poll tool/system_update.progress
	 * and show a live progress bar instead of one static "please wait"
	 * message for however long this whole request takes.
	 *
	 * @return array<string, mixed>
	 */
	public function applyUpdate(): array {
		$this->writeProgress('start', 2);

		$settings = $this->getSettings();

		if (!$settings['repo'] || !$settings['token']) {
			$this->writeProgress('error', 0);

			return ['error' => 'not_configured'];
		}

		if (!class_exists('ZipArchive')) {
			$this->writeProgress('error', 0);

			return ['error' => 'zip_extension'];
		}

		@set_time_limit(0);
		@ini_set('memory_limit', '512M');

		$this->writeProgress('resolve', 5);

		// Resolve the exact commit we're about to apply, so the recorded
		// "current_commit" always reflects the code actually written to disk.
		$commit_result = $this->githubRequest('https://api.github.com/repos/' . $settings['repo'] . '/commits/' . rawurlencode($settings['branch']), $settings['token']);

		if (isset($commit_result['error'])) {
			$this->writeProgress('error', 5);

			return $commit_result;
		}

		$sha = $commit_result['data']['sha'] ?? '';

		if (!$sha) {
			$this->writeProgress('error', 5);

			return ['error' => 'download'];
		}

		$this->writeProgress('backup', 10);

		// Label the backup with the version numbers involved (e.g.
		// "5.1.1 → 5.2.0"), matching how the site's own version is shown
		// everywhere else, instead of raw git commit hashes. Falls back to
		// the commit-hash form when either VERSION file isn't available
		// (e.g. an older repo state with no VERSION file yet on this
		// branch), so a backup never goes unlabeled.
		$current_version = $this->getLocalVersion();
		$latest_version = $this->getRemoteVersion($settings['repo'], $settings['token'], $settings['branch']);

		if ($current_version && $latest_version) {
			// "\u{2066}" / "\u{2069}" are Unicode LTR-isolate marks, not
			// visible characters — without them, "5.1.1 → 5.2.0" sitting
			// inside a right-to-left Persian sentence can get visually
			// reordered by the browser's bidi algorithm (rendering as if
			// it went from the newer version to the older one, backwards
			// from what the string actually says). Isolating the
			// old-to-new segment forces it to always display left-to-right
			// regardless of the surrounding RTL text.
			$reason = 'پیش از بروزرسانی: ' . "\u{2066}" . $current_version . ' → ' . $latest_version . "\u{2069}";
		} else {
			$reason = 'پیش از بروزرسانی به ' . substr($sha, 0, 10);
		}

		$backup = $this->createBackup($reason, $settings['current_commit'], $sha);

		if (isset($backup['error'])) {
			$this->writeProgress('error', 10);

			return $backup;
		}

		$this->writeProgress('download', 40);

		$tmp_dir = DIR_STORAGE . 'update_tmp/';
		$zip_file = $tmp_dir . 'update.zip';
		$extract_dir = $tmp_dir . 'extracted/';

		// Note: this wipes and recreates $tmp_dir, which is why the
		// progress file lives outside it (see PROGRESS_FILE's docblock) —
		// otherwise every call here would erase the very file a concurrent
		// poll is trying to read.
		$this->prepareTmpDir($tmp_dir, $extract_dir);

		$download = $this->downloadZipball($settings['repo'], $settings['branch'], $settings['token'], $zip_file);

		if (isset($download['error'])) {
			$this->writeProgress('error', 40);

			$this->cleanupTmpDir($tmp_dir);

			$download['backup_id'] = $backup['id'];

			return $download;
		}

		$this->writeProgress('extract', 65);

		$zip = new \ZipArchive();

		if ($zip->open($zip_file) !== true) {
			$this->writeProgress('error', 65);

			$this->cleanupTmpDir($tmp_dir);

			return ['error' => 'extract', 'backup_id' => $backup['id']];
		}

		if ($zip->numFiles < 1) {
			$this->writeProgress('error', 65);

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
			$this->writeProgress('error', 65);

			$this->cleanupTmpDir($tmp_dir);

			return ['error' => 'extract', 'backup_id' => $backup['id']];
		}

		$source_root = $extract_dir . $root_folder . '/';

		if (!is_dir($source_root)) {
			$this->writeProgress('error', 65);

			$this->cleanupTmpDir($tmp_dir);

			return ['error' => 'extract', 'backup_id' => $backup['id']];
		}

		$this->writeProgress('copy', 85);

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

		$this->copyRecursive($source_root, MCART_ROOT, self::EXCLUDE_PATHS, $write_failures, $path_remap);

		$this->writeProgress('finalize', 95);

		$this->cleanupTmpDir($tmp_dir);

		// New code is on disk — force the framework to recompile templates
		// and drop any stale cached data instead of serving pre-update output.
		$this->clearCache();

		// Apply any one-time database changes past updates have needed
		// (new permissions, data fixes, ...) — see getMigrations()'s own
		// docblock for why this exists instead of shipping a manual SQL
		// script alongside a feature that needs one.
		$this->runMigrations();

		$this->setBaseline($sha);

		if ($write_failures) {
			$this->writeProgress('error', 95);

			return [
				'error'          => 'write',
				'applied_commit' => $sha,
				'failed_count'   => count($write_failures),
				'backup_id'      => $backup['id'],
			];
		}

		$this->writeProgress('done', 100);

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

		$this->zipDirectory(MCART_ROOT, $zip_file, self::BACKUP_EXCLUDE_PATHS);

		if (!is_file($zip_file) || !filesize($zip_file)) {
			return ['error' => 'backup_failed'];
		}

		// Storage is backed up as its own zip since it can live entirely
		// outside MCART_ROOT (moved out for security — see DIR_STORAGE).
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
	 * MCART_ROOT. Does not delete files that didn't exist at backup time
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

		$extracted_ok = $zip->extractTo(MCART_ROOT);

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
