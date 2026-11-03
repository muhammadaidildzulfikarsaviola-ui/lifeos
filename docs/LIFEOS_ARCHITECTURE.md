# LIFEOS SOFTWARE ARCHITECTURE

**Version:** 1.1  
**Architecture:** Modular Monolith  
**Runtime:** PHP Native 8.2, no Composer for local v1  
**Database:** MariaDB 10.4.32  
**Environment:** Windows + XAMPP  
**Deployment:** Localhost only

LIFEOS is a local-first modular monolith. PHP files are loaded through explicit includes and a small namespace loader in the front controller. PDO is the persistence interface.

## Layers
- Presentation: views and static assets.
- HTTP: Request, Router, Response, controllers and middleware.
- Application: module services and use cases.
- Persistence: repositories using PDO prepared statements.
- Infrastructure: database, session, CSRF, logging, filesystem, backup and configuration.

Controllers do not contain business rules or SQL. Views never access repositories. Cross-module integration occurs through service contracts.

## Request lifecycle
Browser -> public/index.php -> Bootstrap -> Router -> Controller -> Service -> Repository -> PDO/MariaDB -> Response.

## Boundaries
Only public/ is web-accessible. app/, config/, database/, resources/, routes/ and storage/ stay outside the web root. Local .env is ignored by Git. MariaDB 10.4-compatible SQL only.

## Database conventions
InnoDB, utf8mb4, BIGINT UNSIGNED IDs, CHAR(36) UUIDs for externally referenced records, DECIMAL(19,2) money, UTC timestamps and DATE for calendar-only values. Profile-owned data uses profile_id. V1 seeds one local profile and has no login.

## Security
Bind development server to 127.0.0.1. Use PDO prepared statements, backend validation, HttpOnly/SameSite=Lax sessions, CSRF for every state-changing route, escaped HTML, and non-public storage. Technical errors are logged.

## Local runtime
D:\XAMPP\php\php.exe -S 127.0.0.1:8000 -t public

The PHP built-in server is for local development only and must not be exposed to LAN or the public internet.
