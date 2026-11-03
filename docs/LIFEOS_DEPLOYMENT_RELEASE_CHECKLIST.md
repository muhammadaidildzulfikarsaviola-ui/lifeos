# LIFEOS Deployment & Release Checklist

**Status:** Draft for implementation  
**Version:** 1.0  
**Target:** Windows 10/11 + XAMPP local installation, Apache, PHP, MySQL/MariaDB-compatible InnoDB

## 1. Purpose

This checklist defines how a tested LIFEOS build is prepared, installed, upgraded, validated, and rolled back. V1 is a local application; this is not a public multi-tenant hosting deployment guide.

## 2. Supported baseline

Before release, record exact tested versions rather than relying on “latest”:
- Windows version and architecture.
- XAMPP version.
- PHP version and enabled extensions.
- Actual database engine and version (MySQL or MariaDB).
- Apache version.
- Browser versions tested (Chrome, Edge, Brave).
- Composer version, if used.
- Node/build tooling versions, if any frontend assets require compilation.

Do not assume MySQL 8 and MariaDB are interchangeable. Run migrations and integration tests against the actual bundled engine.

## 3. Pre-release checklist

### Product and code
- [ ] Release scope matches approved PRD.
- [ ] All nine v1 modules are present and usable.
- [ ] No placeholder buttons or dead navigation.
- [ ] Empty, loading, success, validation and error states exist.
- [ ] No critical/high defects remain open.
- [ ] Cross-module relationships are verified.
- [ ] No test/debug data appears in production seed.
- [ ] No secrets or local machine paths are committed.
- [ ] Composer autoload and dependency lock are current, if Composer is used.
- [ ] Frontend assets are versioned and cache-busted.

### Database
- [ ] Fresh-install migrations pass from an empty database.
- [ ] Upgrade migrations pass from the previous supported release.
- [ ] Foreign keys and indexes are present.
- [ ] InnoDB and utf8mb4 verified.
- [ ] Finance ledger invariants pass.
- [ ] Recurring occurrence uniqueness/idempotency passes.
- [ ] Profile ownership constraints are validated in services.
- [ ] Backup and restore drill passes.
- [ ] Migration rollback strategy is documented; destructive migrations require a backup.

### Security and data
- [ ] CSRF protection enabled for state-changing requests.
- [ ] PDO prepared statements used.
- [ ] Output escaping verified.
- [ ] Upload validation and storage outside public root verified.
- [ ] File download ownership checks verified.
- [ ] Error responses do not expose stack traces, SQL, secrets or absolute paths.
- [ ] Backup folder is not web-accessible.
- [ ] Default config contains no production credentials.
- [ ] App is not exposed to LAN/WAN unless explicitly configured and secured.

### Performance and compatibility
- [ ] Dashboard load tested with realistic data volume.
- [ ] Search and list pagination tested.
- [ ] Calendar navigation and event rendering tested.
- [ ] Reports tested across large date ranges.
- [ ] No N+1 query hotspots in common views.
- [ ] Mobile/tablet/desktop layouts checked.
- [ ] Browser console has no critical errors.

## 4. Local installation procedure

1. Install a supported XAMPP build and record its PHP/database versions.
2. Place the LIFEOS project in a stable directory outside temporary folders.
3. Configure Apache VirtualHost so the document root points to `lifeos/public`.
4. Keep `app/`, `config/`, `database/`, `storage/`, and vendor dependencies outside public root.
5. Create a dedicated database and least-privilege database user where supported.
6. Copy the example environment/config file and set local DB credentials, timezone, storage path and app URL.
7. Install Composer dependencies using the lock file, if the project uses Composer.
8. Run database migrations and required seeders.
9. Create the initial local profile and default preferences.
10. Verify storage directories are writable by the Apache/PHP process but not publicly served.
11. Open the app and run the installation health check.
12. Perform a test backup and verify it.

Avoid putting the project under a public shared folder or exposing Apache to the network by default.

## 5. Configuration requirements

Configuration should include:
- environment (`development`, `test`, `production`);
- base URL;
- DB host, port, database, username, password;
- DB engine compatibility settings;
- application timezone (`Asia/Jakarta`);
- storage and backup paths;
- upload maximum size (50 MB);
- logging level and path;
- scheduler/CLI PHP executable path;
- feature flags only where they are genuinely needed.

Secrets belong in local environment/config files excluded from version control. Provide a safe example file with placeholders.

## 6. Upgrade procedure

1. Read release notes and compatibility notes.
2. Close LIFEOS UI and stop scheduled jobs.
3. Create and verify a full backup.
4. Check available disk space.
5. Replace application code using the documented release method; preserve user data and local config.
6. Run migrations in order.
7. Run post-migration integrity checks.
8. Clear only safe application caches if present.
9. Restart Apache if required.
10. Open LIFEOS and run smoke tests.
11. Re-enable scheduled jobs.
12. Record installed version and migration version.

Do not overwrite `storage/`, user uploads, backups, or local configuration during code updates.

## 7. Rollback procedure

Rollback must distinguish code rollback from database rollback:
- If only code changed and schema remains backward-compatible, restore the previous code version.
- If schema changed, use the verified pre-upgrade backup unless a tested down-migration exists.
- Never manually delete migration records to force an older version to run.
- Restore attachments and database from the same backup package to avoid mismatch.
- Run smoke tests after rollback and document the reason.

## 8. Release package

A release package should contain:
- application source or packaged release files;
- dependency lock file;
- database migration files;
- safe example configuration;
- installation and upgrade guide;
- release notes;
- version identifier;
- checksum file.

Do not include local database dumps, real user data, private credentials, debug logs, or machine-specific paths.

## 9. Smoke test after install/upgrade

- [ ] Dashboard loads.
- [ ] Create/edit/delete a test note and restore it from trash.
- [ ] Create an income and expense; wallet balance matches ledger.
- [ ] Create a transfer; total income/expense reports do not count it.
- [ ] Create and complete a task; progress updates.
- [ ] Create a habit and record today's completion.
- [ ] Create a calendar event and reminder.
- [ ] Create a goal and project; linked progress is correct.
- [ ] Generate a report for a known date range.
- [ ] Upload/download an allowed attachment.
- [ ] Create and verify a backup.
- [ ] Search works and no critical console errors occur.

Use clearly marked test records and remove them after the smoke test, unless the environment is a disposable test instance.

## 10. Release versioning

Use semantic versioning where practical:
- MAJOR: incompatible data model or workflow change.
- MINOR: backward-compatible feature addition.
- PATCH: backward-compatible bug/security fix.

Every release should include:
- version and release date;
- new features;
- fixes;
- known limitations;
- migration notes;
- backup/restore notes;
- compatibility matrix.

## 11. Go/no-go criteria

Release only when:
- critical acceptance tests pass;
- no known data-loss defect remains;
- backup and restore have been tested;
- migrations pass on a clean install and supported upgrade path;
- security checklist passes;
- user-facing documentation is updated;
- rollback route is known and tested.

## 12. Post-release

For the first use after release:
- verify logs and scheduler status;
- verify backup destination and latest backup;
- monitor error rate and slow operations;
- collect issues with reproduction steps;
- avoid silently changing schema or user data to patch a symptom.
