-- MDcart: use each language's own native name instead of an English gloss
-- ("Persian" -> "فارسی"), so the language switcher shows a name that
-- doesn't need translating no matter which language is currently active.
-- Safe to run more than once.

UPDATE `oc_language`
SET `name` = 'فارسی'
WHERE `code` = 'fa' AND `name` = 'Persian';
