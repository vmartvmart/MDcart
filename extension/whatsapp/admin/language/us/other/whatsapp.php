<?php
// Heading
$_['heading_title']             = 'WhatsApp Cloud API Notifications';
$_['whatsapp_heading_title']    = 'WhatsApp Cloud API Notifications';

// Text
$_['text_extension']            = 'Extensions';
$_['text_home']                 = 'Dashboard';
$_['text_edit']                 = 'Edit WhatsApp Notifications';
$_['text_success']               = 'Success: You have modified WhatsApp settings!';
$_['text_general']              = 'General';
$_['text_templates']            = 'Message Templates';
$_['text_notifications']        = 'Notifications';
$_['text_templates_notice']     = 'WhatsApp only allows proactive messages (order updates, alerts) using a message template pre-approved in Meta Business Manager. Create your templates there first, then enter their exact names below. Each template must have a body with 2 text variables: {{1}} = order number, {{2}} = amount or status text.';

// Entry
$_['entry_status']              = 'Status';
$_['entry_phone_number_id']     = 'Phone Number ID';
$_['entry_access_token']        = 'Access Token';
$_['entry_language_code']       = 'Template Language Code';
$_['entry_template_order_add']  = 'New Order Template Name';
$_['entry_template_order_status'] = 'Order Status Template Name';
$_['entry_order_add_status']    = 'Message to customer on new order';
$_['entry_order_status_status'] = 'Message to customer on order status change';
$_['entry_admin_status']        = 'Message to admin on new order';
$_['entry_admin_telephone']     = 'Admin WhatsApp Number';

// Help
$_['help_phone_number_id']      = 'From Meta for Developers > your app > WhatsApp > API Setup > Phone Number ID.';
$_['help_access_token']         = 'A permanent access token generated for a System User in Meta Business Manager (temporary tokens from the Cloud API test panel expire after 24 hours).';
$_['help_language_code']        = 'Must match the language selected for the template in Meta Business Manager, e.g. fa or en_US.';
$_['help_admin_telephone']      = 'WhatsApp number (Iranian format) to receive a message whenever a new order is placed.';

// Error
$_['error_permission']          = 'Warning: You do not have permission to modify WhatsApp settings!';
$_['error_warning']             = 'Warning: Please check the form carefully for errors!';
$_['error_phone_number_id']     = 'Phone Number ID is required!';
$_['error_access_token']        = 'Access Token is required!';
