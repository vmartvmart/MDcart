<?php
namespace MDcart\Admin\Controller\Event;
/**
 * Class Currency
 *
 * @package MDcart\Admin\Controller\Event
 */
class Currency extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * Auto update currencies
	 *
	 * model/setting/setting/editSetting
	 * model/localisation/currency/addCurrency
	 * model/localisation/currency/editCurrency
	 *
	 * @param string            $route
	 * @param array<int, mixed> $args
	 * @param mixed             $output
	 *
	 * @return void
	 */
	public function index(string &$route, array &$args, &$output): void {
		if ($route == 'model/setting/setting/editSetting' && $args[0] == 'config' && isset($args[1]['config_currency'])) {
			$currency = $args[1]['config_currency'];
		} else {
			$currency = $this->config->get('config_currency');
		}

		// Re-peg: `oc_currency.value` is only meaningful relative to
		// whichever currency is treated as the anchor (value = 1.00000000).
		// The rest of MDcart (product prices, order totals, the accounting
		// journal, Purchase Invoice FX handling) all assumes that anchor IS
		// config_currency - but nothing enforced that until now, so an
		// admin could change config_currency in Settings > General and
		// every stored price would silently keep being interpreted in
		// whatever currency used to be the anchor (USD by default). When
		// the admin is about to switch the default currency to a new one,
		// pin that new currency's value to exactly 1 and rescale every
		// other currency by the same factor so their real cross-rates
		// (USD/EUR/AED/IRR/...) are preserved, just re-expressed relative
		// to the new anchor. This mirrors what stock OpenCart's ECB
		// extension does for the currencies it knows about, but applies
		// unconditionally so it also covers IRR/IRT/AED, which ECB does
		// not track.
		//
		// This does NOT touch already-stored product/order prices - those
		// numbers stay exactly as typed. Switching the default currency is
		// still something to do deliberately (ideally once, early on),
		// since any price entered before the switch was denominated in the
		// old anchor and will read as if it were in the new one afterward.
		if ($route == 'model/setting/setting/editSetting' && $args[0] == 'config' && isset($args[1]['config_currency'])) {
			$old_currency = (string)$this->config->get('config_currency');
			$new_currency = (string)$args[1]['config_currency'];

			if ($new_currency !== '' && $new_currency !== $old_currency) {
				$this->load->model('localisation/currency');

				$anchor_info = $this->model_localisation_currency->getCurrencyByCode($new_currency);

				if ($anchor_info && (float)$anchor_info['value'] > 0) {
					$scale = (float)$anchor_info['value'];

					foreach ($this->model_localisation_currency->getCurrencies() as $result) {
						if ((float)$result['value'] <= 0) {
							continue;
						}

						$new_value = ($result['code'] === $new_currency) ? 1.00000000 : ((float)$result['value'] / $scale);

						$this->model_localisation_currency->editValueByCode($result['code'], $new_value);
					}
				}
			}
		}

		// Extension
		$this->load->model('setting/extension');

		$extension_info = $this->model_setting_extension->getExtensionByCode('currency', $this->config->get('config_currency_engine'));

		if ($extension_info) {
			$this->load->controller('extension/' . $extension_info['extension'] . '/currency/' . $extension_info['code'] . '.currency', $currency);
		}
	}
}
