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

### `guest_pack_purchases`
Audit log **and** ledger for guest-pack capacity. `participant_limit` is
recomputed from the `applied` rows (`base + Σ deltas`, or unlimited), so refunds
reverse cleanly.
| field              | type      | notes                                             |
|--------------------|-----------|---------------------------------------------------|
| id                 | bigint PK |                                                   |
| event_id           | FK events?| nullable; null when the purchase can't be matched |
| user_id            | FK users? | best-effort from RevenueCat `app_user_id`         |
| rc_event_id        | string?   | RevenueCat `event.id`; unique (webhook dedup)     |
| transaction_id     | string?   | store transaction; links a refund to its purchase |
| type               | string    | `NON_RENEWING_PURCHASE` \| `REFUND` \| …           |
| product_id         | string?   | purchased SKU                                     |
| participants_delta | int?      | credited participants (negative on reversal)      |
| grants_unlimited   | bool      | pack removed the cap                              |
| status             | string    | `applied`/`reversed`/`reversal`/`ignored`/`unmatched` |
| source             | string    | `webhook` \| `sync`                               |
| raw                | json      | full payload                                      |
| created_at         | timestamp |                                                   |

> `events` also gains `base_participant_limit` — the pre-pack capacity captured
> the first time a pack is applied, so refunds have a floor to reverse back to.

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
| DELETE | `/auth/user`            | ✔    | — → `{message}` |
| POST   | `/auth/google/callback` | —    | `{id_token, access_token?}` → `{user, token}` |
| POST   | `/auth/apple/callback`  | —    | `{identity_token, authorization_code?, user_identifier?, full_name?, email?}` → `{user, token}` |

`User` = `{ id, name, email, created_at, updated_at }`.
`full_name` (Apple) = `{ givenName?, familyName? } | null`.

#### Sign in with Apple

Implemented by `SocialAuthController@apple` + `SocialTokenVerifier` (identity
token) and `AppleTokenClient` (Apple's token/revoke endpoints).

- **`identity_token` is the only credential.** Verified against Apple's JWKS
  (cached an hour), with `iss`, `exp` and `aud` all checked. Identity comes from
  the signed `sub`; `user_identifier` is client-supplied and is only cross-checked
  against it — a mismatch is a 422.
- **`APPLE_CLIENT_ID` is a comma-separated list.** The app ships three bundle ids
  (`com.get.everly`, `.dev`, `.preview`), each minting tokens with its own `aud`.
  The first entry is the primary: the one the .p8 key is registered against.
- **`full_name` / `email` arrive only on the first authorization.** Later sign-ins
  send nulls, and the app replays its cached copy after a failed attempt, so
  repeats are idempotent. A stored name is never overwritten with null. Apple's
  private relay may withhold the email entirely, in which case the account gets a
  unique non-routable placeholder.
- **`authorization_code` buys the ability to revoke.** It is exchanged once for a
  refresh token (`users.apple_refresh_token`, encrypted at rest, never fillable,
  never serialized). The exchange is best-effort: Apple rejects an already-redeemed
  code, and a login must never fail over it.
- **The exchange and the revoke name the bundle id the credential came from**,
  taken from the verified `aud` and stored in `users.apple_client_id`. Apple
  refuses a code or token redeemed under a different client, so a `.dev` sign-in
  cannot be exchanged as production. This means **the .p8 key must be authorised
  for all three App IDs** — i.e. they belong to the same Sign in with Apple group
  as the key's primary App ID.
- **`DELETE /auth/user` revokes before deleting.** App Store guideline 5.1.1(v)
  requires it of any app offering Sign in with Apple. A failed revoke is logged
  but does not block the deletion. Events and photos cascade via their foreign
  keys.

Revocation needs `APPLE_TEAM_ID`, `APPLE_KEY_ID` and the .p8 key
(`APPLE_PRIVATE_KEY_PATH` or inline `APPLE_PRIVATE_KEY`); `AppleTokenClient`
no-ops without them, so **sign-in works with no Apple key configured — only
revocation is lost.**

`php artisan apple:check` reports the configuration: values present, .p8 loads,
ES256 signing works, key id matches the file name.

**It cannot tell you whether the key is authorised for these bundle ids**, and
nothing else can either. Apple validates the `code`/`token` *before* the client
credentials, so both `/auth/token` and `/auth/revoke` answer `invalid_grant` to
everything — a fabricated team id, key id and bundle id included (measured, not
assumed). Only a real `authorization_code` exercises the client secret, so the
first genuine test is a sign-in on a device followed by an account deletion;
a wrong key shows up in the log as `Apple token revocation failed` carrying
`invalid_client`.

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
| GET    | `/upload/{token}` | —    | `X-Guest-Token?` | — → `UploadToken` |
| POST   | `/upload/{token}` | —    | `X-Guest-Token?` (multipart) | file field `photo` → `{ guest_token, remaining }` |
| GET    | `/upload/{token}/photos` | — | `X-Guest-Token?` | — → `{ photos[], revealed }` |

`{token}` is the event's `qr_code_token`.

**`UploadToken`** (is this event accepting uploads?)
```json
{
  "active": true,
  "event": {
    "name": "Sarah & James",
    "title": "Sarah & James",
    "cover_image_url": "https://.../cover.jpg",
    "canUpload": true,
    "shotLimit": 7,
    "shotsPerGuest": 10
  }
}
```

`shotLimit` is what this guest **may still upload**, `shotsPerGuest` the event's
allowance — together they render "7 of 10 left". Both are `null` when the event
sets no per-guest limit.

**`X-Guest-Token` is optional on the GET and only affects `shotLimit`.** Sent, the
remainder is counted against that guest; omitted (or unrecognised), the response
reports the full allowance, because a guest with no token has uploaded nothing.
The header is never used to *create* a guest here — a read must not consume a
participant slot.

`POST` returns `remaining` alongside `guest_token` on 201, so a client can keep a
"photos left" counter honest without a second request.

> The limits themselves are enforced server-side regardless: `POST` rejects with
> 422 once `Guest::hasReachedShotLimit()` is true, when the event has stopped
> accepting uploads, or when `participant_limit` is full. These fields exist so a
> client can *say so first*, not so it can be trusted to.

**`GET /upload/{token}/photos`** — the requesting guest's **own** photos:

```json
{
  "photos": [
    { "id": 27, "url": "https://.../x.jpg", "thumbnail_url": "https://.../x_thumb.jpg", "media_type": "image" }
  ],
  "revealed": false
}
```

Without a recognised `X-Guest-Token` it returns an empty list, not an error — a
guest who has uploaded nothing has nothing here. It exists because the client
keeps only an opaque token, so after a reload the server is the only thing that
knows what this guest contributed.

> 🔒 **The `where('guest_id', …)` in `GuestUploadController::mine()` is the whole
> safety property of this route, and it is not conditional on anything.** No
> flag, reveal state or query parameter widens it to the rest of the album.
> `revealed` is reported so a client can caption the list ("only you can see
> these until the reveal") — it never gates the list, because the list is the
> guest's own either way. A guest-facing view of *everyone's* photos would be a
> new route with its own reasoning, never a loosened `where` on this one.

**Upload request:** `multipart/form-data` with a **`photo`** file part. On first upload
the server issues a `guest_token`; the client stores it and resends it as the
`X-Guest-Token` header on subsequent uploads so the same guest is tracked (and
`shot_limit` enforced). Response: `{ "guest_token": "..." }`.

> ⚠️ The client currently appends the file under the field name **`photo`**
> (`app/upload/[token].tsx`). Match that field name, or tell me to change it.

### 2.5b Guest web flow  (HTML, not API)

| Method | Path                      | Auth | Response |
|--------|---------------------------|------|----------|
| GET    | `/upload/{token}`         | —    | `text/html` — the invitation |
| GET    | `/upload/{token}/album`   | —    | `text/html` — the album |

Note the missing `/api` prefix: these are **web** routes (`routes/web.php` →
`JoinController`, views `join.blade.php` and `join-album.blade.php`), sharing
the path shape of the API endpoint above so the deep link, the QR and the
shared link all read as one address.

**A guest can complete the whole flow in a browser — no app required.** The
invitation collects a name; the album uploads photos straight to `POST
/api/upload/{token}` (same origin, so no CORS and no CSRF token — the `api`
group is stateless). The guest identity is the `X-Guest-Token` the API issues on
the first upload, kept in `localStorage` under `everly.guest.<qr_code_token>`
along with the name; losing it restarts the guest's shot allowance and splits
their photos between two anonymous guests, so it is written before navigating.
Reaching `/album` with no stored name redirects back to the invitation.

Same credential as 2.5: the `qr_code_token` in the URL is the whole
authorisation, and the pages show only what its holder is already entitled to —
event name, cover, host's name, photo count, guest count, and whether it still
takes uploads. **Never anyone's photos.** The album grid shows only the guest's
own uploads from the current session, as local object URLs; there is no endpoint
that would return another guest's, and there should not be one. The reveal is
the product.

Server-rendered rather than fetched client-side, because the invite is pasted
into WhatsApp and iMessage far more than it is typed, and those unfurlers don't
run JavaScript. An unknown token gets Laravel's default 404.

Config lives under `everly.invite` (`config/everly.php`): `app_store_url` (env
`INVITE_APP_STORE_URL`) and the `fallback_cover` shown when the event has none.

> ⚠️ `throttle:guest-uploads` is 30/min **keyed by IP**. A venue is one wifi NAT,
> so with web uploads the limit is spent by the room, not the person. Keying by
> `X-Guest-Token` when present (falling back to IP for the first upload) is the
> fix; it affects the app's uploads too, so it is left as a decision.

### 2.6 Referral

| Method | Path                 | Auth | Body → Response |
|--------|----------------------|------|-----------------|
| POST   | `/referrals/redeem`  | ✔    | `{ code }` → `{ success, reward }` |

### 2.7 Guest packs (extra participant capacity)

Guests packs are one-time in-app purchases (via **RevenueCat**) that raise an
event's `participant_limit`. Capacity is decoupled from `plans`: how much a pack
is worth lives entirely in `config/guest_packs.php` (product identifier →
participants, `null` = unlimited). Every event keeps a **baseline of 5** guests
that a refund can never drop it below.

| Method | Path                              | Auth | Notes |
|--------|-----------------------------------|------|-------|
| POST   | `/webhooks/revenuecat`            | secret | RevenueCat webhook (below) |
| POST   | `/events/{id}/guest-packs/sync`   | ✔    | Optional instant credit; off unless `GUEST_PACK_SYNC_ENABLED=true` |

**Webhook auth.** No Sanctum token — RevenueCat sends the shared secret
`REVENUECAT_WEBHOOK_AUTH` in the `Authorization` header. The endpoint fails
closed (401) when the secret is unset or mismatched.

**Which event?** A purchase must say which event it tops up. The client sets the
RevenueCat **subscriber attribute** `everly_event_id` to the target event id
before calling `purchase()`; the webhook reads it from
`event.subscriber_attributes.everly_event_id.value`.

**Handled event types.** `NON_RENEWING_PURCHASE` credits the mapped participants
(additive; `null` = unlimited). `REFUND` reverses the matching purchase (found by
`transaction_id`) and recomputes capacity, floored at the baseline 5. Any other
type is recorded and ignored.

**Idempotency.** A purchase is credited at most once regardless of path: webhook
retries are deduped by RevenueCat's `event.id`, and a `sync` call plus a later
webhook for the same `transaction_id` credit only once. Every delivery is written
to `guest_pack_purchases` (audit) with a `status` explaining what happened.

**`POST /events/{id}/guest-packs/sync`** body `{ product_id, transaction_id }` →
`{ status, event }` (the updated `Event`). `product_id` must be a known pack SKU.

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
