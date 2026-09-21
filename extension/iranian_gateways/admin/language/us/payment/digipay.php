<?php
// Heading
$_['heading_title']              = 'DigiPay';

// Text
$_['text_extension']             = 'Extensions';
$_['text_success']               = 'Success: You have modified DigiPay details!';
$_['text_edit']                  = 'Edit DigiPay';
$_['text_all_zones']             = 'All Zones';
$_['text_none']                  = '--- None ---';
$_['text_callback_url']          = 'Callback URL';

// Entry
$_['entry_client_id']            = 'Client ID';
$_['entry_client_secret']        = 'Client Secret';
$_['entry_username']             = 'Username';
$_['entry_password']             = 'Password';
$_['entry_sandbox']              = 'Sandbox Mode';
$_['entry_order_status']         = 'Order Status (Payment Successful)';
$_['entry_deliver_order_status'] = 'Order Status (Delivered - Credit/BNPL only)';
$_['entry_geo_zone']             = 'Geo Zone';
$_['entry_status']               = 'Status';
$_['entry_sort_order']           = 'Sort Order';

// Help
$_['help_sandbox']               = 'Use DigiPay\'s staging/test environment (uat.mydigipay.info) - no real money moves. Turn this off before going live with real customers.';
$_['help_callback_url']          = 'If DigiPay\'s panel asks for a callback/redirect URL, use this one.';
$_['help_deliver_order_status']  = 'Only applies to purchases paid with DigiPay\'s credit or buy-now-pay-later (BNPL) options - DigiPay requires the merchant to separately confirm the goods were delivered before that purchase is finalized. When an order paid this way is changed to the status selected here, DigiPay is automatically notified. Leave as "None" to never send this automatically.';

// Error
$_['error_permission']           = 'Warning: You do not have permission to modify DigiPay settings!';
$_['error_client_id']            = 'Client ID is required!';
$_['error_client_secret']        = 'Client Secret is required!';
$_['error_username']             = 'Username is required!';
$_['error_password']             = 'Password is required!';
