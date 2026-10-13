# Section 4: API Testing (Arep / Ariff)

Import `postman/BOOM_Plus.postman_collection.json` and `postman/BOOM_Plus.postman_environment.json` into Postman and select the **BOOM+ Local** environment.

1. Run **Auth → Login (admin / organiser / customer)**. Each request's test script stores `adminToken`, `organiserToken`, `customerToken`.
2. Run folders in order: Venues, Events, Bookings, Negative tests. Every request has assertions for status code and response shape.
3. Capture screenshots of passing and failing assertions for the report.

## Success cases
| Request | Expect |
|---|---|
| POST /auth/login | 200, token returned |
| GET /events?sport=Futsal&sort=-start_time&limit=2 | 200, `meta.total` present |
| POST /bookings (event_ticket) | 201, status `confirmed`, `qr_code` data URI |
| POST /bookings/{id}/cancel | 200, status `cancelled`, seats released |

## Failure cases
| Request | Expect |
|---|---|
| POST /auth/login wrong password | 401 |
| GET /users with customer token | 403 |
| GET /bookings without token | 401 |
| POST /bookings quantity 99 | 422 with `errors.quantity` |
| POST /bookings on a sold-out ticket | 409 |
| POST /bookings court slot already taken | 409 |
| POST /events overlapping another event at the same venue | 409 |
| 16 rapid POST /auth/login | 429 with `Retry-After` |
