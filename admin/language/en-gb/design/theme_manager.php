<?php
// Heading
$_['heading_title'] = 'Theme Manager';

// Text
$_['text_home'] = 'Home';
$_['text_help'] = 'Only the Administrator account can add or remove storefront themes here. A theme package is a .zip containing exactly 3 files sharing one name: {name}_header.twig, {name}_home.twig, and theme-{name}.css (an optional {name}.json with a "label"/"label_fa" field sets its display name). Once added, it appears immediately in Design > Storefront Theme.';
$_['text_upload'] = 'Upload Theme Package (.zip)';
$_['text_choose_file'] = 'Choose file...';
$_['text_installed'] = 'Installed Themes';
$_['text_active'] = 'Active';
$_['text_loading'] = 'Working...';
$_['text_upload_success'] = 'Theme installed! It now appears in the theme switcher.';
$_['text_delete_success'] = 'Theme removed.';
$_['text_confirm_delete'] = 'Remove this theme? This cannot be undone.';

// Button
$_['button_upload'] = 'Upload';
$_['button_delete'] = 'Delete';
$_['button_back'] = 'Back to Theme Switcher';

// Error
$_['error_permission'] = 'Warning: Only the Administrator account can manage themes.';
$_['error_upload'] = 'Please choose a .zip file to upload.';
$_['error_not_zip'] = 'The uploaded file is not a valid .zip archive.';
$_['error_too_large'] = 'The file is too large (5MB maximum).';
$_['error_package_invalid'] = 'Invalid theme package: it must contain exactly {name}_header.twig, {name}_home.twig, and theme-{name}.css, all sharing the same {name}.';
$_['error_theme_exists'] = 'A theme with this name already exists. Remove it first if you want to replace it.';
$_['error_invalid_theme'] = 'Invalid theme.';
$_['error_theme_active'] = 'Cannot remove the currently active theme. Switch to another theme first.';
