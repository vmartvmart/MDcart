<?php
namespace MDcart\Admin\Controller\Design;
/**
 * Class Color
 *
 * Design > Colors — lets the admin pick a handful of colors for both the
 * storefront and this admin panel, independent of whichever Design > Theme
 * Switcher preset (or the default theme) is active. Every field is
 * optional: leaving one off (its "use a custom color" switch unchecked)
 * means the theme's own color is used untouched, so a store that never
 * opens this page looks exactly as it did before this page existed.
 *
 * All values are stored as ordinary settings (config_color_store_* and
 * config_color_admin_*, code "config", same as every other Setting >
 * General field), and turned into actual CSS by
 * catalog/controller/common/header.php and panel/controller/common/header.php
 * — see buildColorOverridesCss() in each for exactly which selectors each
 * knob touches and why.
 *
 * Can be loaded using $this->load->controller('design/color');
 *
 * @package MDcart\Admin\Controller\Design
 */
class Color extends \MDcart\System\Engine\Controller {
	/**
	 * @var string[] every config_color_* key this page reads and writes
	 */
	private array $fields = [
		'config_color_store_menu_bg',
		'config_color_store_menu_text',
		'config_color_store_primary',
		'config_color_store_footer_bg',
		'config_color_store_footer_text',
		'config_color_store_card_bg',
		'config_color_admin_header_bg',
		'config_color_admin_header_text',
		'config_color_admin_primary',
		'config_color_admin_footer_bg',
		'config_color_admin_footer_text',
	];

	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('design/color');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']),
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('design/color', 'user_token=' . $this->session->data['user_token']),
		];

		// The third argument (true) keeps a raw "&" for use in JavaScript,
		// instead of the HTML-escaped "&amp;" url->link() returns by
		// default for href="" attributes.
		$data['save'] = $this->url->link('design/color.save', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];

		foreach ($this->fields as $field) {
			$data[$field] = (string)$this->config->get($field);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/color', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('design/color');

		$json = [];

		if (!$this->user->hasPermission('modify', 'design/color')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$values = [];

		if (!$json) {
			foreach ($this->fields as $field) {
				$value = (string)($this->request->post[$field] ?? '');

				// An unchecked "use a custom color" switch means its paired
				// <input type="color"> is disabled, so browsers omit it
				// from the submitted form entirely (never sent as empty) —
				// this is what actually clears a previously-set override.
				if ($value !== '' && !oc_validate_hex_color($value)) {
					$json['error'] = sprintf($this->language->get('error_color'), $this->language->get('entry_' . substr($field, strlen('config_color_'))));

					break;
				}

				$values[$field] = $value;
			}
		}

		if (!$json) {
			$this->load->model('setting/setting');

			// A dedicated "config_color" settings group (not "config"
			// itself) — every key below still starts with "config_" so
			// $this->config->get() finds them exactly like any other
			// setting (startup/setting loads every row regardless of its
			// code group), but editSetting() deletes every existing row of
			// the group it's given before reinserting, so saving into
			// "config" here would have wiped out every unrelated general
			// setting (site name, email, the logos, ...) instead of only
			// these 11 color fields.
			$this->model_setting_setting->editSetting('config_color', $values);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
