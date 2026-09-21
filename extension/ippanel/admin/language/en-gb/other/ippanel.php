<?php
// Heading
$_['heading_title']          = 'IPPanel SMS';
$_['ippanel_heading_title']  = 'IPPanel SMS';

// Text
$_['text_extension']         = 'Extensions';
$_['text_home']              = 'Dashboard';
$_['text_edit']              = 'Edit IPPanel SMS';
$_['text_success']           = 'Success: You have modified IPPanel SMS settings!';
$_['text_general']           = 'General';
$_['text_notifications']     = 'Notifications';
$_['text_otp']                = 'Mobile Verification & OTP Login';

// Entry
$_['entry_status']           = 'Status';
$_['entry_api_key']          = 'API Key';
$_['entry_sender']           = 'Sender Number';
$_['entry_order_add_status'] = 'SMS to customer on new order';
$_['entry_order_status_status'] = 'SMS to customer on order status change';
$_['entry_admin_status']     = 'SMS to admin on new order';
$_['entry_admin_telephone']  = 'Admin Mobile Number';
$_['entry_otp_status']        = 'Enable mobile verification & OTP login';

// Help
$_['help_api_key']           = 'Generate a permanent API key from User Panel > Developers > Access Keys on ippanel.com.';
$_['help_sender']            = 'The sender/originator number assigned to your IPPanel account (e.g. +983000505).';
$_['help_admin_telephone']   = 'Mobile number (Iranian format) to receive an SMS whenever a new order is placed.';
$_['help_otp_status']         = 'When enabled, new customers must verify their mobile number with an SMS code during registration, and a "login with mobile number + OTP" option is added to the login page. Requires Status to be enabled and the API Key / Sender Number above to be set.';

// Error
$_['error_permission']       = 'Warning: You do not have permission to modify IPPanel SMS!';
$_['error_warning']          = 'Warning: Please check the form carefully for errors!';
$_['error_api_key']          = 'API Key is required when status is enabled!';
$_['error_sender']           = 'Sender Number is required when status is enabled!';
$_['error_otp_status']        = 'You must enable IPPanel Status before enabling mobile verification!';
