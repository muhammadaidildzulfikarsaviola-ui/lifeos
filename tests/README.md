# LIFEOS Core Test Harness

No third-party test dependency is required for the current foundation tests.

Run from the repository root:

```powershell
D:\XAMPP\php\php.exe tests\run.php
```

The harness covers request parsing and normalization, JSON validation, response envelopes, route matching, 404/405 handling, CSRF behavior, and field validation.

Database integration tests are intentionally separate and must run against a dedicated disposable MariaDB test database. Never point destructive integration tests at the personal LIFEOS database.
