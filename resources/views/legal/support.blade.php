@extends('legal.layout')

@php($email = config('everly.support_email'))

@section('title', 'Support')

@section('content')
<h1><span class="thin">How can we</span><span class="bold">help?</span></h1>
<p class="meta">Questions about your event or a purchase? We read every email.</p>

<div class="card">
  <strong>Email us</strong><br>
  <a href="mailto:{{ $email }}">{{ $email }}</a>
</div>

<h2>Do my guests need an account?</h2>
<p>No. Guests scan the event's QR code, type a name if they want, and start shooting. Only the host signs in.</p>

<h2>When will everyone see the photos?</h2>
<p>When the host decides: instantly, when the event ends, or at a time the host schedules. Until then, nobody sees the gallery.</p>

<h2>How do I invite more guests?</h2>
<p>Every event starts with 5 guests for free. Open the event, go to its settings and add a guest pack. Packs are one-time purchases for that event; there is no subscription.</p>

<h2>I bought a guest pack but my event didn't change</h2>
<p>Close and reopen the event so it refreshes. If the guest allowance is still the same, email us the event name and the date of the purchase.</p>

<h2>How do I remove or report a photo?</h2>
<p>Hosts: open the photo in your event and tap <strong>&middot;&middot;&middot;</strong>. You can remove that photo, or remove everything that guest took and block them from adding more. To report a photo to us, choose <strong>Report Photo</strong> there, or email us the event name. We review every report within 24 hours and remove content that breaks the law or is abusive.</p>

<h2>Can I get a refund?</h2>
<p>Purchases are processed by Apple, so refunds are requested at <a href="https://reportaproblem.apple.com">reportaproblem.apple.com</a>.</p>

<h2>How do I delete my account?</h2>
<p>In the app, go to <strong>Settings &rarr; Delete Account</strong>. This deletes your account and every event you host, including their photos. See our <a href="{{ route('privacy') }}">Privacy Policy</a> for details.</p>
@endsection
