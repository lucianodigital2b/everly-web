# Everly — Backend Specification

This document describes the exact data structures and endpoints the **Everly** mobile
app already consumes (see `services/api.ts`). Build the backend to this contract and
the app works with zero client changes.

- **Stack (assumed):** Laravel + Sanctum (token auth). Any stack works as long as the
  JSON shapes and routes match.
- **Auth:** Bearer token (`Authorization: Bearer <token>`), issued on register/login.
- **Content type:** `application/json` for all endpoints except guest photo upload
  (`multipart/form-data`).
- **Base URL:** the app reads `EXPO_PUBLIC_API_URL` (e.g. `https://api.everly.app/api`).
  > ⚠️ `services/api.ts` currently hardcodes an ngrok URL in `BASE_URL` — point it at
  > `process.env.EXPO_PUBLIC_API_URL` before shipping.

### Standard error shape (Laravel-style)

The client reads `error.response.data.message` and `error.response.data.errors`:

```json
{
  "message": "The email has already been taken.",
  "errors": { "email": ["The email has already been taken."] }
}
```

A `401` on any authenticated request makes the app clear the session and log out.

---

## 1. Data models

### `users`
| field         | type      | notes                         |
|---------------|-----------|-------------------------------|
| id            | bigint PK |                               |
| name          | string    |                               |
| email         | string    | unique                        |
| password      | string    | hashed; never returned        |
| created_at    | timestamp |                               |
| updated_at    | timestamp |                               |

Social login: store provider identifiers as needed (`google_id`, `apple_id`). The app
sends provider tokens (below) and expects back a normal `{ user, token }`.

### `plans`
| field            | type    | notes                                   |
|------------------|---------|-----------------------------------------|
| id               | bigint  |                                         |
| name             | string  | e.g. `Free`, `Intimate`, `Celebration`, `Grand` |
| price_cents      | int     | in the plan currency                    |
| currency         | string  | e.g. `brl`, `usd`                       |
| max_uploads      | int     | per-event photo cap (0 / null = ∞)      |
| allow_download   | bool    | drives `gates.canDownload`              |
| allow_slideshow  | bool    | drives `gates.canSlideshow`             |
| white_label      | bool    | drives `gates.whiteLabel`               |
| duration_days    | int     | event lifetime after activation         |

Reference tiers (from product spec): Free = 5 participants, Intimate = 30,
Celebration = 100, Grand = unlimited.

### `events`
| field              | type      | notes                                             |
|--------------------|-----------|---------------------------------------------------|
| id                 | bigint PK |                                                   |
| user_id            | FK users  | owner                                             |
| plan_id            | FK plans  |                                                   |
| qr_code_token      | string    | unique, unguessable; used by the guest upload URL |
| status             | enum      | `active` \| `pending_payment`                     |
| public             | bool      |                                                   |
| name               | string    | required                                          |
| title              | string?   | display title (falls back to `name`)              |
| event_date         | datetime? |                                                   |
| shot_limit         | int?      | photos per guest (null = ∞)                       |
| participant_limit  | int?      | max guests (null = ∞)                             |
| reveal_time        | string?   | `Instant` \| `End of Event` \| `Scheduled`        |
| reveal_at          | datetime? | **suggested**: when `reveal_time = Scheduled`     |
| filter             | string?   | guest shooting filter key: `none`/`warm`/`film`/`mono` |
| tier               | string?   | tier label mirrored from the plan                 |
| is_revealed        | bool      | gallery revealed → photos visible to owner        |
| cover_image_url    | string?   | absolute URL                                      |
| activated_at       | datetime? | set when payment completes / plan is free         |
| expires_at         | datetime? | `activated_at + plan.duration_days`               |
| created_at         | timestamp |                                                   |
| updated_at         | timestamp |                                                   |

**Reveal logic** (server-derived): a gallery's photos are shown to the owner when
`is_revealed = true`. Compute/flip `is_revealed` based on `reveal_time`:
`Instant` → true immediately; `End of Event` → true after `expires_at`;
`Scheduled` → true after `reveal_at`.

### `event_photos`
| field             | type      | notes                                  |
|-------------------|-----------|----------------------------------------|
| id                | bigint PK |                                        |
| event_id          | FK events |                                        |
| guest_id          | FK guests | uploader (nullable)                    |
| url               | string    | absolute URL to full media             |
| thumbnail_url     | string?   | absolute URL to thumbnail              |
| media_type        | enum      | `image` \| `video`                     |
| duration_seconds  | int?      | for `video`                            |
| width             | int?      |                                        |
| height            | int?      |                                        |
| created_at        | timestamp |                                        |

### `guests`
Represents an anonymous uploader tied to one event via the QR token.
| field       | type      | notes                                          |
|-------------|-----------|------------------------------------------------|
| id          | bigint PK |                                                |
| event_id    | FK events |                                                |
| guest_token | string    | unique; returned to the client, resent as header |
| upload_count| int       | to enforce `shot_limit` per guest              |
| created_at  | timestamp |                                                |

### `payments`
| field        | type      | notes                                   |
|--------------|-----------|-----------------------------------------|
| id           | bigint PK |                                         |
| event_id     | FK events |                                         |
| status       | string    | e.g. `pending`, `paid`, `failed`        |
| amount_cents | int?      |                                         |
| currency     | string?   |                                         |
| invoice_url  | string?   | PIX checkout / invoice URL              |
| created_at   | timestamp |                                         |

### `referrals` (optional)
| field      | type   | notes                          |
|------------|--------|--------------------------------|
| id         | bigint |                                |
| code       | string | unique redeemable code         |
| user_id    | FK     | redeemer                       |
| reward     | json   | whatever the reward payload is |
| created_at | ts     |                                |

---

## 2. Endpoints

### 2.1 Auth  `/auth/*`

| Method | Path                    | Auth | Body → Response |
|--------|-------------------------|------|-----------------|
| POST   | `/auth/register`        | —    | `{name,email,password,password_confirmation}` → `{user, token}` |
| POST   | `/auth/login`           | —    | `{email,password,device_name?}` → `{user, token}` |
| POST   | `/auth/logout`          | ✔    | — → `{message}` |
| GET    | `/auth/user`            | ✔    | — → `User` |
| POST   | `/auth/google/callback` | —    | `{id_token, access_token?}` → `{user, token}` |
| POST   | `/auth/apple/callback`  | —    | `{identity_token, user_identifier, full_name, email}` → `{user, token}` |

`User` = `{ id, name, email, created_at, updated_at }`.
`full_name` (Apple) = `{ givenName?, familyName? } | null`.

### 2.2 Plans

| Method | Path     | Auth | Response |
|--------|----------|------|----------|
| GET    | `/plans` | ✔    | `Plan[]` |

### 2.3 Events  `/events/*`

| Method | Path                   | Auth | Body → Response |
|--------|------------------------|------|-----------------|
| GET    | `/events`              | ✔    | — → `Event[]` |
| POST   | `/events`              | ✔    | `EventStoreBody` → `Event` |
| GET    | `/events/{id}`         | ✔    | — → `EventDetail` |
| PATCH  | `/events/{id}`         | ✔    | `EventUpdateBody` → `Event` |
| DELETE | `/events/{id}`         | ✔    | — → `204 No Content` |
| GET    | `/events/{id}/photos`  | ✔    | — → `{ photos: EventPhoto[] }` |

**`EventStoreBody`**
```json
{
  "name": "Sarah & James",
  "plan_id": 2,
  "title": "Sarah & James",
  "event_date": "2026-09-12T00:00:00Z",
  "expires_at": "2026-09-14T00:00:00Z",
  "shot_limit": 10,
  "participant_limit": 48,
  "reveal_time": "Instant",
  "filter": "film",
  "tier": "Celebration",
  "is_revealed": false,
  "cover_image_url": "https://.../cover.jpg"
}
```
Only `name` and `plan_id` are required; the rest are optional.

**`EventUpdateBody`** = any subset of `EventStoreBody` plus `public: boolean`.

**`Event`** (full object, always includes the nested `plan`)
```json
{
  "id": 1,
  "user_id": 1,
  "plan_id": 2,
  "qr_code_token": "abc123",
  "status": "active",
  "public": true,
  "name": "Sarah & James",
  "title": "Sarah & James",
  "event_date": "2026-09-12T00:00:00Z",
  "shot_limit": 10,
  "participant_limit": 48,
  "reveal_time": "Instant",
  "filter": "film",
  "tier": "Celebration",
  "is_revealed": true,
  "cover_image_url": "https://.../cover.jpg",
  "activated_at": "2026-09-05T00:00:00Z",
  "expires_at": "2026-09-14T00:00:00Z",
  "created_at": "2026-09-01T00:00:00Z",
  "updated_at": "2026-09-13T00:00:00Z",
  "plan": { "...Plan..." }
}
```

**`EventDetail`** (returned by `GET /events/{id}`)
```json
{
  "event": { "...Event..." },
  "gates": {
    "canUpload": true,
    "canDownload": true,
    "canSlideshow": true,
    "whiteLabel": false
  },
  "uploadCount": 8,
  "latestPayment": { "status": "paid", "invoiceUrl": null }
}
```
- `gates` is derived from the event's plan flags + limits (server computes it).
- `uploadCount` = number of photos in the event.
- `latestPayment` may be `null`.

**`EventPhoto`**
```json
{
  "id": 1,
  "url": "https://.../photo.jpg",
  "thumbnail_url": "https://.../thumb.jpg",
  "media_type": "image",
  "duration_seconds": null,
  "width": 900,
  "height": 675,
  "created_at": "2026-09-13T00:00:00Z"
}
```
> The app only renders gallery photos when `event.is_revealed === true`.

### 2.4 Checkout  (PIX)

| Method | Path                     | Auth | Response |
|--------|--------------------------|------|----------|
| POST   | `/events/{id}/checkout`  | ✔    | `{ status, invoiceUrl }` |
| GET    | `/events/{id}/payment`   | ✔    | `{ payment: Payment \| null }` |

`Payment` = `{ status, amount_cents?, currency?, invoiceUrl, created_at? }`.

Flow: paid plan → event created as `pending_payment` → `POST checkout` returns a PIX
`invoiceUrl` → client polls `GET payment` until `status = paid` → server sets event
`status = active`, `activated_at`, `expires_at`. Free plans skip checkout (activate on
create).

### 2.5 Guest photo upload  (no auth — QR token)

| Method | Path              | Auth | Headers | Body → Response |
|--------|-------------------|------|---------|-----------------|
| GET    | `/upload/{token}` | —    | —       | — → `UploadToken` |
| POST   | `/upload/{token}` | —    | `X-Guest-Token?` (multipart) | file field `photo` → `{ guest_token }` |

`{token}` is the event's `qr_code_token`.

**`UploadToken`** (is this event accepting uploads?)
```json
{
  "active": true,
  "event": {
    "name": "Sarah & James",
    "title": "Sarah & James",
    "cover_image_url": "https://.../cover.jpg",
    "canUpload": true
  }
}
```

**Upload request:** `multipart/form-data` with a **`photo`** file part. On first upload
the server issues a `guest_token`; the client stores it and resends it as the
`X-Guest-Token` header on subsequent uploads so the same guest is tracked (and
`shot_limit` enforced). Response: `{ "guest_token": "..." }`.

> ⚠️ The client currently appends the file under the field name **`photo`**
> (`app/upload/[token].tsx`). Match that field name, or tell me to change it.

### 2.6 Referral

| Method | Path                 | Auth | Body → Response |
|--------|----------------------|------|-----------------|
| POST   | `/referrals/redeem`  | ✔    | `{ code }` → `{ success, reward }` |

---

## 3. Enums / constants

| Concept          | Values |
|------------------|--------|
| event status     | `active`, `pending_payment` |
| reveal_time      | `Instant`, `End of Event`, `Scheduled` |
| guest filter     | `none`, `warm`, `film`, `mono` |
| media_type       | `image`, `video` |
| plan / tier      | `Free`, `Intimate`, `Celebration`, `Grand` |

---

## 4. Build order (suggested)

1. `users` + Sanctum → `/auth/register`, `/auth/login`, `/auth/user`, `/auth/logout`.
2. `plans` + seeder → `/plans`.
3. `events` CRUD + `EventDetail` (gates/uploadCount) → home + detail screens work.
4. `guests` + `/upload/{token}` (GET + POST multipart) → guest QR upload works.
5. `event_photos` + `/events/{id}/photos` → bento gallery populates.
6. `payments` + `/events/{id}/checkout` + `/payment` (PIX) → paid tiers.
7. Social callbacks + referrals last.

> Tip for testing without the real API: the app ships a dev mock. Log in via the
> **“Enter demo (dev)”** link on the welcome screen — the API layer serves sample
> events/photos (see `services/mockData.ts`) whenever the session token equals the
> mock token, so you can develop the backend and the app side-by-side.
