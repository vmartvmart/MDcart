<?php
// Heading
$_['heading_title']              = 'Update';

// Text
$_['text_success']                = 'Success: You have modified update settings!';
$_['text_edit']                   = 'Update';
$_['text_settings']                = 'Repository Settings';
$_['text_advanced_settings']       = 'Advanced Settings';
$_['text_status']                  = 'Version Status';
$_['text_checking']                = 'Checking for updates...';
$_['text_up_to_date']              = 'Your installation is up to date.';
$_['text_update_available']        = 'A new version is available.';
$_['text_no_baseline']             = 'No baseline version recorded yet. If this install already matches the repository, use the button below to record the latest commit as your current version without downloading.';
$_['text_current_commit']          = 'Current version';
$_['text_applied_date']            = 'Applied on this site';
$_['text_latest_commit']           = 'Latest version';
$_['text_commit_message']          = 'Change description';
$_['text_commit_date']             = 'Date';
$_['text_commit_author']           = 'Author';
$_['text_changelog']               = 'What this update changes';
$_['text_applying']                = 'Taking a backup and applying the update... this can take a few minutes. Do not close this page.';
$_['text_apply_success']           = 'Update applied successfully. New version:';
$_['text_baseline_success']        = 'Baseline version recorded successfully.';
$_['text_confirm_apply']           = 'Are you sure? A full backup of the files and database will be taken first, and you can restore from it if needed. This can take a few minutes.';
$_['text_not_configured']          = 'Enter and save the repository and access token first.';
$_['text_never']                   = 'Never';
$_['text_help_repo']               = 'Format: owner/repo, e.g. vmartvmart/MDcart';
$_['text_help_branch']             = 'The branch the site should update from (default: main)';
$_['text_help_token']              = 'A Personal Access Token with read access to the private repository (scope: repo)';
$_['text_help_admin_dir']          = 'If you renamed the admin folder (e.g. to panel), enter it here so future updates land in that same folder instead of recreating an empty admin folder.';
$_['text_help_scope']              = 'This tool only adds or replaces files; files removed in the new version are not deleted from the site. Settings files and folders such as cache/logs/session/upload/backup are never touched.';
$_['text_backups']                 = 'Backups & Restore';
$_['text_help_backups']            = 'A full backup of the files and database is taken automatically before every update. The last 5 backups are kept, older ones are removed automatically. Restoring reverts both files and database to that moment; note that files added after that backup are not deleted.';
$_['text_no_backups']              = 'No backups yet.';
$_['text_backup_created_prefix']   = 'An automatic backup was taken before this update:';
$_['text_confirm_restore']         = 'Are you sure? The site\'s files and database will be reverted to the state of this backup. This cannot be undone except from another backup.';
$_['text_restoring']               = 'Restoring from backup... do not close this page.';
$_['text_restore_success']         = 'Restore completed successfully.';

// Entry
$_['entry_repo']                   = 'Repository';
$_['entry_branch']                 = 'Branch';
$_['entry_token']                  = 'Personal Access Token';
$_['entry_admin_dir']              = 'Admin Folder Name';

// Column
$_['column_backup_date']           = 'Date';
$_['column_backup_reason']         = 'Reason';
$_['column_backup_size']           = 'Size';
$_['column_backup_action']         = 'Action';

// Button
$_['button_save']                  = 'Save Settings';
$_['button_check']                 = 'Check for Update';
$_['button_apply']                 = 'Get &amp; Apply Update';
$_['button_baseline']              = 'Record Current as Baseline (no download)';
$_['button_restore']               = 'Restore';

// Error
$_['error_permission']             = 'Warning: You do not have permission to access this!';
$_['error_repo']                   = 'Repository is required!';
$_['error_token']                  = 'Access token is required!';
$_['error_not_configured']         = 'Repository or token is not configured!';
$_['error_connection']             = 'Could not connect: ';
$_['error_api']                    = 'Error: ';
$_['error_zip_extension']          = 'The PHP ZipArchive extension is not enabled on this server; update/backup cannot proceed.';
$_['error_download']               = 'Failed to download the update package.';
$_['error_extract']                = 'Failed to extract the file.';
$_['error_write']                  = 'Failed to write some files on the server; check folder permissions.';
$_['error_backup_failed']          = 'The pre-update backup failed, so for safety the update was not applied. Check disk space and write permissions.';
$_['error_backup_not_found']       = 'That backup could not be found.';
