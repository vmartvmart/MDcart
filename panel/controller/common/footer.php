<?php
namespace MDcart\Admin\Controller\Common;
/**
 * Class Footer
 *
 * Can be loaded using $this->load->controller('common/footer');
 *
 * @package MDcart\Admin\Controller\Common
 */
class Footer extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('common/footer');

		if ($this->user->isLogged() && isset($this->request->get['user_token']) && ($this->request->get['user_token'] == $this->session->data['user_token'])) {
			// Our own app version (see the VERSION file at the root of the
			// install), not OpenCart core's VERSION constant — the two are
			// independent, and the admin footer should reflect ours.
			$app_version = is_file(MCART_ROOT . 'VERSION') ? trim((string)file_get_contents(MCART_ROOT . 'VERSION')) : '';

			$data['text_version'] = $app_version ? sprintf($this->language->get('text_version'), $app_version) : '';
		} else {
			$data['text_version'] = '';
		}

		$data['bootstrap'] = 'view/javascript/bootstrap/js/bootstrap.bundle.min.js';

		return $this->load->view('common/footer', $data);
	}
}
