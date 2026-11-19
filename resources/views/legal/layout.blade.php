{{--
    Shell for the public legal pages (privacy, support) that the App Store
    listing links to. Self-contained like join.blade.php: no Inertia; the only script is
    PostHog (partials/analytics), loaded async and only in production.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>@yield('title') &middot; everly</title>
<meta name="theme-color" content="#FBF8F5">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=Sacramento&display=swap" rel="stylesheet">
<style>
  /* Tokens mirror the app's constants/colors.ts (and join.blade.php). */
  :root {
    color-scheme: light;
    --ground: #FBF8F5;
    --well: #F5EDEA;
    --ink: #2E2429;
    --ink-small: #6F6068;
    --accent: #E6A9B4;
    --accent-small: #9E5163;
    --hairline: rgba(46, 36, 41, 0.07);
  }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body {
    background: var(--ground);
    color: var(--ink);
    font: 400 16px/1.65 Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    -webkit-font-smoothing: antialiased;
  }
  main { max-width: 680px; margin: 0 auto; padding: 40px 24px 80px; }
  .wordmark { font-family: Sacramento, cursive; font-size: 48px; line-height: 1; text-decoration: none; color: var(--ink); }
  .wordmark span { color: var(--accent); }
  h1 { margin: 36px 0 4px; font-size: 40px; line-height: 1.1; letter-spacing: -1px; }
  h1 .thin { font-weight: 300; display: block; }
  h1 .bold { font-weight: 800; display: block; }
  .meta { color: var(--ink-small); font-size: 14px; margin: 12px 0 36px; }
  h2 { font-size: 19px; font-weight: 800; letter-spacing: -0.3px; margin: 40px 0 8px; }
  p, li { color: var(--ink); }
  ul { padding-left: 20px; }
  li { margin: 6px 0; }
  a { color: var(--accent-small); }
  .card { background: #fff; border: 1px solid var(--hairline); border-radius: 26px; padding: 22px 24px; margin: 24px 0; }
  footer { margin-top: 56px; padding-top: 20px; border-top: 1px solid var(--hairline); color: var(--ink-small); font-size: 14px; }
  footer a { margin-right: 16px; }
</style>
@include('partials.analytics')
</head>
<body>
<main>
  <a class="wordmark" href="{{ url('/') }}">ever<span>ly</span></a>
  @yield('content')
  <footer>
    <a href="{{ route('privacy') }}">Privacy Policy</a>
    <a href="{{ route('support') }}">Support</a>
  </footer>
</main>
</body>
</html>
