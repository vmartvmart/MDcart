<?php
namespace MDcart\Catalog\Model\User;
/**
 * Class User
 *
 * Read-only lookup of admin users, used by the order-notification event
 * controllers (email/Telegram/Bale/WhatsApp/IPPanel) to resolve which
 * staff members should be notified about a new order, based on the
 * shared "which roles get notified" setting (config_notify_admin_group_ids)
 * configured under Settings > General.
 *
 * Can be loaded using $this->load->model('user/user');
 *
 * @package MDcart\Catalog\Model\User
 */
class User extends \MDcart\System\Engine\Model {
	/**
	 * Get Notify Users
	 *
	 * Returns active admin users belonging to any of the given user
	 * groups, with their own order-notification contact fields
	 * (email, notify_mobile, notify_telegram_chat_id, notify_bale_chat_id) -
	 * each set individually by that user on their own Profile page.
	 *
	 * @param array<int, mixed> $group_ids
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @example
	 *
	 * $this->load->model('user/user');
	 *
	 * $notify_users = $this->model_user_user->getNotifyUsers($group_ids);
	 */
	public function getNotifyUsers(array $group_ids): array {
		$group_ids = array_values(array_unique(array_filter(array_map('intval', $group_ids))));

		if (!$group_ids) {
			return [];
		}

		$query = $this->db->query("SELECT `user_id`, `email`, `notify_mobile`, `notify_telegram_chat_id`, `notify_bale_chat_id` FROM `" . DB_PREFIX . "user` WHERE `status` = '1' AND `user_group_id` IN (" . implode(',', $group_ids) . ")");

		return $query->rows;
	}
}
