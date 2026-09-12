-- MDcart: rename the English language's `code` from en-gb to us
-- Run this ONLY AFTER the matching code patch (folder rename + code
-- references) has been applied to this site via System Update.
-- Safe to run more than once.

UPDATE `oc_language`
SET `code` = 'us', `locale` = 'en-us,en'
WHERE `code` = 'en-gb';

UPDATE `oc_setting`
SET `value` = 'us'
WHERE `key` IN ('config_language_catalog', 'config_language_admin')
  AND `value` = 'en-gb';

UPDATE `oc_extension_path`
SET `path` = REPLACE(`path`, 'opencart/catalog/language/en-gb', 'opencart/catalog/language/us')
WHERE `path` LIKE 'opencart/catalog/language/en-gb%';

UPDATE `oc_extension_path`
SET `path` = REPLACE(`path`, 'opencart/admin/language/en-gb', 'opencart/admin/language/us')
WHERE `path` LIKE 'opencart/admin/language/en-gb%';
