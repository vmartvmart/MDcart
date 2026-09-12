<?php
namespace MDcart\Admin\Controller\Accounting;
/**
 * Class ExchangeRate
 *
 * A small screen for keeping the store's Rial-based currencies (IRR, and
 * IRT/Toman which this store represents as Rial / 10 for display) in sync
 * with the real AED-to-Rial market rate - see admin/model/accounting/
 * exchange_rate.php for the full conversion math.
 *
 * Two ways to update the rate:
 *  - Manual: type in today's "1 AED = X Rial" rate and save.
 *  - Fetch: pull that same number automatically from tgju.org's public
 *    AED price page (https://www.tgju.org/profile/price_aed).
 *
 * Automatic scheduling (twice a day) is intentionally NOT wired up yet -
 * the store is still on local XAMPP, not a real host with cron access.
 * Once it moves to real hosting, a host-level cron job (or task scheduler)
 * can simply call the fetch action on a schedule - see the comment on
 * fetch() below for the exact URL to hit.
 *
 * Can be loaded using $this->load->controller('accounting/exchange_rate');
 *
 * @package MDcart\Admin\Controller\Accounting
 */
class ExchangeRate extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('accounting/exchange_rate');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('accounting/exchange_rate', 'user_token=' . $this->session->data['user_token'])
		];

		$data['fetch'] = $this->url->link('accounting/exchange_rate.fetch', 'user_token=' . $this->session->data['user_token'], true);
		$data['save'] = $this->url->link('accounting/exchange_rate.save', 'user_token=' . $this->session->data['user_token'], true);

		$this->load->model('accounting/exchange_rate');

		$data['rates'] = $this->model_accounting_exchange_rate->getRates();
		$data['aed_usd_peg'] = \MDcart\Admin\Model\Accounting\ExchangeRate::AED_USD_PEG;

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('accounting/exchange_rate', $data));
	}

	/**
	 * Manual entry (AJAX) - admin types in today's "1 AED = X Rial" rate.
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('accounting/exchange_rate');

		$json = [];

		if (!$this->user->hasPermission('modify', 'accounting/exchange_rate')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$rial_per_aed = (float)($this->request->post['rial_per_aed'] ?? 0);

		if (!$json && $rial_per_aed <= 0) {
			$json['error'] = $this->language->get('error_rate');
		}

		if (!$json) {
			$this->load->model('accounting/exchange_rate');
			$this->model_accounting_exchange_rate->applyRialPerAed($rial_per_aed);

			$json['success'] = $this->language->get('text_success');
			$json['rial_per_aed'] = $rial_per_aed;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Automatic fetch (AJAX) - pulls the current AED-to-Rial rate from
	 * tgju.org's public AED profile page and applies it the same way a
	 * manual entry would.
	 *
	 * This same URL (accounting/exchange_rate.fetch&user_token=...) is what
	 * a future host-level cron job should call twice a day once this store
	 * has real hosting - a plain HTTP GET to it (with a valid user_token)
	 * does the whole refresh, no browser/admin session needed beyond that
	 * token. Not wired up automatically yet by design (see class docblock).
	 *
	 * @return void
	 */
	public function fetch(): void {
		$this->load->language('accounting/exchange_rate');

		$json = [];

		if (!$this->user->hasPermission('modify', 'accounting/exchange_rate')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$rial_per_aed = $this->fetchRialPerAedFromTgju();

			if ($rial_per_aed === null) {
				$json['error'] = $this->language->get('error_fetch');
			} else {
				$this->load->model('accounting/exchange_rate');
				$this->model_accounting_exchange_rate->applyRialPerAed($rial_per_aed);

				$json['success'] = $this->language->get('text_success_fetch');
				$json['rial_per_aed'] = $rial_per_aed;
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Fetches https://www.tgju.org/profile/price_aed and extracts today's
	 * "1 AED = X Rial" rate from it.
	 *
	 * tgju.org is a heavy client-rendered page, so this deliberately does
	 * NOT depend on any particular widget/ticker markup (that changes
	 * often and would break silently). Instead it looks for the plain
	 * Persian sentence the page prints in its own FAQ/description text -
	 * "در حال حاضر قیمت هر درهم امارات X ریال می باشد" ("the current price
	 * of one UAE Dirham is X Rial") - which, like the rest of that
	 * description content, is rendered server-side for search engines to
	 * read, unlike the live-updating ticker number. A couple of narrower
	 * fallback patterns are tried too, in case tgju rewords that sentence.
	 *
	 * Returns null (never throws) on any failure - network error, changed
	 * page structure, etc. - so a failed automatic fetch never corrupts an
	 * existing, working rate. The admin always still has the manual field.
	 *
	 * @return float|null
	 */
	private function fetchRialPerAedFromTgju(): ?float {
		$url = 'https://www.tgju.org/profile/price_aed';

		$html = false;

		if (function_exists('curl_init')) {
			$ch = curl_init($url);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 15);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept-Language: fa,en;q=0.9']);

			$html = curl_exec($ch);

			curl_close($ch);
		}

		if ($html === false || $html === '') {
			// Fall back to file_get_contents in case cURL isn't available -
			// most shared hosts have both, but not guaranteed.
			$context = stream_context_create([
				'http' => [
					'timeout' => 15,
					'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\nAccept-Language: fa,en;q=0.9\r\n"
				]
			]);

			$html = @file_get_contents($url, false, $context);
		}

		if (!$html) {
			return null;
		}

		$patterns = [
			// "در حال حاضر قیمت هر درهم امارات 637,540 ریال می باشد"
			'/قیمت\s+هر\s+درهم\s+امارات\s+([0-9,\x{06F0}-\x{06F9}]+)\s+ریال/u',
			// A "نرخ فعلی" / "current rate" style table row: نرخ فعلی ... 637,540
			'/نرخ\s+فعلی[^0-9]{0,40}([0-9,\x{06F0}-\x{06F9}]{5,})/u',
			// Any data-col attribute tgju commonly uses for the live trade value.
			'/data-col="info\.last_trade\.PDrCotVal"[^>]*>\s*([0-9,\x{06F0}-\x{06F9}]+)/u'
		];

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $html, $matches)) {
				$number = $this->persianDigitsToLatin($matches[1]);
				$number = str_replace(',', '', $number);

				if (is_numeric($number) && (float)$number > 0) {
					return (float)$number;
				}
			}
		}

		return null;
	}

	/**
	 * Converts Persian/Arabic-Indic digits (۰-۹) to plain Latin digits, in
	 * case a rate is embedded in the page using Persian numerals.
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	private function persianDigitsToLatin(string $value): string {
		$persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
		$arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
		$latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

		return str_replace($arabic, $latin, str_replace($persian, $latin, $value));
	}
}
