{{--
    Everly public landing page.
    Self-contained, no JavaScript required. Only async analytics in production.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Everly: wedding planner &amp; guest photo album for iPhone</title>
<meta name="description" content="Plan the wedding and keep every photo. Checklist, guest RSVPs, budget, timeline and a shared guest camera, in one iPhone app.">
<link rel="canonical" href="{{ url('/') }}">
<meta name="theme-color" content="#FBF8F5">
<meta name="apple-itunes-app" content="app-id=6759682374">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="Everly">
<meta property="og:title" content="Everly: wedding planner &amp; guest photo album for iPhone">
<meta property="og:description" content="Plan the wedding and keep every photo. Checklist, guest RSVPs, budget, timeline and a shared guest camera, in one iPhone app.">
<meta property="og:url" content="{{ url('/') }}">
<meta property="og:image" content="{{ url('/images/landing/og.jpg') }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Everly: wedding planner &amp; guest photo album for iPhone">
<meta name="twitter:description" content="Plan the wedding and keep every photo. Checklist, guest RSVPs, budget, timeline and a shared guest camera, in one iPhone app.">
<meta name="twitter:image" content="{{ url('/images/landing/og.jpg') }}">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght,SOFT,WONK@9..144,500,100,0&family=Inter:wght@500;600;700&family=Lexend:wght@400;500&family=Sacramento&display=swap" rel="stylesheet">

{{-- New Kansas (the app's title font) comes from Adobe Fonts, whose license
     doesn't allow self-hosting the file. Set ADOBE_FONTS_KIT to the web
     project's id to load it; until then headings fall back to Fraunces. --}}
@if (config('everly.web.adobe_fonts_kit'))
<link rel="stylesheet" href="https://use.typekit.net/{{ config('everly.web.adobe_fonts_kit') }}.css">
@endif
<link rel="preload" as="image" href="/images/landing/hero-hand.webp">
<link rel="preload" as="image" href="/images/landing/screen-plan.webp">

@include('partials.analytics')

<style>
    :root {
        color-scheme: light;
        --ground: #FBF8F5;
        --card: #FFFFFF;
        --well: #F5EDEA;
        --blush: #E6A9B4;
        --blush-light: #F4CED5;
        --rose-deep: #C77E8D;
        --ink: #2E2429;
        --ink-2: #8B7C82;
        --ink-3: #B9AAB0;
        --border: rgba(46, 36, 41, 0.07);
        --titanium: #1c1a1d;
        --radius-card: 28px;
        --shadow-soft: 0 1px 2px rgba(46, 36, 41, 0.04), 0 12px 32px rgba(46, 36, 41, 0.06);
        --shadow-lift: 0 2px 4px rgba(46, 36, 41, 0.06), 0 24px 48px rgba(46, 36, 41, 0.12);
        --font-display: 'new-kansas', 'Fraunces', Georgia, serif;
        --font-body: 'Lexend', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        --font-chrome: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        --font-script: 'Sacramento', cursive;
        --container-max: 1160px;
        --section-y-mobile: 72px;
        --section-y-desktop: 120px;
    }

    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body {
        background: var(--ground);
        color: var(--ink);
        font: 400 16px/1.65 var(--font-body);
        -webkit-font-smoothing: antialiased;
        text-rendering: optimizeLegibility;
        overflow-x: hidden;
    }

    img { max-width: 100%; height: auto; display: block; }

    a { color: var(--ink); }

    h1, h2, h3 {
        font-family: var(--font-display);
        font-weight: 500;
        font-variation-settings: 'SOFT' 100, 'WONK' 0;
        line-height: 1.1;
        letter-spacing: 0.01em;
        margin: 0;
    }

    h1 {
        font-size: clamp(42px, 9vw, 66px);
        line-height: 1.05;
    }
    h2 {
        font-size: clamp(32px, 6vw, 48px);
        line-height: 1.1;
    }
    h3 {
        font-size: 22px;
        line-height: 1.25;
    }

    p { margin: 0 0 1.2em; }

    .container {
        width: 100%;
        max-width: var(--container-max);
        margin: 0 auto;
        padding-left: 24px;
        padding-right: 24px;
    }

    .section {
        padding: var(--section-y-mobile) 0;
    }

    @media (min-width: 860px) {
        .section { padding: var(--section-y-desktop) 0; }
    }

    /* Header */
    .site-header {
        position: sticky;
        top: 0;
        z-index: 100;
        background: rgba(251, 248, 245, 0.9);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-bottom: 1px solid var(--border);
    }
    .site-header .container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 72px;
        gap: 24px;
    }

    .wordmark {
        font-family: var(--font-script);
        font-size: 34px;
        line-height: 1;
        color: var(--ink);
        text-decoration: none;
        white-space: nowrap;
    }

    .nav {
        display: none;
        align-items: center;
        gap: 28px;
        margin-left: auto;
    }
    .nav a {
        font-family: var(--font-chrome);
        font-size: 14px;
        font-weight: 500;
        color: var(--ink);
        text-decoration: none;
        transition: color 0.2s ease;
    }
    .nav a:hover { color: var(--rose-deep); }
    @media (min-width: 860px) { .nav { display: flex; } }

    .btn {
        font-family: var(--font-chrome);
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        border-radius: 999px;
        padding: 12px 24px;
        min-height: 44px;
        font-size: 15px;
        cursor: pointer;
        border: none;
        transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }
    .btn--dark {
        background: var(--ink);
        color: var(--ground);
    }
    .btn--dark:hover {
        background: #1f191d;
        box-shadow: 0 10px 24px rgba(46,36,41,0.18);
        transform: translateY(-1px);
    }
    .btn--small {
        padding: 10px 20px;
        min-height: 40px;
        font-size: 14px;
    }
    .btn-text {
        font-family: var(--font-chrome);
        font-weight: 600;
        color: var(--ink);
        text-decoration: underline;
        text-underline-offset: 5px;
        text-decoration-thickness: 1.5px;
        text-decoration-color: var(--border);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 16px;
        transition: color 0.2s;
    }
    .btn-text:hover { color: var(--rose-deep); }

    /* Store button */
    .btn-store {
        background: var(--ink);
        color: var(--ground);
        padding: 12px 22px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        gap: 14px;
        min-height: 56px;
        text-decoration: none;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .btn-store:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 32px rgba(46,36,41,0.25);
    }
    .btn-store svg {
        width: 26px;
        height: 29px;
        flex-shrink: 0;
    }
    .btn-store__text {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        line-height: 1.2;
    }
    .btn-store__small {
        font-family: var(--font-chrome);
        font-size: 12px;
        font-weight: 500;
        color: var(--blush);
    }
    .btn-store__large {
        font-family: var(--font-chrome);
        font-size: 20px;
        font-weight: 600;
    }

    /* Hero */
    .hero {
        padding: 56px 0 72px;
    }
    .hero .container {
        display: grid;
        grid-template-columns: 1fr;
        gap: 48px;
        align-items: center;
    }
    @media (min-width: 860px) {
        .hero .container {
            grid-template-columns: 1.05fr 0.95fr;
            gap: 64px;
        }
        .hero { padding: 80px 0 100px; }
    }

    .eyebrow {
        font-family: var(--font-chrome);
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--rose-deep);
        margin: 0 0 16px;
    }

    .hero__title {
        margin: 0 0 16px;
    }

    .hero__sub {
        font-size: 18px;
        line-height: 1.7;
        color: var(--ink-2);
        max-width: 28em;
        margin: 0 0 32px;
    }

    .hero__actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 20px;
    }

    .hero__media {
        position: relative;
    }
    .hero__media img {
        border-radius: var(--radius-card);
        box-shadow: var(--shadow-soft);
        width: 100%;
        height: auto;
        object-fit: cover;
    }

    .float-chip {
        position: absolute;
        background: var(--card);
        border-radius: 16px;
        box-shadow: var(--shadow-lift);
        padding: 12px 16px;
        display: flex;
        flex-direction: column;
        gap: 2px;
        z-index: 2;
    }
    .float-chip__count {
        font-family: var(--font-chrome);
        font-weight: 700;
        font-size: 18px;
        line-height: 1;
        color: var(--ink);
    }
    .float-chip__label {
        font-family: var(--font-body);
        font-size: 13px;
        color: var(--ink-2);
    }

    .hero .float-chip {
        left: -12px;
        bottom: 32px;
    }
    @media (max-width: 480px) {
        .hero .float-chip { left: 8px; bottom: -20px; }
    }

    /* Feature index */
    .feature-index {
        padding: 24px 0;
    }
    .chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }
    .chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 999px;
        padding: 10px 20px;
        font-family: var(--font-chrome);
        font-size: 14px;
        font-weight: 500;
        color: var(--ink);
        text-decoration: none;
        transition: border-color 0.2s, background 0.2s;
        min-height: 40px;
    }
    .chip:hover {
        border-color: var(--blush);
        background: var(--well);
    }

    /* Planner sections */
    .split {
        display: grid;
        grid-template-columns: 1fr;
        gap: 48px;
        align-items: center;
    }
    @media (min-width: 860px) {
        .split {
            grid-template-columns: 1fr 1fr;
            gap: 80px;
        }
        .split--flip .split__media {
            order: -1;
        }
    }

    .split__media {
        position: relative;
    }
    .split__content h2 { margin-bottom: 18px; }
    .split__content p { color: var(--ink-2); }
    .album__header h2 { margin-bottom: 4px; }

    .feature-list {
        list-style: none;
        padding: 0;
        margin: 24px 0 0;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .feature-list li {
        display: flex;
        align-items: center;
        gap: 12px;
        font-family: var(--font-body);
        font-size: 16px;
        line-height: 1.5;
        color: var(--ink);
    }
    .feature-list svg {
        width: 22px;
        height: 22px;
        flex-shrink: 0;
    }
    .feature-list .check {
        color: var(--rose-deep);
    }

    .ring-photo {
        border-radius: var(--radius-card);
        box-shadow: var(--shadow-soft);
        width: 100%;
        height: auto;
        object-fit: cover;
    }

    .photo-float-chip {
        position: absolute;
        right: -8px;
        top: 24px;
        background: var(--card);
        border-radius: 16px;
        box-shadow: var(--shadow-lift);
        padding: 10px 14px;
        font-family: var(--font-chrome);
        font-size: 13px;
        font-weight: 600;
        color: var(--ink);
        z-index: 2;
    }

    /* Budget decorative card */
    .budget-card {
        position: absolute;
        background: var(--card);
        border-radius: 20px;
        box-shadow: var(--shadow-lift);
        padding: 18px 20px;
        width: min(280px, 80%);
        z-index: 3;
        left: -16px;
        bottom: 28px;
        font-family: var(--font-chrome);
    }
    .budget-card__row {
        margin-bottom: 14px;
    }
    .budget-card__row:last-child { margin-bottom: 0; }
    .budget-card__title {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
        font-weight: 600;
        color: var(--ink);
        margin-bottom: 6px;
    }
    .budget-card__amount {
        color: var(--ink-2);
        font-weight: 500;
    }
    .mini-progress {
        height: 6px;
        background: var(--well);
        border-radius: 999px;
        overflow: hidden;
    }
    .mini-progress > span {
        display: block;
        height: 100%;
        background: var(--blush);
        border-radius: inherit;
    }

    /* iPhone frame */
    .phone {
        position: relative;
        background: var(--titanium);
        padding: 10px;
        border-radius: 48px;
        box-shadow:
            0 0 0 1px rgba(255,255,255,0.08) inset,
            0 30px 60px rgba(46,36,41,0.25),
            0 4px 8px rgba(46,36,41,0.1);
    }
    .phone::before {
        content: "";
        position: absolute;
        left: -2px;
        top: 18%;
        width: 3px;
        height: 64px;
        background: #34262d;
        border-radius: 0 2px 2px 0;
    }
    .phone::after {
        content: "";
        position: absolute;
        right: -2px;
        top: 16%;
        width: 3px;
        height: 90px;
        background: #34262d;
        border-radius: 2px 0 0 2px;
    }
    .phone__screen {
        aspect-ratio: 430 / 932;
        width: 100%;
        border-radius: 38px;
        overflow: hidden;
        background: #fff;
    }
    .phone__screen img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .phone--tilt-l { transform: rotate(-2.5deg); }
    .phone--tilt-r { transform: rotate(2deg); }

    .phone--sm { max-width: 240px; }
    .phone--md { max-width: 300px; margin: 0 auto; }
    .phone--lg { max-width: 340px; margin: 0 auto; }

    @media (min-width: 640px) {
        .phone--hero { max-width: 380px; }
    }

    /* Album band */
    .album-band {
        background: var(--well);
        padding: var(--section-y-mobile) 0;
    }
    @media (min-width: 860px) {
        .album-band { padding: var(--section-y-desktop) 0; }
    }

    .album__header {
        text-align: center;
        max-width: 620px;
        margin: 0 auto 48px;
    }

    .album__phones {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 64px;
        padding: 0 16px;
    }
    .album__phones .phone:first-child {
        transform: rotate(-4deg);
        z-index: 1;
    }
    .album__phones .phone {
        width: 46%;
        max-width: 270px;
    }
    .album__phones .phone:last-child {
        transform: rotate(3deg) translateY(28px);
        margin-left: -18px;
    }
    @media (min-width: 640px) {
        .album__phones .phone:last-child { margin-left: -28px; }
    }

    .steps-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 32px;
    }
    @media (min-width: 680px) {
        .steps-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 40px;
        }
    }
    .step {
        background: var(--card);
        border-radius: var(--radius-card);
        box-shadow: var(--shadow-soft);
        padding: 28px;
    }
    .step__number {
        font-family: var(--font-chrome);
        font-weight: 700;
        font-size: 12px;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--rose-deep);
        margin-bottom: 12px;
    }
    .step h3 {
        font-size: 20px;
        margin-bottom: 8px;
    }
    .step p {
        font-size: 15px;
        color: var(--ink-2);
        margin: 0;
    }

    /* Comparison */
    .comparison-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 24px;
        margin-top: 48px;
    }
    @media (min-width: 720px) {
        .comparison-grid {
            grid-template-columns: 1fr 1fr;
            gap: 32px;
        }
    }
    .comparison-card {
        background: var(--card);
        border-radius: var(--radius-card);
        box-shadow: var(--shadow-soft);
        padding: 32px;
        border: 1px solid var(--border);
    }
    .comparison-card h3 {
        font-size: 26px;
        margin-bottom: 20px;
    }
    .comparison-card ul {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .comparison-card li {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: 15px;
        line-height: 1.5;
        color: var(--ink);
    }
    .comparison-card svg {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
        margin-top: 2px;
        color: var(--rose-deep);
    }
    .comparison-note {
        font-size: 14px;
        color: var(--ink-2);
        margin-top: 20px;
        line-height: 1.6;
    }

    /* FAQ */
    .faq {
        max-width: 760px;
        margin: 0 auto;
    }
    .faq__item {
        border-bottom: 1px solid var(--border);
    }
    .faq__item summary {
        list-style: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 24px 0;
        font-family: var(--font-chrome);
        font-weight: 600;
        font-size: 17px;
        color: var(--ink);
    }
    .faq__item summary::-webkit-details-marker { display: none; }
    .faq__icon {
        position: relative;
        width: 20px;
        height: 20px;
        flex-shrink: 0;
    }
    .faq__icon::before,
    .faq__icon::after {
        content: "";
        position: absolute;
        background: var(--rose-deep);
        border-radius: 2px;
        transition: transform 0.2s ease;
        top: 50%;
        left: 50%;
    }
    .faq__icon::before {
        width: 14px;
        height: 2px;
        transform: translate(-50%, -50%);
    }
    .faq__icon::after {
        width: 2px;
        height: 14px;
        transform: translate(-50%, -50%);
    }
    .faq__item[open] .faq__icon::after {
        transform: translate(-50%, -50%) scaleY(0);
    }
    .faq__item p {
        margin: 0;
        padding: 0 0 24px;
        color: var(--ink-2);
        font-size: 16px;
        line-height: 1.7;
    }

    /* Final CTA */
    .final-cta {
        background: var(--well);
        padding: var(--section-y-mobile) 0;
        text-align: center;
    }
    @media (min-width: 860px) {
        .final-cta { padding: var(--section-y-desktop) 0; }
    }
    .final-cta h2 {
        max-width: 620px;
        margin: 0 auto 20px;
    }
    .final-cta p {
        color: var(--ink-2);
        max-width: 520px;
        margin: 0 auto 32px;
    }
    .final-cta .btn-store {
        margin: 0 auto;
    }

    /* Footer */
    footer {
        padding: 48px 0 56px;
        border-top: 1px solid var(--border);
    }
    .footer-inner {
        display: flex;
        flex-direction: column;
        gap: 24px;
        align-items: flex-start;
    }
    @media (min-width: 640px) {
        .footer-inner {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
        }
    }
    .footer-links {
        display: flex;
        flex-wrap: wrap;
        gap: 16px 24px;
    }
    .footer-links a {
        font-family: var(--font-chrome);
        font-size: 14px;
        color: var(--ink-2);
        text-decoration: none;
        transition: color 0.2s;
    }
    .footer-links a:hover { color: var(--rose-deep); }
    .footer-copy {
        font-size: 14px;
        color: var(--ink-2);
    }

    /* Focus */
    :focus-visible {
        outline: 2px solid var(--rose-deep);
        outline-offset: 3px;
    }

    /* Motion */
    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(12px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .hero__content > * {
        animation: fadeUp 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    .hero__content > *:nth-child(1) { animation-delay: 0.05s; }
    .hero__content > *:nth-child(2) { animation-delay: 0.1s; }
    .hero__content > *:nth-child(3) { animation-delay: 0.15s; }
    .hero__content > *:nth-child(4) { animation-delay: 0.2s; }

    @media (prefers-reduced-motion: reduce) {
        .hero__content > * {
            animation: none;
        }
        .btn-store,
        .btn,
        .chip,
        .faq__icon::before,
        .faq__icon::after {
            transition: none;
        }
    }
</style>
</head>
<body>
<header class="site-header">
    <div class="container">
        <a class="wordmark" href="{{ url('/') }}">everly</a>
        <nav class="nav" aria-label="Primary navigation">
            <a href="#planner">Planner</a>
            <a href="#album">Album</a>
            <a href="#faq">FAQ</a>
        </nav>
        <a class="btn btn--dark btn--small" href="{{ route('store-link', ['utm_source' => 'website', 'utm_medium' => 'landing', 'utm_campaign' => 'header']) }}">Get the app</a>
    </div>
</header>

<main>
    <!-- Hero -->
    <section class="hero">
        <div class="container">
            <div class="hero__content">
                <p class="eyebrow">The wedding planner with a guest camera</p>
                <h1 class="hero__title">Plan the wedding.<br>Keep every photo.</h1>
                <p class="hero__sub">The calm planning app with your checklist, guest list, budget and timeline in one place. Plus a shared album your guests fill from their phones.</p>
                <div class="hero__actions">
                    <a class="btn-store" href="{{ route('store-link', ['utm_source' => 'website', 'utm_medium' => 'landing', 'utm_campaign' => 'hero']) }}">
                        <svg viewBox="0 0 384 512" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M318.7 268.7c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.7C63.3 141.2 4 184.8 4 273.5q0 39.3 14.4 81.2c12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-61.7-90-61.7-91.9zm-56.6-164.2c27.3-32.4 24.8-61.9 24-72.5-24.1 1.4-52 16.4-67.9 34.9-17.5 19.8-27.8 44.3-25.6 71.9 26.1 2 49.9-11.4 69.5-34.3z"/>
                        </svg>
                        <span class="btn-store__text">
                            <span class="btn-store__small">Download on the</span>
                            <span class="btn-store__large">App Store</span>
                        </span>
                    </a>
                    <a class="btn-text" href="#planner">See how it works <span aria-hidden="true">↓</span></a>
                </div>
            </div>
            <div class="hero__media">
                <img src="/images/landing/hero-hand.webp" alt="Hand holding an iPhone showing the Everly Plan screen, in a warm bedroom at night." width="1200" height="1600" decoding="async">
                <div class="float-chip" aria-hidden="true">
                    <span class="float-chip__count">205 days to go</span>
                    <span class="float-chip__label">Your wedding</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Feature index -->
    <section class="feature-index">
        <div class="container">
            <div class="chip-row" aria-label="App features">
                <a class="chip" href="#planner">Checklist</a>
                <a class="chip" href="#guests">Guests</a>
                <a class="chip" href="#budget">Budget</a>
                <a class="chip" href="#timeline">Timeline</a>
                <a class="chip" href="#album">Shared album</a>
            </div>
        </div>
    </section>

    <!-- Planner sections -->
    <section class="section" id="planner">
        <div class="container">
            <div class="split">
                <div class="split__content">
                    <p class="eyebrow">Plan</p>
                    <h2>A checklist that knows what&rsquo;s next.</h2>
                    <p>Everly builds a countdown, overall progress and tasks grouped by phase, so you always know what to do next.</p>
                    <ul class="feature-list">
                        <li>
                            <svg class="check" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Countdown and overall progress
                        </li>
                        <li>
                            <svg class="check" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Up next tasks, always in reach
                        </li>
                        <li>
                            <svg class="check" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Checklist organised by time before the wedding
                        </li>
                    </ul>
                </div>
                <div class="split__media">
                    <div class="phone phone--md">
                        <div class="phone__screen">
                            <img src="/images/landing/screen-plan.webp" alt="Everly plan and checklist screen." width="1290" height="2796" loading="lazy" decoding="async">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="guests">
        <div class="container">
            <div class="split split--flip">
                <div class="split__content">
                    <p class="eyebrow">Guests</p>
                    <h2>Know who is coming, what they eat, and who they bring.</h2>
                    <p>Keep one guest list with RSVP status, meal choices, plus-ones and groups. Import from contacts or export to PDF when you need it.</p>
                    <ul class="feature-list">
                        <li>
                            <svg class="check" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            RSVP status and meal choices
                        </li>
                        <li>
                            <svg class="check" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Plus-ones and guest groups
                        </li>
                        <li>
                            <svg class="check" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Import from contacts, export to PDF
                        </li>
                    </ul>
                </div>
                <div class="split__media">
                    <img class="ring-photo" src="/images/landing/ring-hand.webp" alt="Hand with an engagement ring holding an iPhone showing the Everly Guests screen." width="1200" height="1600" loading="lazy" decoding="async">
                    <div class="photo-float-chip" aria-hidden="true">74 attending &middot; 45 awaiting</div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="budget">
        <div class="container">
            <div class="split">
                <div class="split__content">
                    <p class="eyebrow">Budget</p>
                    <h2>See where the money goes, before it goes.</h2>
                    <p>Track your total budget, spent and remaining, with per-category progress and an expense log for every vendor and payment.</p>
                    <ul class="feature-list">
                        <li>
                            <svg class="check" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Total budget, spent and remaining
                        </li>
                        <li>
                            <svg class="check" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Per-category bars and donut chart
                        </li>
                        <li>
                            <svg class="check" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Expense log by vendor and payment
                        </li>
                    </ul>
                </div>
                <div class="split__media">
                    <div class="phone phone--md phone--tilt-l">
                        <div class="phone__screen">
                            <img src="/images/landing/screen-budget.webp" alt="Everly budget tracker screen." width="1290" height="2796" loading="lazy" decoding="async">
                        </div>
                    </div>
                    <div class="budget-card" aria-hidden="true">
                        <div class="budget-card__row">
                            <div class="budget-card__title">
                                <span>Venue</span>
                                <span class="budget-card__amount">$8,500 / $9,000</span>
                            </div>
                            <div class="mini-progress"><span style="width: 94%"></span></div>
                        </div>
                        <div class="budget-card__row">
                            <div class="budget-card__title">
                                <span>Catering</span>
                                <span class="budget-card__amount">$5,200 / $9,600</span>
                            </div>
                            <div class="mini-progress"><span style="width: 54%"></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="timeline">
        <div class="container">
            <div class="split split--flip">
                <div class="split__content">
                    <p class="eyebrow">Timeline</p>
                    <h2>Every task, counted back from the day.</h2>
                    <p>See the whole plan by phase, from a year out to the final weeks, and exactly what is due right now.</p>
                    <ul class="feature-list">
                        <li>
                            <svg class="check" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Tasks grouped by months before the wedding
                        </li>
                        <li>
                            <svg class="check" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Filter by to do, done or essentials
                        </li>
                        <li>
                            <svg class="check" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Due dates that count back from your date
                        </li>
                    </ul>
                </div>
                <div class="split__media">
                    <div class="phone phone--md">
                        <div class="phone__screen">
                            <img src="/images/landing/screen-timeline.webp" alt="Everly timeline screen with tasks grouped by months before the wedding." width="1290" height="2796" loading="lazy" decoding="async">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Album band -->
    <section class="album-band" id="album">
        <div class="container">
            <div class="album__header">
                <p class="eyebrow">Shared album</p>
                <h2>Your guests are the second photographer.</h2>
                <p style="max-width: 560px; margin: 16px auto 0;">Set up a shared guest camera. Guests scan a QR, shoot a limited roll with one shared filter, and the album stays hidden until you reveal it.</p>
            </div>
            <div class="album__phones">
                <div class="phone phone--sm phone--tilt-l">
                    <div class="phone__screen">
                        <img src="/images/landing/screen-qr.webp" alt="Everly event QR code screen." width="1290" height="2796" loading="lazy" decoding="async">
                    </div>
                </div>
                <div class="phone phone--sm phone--tilt-r">
                    <div class="phone__screen">
                        <img src="/images/landing/screen-album.webp" alt="Everly shared photo album screen." width="1290" height="2796" loading="lazy" decoding="async">
                    </div>
                </div>
            </div>
            <div class="steps-grid">
                <div class="step">
                    <div class="step__number">1</div>
                    <h3>Share the QR</h3>
                    <p>Guests scan a QR code at the party. No accounts needed.</p>
                </div>
                <div class="step">
                    <div class="step__number">2</div>
                    <h3>Everyone shoots</h3>
                    <p>Each guest gets a limited roll of shots with one shared filter.</p>
                </div>
                <div class="step">
                    <div class="step__number">3</div>
                    <h3>Reveal it together</h3>
                    <p>Nobody sees the photos until you open the album. Then they are all in one place.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Free vs Premium -->
    <section class="section" id="pricing">
        <div class="container">
            <div class="album__header" style="margin-bottom: 0;">
                <p class="eyebrow">Free vs Premium</p>
                <h2>Start free. Add more when you need it.</h2>
            </div>
            <div class="comparison-grid">
                <div class="comparison-card">
                    <h3>Free</h3>
                    <ul>
                        <li>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Plan tab with countdown, progress and up-next tasks
                        </li>
                        <li>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Checklist organised by phase
                        </li>
                        <li>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Shared wedding album
                        </li>
                    </ul>
                    <p class="comparison-note">Download is free.</p>
                </div>
                <div class="comparison-card">
                    <h3>Premium</h3>
                    <ul>
                        <li>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Guest list with RSVPs, menus and groups
                        </li>
                        <li>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Import guests from contacts and export to PDF
                        </li>
                        <li>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Budget tracker for every vendor and payment
                        </li>
                        <li>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="10" cy="10" r="9" opacity="0.3"/>
                                <path d="M6 10.5l2.5 2.5L14 7"/>
                            </svg>
                            Timeline of every task by date
                        </li>
                    </ul>
                    <p class="comparison-note">Premium is an optional in-app subscription.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="section" id="faq">
        <div class="container">
            <div class="album__header" style="margin-bottom: 40px;">
                <p class="eyebrow">FAQ</p>
                <h2>Questions, answered.</h2>
            </div>
            <div class="faq">
                <details class="faq__item">
                    <summary>Is Everly free? <span class="faq__icon" aria-hidden="true"></span></summary>
                    <p>Yes. Download is free. The Plan tab, countdown, progress, checklist, and shared wedding album are free. Premium is an optional in-app subscription for guests, budget, timeline, and import and export.</p>
                </details>
                <details class="faq__item">
                    <summary>Do my guests need to download the app? <span class="faq__icon" aria-hidden="true"></span></summary>
                    <p>No. Guests scan the QR or open the invite link and can add photos straight from their phone&rsquo;s browser, with no account and no sign-up. Guests who have the app can also shoot with the shared event camera.</p>
                </details>
                <details class="faq__item">
                    <summary>Can we use it for engagement parties, showers or rehearsal dinners? <span class="faq__icon" aria-hidden="true"></span></summary>
                    <p>Yes. The shared album works for any event. Each event has its own QR code and guest limit, so you can create one for each celebration if you like.</p>
                </details>
                <details class="faq__item">
                    <summary>Is it on Android? <span class="faq__icon" aria-hidden="true"></span></summary>
                    <p>Everly is iPhone only for now. We built the iOS app first and may consider Android later.</p>
                </details>
                <details class="faq__item">
                    <summary>Who sees our photos? <span class="faq__icon" aria-hidden="true"></span></summary>
                    <p>Only people in the event. Nobody sees anything until you reveal the album. You can also choose whether guests see every photo or only their own.</p>
                </details>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="final-cta">
        <div class="container">
            <h2>Your wedding, planned.<br>Your photos, together.</h2>
            <p>Download Everly free and start your plan and your shared album today.</p>
            <a class="btn-store" href="{{ route('store-link', ['utm_source' => 'website', 'utm_medium' => 'landing', 'utm_campaign' => 'final']) }}">
                <svg viewBox="0 0 384 512" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M318.7 268.7c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.7C63.3 141.2 4 184.8 4 273.5q0 39.3 14.4 81.2c12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-61.7-90-61.7-91.9zm-56.6-164.2c27.3-32.4 24.8-61.9 24-72.5-24.1 1.4-52 16.4-67.9 34.9-17.5 19.8-27.8 44.3-25.6 71.9 26.1 2 49.9-11.4 69.5-34.3z"/>
                </svg>
                <span class="btn-store__text">
                    <span class="btn-store__small">Download on the</span>
                    <span class="btn-store__large">App Store</span>
                </span>
            </a>
        </div>
    </section>
</main>

<footer>
    <div class="container">
        <div class="footer-inner">
            <div class="footer-brand">
                <a class="wordmark" href="{{ url('/') }}">everly</a>
                <p class="footer-copy" style="margin: 8px 0 0;">Made for couples who&rsquo;d rather be planning than spreadsheeting.</p>
            </div>
            <div class="footer-links">
                <a href="{{ route('privacy') }}">Privacy Policy</a>
                <a href="{{ route('support') }}">Support</a>
            </div>
            <p class="footer-copy">&copy; {{ date('Y') }} Everly</p>
        </div>
    </div>
</footer>
</body>
</html>
