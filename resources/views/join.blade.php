{{--
    The guest invite page — what a link shared outside the app lands on.

    Deliberately NOT the Inertia layout: a guest arriving here has no session,
    no React bundle worth downloading, and is usually on a phone at a party on
    bad wifi. It is one self-contained document, with no JavaScript at all.

    Server-rendered on purpose. The invite is pasted into WhatsApp and iMessage
    far more often than it is typed, and those unfurlers do not run JavaScript —
    a client-fetched title would unfurl as a generic placeholder every time.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $event->name }} &middot; everly</title>
<meta name="theme-color" content="#FBF8F5">
<meta name="robots" content="noindex, nofollow">

<meta property="og:type" content="website">
<meta property="og:title" content="{{ $host ? "You're invited by {$host}" : "You're invited" }}">
<meta property="og:description" content="{{ $event->name }} — add your photos to the album on Everly.">
<meta property="og:image" content="{{ $coverUrl }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta name="twitter:card" content="summary_large_image">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@600;700&family=Sacramento&display=swap" rel="stylesheet">

<style>
  /* ── Tokens ────────────────────────────────────────────────────────────────
     Mirrors the mobile app's constants/colors.ts. Light-only on purpose: the
     blush palette has no dark counterpart, and a prefers-color-scheme block
     would have to invert the accent into a colour the brand doesn't own. */
  :root {
    color-scheme: light;
    --ground: #FBF8F5;
    --well: #F5EDEA;
    --ink: #2E2429;

    /* The app's --ink-2 / --ink-3 / --accent-deep are tuned for 14px+ on a
       phone held close. At the 12–13px this page uses they measure 3.7:1,
       2.1:1 and 2.9:1 on the ivory ground — all under the 4.5:1 AA needs, and
       this page gets read outdoors at a party. Same hues carried down in
       lightness until they pass: 5.6:1 and 5.2:1. */
    --ink-small: #6F6068;
    --accent-small: #9E5163;

    --accent: #E6A9B4;
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

  /* Phone-shaped column, centred on desktop. This link is opened on a phone
     essentially always — desktop just shouldn't look broken. */
  .shell {
    max-width: 480px;
    margin: 0 auto;
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    background: var(--ground);
  }

  /* ── Hero ─────────────────────────────────────────────────────────────────
     Two thirds of the screen, and the photo carries it. */
  .hero {
    position: relative;
    height: clamp(280px, 62dvh, 600px);
    flex: none;
    background: var(--well);
  }
  .hero img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    /* Slightly warmed so a cool-toned event cover still sits in the palette. */
    filter: saturate(0.94) contrast(1.02);
  }

  /* The dissolve, measured from the bottom rather than as a percentage of the
     hero. Two things this has to get right, both learned the hard way:

     It ends on *fully* opaque ground two pixels before the image's own edge.
     A near-miss — 92%, or a last stop that lands exactly on the edge — leaves
     the image's boundary showing as a hairline across the page.

     And it is 140px, not 40% of the hero. As a percentage it scaled with the
     photo and swallowed the subject, so the one thing worth looking at was the
     one thing half dissolved. Fixed from the bottom, it only ever eats the
     grass under the couple's feet.

     The many stops are the point, not clutter. Four stops left a visible band:
     over a dark cover, an 0.88 veil still reads as grey, so the short run from
     there to solid ground concentrated the whole remaining change into ~16px
     and the eye caught it as an edge. These approximate an ease, keeping the
     last leg (0.972 → solid) under half a level of lightness per pixel. */
  .hero::after {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(
      to bottom,
      rgba(251, 248, 245, 0)     calc(100% - 140px),
      rgba(251, 248, 245, 0.10)  calc(100% - 116px),
      rgba(251, 248, 245, 0.30)  calc(100% - 92px),
      rgba(251, 248, 245, 0.55)  calc(100% - 70px),
      rgba(251, 248, 245, 0.76)  calc(100% - 50px),
      rgba(251, 248, 245, 0.90)  calc(100% - 32px),
      rgba(251, 248, 245, 0.972) calc(100% - 15px),
      var(--ground) calc(100% - 3px),
      var(--ground) 100%
    );
  }

  /* Sits above the dissolve, on photo that is still ~60% visible, so it reads
     as pinned to the image. It used to straddle the photo's bottom edge, which
     was the right detail for a hard cut — with a gradient there is no edge to
     straddle, and down there it would just be a dark pill floating on ivory.

     Dark translucent rather than white frosted: an event cover can be a bright
     sky as easily as a dark forest, and only the dark fill stays legible over
     both. */
  .host {
    position: absolute;
    left: 50%;
    bottom: 78px;
    transform: translateX(-50%);
    z-index: 3;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    max-width: calc(100% - 48px);
    padding: 8px 16px;
    border-radius: 999px;
    background: rgba(46, 36, 41, 0.62);
    backdrop-filter: blur(16px) saturate(1.3);
    -webkit-backdrop-filter: blur(16px) saturate(1.3);
    box-shadow: 0 6px 18px rgba(46, 36, 41, 0.2);
    color: #fff;
    font-size: 12.5px;
    font-weight: 600;
    letter-spacing: -0.1px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .host svg { width: 13px; height: 13px; flex: none; stroke: currentColor; }

  /* ── Content ──────────────────────────────────────────────────────────── */
  /* Tighter than it looks: the hero's last 120px are already dissolving into
     this same ground, so the title's real breathing room is that plus this. */
  .content {
    padding: 22px 28px 0;
    text-align: center;
  }

  h1 {
    font-family: Lora, Georgia, serif;
    font-weight: 700;
    font-size: clamp(27px, 7.6vw, 34px);
    line-height: 1.14;
    letter-spacing: -0.5px;
    color: var(--ink);
    margin: 0;
    text-wrap: balance;
  }

  .tagline {
    font-family: Lora, Georgia, serif;
    font-weight: 600;
    font-size: 15px;
    line-height: 1.45;
    color: var(--ink-small);
    margin: 9px auto 0;
    max-width: 300px;
    text-wrap: pretty;
  }

  /* Inline text with hairline icons, not pills. As chips they read as three
     competing buttons directly above the two real ones. */
  .meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 4px 14px;
    margin-top: 13px;
    font-size: 12.5px;
    font-weight: 500;
    color: var(--ink-small);
  }
  .meta span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
  }
  .meta svg { width: 13px; height: 13px; flex: none; stroke: var(--accent-small); }
  .meta .is-closed { color: #A8635C; }
  .meta .is-closed svg { stroke: #A8635C; }

  /* The reveal is the reason to join an album rather than text the photos
     around, and nothing else on the page says it. One line — the three-step
     explainer this replaced was padding dressed up as onboarding. */
  .reveal {
    margin: 16px auto 0;
    max-width: 310px;
    font-size: 13px;
    line-height: 1.45;
    color: var(--ink-small);
    text-wrap: pretty;
  }

  /* ── Name field ───────────────────────────────────────────────────────────
     The credit printed under each photo in the host's gallery. Asked before
     the camera opens rather than after, because after is when the guest has
     what they came for and stops filling anything in. */
  .field {
    display: flex;
    align-items: center;
    gap: 10px;
    height: 54px;
    padding: 0 18px;
    border-radius: 16px;
    background: var(--well);
    border: 1px solid transparent;
    transition: border-color 160ms, background-color 160ms;
  }
  .field:focus-within { border-color: rgba(199, 126, 141, 0.55); background: #FFFFFF; }
  .field.is-missing { border-color: #C9776E; background: rgba(208, 138, 131, 0.08); }
  .field svg { width: 16px; height: 16px; flex: none; stroke: var(--accent-small); }
  .field input {
    flex: 1;
    min-width: 0;
    border: 0;
    background: none;
    outline: none;
    font-family: inherit;
    font-size: 15.5px;
    font-weight: 500;
    letter-spacing: -0.2px;
    color: var(--ink);
  }
  .field input::placeholder { color: #8A7C82; font-weight: 400; }

  /* ── Upload status ────────────────────────────────────────────────────── */
  .status {
    margin: 14px 0 0;
    text-align: center;
    font-size: 13px;
    line-height: 1.45;
    color: var(--ink-small);
    text-wrap: pretty;
  }
  .status.is-error { color: #A8635C; }

  /* ── Actions ──────────────────────────────────────────────────────────── */
  .actions {
    margin-top: auto;
    padding: 30px 20px max(20px, env(safe-area-inset-bottom));
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .btn {
    -webkit-appearance: none;
    appearance: none;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    height: 54px;
    border-radius: 999px;
    border: 1px solid transparent;
    font-family: inherit;
    font-size: 15.5px;
    font-weight: 600;
    letter-spacing: -0.2px;
    text-decoration: none;
    cursor: pointer;
    transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1);
  }
  .btn:active { transform: scale(0.978); }
  .btn svg { width: 17px; height: 17px; flex: none; }

  /* The accent is a pastel — it carries dark ink, never white. */
  .btn-primary {
    background: var(--accent);
    color: var(--ink);
    box-shadow: var(--shadow-lift);
  }
  .btn-primary.is-disabled {
    background: var(--well);
    color: var(--ink-small);
    box-shadow: none;
    pointer-events: none;
  }
  /* No fill and no shadow: on an ivory ground, white *is* a fill, and as a
     white card this weighed as much as the blush primary. It matters only to
     the guests who don't have the app — the way out, not a second offer. */
  .btn-secondary {
    background: transparent;
    color: var(--ink-small);
    border-color: rgba(46, 36, 41, 0.14);
  }

  .sig {
    margin: 18px 0 0;
    text-align: center;
    font-family: Sacramento, cursive;
    font-size: 22px;
    line-height: 1;
    color: var(--ink-small);
  }

  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { transition-duration: 0.01ms !important; }
  }
</style>
</head>
<body>

<main class="shell">
  <div class="hero">
    <img src="{{ $coverUrl }}" alt="">
    @if ($host)
      <span class="host">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Invited by {{ $host }}
      </span>
    @endif
  </div>

  <section class="content">
    <h1>{{ $event->name }}</h1>

    {{-- `title` is nullable and the API falls back to `name`, so the two are
         equal far more often than not. A subhead that repeats the headline
         reads as a bug, so it only earns its line when it says something new. --}}
    @if ($subtitle)
      <p class="tagline">{{ $subtitle }}</p>
    @endif

    <p class="meta">
      <span class="{{ $open ? '' : 'is-closed' }}">
        @if ($open)
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5A2 2 0 0 1 5 6.5h1.6l1.2-2h8.4l1.2 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><circle cx="12" cy="13" r="3.4"/></svg>
          Open for photos
        @else
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10.5" width="16" height="10.5" rx="2.5"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/></svg>
          Ended
        @endif
      </span>

      @if ($open && $event->shot_limit)
        <span>
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="3" width="13" height="13" rx="2.5"/><path d="M16 20.5a2 2 0 0 1-2 2H5.5a2 2 0 0 1-2-2V9"/></svg>
          {{ $event->shot_limit }} {{ $event->shot_limit === 1 ? 'shot' : 'shots' }} each
        </span>
      @endif

      @if ($photoCount > 0)
        <span>
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15.5 16.5 11 6 21.5"/><rect x="3" y="3" width="18" height="18" rx="2.5"/><circle cx="9" cy="9" r="1.6"/></svg>
          {{ $photoCount }} already in
        </span>
      @endif
    </p>

    <p class="reveal">
      @if ($open)
        {{ $revealNote }}
      @else
        This album has stopped taking photos. Everything added before it closed
        is safe with {{ $host ?? 'the host' }}.
      @endif
    </p>
  </section>

  <div class="actions">
    @if ($open)
      <label class="field" id="field">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
        <input id="name" type="text" placeholder="Enter your name" maxlength="40"
               autocomplete="name" autocapitalize="words" enterkeyhint="done" spellcheck="false">
      </label>

      <button class="btn btn-primary" id="cta" type="button">
        <span>Enter the album</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
      </button>

      <p class="status" id="status" hidden></p>

      {{-- Second, and quiet. The whole point of this page is that the app is
           optional now — it is here for guests who already have it, not as a
           download pitch. --}}
      <a class="btn btn-secondary" href="{{ $deepLink }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
        Open in the Everly app
      </a>
    @else
      <span class="btn btn-primary is-disabled">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10.5" width="16" height="10.5" rx="2.5"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/></svg>
        Not accepting photos
      </span>

      <a class="btn btn-secondary" href="{{ $storeUrl }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 20h16"/></svg>
        Get the app
      </a>
    @endif

    <p class="sig">everly</p>
  </div>
</main>

@if ($open)
<script>
(function () {
  "use strict";

  var ALBUM_URL = @json($albumUrl);
  var STORE_KEY = "everly.guest.{{ $token }}";

  var field = document.getElementById("field");
  var nameInput = document.getElementById("name");
  var cta = document.getElementById("cta");
  var status = document.getElementById("status");

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

  if (saved.name) nameInput.value = saved.name;

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

  nameInput.addEventListener("input", function () {
    field.classList.remove("is-missing");
  });

  function enter() {
    if (!nameInput.value.trim()) {
      /* The host's gallery credits every photo by name, so an unnamed upload
         is a photo nobody can thank anyone for. Cheap to ask once, here — and
         asking on the way in means the album screen never has to interrupt. */
      field.classList.add("is-missing");
      nameInput.focus();
      say("Add your name first — it goes on the photos you send.", "error");
      return;
    }
    saved.name = nameInput.value.trim();
    remember();
    window.location.href = ALBUM_URL;
  }

  cta.addEventListener("click", enter);

  nameInput.addEventListener("keydown", function (e) {
    if (e.key === "Enter") { e.preventDefault(); enter(); }
  });
})();
</script>
@endif
</body>
</html>
