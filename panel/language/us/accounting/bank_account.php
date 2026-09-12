<?php
// Heading
$_['heading_title'] = 'Bank / Cash Accounts';

// Text
$_['text_home'] = 'Home';
$_['text_add'] = 'Add Bank/Cash Account';
$_['text_edit'] = 'Edit Bank/Cash Account';
$_['text_list'] = 'Bank / Cash Accounts';
$_['text_success'] = 'Success: You have modified bank/cash accounts!';
$_['text_confirm'] = 'Are you sure?';
$_['text_role_pos_cash'] = 'Default: POS Cash Payments';
$_['text_role_pos_card'] = 'Default: POS Card Payments';
$_['text_role_website_sales'] = 'Default: Website Sales';
$_['text_role_none'] = '-- None --';

// Column
$_['column_name'] = 'Name';
$_['column_bank_name'] = 'Bank';
$_['column_account_number'] = 'Account Number';
$_['column_role'] = 'Auto-Posting Role';
$_['column_balance'] = 'Current Balance';
$_['column_status'] = 'Status';

// Entry
$_['entry_name'] = 'Name';
$_['entry_bank_name'] = 'Bank Name';
$_['entry_account_number'] = 'Account Number';
$_['entry_iban'] = 'IBAN / Sheba';
$_['entry_opening_balance'] = 'Opening Balance';
$_['entry_currency'] = 'Currency';
$_['entry_default_role'] = 'Auto-Posting Role';
$_['entry_status'] = 'Status';
$_['help_default_role'] = 'When a POS or website sale is completed, its receipt is automatically posted to whichever account holds the matching role. Only one account can hold each role - picking one here removes it from any other account.';

// Button
$_['button_add'] = 'Add New';
$_['button_delete'] = 'Delete';
$_['button_save'] = 'Save';
$_['button_back'] = 'Back';

// Error
$_['error_permission'] = 'Warning: You do not have permission to modify Bank/Cash Accounts!';
$_['error_name'] = 'Name must be between 1 and 128 characters!';
$_['error_has_journal_lines'] = 'Warning: This account already has journal entries posted against it and cannot be deleted!';
