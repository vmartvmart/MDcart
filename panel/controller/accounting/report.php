<?php
namespace MDcart\Admin\Controller\Accounting;
/**
 * Class Report
 *
 * Two classic double-entry reports: the General Ledger (every posted
 * line for one chosen account, with a running balance) and the Trial
 * Balance (every account's total debit/credit for a date range - the
 * whole book should always net to zero if it balances).
 *
 * Can be loaded using $this->load->controller('accounting/report');
 *
 * @package MDcart\Admin\Controller\Accounting
 */
class Report extends \MDcart\System\Engine\Controller {
	/**
	 * Ledger
	 *
	 * @return void
	 */
	public function ledger(): void {
		$this->load->language('accounting/report');

		$this->document->setTitle($this->language->get('heading_title_ledger'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title_ledger'),
			'href' => $this->url->link('accounting/report.ledger', 'user_token=' . $this->session->data['user_token'])
		];

		$data['filter_action'] = $this->url->link('accounting/report.ledger', 'user_token=' . $this->session->data['user_token'], true);

		$this->load->model('accounting/account');

		$data['accounts'] = $this->model_accounting_account->getAccounts();

		$data['filter_account_id'] = $this->request->get['filter_account_id'] ?? '';
		$data['filter_date_start'] = $this->request->get['filter_date_start'] ?? date('Y-m-01');
		$data['filter_date_end'] = $this->request->get['filter_date_end'] ?? date('Y-m-d');

		$data['rows'] = [];
		$data['opening_balance'] = '0.00';
		$data['closing_balance'] = '0.00';
		$data['account_name'] = '';

		if (!empty($data['filter_account_id'])) {
			$account_id = (int)$data['filter_account_id'];
			$account_info = $this->model_accounting_account->getAccount($account_id);

			if ($account_info) {
				$data['account_name'] = $account_info['code'] . ' - ' . $account_info['name'];

				$this->load->model('accounting/journal');

				$opening = 0.0;

				if ($account_info['is_bank_cash']) {
					$this->load->model('accounting/bank_account');

					$bank_query = $this->db->query("SELECT `opening_balance` FROM `" . DB_PREFIX . "bank_account` WHERE `account_id` = '" . $account_id . "'");
					$opening = $bank_query->num_rows ? (float)$bank_query->row['opening_balance'] : 0.0;
				}

				// Balance carried in from before the filtered date range.
				if (!empty($data['filter_date_start'])) {
					$before = $this->model_accounting_journal->getLedger($account_id, ['filter_date_end' => date('Y-m-d', strtotime($data['filter_date_start'] . ' -1 day'))]);

					foreach ($before as $line) {
						$opening += (in_array($account_info['type'], ['asset', 'expense'], true)) ? ((float)$line['debit'] - (float)$line['credit']) : ((float)$line['credit'] - (float)$line['debit']);
					}
				}

				$lines = $this->model_accounting_journal->getLedger($account_id, $data);

				$running = $opening;

				foreach ($lines as $line) {
					$delta = (in_array($account_info['type'], ['asset', 'expense'], true)) ? ((float)$line['debit'] - (float)$line['credit']) : ((float)$line['credit'] - (float)$line['debit']);
					$running += $delta;

					$data['rows'][] = [
						'date_added'     => date($this->language->get('date_format_short'), strtotime($line['date_added'])),
						'journal_number' => $line['journal_number'],
						'reference_type' => $this->language->get('text_type_' . $line['reference_type']),
						'description'    => $line['description'],
						'debit'          => $line['debit'] > 0 ? number_format((float)$line['debit'], 2) : '',
						'credit'         => $line['credit'] > 0 ? number_format((float)$line['credit'], 2) : '',
						'running'        => number_format($running, 2),
						'view'           => $this->url->link('accounting/journal.view', 'user_token=' . $this->session->data['user_token'] . '&journal_id=' . $line['journal_id'])
					];
				}

				$data['opening_balance'] = number_format($opening, 2);
				$data['closing_balance'] = number_format($running, 2);
			}
		}

		$data['user_token'] = $this->session->data['user_token'];
		$data['currency_symbol'] = $this->config->get('config_currency');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('accounting/ledger', $data));
	}

	/**
	 * Trial Balance
	 *
	 * @return void
	 */
	public function trial_balance(): void {
		$this->load->language('accounting/report');

		$this->document->setTitle($this->language->get('heading_title_trial_balance'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title_trial_balance'),
			'href' => $this->url->link('accounting/report.trial_balance', 'user_token=' . $this->session->data['user_token'])
		];

		$data['filter_action'] = $this->url->link('accounting/report.trial_balance', 'user_token=' . $this->session->data['user_token'], true);

		$data['filter_date_start'] = $this->request->get['filter_date_start'] ?? '';
		$data['filter_date_end'] = $this->request->get['filter_date_end'] ?? date('Y-m-d');

		$this->load->model('accounting/journal');
		$this->load->model('accounting/bank_account');

		$results = $this->model_accounting_journal->getTrialBalance($data);

		$data['rows'] = [];
		$total_debit = 0.0;
		$total_credit = 0.0;

		foreach ($results as $result) {
			$debit = (float)$result['debit'];
			$credit = (float)$result['credit'];

			if (!empty($result['is_bank_cash'])) {
				$bank_query = $this->db->query("SELECT `opening_balance` FROM `" . DB_PREFIX . "bank_account` WHERE `account_id` = '" . (int)$result['account_id'] . "'");

				if ($bank_query->num_rows) {
					$debit += (float)$bank_query->row['opening_balance'];
				}
			}

			$total_debit += $debit;
			$total_credit += $credit;

			$data['rows'][] = [
				'code'    => $result['code'],
				'name'    => $result['name'],
				'type'    => $this->language->get('text_type_' . $result['type']),
				'debit'   => number_format($debit, 2),
				'credit'  => number_format($credit, 2),
				'balance' => number_format($debit - $credit, 2)
			];
		}

		$data['total_debit'] = number_format($total_debit, 2);
		$data['total_credit'] = number_format($total_credit, 2);
		$data['balanced'] = (round($total_debit, 2) === round($total_credit, 2));

		$data['user_token'] = $this->session->data['user_token'];
		$data['currency_symbol'] = $this->config->get('config_currency');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('accounting/trial_balance', $data));
	}
}
