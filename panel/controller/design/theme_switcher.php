<?php
namespace MDcart\Admin\Controller\Design;
/**
 * Class ThemeSwitcher
 *
 * A friendly "pick a storefront theme and confirm" UI built on OpenCart's real
 * per-route theme override mechanism (oc_theme + the 'event/theme' event,
 * registered against the broad 'view/' trigger so it applies to any route).
 *
 * Each named theme is a pair of preset Twig files under
 * admin/view/theme_presets/{name}_header.twig and {name}_home.twig, plus a
 * self-hosted stylesheet at catalog/view/stylesheet/theme-{name}.css. The
 * list of available themes is discovered from disk (see getAvailableThemes()),
 * not hardcoded — Design > Theme Manager adds/removes theme files, and
 * whatever is on disk shows up here automatically.
 *
 * Picking a theme copies that preset's content into the two oc_theme rows
 * (common/header, common/home) and enables them; picking "Default" disables
 * both rows, which falls back to the untouched, standard templates. Every
 * other route — product, category, checkout, etc. — is never touched; each
 * theme's whole visual identity comes from its own stylesheet cascading off
 * a body class set in the header preset, so there is never any
 * duplicated/hardcoded page content to drift out of sync with the real store.
 *
 * The currently active theme name is tracked separately in
 * other_theme_switcher_active (a plain setting), since oc_theme itself has no
 * "which preset is this" label — only route/status.
 *
 * Can be loaded using $this->load->controller('design/theme_switcher');
 *
 * @package MDcart\Admin\Controller\Design
 */
class ThemeSwitcher extends \MDcart\System\Engine\Controller {
	/**
	 * @var string[] the oc_theme routes every named theme overrides
	 */
	private array $routes = ['common/header', 'common/home'];

	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('design/theme_switcher');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']),
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('design/theme_switcher', 'user_token=' . $this->session->data['user_token']),
		];

		// The third argument (true) is required so the URL keeps a raw "&" for
		// use in JavaScript, instead of the HTML-escaped "&amp;" that url->link()
		// returns by default for use in href="" attributes.
		$data['save'] = $this->url->link('design/theme_switcher.save', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];

		$data['active_theme'] = (string)($this->config->get('other_theme_switcher_active') ?: 'default');

		$swatches = [
			'whoop'        => ['swatch' => '#000000', 'swatch_text' => '#ffffff'],
			'stockland'    => ['swatch' => 'linear-gradient(135deg,#eef3ff 0%,#ffffff 100%)', 'swatch_text' => '#1a56db'],
			'persiantvbox' => ['swatch' => 'radial-gradient(circle at 30% 20%,rgba(236,72,153,.5),transparent 60%),radial-gradient(circle at 75% 75%,rgba(139,92,246,.5),transparent 60%),#0b0710', 'swatch_text' => '#f5f0fa'],
			'digikala'     => ['swatch' => 'linear-gradient(120deg,#ff7a59 0%,#ef394e 55%,#c92338 100%)', 'swatch_text' => '#ffffff'],
			'aliexpress'   => ['swatch' => 'linear-gradient(120deg,#8b0000 0%,#c40000 50%,#ff4747 100%)', 'swatch_text' => '#ffffff'],
		];

		$themes = $this->getAvailableThemes();
		$is_persian = $this->language->get('code') === 'fa';

		foreach ($themes as $name => &$theme) {
			if ($is_persian && $theme['label_fa']) {
				$theme['label'] = $theme['label_fa'];
			}

			if (isset($swatches[$name])) {
				$theme += $swatches[$name];
			} else {
				$hue = crc32($name) % 360;

				$theme['swatch'] = 'hsl(' . $hue . ', 55%, 45%)';
				$theme['swatch_text'] = '#ffffff';
			}
		}

		unset($theme);

		$data['themes'] = $themes;
		$data['text_theme_manager'] = $this->language->get('text_theme_manager');
		$data['text_session_expired'] = $this->language->get('text_session_expired');
		$data['text_unknown_error'] = $this->language->get('text_unknown_error');

		if ($this->user->getGroupId() === 1 && $this->user->hasPermission('access', 'design/theme_manager')) {
			$data['theme_manager'] = $this->url->link('design/theme_manager', 'user_token=' . $this->session->data['user_token']);
		} else {
			$data['theme_manager'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/theme_switcher', $data));
	}

	/**
	 * Save (AJAX)
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('design/theme_switcher');

		$json = [];

		if (!$this->user->hasPermission('modify', 'design/theme_switcher')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$theme = (string)($this->request->post['theme'] ?? '');
		$themes = $this->getAvailableThemes();

		if (!$json && $theme !== 'default' && !isset($themes[$theme])) {
			$json['error'] = $this->language->get('error_invalid_theme');
		}

		if (!$json) {
			$this->load->model('design/theme');
			$this->load->model('setting/setting');

			if ($theme === 'default') {
				foreach ($this->routes as $route) {
					$match = $this->findRoute($route);

					if ($match) {
						$this->model_design_theme->editTheme((int)$match['theme_id'], [
							'store_id' => 0,
							'route'    => $route,
							'code'     => $match['code'],
							'status'   => 0,
						]);
					}
				}

				$this->model_setting_setting->editSetting('other_theme_switcher', ['other_theme_switcher_active' => 'default']);

				$json['success'] = $this->language->get('text_success');
			} else {
				$preset = $themes[$theme];
				$missing = false;

				foreach (['header' => 'common/header', 'home' => 'common/home'] as $key => $route) {
					$file = DIR_APPLICATION . 'view/theme_presets/' . $preset[$key];

					if (!is_file($file)) {
						$missing = true;

						continue;
					}

					$code = (string)file_get_contents($file);
					$match = $this->findRoute($route);

					if ($match) {
						$this->model_design_theme->editTheme((int)$match['theme_id'], [
							'store_id' => 0,
							'route'    => $route,
							'code'     => $code,
							'status'   => 1,
						]);
					} else {
						$this->model_design_theme->addTheme([
							'store_id' => 0,
							'route'    => $route,
							'code'     => $code,
							'status'   => 1,
						]);
					}
				}

				if ($missing) {
					$json['error'] = $this->language->get('error_not_installed');
				} else {
					$this->model_setting_setting->editSetting('other_theme_switcher', ['other_theme_switcher_active' => $theme]);

					$json['success'] = $this->language->get('text_success');
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Discover installed themes from admin/view/theme_presets/*_header.twig, keeping
	 * only names that also have a matching _home.twig and a self-hosted stylesheet.
	 *
	 * @return array<string, array<string, string>> theme name => ['header'=>..., 'home'=>..., 'label'=>...]
	 */
	public static function getAvailableThemes(): array {
		$themes = [];

		foreach (glob(DIR_APPLICATION . 'view/theme_presets/*_header.twig') ?: [] as $file) {
			$name = basename($file, '_header.twig');

			if (!preg_match('/^[a-z0-9_]{2,30}$/', $name)) {
				continue;
			}

			$home_file = DIR_APPLICATION . 'view/theme_presets/' . $name . '_home.twig';
			$css_file = MCART_ROOT . 'catalog/view/stylesheet/theme-' . $name . '.css';

			if (!is_file($home_file) || !is_file($css_file)) {
				continue;
			}

			$label = ucwords(str_replace(['_', '-'], ' ', $name));
			$label_fa = '';

			$manifest_file = DIR_APPLICATION . 'view/theme_presets/' . $name . '.json';

			if (is_file($manifest_file)) {
				$manifest = json_decode((string)file_get_contents($manifest_file), true);

				if (!empty($manifest['label'])) {
					$label = (string)$manifest['label'];
				}

				if (!empty($manifest['label_fa'])) {
					$label_fa = (string)$manifest['label_fa'];
				}
			}

			$themes[$name] = [
				'header'   => $name . '_header.twig',
				'home'     => $name . '_home.twig',
				'label'    => $label,
				'label_fa' => $label_fa,
			];
		}

		return $themes;
	}

	/**
	 * @param string $route
	 *
	 * @return array<string, mixed>|null
	 */
	private function findRoute(string $route): ?array {
		$this->load->model('design/theme');

		foreach ($this->model_design_theme->getThemes(0, 200) as $row) {
			if ($row['route'] === $route && (int)$row['store_id'] === 0) {
				return $row;
			}
		}

		return null;
	}
}
