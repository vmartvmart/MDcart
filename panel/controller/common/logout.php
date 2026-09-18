<?php
namespace MDcart\Admin\Controller\Common;
/**
 * Class Logout
 *
 * Can be loaded using $this->load->controller('common/logout');
 *
 * @package MDcart\Admin\Controller\Common
 */
class Logout extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->user->logout();

		unset($this->session->data['user_token'], $this->session->data['redirect_after_login']);

		$this->response->redirect($this->url->link('common/login', '', true));
	}
}
