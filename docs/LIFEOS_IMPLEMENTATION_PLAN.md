# LIFEOS IMPLEMENTATION PLAN

**Backend:** PHP Native 8.2  
**Database:** MariaDB 10.4.32  
**Frontend:** Bootstrap 5.3-compatible UI, Vanilla JavaScript, Alpine.js when needed  
**Environment:** Windows + XAMPP

The project is developed as a local-first modular monolith. Composer is not required for the local single-user v1. PHP modules use explicit includes and a small namespace loader.

## Development phases
0. Project setup, configuration, local runtime, logging and PDO.
1. Application foundation: request/response, router, controllers, validation, CSRF, session, views, error handling and shared UI.
2. Database foundation: migration runner, profile/settings, then domain schemas and seeders.
3. Finance.
4. Schedule & Calendar.
5. Tasks.
6. Projects.
7. Goals.
8. Habits.
9. Journal & Notes.
10. Reports.
11. Dashboard.
12. Shared system features: trash, history, attachments, notifications, import/export and backup.
13. Quality assurance.

## Definition of done
A feature is complete only when its backend behavior, persistence, validation, error handling, UI states and relevant security controls work. A visible page or demo is not a completed feature.

## Database constraints
All SQL must run on MariaDB 10.4. Use InnoDB, utf8mb4, DECIMAL(19,2) for money, UTC timestamps, DATE for calendar dates, foreign keys, explicit indexes and profile ownership. Finance ledger operations must be atomic. Migration files are immutable after application; subsequent changes use new migration files.

## Development rules
- No AI API integration.
- No direct SQL in views or controllers.
- No cross-module repository access.
- No placeholder buttons or fake functionality.
- Do not add dependencies without a technical reason.
- Do not destructively alter local user data without explicit confirmation.
