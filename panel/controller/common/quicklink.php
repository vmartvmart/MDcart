<?php
namespace MDcart\Admin\Controller\Common;
/**
 * Class Quicklink
 *
 * Backs the clean "/pos" entry point at the site root (see the sibling
 * pos/index.php, one level above the admin app folder) — a single hop that
 * sends an already-logged-in admin user straight into a target admin
 * screen without them needing to know their current session's user_token,
 * and sends anyone not logged in to the normal login screen first.
 *
 * Deliberately exempted from the standard user_token check (see
 * controller/startup/login.php) and from the standard permission check
 * (see controller/startup/permission.php), since this controller itself
 * performs no privileged action — it only reads the session and redirects.
 * The actual destination route (e.g. sale/pos) still goes through both of
 * those checks normally once redirected to, so real access control is
 * unaffected.
 *
 * Can be loaded using $this->load->controller('common/quicklink');
 *
 * @package MDcart\Admin\Controller\Common
 */
class Quicklink extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		// Which admin route to jump to. Kept behind a strict allow-list
		// (rather than trusting the "target" query value directly) so this
		// can never be turned into an open redirect to an arbitrary route.
		$requested = isset($this->request->get['target']) ? (string)$this->request->get['target'] : 'pos';

		$allowed = [
			'pos' => 'sale/pos',
		];

		$route = $allowed[$requested] ?? $allowed['pos'];

		if ($this->user->isLogged()) {
			$this->response->redirect($this->url->link($route, 'user_token=' . $this->session->data['user_token'], true));
		} else {
			$this->response->redirect($this->url->link('common/login', '', true));
		}
	}
}
