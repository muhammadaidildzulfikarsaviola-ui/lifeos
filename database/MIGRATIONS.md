# LIFEOS Migration System

Run from the project root:
- D:\\XAMPP\\php\\php.exe bin\\lifeos migrate
- D:\\XAMPP\\php\\php.exe bin\\lifeos migrate:status
- D:\\XAMPP\\php\\php.exe bin\\lifeos migrate:retry <migration_name>

Migration files use NNNN_lowercase_name.php and return an array containing a callable up(PDO $pdo). The runner creates schema_migrations, takes a MariaDB advisory lock, records SHA-256 checksums and status, and refuses changed migrations after they have been applied.

MariaDB DDL can implicitly commit. A failed DDL migration may leave partial schema. Inspect and repair that state before explicitly retrying it. Never edit an applied migration; add a new migration instead. The first migration creates the local profile and settings foundation. Domain schemas follow after the full ERD is reconciled for MariaDB 10.4.
