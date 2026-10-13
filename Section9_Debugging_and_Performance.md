# Section 9: Debugging and Performance Optimisation (Putera)

| # | Issue found | Root cause | Fix | Result |
|---|---|---|---|---|
| 1 | Bearer token ignored on Apache | Apache strips the `Authorization` header from CGI environments | `.htaccess` rule copies it to `HTTP_AUTHORIZATION`; `authorization_header()` also falls back to `apache_request_headers()` | Auth works on every XAMPP install |
| 2 | Possible overselling under concurrent bookings | Read-then-write on `ticket_types.sold` | Transaction with `SELECT … FOR UPDATE` plus `CHECK (sold <= capacity)` | No oversell possible |
| 3 | Court double booking | Two requests could pass the overlap check together | Court row is locked `FOR UPDATE` before the overlap query | Second request gets 409 |
| 4 | Every frontend user shared one rate-limit bucket | The portal calls the API from 127.0.0.1 | Portal forwards `X-Forwarded-For`; API trusts it only from loopback | Fair per-client limits |
| 5 | 500 responses leaked PHP errors | Default PHP error display | `display_errors` off, global exception handler returns generic JSON and logs details | No information leakage |
| 6 | Slow event / booking lists | Missing indexes for date and venue lookups | Added `idx_events_venue (venue_id, start_time)`, `idx_bookings_user (user_id, created_at)`, `idx_bookings_court_slot`, `idx_bookings_ticket` | Verify with `EXPLAIN` (record before / after timings in the report) |

Use the admin **API request log** page (`Putera_Aidil/logs.php`) to see duration per request, slow requests (>200 ms) and error rates, and `EXPLAIN` on the heavier queries to confirm index use.
