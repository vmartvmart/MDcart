<?php
namespace MDcart\Catalog\Controller\Startup;
/**
 * Class Maintenance
 *
 * @package MDcart\Catalog\Controller\Startup
 */
class Maintenance extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return \MDcart\System\Engine\Action|null
	 */
	public function index(): ?\MDcart\System\Engine\Action {
		if ($this->config->get('config_maintenance')) {
			// Route
			if (isset($this->request->get['route'])) {
				$route = $this->request->get['route'];
			} else {
				$route = $this->config->get('action_default');
			}

			$ignore = [
				'common/language/language',
				'common/currency/currency'
			];

			// Show site if logged in as admin
			$user = new \MDcart\System\Library\Cart\User($this->registry);

			if (substr($route, 0, 3) != 'api' && !in_array($route, $ignore) && !$user->isLogged()) {
				return new \MDcart\System\Engine\Action('common/maintenance');
			}
		}

		return null;
	}
}
