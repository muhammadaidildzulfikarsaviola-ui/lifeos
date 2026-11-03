# LIFEOS Data Migration, Import & Export Specification

**Status:** Draft for implementation  
**Version:** 1.0  
**Scope:** CSV/XLSX import, CSV/XLSX export, schema migrations and user-data portability

## 1. Purpose

This document defines safe movement of user data into, out of, and between LIFEOS versions. It separates two different concerns:

- **Schema migrations:** evolve the internal database structure between application releases.
- **User-data import/export:** move records to/from user-readable formats such as CSV/XLSX.

They must not share unsafe execution paths.

## 2. Schema migration policy

- Every schema change is a numbered, ordered migration.
- Migrations run through a CLI command or controlled maintenance screen, not on every web request.
- Record migration ID, checksum/version, start/end time and result.
- Run migrations against a verified backup first.
- Migrations must be repeat-safe where possible; otherwise detect and refuse duplicate execution.
- Do not edit a migration that has already shipped. Add a new migration.
- Destructive migrations require explicit review and a verified backup.
- Test fresh install and upgrade from every supported previous release.
- Do not assume MySQL and MariaDB support identical SQL syntax/features.

A migration should define `up`; a `down` operation is optional and only allowed when genuinely safe. Data-destructive changes should use staged migration: add new structure, backfill, verify, then remove old structure in a later release.

## 3. Import supported formats

V1:
- CSV: UTF-8 preferred; detect/handle UTF-8 BOM.
- XLSX: supported through a maintained PHP library if Composer dependencies are approved.
- Maximum file size: configurable, default 50 MB.
- Reject macro-enabled formats and unsupported binary formats.
- Import is a user-initiated workflow with preview and explicit commit.

Do not parse XLSX by trusting filename or browser MIME type. Validate extension, detected MIME/signature, archive structure and decompressed size.

## 4. Import workflow

1. Upload file to private staging storage.
2. Create `import_jobs` record with module, file metadata, status and creator/profile.
3. Inspect workbook/sheet or CSV header.
4. Let user map source columns to LIFEOS fields.
5. Validate every row without writing production entities.
6. Store row-level result in `import_job_rows`: row number, status, safe error codes/messages and normalized preview data where appropriate.
7. Display summary: valid, invalid, duplicate, skipped rows.
8. User selects the commit behavior allowed for that module.
9. Commit validated rows in controlled batches/transactions.
10. Record imported entity IDs and final job status.
11. Retain staging metadata for troubleshooting according to retention policy; delete temporary source file when user requests or policy expires.

Raw row content should not be copied into logs. Staging data is sensitive and stored outside public root.

## 5. Duplicate handling

Offer explicit strategies only when the module supports deterministic matching:
- Skip duplicates.
- Update existing record.
- Import as new record.

Default should be **skip duplicates** where a stable external key or deterministic natural key exists. Do not guess identity based on fuzzy title matching. Show the matching key and consequence before commit.

For finance records, duplicate detection must be conservative. Do not automatically merge transactions based only on amount/date/description. Use an import reference key when present; otherwise require user review.

## 6. Atomicity and partial success

Small imports may commit in one transaction. Large imports may use batches. For batched imports:
- each batch is atomic;
- the job records committed row IDs;
- retries skip already committed rows using an idempotency key;
- partial success is explicit and visible;
- a failed batch does not claim the entire import succeeded.

The UI must explain whether rollback is possible. If earlier batches committed, provide a safe compensating cleanup path only when ownership and side effects are understood. Never promise full rollback when the import has already produced external side effects.

## 7. Module import templates

Provide downloadable templates and field descriptions for:
- Finance transactions (income/expense only by default; transfers and loan repayment require specialized mapping/workflow).
- Tasks and subtasks.
- Habits and schedules.
- Journal entries.
- Notes and folders.
- Goals and milestones.
- Projects and milestones.
- Calendar events and shifts.

Relationships should be imported in phases or via stable external reference columns. Example: import projects first, then tasks with project external key. Reject unresolved references with row-level errors.

Attachments are not embedded in CSV/XLSX. Provide a separate attachment bundle/import mapping if later supported.

## 8. Export workflow

Export supports CSV and XLSX for eligible lists/reports. Before generating:
- validate module and date filters;
- apply the same profile ownership and visibility rules as the UI;
- define timezone and date format;
- define amount/currency formatting;
- include column headers and export timestamp;
- generate file in private temporary storage;
- provide a controlled download endpoint;
- delete temporary exports after a configurable retention period.

Do not export internal secrets, password hashes, session data, raw logs or unnecessary internal IDs by default. Include stable IDs only when needed for round-trip import and clearly label them as reference IDs.

## 9. Formula injection protection

CSV/XLSX exports must neutralize spreadsheet formula injection. Text values beginning with `=`, `+`, `-`, or `@` (including leading whitespace/control characters before them) must be escaped or emitted as literal text according to the chosen library. Test this behavior. Do not rely on the spreadsheet application to protect the user.

## 10. Date, time and number formats

- Store date-only values as `YYYY-MM-DD`.
- Interpret imported local date/time values in `Asia/Jakarta` unless the template explicitly declares a timezone.
- Convert timestamps to UTC for storage.
- Use decimal parsing that does not confuse Indonesian separators (`1.234,56`) with US-style (`1,234.56`); the import template must declare expected number format or provide a user-selected locale.
- Monetary values are parsed as decimal strings, never binary floats.
- Reject ambiguous values rather than silently guessing.

## 11. Import validation catalog

Validate:
- required columns and unknown/missing mapping;
- maximum field length;
- enum values;
- date/time syntax and valid ranges;
- positive amount and decimal precision;
- referenced wallet/category/project/goal ownership;
- duplicate keys;
- dependency cycles for task dependencies;
- recurrence syntax and supported frequency;
- file size and content format.

Return stable error codes plus Indonesian user-facing descriptions. Avoid exposing raw parser exceptions.

## 12. Data portability and privacy

Exports may contain highly personal financial, journal and note data. Require deliberate user action, clear scope, and private download handling. Never create public predictable export URLs. Temporary export links should be unguessable and expire. Do not send exported data to third-party services automatically.

## 13. Acceptance criteria

- CSV with UTF-8 BOM imports correctly.
- XLSX import rejects malformed/oversized archives.
- User can map columns and preview before commit.
- Invalid rows are reported with row numbers and actionable errors.
- Retry does not duplicate previously committed rows.
- Finance duplicate handling is conservative.
- CSV/XLSX export respects filters and ownership.
- Formula injection test cases are neutralized.
- Indonesian decimal/date formats are handled explicitly.
- Schema migrations pass fresh-install and supported upgrade tests.
