<?php
namespace MDcart\Catalog\Controller\Account;
/**
 * Class Edit
 *
 * @package MDcart\Catalog\Controller\Account
 */
class Edit extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('account/edit');

		if (!$this->load->controller('account/login.validate')) {
			$this->session->data['redirect'] = $this->url->link('account/edit', 'language=' . $this->config->get('config_language'));

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
			'href' => $this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_edit'),
			'href' => $this->url->link('account/edit', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'])
		];

		$data['error_upload_size'] = sprintf($this->language->get('error_upload_size'), $this->config->get('config_file_max_size'));

		$data['config_file_max_size'] = ((int)$this->config->get('config_file_max_size') * 1024 * 1024);

		// The field must actually be shown whenever it's going to be
		// required - see isTelephoneNeeded()'s docblock for why.
		$data['config_telephone_display'] = $this->config->get('config_telephone_display') || $this->isTelephoneNeeded();
		$data['config_telephone_required'] = $this->config->get('config_telephone_required') || $this->isTelephoneNeeded();

		$data['save'] = $this->url->link('account/edit.save', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);

		$this->session->data['upload_token'] = oc_token(32);

		$data['upload'] = $this->url->link('tool/upload', 'language=' . $this->config->get('config_language') . '&upload_token=' . $this->session->data['upload_token']);

		// Customer
		$this->load->model('account/customer');

		$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

		$data['firstname'] = $customer_info['firstname'];
		$data['lastname'] = $customer_info['lastname'];
		$data['email'] = $customer_info['email'];
		$data['telephone'] = $customer_info['telephone'];

		// Custom Fields
		$data['custom_fields'] = [];

		$this->load->model('account/custom_field');

		$custom_fields = $this->model_account_custom_field->getCustomFields($this->customer->getGroupId());

		foreach ($custom_fields as $custom_field) {
			if ($custom_field['location'] == 'account') {
				$data['custom_fields'][] = $custom_field;
			}
		}

		$data['account_custom_field'] = $customer_info['custom_field'];

		$data['back'] = $this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);

		$data['language'] = $this->config->get('config_language');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/edit', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('account/edit');

		$json = [];

		$required = [
			'firstname' => '',
			'lastname'  => '',
			'email'     => '',
			'telephone' => ''
		];

		$post_info = $this->request->post + $required;

		if (!$this->load->controller('account/login.validate')) {
			$this->session->data['redirect'] = $this->url->link('account/edit', 'language=' . $this->config->get('config_language'));

			$json['redirect'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			if (!oc_validate_length($post_info['firstname'], 1, 32)) {
				$json['error']['firstname'] = $this->language->get('error_firstname');
			}

			if (!oc_validate_length($post_info['lastname'], 1, 32)) {
				$json['error']['lastname'] = $this->language->get('error_lastname');
			}

			if (!oc_validate_email($post_info['email'])) {
				$json['error']['email'] = $this->language->get('error_email');
			}

			// Customer
			$this->load->model('account/customer');

			if (($this->customer->getEmail() != $post_info['email']) && $this->model_account_customer->getTotalCustomersByEmail($post_info['email'])) {
				$json['error']['warning'] = $this->language->get('error_exists');
			}

			if (($this->config->get('config_telephone_required') || $this->isTelephoneNeeded()) && !oc_validate_length($post_info['telephone'], 3, 32)) {
				$json['error']['telephone'] = $this->language->get('error_telephone');
			}

			// Custom fields validation
			$this->load->model('account/custom_field');

			$custom_fields = $this->model_account_custom_field->getCustomFields($this->customer->getGroupId());

			foreach ($custom_fields as $custom_field) {
				if ($custom_field['location'] == 'account') {
					if ($custom_field['required'] && empty($post_info['custom_field'][$custom_field['custom_field_id']])) {
						$json['error']['custom_field_' . $custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
					} elseif ($custom_field['type'] == 'text' && !empty($custom_field['validation']) && !oc_validate_regex($post_info['custom_field'][$custom_field['custom_field_id']], $custom_field['validation'])) {
						$json['error']['custom_field_' . $custom_field['custom_field_id']] = sprintf($this->language->get('error_regex'), $custom_field['name']);
					}
				}
			}
		}

		if (!$json) {
			// Update customer in db
			$this->model_account_customer->editCustomer($this->customer->getId(), $post_info);

			$json['success'] = $this->language->get('text_success');

			// Update customer session details
			$this->session->data['customer'] = [
				'customer_id'       => $this->customer->getId(),
				'customer_group_id' => $this->customer->getGroupId(),
				'firstname'         => $post_info['firstname'],
				'lastname'          => $post_info['lastname'],
				'email'             => $post_info['email'],
				'telephone'         => $post_info['telephone'],
				'custom_field'      => $post_info['custom_field'] ?? []
			];

			unset($this->session->data['order_id']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Whether some feature elsewhere in the store actually needs the
	 * customer to have a phone number on file, regardless of the separate
	 * config_telephone_display/config_telephone_required store settings -
	 * OTP login/registration (IPPanel) and DigiPay both do. If either is
	 * on, the field must be both visible and required here, even for an
	 * existing account created before that feature existed - otherwise a
	 * customer who needs to add one (e.g. to pay by DigiPay) has nowhere
	 * to. Confirmed live 2026-09. See the identical method in
	 * account/register.php and checkout/register.php.
	 *
	 * @return bool
	 */
	private function isTelephoneNeeded(): bool {
		$otp_enabled = (bool)($this->config->get('other_ippanel_otp_status') && $this->config->get('other_ippanel_status') && $this->config->get('other_ippanel_api_key') && $this->config->get('other_ippanel_sender'));

		$digipay_enabled = (bool)($this->config->get('payment_digipay_client_id') && $this->config->get('payment_digipay_client_secret') && $this->config->get('payment_digipay_username') && $this->config->get('payment_digipay_password'));

		return $otp_enabled || $digipay_enabled;
	}
}
