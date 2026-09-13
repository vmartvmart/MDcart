<?php
namespace MDcart\Admin\Controller\Extension\AutoTranslate\Other;
/**
 * Class AutoTranslate
 *
 * Settings page for the fa<->en auto-translation extension.
 *
 * SECURITY: the stored Anthropic API key is never echoed back to the browser
 * in full. index() only ever exposes a masked "sk-ant-...last4" preview; the
 * actual <input> for the key is left blank, and save() only overwrites the
 * stored key when the admin actually typed a new value (see save()'s
 * merge-with-existing-key comment below). This keeps the raw key out of the
 * page's HTML/DOM at all times after the first save, out of browser history
 * autofill, and out of screenshots/screen shares of the settings page.
 *
 * @package MDcart\Admin\Controller\Extension\AutoTranslate\Other
 */
class AutoTranslate extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/auto_translate/other/auto_translate');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=other')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/auto_translate/other/auto_translate', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/auto_translate/other/auto_translate.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=other');

		foreach (['status', 'model', 'products_status', 'categories_status'] as $key) {
			$data['other_auto_translate_' . $key] = $this->config->get('other_auto_translate_' . $key);
		}

		// Never send the real key to the browser - only whether one is stored, and a masked preview of it.
		$api_key = (string)$this->config->get('other_auto_translate_api_key');

		$data['other_auto_translate_api_key_set'] = $api_key !== '';
		$data['other_auto_translate_api_key_masked'] = $api_key !== '' ? str_repeat('•', 8) . mb_substr($api_key, -4) : '';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/auto_translate/other/auto_translate', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/auto_translate/other/auto_translate');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/auto_translate/other/auto_translate')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$existing_key = (string)$this->config->get('other_auto_translate_api_key');
		$posted_key = trim((string)($this->request->post['other_auto_translate_api_key'] ?? ''));

		if (!empty($this->request->post['other_auto_translate_status']) && $posted_key === '' && $existing_key === '') {
			$json['error']['api_key'] = $this->language->get('error_api_key');
		}

		if (!empty($json['error'])) {
			$json['error']['warning'] = $json['error']['warning'] ?? $this->language->get('error_warning');
		}

		if (!$json) {
			$post = $this->request->post;

			// The settings form field is always left blank on render (see index()), so an
			// empty submission means "keep the existing key", never "erase it". editSetting()
			// deletes and re-inserts every other_auto_translate_* row from what we hand it, so
			// we must feed the existing key back in explicitly whenever a new one wasn't typed.
			$post['other_auto_translate_api_key'] = $posted_key !== '' ? $posted_key : $existing_key;

			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('other_auto_translate', $post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * The four hooks this extension needs, each as its own event code (oc_event has no
	 * (code, trigger) composite key, so one code can only ever carry one trigger/action pair).
	 *
	 * @return array<int, array{0: string, 1: string, 2: string}> [code, trigger, action]
	 */
	private function getEvents(): array {
		return [
			['auto_translate_product_add', 'model/catalog/product.addProduct/after', 'extension/auto_translate/event/product.add'],
			['auto_translate_product_edit', 'model/catalog/product.editProduct/after', 'extension/auto_translate/event/product.edit'],
			['auto_translate_category_add', 'model/catalog/category.addCategory/after', 'extension/auto_translate/event/category.add'],
			['auto_translate_category_edit', 'model/catalog/category.editCategory/after', 'extension/auto_translate/event/category.edit'],
		];
	}

	/**
	 * Install
	 *
	 * @return void
	 */
	public function install(): void {
		$this->load->model('setting/event');

		foreach ($this->getEvents() as [$code, $trigger, $action]) {
			if (!$this->model_setting_event->getEventByCode($code)) {
				$this->model_setting_event->addEvent([
					'code'        => $code,
					'description' => 'Auto-translates a product/category\'s empty fa/en fields from the other language on save.',
					'trigger'     => $trigger,
					'action'      => $action,
					'status'      => 1,
					'sort_order'  => 1
				]);
			}
		}

		$this->load->model('setting/extension');

		if (!$this->model_setting_extension->getInstallByCode('auto_translate')) {
			$this->model_setting_extension->addInstall([
				'extension_id'          => 0,
				'extension_download_id' => 0,
				'name'                  => 'Auto Translate (fa <-> en)',
				'description'           => 'Automatically fills in a product or category\'s empty Persian/English name, description, tags and SEO meta fields by translating from whichever language was actually entered, using the Anthropic Claude API.',
				'code'                  => 'auto_translate',
				'version'               => '1.0',
				'author'                => '',
				'link'                  => ''
			]);
		}
	}

	/**
	 * Uninstall
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$this->load->model('setting/event');

		foreach ($this->getEvents() as [$code]) {
			$this->model_setting_event->deleteEventByCode($code);
		}
	}
}
