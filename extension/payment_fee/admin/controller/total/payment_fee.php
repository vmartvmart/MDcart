<?php
namespace MDcart\Admin\Controller\Extension\PaymentFee\Total;
/**
 * Class PaymentFee
 *
 * Settings page for the per-payment-method surcharge/discount total. Lists
 * every installed payment method (regardless of whether it is currently
 * enabled, so a rate can be prepared ahead of time) and lets the admin
 * enter a percentage for each: positive adds a surcharge, negative gives
 * a discount, zero (or blank) leaves that payment method unaffected.
 *
 * @package MDcart\Admin\Controller\Extension\PaymentFee\Total
 */
class PaymentFee extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/payment_fee/total/payment_fee');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=total')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment_fee/total/payment_fee', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/payment_fee/total/payment_fee.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=total');

		$data['payment_methods'] = $this->getPaymentMethods();

		$rates = (array)$this->config->get('total_payment_fee_rates');

		$data['total_payment_fee_rates'] = [];

		foreach ($data['payment_methods'] as $payment_method) {
			$data['total_payment_fee_rates'][$payment_method['code']] = $rates[$payment_method['code']] ?? '';
		}

		$data['total_payment_fee_status'] = $this->config->get('total_payment_fee_status');
		$data['total_payment_fee_sort_order'] = $this->config->get('total_payment_fee_sort_order') ?: 8;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment_fee/total/payment_fee', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/payment_fee/total/payment_fee');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/payment_fee/total/payment_fee')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$rates = [];

		$posted_rates = $this->request->post['total_payment_fee_rates'] ?? [];

		$valid_codes = array_column($this->getPaymentMethods(), 'code');

		foreach ($posted_rates as $code => $value) {
			// Only accept codes for payment methods that actually exist -
			// posted data is otherwise trusted only as far as this list.
			if (!in_array($code, $valid_codes, true)) {
				continue;
			}

			$value = trim((string)$value);

			if ($value === '') {
				continue;
			}

			if (!is_numeric($value)) {
				$json['error']['rate_' . $code] = $this->language->get('error_rate');

				continue;
			}

			if ((float)$value <= -100) {
				$json['error']['rate_' . $code] = $this->language->get('error_rate_min');

				continue;
			}

			$rates[$code] = (float)$value;
		}

		if (!$json) {
			$post_data = $this->request->post;
			$post_data['total_payment_fee_rates'] = $rates;

			// Setting
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('total_payment_fee', $post_data);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Every installed payment method (extension row of type "payment"),
	 * with a best-effort display name pulled from that extension's own
	 * admin language file. Falls back to a readable version of the code
	 * itself if the language file for the current admin language is
	 * missing (a pre-existing gap in a few of this store's older payment
	 * extensions) so the list is never blank.
	 *
	 * Note: $this->load->language() returns the FULL cumulative merged
	 * language data on every call, not just the keys the just-loaded file
	 * defines, so checking its return value directly can't tell a missing
	 * file apart from one that simply repeats the previous iteration's
	 * heading_title. Instead, a unique sentinel is written to 'heading_title'
	 * immediately before each load - if the load doesn't overwrite it, the
	 * file (or that key within it) doesn't exist for the current language.
	 *
	 * Loading a payment method's own admin language file also merges ALL of
	 * its keys (button_save, entry_status, help_*, etc.) into the shared
	 * Language object, which would otherwise silently clobber this settings
	 * page's own strings (already loaded by index()/save()) with whichever
	 * payment method happened to load last. A snapshot of the language data
	 * is restored after every iteration to keep each payment method's file
	 * isolated and to leave this page's own language data untouched once
	 * the loop is done.
	 *
	 * @return array<int, array{code: string, name: string}>
	 */
	private function getPaymentMethods(): array {
		$this->load->model('setting/extension');

		$results = $this->model_setting_extension->getExtensionsByType('payment');

		$methods = [];

		$snapshot = $this->language->all();

		foreach ($results as $result) {
			$sentinel = "\0missing\0" . $result['code'];

			$this->language->set('heading_title', $sentinel);

			try {
				$this->load->language('extension/' . $result['extension'] . '/payment/' . $result['code']);
			} catch (\Throwable $e) {
				// Ignore - handled by the sentinel check below.
			}

			$name = $this->language->get('heading_title');

			if ($name === $sentinel) {
				$name = ucwords(str_replace(['_', '-'], ' ', $result['code']));
			}

			$methods[] = [
				'code' => $result['code'],
				'name' => $name
			];

			// Restore before the next iteration (and before returning) so
			// no payment method's language file can leak keys into another
			// method's lookup, or into the rest of this page's render.
			$this->language->clear();

			foreach ($snapshot as $key => $value) {
				$this->language->set($key, $value);
			}
		}

		usort($methods, fn($a, $b) => strcmp($a['name'], $b['name']));

		return $methods;
	}
}
