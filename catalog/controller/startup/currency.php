<?php
namespace MDcart\Catalog\Controller\Startup;
/**
 * Class Currency
 *
 * @package MDcart\Catalog\Controller\Startup
 */
class Currency extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$code = '';

		// Currency
		$this->load->model('localisation/currency');

		$currencies = $this->model_localisation_currency->getCurrencies();

		if (isset($this->session->data['currency'])) {
			$code = $this->session->data['currency'];
		}

		if (isset($this->request->cookie['currency']) && !array_key_exists($code, $currencies)) {
			$code = $this->request->cookie['currency'];
		}

		if (!array_key_exists($code, $currencies)) {
			// config_currency_display lets the admin show customers a
			// different default currency than the one prices are actually
			// entered/computed in (config_currency, the pricing anchor -
			// see panel/controller/event/currency.php). Fall back to the
			// pricing currency itself when no separate display currency has
			// been chosen, or when it names a currency that no longer
			// exists/is disabled - this keeps existing stores behaving
			// exactly as before this setting was introduced.
			$config_currency_display = (string)$this->config->get('config_currency_display');

			if ($config_currency_display !== '' && array_key_exists($config_currency_display, $currencies)) {
				$code = $config_currency_display;
			} else {
				$code = $this->config->get('config_currency');
			}
		}

		if (!isset($this->session->data['currency']) || $this->session->data['currency'] != $code) {
			$this->session->data['currency'] = $code;
		}

		// Set a new currency cookie if the code does not match the current one
		if (!isset($this->request->cookie['currency']) || $this->request->cookie['currency'] != $code) {
			$option = [
				'expires'  => time() + 60 * 60 * 24 * 30,
				'path'     => $this->config->get('session_path'),
				'secure'   => $this->request->server['HTTPS'],
				'httponly' => true,
				'samesite' => 'Lax'
			];

			setcookie('currency', $code, $option);
		}

		$this->registry->set('currency', new \MDcart\System\Library\Cart\Currency($this->registry));
	}
}
