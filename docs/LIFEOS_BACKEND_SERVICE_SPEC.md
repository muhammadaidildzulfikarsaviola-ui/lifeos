# LIFEOS Backend Service Specification

**Status:** Draft for implementation  
**Version:** 1.0  
**Target stack:** PHP Native 8.2+ / MySQL-compatible InnoDB / Apache via XAMPP  
**Application mode:** Local-first, single active profile in v1

## 1. Purpose

This document defines backend boundaries, service responsibilities, data-access rules, validation, transaction handling, and cross-module orchestration for LIFEOS. It supplements the PRD, architecture, API contract, domain rules, and database documents. If a conflict exists, resolve it through the database review and domain rules before implementation; do not silently invent a third interpretation.

## 2. Architectural boundaries

Use a layered modular monolith:

1. **HTTP layer:** receives requests, authenticates neither user nor session in v1, applies request-size/content-type checks, invokes controllers, and emits responses.
2. **Controller layer:** translates HTTP input into application commands, calls services, and maps results/errors to HTTP responses. Controllers must not contain domain calculations or SQL.
3. **Application/service layer:** enforces use cases, authorization/ownership boundaries, validation orchestration, domain rules, and transaction boundaries.
4. **Repository layer:** performs parameterized SQL and maps rows to domain-friendly structures. Repositories do not decide business policy.
5. **Infrastructure layer:** database connection, filesystem, clock, logging, backup, import/export, notification adapters, and scheduler integration.
6. **Presentation layer:** server-rendered PHP views and frontend components. It must not access the database directly.

A module may expose a small public service API to other modules. Cross-module calls must go through services, not another module's repositories.

## 3. Suggested directory structure

```text
app/
  Core/
    Database/
    Http/
    Validation/
    Exceptions/
    Logging/
    Security/
    Support/
  Modules/
    Finance/
      Controllers/
      Services/
      Repositories/
      DTO/
      Validators/
    Tasks/
    Schedule/
    Habits/
    Journal/
    Notes/
    Goals/
    Projects/
    Reports/
  Shared/
    Services/
    Repositories/
    DTO/
    Enums/
    ValueObjects/
  Infrastructure/
    Filesystem/
    Notifications/
    Backup/
    ImportExport/
    Scheduler/
  Views/
bootstrap/
config/
database/
  migrations/
  seeds/
public/
  index.php
  assets/
storage/
  app/
  backups/
  logs/
  imports/
  exports/
tests/
```

Keep module internals private by convention. Shared code is for genuinely cross-module concerns, not a dumping ground.

## 4. Service contracts and responsibilities

Services should expose use-case-oriented methods. Prefer explicit inputs and return values over passing raw `$_POST` or global state.

| Service | Responsibilities | Must not do |
|---|---|---|
| FinanceService | Create/update/delete transactions, transfers, budgets, recurring finance templates, loan records and payments; calculate balances and budget actuals | Mutate account balances as an independent source of truth |
| LedgerService | Write balanced ledger lines; calculate account balances; validate transfer symmetry | Treat transfers as income or expense |
| RecurringTransactionService | Generate due occurrences idempotently; record occurrence status | Generate duplicate transactions on retry |
| ScheduleService | Manage events, recurrence rules, overrides, shifts, reminders and event completion | Store shifts as duplicate calendar events |
| TaskService | Manage tasks, subtasks, status, progress, dependencies, recurring tasks and time logs | Enforce task dependencies as a hard blocker in v1 |
| HabitService | Manage habit definitions, schedules, daily logs and streak/statistics | Count rest days as missed days |
| JournalService | Manage dated entries, mood, templates, prompts and search metadata | Assume one journal entry per date |
| NoteService | Manage folders, notes, tags, archive/pin state, checklists and attachments | Store uploaded files inside the public web root |
| GoalService | Manage goal targets, milestones and linked-item progress | Mix manually entered progress with linked progress without an explicit mode |
| ProjectService | Manage project lifecycle, milestones, notes, task links and project progress | Recalculate project budget from unrelated transactions |
| ReportService | Aggregate read-only cross-module data for defined periods | Modify source records during report generation |
| ImportService | Stage CSV/XLSX imports, validate rows, preview and commit atomically where possible | Write unvalidated rows directly into production tables |
| BackupService | Create, verify, list and restore backups with safety checks | Overwrite the active database without explicit confirmation |
| TrashService | Soft-delete, list, restore and permanently delete eligible records | Restore a record without validating dependencies/ownership |
| AttachmentService | Validate, store, retrieve and delete local attachments | Trust client-provided MIME types or filenames |

## 5. Core service rules

### 5.1 Input and validation

- Normalize input at the HTTP boundary; validate again in the service for non-HTTP callers.
- Validate required fields, type, length, allowed enum values, date/time ranges, numeric precision, and relationship ownership.
- Never trust IDs supplied by the browser. Confirm that each referenced record belongs to the active profile or is explicitly global.
- Use allowlists for polymorphic entity types (`entity_tags`, `entity_attachments`, `goal_links`, `trash_items`).
- Reject unknown fields where practical; do not silently accept unexpected mass-assignment input.
- Return field-level validation errors in a stable response structure.

### 5.2 Database transactions

Use a DB transaction for every use case that writes multiple related rows or requires atomicity. Examples:

- Transaction + ledger lines + loan allocation.
- Transfer + source and destination ledger lines.
- Task creation with subtasks or dependency links.
- Habit definition and schedule rows.
- Import commit batch.
- Trash operation plus trash metadata.
- Restore operation plus source record restoration.

Do not hold a DB transaction open while waiting for user input, filesystem uploads, external network calls, or long-running exports. Validate and stage first, then perform a short commit transaction.

### 5.3 Idempotency and concurrency

- Recurring generation uses a unique `(template_id, occurrence_key)` constraint and a transaction. On duplicate-key conflict, return the already-created occurrence.
- Notification delivery uses claim/lock, attempt count, retry schedule, and terminal status. Two scheduler runs must not send the same delivery concurrently.
- Import commit stores a stable job identifier and row result; retry must not duplicate records.
- Backup jobs use unique job IDs and write to a temporary file before finalizing.
- Use row-level locks (`SELECT ... FOR UPDATE`) when updating balances derived from concurrent ledger writes is not needed; lock the actual mutable parent/sequence row where concurrency requires it. Balance remains ledger-derived.

### 5.4 Time and money

- Store timestamps in UTC using the agreed SQL convention (`DATETIME` UTC); convert to `Asia/Jakarta` for display and user input interpretation.
- Store calendar-only dates as `DATE`.
- Use `DECIMAL` for monetary values, never floating-point.
- Keep amounts positive and express direction explicitly in ledger lines.
- Round only at defined currency/precision boundaries.

## 6. Finance orchestration

### 6.1 Income and expense

`FinanceService.createTransaction()` validates wallet, category, amount, date, and optional project. In one transaction it inserts the transaction header and ledger line(s). The ledger is the source of truth for account balance.

- Income: one incoming line.
- Expense: one outgoing line.
- Transfer: two lines, source outgoing and destination incoming, same currency and amount.
- Transfers are excluded from income/expense reporting.
- `project_id` is nullable and links one transaction to at most one project in v1.

### 6.2 Personal borrowing and repayment

Borrowing creates/updates a liability record and records the cash movement according to the chosen domain model. Repayment records one cash-out transaction and a loan-payment allocation split into principal and interest. Principal reduces outstanding liability; interest is an expense. Do not count principal as expense or count the cash-out twice.

The implementation must settle the exact posting shape against `LIFEOS_DATABASE_REVIEW.md` and `LIFEOS_DOMAIN_RULES.md` before migrations are frozen.

### 6.3 Budget actuals

Budget actuals are calculated from eligible expense transactions in the budget period/category. Transfers are excluded. Loan principal is excluded; interest is included as expense. Do not maintain a separately editable “actual” number.

## 7. Cross-module orchestration

- Task-to-calendar association uses `task_schedule_links`; a task is not itself a calendar event.
- Shifts are managed by ScheduleService and remain the canonical shift records.
- Goal links and project links are created through GoalService/ProjectService and validated against allowed entity types.
- Project budget actuals are derived from Finance transactions with matching `project_id`.
- Reports call read-only query services/repositories and must respect the same business definitions as module screens.
- Deleting a referenced entity must follow trash/dependency rules and preserve referential integrity.

Avoid synchronous chains that cause circular module dependencies. If Finance needs a project reference, it validates through a narrow ProjectService query or a shared ownership validator, not by importing ProjectRepository internals.

## 8. Error handling and response mapping

Use typed application exceptions, for example:

- `ValidationException` → HTTP 422
- `NotFoundException` → HTTP 404
- `ConflictException` → HTTP 409
- `ForbiddenOperationException` → HTTP 403
- `StorageException` → HTTP 500 with safe public message
- `DatabaseException` → HTTP 500 with correlation ID; log technical details privately

Never expose SQL, stack traces, absolute filesystem paths, secrets, or raw exception details to the browser. Log the exception with a correlation/request ID.

Suggested API error shape:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Periksa kembali data yang dimasukkan.",
    "fields": {
      "amount": ["Nominal harus lebih besar dari 0."]
    },
    "request_id": "..."
  }
}
```

## 9. Repository rules

- Use PDO prepared statements.
- Repositories accept explicit parameters and return typed arrays/DTOs.
- No SQL string concatenation with user input.
- Keep query methods focused; avoid generic repositories that obscure business intent.
- Pagination and sorting must use allowlisted column names.
- Use explicit selected columns rather than `SELECT *` in production paths.
- Wrap query failures in infrastructure exceptions while retaining the original exception for logs.

## 10. Files and attachments

AttachmentService validates extension, MIME detected server-side, size (maximum 50 MB), and safe generated storage name. Store files outside `public/`; expose download through a controller that checks ownership and streams the file with safe headers. Database metadata and file write must be reconciled: if DB commit fails, remove the orphaned file; if file write fails, do not create a valid attachment record.

## 11. Trash and restore

Soft delete is the default for supported entities. TrashService records source type, source ID, deletion time, and enough metadata for listing/recovery, but the source row remains the authoritative record. Restore validates source existence, ownership, unique constraints, and dependencies. Permanent deletion is explicit and should clean up dependent metadata/files according to policy.

## 12. Testing requirements

- Unit-test calculations and domain rules without HTTP.
- Integration-test each service with a real test database.
- Test rollback for multi-row writes.
- Test ownership violations and invalid polymorphic types.
- Test recurring generation twice and concurrently where feasible.
- Test duplicate notification claims.
- Test import retries and partial invalid rows.
- Test attachment cleanup after failures.
- Test report totals against known ledger fixtures.

## 13. Definition of done

A service is ready when its contract is documented, validation is covered, transaction boundaries are tested, ownership is enforced, errors are mapped safely, logs are useful, cross-module dependencies are one-way/narrow, and the relevant acceptance criteria pass.

## 14. Implementation decision gate

Before coding migrations or services, reconcile this document with the final ERD and SQL migration specification. In particular, confirm loan posting, recurring occurrence foreign keys, polymorphic references, and profile ownership. These are explicit design gates, not details to “fix later.”
