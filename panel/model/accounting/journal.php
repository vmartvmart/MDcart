<?php
namespace MDcart\Admin\Model\Accounting;
/**
 * Class Journal
 *
 * The general journal (دفتر روزنامه). Every accounting event in this
 * system - manual entries, quick Expense/Receipt/Payment forms, and the
 * automatic postings the checkout hook makes for POS/storefront sales -
 * ends up as one row here (a header) plus 2+ balanced rows in
 * oc_journal_line (debit total must equal credit total). Posted entries
 * are never edited or hard-deleted - correcting one means posting a
 * reversing entry with voidJournal(), which is standard double-entry
 * practice and keeps a full audit trail.
 *
 * Can be loaded using $this->load->model('accounting/journal');
 *
 * @package MDcart\Admin\Model\Accounting
 */
class Journal extends \MDcart\System\Engine\Model {
	/**
	 * Add a free-form, multi-line manual journal entry. $data['lines'] is
	 * an array of ['account_id' => int, 'debit' => float, 'credit' => float,
	 * 'description' => string]; total debit must equal total credit or
	 * nothing is written.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return int 0 if the entry did not balance or had fewer than 2 lines
	 */
	public function addJournal(array $data): int {
		$lines = $data['lines'] ?? [];

		if (count($lines) < 2) {
			return 0;
		}

		$total_debit = 0.0;
		$total_credit = 0.0;

		foreach ($lines as $line) {
			$total_debit += (float)($line['debit'] ?? 0);
			$total_credit += (float)($line['credit'] ?? 0);
		}

		if (round($total_debit, 4) !== round($total_credit, 4) || $total_debit == 0.0) {
			return 0;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "journal` SET `journal_number` = '" . $this->db->escape($this->nextJournalNumber()) . "', `reference_type` = '" . $this->db->escape($data['reference_type'] ?? 'manual') . "', `reference_id` = '" . (int)($data['reference_id'] ?? 0) . "', `description` = '" . $this->db->escape((string)($data['description'] ?? '')) . "', `date_added` = NOW(), `user_id` = '" . (int)($data['user_id'] ?? 0) . "', `status` = 'posted', `reversal_of` = '0'");

		$journal_id = (int)$this->db->getLastId();

		foreach ($lines as $line) {
			$debit = (float)($line['debit'] ?? 0);
			$credit = (float)($line['credit'] ?? 0);

			if (!$debit && !$credit) {
				continue;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "journal_line` SET `journal_id` = '" . $journal_id . "', `account_id` = '" . (int)$line['account_id'] . "', `debit` = '" . $debit . "', `credit` = '" . $credit . "', `description` = '" . $this->db->escape((string)($line['description'] ?? '')) . "'");
		}

		return $journal_id;
	}

	/**
	 * Quick Expense entry: Debit the expense category account, Credit the
	 * bank/cash account money left from.
	 *
	 * @param array<string, mixed> $data {bank_account_id, category_account_id, amount, description, user_id, date_added}
	 *
	 * @return int
	 */
	public function addExpense(array $data): int {
		return $this->addJournal([
			'reference_type' => 'expense',
			'description'    => (string)($data['description'] ?? ''),
			'user_id'        => (int)($data['user_id'] ?? 0),
			'lines'          => [
				['account_id' => (int)$data['category_account_id'], 'debit' => (float)$data['amount'], 'credit' => 0, 'description' => (string)($data['description'] ?? '')],
				['account_id' => (int)$data['bank_account_id'], 'debit' => 0, 'credit' => (float)$data['amount'], 'description' => (string)($data['description'] ?? '')]
			]
		]);
	}

	/**
	 * Quick Receipt entry (money coming IN that is not a storefront/POS
	 * sale - e.g. a manual deposit, a refund received, other income):
	 * Debit the bank/cash account, Credit the chosen income/other account.
	 *
	 * @param array<string, mixed> $data {bank_account_id, category_account_id, amount, description, user_id}
	 *
	 * @return int
	 */
	public function addReceipt(array $data): int {
		return $this->addJournal([
			'reference_type' => 'receipt',
			'description'    => (string)($data['description'] ?? ''),
			'user_id'        => (int)($data['user_id'] ?? 0),
			'lines'          => [
				['account_id' => (int)$data['bank_account_id'], 'debit' => (float)$data['amount'], 'credit' => 0, 'description' => (string)($data['description'] ?? '')],
				['account_id' => (int)$data['category_account_id'], 'debit' => 0, 'credit' => (float)$data['amount'], 'description' => (string)($data['description'] ?? '')]
			]
		]);
	}

	/**
	 * Quick Payment entry (paying down a liability or other outgoing
	 * payment that is not a day-to-day operating expense - e.g. paying a
	 * supplier invoice, a loan instalment, an owner draw): Debit the
	 * chosen account, Credit the bank/cash account money left from.
	 *
	 * @param array<string, mixed> $data {bank_account_id, category_account_id, amount, description, user_id}
	 *
	 * @return int
	 */
	public function addPayment(array $data): int {
		return $this->addJournal([
			'reference_type' => 'payment',
			'description'    => (string)($data['description'] ?? ''),
			'user_id'        => (int)($data['user_id'] ?? 0),
			'lines'          => [
				['account_id' => (int)$data['category_account_id'], 'debit' => (float)$data['amount'], 'credit' => 0, 'description' => (string)($data['description'] ?? '')],
				['account_id' => (int)$data['bank_account_id'], 'debit' => 0, 'credit' => (float)$data['amount'], 'description' => (string)($data['description'] ?? '')]
			]
		]);
	}

	/**
	 * Void a posted journal by posting an equal-and-opposite reversing
	 * entry (never edits or deletes the original - full audit trail).
	 *
	 * @param int $journal_id
	 * @param int $user_id
	 *
	 * @return int the new reversing journal's ID, or 0 if nothing to reverse
	 */
	public function voidJournal(int $journal_id, int $user_id = 0): int {
		$journal_info = $this->getJournal($journal_id);

		if (!$journal_info || $journal_info['status'] !== 'posted') {
			return 0;
		}

		$lines = $this->getJournalLines($journal_id);

		if (!$lines) {
			return 0;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "journal` SET `journal_number` = '" . $this->db->escape($this->nextJournalNumber()) . "', `reference_type` = '" . $this->db->escape($journal_info['reference_type']) . "', `reference_id` = '" . (int)$journal_info['reference_id'] . "', `description` = '" . $this->db->escape('Reversal of ' . ($journal_info['journal_number'] ?: ('#' . $journal_id))) . "', `date_added` = NOW(), `user_id` = '" . (int)$user_id . "', `status` = 'posted', `reversal_of` = '" . (int)$journal_id . "'");

		$reversal_id = (int)$this->db->getLastId();

		foreach ($lines as $line) {
			// Swap debit/credit - the exact opposite of the original line.
			$this->db->query("INSERT INTO `" . DB_PREFIX . "journal_line` SET `journal_id` = '" . $reversal_id . "', `account_id` = '" . (int)$line['account_id'] . "', `debit` = '" . (float)$line['credit'] . "', `credit` = '" . (float)$line['debit'] . "', `description` = '" . $this->db->escape('Reversal: ' . $line['description']) . "'");
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "journal` SET `status` = 'voided' WHERE `journal_id` = '" . (int)$journal_id . "'");

		return $reversal_id;
	}

	/**
	 * @param int $journal_id
	 *
	 * @return array<string, mixed>
	 */
	public function getJournal(int $journal_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "journal` WHERE `journal_id` = '" . (int)$journal_id . "'");

		return $query->num_rows ? $query->row : [];
	}

	/**
	 * @param int $journal_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getJournalLines(int $journal_id): array {
		$query = $this->db->query("SELECT `jl`.*, `a`.`code` AS `account_code`, `a`.`name` AS `account_name` FROM `" . DB_PREFIX . "journal_line` `jl` LEFT JOIN `" . DB_PREFIX . "account` `a` ON (`jl`.`account_id` = `a`.`account_id`) WHERE `jl`.`journal_id` = '" . (int)$journal_id . "' ORDER BY `jl`.`journal_line_id` ASC");

		return $query->rows;
	}

	/**
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getJournals(array $data = []): array {
		$sql = "SELECT `j`.*, (SELECT SUM(`debit`) FROM `" . DB_PREFIX . "journal_line` WHERE `journal_id` = `j`.`journal_id`) AS `total_debit` FROM `" . DB_PREFIX . "journal` `j`";

		$sql .= $this->buildWhere($data);

		$sql .= " ORDER BY `j`.`journal_id` DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			$start = isset($data['start']) ? (int)$data['start'] : 0;
			$limit = isset($data['limit']) ? (int)$data['limit'] : 20;

			$sql .= " LIMIT " . max(0, $start) . "," . max(1, $limit);
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * @param array<string, mixed> $data
	 *
	 * @return int
	 */
	public function getTotalJournals(array $data = []): int {
		$sql = "SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "journal` `j`";

		$sql .= $this->buildWhere($data);

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * General ledger for one account: every posted line touching it, in
	 * date order, with a running balance.
	 *
	 * @param int                  $account_id
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getLedger(int $account_id, array $data = []): array {
		$sql = "SELECT `jl`.`debit`, `jl`.`credit`, `jl`.`description`, `j`.`journal_id`, `j`.`journal_number`, `j`.`reference_type`, `j`.`reference_id`, `j`.`date_added` FROM `" . DB_PREFIX . "journal_line` `jl` LEFT JOIN `" . DB_PREFIX . "journal` `j` ON (`jl`.`journal_id` = `j`.`journal_id`) WHERE `jl`.`account_id` = '" . (int)$account_id . "' AND `j`.`status` = 'posted'";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(`j`.`date_added`) >= '" . $this->db->escape((string)$data['filter_date_start']) . "'";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(`j`.`date_added`) <= '" . $this->db->escape((string)$data['filter_date_end']) . "'";
		}

		$sql .= " ORDER BY `j`.`date_added` ASC, `j`.`journal_id` ASC";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * @param array<string, mixed> $data
	 *
	 * @return string
	 */
	private function buildWhere(array $data): string {
		$where = [];

		if (!empty($data['filter_reference_type'])) {
			$where[] = "`j`.`reference_type` = '" . $this->db->escape((string)$data['filter_reference_type']) . "'";
		}

		if (!empty($data['filter_status'])) {
			$where[] = "`j`.`status` = '" . $this->db->escape((string)$data['filter_status']) . "'";
		}

		if (!empty($data['filter_date_start'])) {
			$where[] = "DATE(`j`.`date_added`) >= '" . $this->db->escape((string)$data['filter_date_start']) . "'";
		}

		if (!empty($data['filter_date_end'])) {
			$where[] = "DATE(`j`.`date_added`) <= '" . $this->db->escape((string)$data['filter_date_end']) . "'";
		}

		if (!empty($data['filter_account_id'])) {
			$where[] = "EXISTS (SELECT 1 FROM `" . DB_PREFIX . "journal_line` WHERE `journal_id` = `j`.`journal_id` AND `account_id` = '" . (int)$data['filter_account_id'] . "')";
		}

		return $where ? (" WHERE " . implode(' AND ', $where)) : '';
	}

	/**
	 * @return string
	 */
	private function nextJournalNumber(): string {
		$query = $this->db->query("SELECT MAX(`journal_id`) AS `max_id` FROM `" . DB_PREFIX . "journal`");

		$next = (int)($query->row['max_id'] ?? 0) + 1;

		return 'JE-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
	}

	/**
	 * Trial balance: every account with any posted activity (or a
	 * non-zero opening balance), with its total debit/credit and net
	 * balance - should always sum to zero overall if the books are
	 * balanced.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getTrialBalance(array $data = []): array {
		$sql = "SELECT `a`.`account_id`, `a`.`code`, `a`.`name`, `a`.`type`, `a`.`is_bank_cash`, SUM(`jl`.`debit`) AS `debit`, SUM(`jl`.`credit`) AS `credit` FROM `" . DB_PREFIX . "account` `a` LEFT JOIN `" . DB_PREFIX . "journal_line` `jl` ON (`a`.`account_id` = `jl`.`account_id`) LEFT JOIN `" . DB_PREFIX . "journal` `j` ON (`jl`.`journal_id` = `j`.`journal_id` AND `j`.`status` = 'posted'";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(`j`.`date_added`) >= '" . $this->db->escape((string)$data['filter_date_start']) . "'";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(`j`.`date_added`) <= '" . $this->db->escape((string)$data['filter_date_end']) . "'";
		}

		$sql .= ")";
		$sql .= " GROUP BY `a`.`account_id` ORDER BY `a`.`code` ASC";

		$query = $this->db->query($sql);

		$rows = [];

		foreach ($query->rows as $row) {
			// Skip pure header/grouping accounts (no bank/cash pairing, no
			// activity at all, and never posted to) - keeps the report
			// focused on accounts that actually carry a balance.
			if (!$row['debit'] && !$row['credit'] && !$row['is_bank_cash']) {
				continue;
			}

			$rows[] = $row;
		}

		return $rows;
	}
}
