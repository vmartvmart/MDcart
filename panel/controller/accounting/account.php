<?php
namespace MDcart\Admin\Controller\Accounting;
/**
 * Class Account
 *
 * Chart of Accounts admin screen (list as an indented tree by code, add/
 * edit/delete). Seeded system accounts (Cash Box, Sales Revenue, Cost of
 * Goods Sold, etc.) can be renamed/disabled but never re-typed, re-parented,
 * or deleted, since automatic postings from sales look them up by their
 * fixed `code`.
 *
 * Can be loaded using $this->load->controller('accounting/account');
 *
 * @package MDcart\Admin\Controller\Accounting
 */
class Account extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('accounting/account');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('accounting/account', 'user_token=' . $this->session->data['user_token'])
		];

		$data['add'] = $this->url->link('accounting/account.form', 'user_token=' . $this->session->data['user_token']);
		$data['delete'] = $this->url->link('accounting/account.delete', 'user_token=' . $this->session->data['user_token']);

		$data['list'] = $this->getList();

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('accounting/account', $data));
	}

	/**
	 * List
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('accounting/account');

		$this->response->setOutput($this->getList());
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	private function getList(): string {
		$this->load->model('accounting/account');

		$results = $this->model_accounting_account->getAccounts();

		$data['accounts'] = [];

		foreach ($results as $result) {
			$data['accounts'][] = [
				'account_id' => $result['account_id'],
				'code'       => $result['code'],
				'name'       => $result['name'],
				'type'       => $this->language->get('text_type_' . $result['type']),
				'depth'      => $result['depth'],
				'is_system'  => $result['is_system'],
				'is_bank_cash' => $result['is_bank_cash'],
				'status'     => $result['status'],
				'balance'    => number_format($this->model_accounting_account->getAccountBalance((int)$result['account_id']), 2),
				'edit'       => $this->url->link('accounting/account.form', 'user_token=' . $this->session->data['user_token'] . '&account_id=' . $result['account_id'])
			];
		}

		$data['user_token'] = $this->session->data['user_token'];

		return $this->load->view('accounting/account_list', $data);
	}

	/**
	 * Form
	 *
	 * @return void
	 */
	public function form(): void {
		$this->load->language('accounting/account');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['text_form'] = !isset($this->request->get['account_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('accounting/account', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('accounting/account.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('accounting/account', 'user_token=' . $this->session->data['user_token']);

		$this->load->model('accounting/account');

		$account_info = [];

		if (isset($this->request->get['account_id'])) {
			$account_info = $this->model_accounting_account->getAccount((int)$this->request->get['account_id']);
		}

		$data['account_id'] = !empty($account_info) ? $account_info['account_id'] : 0;
		$data['parent_id'] = !empty($account_info) ? $account_info['parent_id'] : 0;
		$data['code'] = !empty($account_info) ? $account_info['code'] : '';
		$data['name'] = !empty($account_info) ? $account_info['name'] : '';
		$data['type'] = !empty($account_info) ? $account_info['type'] : 'expense';
		$data['status'] = !empty($account_info) ? $account_info['status'] : 1;
		$data['is_system'] = !empty($account_info) ? (bool)$account_info['is_system'] : false;
		$data['is_bank_cash'] = !empty($account_info) ? (bool)$account_info['is_bank_cash'] : false;

		$data['accounts'] = $this->model_accounting_account->getAccounts();
		$data['types'] = ['asset', 'liability', 'equity', 'revenue', 'expense'];

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('accounting/account_form', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('accounting/account');

		$json = [];

		if (!$this->user->hasPermission('modify', 'accounting/account')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'account_id' => 0,
			'parent_id'  => 0,
			'code'       => '',
			'name'       => '',
			'type'       => 'expense',
			'status'     => 1
		];

		$post_info = $this->request->post + $required;

		if (!oc_validate_length($post_info['name'], 1, 128)) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		if (!oc_validate_length($post_info['code'], 1, 20)) {
			$json['error']['code'] = $this->language->get('error_code');
		}

		if (!in_array($post_info['type'], ['asset', 'liability', 'equity', 'revenue', 'expense'], true)) {
			$json['error']['warning'] = $this->language->get('error_type');
		}

		if (!$json) {
			$this->load->model('accounting/account');

			if (!$post_info['account_id']) {
				$existing = $this->model_accounting_account->getAccountByCode((string)$post_info['code']);

				if ($existing) {
					$json['error']['code'] = $this->language->get('error_code_exists');
				}
			}
		}

		if (!$json) {
			if (!$post_info['account_id']) {
				$json['account_id'] = $this->model_accounting_account->addAccount($post_info);
			} else {
				$this->model_accounting_account->editAccount((int)$post_info['account_id'], $post_info);
			}

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
		$this->load->language('accounting/account');

		$json = [];

		if (isset($this->request->post['selected'])) {
			$selected = (array)$this->request->post['selected'];
		} else {
			$selected = [];
		}

		if (!$this->user->hasPermission('modify', 'accounting/account')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$this->load->model('accounting/account');

		foreach ($selected as $account_id) {
			$account_info = $this->model_accounting_account->getAccount((int)$account_id);

			if (!empty($account_info['is_system'])) {
				$json['error'] = $this->language->get('error_system_account');
			} elseif ($this->model_accounting_account->hasChildren((int)$account_id)) {
				$json['error'] = $this->language->get('error_has_children');
			} elseif ($this->model_accounting_account->hasJournalLines((int)$account_id)) {
				$json['error'] = $this->language->get('error_has_journal_lines');
			}
		}

		if (!$json) {
			foreach ($selected as $account_id) {
				$this->model_accounting_account->deleteAccount((int)$account_id);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
