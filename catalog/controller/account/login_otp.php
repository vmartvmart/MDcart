<?php
namespace MDcart\Catalog\Controller\Account;
/**
 * Class LoginOtp
 *
 * Passwordless "login with mobile number" flow, additive to the existing
 * email+password login in Login. Only available while other_ippanel_otp_status
 * (and the IPPanel credentials it depends on) are configured.
 *
 * @package MDcart\Catalog\Controller\Account
 */
class LoginOtp extends \MDcart\System\Engine\Controller {
	/**
	 * Maximum wrong-code attempts before the code must be resent.
	 */
	private const MAX_ATTEMPTS = 5;

	/**
	 * Whether the feature is currently enabled and usable.
	 *
	 * @return bool
	 */
	private function isEnabled(): bool {
		return (bool)($this->config->get('other_ippanel_otp_status') && $this->config->get('other_ippanel_status') && $this->config->get('other_ippanel_api_key') && $this->config->get('other_ippanel_sender'));
	}

	/**
	 * Index
	 *
	 * Step 1: enter mobile number.
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('account/login_otp');

		if ($this->customer->isLogged() && isset($this->request->get['customer_token']) && isset($this->session->data['customer_token']) && ($this->request->get['customer_token'] == $this->session->data['customer_token'])) {
			$this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true));
		}

		if (!$this->isEnabled()) {
			$this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language'), true));
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', 'language=' . $this->config->get('config_language'))
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('account/login_otp', 'language=' . $this->config->get('config_language'))
		];

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];

			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->request->get['redirect'])) {
			$data['redirect'] = $this->request->get['redirect'];
		} else {
			$data['redirect'] = '';
		}

		$data['confirm'] = $this->url->link('account/login_otp.confirm', 'language=' . $this->config->get('config_language'));
		$data['login'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'));

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/login_otp', $data));
	}

	/**
	 * Confirm
	 *
	 * Step 1 submit: looks up the number, sends the SMS code.
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->load->language('account/login_otp');

		$json = [];

		if (!$this->isEnabled()) {
			$json['redirect'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			$post_info = $this->request->post + ['telephone' => '', 'redirect' => ''];

			$this->load->model('account/customer');

			$customer_info = $this->model_account_customer->getCustomerByTelephone((string)$post_info['telephone']);

			if (!$customer_info || !$customer_info['status']) {
				$json['error']['telephone'] = $this->language->get('error_not_found');
			}
		}

		if (!$json) {
			$telephone = $this->model_account_customer->normalizeTelephone((string)$post_info['telephone']);

			$code = (string)random_int(100000, 999999);

			// Never let a DB error (e.g. the customer_otp table not existing
			// yet because the migration hasn't been run) or an SMS-send
			// failure result in a silent dead end for the customer - surface
			// a clear error instead, per this project's rule that code
			// touching a migration-added table must degrade gracefully.
			try {
				$this->model_account_customer->addOtp($telephone, 'login', $code, (int)$customer_info['customer_id']);

				$this->load->library('extension/ippanel/ippanel');

				$ippanel = new \MDcart\System\Library\Extension\Ippanel\Ippanel(
					(string)$this->config->get('other_ippanel_api_key'),
					(string)$this->config->get('other_ippanel_sender')
				);

				if (!$ippanel->send($telephone, sprintf($this->language->get('text_sms_otp'), $code))) {
					throw new \RuntimeException((string)$ippanel->error);
				}
			} catch (\Throwable $e) {
				error_log('MDcart login_otp.confirm: failed to send OTP SMS - ' . $e->getMessage());

				$json['error']['warning'] = $this->language->get('error_send_failed');
			}
		}

		if (!$json && isset($telephone)) {
			$this->session->data['login_otp'] = [
				'telephone'   => $telephone,
				'customer_id' => (int)$customer_info['customer_id'],
				'redirect'    => (string)$post_info['redirect']
			];

			$json['redirect'] = $this->url->link('account/login_otp.code', 'language=' . $this->config->get('config_language'), true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Code
	 *
	 * Step 2: enter the SMS code.
	 *
	 * @return void
	 */
	public function code(): void {
		$this->load->language('account/login_otp');

		if ($this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true));
		}

		if (empty($this->session->data['login_otp']['customer_id']) || empty($this->session->data['login_otp']['telephone'])) {
			$this->response->redirect($this->url->link('account/login_otp', 'language=' . $this->config->get('config_language'), true));
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', 'language=' . $this->config->get('config_language'))
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('account/login_otp', 'language=' . $this->config->get('config_language'))
		];

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];

			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		$data['telephone'] = $this->session->data['login_otp']['telephone'];
		$data['text_sent'] = sprintf($this->language->get('text_sent'), $data['telephone']);

		$this->session->data['login_otp_token'] = oc_token(26);

		$data['verify'] = $this->url->link('account/login_otp.verify', 'language=' . $this->config->get('config_language') . '&login_otp_token=' . $this->session->data['login_otp_token']);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/login_otp_code', $data));
	}

	/**
	 * Verify
	 *
	 * Step 2 submit: validates the code and logs the customer in.
	 *
	 * @return void
	 */
	public function verify(): void {
		$this->load->language('account/login_otp');

		$json = [];

		if (empty($this->session->data['login_otp']['customer_id']) || empty($this->session->data['login_otp']['telephone'])) {
			$json['redirect'] = $this->url->link('account/login_otp', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json && (!isset($this->request->get['login_otp_token']) || !isset($this->session->data['login_otp_token']) || ($this->request->get['login_otp_token'] != $this->session->data['login_otp_token']))) {
			$json['redirect'] = $this->url->link('account/login_otp', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			$post_info = $this->request->post + ['code' => ''];

			$telephone = (string)$this->session->data['login_otp']['telephone'];

			$this->load->model('account/customer');

			$otp_info = $this->model_account_customer->getOtp($telephone, 'login');

			if (!$otp_info) {
				$json['error']['warning'] = $this->language->get('error_expired');
			} elseif ((int)$otp_info['attempts'] >= self::MAX_ATTEMPTS) {
				$this->model_account_customer->deleteOtp($telephone, 'login');

				$json['error']['warning'] = $this->language->get('error_attempts');
			} elseif (!oc_validate_length((string)$post_info['code'], 1, 6) || ((string)$post_info['code'] !== $otp_info['code'])) {
				$this->model_account_customer->incrementOtpAttempts($telephone, 'login');

				$json['error']['code'] = $this->language->get('error_code');
			}
		}

		if (!$json) {
			$customer_info = $this->model_account_customer->getCustomerByTelephone($telephone);

			if (!$customer_info || !$customer_info['status'] || !$this->customer->login($customer_info['email'], '', true)) {
				$json['error']['warning'] = $this->language->get('error_login');
			}
		}

		if (!$json) {
			$this->model_account_customer->deleteOtp($telephone, 'login');

			// Remove form token
			unset($this->session->data['login_otp_token']);

			$redirect = (string)($this->session->data['login_otp']['redirect'] ?? '');

			unset($this->session->data['login_otp']);

			// Add customer details into session
			$this->session->data['customer'] = $customer_info;

			// Unset any previous data stored in the session.
			unset($this->session->data['order_id']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);

			// Wishlist
			if (isset($this->session->data['wishlist']) && is_array($this->session->data['wishlist'])) {
				$this->load->model('account/wishlist');

				foreach ($this->session->data['wishlist'] as $key => $product_id) {
					$this->model_account_wishlist->addWishlist($this->customer->getId(), $product_id);

					unset($this->session->data['wishlist'][$key]);
				}
			}

			// Log the IP info
			$this->model_account_customer->addLogin($this->customer->getId(), oc_get_ip());

			// Create customer token
			$this->session->data['customer_token'] = oc_token(26);

			$this->model_account_customer->deleteLoginAttempts($customer_info['email']);

			if ($redirect && str_starts_with($redirect, $this->config->get('config_url'))) {
				$json['redirect'] = $redirect . '&customer_token=' . $this->session->data['customer_token'];
			} else {
				$json['redirect'] = $this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
