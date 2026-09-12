-- Grants the "Administrator" user group (user_group_id = 1) access to the
-- new Design > Colors page (route design/color), added by the Design >
-- Colors feature. Fresh installs already get this via install_starter.sql;
-- this migration is for the already-existing live database, whose
-- oc_user_group.permission JSON blob was written before this route existed.
--
-- Safe to run more than once: each UPDATE is guarded by JSON_SEARCH so it
-- only appends "design/color" the first time. (JSON_CONTAINS was tried
-- first but rejected: on this codebase's stored permission blobs -- whose
-- string elements use JSON's escaped-slash form "design\/color", the same
-- form PHP's json_encode() produces by default -- JSON_CONTAINS compares
-- serialized text rather than decoded values, so a plain "design/color"
-- candidate never matches. JSON_SEARCH decodes correctly either way.)

UPDATE `oc_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'design/color')
WHERE `user_group_id` = '1'
  AND JSON_SEARCH(`permission`, 'one', 'design/color', NULL, '$.access') IS NULL;

UPDATE `oc_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'design/color')
WHERE `user_group_id` = '1'
  AND JSON_SEARCH(`permission`, 'one', 'design/color', NULL, '$.modify') IS NULL;
