<?php
namespace MDcart\Admin\Controller\Accounting;
/**
 * Class BankAccount
 *
 * Bank/Cash accounts admin screen. Three come pre-seeded (Cash Box, Card/
 * POS Terminal, Website Sales - Undeposited Funds) with their `default_role`
 * already set, which is what makes automatic posting from POS/storefront
 * sales work without any setup - add more here for real bank accounts as
 * needed, and reassign a role to a different account any time.
 *
 * Can be loaded using $this->load->controller('accounting/bank_account');
 *
 * @package MDcart\Admin\Controller\Accounting
 */
class BankAccount extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('accounting/bank_account');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('accounting/bank_account', 'user_token=' . $this->session->data['user_token'])
		];

		$data['add'] = $this->url->link('accounting/bank_account.form', 'user_token=' . $this->session->data['user_token']);
		$data['delete'] = $this->url->link('accounting/bank_account.delete', 'user_token=' . $this->session->data['user_token']);

		$data['list'] = $this->getList();

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('accounting/bank_account', $data));
	}

	/**
	 * List
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('accounting/bank_account');

		$this->response->setOutput($this->getList());
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	private function getList(): string {
		$this->load->model('accounting/bank_account');

		$results = $this->model_accounting_bank_account->getBankAccounts();

		$data['bank_accounts'] = [];

		foreach ($results as $result) {
			$data['bank_accounts'][] = [
				'bank_account_id' => $result['bank_account_id'],
				'name'            => $result['name'],
				'bank_name'       => $result['bank_name'],
				'account_number'  => $result['account_number'],
				'default_role'    => $result['default_role'],
				'default_role_text' => $result['default_role'] ? $this->language->get('text_role_' . $result['default_role']) : '',
				'status'          => $result['status'],
				'balance'         => number_format($this->model_accounting_bank_account->getBalance((int)$result['bank_account_id']), 2),
				'edit'            => $this->url->link('accounting/bank_account.form', 'user_token=' . $this->session->data['user_token'] . '&bank_account_id=' . $result['bank_account_id'])
			];
		}

		$data['user_token'] = $this->session->data['user_token'];
		$data['currency_symbol'] = $this->config->get('config_currency');

		return $this->load->view('accounting/bank_account_list', $data);
	}

	/**
	 * Form
	 *
	 * @return void
	 */
	public function form(): void {
		$this->load->language('accounting/bank_account');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['text_form'] = !isset($this->request->get['bank_account_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('accounting/bank_account', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('accounting/bank_account.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('accounting/bank_account', 'user_token=' . $this->session->data['user_token']);

		$this->load->model('accounting/bank_account');

		$bank_account_info = [];

		if (isset($this->request->get['bank_account_id'])) {
			$bank_account_info = $this->model_accounting_bank_account->getBankAccount((int)$this->request->get['bank_account_id']);
		}

		$data['bank_account_id'] = !empty($bank_account_info) ? $bank_account_info['bank_account_id'] : 0;
		$data['name'] = !empty($bank_account_info) ? $bank_account_info['name'] : '';
		$data['bank_name'] = !empty($bank_account_info) ? $bank_account_info['bank_name'] : '';
		$data['account_number'] = !empty($bank_account_info) ? $bank_account_info['account_number'] : '';
		$data['iban'] = !empty($bank_account_info) ? $bank_account_info['iban'] : '';
		$data['opening_balance'] = !empty($bank_account_info) ? $bank_account_info['opening_balance'] : '0.0000';
		$data['currency'] = !empty($bank_account_info) ? $bank_account_info['currency'] : $this->config->get('config_currency');
		$data['default_role'] = !empty($bank_account_info) ? $bank_account_info['default_role'] : '';
		$data['status'] = !empty($bank_account_info) ? $bank_account_info['status'] : 1;

		$data['roles'] = ['pos_cash', 'pos_card', 'website_sales'];

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('accounting/bank_account_form', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('accounting/bank_account');

		$json = [];

		if (!$this->user->hasPermission('modify', 'accounting/bank_account')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'bank_account_id' => 0,
			'name'            => '',
			'default_role'    => '',
			'status'          => 1
		];

		$post_info = $this->request->post + $required;

		if (!oc_validate_length($post_info['name'], 1, 128)) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		if (!$json) {
			$this->load->model('accounting/bank_account');

			if (!$post_info['bank_account_id']) {
				$json['bank_account_id'] = $this->model_accounting_bank_account->addBankAccount($post_info);
			} else {
				$this->model_accounting_bank_account->editBankAccount((int)$post_info['bank_account_id'], $post_info);
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
		$this->load->language('accounting/bank_account');

		$json = [];

		if (isset($this->request->post['selected'])) {
			$selected = (array)$this->request->post['selected'];
		} else {
			$selected = [];
		}

		if (!$this->user->hasPermission('modify', 'accounting/bank_account')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$this->load->model('accounting/bank_account');

		foreach ($selected as $bank_account_id) {
			if ($this->model_accounting_bank_account->hasJournalLines((int)$bank_account_id)) {
				$json['error'] = $this->language->get('error_has_journal_lines');
			}
		}

		if (!$json) {
			foreach ($selected as $bank_account_id) {
				$this->model_accounting_bank_account->deleteBankAccount((int)$bank_account_id);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
