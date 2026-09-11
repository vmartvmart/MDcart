<?php
namespace Opencart\Admin\Controller\Accounting;
/**
 * Class Journal
 *
 * The general journal admin screen: a filterable list of every posted
 * entry (manual, Expense/Receipt/Payment quick entries, and the automatic
 * postings the checkout hook makes for POS/storefront sales - reference
 * types 'order_sale' and 'order_cogs'), a free-form multi-line manual
 * entry form, three quick single-line forms (Expense/Receipt/Payment),
 * a read-only view of any entry, and a Void action that posts a reversing
 * entry rather than editing or deleting history.
 *
 * Can be loaded using $this->load->controller('accounting/journal');
 *
 * @package Opencart\Admin\Controller\Accounting
 */
class Journal extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('accounting/journal');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('accounting/journal', 'user_token=' . $this->session->data['user_token'])
		];

		$data['add_manual'] = $this->url->link('accounting/journal.form', 'user_token=' . $this->session->data['user_token']);
		$data['add_expense'] = $this->url->link('accounting/journal.expense', 'user_token=' . $this->session->data['user_token']);
		$data['add_receipt'] = $this->url->link('accounting/journal.receipt', 'user_token=' . $this->session->data['user_token']);
		$data['add_payment'] = $this->url->link('accounting/journal.payment', 'user_token=' . $this->session->data['user_token']);
		$data['list_action'] = $this->url->link('accounting/journal.list', 'user_token=' . $this->session->data['user_token'], true);

		$data['list'] = $this->getList();

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('accounting/journal', $data));
	}

	/**
	 * List (AJAX partial)
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('accounting/journal');

		$this->response->setOutput($this->getList());
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	private function getList(): string {
		$this->load->model('accounting/journal');

		$filter_data = [
			'filter_reference_type' => $this->request->get['filter_reference_type'] ?? '',
			'filter_date_start'     => $this->request->get['filter_date_start'] ?? '',
			'filter_date_end'       => $this->request->get['filter_date_end'] ?? ''
		];

		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = 20;

		$filter_data['start'] = ($page - 1) * $limit;
		$filter_data['limit'] = $limit;

		$results = $this->model_accounting_journal->getJournals($filter_data);
		$total = $this->model_accounting_journal->getTotalJournals($filter_data);

		$data['journals'] = [];

		foreach ($results as $result) {
			$data['journals'][] = [
				'journal_id'     => $result['journal_id'],
				'journal_number' => $result['journal_number'],
				'reference_type' => $this->language->get('text_type_' . $result['reference_type']),
				'description'    => $result['description'],
				'amount'         => number_format((float)$result['total_debit'], 2),
				'status'         => $this->language->get('text_status_' . $result['status']),
				'status_raw'     => $result['status'],
				'date_added'     => date($this->language->get('date_format_short') . ' H:i', strtotime($result['date_added'])),
				'view'           => $this->url->link('accounting/journal.view', 'user_token=' . $this->session->data['user_token'] . '&journal_id=' . $result['journal_id'])
			];
		}

		$url = '';

		foreach (['filter_reference_type', 'filter_date_start', 'filter_date_end'] as $key) {
			if (!empty($filter_data[$key])) {
				$url .= '&' . $key . '=' . $filter_data[$key];
			}
		}

		$data['pages'] = [];

		$pages = (int)ceil($total / $limit);

		for ($i = 1; $i <= $pages; $i++) {
			$data['pages'][] = [
				'text' => (string)$i,
				'href' => $this->url->link('accounting/journal.list', 'user_token=' . $this->session->data['user_token'] . $url . '&page=' . $i, true)
			];
		}

		$data['currency_symbol'] = $this->config->get('config_currency');

		return $this->load->view('accounting/journal_list', $data);
	}

	/**
	 * Form - free-form manual multi-line journal entry.
	 *
	 * @return void
	 */
	public function form(): void {
		$this->load->language('accounting/journal');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('accounting/journal', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('accounting/journal.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('accounting/journal', 'user_token=' . $this->session->data['user_token']);

		$this->load->model('accounting/account');

		$data['accounts'] = $this->model_accounting_account->getAccounts();

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('accounting/journal_form', $data));
	}

	/**
	 * Save - manual multi-line entry.
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('accounting/journal');

		$json = [];

		if (!$this->user->hasPermission('modify', 'accounting/journal')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$lines = (array)($this->request->post['lines'] ?? []);
		$description = (string)($this->request->post['description'] ?? '');

		$clean_lines = [];
		$total_debit = 0.0;
		$total_credit = 0.0;

		foreach ($lines as $line) {
			$account_id = (int)($line['account_id'] ?? 0);
			$debit = (float)($line['debit'] ?? 0);
			$credit = (float)($line['credit'] ?? 0);

			if (!$account_id || (!$debit && !$credit)) {
				continue;
			}

			$clean_lines[] = ['account_id' => $account_id, 'debit' => $debit, 'credit' => $credit, 'description' => (string)($line['description'] ?? '')];
			$total_debit += $debit;
			$total_credit += $credit;
		}

		if (count($clean_lines) < 2) {
			$json['error']['warning'] = $this->language->get('error_lines');
		} elseif (round($total_debit, 4) !== round($total_credit, 4)) {
			$json['error']['warning'] = $this->language->get('error_unbalanced');
		}

		if (!$json) {
			$this->load->model('accounting/journal');

			$journal_id = $this->model_accounting_journal->addJournal([
				'reference_type' => 'manual',
				'description'    => $description,
				'user_id'        => $this->user->getId(),
				'lines'          => $clean_lines
			]);

			if (!$journal_id) {
				$json['error']['warning'] = $this->language->get('error_unbalanced');
			} else {
				$json['journal_id'] = $journal_id;
				$json['success'] = $this->language->get('text_success');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Expense (quick form)
	 *
	 * @return void
	 */
	public function expense(): void {
		$this->quickForm('expense');
	}

	/**
	 * Receipt (quick form)
	 *
	 * @return void
	 */
	public function receipt(): void {
		$this->quickForm('receipt');
	}

	/**
	 * Payment (quick form)
	 *
	 * @return void
	 */
	public function payment(): void {
		$this->quickForm('payment');
	}

	/**
	 * @param string $mode 'expense' | 'receipt' | 'payment'
	 *
	 * @return void
	 */
	private function quickForm(string $mode): void {
		$this->load->language('accounting/journal');

		$this->document->setTitle($this->language->get('heading_title_' . $mode));

		$data['mode'] = $mode;
		$data['heading_title'] = $this->language->get('heading_title_' . $mode);

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('accounting/journal', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $data['heading_title'],
			'href' => $this->url->link('accounting/journal.' . $mode, 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('accounting/journal.saveQuick', 'user_token=' . $this->session->data['user_token'] . '&mode=' . $mode);
		$data['back'] = $this->url->link('accounting/journal', 'user_token=' . $this->session->data['user_token']);

		$this->load->model('accounting/bank_account');
		$this->load->model('accounting/account');

		$data['bank_accounts'] = $this->model_accounting_bank_account->getBankAccounts();

		// Any non-bank/cash account can be the "other side" of a quick entry -
		// keeps this flexible (an Expense can hit any expense category the
		// admin has added; a Receipt/Payment can hit revenue, liability,
		// even equity accounts for things like an owner's capital injection).
		$data['category_accounts'] = $this->model_accounting_account->getAccounts(['exclude_bank_cash' => true]);

		$data['currency_symbol'] = $this->config->get('config_currency');

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('accounting/journal_quick_form', $data));
	}

	/**
	 * Save Quick - Expense/Receipt/Payment.
	 *
	 * @return void
	 */
	public function saveQuick(): void {
		$this->load->language('accounting/journal');

		$json = [];

		if (!$this->user->hasPermission('modify', 'accounting/journal')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$mode = (string)($this->request->get['mode'] ?? $this->request->post['mode'] ?? '');

		if (!in_array($mode, ['expense', 'receipt', 'payment'], true)) {
			$json['error']['warning'] = $this->language->get('error_unbalanced');
		}

		$bank_account_id = (int)($this->request->post['bank_account_id'] ?? 0);
		$category_account_id = (int)($this->request->post['category_account_id'] ?? 0);
		$amount = (float)($this->request->post['amount'] ?? 0);
		$description = (string)($this->request->post['description'] ?? '');

		if (!$bank_account_id) {
			$json['error']['bank_account_id'] = $this->language->get('error_bank_account');
		}

		if (!$category_account_id) {
			$json['error']['category_account_id'] = $this->language->get('error_category_account');
		}

		if ($amount <= 0) {
			$json['error']['amount'] = $this->language->get('error_amount');
		}

		if (!$json) {
			$this->load->model('accounting/bank_account');
			$this->load->model('accounting/journal');

			$bank_account_info = $this->model_accounting_bank_account->getBankAccount($bank_account_id);

			if (!$bank_account_info) {
				$json['error']['bank_account_id'] = $this->language->get('error_bank_account');
			} else {
				$data = [
					'bank_account_id'     => (int)$bank_account_info['account_id'],
					'category_account_id' => $category_account_id,
					'amount'              => $amount,
					'description'         => $description,
					'user_id'             => $this->user->getId()
				];

				if ($mode === 'expense') {
					$journal_id = $this->model_accounting_journal->addExpense($data);
				} elseif ($mode === 'receipt') {
					$journal_id = $this->model_accounting_journal->addReceipt($data);
				} else {
					$journal_id = $this->model_accounting_journal->addPayment($data);
				}

				if (!$journal_id) {
					$json['error']['warning'] = $this->language->get('error_unbalanced');
				} else {
					$json['journal_id'] = $journal_id;
					$json['success'] = $this->language->get('text_success');
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * View (read-only) + Void button.
	 *
	 * @return void
	 */
	public function view(): void {
		$this->load->language('accounting/journal');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('accounting/journal');

		$journal_id = (int)($this->request->get['journal_id'] ?? 0);
		$journal_info = $this->model_accounting_journal->getJournal($journal_id);

		if (!$journal_info) {
			$this->response->redirect($this->url->link('accounting/journal', 'user_token=' . $this->session->data['user_token']));

			return;
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('accounting/journal', 'user_token=' . $this->session->data['user_token'])
		];

		$data['journal_id'] = $journal_info['journal_id'];
		$data['journal_number'] = $journal_info['journal_number'];
		$data['reference_type'] = $this->language->get('text_type_' . $journal_info['reference_type']);
		$data['description'] = $journal_info['description'];
		$data['status'] = $journal_info['status'];
		$data['status_text'] = $this->language->get('text_status_' . $journal_info['status']);
		$data['date_added'] = date($this->language->get('date_format_short') . ' H:i', strtotime($journal_info['date_added']));

		$lines = $this->model_accounting_journal->getJournalLines($journal_info['journal_id']);

		$data['lines'] = [];
		$total_debit = 0.0;
		$total_credit = 0.0;

		foreach ($lines as $line) {
			$data['lines'][] = [
				'account_code' => $line['account_code'],
				'account_name' => $line['account_name'],
				'debit'        => $line['debit'] > 0 ? number_format((float)$line['debit'], 2) : '',
				'credit'       => $line['credit'] > 0 ? number_format((float)$line['credit'], 2) : '',
				'description'  => $line['description']
			];

			$total_debit += (float)$line['debit'];
			$total_credit += (float)$line['credit'];
		}

		$data['total_debit'] = number_format($total_debit, 2);
		$data['total_credit'] = number_format($total_credit, 2);

		$data['void'] = $this->url->link('accounting/journal.void', 'user_token=' . $this->session->data['user_token'] . '&journal_id=' . $journal_info['journal_id'], true);
		$data['back'] = $this->url->link('accounting/journal', 'user_token=' . $this->session->data['user_token']);

		$data['currency_symbol'] = $this->config->get('config_currency');

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('accounting/journal_view', $data));
	}

	/**
	 * Void (AJAX) - posts a reversing entry.
	 *
	 * @return void
	 */
	public function void(): void {
		$this->load->language('accounting/journal');

		$json = [];

		if (!$this->user->hasPermission('modify', 'accounting/journal')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$journal_id = (int)($this->request->post['journal_id'] ?? $this->request->get['journal_id'] ?? 0);

		if (!$json) {
			$this->load->model('accounting/journal');

			$reversal_id = $this->model_accounting_journal->voidJournal($journal_id, $this->user->getId());

			if (!$reversal_id) {
				$json['error'] = $this->language->get('error_void');
			} else {
				$json['success'] = $this->language->get('text_success');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
