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
			$full_route = (string)$this->request->get['route'];
		} else {
			$full_route = '';
		}

		// Remove any method call for checking ignore pages.
		$route = $full_route;

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
			// Remember exactly where the user was headed (route + every
			// other GET param, minus the now-meaningless user_token) so
			// common/login can send them back here once they've logged
			// back in, instead of always landing on the dashboard.
			$this->rememberCurrentPage($full_route);

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

		if (!in_array($route, $ignore) && (!isset($this->request->get['user_token']) || !isset($this->session->data['user_token']))) {
			$this->rememberCurrentPage($full_route);

			return new \MDcart\System\Engine\Action('common/login');
		}

		if (!in_array($route, $ignore) && ($this->request->get['user_token'] != $this->session->data['user_token'])) {
			// The user IS genuinely logged in - isLogged() above already
			// confirmed a valid session - this is just a stale token in
			// THIS tab's URL. That happens whenever the same browser logs
			// in again in a different tab, since every login rotates the
			// token. Rather than forcing another username/password prompt
			// in this tab too, silently bounce it to the exact same page
			// with the now-current token, so every open tab "catches up"
			// to whichever tab most recently logged in, with no re-login
			// and no loss of place.
			$this->response->redirect($this->currentPageLink($full_route, (string)$this->session->data['user_token']));
		}

		return null;
	}

	/**
	 * Save the page the user was trying to reach (route + GET args,
	 * minus the stale user_token) so common/login can return them to it
	 * after a fresh login, instead of always landing on the dashboard.
	 *
	 * @param string $full_route
	 *
	 * @return void
	 */
	private function rememberCurrentPage(string $full_route): void {
		if ($full_route === '' || $full_route === 'common/login') {
			unset($this->session->data['redirect_after_login']);

			return;
		}

		$args = $this->request->get;

		unset($args['route'], $args['user_token']);

		$this->session->data['redirect_after_login'] = [
			'route' => $full_route,
			'args'  => $args
		];
	}

	/**
	 * Rebuild the current page's URL using a given (fresh) user_token.
	 *
	 * @param string $full_route
	 * @param string $user_token
	 *
	 * @return string
	 */
	private function currentPageLink(string $full_route, string $user_token): string {
		$args = $this->request->get;

		unset($args['route'], $args['user_token']);

		$args['user_token'] = $user_token;

		return $this->url->link($full_route, $args, true);
	}
}
