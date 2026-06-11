-- Create the test database used by PHPUnit and grant the application
-- user access to it (plus any future ParaTest-suffixed siblings).
--
-- Symfony's `when@test` doctrine config appends `_test` to the configured
-- database name, and the MariaDB image only creates MYSQL_DATABASE and
-- grants MYSQL_USER on it. Without this file the test suite fails with
-- either "Unknown database 'db_test'" (database absent) or "Access
-- denied for user 'db'@'%'" (database present but no grant).
--
-- The `\_test` escape on the GRANT pattern matches `db_test`,
-- `db_test_paratest_1`, etc. — but not unrelated names like `dbXtest`.
--
-- This file is mounted into `/docker-entrypoint-initdb.d/` and runs
-- once when the container's data volume is first initialised. The
-- `task db-prepare-test` target re-applies the same logic for local
-- devs whose volume predates the mount.

CREATE DATABASE IF NOT EXISTS `db_test`;
GRANT ALL PRIVILEGES ON `db\_test%`.* TO `db`@`%`;
FLUSH PRIVILEGES;
