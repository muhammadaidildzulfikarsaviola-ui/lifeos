# LIFEOS Logging & Monitoring Specification

**Status:** Draft for implementation  
**Version:** 1.0  
**Scope:** Local-first PHP application and scheduled CLI jobs

## 1. Purpose

Logging gives LIFEOS enough diagnostic evidence to investigate failures without exposing sensitive personal data. Monitoring in v1 is local and operational: application health, scheduler execution, storage capacity, database connectivity, backup status, and failed jobs.

## 2. Logging principles

- Logs are structured and machine-readable (JSON Lines recommended).
- Every request/job has a correlation ID.
- Log events, not entire user records.
- Never log passwords, secrets, session tokens, full journal/note content, attachment contents, or complete SQL parameter payloads.
- User-facing messages remain safe and understandable; technical detail stays in local logs.
- Logs must not become an unbounded storage leak.

## 3. Log categories

| Category | Examples | Level |
|---|---|---|
| Application | request start/end, route failure, uncaught exception | INFO/WARNING/ERROR |
| Security | CSRF failure, invalid upload, suspicious path | WARNING/ERROR |
| Database | connection failure, transaction rollback, slow query summary | WARNING/ERROR |
| Scheduler | job start/end, duration, lock collision | INFO/WARNING/ERROR |
| Backup | archive created, checksum failure, restore attempt | INFO/WARNING/ERROR |
| Import/export | job status, rejected row count, export failure | INFO/WARNING/ERROR |
| Notification | queued/sent/retried/failed delivery | INFO/WARNING/ERROR |

Do not log every successful CRUD operation by default. Audit history and operational logs serve different purposes.

## 4. Log event schema

Recommended fields:
```json
{
  "timestamp": "2026-11-03T15:00:00Z",
  "level": "ERROR",
  "category": "database",
  "event": "transaction_commit_failed",
  "request_id": "opaque-id",
  "job_id": null,
  "module": "finance",
  "message": "Database transaction failed.",
  "error_code": "DB_COMMIT_FAILED",
  "duration_ms": 84
}
```

Fields are optional where irrelevant. Use UTC timestamps. Do not put raw user content into `message`.

## 5. Levels and policy

- DEBUG: development diagnostics only; disabled in normal use.
- INFO: lifecycle events and successful maintenance jobs.
- NOTICE: noteworthy but expected conditions.
- WARNING: recoverable failure, rejected input, retry or degraded feature.
- ERROR: operation failed and needs attention.
- CRITICAL: data integrity, restore, or application availability may be at risk.

Production/local-use default should be INFO, with WARNING and above retained reliably.

## 6. Request correlation

Generate a cryptographically random request ID at the HTTP entry point. Include it in:
- application log entries;
- error response metadata;
- service exception context;
- any related asynchronous job record.

Do not accept a client-provided correlation ID without validation. If one is accepted for tracing, generate a separate trusted internal ID.

## 7. Sensitive-data handling

Redact:
- DB credentials and environment variables;
- file contents and full note/journal bodies;
- personal finance descriptions and counterparties unless a narrowly justified audit requirement exists;
- raw import row contents;
- absolute filesystem paths in user-facing messages.

Prefer record type + internal ID + safe error code. Logs are still personal data and must be stored in a private directory with access limited to the local application/operator.

## 8. Storage and rotation

- Store logs outside `public/`, for example `storage/logs/`.
- Rotate by date and/or size.
- Recommended initial limit: 20 MB per file, retain 30 days; make both configurable.
- Keep backup/restore audit records longer than ordinary debug logs, but do not retain verbose payloads.
- When disk space is low, warn before logging fails. Never delete user backups to make room for logs automatically.
- A log cleanup job must not remove active log files.

## 9. Monitoring and health checks

Provide a local health screen or diagnostics command for:
- PHP version and required extensions;
- database connection and engine/version;
- schema migration version;
- writable storage directories;
- free disk space;
- backup destination writable status;
- latest successful backup and verification state;
- scheduler last-run status;
- pending/failed notification and import jobs;
- application version.

Health checks must not expose credentials or full paths to an unauthenticated network. V1 assumes local access; if LAN access is enabled, health details require additional protection.

## 10. Slow operation diagnostics

Track duration for:
- dashboard aggregate queries;
- global search;
- reports;
- calendar range fetch;
- import validation/commit;
- backup/restore;
- attachment processing.

Log query name and duration, not interpolated SQL values. A configurable slow-query threshold should start conservatively (e.g. 500 ms) and be tuned with realistic local data.

## 11. Scheduler observability

Every scheduled job should record:
- job type and ID;
- start/end UTC;
- result and exit code;
- attempt count;
- duration;
- safe error code;
- next retry or next scheduled time where applicable.

Use locks to avoid concurrent duplicate runs. A stale lock recovery process must verify the owning process/job is no longer active before releasing it.

## 12. Alerts and user-facing status

V1 does not require external alerting services. Show in-app status for:
- last backup success/failure;
- missed scheduled backup;
- failed import/export;
- notification delivery failure;
- low storage;
- database unavailable.

Do not generate noisy repeated alerts for the same unresolved condition. Deduplicate by condition and update its last-seen timestamp.

## 13. Error handling

- Catch exceptions at HTTP/CLI boundaries.
- Log full exception object privately, with sensitive values scrubbed.
- Return safe error message and request/job ID.
- Never swallow an exception and report success.
- Roll back open transactions on failure.
- Mark job state accurately (`failed`, `partial`, `cancelled`, `completed`).

## 14. Acceptance criteria

- Every request and scheduled job has a traceable ID.
- Structured logs can be parsed line-by-line.
- Secrets and journal/note content do not appear in logs.
- Logs rotate and remain outside public root.
- Health check identifies missing extensions, DB failure, unwritable storage and stale backup.
- Slow operations can be identified without leaking query values.
- Failed scheduled jobs are visible in the UI.
