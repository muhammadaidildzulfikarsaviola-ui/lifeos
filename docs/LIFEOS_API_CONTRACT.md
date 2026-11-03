# LIFEOS API CONTRACT & DATA FLOW

**Project:** LIFEOS  
**Version:** 1.0  
**Status:** Technical Specification  
**Backend:** PHP Native 8.2  
**Frontend:** Vanilla JavaScript + Alpine.js  
**Communication:** HTTP + JSON  
**Architecture:** Internal Application API

---

# 1. PURPOSE

Dokumen ini menetapkan standar komunikasi antara frontend dan backend LIFEOS.

Seluruh modul harus mengikuti kontrak yang sama untuk:

- Request.
- Response.
- Validation.
- Error handling.
- Authentication context.
- CSRF protection.
- Pagination.
- Filtering.
- Sorting.
- Data import.
- JSON progress bridge.

API merupakan bagian internal dari aplikasi PHP Native LIFEOS.

Tidak ada server API terpisah dan tidak ada integrasi AI API.

---

# 2. API ARCHITECTURE

## 2.1 Base URL

Development:

http://lifeos.local

API prefix:

/api

Contoh:

/api/finance/transactions
/api/finance/accounts
/api/tasks
/api/projects
/api/habits
/api/schedule/events
/api/goals
/api/journal
/api/notes

## 2.2 HTTP Methods

| Method | Purpose |
|---|---|
| GET | Retrieve data |
| POST | Create data or execute an action |
| PUT | Replace/update resource |
| PATCH | Partial update |
| DELETE | Delete resource |

Semua operasi yang mengubah data harus melalui backend validation.

## 2.3 Content Types

Request JSON:

Content-Type: application/json

Response JSON:

Content-Type: application/json; charset=utf-8

File upload:

Content-Type: multipart/form-data

---

# 3. STANDARD RESPONSE FORMAT

Seluruh endpoint JSON harus menggunakan response envelope yang konsisten.

## 3.1 Success Response

Contoh:

{
  "success": true,
  "message": "Transaction created successfully.",
  "data": {
    "id": 125,
    "uuid": "example-uuid",
    "description": "Monthly salary",
    "amount": 5000000
  },
  "meta": null
}

## 3.2 Collection Response

{
  "success": true,
  "message": "Transactions retrieved successfully.",
  "data": [
    {
      "id": 125,
      "description": "Monthly salary",
      "amount": 5000000
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 100,
      "last_page": 5
    }
  }
}

## 3.3 Error Response

{
  "success": false,
  "message": "Validation failed.",
  "data": null,
  "errors": {
    "amount": [
      "Amount must be greater than zero."
    ]
  },
  "meta": null
}

## 3.4 Response Rules

- success selalu boolean.
- message selalu string.
- data dapat berupa object, array, atau null.
- errors hanya digunakan ketika ada error.
- meta berisi informasi tambahan.
- Jangan mengirim SQL error atau stack trace ke browser.
- Error teknis dicatat di server log.

---

# 4. HTTP STATUS CODES

| Code | Meaning | Usage |
|---|---|---|
| 200 | OK | Successful retrieval/update |
| 201 | Created | Resource created |
| 204 | No Content | Successful deletion without response body |
| 400 | Bad Request | Invalid request structure |
| 401 | Unauthorized | Invalid or missing application context, if applicable |
| 403 | Forbidden | Action not permitted |
| 404 | Not Found | Resource does not exist |
| 409 | Conflict | Duplicate or conflicting operation |
| 413 | Payload Too Large | Upload exceeds limit |
| 415 | Unsupported Media Type | Unsupported content type |
| 422 | Unprocessable Entity | Validation failure |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Unexpected application error |

Catatan:

Versi pertama tidak menggunakan login. Status 401 bukan mekanisme autentikasi pengguna aktif, melainkan disediakan untuk kemungkinan kebutuhan sistem di masa depan.

---

# 5. REQUEST VALIDATION

Setiap request harus divalidasi di backend.

## Validation Rules

- Required fields.
- Data types.
- String length.
- Numeric range.
- Date validity.
- Date range.
- Enum values.
- Foreign key existence.
- Profile ownership.
- Resource status.
- File size.
- MIME type.
- JSON structure.

Frontend validation hanya untuk membantu pengalaman pengguna.

Backend tetap menjadi sumber validasi utama.

## Example

Request:

{
  "description": "Lunch",
  "amount": 25000,
  "transaction_date": "2026-11-03",
  "account_id": 1,
  "category_id": 5
}

Jika amount tidak valid:

HTTP 422

{
  "success": false,
  "message": "Validation failed.",
  "data": null,
  "errors": {
    "amount": [
      "Amount must be greater than zero."
    ]
  },
  "meta": null
}

---

# 6. CSRF PROTECTION

Semua request yang mengubah data harus menggunakan CSRF token.

Berlaku untuk:

- POST.
- PUT.
- PATCH.
- DELETE.

Token dibuat dan diverifikasi oleh backend.

Frontend mengambil token dari meta tag atau endpoint khusus.

Contoh:

<meta name="csrf-token" content="SERVER_GENERATED_TOKEN">

JavaScript:

const token = document
    .querySelector('meta[name="csrf-token"]')
    .content;

fetch('/api/tasks', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token
    },
    body: JSON.stringify(payload)
});

Jangan menggunakan CSRF token statis.

CSRF token harus terikat dengan session aplikasi lokal.

---

# 7. PAGINATION

Semua daftar data yang berpotensi besar harus menggunakan pagination.

## Query Parameters

?page=1
&per_page=20

Default:

- page = 1
- per_page = 20
- maximum per_page = 100

Response meta:

{
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 250,
    "last_page": 13
  }
}

Pagination harus diterapkan di database query, bukan mengambil seluruh data lalu memotongnya di PHP.

---

# 8. SEARCH, FILTERING & SORTING

## Search

Contoh:

GET /api/tasks?q=project

Pencarian harus dibatasi pada kolom yang relevan.

## Filtering

Contoh:

GET /api/tasks?status=in_progress&priority=high

Filter tanggal:

GET /api/finance/transactions?from=2026-11-01&to=2026-11-30

## Sorting

Contoh:

GET /api/tasks?sort_by=deadline&sort_direction=asc

Sorting hanya boleh menggunakan whitelist kolom yang diizinkan.

Jangan memasukkan parameter sorting pengguna langsung ke SQL.

---

# 9. API ENDPOINT MAP

Daftar endpoint awal.

## 9.1 Dashboard

GET /api/dashboard/summary

GET /api/dashboard/widgets/finance

GET /api/dashboard/widgets/schedule

GET /api/dashboard/widgets/tasks

GET /api/dashboard/widgets/habits

GET /api/dashboard/widgets/projects

GET /api/dashboard/widgets/goals

GET /api/dashboard/widgets/journal

GET /api/dashboard/widgets/bills

GET /api/dashboard/widgets/analytics

## 9.2 Finance

GET /api/finance/transactions

POST /api/finance/transactions

GET /api/finance/transactions/{id}

PATCH /api/finance/transactions/{id}

DELETE /api/finance/transactions/{id}

GET /api/finance/accounts

POST /api/finance/accounts

PATCH /api/finance/accounts/{id}

POST /api/finance/transfers

GET /api/finance/budgets

POST /api/finance/budgets

GET /api/finance/loans

POST /api/finance/loans

POST /api/finance/loans/{id}/payments

GET /api/finance/bills

POST /api/finance/bills

## 9.3 Schedule

GET /api/schedule/events

POST /api/schedule/events

PATCH /api/schedule/events/{id}

DELETE /api/schedule/events/{id}

GET /api/schedule/reminders

GET /api/schedule/shifts

POST /api/schedule/shifts

GET /api/schedule/holidays

POST /api/schedule/holidays/import

## 9.4 Tasks

GET /api/tasks

POST /api/tasks

GET /api/tasks/{id}

PATCH /api/tasks/{id}

DELETE /api/tasks/{id}

POST /api/tasks/{id}/subtasks

POST /api/tasks/{id}/dependencies

DELETE /api/tasks/{id}/dependencies/{dependencyId}

POST /api/tasks/{id}/time-logs

GET /api/tasks/{id}/history

## 9.5 Habits

GET /api/habits

POST /api/habits

PATCH /api/habits/{id}

DELETE /api/habits/{id}

GET /api/habits/{id}/logs

POST /api/habits/{id}/logs

POST /api/habits/{id}/rest-days

GET /api/habits/{id}/statistics

## 9.6 Journal

GET /api/journal/entries

POST /api/journal/entries

GET /api/journal/entries/{id}

PATCH /api/journal/entries/{id}

DELETE /api/journal/entries/{id}

GET /api/journal/moods

## 9.7 Notes

GET /api/notes

POST /api/notes

GET /api/notes/{id}

PATCH /api/notes/{id}

DELETE /api/notes/{id}

GET /api/notes/folders

POST /api/notes/folders

POST /api/notes/{id}/attachments

## 9.8 Goals

GET /api/goals

POST /api/goals

GET /api/goals/{id}

PATCH /api/goals/{id}

DELETE /api/goals/{id}

POST /api/goals/{id}/milestones

POST /api/goals/{id}/links

GET /api/goals/{id}/progress

## 9.9 Projects

GET /api/projects

POST /api/projects

GET /api/projects/{id}

PATCH /api/projects/{id}

DELETE /api/projects/{id}

POST /api/projects/{id}/milestones

GET /api/projects/{id}/progress

GET /api/projects/{id}/budget

## 9.10 Reports

GET /api/reports/finance

GET /api/reports/tasks

GET /api/reports/habits

GET /api/reports/projects

GET /api/reports/goals

GET /api/reports/time-tracking

GET /api/reports/summary

## 9.11 System

GET /api/trash

POST /api/trash/{id}/restore

DELETE /api/trash/{id}/permanent

GET /api/history

POST /api/import/preview

POST /api/import/commit

POST /api/backup/create

GET /api/backup

POST /api/backup/restore/preview

POST /api/backup/restore/commit

---

# 10. STANDARD DATA FLOW

## Create

1. User submits form.
2. Frontend validates basic input.
3. Frontend sends request.
4. Backend validates CSRF.
5. Controller validates request structure.
6. Service applies business rules.
7. Repository writes to database.
8. Service records relevant history.
9. Backend returns response.
10. Frontend updates the UI.

## Update

1. User edits resource.
2. Frontend sends changed fields.
3. Backend verifies resource ownership.
4. Backend validates fields.
5. Service applies business rules.
6. Repository updates database.
7. Change history is recorded.
8. Frontend updates affected components.

## Delete

1. User requests deletion.
2. Frontend shows confirmation.
3. Backend validates resource.
4. Service checks dependencies.
5. Soft delete is performed.
6. Trash record is created.
7. Change history is recorded.
8. Frontend displays success.

Database writes and trash/history operations should be atomic where applicable.

---

# 11. TRANSACTION SAFETY

Finance operations must use database transactions.

Example transfer:

1. Validate source account.
2. Validate destination account.
3. Ensure accounts are different.
4. Validate amount.
5. Begin database transaction.
6. Create transaction header.
7. Create transaction lines.
8. Create transfer record.
9. Commit.
10. Rollback if any operation fails.

Never update account balances independently from the transaction ledger.

---

# 12. CHATGPT TASK JSON BRIDGE

## Purpose

Memungkinkan pengguna meminta bantuan ChatGPT dalam memperbarui progres task melalui pertukaran JSON manual.

Tidak ada AI API.

Tidak ada akses database langsung.

## 12.1 Generated Prompt

LIFEOS menghasilkan prompt berisi:

- Task UUID.
- Task title.
- Current status.
- Current progress.
- Deadline.
- Existing notes.
- Expected JSON schema.

Pengguna menyalin prompt tersebut ke ChatGPT.

## 12.2 JSON Response Contract

Contoh:

{
  "schema_version": "1.0",
  "source": "lifeos_manual_chatgpt_bridge",
  "generated_at": "2026-11-03T22:00:00+07:00",
  "updates": [
    {
      "task_uuid": "example-task-uuid",
      "status": "in_progress",
      "progress": 65,
      "note": "Database schema selesai dan sedang diuji."
    }
  ]
}

## 12.3 Allowed Fields

| Field | Required | Rule |
|---|---|---|
| schema_version | Yes | Supported version |
| source | Yes | Expected source identifier |
| generated_at | Yes | Valid timestamp |
| updates | Yes | Array |
| task_uuid | Yes | Existing task UUID |
| status | No | Allowed task status |
| progress | No | 0–100 |
| note | No | Maximum configured length |

Minimal satu dari `status`, `progress`, atau `note` harus tersedia dalam setiap update.

## 12.4 Import Flow

1. User pastes JSON.
2. Backend parses JSON.
3. Validate schema version.
4. Validate UUID.
5. Validate profile ownership.
6. Validate task existence.
7. Validate status.
8. Validate progress.
9. Detect conflicting updates.
10. Display preview.
11. User confirms.
12. Save updates.
13. Record change history.

## 12.5 Security Rules

- Unknown fields are rejected or ignored according to strict schema policy.
- SQL is never accepted.
- PHP code is never evaluated.
- UUID must match an existing task.
- Deleted tasks cannot be updated.
- Updates must remain within the active profile.
- All changes require confirmation.

---

# 13. IMPORT CONTRACT

Supported formats:

- CSV.
- XLSX.

Flow:

1. Upload.
2. Validate file type.
3. Parse.
4. Preview rows.
5. Validate fields.
6. Display invalid rows.
7. User confirms.
8. Commit valid rows according to import policy.
9. Record import history.

Import must not overwrite existing records without an explicit conflict policy.

---

# 14. FILE UPLOAD CONTRACT

Maximum size:

50 MB per file.

Allowed extensions:

- JPG.
- JPEG.
- PNG.
- WEBP.
- PDF.
- DOCX.
- XLSX.
- TXT.
- ZIP.

Validation:

- MIME detection.
- Extension validation.
- File size.
- Unique stored filename.
- Storage outside public web root.
- Safe download handling.

Never trust the original filename or client-provided MIME type.

---

# 15. FRONTEND API CLIENT

Create a shared JavaScript API client.

Suggested file:

public/assets/js/core/api.js

Responsibilities:

- Base URL.
- Default headers.
- CSRF token.
- JSON serialization.
- Response parsing.
- Error normalization.
- Network error handling.

Module scripts must use the shared client rather than implementing independent fetch logic.

---

# 16. ERROR HANDLING

Frontend should distinguish:

- Validation errors.
- Not found.
- Permission errors.
- Conflicts.
- Network errors.
- Server errors.

Users should receive understandable messages.

Technical details must be recorded in server logs.

Never display SQL statements, database credentials, stack traces, or filesystem paths in production UI.

---

# 17. API TESTING

Required tests:

- Successful GET.
- Successful POST.
- Successful PATCH.
- Successful DELETE.
- Invalid JSON.
- Missing CSRF.
- Invalid CSRF.
- Invalid foreign key.
- Wrong profile ownership.
- Invalid pagination.
- Invalid sorting.
- Duplicate transaction.
- Duplicate recurring execution.
- Invalid import file.
- Invalid JSON bridge payload.
- Deleted task update.
- Database transaction rollback.

---

# 18. IMPLEMENTATION RULES

1. All endpoints use the standard response envelope.
2. All write operations require CSRF.
3. All business rules belong in services.
4. All database access belongs in repositories.
5. Controllers must remain thin.
6. Frontend must use the shared API client.
7. Sorting fields must use a whitelist.
8. Pagination must be database-based.
9. Finance operations must be atomic.
10. JSON bridge must use strict validation.
11. No endpoint may bypass profile ownership checks.
12. No endpoint may expose internal server errors.

---

# END OF API CONTRACT