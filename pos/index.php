<?php
/**
 * Clean entry point: https://yoursite.com/pos redirects straight into the
 * admin panel's POS screen if you're already logged in there, or to the
 * admin login screen first otherwise. Works with no server/.htaccess
 * configuration at all — it just forwards the request into the admin
 * front controller with a fixed route (see
 * <admin folder>/controller/common/quicklink.php for the actual logic).
 *
 * The admin folder is commonly renamed for security (see the "بروزرسانی"
 * page's Advanced Settings in the admin panel). This file finds it via the
 * ".admin_dir" marker file at the site root, which is kept in sync
 * automatically — written at install time (install.php) and rewritten any
 * time the admin folder name is updated on the update/settings page
 * (admin/model/tool/system_update.php). Falls back to "admin" if the
 * marker is missing, matching a fresh default install.
 */

$root = dirname(__DIR__) . '/';
$marker = $root . '.admin_dir';

$admin_dir = is_file($marker) ? trim((string)file_get_contents($marker)) : '';
if ($admin_dir === '') {
	$admin_dir = 'admin';
}

$admin_path = $root . $admin_dir . '/';

if (!is_file($admin_path . 'index.php')) {
	http_response_code(500);
	exit('Admin folder not found.');
}

$_GET['route'] = 'common/quicklink';
$_GET['target'] = 'pos';

chdir($admin_path);
require($admin_path . 'index.php');
