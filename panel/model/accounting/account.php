<?php
namespace Opencart\Admin\Model\Accounting;
/**
 * Class Account
 *
 * Chart of Accounts. Seeded accounts (install-accounting-schema.php) are
 * marked `is_system` and can never be deleted or re-typed - everything
 * else (custom expense categories added under "General Expenses", for
 * example) is fully editable/deletable as long as it has no journal
 * lines posted against it yet.
 *
 * Can be loaded using $this->load->model('accounting/account');
 *
 * @package Opencart\Admin\Model\Accounting
 */
class Account extends \Opencart\System\Engine\Model {
	/**
	 * Add Account
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return int
	 */
	public function addAccount(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "account` SET `parent_id` = '" . (int)$data['parent_id'] . "', `code` = '" . $this->db->escape($data['code']) . "', `name` = '" . $this->db->escape($data['name']) . "', `type` = '" . $this->db->escape($data['type']) . "', `is_system` = '0', `is_bank_cash` = '0', `status` = '" . (int)$data['status'] . "', `date_added` = NOW()");

		return (int)$this->db->getLastId();
	}

	/**
	 * Edit Account
	 *
	 * @param int                  $account_id
	 * @param array<string, mixed> $data
	 *
	 * @return void
	 */
	public function editAccount(int $account_id, array $data): void {
		$account_info = $this->getAccount($account_id);

		if (!$account_info) {
			return;
		}

		// System accounts keep their code/type/parent - only the display name
		// (and status) can change, so automatic postings never break.
		if ($account_info['is_system']) {
			$this->db->query("UPDATE `" . DB_PREFIX . "account` SET `name` = '" . $this->db->escape($data['name']) . "', `status` = '" . (int)$data['status'] . "' WHERE `account_id` = '" . (int)$account_id . "'");
		} else {
			$this->db->query("UPDATE `" . DB_PREFIX . "account` SET `parent_id` = '" . (int)$data['parent_id'] . "', `code` = '" . $this->db->escape($data['code']) . "', `name` = '" . $this->db->escape($data['name']) . "', `type` = '" . $this->db->escape($data['type']) . "', `status` = '" . (int)$data['status'] . "' WHERE `account_id` = '" . (int)$account_id . "'");
		}
	}

	/**
	 * Delete Account
	 *
	 * @param int $account_id
	 *
	 * @return void
	 */
	public function deleteAccount(int $account_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "account` WHERE `account_id` = '" . (int)$account_id . "'");
	}

	/**
	 * @param int $account_id
	 *
	 * @return bool
	 */
	public function hasChildren(int $account_id): bool {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "account` WHERE `parent_id` = '" . (int)$account_id . "'");

		return (bool)(int)$query->row['total'];
	}

	/**
	 * @param int $account_id
	 *
	 * @return bool
	 */
	public function hasJournalLines(int $account_id): bool {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "journal_line` WHERE `account_id` = '" . (int)$account_id . "'");

		return (bool)(int)$query->row['total'];
	}

	/**
	 * @param int $account_id
	 *
	 * @return array<string, mixed>
	 */
	public function getAccount(int $account_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "account` WHERE `account_id` = '" . (int)$account_id . "'");

		return $query->num_rows ? $query->row : [];
	}

	/**
	 * @param int $account_id
	 *
	 * @return array<string, mixed>
	 */
	public function getAccountByCode(string $code): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "account` WHERE `code` = '" . $this->db->escape($code) . "'");

		return $query->num_rows ? $query->row : [];
	}

	/**
	 * All accounts, ordered so every account immediately follows its
	 * parent (indent by `depth` in the view for a simple tree look).
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getAccounts(array $data = []): array {
		$sql = "SELECT * FROM `" . DB_PREFIX . "account`";

		$where = [];

		if (!empty($data['filter_type'])) {
			$where[] = "`type` = '" . $this->db->escape($data['filter_type']) . "'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$where[] = "`status` = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['exclude_bank_cash'])) {
			$where[] = "`is_bank_cash` = '0'";
		}

		if ($where) {
			$sql .= " WHERE " . implode(' AND ', $where);
		}

		$sql .= " ORDER BY `code` ASC";

		$query = $this->db->query($sql);

		$by_parent = [];

		foreach ($query->rows as $row) {
			$by_parent[(int)$row['parent_id']][] = $row;
		}

		$flatten = function (int $parent_id, int $depth) use (&$flatten, &$by_parent): array {
			$result = [];

			foreach ($by_parent[$parent_id] ?? [] as $row) {
				$row['depth'] = $depth;
				$result[] = $row;
				$result = array_merge($result, $flatten((int)$row['account_id'], $depth + 1));
			}

			return $result;
		};

		return $flatten(0, 0);
	}

	/**
	 * @return int
	 */
	public function getTotalAccounts(): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "account`");

		return (int)$query->row['total'];
	}

	/**
	 * Current balance of an account (all-time), as (debit - credit) for
	 * asset/expense accounts, or (credit - debit) for liability/equity/
	 * revenue accounts - so a positive number always reads as "the normal,
	 * healthy side" for that account type.
	 *
	 * @param int $account_id
	 *
	 * @return float
	 */
	public function getAccountBalance(int $account_id): float {
		$account_info = $this->getAccount($account_id);

		if (!$account_info) {
			return 0.0;
		}

		$query = $this->db->query("SELECT SUM(`debit`) AS `debit`, SUM(`credit`) AS `credit` FROM `" . DB_PREFIX . "journal_line` `jl` LEFT JOIN `" . DB_PREFIX . "journal` `j` ON (`jl`.`journal_id` = `j`.`journal_id`) WHERE `jl`.`account_id` = '" . (int)$account_id . "' AND `j`.`status` = 'posted'");

		$debit = (float)($query->row['debit'] ?? 0);
		$credit = (float)($query->row['credit'] ?? 0);

		$opening = 0.0;

		if ($account_info['is_bank_cash']) {
			$bank_query = $this->db->query("SELECT `opening_balance` FROM `" . DB_PREFIX . "bank_account` WHERE `account_id` = '" . (int)$account_id . "'");
			$opening = $bank_query->num_rows ? (float)$bank_query->row['opening_balance'] : 0.0;
		}

		if (in_array($account_info['type'], ['asset', 'expense'], true)) {
			return $opening + $debit - $credit;
		}

		return $opening + $credit - $debit;
	}
}
