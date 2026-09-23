<?php
namespace MDcart\Catalog\Controller\Account;
/**
 * Class Register
 *
 * @package MDcart\Catalog\Controller\Account
 */
class Register extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		if ($this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true));
		}

		$this->load->language('account/register');

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
			'text' => $this->language->get('text_register'),
			'href' => $this->url->link('account/register', 'language=' . $this->config->get('config_language'))
		];

		$data['text_account_already'] = sprintf($this->language->get('text_account_already'), $this->url->link('account/login', 'language=' . $this->config->get('config_language')));

		$data['error_upload_size'] = sprintf($this->language->get('error_upload_size'), $this->config->get('config_file_max_size'));

		$data['config_file_max_size'] = ((int)$this->config->get('config_file_max_size') * 1024 * 1024);
		$data['config_telephone_display'] = $this->config->get('config_telephone_display');
		$data['config_telephone_required'] = $this->config->get('config_telephone_required');

		// Create form token
		$this->session->data['register_token'] = oc_token(26);

		$data['register'] = $this->url->link('account/register.register', 'language=' . $this->config->get('config_language') . '&register_token=' . $this->session->data['register_token']);

		$this->session->data['upload_token'] = oc_token(32);

		$data['upload'] = $this->url->link('tool/upload', 'language=' . $this->config->get('config_language') . '&upload_token=' . $this->session->data['upload_token']);

		// Customer Groups
		$data['customer_groups'] = [];

		if (is_array($this->config->get('config_customer_group_display'))) {
			$this->load->model('account/customer_group');

			$customer_groups = $this->model_account_customer_group->getCustomerGroups();

			foreach ($customer_groups as $customer_group) {
				if (in_array($customer_group['customer_group_id'], (array)$this->config->get('config_customer_group_display'))) {
					$data['customer_groups'][] = $customer_group;
				}
			}
		}

		$data['customer_group_id'] = (int)$this->config->get('config_customer_group_id');

		// Custom Fields
		$data['custom_fields'] = [];

		$this->load->model('account/custom_field');

		$custom_fields = $this->model_account_custom_field->getCustomFields();

		foreach ($custom_fields as $custom_field) {
			if ($custom_field['location'] == 'account') {
				$data['custom_fields'][] = $custom_field;
			}
		}

		// Captcha
		$this->load->model('setting/extension');

		$extension_info = $this->model_setting_extension->getExtensionByCode('captcha', $this->config->get('config_captcha'));

		if ($extension_info && $this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('register', (array)$this->config->get('config_captcha_page'))) {
			$data['captcha'] = $this->load->controller('extension/' . $extension_info['extension'] . '/captcha/' . $extension_info['code']);
		} else {
			$data['captcha'] = '';
		}

		// Information
		$this->load->model('catalog/information');

		$information_info = $this->model_catalog_information->getInformation((int)$this->config->get('config_account_id'));

		if ($information_info) {
			$data['text_agree'] = sprintf($this->language->get('text_agree'), $this->url->link('information/information.info', 'language=' . $this->config->get('config_language') . '&information_id=' . $this->config->get('config_account_id')), $information_info['title']);
		} else {
			$data['text_agree'] = '';
		}

		$data['language'] = $this->config->get('config_language');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/register', $data));
	}

	/**
	 * Register
	 *
	 * @return void
	 */
	public function register(): void {
		$this->load->language('account/register');

		$json = [];

		$required = [
			'customer_group_id' => 0,
			'firstname'         => '',
			'lastname'          => '',
			'email'             => '',
			'telephone'         => '',
			'custom_field'      => [],
			'password'          => '',
			'agree'             => 0
		];

		$post_info = $this->request->post + $required;

		if (!isset($this->request->get['register_token']) || !isset($this->session->data['register_token']) || ($this->session->data['register_token'] != $this->request->get['register_token'])) {
			$json['redirect'] = $this->url->link('account/register', 'language=' . $this->config->get('config_language'), true);
		}

		// Captcha first to prevent probing for registered emails
		$this->load->model('setting/extension');

		$extension_info = $this->model_setting_extension->getExtensionByCode('captcha', $this->config->get('config_captcha'));

		if ($extension_info && $this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('register', (array)$this->config->get('config_captcha_page'))) {
			$captcha = $this->load->controller('extension/' . $extension_info['extension'] . '/captcha/' . $extension_info['code'] . '.validate');

			if ($captcha) {
				$json['error']['captcha'] = $captcha;
			}
		}

		if (!$json) {
			// Customer Group
			if ($post_info['customer_group_id']) {
				$customer_group_id = (int)$post_info['customer_group_id'];
			} else {
				$customer_group_id = (int)$this->config->get('config_customer_group_id');
			}

			$this->load->model('account/customer_group');

			$customer_group_info = $this->model_account_customer_group->getCustomerGroup($customer_group_id);

			if (!$customer_group_info || !in_array($customer_group_id, (array)$this->config->get('config_customer_group_display'))) {
				$json['error']['warning'] = $this->language->get('error_customer_group');
			}

			if (!oc_validate_length($post_info['firstname'], 1, 32)) {
				$json['error']['firstname'] = $this->language->get('error_firstname');
			}

			if (!oc_validate_length($post_info['lastname'], 1, 32)) {
				$json['error']['lastname'] = $this->language->get('error_lastname');
			}

			if (!oc_validate_email($post_info['email'])) {
				$json['error']['email'] = $this->language->get('error_email');
			}

			// Total Customers
			$this->load->model('account/customer');

			if ($this->model_account_customer->getTotalCustomersByEmail($post_info['email'])) {
				$json['error']['warning'] = $this->language->get('error_exists');
			}

			if ($this->config->get('config_telephone_required') && !oc_validate_length($post_info['telephone'], 3, 32)) {
				$json['error']['telephone'] = $this->language->get('error_telephone');
			}

			// Custom fields validation
			$this->load->model('account/custom_field');

			$custom_fields = $this->model_account_custom_field->getCustomFields($customer_group_id);

			foreach ($custom_fields as $custom_field) {
				if ($custom_field['location'] == 'account') {
					if ($custom_field['required'] && empty($post_info['custom_field'][$custom_field['custom_field_id']])) {
						$json['error']['custom_field_' . $custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
					} elseif (($custom_field['type'] == 'text') && !empty($custom_field['validation']) && !oc_validate_regex($post_info['custom_field'][$custom_field['custom_field_id']], $custom_field['validation'])) {
						$json['error']['custom_field_' . $custom_field['custom_field_id']] = sprintf($this->language->get('error_regex'), $custom_field['name']);
					}
				}
			}

			$password = html_entity_decode($post_info['password'], ENT_QUOTES, 'UTF-8');

			if (!oc_validate_length($password, (int)$this->config->get('config_password_length'), 40)) {
				$json['error']['password'] = sprintf($this->language->get('error_password_length'), (int)$this->config->get('config_password_length'));
			}

			$required = [];

			if ($this->config->get('config_password_uppercase') && !preg_match('/[A-Z]/', $password)) {
				$required[] = $this->language->get('error_password_uppercase');
			}

			if ($this->config->get('config_password_lowercase') && !preg_match('/[a-z]/', $password)) {
				$required[] = $this->language->get('error_password_lowercase');
			}

			if ($this->config->get('config_password_number') && !preg_match('/[0-9]/', $password)) {
				$required[] = $this->language->get('error_password_number');
			}

			if ($this->config->get('config_password_symbol') && !preg_match('/[^a-zA-Z0-9]/', $password)) {
				$required[] = $this->language->get('error_password_symbol');
			}

			if ($required) {
				$json['error']['password'] = sprintf($this->language->get('error_password'), implode(', ', $required), $this->config->get('config_password_length'));
			}

			// Agree to terms
			$this->load->model('catalog/information');

			$information_info = $this->model_catalog_information->getInformation((int)$this->config->get('config_account_id'));

			if ($information_info && !$post_info['agree']) {
				$json['error']['warning'] = sprintf($this->language->get('error_agree'), $information_info['title']);
			}
		}

		if (!$json) {
			$customer_id = $this->model_account_customer->addCustomer($post_info);

			$otp_enabled = $this->config->get('other_ippanel_otp_status') && $this->config->get('other_ippanel_status') && $this->config->get('other_ippanel_api_key') && $this->config->get('other_ippanel_sender');

			$normalized_telephone = ($post_info['telephone'] !== '') ? $this->model_account_customer->normalizeTelephone($post_info['telephone']) : '';

			$otp_send_failed = false;

			if ($otp_enabled && ($normalized_telephone !== '')) {
				// Phone verification is required before this account can be
				// used - keep it pending (status = 0) regardless of the
				// approval branch below, until the customer verifies the
				// SMS code sent to $normalized_telephone. Also store the
				// normalized number so later lookups by telephone (OTP
				// login, admin) match reliably regardless of the format the
				// customer originally typed it in.
				$this->db->query("UPDATE `" . DB_PREFIX . "customer` SET `status` = '0', `telephone` = '" . $this->db->escape($normalized_telephone) . "' WHERE `customer_id` = '" . (int)$customer_id . "'");

				$code = (string)random_int(100000, 999999);

				// A DB error (e.g. the customer_otp table not existing yet
				// because the migration hasn't been run) or an SMS-send
				// failure must not leave the customer on a dead-end page -
				// see the matching try/catch in login_otp.php::confirm().
				try {
					$this->model_account_customer->addOtp($normalized_telephone, 'register', $code, $customer_id);

					$this->sendOtpSms($normalized_telephone, $code);
				} catch (\Throwable $e) {
					error_log('MDcart register.save: failed to send registration OTP SMS - ' . $e->getMessage());

					$otp_send_failed = true;
				}
			}

			if ($otp_enabled && ($normalized_telephone !== '') && !$otp_send_failed) {
				$this->session->data['register_otp'] = [
					'customer_id' => $customer_id,
					'telephone'   => $normalized_telephone,
					'approval'    => !empty($customer_group_info['approval'])
				];

				// Remove form token
				unset($this->session->data['register_token']);

				// Clear any previous login attempts for unregistered accounts.
				$this->model_account_customer->deleteLoginAttempts($post_info['email']);

				// Clear old session data
				unset($this->session->data['order_id']);
				unset($this->session->data['guest']);
				unset($this->session->data['shipping_method']);
				unset($this->session->data['shipping_methods']);
				unset($this->session->data['payment_method']);
				unset($this->session->data['payment_methods']);

				$json['redirect'] = $this->url->link('account/register_otp', 'language=' . $this->config->get('config_language'), true);
			} elseif ($otp_send_failed) {
				// The account exists (pending, status = 0) but no working
				// OTP code was sent - tell the customer plainly rather than
				// send them to a verification page that can never succeed.
				$json['error']['warning'] = $this->language->get('error_send_failed');
			} else {
				// Login if requires approval
				if (!$customer_group_info['approval']) {
					$this->customer->login($post_info['email'], html_entity_decode($post_info['password'], ENT_QUOTES, 'UTF-8'));

					// Add customer details into session
					$this->session->data['customer'] = [
						'customer_id'       => $customer_id,
						'customer_group_id' => $customer_group_id,
						'firstname'         => $post_info['firstname'],
						'lastname'          => $post_info['lastname'],
						'email'             => $post_info['email'],
						'telephone'         => $post_info['telephone'],
						'custom_field'      => $post_info['custom_field']
					];

					// Log the IP info
					$this->model_account_customer->addLogin($this->customer->getId(), oc_get_ip());

					// Create customer token
					$this->session->data['customer_token'] = oc_token(26);
				}

				// Remove form token
				unset($this->session->data['register_token']);

				// Clear any previous login attempts for unregistered accounts.
				$this->model_account_customer->deleteLoginAttempts($post_info['email']);

				// Clear old session data
				unset($this->session->data['order_id']);
				unset($this->session->data['guest']);
				unset($this->session->data['shipping_method']);
				unset($this->session->data['shipping_methods']);
				unset($this->session->data['payment_method']);
				unset($this->session->data['payment_methods']);

				$json['redirect'] = $this->url->link('account/success', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''), true);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Send Otp Sms
	 *
	 * Sends a registration OTP code through the IPPanel SMS integration.
	 * Caller must have already confirmed IPPanel + OTP are enabled and
	 * configured.
	 *
	 * @param string $telephone normalized telephone number
	 * @param string $code      numeric OTP code
	 *
	 * @return void
	 */
	private function sendOtpSms(string $telephone, string $code): void {
		$this->load->language('account/register_otp');
		$this->load->library('extension/ippanel/ippanel');

		$ippanel = new \MDcart\System\Library\Extension\Ippanel\Ippanel(
			(string)$this->config->get('other_ippanel_api_key'),
			(string)$this->config->get('other_ippanel_sender')
		);

		$ippanel->send($telephone, sprintf($this->language->get('text_sms_otp'), $code));
	}
}
