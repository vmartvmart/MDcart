<?php
namespace MDcart\Admin\Controller\Design;
/**
 * Class ThemeManager
 *
 * Super-admin-only (Administrator group) page to add or remove storefront
 * themes for Design > Theme (theme_switcher). A theme is a package of 3
 * files sharing one name: {name}_header.twig, {name}_home.twig (both under
 * admin/view/theme_presets/) and theme-{name}.css (under
 * catalog/view/stylesheet/). Uploading a .zip containing exactly those 3
 * files (plus an optional theme.json label manifest) installs a new theme
 * that immediately shows up in the theme switcher — no code changes needed,
 * since theme_switcher.php discovers themes from disk.
 *
 * Can be loaded using $this->load->controller('design/theme_manager');
 *
 * @package MDcart\Admin\Controller\Design
 */
class ThemeManager extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('design/theme_manager');

		if (!$this->isSuperAdmin()) {
			$this->response->redirect($this->url->link('error/permission', 'user_token=' . $this->session->data['user_token']));

			return;
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']),
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('design/theme_manager', 'user_token=' . $this->session->data['user_token']),
		];

		$data['upload'] = $this->url->link('design/theme_manager.upload', 'user_token=' . $this->session->data['user_token'], true);
		$data['delete'] = $this->url->link('design/theme_manager.delete', 'user_token=' . $this->session->data['user_token'], true);
		$data['theme_switcher'] = $this->url->link('design/theme_switcher', 'user_token=' . $this->session->data['user_token']);
		$data['user_token'] = $this->session->data['user_token'];
		$data['error_permission'] = $this->language->get('error_permission');

		$active_theme = (string)($this->config->get('other_theme_switcher_active') ?: 'default');

		$themes = [];

		foreach (ThemeSwitcher::getAvailableThemes() as $name => $theme) {
			$themes[] = [
				'name'   => $name,
				'label'  => $theme['label'],
				'active' => $name === $active_theme,
			];
		}

		$data['themes'] = $themes;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/theme_manager', $data));
	}

	/**
	 * Upload (AJAX) — installs a new theme from an uploaded .zip package.
	 *
	 * @return void
	 */
	public function upload(): void {
		$this->load->language('design/theme_manager');

		$json = [];

		if (!$this->isSuperAdmin()) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json && (empty($this->request->files['file']) || !is_uploaded_file($this->request->files['file']['tmp_name']))) {
			$json['error'] = $this->language->get('error_upload');
		}

		if (!$json && strtolower(pathinfo($this->request->files['file']['name'], PATHINFO_EXTENSION)) !== 'zip') {
			$json['error'] = $this->language->get('error_not_zip');
		}

		if (!$json && $this->request->files['file']['size'] > 5 * 1024 * 1024) {
			$json['error'] = $this->language->get('error_too_large');
		}

		if (!$json) {
			$zip = new \ZipArchive();

			if ($zip->open($this->request->files['file']['tmp_name']) !== true) {
				$json['error'] = $this->language->get('error_not_zip');
			} else {
				$entries = [];

				for ($i = 0; $i < $zip->numFiles; $i++) {
					$entries[] = $zip->getNameIndex($i);
				}

				$name = null;

				foreach ($entries as $entry) {
					if (preg_match('/^([a-z0-9_]{2,30})_header\.twig$/', $entry, $matches)) {
						$name = $matches[1];

						break;
					}
				}

				$required = $name ? [
					$name . '_header.twig',
					$name . '_home.twig',
					'theme-' . $name . '.css',
				] : [];

				$missing = array_filter($required, fn ($file) => !in_array($file, $entries, true));

				if (!$name || $missing) {
					$json['error'] = $this->language->get('error_package_invalid');
				} elseif (in_array($name, ['default'], true) || is_file(DIR_APPLICATION . 'view/theme_presets/' . $name . '_header.twig')) {
					$json['error'] = $this->language->get('error_theme_exists');
				} else {
					$tmp_dir = sys_get_temp_dir() . '/oc_theme_upload_' . uniqid();

					mkdir($tmp_dir, 0755, true);
					$zip->extractTo($tmp_dir, array_merge($required, array_filter($entries, fn ($e) => $e === $name . '.json')));

					copy($tmp_dir . '/' . $name . '_header.twig', DIR_APPLICATION . 'view/theme_presets/' . $name . '_header.twig');
					copy($tmp_dir . '/' . $name . '_home.twig', DIR_APPLICATION . 'view/theme_presets/' . $name . '_home.twig');
					copy($tmp_dir . '/theme-' . $name . '.css', MCART_ROOT . 'catalog/view/stylesheet/theme-' . $name . '.css');

					if (is_file($tmp_dir . '/' . $name . '.json')) {
						copy($tmp_dir . '/' . $name . '.json', DIR_APPLICATION . 'view/theme_presets/' . $name . '.json');
					}

					array_map('unlink', glob($tmp_dir . '/*'));
					rmdir($tmp_dir);

					$json['success'] = $this->language->get('text_upload_success');
				}

				$zip->close();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Delete (AJAX) — removes an installed theme's 3 files.
	 *
	 * @return void
	 */
	public function delete(): void {
		$this->load->language('design/theme_manager');

		$json = [];

		if (!$this->isSuperAdmin()) {
			$json['error'] = $this->language->get('error_permission');
		}

		$name = (string)($this->request->post['theme'] ?? '');

		if (!$json && !preg_match('/^[a-z0-9_]{2,30}$/', $name)) {
			$json['error'] = $this->language->get('error_invalid_theme');
		}

		if (!$json) {
			$active_theme = (string)($this->config->get('other_theme_switcher_active') ?: 'default');

			if ($name === $active_theme) {
				$json['error'] = $this->language->get('error_theme_active');
			} elseif (!is_file(DIR_APPLICATION . 'view/theme_presets/' . $name . '_header.twig')) {
				$json['error'] = $this->language->get('error_invalid_theme');
			} else {
				foreach ([
					DIR_APPLICATION . 'view/theme_presets/' . $name . '_header.twig',
					DIR_APPLICATION . 'view/theme_presets/' . $name . '_home.twig',
					DIR_APPLICATION . 'view/theme_presets/' . $name . '.json',
					MCART_ROOT . 'catalog/view/stylesheet/theme-' . $name . '.css',
				] as $file) {
					if (is_file($file)) {
						unlink($file);
					}
				}

				$json['success'] = $this->language->get('text_delete_success');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Only the Administrator group (user_group_id 1) may manage themes, regardless
	 * of what a group's own permission table says — this is deliberately stricter
	 * than the normal hasPermission() check.
	 *
	 * @return bool
	 */
	private function isSuperAdmin(): bool {
		return $this->user->isLogged() && $this->user->getGroupId() === 1;
	}
}
