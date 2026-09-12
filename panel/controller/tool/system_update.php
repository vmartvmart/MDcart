<?php
namespace Opencart\Admin\Controller\Tool;
/**
 * Class SystemUpdate
 *
 * Admin page for checking and applying updates from the private
 * distribution repository (see Opencart\Admin\Model\Tool\SystemUpdate).
 *
 * @package Opencart\Admin\Controller\Tool
 */
class SystemUpdate extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('tool/system_update');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('tool/system_update', 'user_token=' . $this->session->data['user_token'])
		];

		// The final "true" here matters: it tells Url::link() to return the
		// URL with a raw "&" between parameters instead of "&amp;" (which
		// is what you want for an href in HTML, but not for a URL that's
		// going straight into a JS string and used as an AJAX request URL
		// — with "&amp;" there, "user_token" never actually arrives as its
		// own query parameter, so every request below looked like it had
		// no token at all and got silently bounced to the login screen
		// instead of running, which is exactly the bug behind "I clicked
		// save and nothing happened".
		// Note: no "save" URL/data here on purpose — the repo/branch/token/
		// admin_dir settings form was removed from this page entirely (see
		// the twig). Those are the same for every customer install and are
		// set directly in the database instead of through a customer-
		// visible UI; see saveSettings() in the model, still there and
		// still callable, just no longer wired to a form here.
		$data['check'] = $this->url->link('tool/system_update.check', 'user_token=' . $this->session->data['user_token'], true);
		$data['baseline'] = $this->url->link('tool/system_update.baseline', 'user_token=' . $this->session->data['user_token'], true);
		$data['apply'] = $this->url->link('tool/system_update.apply', 'user_token=' . $this->session->data['user_token'], true);
		$data['progress'] = $this->url->link('tool/system_update.progress', 'user_token=' . $this->session->data['user_token'], true);
		$data['backups'] = $this->url->link('tool/system_update.backups', 'user_token=' . $this->session->data['user_token'], true);
		$data['restore'] = $this->url->link('tool/system_update.restore', 'user_token=' . $this->session->data['user_token'], true);
		$data['back'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']);

		$this->load->model('tool/system_update');

		$settings = $this->model_tool_system_update->getSettings();

		$data['current_commit'] = $settings['current_commit'];
		$data['current_version'] = $this->model_tool_system_update->getLocalVersion();
		$data['applied_at'] = $settings['applied_at'];
		// Lets the page format dates in the admin's own calendar (Jalali
		// for Persian, Gregorian otherwise) instead of always one or the
		// other — see scFormatDate() in the template.
		$data['language_code'] = $this->config->get('language_code');

		$data['backup_list_html'] = $this->load->view('tool/system_update_backups', [
			'backup_list' => $this->formatBackups($this->model_tool_system_update->listBackups()),
			'restore'     => $data['restore'],
		]);

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('tool/system_update', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		ob_start();

		$this->load->language('tool/system_update');

		$json = [];

		if (!$this->user->hasPermission('modify', 'tool/system_update')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$repo = isset($this->request->post['repo']) ? trim((string)$this->request->post['repo']) : '';
		$branch = isset($this->request->post['branch']) ? trim((string)$this->request->post['branch']) : '';
		$token = isset($this->request->post['token']) ? trim((string)$this->request->post['token']) : '';
		$admin_dir = isset($this->request->post['admin_dir']) ? trim((string)$this->request->post['admin_dir']) : 'admin';

		if (!$json && !$repo) {
			$json['error'] = $this->language->get('error_repo');
		}

		if (!$json && !$token) {
			$json['error'] = $this->language->get('error_token');
		}

		if (!$json) {
			$this->load->model('tool/system_update');

			$this->model_tool_system_update->saveSettings($repo, $branch, $token, $admin_dir ?: 'admin');

			$json['success'] = $this->language->get('text_success');
		}

		$this->discardStrayOutput();

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Check
	 *
	 * @return void
	 */
	public function check(): void {
		ob_start();

		$this->load->language('tool/system_update');

		$json = [];

		if (!$this->user->hasPermission('access', 'tool/system_update')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('tool/system_update');

			$result = $this->model_tool_system_update->checkForUpdate();

			if (isset($result['error'])) {
				$json['error'] = $this->formatError($result);
			} else {
				$json = $result;
				$json['up_to_date'] = ($result['current_commit'] !== '') && ($result['current_commit'] === $result['latest_commit']);
				$json['has_baseline'] = ($result['current_commit'] !== '');
			}
		}

		$this->discardStrayOutput();

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Baseline
	 *
	 * Records the latest upstream commit as "current" without downloading
	 * anything — for an install whose files already match the repo.
	 *
	 * @return void
	 */
	public function baseline(): void {
		ob_start();

		$this->load->language('tool/system_update');

		$json = [];

		if (!$this->user->hasPermission('modify', 'tool/system_update')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('tool/system_update');

			$result = $this->model_tool_system_update->checkForUpdate();

			if (isset($result['error'])) {
				$json['error'] = $this->formatError($result);
			} elseif (!$result['latest_commit']) {
				$json['error'] = $this->language->get('error_connection');
			} else {
				$this->model_tool_system_update->setBaseline($result['latest_commit']);

				$json['success'] = $this->language->get('text_baseline_success');
				$json['current_commit'] = $result['latest_commit'];
				$json['current_version'] = $this->model_tool_system_update->getLocalVersion();
			}
		}

		$this->discardStrayOutput();

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Apply
	 *
	 * @return void
	 */
	public function apply(): void {
		ob_start();

		$this->load->language('tool/system_update');

		$json = [];

		if (!$this->user->hasPermission('modify', 'tool/system_update')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('tool/system_update');

			$result = $this->model_tool_system_update->applyUpdate();

			if (isset($result['error']) && $result['error'] !== 'write') {
				$json['error'] = $this->formatError($result);
			} elseif (isset($result['error']) && $result['error'] === 'write') {
				// Files were copied but a handful failed (permissions) — still record
				// the new commit since the update mostly applied; surface a warning.
				$applied_version = $this->model_tool_system_update->getLocalVersion();
				$json['warning'] = $this->language->get('error_write');
				$json['success'] = $this->language->get('text_apply_success') . ' ' . ($applied_version ?: substr($result['applied_commit'], 0, 10));
				$json['applied_commit'] = $result['applied_commit'];
				$json['applied_version'] = $applied_version;
			} else {
				$applied_version = $this->model_tool_system_update->getLocalVersion();
				$json['success'] = $this->language->get('text_apply_success') . ' ' . ($applied_version ?: substr($result['applied_commit'], 0, 10));
				$json['applied_commit'] = $result['applied_commit'];
				$json['applied_version'] = $applied_version;
			}

			if (isset($result['backup_id'])) {
				$json['backup_id'] = $result['backup_id'];
			}
		}

		$this->discardStrayOutput();

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Progress
	 *
	 * Polled by the page (every ~1 second) while apply() is running, so
	 * the admin sees a live progress bar instead of one static "please
	 * wait" message for however long the whole request takes. Deliberately
	 * a plain GET with only 'access' permission (same as check()) — it
	 * only reads a small status file, it changes nothing, so it's safe to
	 * call this as often as the page needs.
	 *
	 * This works concurrently with the long-running apply() request
	 * because sessions here are stored in the database (see
	 * system/library/session/db.php: a plain read/REPLACE, no row
	 * locking), unlike PHP's native file-based sessions where a second
	 * request on the same session would otherwise queue behind the first
	 * until it finished — which would have made a progress poll pointless.
	 *
	 * @return void
	 */
	public function progress(): void {
		ob_start();

		$this->load->language('tool/system_update');

		$json = [];

		if (!$this->user->hasPermission('access', 'tool/system_update')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('tool/system_update');

			$json = $this->model_tool_system_update->getProgress();
		}

		$this->discardStrayOutput();

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Localize Countries
	 *
	 * One-time data fix (not exposed in any menu — hit directly by URL):
	 * fills in real Persian names for every country (they were seeded
	 * with the English name copied into the Persian row too, since
	 * OpenCart's own install never had a second language to translate
	 * for), and renames Iran's English name from the stock ISO official
	 * form ("Iran (Islamic Republic of)") to plain "Iran". Gated behind
	 * the same 'tool/system_update' permission as the rest of this
	 * controller — see Model::localizeCountries() for exactly what it
	 * changes and why touching this one table reaches every dropdown on
	 * the site.
	 *
	 * @return void
	 */
	public function localizeCountries(): void {
		ob_start();

		$json = [];

		if (!$this->user->hasPermission('modify', 'tool/system_update')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('tool/system_update');

			$json = $this->model_tool_system_update->localizeCountries();
			$json['success'] = true;
		}

		$this->discardStrayOutput();

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Backups
	 *
	 * Returns the rendered backup list (same partial used on page load), so
	 * the page can refresh it after an update or a restore without a full
	 * reload.
	 *
	 * @return void
	 */
	public function backups(): void {
		$this->load->language('tool/system_update');

		if (!$this->user->hasPermission('access', 'tool/system_update')) {
			$this->response->setOutput('');

			return;
		}

		$this->load->model('tool/system_update');

		$data['backup_list'] = $this->formatBackups($this->model_tool_system_update->listBackups());
		$data['restore'] = $this->url->link('tool/system_update.restore', 'user_token=' . $this->session->data['user_token'], true);

		$this->response->setOutput($this->load->view('tool/system_update_backups', $data));
	}

	/**
	 * Restore
	 *
	 * Rolls the site back to a previously taken automatic backup (database
	 * + files). See Model::restoreBackup() for exactly what this does and
	 * does not undo.
	 *
	 * @return void
	 */
	public function restore(): void {
		ob_start();

		$this->load->language('tool/system_update');

		$json = [];

		if (!$this->user->hasPermission('modify', 'tool/system_update')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$backup_id = isset($this->request->post['backup_id']) ? (string)$this->request->post['backup_id'] : '';

		if (!$json && !$backup_id) {
			$json['error'] = $this->language->get('error_backup_not_found');
		}

		if (!$json) {
			$this->load->model('tool/system_update');

			$result = $this->model_tool_system_update->restoreBackup($backup_id);

			if (isset($result['error'])) {
				$json['error'] = $this->formatError($result);
			} else {
				$json['success'] = $this->language->get('text_restore_success');
				$json['restored_id'] = $result['restored_id'];
			}
		}

		$this->discardStrayOutput();

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Format Backups
	 *
	 * @param array<int, array<string, mixed>> $backups
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function formatBackups(array $backups): array {
		$formatted = [];

		foreach ($backups as $backup) {
			$total_bytes = (int)($backup['db_size'] ?? 0) + (int)($backup['files_size'] ?? 0) + (int)($backup['storage_size'] ?? 0);

			$formatted[] = [
				'id'            => $backup['id'] ?? '',
				'created_at'    => $backup['created_at'] ?? '',
				'reason'        => $backup['reason'] ?? '',
				'commit_before' => substr((string)($backup['commit_before'] ?? ''), 0, 10),
				'commit_after'  => substr((string)($backup['commit_after'] ?? ''), 0, 10),
				'size'          => $this->formatBytes($total_bytes),
			];
		}

		return $formatted;
	}

	/**
	 * Format Bytes
	 *
	 * @param int $bytes
	 *
	 * @return string
	 */
	private function formatBytes(int $bytes): string {
		$suffix = ['B', 'KB', 'MB', 'GB', 'TB'];

		$i = 0;
		$size = $bytes;

		while (($size / 1024) > 1 && $i < count($suffix) - 1) {
			$size /= 1024;

			$i++;
		}

		return round($size, 2) . $suffix[$i];
	}

	/**
	 * Discard Stray Output
	 *
	 * Ends the output buffer started at the top of the calling action (see
	 * save(), check(), baseline(), apply(), restore()). On some PHP setups
	 * a stray warning/notice/deprecation message can get printed straight
	 * into the response body — before our own JSON is written — which
	 * silently corrupts that JSON. The AJAX call on the update page then
	 * fails with no visible reason at all (jQuery's dataType: 'json' parse
	 * fails even though the HTTP status is a normal 200).
	 *
	 * This swallows anything unexpectedly printed during the action instead
	 * of letting it leak into the response, and logs it to
	 * storage/logs/system_update_stray_output.log so the real cause is
	 * still there to look at, rather than just disappearing.
	 *
	 * @return void
	 */
	private function discardStrayOutput(): void {
		$stray = ob_get_clean();

		if ($stray !== false && trim($stray) !== '') {
			@file_put_contents(DIR_LOGS . 'system_update_stray_output.log', '[' . date('Y-m-d H:i:s') . '] ' . $stray . "\n", FILE_APPEND);
		}
	}

	/**
	 * Format Error
	 *
	 * @param array<string, mixed> $result
	 *
	 * @return string
	 */
	private function formatError(array $result): string {
		switch ($result['error']) {
			case 'not_configured':
				return $this->language->get('error_not_configured');
			case 'connection':
				return $this->language->get('error_connection') . ($result['detail'] ?? '');
			case 'api':
				return $this->language->get('error_api') . ($result['detail'] ?? '');
			case 'zip_extension':
				return $this->language->get('error_zip_extension');
			case 'download':
				return $this->language->get('error_download') . (isset($result['detail']) ? ' (' . $result['detail'] . ')' : '');
			case 'extract':
				return $this->language->get('error_extract');
			case 'write':
				return $this->language->get('error_write');
			case 'backup_failed':
				return $this->language->get('error_backup_failed');
			case 'backup_not_found':
				return $this->language->get('error_backup_not_found');
			default:
				return $this->language->get('error_connection');
		}
	}
}
