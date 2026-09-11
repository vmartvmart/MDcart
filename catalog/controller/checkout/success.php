<?php
namespace Opencart\Catalog\Controller\Checkout;
/**
 * Class Success
 *
 * @package Opencart\Catalog\Controller\Checkout
 */
class Success extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('checkout/success');

		$order_id = (int)($this->session->data['order_id'] ?? 0);

		if (isset($this->session->data['order_id'])) {
			$this->cart->clear();

			unset($this->session->data['order_id']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['comment']);
			unset($this->session->data['agree']);
			unset($this->session->data['coupon']);
			unset($this->session->data['reward']);
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_basket'),
			'href' => $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'))
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_checkout'),
			'href' => $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'))
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_success'),
			'href' => $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'))
		];

		if ($this->customer->isLogged()) {
			$data['text_message'] = sprintf($this->language->get('text_customer'), $this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']), $this->url->link('account/order', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']), $this->url->link('account/download', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']), $this->url->link('information/contact', 'language=' . $this->config->get('config_language')));
		} else {
			$data['text_message'] = sprintf($this->language->get('text_guest'), $this->url->link('information/contact', 'language=' . $this->config->get('config_language')));
		}

		$data['continue'] = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));

		$data['messenger_links'] = [];

		if ($order_id) {
			$telegram_link = $this->getMessengerConnectLink('telegram', $order_id, 'other_telegram_status', 'other_telegram_bot_username', 'https://t.me/');

			if ($telegram_link) {
				$data['messenger_links'][] = ['channel' => 'Telegram', 'url' => $telegram_link];
			}

			$bale_link = $this->getMessengerConnectLink('bale', $order_id, 'other_bale_status', 'other_bale_bot_username', 'https://ble.ir/');

			if ($bale_link) {
				$data['messenger_links'][] = ['channel' => 'Bale', 'url' => $bale_link];
			}
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/success', $data));
	}

	/**
	 * Build (or reuse) a "connect your account" deep link for a messenger channel
	 * (Telegram/Bale) so the customer can opt in to receive updates for this order.
	 * Returns null if that channel's extension isn't installed/enabled/configured.
	 *
	 * @param string $channel         'telegram' or 'bale'
	 * @param int    $order_id
	 * @param string $status_key      config key for the channel's enabled toggle
	 * @param string $username_key    config key for the channel's bot username
	 * @param string $link_domain     e.g. 'https://t.me/'
	 *
	 * @return string|null
	 */
	private function getMessengerConnectLink(string $channel, int $order_id, string $status_key, string $username_key, string $link_domain): ?string {
		if (!$this->config->get($status_key) || !$this->config->get($username_key)) {
			return null;
		}

		$query = $this->db->query("SELECT `token` FROM `" . DB_PREFIX . "notify_link` WHERE `channel` = '" . $this->db->escape($channel) . "' AND `purpose` = 'order' AND `order_id` = '" . (int)$order_id . "' AND `status` = '0' ORDER BY `link_id` DESC LIMIT 1");

		if ($query->row) {
			$token = $query->row['token'];
		} else {
			$token = bin2hex(random_bytes(16));

			$this->db->query("INSERT INTO `" . DB_PREFIX . "notify_link` SET `channel` = '" . $this->db->escape($channel) . "', `token` = '" . $this->db->escape($token) . "', `order_id` = '" . (int)$order_id . "', `customer_id` = '" . (int)$this->customer->getId() . "', `purpose` = 'order', `date_added` = NOW()");
		}

		return $link_domain . $this->config->get($username_key) . '?start=order_' . $token;
	}
}
