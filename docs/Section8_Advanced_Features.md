# Section 8: Additional / Advanced Features (Arep / Ariff)

## External API integration — QR code generator
`api/lib/qr.php` calls **api.qrserver.com** (`/v1/create-qr-code/`) with payload `BOOMPLUS:<booking_ref>` whenever a booking's ticket is created or first viewed. The PNG is validated (PNG signature), converted to a data URI and cached in `bookings.qr_code`. If the service is unreachable the booking still succeeds and the UI shows "QR unavailable"; the next view retries. The customer portal renders the QR on the e-ticket, and the organiser check-in page (`Ariff/verify.php`) looks bookings up by reference.

## Security
- JWT (HS256) + RBAC middleware: `require_auth()`, `require_role()`, `require_owner_or_admin()`.
- Rate limiting: 300 requests / minute / IP globally and 15 / minute for login, register and password-reset endpoints; headers `X-RateLimit-Limit/Remaining/Reset`, `Retry-After` on 429. State is stored in `rate_limits`.
- Security headers: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Cache-Control: no-store`.
- Prepared statements for all SQL; bcrypt; single-use hashed reset tokens; CSRF tokens on every portal form.

## Middleware stack (`api/middleware/`)
| File | Purpose |
|---|---|
| `security.php` | headers, CORS, preflight |
| `rate_limit.php` | per-IP and per-action throttling |
| `logger.php` | writes every request (method, path, status, duration, user) to `request_logs` |
| `error.php` | converts errors / exceptions to consistent JSON, hides internals |
| `validation.php` | declarative rules (`required|int|min:1|in:a,b`) returning 422 with field errors |
| `auth.php` | JWT verification, role and ownership checks, audit trail |

## Other features
Filtering, sorting and pagination on all list endpoints; transactional seat locking; automatic refund and seat release on cancellation; event cancellation cascades to bookings; schedule-conflict detection; daily venue schedule endpoint.
