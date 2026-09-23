<?php
namespace MDcart\Catalog\Controller\Account;
/**
 * Class Login
 *
 * Can be loaded using $this->load->controller('account/login');
 *
 * @package MDcart\Catalog\Controller\Account
 */
class Login extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('account/login');

		$this->document->setTitle($this->language->get('heading_title'));

		// If already logged in and has matching token then redirect to account page
		if ($this->customer->isLogged() && isset($this->request->get['customer_token']) && isset($this->session->data['customer_token']) && ($this->request->get['customer_token'] == $this->session->data['customer_token'])) {
			$this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true));
		}

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
			'text' => $this->language->get('text_login'),
			'href' => $this->url->link('account/login', 'language=' . $this->config->get('config_language'))
		];

		// Check to see if user is using incorrect token
		if (isset($this->session->data['customer_token'])) {
			$data['error_warning'] = $this->language->get('error_token');

			$this->customer->logout();

			unset($this->session->data['order_id']);
			unset($this->session->data['customer']);
			unset($this->session->data['shipping_address']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_address']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['comment']);
			unset($this->session->data['coupon']);
			unset($this->session->data['reward']);
			unset($this->session->data['customer_token']);
		} elseif (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];

			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->session->data['redirect'])) {
			$data['redirect'] = $this->session->data['redirect'];

			unset($this->session->data['redirect']);
		} elseif (isset($this->request->get['redirect'])) {
			$data['redirect'] = $this->request->get['redirect'];
		} else {
			$data['redirect'] = '';
		}

		$this->session->data['login_token'] = oc_token(26);

		$data['login'] = $this->url->link('account/login.login', 'language=' . $this->config->get('config_language') . '&login_token=' . $this->session->data['login_token']);
		$data['check'] = $this->url->link('account/login.check', 'language=' . $this->config->get('config_language'));
		$data['register'] = $this->url->link('account/register', 'language=' . $this->config->get('config_language'));
		$data['forgotten'] = $this->url->link('account/forgotten', 'language=' . $this->config->get('config_language'));

		// Prefill support: coming back from an identifier that turned out
		// to have no matching account, the visitor doesn't have to retype
		// it here either.
		if (isset($this->request->get['identifier'])) {
			$data['identifier'] = (string)$this->request->get['identifier'];
		} else {
			$data['identifier'] = '';
		}

		$data['error_identifier'] = $this->language->get('error_identifier');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/login', $data));
	}

	/**
	 * Login
	 *
	 * @return void
	 */
	public function login(): void {
		$this->load->language('account/login');

		$json = [];

		// Stop any undefined index messages. 'identifier' is the new
		// unified email-or-mobile field; 'email' is kept as a fallback so
		// nothing that still posts the old field name breaks.
		$required = [
			'identifier' => '',
			'email'      => '',
			'password'   => '',
			'redirect'   => ''
		];

		$post_info = $this->request->post + $required;

		$identifier = ($post_info['identifier'] !== '') ? (string)$post_info['identifier'] : (string)$post_info['email'];

		$this->customer->logout();

		if (!isset($this->request->get['login_token']) || !isset($this->session->data['login_token']) || ($this->request->get['login_token'] != $this->session->data['login_token'])) {
			$json['redirect'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			// Check how many login attempts have been made.
			$this->load->model('account/customer');

			$login_info = $this->model_account_customer->getLoginAttempts($identifier);

			if ($login_info && ($login_info['total'] >= $this->config->get('config_login_attempts')) && strtotime('-1 hour') < strtotime($login_info['date_modified'])) {
				$json['error']['warning'] = $this->language->get('error_attempts');
			}
		}

		if (!$json) {
			// Resolve the identifier to a customer - email if it looks like
			// one, otherwise treat it as a mobile number.
			if (str_contains($identifier, '@')) {
				$customer_info = $this->model_account_customer->getCustomerByEmail($identifier);
			} else {
				$customer_info = $this->model_account_customer->getCustomerByTelephone($identifier);
			}

			if ($customer_info && !$customer_info['status']) {
				$json['error']['warning'] = $this->language->get('error_approved');
			} elseif (!$customer_info || !$this->customer->login($customer_info['email'], html_entity_decode($post_info['password'], ENT_QUOTES, 'UTF-8'))) {
				$json['error']['warning'] = $this->language->get('error_login');

				$this->model_account_customer->addLoginAttempt($identifier);
			}
		}

		if (!$json) {
			// Remove form token from session
			unset($this->session->data['login_token']);

			// Add customer details into session
			$this->session->data['customer'] = [
				'customer_id'       => $customer_info['customer_id'],
				'customer_group_id' => $customer_info['customer_group_id'],
				'firstname'         => $customer_info['firstname'],
				'lastname'          => $customer_info['lastname'],
				'email'             => $customer_info['email'],
				'telephone'         => $customer_info['telephone'],
				'custom_field'      => $customer_info['custom_field']
			];

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

			$this->model_account_customer->deleteLoginAttempts($identifier);

			if (isset($post_info['redirect'])) {
				$redirect = urldecode(html_entity_decode($post_info['redirect'], ENT_QUOTES, 'UTF-8'));
			} else {
				$redirect = '';
			}

			// Added strpos check to pass McAfee PCI compliance test (http://forum.opencart.com/viewtopic.php?f=10&t=12043&p=151494#p151295)
			if ($redirect && str_starts_with($redirect, $this->config->get('config_url'))) {
				$json['redirect'] = $redirect . '&customer_token=' . $this->session->data['customer_token'];
			} else {
				$json['redirect'] = $this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Check
	 *
	 * AJAX endpoint used by the unified login form: resolves a posted
	 * email-or-mobile identifier to either an existing account (so the
	 * template can reveal the password field, plus an OTP-login link when
	 * eligible) or a new one (so the template can send the visitor into
	 * registration with the typed value prefilled).
	 *
	 * Deliberately never returns the resolved e-mail address - a
	 * phone-number identifier must not let a visitor probing with just a
	 * phone number discover the e-mail address on the account.
	 *
	 * @return void
	 */
	public function check(): void {
		$this->load->language('account/login');

		$json = [];

		$post_info = $this->request->post + ['identifier' => ''];

		$identifier = trim((string)$post_info['identifier']);

		if ($identifier === '') {
			$json['error'] = $this->language->get('error_identifier');
		}

		if (!$json) {
			$this->load->model('account/customer');

			$is_email = str_contains($identifier, '@');

			if ($is_email) {
				$customer_info = $this->model_account_customer->getCustomerByEmail($identifier);
			} else {
				$customer_info = $this->model_account_customer->getCustomerByTelephone($identifier);
			}

			if ($customer_info) {
				$json['state'] = 'existing';

				if (!$is_email && $customer_info['telephone'] && $this->config->get('other_ippanel_otp_status') && $this->config->get('other_ippanel_status') && $this->config->get('other_ippanel_api_key') && $this->config->get('other_ippanel_sender')) {
					$json['otp'] = $this->url->link('account/login_otp', 'language=' . $this->config->get('config_language') . '&telephone=' . urlencode($identifier));
				} else {
					$json['otp'] = '';
				}
			} else {
				$json['state'] = 'new';

				if ($is_email) {
					$json['register'] = $this->url->link('account/register', 'language=' . $this->config->get('config_language') . '&email=' . urlencode($identifier));
				} else {
					$json['register'] = $this->url->link('account/register', 'language=' . $this->config->get('config_language') . '&telephone=' . urlencode($identifier));
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Token
	 *
	 * @return void
	 */
	public function token(): void {
		$this->load->language('account/login');

		if (isset($this->request->get['email'])) {
			$email = $this->request->get['email'];
		} else {
			$email = '';
		}

		if (isset($this->request->get['code'])) {
			$code = $this->request->get['code'];
		} else {
			$code = '';
		}

		// Login override for admin users
		$this->customer->logout();
		$this->cart->clear();

		unset($this->session->data['order_id']);
		unset($this->session->data['payment_address']);
		unset($this->session->data['payment_method']);
		unset($this->session->data['payment_methods']);
		unset($this->session->data['shipping_address']);
		unset($this->session->data['shipping_method']);
		unset($this->session->data['shipping_methods']);
		unset($this->session->data['comment']);
		unset($this->session->data['coupon']);
		unset($this->session->data['reward']);
		unset($this->session->data['customer_token']);

		// Customer
		$this->load->model('account/customer');

		$customer_info = $this->model_account_customer->getTokenByCode($code);

		if ($customer_info && $customer_info['email'] == $email && $customer_info['type'] == 'login' && $this->customer->login($customer_info['email'], '', true)) {
			// Add customer details into session
			$this->session->data['customer'] = $customer_info;

			// Default Address
			$this->load->model('account/address');

			$address_info = $this->model_account_address->getAddress($this->customer->getId(), $this->customer->getAddressId());

			if ($address_info) {
				$this->session->data['shipping_address'] = $address_info;
			}

			if ($this->config->get('config_tax_customer') && $address_info) {
				$this->session->data[$this->config->get('config_tax_customer') . '_address'] = $address_info;
			}

			$this->model_account_customer->deleteTokenByCode($code);

			// Create customer token
			$this->session->data['customer_token'] = oc_token(26);

			$this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true));
		} else {
			$this->session->data['error'] = $this->language->get('error_login');

			$this->model_account_customer->deleteTokenByCode($code);

			$this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language'), true));
		}
	}

	/**
	 * Validate
	 *
	 * @return bool
	 */
	public function validate(): bool {
		return !(!$this->customer->isLogged() || (!isset($this->request->get['customer_token']) || !isset($this->session->data['customer_token']) || ($this->request->get['customer_token'] != $this->session->data['customer_token'])));
	}
}
