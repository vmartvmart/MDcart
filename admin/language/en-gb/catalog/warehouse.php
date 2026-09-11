<?php
// Heading
$_['heading_title'] = 'Warehouses';

// Text
$_['text_home'] = 'Home';
$_['text_list'] = 'Warehouse List';
$_['text_add'] = 'Add Warehouse';
$_['text_edit'] = 'Edit Warehouse';
$_['text_form'] = 'Warehouse Form';
$_['text_success'] = 'Success: You have modified warehouses!';
$_['text_no_results'] = 'No results!';
$_['text_confirm'] = 'Are you sure?';
$_['text_yes'] = 'Yes';
$_['text_enabled'] = 'Enabled';
$_['text_disabled'] = 'Disabled';
$_['text_help'] = 'Exactly one warehouse should be marked "Selling Warehouse" - only that warehouse\'s stock and landed cost are shown to online customers. Every other warehouse is only used by Warehouse Transfers and the POS.';

// Column
$_['column_name'] = 'Name';
$_['column_currency'] = 'Currency';
$_['column_selling_warehouse'] = 'Selling Warehouse';
$_['column_status'] = 'Status';
$_['column_action'] = 'Action';

// Entry
$_['entry_name'] = 'Warehouse Name';
$_['entry_address'] = 'Address';
$_['entry_currency'] = 'Purchase Currency';
$_['entry_selling_warehouse'] = 'Selling Warehouse';
$_['entry_status'] = 'Status';
$_['entry_sort_order'] = 'Sort Order';

// Help
$_['help_currency'] = 'The currency this warehouse buys stock in (e.g. AED, USD). Conversion to the store\'s selling currency uses the exchange rate set under System > Localisation > Currency.';
$_['help_selling_warehouse'] = 'Turning this on makes this warehouse the one and only source for the online storefront\'s stock and price. It will automatically be turned off for every other warehouse.';

// Button
$_['button_add'] = 'Add New';
$_['button_edit'] = 'Edit';
$_['button_delete'] = 'Delete';
$_['button_save'] = 'Save';
$_['button_back'] = 'Back';

// Error
$_['error_permission'] = 'Warning: You do not have permission to modify warehouses!';
$_['error_name'] = 'Warehouse Name must be between 1 and 128 characters!';
$_['error_currency'] = 'Please choose a purchase currency!';
$_['error_selling_warehouse'] = 'Warning: You cannot delete the current selling warehouse! Mark another warehouse as the selling warehouse first.';
