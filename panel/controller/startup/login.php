<?php
namespace MDcart\Admin\Controller\Startup;
/**
 * Class Login
 *
 * @package MDcart\Admin\Controller\Startup
 */
class Login extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return \MDcart\System\Engine\Action
	 */
	public function index(): ?object {
		if (isset($this->request->get['route'])) {
			$route = (string)$this->request->get['route'];
		} else {
			$route = '';
		}

		// Remove any method call for checking ignore pages.
		$pos = strrpos($route, '.');

		if ($pos !== false) {
			$route = substr($route, 0, $pos);
		}

		$ignore = [
			'common/login',
			'common/forgotten',
			'common/language',
			'common/authorize'
		];

		// User
		$this->registry->set('user', new \MDcart\System\Library\Cart\User($this->registry));

		if (!$this->user->isLogged() && !in_array($route, $ignore)) {
			return new \MDcart\System\Engine\Action('common/login');
		}

		$ignore = [
			'common/login',
			'common/logout',
			'common/forgotten',
			'common/language',
			'common/authorize',
			// Backs the standalone "/pos" (and future similar) clean-URL
			// entry point — see admin/controller/common/quicklink.php. It
			// never has a user_token of its own to pass in, and doesn't
			// need one: it reads the session directly and figures out the
			// correct token itself before redirecting onward, exactly like
			// this same check does a few lines below for common/login.
			'common/quicklink',
			'error/not_found',
			'error/permission'
		];

		if (!in_array($route, $ignore) && (!isset($this->request->get['user_token']) || !isset($this->session->data['user_token']) || ($this->request->get['user_token'] != $this->session->data['user_token']))) {
			return new \MDcart\System\Engine\Action('common/login');
		}

		return null;
	}
}
