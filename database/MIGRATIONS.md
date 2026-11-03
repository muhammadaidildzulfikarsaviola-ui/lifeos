# Migration System

Run from project root:
- php bin/lifeos migrate
- php bin/lifeos migrate:status
- php bin/lifeos migrate:retry <migration_name>

Migration filenames use NNNN_lowercase_name.php and return an array with a callable up(PDO $pdo). The runner records checksums and status, takes a MariaDB advisory lock, and refuses changed applied migrations.

MariaDB DDL can implicitly commit. A failed DDL migration may leave partial schema. Inspect and repair that state before explicitly retrying it. Never edit an applied migration; create a new migration instead. The first migration creates only profile and settings foundations; module schema follows after MariaDB 10.4 reconciliation.
