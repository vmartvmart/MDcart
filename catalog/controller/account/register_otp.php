<?php
namespace MDcart\Catalog\Controller\Account;
/**
 * Class RegisterOtp
 *
 * Second step of registration when mobile verification is enabled
 * (other_ippanel_otp_status): the customer record already exists but is
 * held pending (status = 0) until the SMS code sent by
 * Register::register() is confirmed here.
 *
 * @package MDcart\Catalog\Controller\Account
 */
class RegisterOtp extends \MDcart\System\Engine\Controller {
	/**
	 * Maximum wrong-code attempts before the code must be resent.
	 */
	private const MAX_ATTEMPTS = 5;

	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('account/register_otp');

		if ($this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true));
		}

		if (empty($this->session->data['register_otp']['customer_id']) || empty($this->session->data['register_otp']['telephone'])) {
			$this->response->redirect($this->url->link('account/register', 'language=' . $this->config->get('config_language'), true));
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
			'href' => $this->url->link('account/register_otp', 'language=' . $this->config->get('config_language'))
		];

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];

			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		$data['telephone'] = $this->session->data['register_otp']['telephone'];
		$data['text_sent'] = sprintf($this->language->get('text_sent'), $data['telephone']);

		$this->session->data['register_otp_token'] = oc_token(26);

		$data['verify'] = $this->url->link('account/register_otp.verify', 'language=' . $this->config->get('config_language') . '&register_otp_token=' . $this->session->data['register_otp_token']);
		$data['resend'] = $this->url->link('account/register_otp.resend', 'language=' . $this->config->get('config_language') . '&register_otp_token=' . $this->session->data['register_otp_token']);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/register_otp', $data));
	}

	/**
	 * Verify
	 *
	 * @return void
	 */
	public function verify(): void {
		$this->load->language('account/register_otp');

		$json = [];

		if (empty($this->session->data['register_otp']['customer_id']) || empty($this->session->data['register_otp']['telephone'])) {
			$json['redirect'] = $this->url->link('account/register', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json && (!isset($this->request->get['register_otp_token']) || !isset($this->session->data['register_otp_token']) || ($this->request->get['register_otp_token'] != $this->session->data['register_otp_token']))) {
			$json['redirect'] = $this->url->link('account/register_otp', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			$post_info = $this->request->post + ['code' => ''];

			$customer_id = (int)$this->session->data['register_otp']['customer_id'];
			$telephone = (string)$this->session->data['register_otp']['telephone'];
			$approval = !empty($this->session->data['register_otp']['approval']);

			$this->load->model('account/customer');

			$otp_info = $this->model_account_customer->getOtp($telephone, 'register');

			if (!$otp_info) {
				$json['error']['warning'] = $this->language->get('error_expired');
			} elseif ((int)$otp_info['attempts'] >= self::MAX_ATTEMPTS) {
				$this->model_account_customer->deleteOtp($telephone, 'register');

				$json['error']['warning'] = $this->language->get('error_attempts');
			} elseif (!oc_validate_length((string)$post_info['code'], 1, 6) || ((string)$post_info['code'] !== $otp_info['code'])) {
				$this->model_account_customer->incrementOtpAttempts($telephone, 'register');

				$json['error']['code'] = $this->language->get('error_code');
			}
		}

		if (!$json) {
			$this->model_account_customer->deleteOtp($telephone, 'register');
			$this->model_account_customer->verifyTelephone($customer_id);

			$customer_info = $this->model_account_customer->getCustomerByTelephone($telephone);

			unset($this->session->data['register_otp']);
			unset($this->session->data['register_otp_token']);

			if (!$approval) {
				$this->model_account_customer->activateCustomer($customer_id);

				if ($this->customer->login((string)($customer_info['email'] ?? ''), '', true)) {
					$this->session->data['customer'] = $customer_info;

					$this->model_account_customer->addLogin($this->customer->getId(), oc_get_ip());

					$this->session->data['customer_token'] = oc_token(26);
				}

				$json['redirect'] = $this->url->link('account/success', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''), true);
			} else {
				// Phone verified, but this customer group still requires
				// admin approval before the account can log in.
				$this->session->data['success'] = $this->language->get('text_verified_pending_approval');

				$json['redirect'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'), true);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Resend
	 *
	 * @return void
	 */
	public function resend(): void {
		$this->load->language('account/register_otp');

		$json = [];

		if (empty($this->session->data['register_otp']['customer_id']) || empty($this->session->data['register_otp']['telephone'])) {
			$json['redirect'] = $this->url->link('account/register', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json && (!isset($this->request->get['register_otp_token']) || !isset($this->session->data['register_otp_token']) || ($this->request->get['register_otp_token'] != $this->session->data['register_otp_token']))) {
			$json['redirect'] = $this->url->link('account/register_otp', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			$otp_enabled = $this->config->get('other_ippanel_otp_status') && $this->config->get('other_ippanel_status') && $this->config->get('other_ippanel_api_key') && $this->config->get('other_ippanel_sender');

			if (!$otp_enabled) {
				$json['error']['warning'] = $this->language->get('error_expired');
			}
		}

		if (!$json) {
			$telephone = (string)$this->session->data['register_otp']['telephone'];
			$customer_id = (int)$this->session->data['register_otp']['customer_id'];

			$code = (string)random_int(100000, 999999);

			$this->load->model('account/customer');

			$this->model_account_customer->addOtp($telephone, 'register', $code, $customer_id);

			$this->load->library('extension/ippanel/ippanel');

			$ippanel = new \MDcart\System\Library\Extension\Ippanel\Ippanel(
				(string)$this->config->get('other_ippanel_api_key'),
				(string)$this->config->get('other_ippanel_sender')
			);

			$ippanel->send($telephone, sprintf($this->language->get('text_sms_otp'), $code));

			$json['success'] = $this->language->get('text_resent');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
