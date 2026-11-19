{{--
    GET /app — see StoreLinkController. Shows for a split second: PostHog
    records the pageview (with utm_*) and an `app_store_click`, then the page
    hands off to the App Store. If PostHog never loads (blocked, offline,
    local), the timeout and the meta refresh still get the visitor there.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>everly: disposable wedding cam</title>
<meta name="description" content="Everyone's camera, one album. Guests scan a QR, shoot with one shared filter, and the album is revealed together.">
<meta name="theme-color" content="#FBF8F5">
<meta http-equiv="refresh" content="3;url={{ $storeUrl }}">
<style>
  :root { color-scheme: light; --ground: #FBF8F5; --ink: #2B2522; --soft: #8A7F7A; }
  * { box-sizing: border-box; }
  html, body { margin: 0; height: 100%; background: var(--ground); color: var(--ink); }
  body {
    display: flex; align-items: center; justify-content: center; padding: 24px 16px;
    font: 400 16px/1.5 -apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", sans-serif;
    text-align: center;
  }
  h1 { font-weight: 300; font-size: 28px; letter-spacing: -0.02em; margin: 0 0 8px; }
  p { color: var(--soft); margin: 0 0 24px; }
  a.button {
    display: inline-block; padding: 14px 28px; border-radius: 999px;
    background: var(--ink); color: var(--ground); text-decoration: none; font-weight: 600;
  }
</style>
@include('partials.analytics')
</head>
<body>
<main>
  <h1>everly</h1>
  <p>Opening the App Store&hellip;</p>
  <a class="button" id="store" href="{{ $storeUrl }}">Get the app</a>
</main>
<script>
(function () {
    var url = @json($storeUrl);
    var gone = false;
    function go() { if (!gone) { gone = true; window.location.replace(url); } }

    document.getElementById('store').addEventListener('click', function () { gone = true; });

    if (window.posthog && window.posthog.__SV) {
        // Queued until array.js loads; sendBeacon survives the navigation.
        window.posthog.capture('app_store_click', { destination: url }, { send_instantly: true, transport: 'sendBeacon' });
        window.posthog.onSessionId(function () { setTimeout(go, 150); });
        setTimeout(go, 1200);
    } else {
        go();
    }
})();
</script>
</body>
</html>
