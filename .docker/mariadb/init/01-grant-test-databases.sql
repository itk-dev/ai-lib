-- Grant the application user access to the per-environment test databases
-- Symfony's `when@test` doctrine config appends `_test` (plus an optional
-- ParaTest suffix) to the configured database name. The MariaDB image only
-- grants MYSQL_USER on MYSQL_DATABASE by default, so without this the
-- test suite fails with "Access denied for user 'db'@'%' to database
-- 'db_test'". The `\_test` escapes the SQL wildcard so the grant matches
-- `db_test`, `db_test_paratest_1`, etc. — but not unrelated names like
-- `dbXtest`.
--
-- This file is mounted into `/docker-entrypoint-initdb.d/` and runs once
-- when the container's data volume is first initialised.

GRANT ALL PRIVILEGES ON `db\_test%`.* TO `db`@`%`;
FLUSH PRIVILEGES;
