<?php
namespace MDcart\Admin\Controller\Startup;
/**
 * Class Permission
 *
 * @package MDcart\Admin\Controller\Startup
 */
class Permission extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return \MDcart\System\Engine\Action|null
	 */
	public function index(): ?\MDcart\System\Engine\Action {
		if (isset($this->request->get['route'])) {
			$pos = strrpos($this->request->get['route'], '.');

			if ($pos === false) {
				$route = $this->request->get['route'];
			} else {
				$route = substr($this->request->get['route'], 0, $pos);
			}

			// We want to ignore some pages from having its permission checked.
			$ignore = [
				'common/dashboard',
				'common/login',
				'common/logout',
				'common/forgotten',
				'common/authorize',
				'common/language',
				// See controller/startup/login.php for why this one is
				// exempt too — it performs no privileged action itself,
				// it only redirects onward to a route that is checked
				// normally (e.g. sale/pos).
				'common/quicklink',
				'error/not_found',
				'error/permission'
			];

			if (!in_array($route, $ignore) && !$this->user->hasPermission('access', $route)) {
				return new \MDcart\System\Engine\Action('error/permission');
			}
		}

		return null;
	}
}
