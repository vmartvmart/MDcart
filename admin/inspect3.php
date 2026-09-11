<?php
register_shutdown_function(function () { @unlink(__FILE__); });
require_once __DIR__ . '/config.php';
$conn = mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if (!$conn) { die("Connect failed: " . mysqli_connect_error()); }

$r = mysqli_query($conn, "SELECT user_id, username, password FROM " . DB_PREFIX . "user WHERE user_id = 1");
$row = mysqli_fetch_assoc($r);
echo "Current password hash: " . $row['password'] . "\n";

$temp_password = 'Task13Test!2026';
$new_hash = password_hash($temp_password, PASSWORD_BCRYPT, ['cost' => 10]);
mysqli_query($conn, "UPDATE " . DB_PREFIX . "user SET password = '" . mysqli_real_escape_string($conn, $new_hash) . "' WHERE user_id = 1");
echo "Password temporarily set to: $temp_password\n";
echo "New hash: $new_hash\n";
