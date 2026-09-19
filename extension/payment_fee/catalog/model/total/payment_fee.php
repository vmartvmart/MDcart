<?php
namespace MDcart\Catalog\Model\Extension\PaymentFee\Total;
/**
 * Class PaymentFee
 *
 * Adds a surcharge or discount line to the order totals based on which
 * payment method the customer has selected, e.g. "+10% when paying with
 * BitPay" or "-5% for card-to-card". Configured per payment method in
 * Extensions > Totals > Payment Method Fee/Discount.
 *
 * Can be called from $this->load->model('extension/payment_fee/total/payment_fee');
 *
 * @package MDcart\Catalog\Model\Extension\PaymentFee\Total
 */
class PaymentFee extends \MDcart\System\Engine\Model {
	/**
	 * Get Total
	 *
	 * Runs whenever order totals are calculated (checkout/confirm, and
	 * again at the moment the order is actually placed), so it always
	 * reflects whichever payment method is currently selected in session
	 * - switching payment methods live-updates this line, and the order
	 * is saved with whichever rate applied at the moment of purchase.
	 *
	 * @param array<int, array<string, mixed>> $totals
	 * @param array<int, float>                $taxes
	 * @param float                            $total
	 *
	 * @return void
	 */
	public function getTotal(array &$totals, array &$taxes, float &$total): void {
		if (!isset($this->session->data['payment_method']['code'])) {
			return;
		}

		// The stored code is "{extension_code}.{option_code}" (e.g.
		// "bitpay.bitpay", "card_to_card.card_to_card") - rates are
		// configured per extension, not per option, since almost every
		// gateway here only ever offers a single option.
		[$payment_code] = explode('.', $this->session->data['payment_method']['code']);

		$rates = (array)$this->config->get('total_payment_fee_rates');

		if (!isset($rates[$payment_code]) || (float)$rates[$payment_code] == 0) {
			return;
		}

		$rate = (float)$rates[$payment_code];

		// The rate applies to the order as calculated so far (products,
		// shipping, tax, any coupon/reward/credit already applied) - not
		// to the original sub-total - so a coupon or discount is honoured
		// before the payment surcharge/discount is layered on top.
		$value = round($total * ($rate / 100), 2);

		if ($value == 0) {
			return;
		}

		$this->load->language('extension/payment_fee/total/payment_fee');

		if ($rate > 0) {
			$title = sprintf($this->language->get('text_surcharge'), $this->session->data['payment_method']['name'], $rate);
		} else {
			$title = sprintf($this->language->get('text_discount'), $this->session->data['payment_method']['name'], abs($rate));
		}

		$totals[] = [
			'extension'  => 'payment_fee',
			'code'       => 'payment_fee',
			'title'      => $title,
			'value'      => $value,
			'sort_order' => (int)$this->config->get('total_payment_fee_sort_order')
		];

		$total += $value;
	}
}
