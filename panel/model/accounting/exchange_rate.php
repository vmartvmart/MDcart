<?php
namespace Opencart\Admin\Model\Accounting;
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
 * @package Opencart\Admin\Model\Accounting
 */
class ExchangeRate extends \Opencart\System\Engine\Model {
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
		// table (IRR.value and AED.value are both "relative to USD", so
		// their ratio is the Rial/AED cross rate).
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
	 * Derivation: the store's currency table stores every currency's value
	 * relative to USD (the base currency always has value = 1). We only
	 * ever observe one real market data point at a time - the AED/Rial
	 * cross rate - but AED is reliably pegged to USD at a fixed, known
	 * ratio (self::AED_USD_PEG). Multiplying the two gives the Rial/USD
	 * rate implied by today's AED/Rial market price:
	 *
	 *     Rial/USD = (AED/USD peg) x (Rial per AED, just fetched/entered)
	 *
	 * That is then IRR's new `value`. Toman has no ISO currency code and is
	 * simply Rial / 10 for display, per how this store represents it, so
	 * IRT's `value` is always exactly IRR's value / 10 - never fetched or
	 * entered separately. AED's own `value` is re-asserted at the fixed peg
	 * every time, so it never drifts even if it was hand-edited elsewhere.
	 *
	 * @param float $rial_per_aed
	 *
	 * @return void
	 */
	public function applyRialPerAed(float $rial_per_aed): void {
		$this->load->model('localisation/currency');

		$rial_per_usd = self::AED_USD_PEG * $rial_per_aed;

		$this->model_localisation_currency->editValueByCode('IRR', $rial_per_usd);
		$this->model_localisation_currency->editValueByCode('IRT', $rial_per_usd / 10);
		$this->model_localisation_currency->editValueByCode('AED', self::AED_USD_PEG);
	}
}
