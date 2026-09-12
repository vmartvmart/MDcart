<?php
namespace MDcart\Admin\Controller\Extension\MarketingBroadcast\Other;
/**
 * Class MarketingBroadcast
 *
 * @package MDcart\Admin\Controller\Extension\MarketingBroadcast\Other
 */
class MarketingBroadcast extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/marketing_broadcast/other/marketing_broadcast');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=other')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/marketing_broadcast/other/marketing_broadcast', 'user_token=' . $this->session->data['user_token'])
		];

		$data['send'] = $this->url->link('extension/marketing_broadcast/other/marketing_broadcast.send', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=other');

		$data['channel_available'] = [
			'sms'      => (bool)$this->config->get('other_ippanel_status'),
			'whatsapp' => (bool)$this->config->get('other_whatsapp_status'),
			'telegram' => (bool)$this->config->get('other_telegram_status'),
			'bale'     => (bool)$this->config->get('other_bale_status')
		];

		$data['other_whatsapp_template_order_add'] = $this->config->get('other_whatsapp_template_order_add');

		$results = $this->db->query("SELECT * FROM `" . DB_PREFIX . "marketing_broadcast` ORDER BY `broadcast_id` DESC LIMIT 20")->rows;

		$data['history'] = [];

		foreach ($results as $result) {
			$data['history'][] = [
				'message'      => oc_substr($result['message'], 0, 80),
				'audience'     => $result['audience'],
				'channels'     => $result['channels'],
				'total_sent'   => (int)$result['total_sent'],
				'total_failed' => (int)$result['total_failed'],
				'date_added'   => oc_jdate('Y/m/d H:i', strtotime($result['date_added']))
			];
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/marketing_broadcast/other/marketing_broadcast', $data));
	}

	/**
	 * Send
	 *
	 * @return void
	 */
	public function send(): void {
		$this->load->language('extension/marketing_broadcast/other/marketing_broadcast');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/marketing_broadcast/other/marketing_broadcast')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$message = trim((string)($this->request->post['message'] ?? ''));
		$channels = (array)($this->request->post['channels'] ?? []);
		$audience = (string)($this->request->post['audience'] ?? 'newsletter');

		if (!$json && !$message) {
			$json['error'] = $this->language->get('error_message');
		}

		if (!$json && !$channels) {
			$json['error'] = $this->language->get('error_channels');
		}

		if (!$json) {
			$result = $this->broadcast($message, $channels, $audience);

			$this->db->query("INSERT INTO `" . DB_PREFIX . "marketing_broadcast` SET `message` = '" . $this->db->escape($message) . "', `audience` = '" . $this->db->escape($audience) . "', `channels` = '" . $this->db->escape(implode(',', $channels)) . "', `total_sent` = '" . (int)$result['sent'] . "', `total_failed` = '" . (int)$result['failed'] . "', `date_added` = NOW()");

			$json['success'] = sprintf($this->language->get('text_success'), $result['sent'], $result['failed']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @param string        $message
	 * @param array<string> $channels
	 * @param string        $audience
	 *
	 * @return array{sent: int, failed: int}
	 */
	private function broadcast(string $message, array $channels, string $audience): array {
		$sent = 0;
		$failed = 0;

		if (in_array('sms', $channels, true) && $this->config->get('other_ippanel_status') && class_exists('\MDcart\System\Library\Extension\Ippanel\Ippanel')) {
			$client = new \MDcart\System\Library\Extension\Ippanel\Ippanel((string)$this->config->get('other_ippanel_api_key'), (string)$this->config->get('other_ippanel_sender'));

			foreach ($this->getCustomerPhones($audience) as $phone) {
				$client->send($phone, $message) ? $sent++ : $failed++;
			}
		}

		if (in_array('whatsapp', $channels, true) && $this->config->get('other_whatsapp_status') && $this->config->get('other_whatsapp_template_order_add') && class_exists('\MDcart\System\Library\Extension\Whatsapp\Whatsapp')) {
			$client = new \MDcart\System\Library\Extension\Whatsapp\Whatsapp((string)$this->config->get('other_whatsapp_phone_number_id'), (string)$this->config->get('other_whatsapp_access_token'));
			$language_code = (string)($this->config->get('other_whatsapp_language_code') ?: 'en_US');

			foreach ($this->getCustomerPhones($audience) as $phone) {
				$client->sendTemplate($phone, (string)$this->config->get('other_whatsapp_template_order_add'), $language_code, [$this->language->get('text_promo_label'), $message]) ? $sent++ : $failed++;
			}
		}

		if (in_array('telegram', $channels, true) && $this->config->get('other_telegram_status') && class_exists('\MDcart\System\Library\Extension\Telegram\Telegram')) {
			$client = new \MDcart\System\Library\Extension\Telegram\Telegram((string)$this->config->get('other_telegram_bot_token'));

			foreach ($this->getLinkedChatIds('telegram', $audience) as $chat_id) {
				$client->sendMessage($chat_id, $message) ? $sent++ : $failed++;
			}
		}

		if (in_array('bale', $channels, true) && $this->config->get('other_bale_status') && class_exists('\MDcart\System\Library\Extension\Bale\Bale')) {
			$client = new \MDcart\System\Library\Extension\Bale\Bale((string)$this->config->get('other_bale_bot_token'));

			foreach ($this->getLinkedChatIds('bale', $audience) as $chat_id) {
				$client->sendMessage($chat_id, $message) ? $sent++ : $failed++;
			}
		}

		return ['sent' => $sent, 'failed' => $failed];
	}

	/**
	 * @param string $audience 'newsletter' or 'all'
	 *
	 * @return array<string>
	 */
	private function getCustomerPhones(string $audience): array {
		$sql = "SELECT `telephone` FROM `" . DB_PREFIX . "customer` WHERE `status` = '1' AND `telephone` != ''";

		if ($audience === 'newsletter') {
			$sql .= " AND `newsletter` = '1'";
		}

		return array_column($this->db->query($sql)->rows, 'telephone');
	}

	/**
	 * @param string $channel
	 * @param string $audience
	 *
	 * @return array<string>
	 */
	private function getLinkedChatIds(string $channel, string $audience): array {
		$sql = "SELECT `nl`.`chat_id` FROM `" . DB_PREFIX . "notify_link` `nl` WHERE `nl`.`channel` = '" . $this->db->escape($channel) . "' AND `nl`.`purpose` = 'customer' AND `nl`.`status` = '1' AND `nl`.`chat_id` != ''";

		if ($audience === 'newsletter') {
			$sql = "SELECT `nl`.`chat_id` FROM `" . DB_PREFIX . "notify_link` `nl` JOIN `" . DB_PREFIX . "customer` `c` ON (`c`.`customer_id` = `nl`.`customer_id`) WHERE `nl`.`channel` = '" . $this->db->escape($channel) . "' AND `nl`.`purpose` = 'customer' AND `nl`.`status` = '1' AND `nl`.`chat_id` != '' AND `c`.`newsletter` = '1' AND `c`.`status` = '1'";
		}

		return array_column($this->db->query($sql)->rows, 'chat_id');
	}

	/**
	 * Install
	 *
	 * @return void
	 */
	public function install(): void {
		$this->load->model('setting/extension');

		if (!$this->model_setting_extension->getInstallByCode('marketing_broadcast')) {
			$this->model_setting_extension->addInstall([
				'extension_id'          => 0,
				'extension_download_id' => 0,
				'name'                  => 'Marketing Broadcast',
				'description'           => 'Sends a one-off promotional message to a customer segment across SMS, WhatsApp, Telegram and Bale.',
				'code'                  => 'marketing_broadcast',
				'version'               => '1.0',
				'author'                => '',
				'link'                  => ''
			]);
		}
	}

	/**
	 * Uninstall
	 *
	 * @return void
	 */
	public function uninstall(): void {
	}
}
