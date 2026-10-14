# Section 3: RESTful API Documentation (Akma)

Base URL `http://localhost/boom/api` · JSON in and out · `Authorization: Bearer <JWT>` for protected routes.

Success: `{ "success": true, "message"?: "...", "data": ..., "meta"?: { "total", "page", "limit", "pages" } }`
Failure: `{ "success": false, "message": "...", "errors"?: { "field": "reason" } }`

| Status | Meaning |
|---|---|
| 200 / 201 | OK / created |
| 400 | Malformed JSON or business rule (e.g. wrong current password) |
| 401 | Missing, invalid or expired token; disabled account |
| 403 | Authenticated but role / ownership not allowed |
| 404 | Resource not found (or hidden from your role) |
| 409 | Conflict: sold out, slot taken, schedule clash, record in use |
| 422 | Validation failed (`errors` lists each field) |
| 429 | Rate limit exceeded (`Retry-After` header) |
| 500 | Unexpected error (details only in the server log) |

## List query parameters
`page`, `limit` (max 100), `sort` (comma list, prefix `-` for descending), `q` (search) plus the filters below.

## Auth
| Method | Path | Access | Body |
|---|---|---|---|
| POST | /auth/register | public | full_name, email, password, phone? |
| POST | /auth/login | public | email, password → token + user |
| GET | /auth/me | any user | |
| POST | /auth/forgot-password | public | email (dev: returns `dev_reset_token`) |
| POST | /auth/reset-password | public | token, password |

## Users
| Method | Path | Access | Notes |
|---|---|---|---|
| GET / PUT | /users/me | any user | profile read / update (full_name, phone) |
| PUT | /users/me/password | any user | current_password, new_password |
| GET | /users | admin | filters `role`, `is_active`, `q`; sort `id,name,email,role,created_at` |
| POST | /users | admin | create user with any role |
| GET | /users/{id} | admin | |
| PUT | /users/{id}/role | admin | role |
| PUT | /users/{id}/status | admin | is_active |
| DELETE | /users/{id} | admin | 409 when the user owns data |

## Venues
| Method | Path | Access | Notes |
|---|---|---|---|
| GET | /venues | public | filters `city`, `sport`, `q`, `mine=1`, `is_active`; sort `name,city,capacity,created_at` |
| POST | /venues | admin, organiser | venue_name, address, city, capacity, contact_phone?, is_active?, owner_id? (admin) |
| GET | /venues/{id} | public | includes courts |
| PUT / PATCH | /venues/{id} | owner, admin | |
| DELETE | /venues/{id} | owner, admin | 409 if events or bookings exist |
| GET / POST | /venues/{id}/courts | public / owner, admin | sport, court_name, hourly_rate, is_active? |
| GET / PUT / PATCH / DELETE | /venues/{id}/courts/{courtId} | public / owner, admin | |
| GET | /venues/{id}/schedule?date=YYYY-MM-DD | public | events and booked court slots for the day |

## Events
| Method | Path | Access | Notes |
|---|---|---|---|
| GET | /events | public | customers see published/completed; organisers also own drafts; filters `sport,status,venue_id,city,from,to,upcoming=1,available=1,mine=1,q`; sort `start_time,title,price,created_at,city` |
| POST | /events | admin, organiser | venue_id, sport, title, start_time, end_time, description?, status? (draft/published), ticket_types[]? |
| GET | /events/{id} | public (drafts: owner/admin) | includes ticket_types with `remaining` |
| PUT / PATCH | /events/{id} | owner, admin | status `cancelled` cancels and refunds all bookings |
| DELETE | /events/{id} | owner, admin | 409 if bookings exist |
| GET / POST | /events/{id}/tickets | public / owner, admin | type_name, price, capacity |
| PUT / PATCH / DELETE | /events/{id}/tickets/{ticketId} | owner, admin | capacity cannot drop below sold |
| GET | /events/{id}/availability | public | real-time seats left per ticket type, `bookable` flag |

Rules: end after start; venue cannot host two draft/published events at overlapping times (409); total ticket capacity ≤ venue capacity (422).

## Bookings
| Method | Path | Access | Notes |
|---|---|---|---|
| GET | /bookings | any user (scoped) | customers: own; organisers: own events/venues; admin: all. Filters `status,booking_type,event_id,user_id,from,to,view=active|history,q`; sort `created_at,amount,status,starts_at,ref` |
| POST | /bookings | customer | `event_ticket`: ticket_type_id, quantity (1-10) · `court`: court_id, slot_start, slot_end (whole hours, 1-4 h) · payment_method |
| GET | /bookings/{id} | scoped | includes `qr_code` (PNG data URI from the external QR API) |
| PUT / PATCH | /bookings/{id} | owner, admin | change quantity or court slot, re-validates availability |
| DELETE, POST /bookings/{id}/cancel | /bookings/{id} | scoped | releases seats, refunds paid payments |
| PUT / PATCH | /bookings/{id}/status | admin, organiser | transitions: pending→confirmed/cancelled, confirmed→completed/cancelled |

Business rules: seats are locked with `SELECT … FOR UPDATE` inside a transaction so concurrent buyers cannot oversell; cash bookings start `pending`, online payments start `confirmed`; court slots cannot overlap another active booking or a published event at that venue.

## System
| Method | Path | Access |
|---|---|---|
| GET | /system/health | public |
| GET | /system/stats | any user (role-specific figures) |
| GET | /system/audit-logs | admin (filters `action,user_id,q`) |
| GET | /system/request-logs | admin (filters `method,status_code,q`) |
