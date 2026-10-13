# BOOM+ — Sport Event Ticketing & Venue Booking

RESTful Web API plus four role-based frontends for **Pickleball, Futsal and Badminton**.
Stack: XAMPP (Apache, MariaDB/MySQL, PHP 8+), PHP, HTML, CSS, SQL only.

## Setup
1. Place this folder at `htdocs/boom` (Linux: `/opt/lampp/htdocs/boom`, Windows: `C:\xampp\htdocs\boom`).
2. Start **Apache** and **MySQL** in XAMPP (`mod_rewrite` must be enabled, it is by default).
3. Import the database: phpMyAdmin → Import → `database/boom_plus.sql`, or run
   `mysql -u root < database/boom_plus.sql`.
4. Adjust `config/config.php` if your DB credentials, folder name or Apache port differ
   (`DB_*`, `JWT_SECRET`, `APP_URL`, `API_BASE`).
5. Open <http://localhost/boom/>.

Demo accounts (password `Password123!`): `admin@boom.test`, `organiser@boom.test` (also `putera@boom.test`), `customer@boom.test`.

## Structure
| Folder | Owner | Purpose |
|---|---|---|
| `api/` | Group | Shared REST API (routes, middleware, libs) |
| `config/`, `database/` | Hafiq | Configuration, DDL + DML scripts |
| `shared/` | Group | API client, layouts, per-role themes, auth and profile pages |
| `Hafiq/` | Hafiq | Authentication & User Profile Portal, user admin, audit log |
| `Akma/` | Akma | Organiser / Admin Management Dashboard |
| `Ariff/` | Ariff (Arep) | Customer Booking & Checkout Portal, QR e-ticket, check-in |
| `Putera_Aidil/` | Putera | Customer Account & Ticketing Portal, API request log |
| `postman/` | Akma, Ariff | Postman collection and environment |
| `docs/` | All | Report sections |

Every portal serves all three roles (admin, organiser, customer) with a different design per role. Only the BOOM+ logo is identical.

## API overview
Base URL `http://localhost/boom/api`. Responses are JSON: `{ "success": true, "data": ..., "meta": ... }` or `{ "success": false, "message": ..., "errors": ... }`.
Authenticate with `POST /auth/login` and send `Authorization: Bearer <token>`.
See `docs/Section3_API_Documentation.md` for every endpoint.

Cross-cutting features: JWT + RBAC, request validation, centralised error handling, request logging (`request_logs`), rate limiting (headers `X-RateLimit-*`), security headers, filtering / sorting / pagination, external QR code API (api.qrserver.com).

## Adding an endpoint
Create `api/routes/<resource>.php` with `handle_<resource>($method, $segments)`, add the name to `RESOURCES` in `api/index.php`, and protect actions with `require_auth()` or `require_role(...)`.

Video Link: 
