<?php
// Heading
$_['heading_title'] = 'Exchange Rate';

// Text
$_['text_home'] = 'Home';
$_['text_not_installed'] = 'The AED/IRR/IRT currencies are not set up yet. Add them under System > Localisation > Currencies first (codes AED, IRR, and IRT).';
$_['text_current'] = 'Current Rate';
$_['text_rial'] = 'Rial';
$_['text_toman'] = 'Toman';
$_['text_last_updated'] = 'Last Updated';
$_['text_never'] = 'Never';
$_['text_how_it_works'] = 'How This Works';
$_['text_explanation_1'] = 'The UAE Dirham (AED) is fixed to the US Dollar at a stable, official peg, so it almost never needs updating on its own. What actually moves day to day is the Rial - so this page only ever asks for one real market number: how many Rial does 1 AED currently trade for.';
$_['text_explanation_2'] = 'From that one number, the store works out the current Rial/Dollar rate itself (using the fixed %s AED-per-Dollar peg), and updates both the Rial (IRR) and Toman (IRT = Rial / 10) currencies to match - so prices shown to customers in Toman always track today\'s real market, even though only the Dirham rate is ever fetched or entered.';
$_['text_explanation_3'] = 'Automatic twice-daily fetching is not scheduled yet, since this store is still on a local test server without host-level cron access. Once it moves to real hosting, a cron job calling this page\'s fetch action on a schedule will turn it on - for now, use the Update button whenever a fresh rate is needed.';
$_['text_success'] = 'Success: exchange rate updated!';
$_['text_success_fetch'] = 'Success: fetched the current rate from tgju.org and updated it!';

// Entry
$_['entry_rial_per_aed'] = '1 AED equals';
$_['entry_toman_equivalent'] = 'Toman equivalent';
$_['help_rial_per_aed'] = 'Enter today\'s market rate in Rial (not Toman) - e.g. tgju.org shows the AED price in Rial. Toman is calculated automatically as Rial / 10.';

// Button
$_['button_save'] = 'Save Manually';
$_['button_fetch'] = 'Update from tgju.org';

// Error
$_['error_permission'] = 'Warning: You do not have permission to modify the exchange rate!';
$_['error_rate'] = 'Warning: Please enter a rate greater than zero!';
$_['error_fetch'] = 'Warning: Could not fetch the current rate from tgju.org right now (the site may be unreachable, or its page layout may have changed). Please enter the rate manually instead.';
$_['error_unknown'] = 'Something went wrong. Please try again.';
