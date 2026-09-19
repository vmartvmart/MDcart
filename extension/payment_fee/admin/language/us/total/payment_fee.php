<?php
// Heading
$_['heading_title']          = 'Payment Method Fee/Discount';

// Text
$_['text_home']              = 'Home';
$_['text_extension']         = 'Extensions';
$_['text_success']           = 'Success: You have modified payment method fee/discount total!';
$_['text_edit']               = 'Edit Payment Method Fee/Discount';
$_['text_payment_fee_help']  = 'Enter a percentage for any payment method below. A positive number adds a surcharge to the order total when the customer pays with it (e.g. 10 adds 10%); a negative number gives a discount (e.g. -5 takes off 5%). Leave a field blank or at 0 to leave that payment method unaffected. The percentage is calculated on the order total as it stands at that point (after any coupon, reward or shipping), and updates live as the customer switches payment method at checkout.';

// Column
$_['column_payment_method']  = 'Payment Method';
$_['column_rate']            = 'Percentage';

// Entry
$_['entry_rate']              = 'Percentage';
$_['entry_status']            = 'Status';
$_['entry_sort_order']       = 'Sort Order';

// Error
$_['error_permission']       = 'Warning: You do not have permission to modify payment method fee/discount total!';
$_['error_rate']              = 'Percentage must be a number!';
$_['error_rate_min']         = 'Percentage cannot be -100 or lower (that would make the order total zero or negative)!';

// Text (list)
$_['text_no_results']        = 'There are no payment methods installed yet.';
