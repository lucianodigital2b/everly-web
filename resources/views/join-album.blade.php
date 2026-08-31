{{--
    The album — screen two of the guest flow, where photos actually get added.

    Reached from the invitation once the guest has given a name. Standalone for
    the same reasons: no session, no Inertia bundle, one document.

    It deliberately shows no gallery. A guest sees the *count* of what everyone
    has contributed and the thumbnails of their own shots from this session,
    never anyone else's — guests don't see each other's photos before the
    reveal, and neither does the host. Removing that would remove the product.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $event->name }} &middot; everly</title>
<meta name="theme-color" content="#FBF8F5">
<meta name="robots" content="noindex, nofollow">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@600;700&family=Sacramento&display=swap" rel="stylesheet">

<style>
  :root {
    color-scheme: light;
    --ground: #FBF8F5;
    --well: #F5EDEA;
    --ink: #2E2429;
    --ink-small: #6F6068;
    --accent: #E6A9B4;
    --accent-small: #9E5163;
    --shadow-lift: 0 12px 28px rgba(199, 126, 141, 0.28);
  }

  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body {
    background: var(--ground);
    color: var(--ink);
    font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    -webkit-font-smoothing: antialiased;
  }

  .shell {
    max-width: 480px;
    margin: 0 auto;
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    background: var(--ground);
  }

  /* ── Hero ─────────────────────────────────────────────────────────────────
     No dissolve here, unlike the invitation: the title and the counts sit *on*
     the photo, so the bottom needs to go darker rather than lighter. The scrim
     is a real one — it exists to carry white text over a cover that could just
     as easily be a bright sky. */
  .hero {
    position: relative;
    height: clamp(300px, 52dvh, 480px);
    flex: none;
    background: var(--well);
    /* No overflow clipping: the action bar hangs 22px past the bottom edge on
       purpose, and hiding overflow here amputates it. The image needs no
       clipping of its own — object-fit already contains it. */
  }
  .hero img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    filter: saturate(0.94) contrast(1.02);
  }
  .hero::after {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
      linear-gradient(to bottom, rgba(46, 36, 41, 0.34) 0%, rgba(46, 36, 41, 0) 24%),
      linear-gradient(to top, rgba(46, 36, 41, 0.86) 0%, rgba(46, 36, 41, 0.55) 22%, rgba(46, 36, 41, 0.12) 46%, rgba(46, 36, 41, 0) 62%);
  }

  .back {
    position: absolute;
    top: max(16px, env(safe-area-inset-top));
    left: 16px;
    z-index: 3;
    width: 38px;
    height: 38px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(46, 36, 41, 0.42);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    color: #fff;
    text-decoration: none;
  }
  .back svg { width: 19px; height: 19px; stroke: currentColor; }

  .hero-body {
    position: absolute;
    z-index: 3;
    left: 0;
    right: 0;
    bottom: 76px;
    padding: 0 22px;
    color: #fff;
    text-align: center;
  }
  .hero-body h1 {
    font-family: Lora, Georgia, serif;
    font-weight: 700;
    font-size: clamp(26px, 7.4vw, 33px);
    line-height: 1.12;
    letter-spacing: -0.5px;
    margin: 0;
    text-wrap: balance;
    text-shadow: 0 1px 16px rgba(46, 36, 41, 0.4);
  }

  .stats {
    display: flex;
    margin-top: 16px;
  }
  .stat {
    flex: 1;
    text-align: center;
    min-width: 0;
  }
  .stat b {
    display: block;
    font-family: Lora, Georgia, serif;
    font-weight: 600;
    font-size: 19px;
    line-height: 1.1;
    letter-spacing: -0.3px;
  }
  .stat span {
    display: block;
    margin-top: 4px;
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.11em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.72);
  }

  /* Straddles the photo's bottom edge, which is where the reference puts it and
     it earns the place: it belongs to the photo, but it is the one thing on
     this screen you are meant to touch. */
  .bar {
    position: absolute;
    z-index: 4;
    left: 16px;
    right: 16px;
    bottom: -22px;
    display: flex;
    align-items: stretch;
    border-radius: 18px;
    overflow: hidden;
    background: rgba(46, 36, 41, 0.72);
    backdrop-filter: blur(18px) saturate(1.3);
    -webkit-backdrop-filter: blur(18px) saturate(1.3);
    box-shadow: 0 10px 28px rgba(46, 36, 41, 0.26);
  }
  .bar button {
    -webkit-appearance: none;
    appearance: none;
    flex: 1;
    min-width: 0;
    border: 0;
    background: none;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-family: inherit;
    font-size: 14.5px;
    font-weight: 600;
    letter-spacing: -0.2px;
    color: #fff;
    cursor: pointer;
    transition: background-color 140ms;
  }
  .bar button:active { background: rgba(255, 255, 255, 0.09); }
  .bar button + button { border-left: 1px solid rgba(255, 255, 255, 0.16); }
  .bar svg { width: 16px; height: 16px; flex: none; stroke: currentColor; }
  .bar button[disabled] { opacity: 0.5; pointer-events: none; }

  /* ── Body ─────────────────────────────────────────────────────────────── */
  .body {
    flex: 1;
    padding: 46px 20px max(24px, env(safe-area-inset-bottom));
    display: flex;
    flex-direction: column;
  }

  .who {
    text-align: center;
    font-size: 12.5px;
    line-height: 1.6;
    color: var(--ink-small);
    margin: 0 0 16px;
  }
  .who a { color: var(--accent-small); text-decoration: underline; text-underline-offset: 3px; }
  .who .left { font-weight: 600; color: var(--ink); }
  .who .left.is-low { color: var(--accent-small); }
  .who .left.is-out { color: #A8635C; }

  /* The reveal, given a card of its own. It is the reason this album exists
     rather than a group chat, and it is the answer to the question a guest has
     the moment they've sent something: where did it go? */
  .banner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 16px;
    background: rgba(230, 169, 180, 0.16);
    border: 1px solid rgba(230, 169, 180, 0.34);
  }
  .banner svg { width: 18px; height: 18px; flex: none; stroke: var(--accent-small); }
  .banner p { margin: 0; font-size: 13px; line-height: 1.45; color: var(--ink); text-wrap: pretty; }

  .progress {
    margin: 18px auto 0;
    width: 100%;
    max-width: 240px;
    height: 3px;
    border-radius: 999px;
    background: var(--well);
    overflow: hidden;
  }
  .progress i {
    display: block;
    height: 100%;
    width: 0%;
    border-radius: 999px;
    background: var(--accent);
    transition: width 240ms cubic-bezier(0.23, 1, 0.32, 1);
  }

  .status {
    margin: 14px 0 0;
    text-align: center;
    font-size: 13px;
    line-height: 1.45;
    color: var(--ink-small);
    text-wrap: pretty;
  }
  .status.is-error { color: #A8635C; }
  .status.is-done { color: #4E7A5F; font-weight: 600; }

  /* ── Your shots ───────────────────────────────────────────────────────── */
  .empty {
    margin: auto;
    padding: 40px 20px;
    text-align: center;
    color: var(--ink-small);
  }
  .empty svg { width: 30px; height: 30px; stroke: #C4B4B9; margin-bottom: 14px; }
  .empty p { margin: 0; font-size: 13.5px; line-height: 1.5; text-wrap: pretty; }
  .empty p b { display: block; font-weight: 600; color: var(--ink); margin-bottom: 3px; }

  .mine { margin-top: 26px; }
  .mine h2 {
    margin: 0 0 4px;
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--ink-small);
  }
  .mine .note {
    margin: 0 0 12px;
    font-size: 12px;
    line-height: 1.45;
    color: var(--ink-small);
  }
  .grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
  }
  .grid figure {
    margin: 0;
    position: relative;
    aspect-ratio: 1;
    border-radius: 12px;
    overflow: hidden;
    background: var(--well);
  }
  .grid img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .grid figure.is-failed::after {
    content: "";
    position: absolute;
    inset: 0;
    background: rgba(208, 138, 131, 0.45);
  }
  .grid .badge {
    position: absolute;
    right: 6px;
    bottom: 6px;
    width: 20px;
    height: 20px;
    border-radius: 999px;
    background: rgba(46, 36, 41, 0.66);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .grid .badge svg { width: 10px; height: 10px; fill: #fff; stroke: none; }

  .sig {
    margin: 26px 0 0;
    text-align: center;
    font-family: Sacramento, cursive;
    font-size: 22px;
    line-height: 1;
    color: var(--ink-small);
  }

  [hidden] { display: none !important; }

  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { transition-duration: 0.01ms !important; }
  }
</style>
</head>
<body>

<main class="shell">
  <div class="hero">
    <img src="{{ $coverUrl }}" alt="">

    <a class="back" href="{{ $inviteUrl }}" aria-label="Back to the invitation">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
    </a>

    <div class="hero-body">
      <h1>{{ $event->name }}</h1>

      <div class="stats">
        <div class="stat">
          <b id="stat-moments">{{ $photoCount }}</b>
          <span>{{ $photoCount === 1 ? 'Moment' : 'Moments' }}</span>
        </div>
        <div class="stat">
          <b>{{ $event->event_date?->format('M j') ?? '—' }}</b>
          <span>{{ $open ? 'Live' : 'Ended' }}</span>
        </div>
        <div class="stat">
          <b>{{ $guestCount }}</b>
          <span>{{ $guestCount === 1 ? 'Person' : 'People' }}</span>
        </div>
      </div>
    </div>

    <div class="bar">
      {{-- No `capture` attribute on purpose: it would force the camera and take
           away the photo library, and half the shots worth adding were taken
           ten minutes ago. iOS offers both from this. --}}
      <input id="picker" type="file" accept="image/*,video/*" multiple hidden>

      <button type="button" id="add" {{ $open ? '' : 'disabled' }}>
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5A2 2 0 0 1 5 6.5h1.6l1.2-2h8.4l1.2 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><circle cx="12" cy="13" r="3.4"/></svg>
        <span id="add-label">{{ $open ? 'Add photos' : 'Closed' }}</span>
      </button>

      <button type="button" id="share">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7"/><path d="M16 6l-4-4-4 4"/><path d="M12 2v14"/></svg>
        <span id="share-label">Share</span>
      </button>
    </div>
  </div>

  <div class="body">
    <p class="who" id="who" hidden></p>

    <div class="banner">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3"/><path d="M12 18v3"/><path d="M4.9 4.9 7 7"/><path d="m17 17 2.1 2.1"/><path d="M3 12h3"/><path d="M18 12h3"/><path d="M4.9 19.1 7 17"/><path d="M17 7l2.1-2.1"/><circle cx="12" cy="12" r="3.2"/></svg>
      <p>
        @if ($open)
          {{ $revealNote }} Nobody sees them as they come in — not even {{ $host ?? 'the host' }}.
        @else
          This album has stopped taking photos. Everything added before it closed
          is safe with {{ $host ?? 'the host' }}.
        @endif
      </p>
    </div>

    <div class="progress" id="progress" hidden><i id="progress-bar"></i></div>
    <p class="status" id="status" hidden></p>

    <div class="empty" id="empty">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5A2 2 0 0 1 5 6.5h1.6l1.2-2h8.4l1.2 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><circle cx="12" cy="13" r="3.4"/></svg>
      <p>
        <b>{{ $open ? "You're in." : 'The album is closed.' }}</b>
        {{ $open
            ? 'Add the shots you took tonight and they go straight into the album.'
            : 'Nothing more can be added, but everything already sent is kept.' }}
      </p>
    </div>

    <section class="mine" id="mine" hidden>
      <h2 id="mine-title">Your photos</h2>
      <p class="note" id="mine-note"></p>
      <div class="grid" id="grid"></div>
    </section>

    <p class="sig">everly</p>
  </div>
</main>

<script>
(function () {
  "use strict";

  var UPLOAD_URL = @json($uploadUrl);
  var PHOTOS_URL = @json($photosUrl);
  var INVITE_URL = @json($inviteUrl);
  var MAX_BYTES = {{ $maxUploadKb }} * 1024;
  var STORE_KEY = "everly.guest.{{ $token }}";
  var OPEN = @json($open);
  var PER_GUEST = @json($shotLimit);          // null = unlimited
  var PARTICIPANT_FULL = @json($participantFull);

  var picker = document.getElementById("picker");
  var add = document.getElementById("add");
  var addLabel = document.getElementById("add-label");
  var share = document.getElementById("share");
  var shareLabel = document.getElementById("share-label");
  var progress = document.getElementById("progress");
  var bar = document.getElementById("progress-bar");
  var status = document.getElementById("status");
  var empty = document.getElementById("empty");
  var mine = document.getElementById("mine");
  var mineTitle = document.getElementById("mine-title");
  var mineNote = document.getElementById("mine-note");
  var grid = document.getElementById("grid");
  var who = document.getElementById("who");
  var statMoments = document.getElementById("stat-moments");

  /* The guest's identity across uploads. The server issues `guest_token` on the
     first upload and counts every later one against it, so losing it would
     restart the guest's shot allowance *and* split their photos into two
     anonymous strangers in the host's gallery. Private mode and blocked site
     data both throw here — the page still has to work, the guest just becomes
     a new one if they reload. */
  var saved = { name: "", token: null };
  try {
    var raw = localStorage.getItem(STORE_KEY);
    if (raw) saved = JSON.parse(raw) || saved;
  } catch (e) { /* no-op */ }

  /* The name is collected on the invitation, so arriving here without one means
     the guest deep-linked past it (or cleared their storage). Send them back
     rather than letting them upload as nobody. */
  if (!saved.name) {
    window.location.replace(INVITE_URL);
    return;
  }

  /* Photos this guest may still send. Starts at the event's allowance and is
     corrected by the server: the count that matters lives against the guest
     token, not in this tab, so a guest who already sent five from another
     browser session must not be told they have ten. */
  var remaining = PER_GUEST;

  var leftEl = document.createElement("span");
  leftEl.className = "left";

  who.innerHTML = "";
  who.appendChild(document.createTextNode("Adding as "));
  var strong = document.createElement("strong");
  strong.textContent = saved.name;
  who.appendChild(strong);
  who.appendChild(document.createTextNode(" · "));
  who.appendChild(leftEl);
  who.appendChild(document.createTextNode(" · "));
  var changeLink = document.createElement("a");
  changeLink.href = INVITE_URL;
  changeLink.textContent = "not you?";
  who.appendChild(changeLink);
  who.hidden = false;

  function renderLeft() {
    if (remaining === null) {
      leftEl.textContent = "unlimited photos";
      leftEl.className = "left";
      return;
    }
    leftEl.textContent = remaining === 0
      ? "no photos left"
      : remaining + " of " + PER_GUEST + " left";
    leftEl.className = "left" + (remaining === 0 ? " is-out" : (remaining <= 2 ? " is-low" : ""));

    if (OPEN) {
      add.disabled = remaining === 0;
      if (remaining === 0) addLabel.textContent = "No photos left";
    }
  }

  renderLeft();

  function remember() {
    try {
      localStorage.setItem(STORE_KEY, JSON.stringify(saved));
    } catch (e) { /* no-op */ }
  }

  function say(text, kind) {
    status.textContent = text;
    status.className = "status" + (kind ? " is-" + kind : "");
    status.hidden = !text;
  }

  function busy(on) {
    add.disabled = on || !OPEN;
    progress.hidden = !on;
    if (!on) bar.style.width = "0%";
  }

  /* Copying the link is the one thing a guest can usefully hand to another
     guest, so it lives here rather than on the invitation, where the person
     reading it had just been given the link already. */
  share.addEventListener("click", function () {
    var url = INVITE_URL;
    var done = function () {
      shareLabel.textContent = "Copied";
      setTimeout(function () { shareLabel.textContent = "Share"; }, 1800);
    };
    if (navigator.share) {
      navigator.share({ title: document.title, url: url }).catch(function () { /* dismissed */ });
      return;
    }
    if (navigator.clipboard) {
      navigator.clipboard.writeText(url).then(done).catch(function () {
        shareLabel.textContent = "Copy failed";
      });
    }
  });

  /* ── The guest's own shots ────────────────────────────────────────────────
     Only ever theirs. `GET .../photos` filters on their guest id and there is
     no endpoint that would return anyone else's — the album stays sealed until
     the reveal, and even after it there is no guest gallery. `revealed` only
     changes what the caption promises about who else can see them. */
  var mineCount = 0;
  var revealed = null;

  function tile(src, isVideo) {
    var figure = document.createElement("figure");
    if (src) {
      var img = document.createElement("img");
      img.src = src;
      img.alt = "";
      img.loading = "lazy";
      figure.appendChild(img);
    }
    if (isVideo) {
      var badge = document.createElement("span");
      badge.className = "badge";
      var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
      svg.setAttribute("viewBox", "0 0 24 24");
      var path = document.createElementNS("http://www.w3.org/2000/svg", "path");
      path.setAttribute("d", "M8 5v14l11-7z");
      svg.appendChild(path);
      badge.appendChild(svg);
      figure.appendChild(badge);
    }
    return figure;
  }

  function renderMine() {
    var has = grid.childElementCount > 0;
    mine.hidden = !has;
    empty.hidden = has;
    mineTitle.textContent = mineCount === 1 ? "Your photo" : "Your " + mineCount + " photos";
    mineNote.textContent = revealed === false
      ? "Only you can see these until the reveal."
      : "These are yours — nobody else's photos show here.";
  }

  /* A local preview for a file being sent right now, so the grid fills as the
     uploads land rather than only after a reload. Prepended, because the server
     returns newest first and the two lists have to agree. */
  function preview(file) {
    var isVideo = /^video\//.test(file.type);
    var isImage = /^image\//.test(file.type);
    var figure = tile(isImage ? URL.createObjectURL(file) : null, isVideo);

    var img = figure.querySelector("img");
    if (img) img.addEventListener("load", function () { URL.revokeObjectURL(img.src); });

    grid.insertBefore(figure, grid.firstChild);
    mine.hidden = false;
    empty.hidden = true;
    return figure;
  }

  /* What they sent before this page load. Without this the grid is empty on
     every refresh — the client holds nothing but an opaque token, so the
     server is the only place that knows what this guest contributed. */
  if (saved.token) {
    fetch(PHOTOS_URL, {
      headers: { "Accept": "application/json", "X-Guest-Token": saved.token }
    })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (body) {
        if (!body || !body.photos) return;
        revealed = body.revealed;
        body.photos.forEach(function (photo) {
          grid.appendChild(tile(photo.thumbnail_url || photo.url, photo.media_type === "video"));
          mineCount++;
        });
        renderMine();
      })
      .catch(function () { /* the grid stays empty; nothing else breaks */ });
  }

  if (!OPEN) return;

  /* Ask the server what this guest has left. Only worth a request once there is
     a token to ask about — without one the guest has uploaded nothing anywhere,
     and the event's full allowance is already the right answer. */
  if (saved.token) {
    fetch(UPLOAD_URL, {
      headers: { "Accept": "application/json", "X-Guest-Token": saved.token }
    })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (body) {
        if (!body || !body.event) return;
        if (typeof body.event.shotLimit !== "undefined") {
          remaining = body.event.shotLimit;
          renderLeft();
        }
      })
      .catch(function () { /* the counter stays optimistic; the server still refuses */ });
  } else if (PARTICIPANT_FULL) {
    /* A brand-new guest at an event that has run out of participant slots will
       be refused on their first upload. Better to say so now than after they
       have picked photos and watched them fail one by one. */
    add.disabled = true;
    addLabel.textContent = "Album is full";
    say("This event has reached its guest limit. Ask " + @json($host ?? 'the host') + " for another slot.", "error");
  }

  add.addEventListener("click", function () { picker.click(); });

  /* Guards the picker, not just the button. `busy()` disables the button, but a
     second `change` — a re-pick, or a browser that fires the event more than
     once — would start a second upload chain running *alongside* the first.
     They then race the server's per-guest count, which is the exact thing the
     sequential chain exists to avoid. */
  var uploading = false;

  picker.addEventListener("change", function () {
    var files = Array.prototype.slice.call(picker.files || []);
    picker.value = "";

    if (!files.length || uploading) return;

    /* Trim to what the guest can actually send. The server would refuse the
       extras anyway, one 422 at a time; saying it once up front is kinder and
       spares the connection a run of doomed uploads. */
    var skipped = 0;
    if (remaining !== null && files.length > remaining) {
      skipped = files.length - remaining;
      files = files.slice(0, remaining);
    }

    if (!files.length) {
      say("You've used all " + PER_GUEST + " of your photos for this event.", "error");
      return;
    }

    send(files, skipped);
  });

  function describe(res, body) {
    if (res.status === 422) {
      var errors = body && body.errors && body.errors.photo;
      if (errors && errors[0]) return errors[0];
      return "That file was rejected.";
    }
    if (res.status === 429) {
      /* The limiter is 30/min keyed by IP, and a party is one wifi NAT — so
         this fires on the venue, not on the person. Say wait, not "you". */
      return "The album is busy right now. Wait a moment and send the rest.";
    }
    if (res.status === 413) return "That file is too large to send.";
    return "Upload failed. Check your connection and try again.";
  }


  function send(files, skipped) {
    var sent = 0;
    var failures = [];

    uploading = true;
    busy(true);

    /* Sequential, not Promise.all: a phone on venue wifi pushing ten originals
       at once times them all out together, and the server counts each upload
       against the guest's shot limit as it lands — parallel requests race that
       count. Slower, but every photo either arrives or names its reason. */
    var chain = Promise.resolve();

    files.forEach(function (file, i) {
      chain = chain.then(function () {
        bar.style.width = Math.round((i / files.length) * 100) + "%";
        say(files.length > 1
          ? "Sending " + (i + 1) + " of " + files.length + "…"
          : "Sending your photo…");

        var figure = preview(file);

        if (file.size > MAX_BYTES) {
          failures.push("“" + file.name + "” is too large.");
          if (figure) figure.classList.add("is-failed");
          return;
        }

        var form = new FormData();
        form.append("photo", file);
        form.append("guest_name", saved.name);

        var headers = { "Accept": "application/json" };
        if (saved.token) headers["X-Guest-Token"] = saved.token;

        return fetch(UPLOAD_URL, { method: "POST", body: form, headers: headers })
          .then(function (res) {
            return res.json().catch(function () { return null; }).then(function (body) {
              if (!res.ok) throw new Error(describe(res, body));
              if (body && body.guest_token && body.guest_token !== saved.token) {
                saved.token = body.guest_token;
                remember();
              }
              sent++;
              mineCount++;
              statMoments.textContent = Number(statMoments.textContent || 0) + 1;

              /* Prefer the server's number over decrementing our own: it is the
                 one the next upload will actually be judged against. */
              remaining = (body && typeof body.remaining !== "undefined")
                ? body.remaining
                : (remaining === null ? null : Math.max(0, remaining - 1));
              renderLeft();
            });
          })
          .catch(function (err) {
            failures.push(err.message || "Upload failed.");
            if (figure) figure.classList.add("is-failed");
          });
      });
    });

    chain.then(function () {
      bar.style.width = "100%";
      uploading = false;
      busy(false);
      addLabel.textContent = "Add more";
      renderMine();

      /* renderLeft owns the disabled state at zero, so it runs after busy(false)
         re-enables the button — otherwise a guest who just used their last shot
         gets an "Add more" button that only fails. */
      renderLeft();

      var tail = skipped
        ? " " + skipped + (skipped === 1 ? " photo was" : " photos were") + " left out — that's your limit for this event."
        : "";

      if (sent && !failures.length) {
        say((sent === 1 ? "Photo added. Thank you!" : sent + " photos added. Thank you!") + tail, skipped ? null : "done");
      } else if (sent) {
        say(sent + " sent. " + failures[0] + tail, "error");
      } else {
        say(failures[0] || "Nothing was sent.", "error");
      }
    });
  }
})();
</script>
</body>
</html>
