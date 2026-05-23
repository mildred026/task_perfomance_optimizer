-- Railway/MySQL fix for ERROR 1064 near 'groups'.
-- Run this once in Railway's MySQL query console.
--
-- The old table name `groups` conflicts with the MySQL reserved keyword GROUPS.
-- The app code now uses project_groups instead.

RENAME TABLE `groups` TO project_groups;
