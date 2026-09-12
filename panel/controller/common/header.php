<?php
namespace MDcart\Admin\Controller\Common;
/**
 * Class Header
 *
 * Can be loaded using $this->load->controller('common/header');
 *
 * @package MDcart\Admin\Controller\Common
 */
class Header extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$data['lang'] = $this->language->get('code');
		$data['direction'] = $this->language->get('direction');

		$data['title'] = $this->document->getTitle();
		$data['base'] = HTTP_SERVER;
		$data['description'] = $this->document->getDescription();
		$data['keywords'] = $this->document->getKeywords();

		// Hard coding css so they can be replaced via the event's system.
		// Bootstrap ships a dedicated RTL build. Select it from the language
		// direction so switching the admin language also switches the layout.
		$data['bootstrap'] = $data['direction'] === 'rtl'
			? 'view/stylesheet/bootstrap.rtl.min.css'
			: 'view/stylesheet/bootstrap.css';
		$data['icons'] = 'view/stylesheet/fonts/fontawesome/css/all.min.css';
		$data['stylesheet'] = 'view/stylesheet/stylesheet.css';
		$data['rtl_stylesheet'] = $data['direction'] === 'rtl' ? 'view/stylesheet/rtl.css' : '';

		// Hard coding scripts so they can be replaced via the event's system.
		$data['jquery'] = 'view/javascript/jquery/jquery-3.7.1.min.js';

		// Give every <input type="date"> a Jalali calendar instead of the
		// browser's native (always Gregorian) one when the admin is in
		// Persian. The underlying field still submits a plain Gregorian
		// Y-m-d value, so no controller/model changes were needed.
		if ($data['direction'] === 'rtl') {
			$data['jalali_datepicker_css'] = 'view/javascript/jalalidatepicker/jalalidatepicker.min.css';
			$data['jalali_datepicker_js'] = 'view/javascript/jalalidatepicker/jalalidatepicker.min.js';
			$data['jalali_date_enhance_js'] = 'view/javascript/jalali-date-enhance.js';
		} else {
			$data['jalali_datepicker_css'] = '';
			$data['jalali_datepicker_js'] = '';
			$data['jalali_date_enhance_js'] = '';
		}

		$data['links'] = $this->document->getLinks();
		$data['styles'] = $this->document->getStyles();
		$data['scripts'] = $this->document->getScripts();

		// Design > Colors overrides for the admin panel itself
		// (config_color_admin_*) — see buildColorOverridesCss()'s docblock.
		$data['color_overrides_css'] = $this->buildColorOverridesCss();

		// Fav icon
		if (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
			$data['icon'] = HTTP_CATALOG . 'image/' . $this->config->get('config_icon');
		} else {
			$data['icon'] = '';
		}

		// Admin panel logo — a custom one can be set from Setting >
		// General (config_logo_admin, stored under image/ like the
		// storefront logo/icon), which survives updates since those only
		// ever add files, never delete a local one that isn't part of the
		// downloaded release. Falls back to the packaged default admin
		// logo (tracked in git) when no custom logo has been set.
		$config_logo_admin = $this->config->get('config_logo_admin');

		if ($config_logo_admin && is_file(DIR_IMAGE . html_entity_decode($config_logo_admin, ENT_QUOTES, 'UTF-8'))) {
			$data['admin_logo'] = HTTP_CATALOG . 'image/' . $config_logo_admin;
		} else {
			$data['admin_logo'] = 'view/image/logo.png';
		}

		$this->load->language('common/header');

		if (!isset($this->request->get['user_token']) || !isset($this->session->data['user_token']) || ($this->request->get['user_token'] != $this->session->data['user_token'])) {
			$data['logged'] = false;

			$data['home'] = $this->url->link('common/login');
		} else {
			$data['logged'] = true;

			$data['home'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']);

			$data['language'] = $this->load->controller('common/language');

			// Notifications
			$filter_data = [
				'start' => 0,
				'limit' => 5
			];

			$data['notifications'] = [];

			$this->load->model('tool/notification');

			$results = $this->model_tool_notification->getNotifications($filter_data);

			foreach ($results as $result) {
				$data['notifications'][] = [
					'title' => $result['title'],
					'href'  => $this->url->link('tool/notification.info', 'user_token=' . $this->session->data['user_token'] . '&notification_id=' . $result['notification_id'])
				];
			}

			$data['notification_all'] = $this->url->link('tool/notification', 'user_token=' . $this->session->data['user_token']);
			$data['notification_total'] = $this->model_tool_notification->getTotalNotifications(['filter_status' => 0]);

			$data['profile'] = $this->url->link('user/profile', 'user_token=' . $this->session->data['user_token']);

			// User
			$this->load->model('user/user');

			$user_info = $this->model_user_user->getUser($this->user->getId());

			if ($user_info) {
				$data['firstname'] = $user_info['firstname'];
				$data['lastname'] = $user_info['lastname'];
			} else {
				$data['firstname'] = '';
				$data['lastname'] = '';
			}

			// Image
			$this->load->model('tool/image');

			if ($user_info && $user_info['image'] && is_file(DIR_IMAGE . html_entity_decode($user_info['image'], ENT_QUOTES, 'UTF-8'))) {
				$data['image'] = $this->model_tool_image->resize($user_info['image'], 45, 45);
			} else {
				$data['image'] = $this->model_tool_image->resize('profile.png', 45, 45);
			}

			// Stores
			$stores = [];

			$stores[] = [
				'name' => $this->config->get('config_name'),
				'href' => HTTP_CATALOG
			];

			$this->load->model('setting/store');

			$data['stores'] = array_merge($stores, $this->model_setting_store->getStores());

			$data['logout'] = $this->url->link('common/logout', 'user_token=' . $this->session->data['user_token']);
		}

		return $this->load->view('common/header', $data);
	}

	/**
	 * Build Color Overrides Css
	 *
	 * The admin-panel counterpart of Design > Colors — see
	 * catalog/controller/common/header.php's buildColorOverridesCss() for
	 * the full rationale (kept independent of any theme, every knob
	 * optional, output after every other stylesheet). This one covers
	 * #header (the admin's own top navbar), #footer, and the admin
	 * panel's own Bootstrap build's primary/button colors — entirely
	 * separate settings from the storefront's, since the two are picked
	 * independently.
	 *
	 * @return string
	 */
	private function buildColorOverridesCss(): string {
		$css = '';

		$header_bg = (string)$this->config->get('config_color_admin_header_bg');
		$header_text = (string)$this->config->get('config_color_admin_header_text');
		$primary = (string)$this->config->get('config_color_admin_primary');
		$footer_bg = (string)$this->config->get('config_color_admin_footer_bg');
		$footer_text = (string)$this->config->get('config_color_admin_footer_text');

		if ($header_bg && oc_validate_hex_color($header_bg)) {
			$css .= '#header { background-color: ' . $header_bg . ' !important; }' . "\n";
		}

		if ($header_text && oc_validate_hex_color($header_text)) {
			$css .= '#header .navbar-nav > li > .nav-link, #header .navbar-brand { color: ' . $header_text . ' !important; }' . "\n";
		}

		// See the storefront header's identical block for why .btn-primary
		// needs its own --bs-btn-* variables redeclared (Bootstrap's
		// compiled CSS hardcodes them as literal hex, it doesn't read
		// --bs-primary at the component level) and why hover/active shades
		// are derived rather than left flat.
		if ($primary && oc_validate_hex_color($primary)) {
			$hover = oc_color_shade($primary, -15);
			$active = oc_color_shade($primary, -25);

			$css .= ':root { --bs-primary: ' . $primary . '; --bs-primary-rgb: ' . oc_hex_to_rgb($primary) . '; --bs-link-color: ' . $primary . '; --bs-link-color-rgb: ' . oc_hex_to_rgb($primary) . '; --bs-link-hover-color: ' . $hover . '; }' . "\n";
			$css .= '.btn-primary { --bs-btn-bg: ' . $primary . '; --bs-btn-border-color: ' . $primary . '; --bs-btn-hover-bg: ' . $hover . '; --bs-btn-hover-border-color: ' . $hover . '; --bs-btn-active-bg: ' . $active . '; --bs-btn-active-border-color: ' . $active . '; --bs-btn-disabled-bg: ' . $primary . '; --bs-btn-disabled-border-color: ' . $primary . '; }' . "\n";
		}

		// #footer has no background/text color of its own by default (it
		// just reserves height and centers text) — these knobs add one
		// rather than override an existing rule.
		if ($footer_bg && oc_validate_hex_color($footer_bg)) {
			$css .= '#footer { background-color: ' . $footer_bg . ' !important; }' . "\n";
		}

		if ($footer_text && oc_validate_hex_color($footer_text)) {
			$css .= '#footer { color: ' . $footer_text . ' !important; }' . "\n";
		}

		return $css;
	}
}
