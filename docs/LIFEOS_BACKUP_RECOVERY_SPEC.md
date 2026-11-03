# LIFEOS Backup & Recovery Specification

**Status:** Draft for implementation  
**Version:** 1.0  
**Scope:** Local MySQL-compatible database, local attachments, configuration and application data

## 1. Purpose and recovery objectives

This document defines how LIFEOS protects user data and restores a consistent working state. Backup and restore are high-risk operations: the application must make the target, scope, and consequences obvious before execution.

Initial v1 policy:
- Manual backup is always available.
- Monthly automatic backup is supported through Windows Task Scheduler and PHP CLI.
- Backup retention is indefinite by default; the user may manually delete backups.
- Backup destination is configurable and must be outside the public web root.
- A backup is considered valid only after integrity checks complete.
- Restore always requires preview, explicit confirmation, and a safety backup of the current state.

No cloud backup is assumed in v1.

## 2. Backup scope

A full backup package should include:

1. Database dump for the LIFEOS schema.
2. Local attachment files referenced by the database.
3. A manifest describing application version, schema version, creation time (UTC), timezone, database engine/version, included components, and checksums.
4. Optional non-secret application configuration needed to interpret the data.

Never include secrets such as database passwords, API keys, session tokens, or machine-specific credentials in plaintext. The restore process should use the target installation's own connection configuration.

A database-only backup may be offered as an advanced option, but the default user-facing backup should be full backup.

## 3. Package format and naming

Recommended package format: ZIP containing:
```text
manifest.json
database/lifeos.sql
attachments/...
checksums.sha256
```

Filename pattern:
`LIFEOS_backup_YYYY-MM-DD_HHmmss_<schema-version>.zip`

Use UTC timestamps in the manifest and a human-readable local timestamp in the UI. Generate filenames safely and avoid overwriting an existing backup. Write to a temporary filename, verify, then atomically rename to the final filename.

## 4. Backup execution

1. Create a backup job record and unique job ID.
2. Check destination exists, is writable, and has sufficient free space.
3. Acquire a lock to prevent concurrent backup/restore jobs.
4. Produce a consistent database dump using a supported tool or a transactionally consistent export method.
5. Copy attachment files while preserving relative storage identifiers, not arbitrary original paths.
6. Generate manifest and checksums.
7. Re-open the ZIP and verify required entries and checksums.
8. Mark job successful and expose it in backup history.
9. Remove temporary files on failure and record a safe diagnostic.

For InnoDB, use a consistent snapshot where supported. Avoid locking the entire database for the full duration if a consistent transaction snapshot is available. Confirm actual XAMPP database engine and tool versions before implementation; XAMPP may ship MariaDB rather than Oracle MySQL.

## 5. Automatic schedule

Default cadence: monthly. The exact day/time should be configurable; recommended default is the first day of each month at 02:00 local time. Windows Task Scheduler invokes a PHP CLI command. The command must:
- use the configured PHP executable and project bootstrap;
- write structured logs;
- use the same locking and verification rules as manual backup;
- return non-zero exit code on failure;
- avoid launching a second backup if one is already active.

The UI must show the last run, next scheduled run (if calculable), and last result. If the machine is off at the scheduled time, the task should be configured to run as soon as possible after a missed start.

## 6. Backup verification

Verification should include:
- ZIP integrity test.
- Manifest parse and schema validation.
- Required file presence.
- Checksum verification.
- SQL dump non-empty and contains expected schema marker.
- Attachment count/size summary.
- Optional restore smoke test into a disposable database during development or scheduled maintenance.

A backup that has not passed verification must not be labelled “Ready to restore”.

## 7. Restore workflow

Restore is destructive and must not happen through a single casual click.

### 7.1 Preview

Before restore, show:
- backup filename and creation date;
- LIFEOS version and schema version;
- database engine/version if known;
- database size and attachment count/size;
- checksum/integrity status;
- compatibility warning if versions differ;
- whether current data will be replaced or merged.

V1 restore mode is **replace current LIFEOS data**, not merge. Do not offer merge unless a future design specifies conflict semantics.

### 7.2 Confirmation

Require the user to type a clear confirmation phrase, e.g. `RESTORE LIFEOS`, and confirm the target database. Explain that current data will be replaced. Automatically create a safety backup of the current state before proceeding. If safety backup fails, abort restore.

### 7.3 Execution

1. Acquire exclusive maintenance lock.
2. Validate archive and manifest again.
3. Create safety backup.
4. Restore database into a staging database/schema where feasible.
5. Run schema/version and integrity checks.
6. Reconcile attachment directory. Do not delete existing files until staging validation succeeds.
7. Switch/replace active data using a controlled process.
8. Run post-restore checks and update application schema version.
9. Release lock and record outcome.

If staging database restore is not feasible in the local setup, document the fallback precisely and ensure the safety backup can be restored. Never claim atomic restore if the chosen engine/tool cannot guarantee it.

## 8. Compatibility and migrations

- Restore of a backup from a newer schema version into an older application must be blocked by default.
- Older backups may require a sequential migration path.
- Run migrations on a copy/staging database first.
- Keep migration scripts versioned and idempotent where practical.
- Do not auto-downgrade schemas.
- Display actionable compatibility errors rather than raw SQL errors.

## 9. Attachment recovery

Attachment paths stored in the database must be relative storage keys, never absolute machine paths. During restore:
- reject path traversal (`..`, absolute paths, drive letters, null bytes);
- normalize separators;
- verify each extracted path remains inside the intended storage root;
- verify checksums;
- extract to a staging directory;
- swap or copy into place only after validation.

Missing attachments should be reported with counts and affected records. A missing attachment must not silently break the entire database restore.

## 10. Security

- Backup directory must not be web-accessible.
- Use restrictive filesystem permissions where available.
- Do not log SQL dumps, secrets, or full sensitive record contents.
- Validate archive entries against path traversal and ZIP bomb risks.
- Limit archive size and decompressed total size before extraction.
- Require explicit confirmation for restore and permanent backup deletion.
- Never execute scripts or binaries found inside a backup archive.

Encryption at rest is not included in the v1 baseline. If added later, key management must be designed separately; do not bundle a password in the same archive.

## 11. UI states

Backup UI should show:
- destination path;
- manual backup action;
- automatic schedule configuration;
- backup list with date, size, status, and verification result;
- progress and failure state;
- restore preview and confirmation;
- delete confirmation;
- clear recovery guidance.

Do not make progress depend on a long-running HTTP request. Use a job record and polling/status endpoint or a CLI task with visible status.

## 12. Logging and audit

Record job ID, action, start/end time, result, archive name, size, verification result, and safe error code. Do not record credentials or personal content. Keep a history of restore attempts, including aborted attempts.

## 13. Failure handling

- Insufficient disk space: abort before writing final archive.
- Database dump failure: discard incomplete package.
- Attachment copy failure: mark backup failed unless the user explicitly chose a database-only backup.
- Checksum mismatch: never mark backup valid.
- Restore safety backup failure: abort restore.
- Restore failure after destructive phase: retain safety backup, report recovery instructions, and prevent normal writes until database state is confirmed.
- Lock collision: report that another maintenance job is running.

## 14. Acceptance criteria

- Manual full backup creates a verified package.
- Monthly task runs via PHP CLI and is idempotent.
- Backup package includes manifest, SQL dump, attachments and checksums.
- Corrupted archive is rejected.
- Restore preview accurately describes replacement.
- Restore cannot proceed without safety backup and explicit confirmation.
- Path traversal entries are rejected.
- Restore compatibility checks block unsupported schema transitions.
- Logs contain useful job metadata without secrets.
- A documented recovery drill succeeds on a clean test installation.
