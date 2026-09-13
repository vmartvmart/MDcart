<?php
namespace MDcart\Admin\Model\Catalog;
/**
 * Class PurchaseInvoice
 *
 * A purchase invoice ("فاکتور خرید") is an append-only record: it is the
 * normal way stock and cost price enter a warehouse (rather than typing
 * numbers directly into the product's Warehouses tab). Confirming one
 * immediately increases the chosen warehouse's stock and folds the new cost
 * into a running weighted-average cost price, in that warehouse's own
 * currency. If the warehouse happens to be the selling warehouse,
 * oc_product.quantity is kept in sync automatically.
 *
 * Since MDcart 5.6, an invoice also carries the real financial side of the
 * purchase: which Supplier it is owed to, what currency/payment type was
 * used, and (when the accounting module - oc_account/oc_journal - is
 * installed) a balanced journal entry posted automatically on save:
 *
 *   Debit  Inventory (1300)         for the invoice total, converted to the
 *                                   store's base/functional currency
 *                                   (config_currency) at today's rate
 *   Credit the chosen Bank/Cash account   for the paid portion
 *   Credit Accounts Payable (2100)        for the unpaid/credit portion
 *
 * A supplier's own currency (AED, USD, ...) is tracked in its ORIGINAL
 * amount - the debt is never re-valued just because the exchange rate moves
 * later. Only when the credit portion is actually settled (addPurchaseInvoicePayment())
 * does a Foreign Exchange Gain/Loss (5900) line absorb the difference
 * between the rate captured here and the rate on the day it is paid.
 *
 * Assumption (same simplifying choice already made for warehouses): an
 * invoice's currency_code always follows the WAREHOUSE it stocks, since the
 * line items' unit_cost values are entered directly in that warehouse's
 * currency and folded straight into oc_product_warehouse.cost_price. A
 * supplier's own `currency_code` (set on the Supplier record) is only a
 * default/informational hint - it is not enforced against the warehouse's
 * currency, since in principle a supplier could invoice in a currency that
 * differs from the warehouse's, however uncommon that is expected to be in
 * practice.
 *
 * Can be loaded using $this->load->model('catalog/purchase_invoice');
 *
 * @package MDcart\Admin\Model\Catalog
 */
class PurchaseInvoice extends \MDcart\System\Engine\Model {
	/**
	 * Add Purchase Invoice - applies the stock/cost increase immediately and,
	 * when the accounting module is installed, posts the matching journal
	 * entry.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return int
	 */
	public function addPurchaseInvoice(array $data): int {
		$warehouse_id = (int)$data['warehouse_id'];

		$warehouse_query = $this->db->query("SELECT `is_selling_warehouse`, `currency_code` FROM `" . DB_PREFIX . "warehouse` WHERE `warehouse_id` = '" . $warehouse_id . "'");
		$is_selling_warehouse = $warehouse_query->num_rows && $warehouse_query->row['is_selling_warehouse'];
		$currency_code = $warehouse_query->num_rows ? (string)$warehouse_query->row['currency_code'] : '';

		// The invoice's own total, in the warehouse's currency - computed up
		// front from the line items so it can be stored on the header row
		// and used for the payment split below.
		$total_amount = 0.0;

		foreach ((array)($data['products'] ?? []) as $product) {
			$total_amount += (float)($product['quantity'] ?? 0) * (float)($product['unit_cost'] ?? 0);
		}

		$supplier_id = (int)($data['supplier_id'] ?? 0);
		$supplier_name = (string)($data['supplier_name'] ?? '');

		// Snapshot the supplier's name onto the invoice at save time, so the
		// invoice list/view still reads correctly even if the supplier is
		// later renamed - the free-text supplier_name field predates
		// Suppliers and is kept only as this snapshot / a legacy fallback
		// for invoices that have no supplier_id.
		if ($supplier_id) {
			$this->load->model('accounting/supplier');

			$supplier_info = $this->model_accounting_supplier->getSupplier($supplier_id);

			if ($supplier_info) {
				$supplier_name = $supplier_info['name'];
			}
		}

		$payment_type = (string)($data['payment_type'] ?? 'credit');

		if (!in_array($payment_type, ['cash', 'bank', 'credit', 'combined'], true)) {
			$payment_type = 'credit';
		}

		$bank_account_id = (int)($data['bank_account_id'] ?? 0);

		if ($payment_type === 'cash' || $payment_type === 'bank') {
			$paid_amount = $total_amount;
		} elseif ($payment_type === 'combined') {
			$paid_amount = (float)($data['paid_amount'] ?? 0);

			if ($paid_amount < 0) {
				$paid_amount = 0.0;
			}

			if ($paid_amount > $total_amount) {
				$paid_amount = $total_amount;
			}
		} else {
			// credit
			$paid_amount = 0.0;
			$bank_account_id = 0;
		}

		// Capture today's conversion rate from the invoice's own currency
		// into the store's base/functional currency (config_currency) -
		// stored on the row so later reports and the eventual settlement's
		// FX gain/loss calculation both use the rate that was actually in
		// effect on the invoice date, not whatever the rate happens to be
		// when someone looks the invoice up later.
		$store_currency = (string)$this->config->get('config_currency');

		if ($currency_code && $currency_code !== $store_currency && $this->currency->has($currency_code) && $this->currency->has($store_currency)) {
			$exchange_rate = $this->currency->convert(1.0, $currency_code, $store_currency);
		} else {
			$exchange_rate = 1.0;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "purchase_invoice` SET `warehouse_id` = '" . $warehouse_id . "', `supplier_id` = '" . $supplier_id . "', `supplier_name` = '" . $this->db->escape($supplier_name) . "', `currency_code` = '" . $this->db->escape($currency_code) . "', `exchange_rate` = '" . (float)$exchange_rate . "', `total_amount` = '" . (float)$total_amount . "', `payment_type` = '" . $this->db->escape($payment_type) . "', `bank_account_id` = '" . $bank_account_id . "', `paid_amount` = '" . (float)$paid_amount . "', `invoice_number` = '" . $this->db->escape((string)($data['invoice_number'] ?? '')) . "', `invoice_date` = " . (!empty($data['invoice_date']) ? "'" . $this->db->escape((string)$data['invoice_date']) . "'" : "NULL") . ", `comment` = '" . $this->db->escape((string)($data['comment'] ?? '')) . "', `user_id` = '" . (int)($data['user_id'] ?? 0) . "', `date_added` = NOW()");

		$purchase_invoice_id = $this->db->getLastId();

		foreach ((array)($data['products'] ?? []) as $product) {
			$product_id = (int)($product['product_id'] ?? 0);
			$quantity = (int)($product['quantity'] ?? 0);
			$unit_cost = (float)($product['unit_cost'] ?? 0);
			$barcode = (string)($product['barcode'] ?? '');

			if (!$product_id || $quantity < 1) {
				continue;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "purchase_invoice_product` SET `purchase_invoice_id` = '" . (int)$purchase_invoice_id . "', `product_id` = '" . $product_id . "', `quantity` = '" . $quantity . "', `unit_cost` = '" . $unit_cost . "', `barcode` = '" . $this->db->escape($barcode) . "'");

			// Weighted-average cost: fold this purchase in with whatever stock/cost
			// this warehouse already has for the product.
			$existing_query = $this->db->query("SELECT `quantity`, `cost_price` FROM `" . DB_PREFIX . "product_warehouse` WHERE `product_id` = '" . $product_id . "' AND `warehouse_id` = '" . $warehouse_id . "'");

			$existing_quantity = $existing_query->num_rows ? (int)$existing_query->row['quantity'] : 0;
			$existing_cost = $existing_query->num_rows ? (float)$existing_query->row['cost_price'] : 0;

			$new_quantity = $existing_quantity + $quantity;
			$new_cost = $new_quantity > 0 ? (($existing_quantity * $existing_cost) + ($quantity * $unit_cost)) / $new_quantity : $unit_cost;

			$this->db->query("INSERT INTO `" . DB_PREFIX . "product_warehouse` (`product_id`, `warehouse_id`, `quantity`, `cost_price`, `date_modified`) VALUES ('" . $product_id . "', '" . $warehouse_id . "', '" . $new_quantity . "', '" . $new_cost . "', NOW()) ON DUPLICATE KEY UPDATE `quantity` = '" . $new_quantity . "', `cost_price` = '" . $new_cost . "', `date_modified` = NOW()");

			if ($is_selling_warehouse) {
				$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity` = '" . $new_quantity . "' WHERE `product_id` = '" . $product_id . "'");
			}
		}

		$this->postInvoiceJournal((int)$purchase_invoice_id, $total_amount, $paid_amount, (float)$exchange_rate, $bank_account_id, (int)($data['user_id'] ?? 0));

		return $purchase_invoice_id;
	}

	/**
	 * Posts the invoice-date journal entry (Debit Inventory, Credit Bank/Cash
	 * for the paid portion, Credit Accounts Payable for the credit portion),
	 * if the accounting module is installed and there is anything to post.
	 *
	 * @param int    $purchase_invoice_id
	 * @param float  $total_amount     invoice's own currency
	 * @param float  $paid_amount      invoice's own currency
	 * @param float  $exchange_rate    invoice currency -> store base currency
	 * @param int    $bank_account_id
	 * @param int    $user_id
	 *
	 * @return void
	 */
	private function postInvoiceJournal(int $purchase_invoice_id, float $total_amount, float $paid_amount, float $exchange_rate, int $bank_account_id, int $user_id): void {
		if (!$total_amount || !$this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "journal'")->num_rows) {
			return;
		}

		$this->load->model('accounting/account');

		$inventory_account = $this->model_accounting_account->getAccountByCode('1300');
		$ap_account = $this->model_accounting_account->getAccountByCode('2100');

		if (!$inventory_account) {
			return;
		}

		$total_base = round($total_amount * $exchange_rate, 4);
		$paid_base = round($paid_amount * $exchange_rate, 4);
		$credit_base = round($total_base - $paid_base, 4);

		$lines = [
			['account_id' => (int)$inventory_account['account_id'], 'debit' => $total_base, 'credit' => 0, 'description' => 'Purchase invoice #' . $purchase_invoice_id]
		];

		if ($paid_base > 0 && $bank_account_id) {
			$this->load->model('accounting/bank_account');

			$bank_account_info = $this->model_accounting_bank_account->getBankAccount($bank_account_id);

			if ($bank_account_info) {
				$lines[] = ['account_id' => (int)$bank_account_info['account_id'], 'debit' => 0, 'credit' => $paid_base, 'description' => 'Purchase invoice #' . $purchase_invoice_id . ' - paid'];
			}
		}

		if ($credit_base > 0 && $ap_account) {
			$lines[] = ['account_id' => (int)$ap_account['account_id'], 'debit' => 0, 'credit' => $credit_base, 'description' => 'Purchase invoice #' . $purchase_invoice_id . ' - on credit'];
		}

		if (count($lines) < 2) {
			// Nothing to balance against (e.g. paid in full but no bank
			// account resolved, or fully on credit but the Accounts Payable
			// system account is missing) - skip rather than post a lopsided
			// entry.
			return;
		}

		$this->load->model('accounting/journal');

		$this->model_accounting_journal->addJournal([
			'reference_type' => 'purchase_invoice',
			'reference_id'   => $purchase_invoice_id,
			'description'    => 'Purchase invoice #' . $purchase_invoice_id,
			'user_id'        => $user_id,
			'lines'          => $lines
		]);
	}

	/**
	 * Record Payment - settles part (or the rest) of a credit/combined
	 * invoice's outstanding balance, in the invoice's own original currency.
	 * Posts Debit Accounts Payable (at the rate the invoice was originally
	 * booked at) / Credit the chosen Bank/Cash account (at today's rate),
	 * with any difference absorbed by the Foreign Exchange Gain/Loss account
	 * - see the class docblock.
	 *
	 * @param array<string, mixed> $data {purchase_invoice_id, bank_account_id, amount, user_id}
	 *
	 * @return int 0 if the invoice/amount/bank account was invalid
	 */
	public function addPurchaseInvoicePayment(array $data): int {
		$purchase_invoice_id = (int)($data['purchase_invoice_id'] ?? 0);
		$bank_account_id = (int)($data['bank_account_id'] ?? 0);
		$amount = round((float)($data['amount'] ?? 0), 4);

		if (!$purchase_invoice_id || !$bank_account_id || $amount <= 0) {
			return 0;
		}

		$invoice_info = $this->getPurchaseInvoice($purchase_invoice_id);

		if (!$invoice_info) {
			return 0;
		}

		$outstanding = round((float)$invoice_info['total_amount'] - (float)$invoice_info['paid_amount'], 4);

		if ($outstanding <= 0) {
			return 0;
		}

		// Tolerate tiny float rounding but never let a payment overshoot
		// what is actually still owed.
		if ($amount > $outstanding + 0.0001) {
			$amount = $outstanding;
		}

		$currency_code = (string)$invoice_info['currency_code'];
		$store_currency = (string)$this->config->get('config_currency');
		$invoice_rate = (float)$invoice_info['exchange_rate'];

		if ($currency_code && $currency_code !== $store_currency && $this->currency->has($currency_code) && $this->currency->has($store_currency)) {
			$settlement_rate = $this->currency->convert(1.0, $currency_code, $store_currency);
		} else {
			$settlement_rate = 1.0;
		}

		$journal_id = 0;

		if ($this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "journal'")->num_rows) {
			$this->load->model('accounting/account');
			$this->load->model('accounting/bank_account');
			$this->load->model('accounting/journal');

			$ap_account = $this->model_accounting_account->getAccountByCode('2100');
			$fx_account = $this->model_accounting_account->getAccountByCode('5900');
			$bank_account_info = $this->model_accounting_bank_account->getBankAccount($bank_account_id);

			if ($ap_account && $bank_account_info) {
				$amount_at_invoice_rate = round($amount * $invoice_rate, 4);
				$amount_at_settlement_rate = round($amount * $settlement_rate, 4);
				$fx_diff = round($amount_at_settlement_rate - $amount_at_invoice_rate, 4);

				$lines = [
					['account_id' => (int)$ap_account['account_id'], 'debit' => $amount_at_invoice_rate, 'credit' => 0, 'description' => 'Settle purchase invoice #' . $purchase_invoice_id]
				];

				if ($fx_diff > 0 && $fx_account) {
					// Rate moved against us since the invoice was booked -
					// paying more base currency than the debt was recorded
					// at, so the extra is a Foreign Exchange LOSS.
					$lines[] = ['account_id' => (int)$fx_account['account_id'], 'debit' => $fx_diff, 'credit' => 0, 'description' => 'FX loss on purchase invoice #' . $purchase_invoice_id . ' settlement'];
				}

				$lines[] = ['account_id' => (int)$bank_account_info['account_id'], 'debit' => 0, 'credit' => $amount_at_settlement_rate, 'description' => 'Settle purchase invoice #' . $purchase_invoice_id];

				if ($fx_diff < 0 && $fx_account) {
					// Rate moved in our favour - paying less base currency
					// than the debt was recorded at, so the difference is a
					// Foreign Exchange GAIN.
					$lines[] = ['account_id' => (int)$fx_account['account_id'], 'debit' => 0, 'credit' => abs($fx_diff), 'description' => 'FX gain on purchase invoice #' . $purchase_invoice_id . ' settlement'];
				}

				$journal_id = $this->model_accounting_journal->addJournal([
					'reference_type' => 'purchase_invoice_payment',
					'reference_id'   => $purchase_invoice_id,
					'description'    => 'Payment - Purchase invoice #' . $purchase_invoice_id,
					'user_id'        => (int)($data['user_id'] ?? 0),
					'lines'          => $lines
				]);
			}
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "purchase_invoice_payment` SET `purchase_invoice_id` = '" . $purchase_invoice_id . "', `bank_account_id` = '" . $bank_account_id . "', `amount` = '" . $amount . "', `exchange_rate` = '" . (float)$settlement_rate . "', `journal_id` = '" . (int)$journal_id . "', `user_id` = '" . (int)($data['user_id'] ?? 0) . "', `date_added` = NOW()");

		$purchase_invoice_payment_id = (int)$this->db->getLastId();

		$this->db->query("UPDATE `" . DB_PREFIX . "purchase_invoice` SET `paid_amount` = `paid_amount` + '" . $amount . "' WHERE `purchase_invoice_id` = '" . $purchase_invoice_id . "'");

		return $purchase_invoice_payment_id;
	}

	/**
	 * Get Purchase Invoice
	 *
	 * @param int $purchase_invoice_id
	 *
	 * @return array<string, mixed>
	 */
	public function getPurchaseInvoice(int $purchase_invoice_id): array {
		$query = $this->db->query("SELECT `pi`.*, `w`.`name` AS `warehouse_name`, `w`.`currency_code` AS `warehouse_currency_code`, `s`.`name` AS `supplier_display_name` FROM `" . DB_PREFIX . "purchase_invoice` `pi` LEFT JOIN `" . DB_PREFIX . "warehouse` `w` ON (`w`.`warehouse_id` = `pi`.`warehouse_id`) LEFT JOIN `" . DB_PREFIX . "supplier` `s` ON (`s`.`supplier_id` = `pi`.`supplier_id`) WHERE `pi`.`purchase_invoice_id` = '" . (int)$purchase_invoice_id . "'");

		return $query->row;
	}

	/**
	 * Get Purchase Invoices
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getPurchaseInvoices(array $data = []): array {
		$sql = "SELECT `pi`.*, `w`.`name` AS `warehouse_name` FROM `" . DB_PREFIX . "purchase_invoice` `pi` LEFT JOIN `" . DB_PREFIX . "warehouse` `w` ON (`w`.`warehouse_id` = `pi`.`warehouse_id`) ORDER BY `pi`.`date_added` DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			$start = isset($data['start']) ? (int)$data['start'] : 0;
			$limit = isset($data['limit']) ? (int)$data['limit'] : 10;

			if ($start < 0) {
				$start = 0;
			}

			if ($limit < 1) {
				$limit = 10;
			}

			$sql .= " LIMIT " . $start . "," . $limit;
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Get Total Purchase Invoices
	 *
	 * @return int
	 */
	public function getTotalPurchaseInvoices(): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "purchase_invoice`");

		return (int)$query->row['total'];
	}

	/**
	 * Get Purchase Invoice Products
	 *
	 * @param int $purchase_invoice_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getPurchaseInvoiceProducts(int $purchase_invoice_id): array {
		$query = $this->db->query("SELECT `pip`.*, `pd`.`name` FROM `" . DB_PREFIX . "purchase_invoice_product` `pip` LEFT JOIN `" . DB_PREFIX . "product_description` `pd` ON (`pd`.`product_id` = `pip`.`product_id` AND `pd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "') WHERE `pip`.`purchase_invoice_id` = '" . (int)$purchase_invoice_id . "'");

		return $query->rows;
	}

	/**
	 * Get Purchase Invoice Payments - the settlement history of a
	 * credit/combined invoice, newest first.
	 *
	 * @param int $purchase_invoice_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getPurchaseInvoicePayments(int $purchase_invoice_id): array {
		$query = $this->db->query("SELECT `pip`.*, `ba`.`name` AS `bank_account_name` FROM `" . DB_PREFIX . "purchase_invoice_payment` `pip` LEFT JOIN `" . DB_PREFIX . "bank_account` `ba` ON (`ba`.`bank_account_id` = `pip`.`bank_account_id`) WHERE `pip`.`purchase_invoice_id` = '" . (int)$purchase_invoice_id . "' ORDER BY `pip`.`date_added` DESC");

		return $query->rows;
	}
}
