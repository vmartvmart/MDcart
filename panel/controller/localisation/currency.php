<?php
namespace MDcart\Admin\Controller\Localisation;
/**
 * Class Currency
 *
 * @package MDcart\Admin\Controller\Localisation
 */
class Currency extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('localisation/currency');

		$this->document->setTitle($this->language->get('heading_title'));

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('localisation/currency', 'user_token=' . $this->session->data['user_token'] . $url)
		];

		$data['refresh'] = $this->url->link('localisation/currency.refresh', 'user_token=' . $this->session->data['user_token'] . $url);
		$data['add'] = $this->url->link('localisation/currency.form', 'user_token=' . $this->session->data['user_token'] . $url);
		$data['delete'] = $this->url->link('localisation/currency.delete', 'user_token=' . $this->session->data['user_token']);

		$data['list'] = $this->getList();

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('localisation/currency', $data));
	}

	/**
	 * List
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('localisation/currency');

		$this->response->setOutput($this->getList());
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	public function getList(): string {
		if (isset($this->request->get['sort'])) {
			$sort = (string)$this->request->get['sort'];
		} else {
			$sort = 'title';
		}

		if (isset($this->request->get['order'])) {
			$order = (string)$this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('localisation/currency', 'user_token=' . $this->session->data['user_token'] . $url)
		];

		$data['action'] = $this->url->link('localisation/currency.list', 'user_token=' . $this->session->data['user_token'] . $url);

		// Currency
		$data['currencies'] = [];

		$filter_data = [
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_pagination_admin'),
			'limit' => $this->config->get('config_pagination_admin')
		];

		$this->load->model('localisation/currency');

		$results = $this->model_localisation_currency->getCurrencies($filter_data);

		foreach ($results as $result) {
			$data['currencies'][] = [
				'title'         => $result['title'] . (($result['code'] == $this->config->get('config_currency')) ? $this->language->get('text_default') : ''),
				'date_modified' => oc_jdate($this->language->get('date_format_short'), strtotime($result['date_modified'])),
				'edit'          => $this->url->link('localisation/currency.form', 'user_token=' . $this->session->data['user_token'] . '&currency_id=' . $result['currency_id'] . $url)
			] + $result;
		}

		$url = '';

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		$data['sort_title'] = $this->url->link('localisation/currency.list', 'user_token=' . $this->session->data['user_token'] . '&sort=title' . $url);
		$data['sort_code'] = $this->url->link('localisation/currency.list', 'user_token=' . $this->session->data['user_token'] . '&sort=code' . $url);
		$data['sort_value'] = $this->url->link('localisation/currency.list', 'user_token=' . $this->session->data['user_token'] . '&sort=value' . $url);
		$data['sort_status'] = $this->url->link('localisation/currency.list', 'user_token=' . $this->session->data['user_token'] . '&sort=status' . $url);
		$data['sort_date_modified'] = $this->url->link('localisation/currency.list', 'user_token=' . $this->session->data['user_token'] . '&sort=date_modified' . $url);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$currency_total = $this->model_localisation_currency->getTotalCurrencies();

		$data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $currency_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('localisation/currency.list', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($currency_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($currency_total - $this->config->get('config_pagination_admin'))) ? $currency_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $currency_total, ceil($currency_total / $this->config->get('config_pagination_admin')));

		$data['sort'] = $sort;
		$data['order'] = $order;

		return $this->load->view('localisation/currency_list', $data);
	}

	/**
	 * Form
	 *
	 * @return void
	 */
	public function form(): void {
		$this->load->language('localisation/currency');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['text_form'] = !isset($this->request->get['currency_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('localisation/currency', 'user_token=' . $this->session->data['user_token'] . $url)
		];

		$data['save'] = $this->url->link('localisation/currency.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('localisation/currency', 'user_token=' . $this->session->data['user_token'] . $url);

		if (isset($this->request->get['currency_id'])) {
			$this->load->model('localisation/currency');

			$currency_info = $this->model_localisation_currency->getCurrency((int)$this->request->get['currency_id']);
		}

		if (!empty($currency_info)) {
			$data['currency_id'] = $currency_info['currency_id'];
		} else {
			$data['currency_id'] = 0;
		}

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (!empty($currency_info)) {
			$data['currency_description'] = $this->model_localisation_currency->getDescriptions($currency_info['currency_id']);
		} else {
			$data['currency_description'] = [];
		}

		if (!empty($currency_info)) {
			$data['code'] = $currency_info['code'];
		} else {
			$data['code'] = '';
		}

		if (!empty($currency_info)) {
			$data['symbol_left'] = $currency_info['symbol_left'];
		} else {
			$data['symbol_left'] = '';
		}

		if (!empty($currency_info)) {
			$data['symbol_right'] = $currency_info['symbol_right'];
		} else {
			$data['symbol_right'] = '';
		}

		if (!empty($currency_info)) {
			$data['decimal_place'] = $currency_info['decimal_place'];
		} else {
			$data['decimal_place'] = '';
		}

		if (!empty($currency_info)) {
			$data['value'] = $currency_info['value'];
		} else {
			$data['value'] = '';
		}

		if (!empty($currency_info)) {
			$data['status'] = $currency_info['status'];
		} else {
			$data['status'] = '';
		}

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('localisation/currency_form', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('localisation/currency');

		$json = [];

		if (!$this->user->hasPermission('modify', 'localisation/currency')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'currency_id'           => 0,
			'currency_description'  => [],
			'code'                  => '',
			'symbol_left'           => '',
			'symbol_right'          => '',
			'decimal_place'         => 0,
			'value'                 => 0.0,
			'status'                => 0
		];

		$post_info = $this->request->post + $required;

		foreach ($post_info['currency_description'] as $language_id => $value) {
			if (!oc_validate_length($value['title'] ?? '', 3, 32)) {
				$json['error']['title_' . $language_id] = $this->language->get('error_title');
			}
		}

		if (oc_strlen($post_info['code']) != 3) {
			$json['error']['code'] = $this->language->get('error_code');
		}

		$this->load->model('localisation/currency');

		$currency_info = $this->model_localisation_currency->getCurrencyByCode($post_info['code']);

		if ($currency_info && (!$post_info['currency_id'] || ($currency_info['currency_id'] != $post_info['currency_id']))) {
			$json['error']['code'] = $this->language->get('error_exists');
		}

		if (!$json) {
			// `oc_currency.title` stays populated as a legacy fallback (see
			// the model's class docblock) - prefer the English/us
			// description (language_id 1), since that's the language every
			// installed store has, falling back to whichever language was
			// actually filled in if English was left blank.
			$post_info['title'] = (string)($post_info['currency_description'][1]['title'] ?? '');

			if ($post_info['title'] === '') {
				foreach ($post_info['currency_description'] as $currency_description) {
					if (!empty($currency_description['title'])) {
						$post_info['title'] = (string)$currency_description['title'];
						break;
					}
				}
			}

			if (!$post_info['currency_id']) {
				$json['currency_id'] = $this->model_localisation_currency->addCurrency($post_info);
			} else {
				$this->model_localisation_currency->editCurrency($post_info['currency_id'], $post_info);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Refresh
	 *
	 * @return void
	 */
	public function refresh(): void {
		$this->load->language('localisation/currency');

		$json = [];

		if (!$this->user->hasPermission('modify', 'localisation/currency')) {
			$json['error'] = $this->language->get('error_permission');
		}

		// Extension
		$this->load->model('setting/extension');

		$extension_info = $this->model_setting_extension->getExtensionByCode('currency', $this->config->get('config_currency_engine'));

		if (!$extension_info) {
			$json['error'] = $this->language->get('error_extension');
		}

		if (!$json) {
			$this->load->controller('extension/' . $extension_info['extension'] . '/currency/' . $extension_info['code'] . '.currency', $this->config->get('config_currency'));

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Delete
	 *
	 * @return void
	 */
	public function delete(): void {
		$this->load->language('localisation/currency');

		$json = [];

		if (isset($this->request->post['selected'])) {
			$selected = (array)$this->request->post['selected'];
		} else {
			$selected = [];
		}

		if (!$this->user->hasPermission('modify', 'localisation/currency')) {
			$json['error'] = $this->language->get('error_permission');
		}

		// Currency
		$this->load->model('localisation/currency');

		// Store
		$this->load->model('setting/store');

		// Order
		$this->load->model('sale/order');

		foreach ($selected as $currency_id) {
			$currency_info = $this->model_localisation_currency->getCurrency($currency_id);

			if ($currency_info) {
				if ($this->config->get('config_currency') == $currency_info['code']) {
					$json['error'] = $this->language->get('error_default');
				}

				$store_total = $this->model_setting_store->getTotalStoresByCurrency($currency_info['code']);

				if ($store_total) {
					$json['error'] = sprintf($this->language->get('error_store'), $store_total);
				}
			}

			$order_total = $this->model_sale_order->getTotalOrdersByCurrencyId($currency_id);

			if ($order_total) {
				$json['error'] = sprintf($this->language->get('error_order'), $order_total);
			}
		}

		if (!$json) {
			foreach ($selected as $currency_id) {
				$this->model_localisation_currency->deleteCurrency($currency_id);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
