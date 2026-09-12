<?php
namespace MDcart\Admin\Model\Accounting;
/**
 * Class BankAccount
 *
 * Bank/Cash accounts - each is a thin wrapper around one Chart-of-Accounts
 * asset account (created together, kept 1:1), adding bank-specific fields
 * (account number, IBAN, opening balance) and an optional `default_role`
 * ('pos_cash' | 'pos_card' | 'website_sales') that the automatic posting
 * hook in catalog/model/checkout/order.php reads to know which account to
 * debit for a given sale's payment method. Only one bank/cash account may
 * hold each role at a time - setting one clears it from any other.
 *
 * Can be loaded using $this->load->model('accounting/bank_account');
 *
 * @package MDcart\Admin\Model\Accounting
 */
class BankAccount extends \MDcart\System\Engine\Model {
	/**
	 * Add Bank Account (creates the paired Chart-of-Accounts row too).
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return int
	 */
	public function addBankAccount(array $data): int {
		$this->load->model('accounting/account');

		// Parent everything under the seeded "Assets" (1000) account.
		$parent_info = $this->model_accounting_account->getAccountByCode('1000');

		$code = '19' . str_pad((string)(mt_rand(0, 999)), 3, '0', STR_PAD_LEFT) . time() % 100;

		$this->db->query("INSERT INTO `" . DB_PREFIX . "account` SET `parent_id` = '" . ($parent_info ? (int)$parent_info['account_id'] : 0) . "', `code` = '" . $this->db->escape($code) . "', `name` = '" . $this->db->escape($data['name']) . "', `type` = 'asset', `is_system` = '0', `is_bank_cash` = '1', `status` = '1', `date_added` = NOW()");

		$account_id = (int)$this->db->getLastId();

		if (!empty($data['default_role'])) {
			$this->clearRole((string)$data['default_role']);
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "bank_account` SET `account_id` = '" . $account_id . "', `name` = '" . $this->db->escape($data['name']) . "', `bank_name` = '" . $this->db->escape($data['bank_name'] ?? '') . "', `account_number` = '" . $this->db->escape($data['account_number'] ?? '') . "', `iban` = '" . $this->db->escape($data['iban'] ?? '') . "', `opening_balance` = '" . (float)($data['opening_balance'] ?? 0) . "', `currency` = '" . $this->db->escape($data['currency'] ?? '') . "', `default_role` = '" . $this->db->escape($data['default_role'] ?? '') . "', `status` = '" . (int)($data['status'] ?? 1) . "', `date_added` = NOW()");

		return (int)$this->db->getLastId();
	}

	/**
	 * Edit Bank Account
	 *
	 * @param int                  $bank_account_id
	 * @param array<string, mixed> $data
	 *
	 * @return void
	 */
	public function editBankAccount(int $bank_account_id, array $data): void {
		$bank_account_info = $this->getBankAccount($bank_account_id);

		if (!$bank_account_info) {
			return;
		}

		if (!empty($data['default_role'])) {
			$this->clearRole((string)$data['default_role']);
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "bank_account` SET `name` = '" . $this->db->escape($data['name']) . "', `bank_name` = '" . $this->db->escape($data['bank_name'] ?? '') . "', `account_number` = '" . $this->db->escape($data['account_number'] ?? '') . "', `iban` = '" . $this->db->escape($data['iban'] ?? '') . "', `opening_balance` = '" . (float)($data['opening_balance'] ?? 0) . "', `currency` = '" . $this->db->escape($data['currency'] ?? '') . "', `default_role` = '" . $this->db->escape($data['default_role'] ?? '') . "', `status` = '" . (int)($data['status'] ?? 1) . "' WHERE `bank_account_id` = '" . (int)$bank_account_id . "'");

		$this->db->query("UPDATE `" . DB_PREFIX . "account` SET `name` = '" . $this->db->escape($data['name']) . "', `status` = '" . (int)($data['status'] ?? 1) . "' WHERE `account_id` = '" . (int)$bank_account_info['account_id'] . "'");
	}

	/**
	 * Delete Bank Account (and its paired Chart-of-Accounts row).
	 *
	 * @param int $bank_account_id
	 *
	 * @return void
	 */
	public function deleteBankAccount(int $bank_account_id): void {
		$bank_account_info = $this->getBankAccount($bank_account_id);

		if (!$bank_account_info) {
			return;
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "bank_account` WHERE `bank_account_id` = '" . (int)$bank_account_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "account` WHERE `account_id` = '" . (int)$bank_account_info['account_id'] . "'");
	}

	/**
	 * @param string $role
	 *
	 * @return void
	 */
	private function clearRole(string $role): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "bank_account` SET `default_role` = '' WHERE `default_role` = '" . $this->db->escape($role) . "'");
	}

	/**
	 * @param int $bank_account_id
	 *
	 * @return bool
	 */
	public function hasJournalLines(int $bank_account_id): bool {
		$bank_account_info = $this->getBankAccount($bank_account_id);

		if (!$bank_account_info) {
			return false;
		}

		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "journal_line` WHERE `account_id` = '" . (int)$bank_account_info['account_id'] . "'");

		return (bool)(int)$query->row['total'];
	}

	/**
	 * @param int $bank_account_id
	 *
	 * @return array<string, mixed>
	 */
	public function getBankAccount(int $bank_account_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "bank_account` WHERE `bank_account_id` = '" . (int)$bank_account_id . "'");

		return $query->num_rows ? $query->row : [];
	}

	/**
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getBankAccounts(array $data = []): array {
		$sql = "SELECT * FROM `" . DB_PREFIX . "bank_account` ORDER BY `name` ASC";

		if (isset($data['start']) || isset($data['limit'])) {
			$start = isset($data['start']) ? (int)$data['start'] : 0;
			$limit = isset($data['limit']) ? (int)$data['limit'] : 10;

			$sql .= " LIMIT " . max(0, $start) . "," . max(1, $limit);
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * @return int
	 */
	public function getTotalBankAccounts(): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "bank_account`");

		return (int)$query->row['total'];
	}

	/**
	 * @param int $bank_account_id
	 *
	 * @return float
	 */
	public function getBalance(int $bank_account_id): float {
		$bank_account_info = $this->getBankAccount($bank_account_id);

		if (!$bank_account_info) {
			return 0.0;
		}

		$this->load->model('accounting/account');

		return $this->model_accounting_account->getAccountBalance((int)$bank_account_info['account_id']);
	}
}
