<?php
namespace MDcart\Admin\Model\Accounting;
/**
 * Class ExchangeRate
 *
 * Keeps the store's Rial-based currencies (IRR and IRT/Toman) in sync with
 * the real AED-to-Rial market rate. The UAE Dirham is fixed to the US
 * Dollar by a stable central-bank peg (self::AED_USD_PEG), so that one
 * constant plus a single fetched/entered "1 AED = X Rial" number is enough
 * to derive an accurate Rial/USD (and therefore Toman/USD) rate without
 * needing a second data source for the Dollar itself - see the comment on
 * applyRialPerAed() for the full derivation.
 *
 * Can be loaded using $this->load->model('accounting/exchange_rate');
 *
 * @package MDcart\Admin\Model\Accounting
 */
class ExchangeRate extends \MDcart\System\Engine\Model {
	/**
	 * UAE Dirham's official peg to the US Dollar. This has been fixed since
	 * 1997 and essentially never changes, unlike the Rial side of things -
	 * so it is safe to treat as a constant rather than something that needs
	 * fetching. If the Central Bank of the UAE ever does revalue it, this is
	 * the one number in the whole feature that would need updating by hand.
	 */
	public const AED_USD_PEG = 3.6725;

	/**
	 * Reads back the store's current Rial-based rates, plus when they were
	 * last changed, for display on the Exchange Rate screen.
	 *
	 * @return array<string, mixed>
	 */
	public function getRates(): array {
		$this->load->model('localisation/currency');

		$rates = [
			'aed' => $this->model_localisation_currency->getCurrencyByCode('AED'),
			'irr' => $this->model_localisation_currency->getCurrencyByCode('IRR'),
			'irt' => $this->model_localisation_currency->getCurrencyByCode('IRT')
		];

		// The number an Iranian admin actually thinks in: how many Rial is
		// one AED worth right now, derived from the store's own currency
		// table (IRR.value and AED.value are both relative to the same
		// anchor currency, whatever it currently is, so their ratio is
		// still the Rial/AED cross rate regardless of which one that is).
		$rial_per_aed = 0.0;

		if (!empty($rates['irr']['value']) && !empty($rates['aed']['value'])) {
			$rial_per_aed = (float)$rates['irr']['value'] / (float)$rates['aed']['value'];
		}

		return [
			'rial_per_aed'  => $rial_per_aed,
			'irr_value'     => !empty($rates['irr']['value']) ? (float)$rates['irr']['value'] : 0.0,
			'irt_value'     => !empty($rates['irt']['value']) ? (float)$rates['irt']['value'] : 0.0,
			'date_modified' => $rates['irr']['date_modified'] ?? null,
			'installed'     => (bool)($rates['aed'] && $rates['irr'] && $rates['irt'])
		];
	}

	/**
	 * Applies a freshly fetched or manually entered "1 AED = X Rial" market
	 * rate to the store's currency table.
	 *
	 * Derivation: we only ever observe one real market data point at a time
	 * - the AED/Rial cross rate - but AED is reliably pegged to USD at a
	 * fixed, known ratio (self::AED_USD_PEG). Multiplying the two gives the
	 * real-world Rial/USD rate implied by today's AED/Rial market price:
	 *
	 *     Rial/USD = (AED/USD peg) x (Rial per AED, just fetched/entered)
	 *
	 * That gives us, in real terms, how much of USD/AED/IRR/IRT is worth
	 * one USD (Toman has no ISO code and is always exactly Rial / 10, so
	 * its "per USD" figure falls straight out of Rial's).
	 *
	 * The store's currency table, however, stores every currency's `value`
	 * relative to whichever currency is config_currency right now (the
	 * "anchor" - see event/currency.php, which is what keeps that anchor's
	 * own value pinned at exactly 1 whenever the admin changes it, and
	 * rescales every other currency to match). So the last step is to
	 * re-express each "per USD" figure as "per anchor unit" instead, by
	 * dividing through by the anchor's own "per USD" figure - and the
	 * anchor currency itself is always left untouched, since its value
	 * must stay pinned at exactly 1 for everything else in the store
	 * (prices, orders, the accounting journal) to compute correctly. When
	 * the anchor is USD (the default), that division is by 1 and every
	 * figure below reduces to exactly what this method always computed
	 * before anchor-switching existed. When the anchor is one of the
	 * currencies THIS method manages (AED, IRR or IRT), today's market
	 * reading gives us its "per USD" figure directly. For any other
	 * anchor (EUR, GBP, ...), there is no fresh market data for it here,
	 * so USD's own currently-stored `value` (USD per anchor unit) is used
	 * to bridge - it already reflects the anchor's real rate as of
	 * whenever it was last synced (by a re-peg or another currency tool).
	 *
	 * @param float $rial_per_aed
	 *
	 * @return void
	 */
	public function applyRialPerAed(float $rial_per_aed): void {
		$this->load->model('localisation/currency');

		$anchor = (string)$this->config->get('config_currency');

		$rial_per_usd = self::AED_USD_PEG * $rial_per_aed;

		// How much of each currency this tool manages equals 1 USD, in
		// real-world terms, right now.
		$per_usd = [
			'USD' => 1.0,
			'AED' => self::AED_USD_PEG,
			'IRR' => $rial_per_usd,
			'IRT' => $rial_per_usd / 10
		];

		if (isset($per_usd[$anchor])) {
			$anchor_per_usd = $per_usd[$anchor];
		} else {
			$usd_info = $this->model_localisation_currency->getCurrencyByCode('USD');
			$usd_value = (!empty($usd_info) && (float)$usd_info['value'] > 0) ? (float)$usd_info['value'] : 1.0;

			// USD's stored value is "USD per anchor unit", so its
			// reciprocal is "anchor units per USD" - exactly what we need
			// here to match the shape of $per_usd above.
			$anchor_per_usd = 1 / $usd_value;
		}

		foreach ($per_usd as $code => $value) {
			// The anchor's own value must stay pinned at exactly 1 - it is
			// never rewritten here, however today's market data happens to
			// work out, since every other currency (including the ones
			// this tool doesn't manage) is defined relative to it.
			if ($code === $anchor) {
				continue;
			}

			$this->model_localisation_currency->editValueByCode($code, $value / $anchor_per_usd);
		}
	}
}
