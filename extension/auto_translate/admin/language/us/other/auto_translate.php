<?php
// Heading
$_['heading_title']                = 'Auto Translate (Persian / English)';

// Text
$_['text_extension']               = 'Extensions';
$_['text_home']                    = 'Dashboard';
$_['text_edit']                    = 'Edit Auto Translate';
$_['text_success']                 = 'Success: You have modified auto-translate settings!';
$_['text_general']                 = 'General';
$_['text_scope']                   = 'What Gets Translated';
$_['text_security']                = 'API Key Security';
$_['text_key_set']                 = 'A key is currently stored:';
$_['text_key_not_set']             = 'No key is stored yet.';

// Entry
$_['entry_status']                 = 'Status';
$_['entry_api_key']                = 'Anthropic (Claude) API Key';
$_['entry_model']                  = 'Model';
$_['entry_products_status']        = 'Auto-Translate Products';
$_['entry_categories_status']      = 'Auto-Translate Categories';

// Help
$_['help_api_key']                 = 'Create your own key at console.anthropic.com. To change it, type a new value here; leaving it blank keeps the currently stored key unchanged, which is never shown in full on this page again after saving.';
$_['help_model']                   = 'Leave blank to use the default, fast and inexpensive model (Claude Haiku). Only change this if you specifically need a different model.';
$_['help_products_status']         = 'When a product\'s name, description, tags or SEO fields are entered in only one of the two languages, the empty fields in the other language are automatically filled in from it (once, on save). A field that already has content is never overwritten.';
$_['help_categories_status']       = 'Same behaviour as products, for category names, descriptions and SEO fields.';
$_['help_security']                = 'The API key is only ever used server-side, only when a product/category is saved in the admin panel - no storefront-facing route can reach it, and this page never displays the full key in its HTML. As extra protection against a leaked key, set a monthly spending limit on this key in your own Anthropic console (console.anthropic.com); that way even if the key were ever exposed, the worst-case cost is capped at whatever limit you choose.';

// Error
$_['error_permission']             = 'Warning: You do not have permission to modify auto-translate settings!';
$_['error_warning']                = 'Warning: Please check the form carefully for errors!';
$_['error_api_key']                = 'API Key is required when status is enabled!';
