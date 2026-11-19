@extends('legal.layout')

@php($email = config('everly.support_email'))

@section('title', 'Privacy Policy')

@section('content')
<h1><span class="thin">Privacy</span><span class="bold">Policy.</span></h1>
<p class="meta">Effective October 4, 2026</p>

<p>Everly turns everyone's photos from an event into one shared album that opens when the host decides. This policy explains what we collect to make that work, who can see it, and how to delete it. Questions go to <a href="mailto:{{ $email }}">{{ $email }}</a>.</p>

<h2>What we collect</h2>
<ul>
  <li><strong>Host account.</strong> When you sign in with Apple we receive an Apple user identifier and, if you choose to share them, your name and email address (which may be an Apple private relay address). We also keep a token from Apple that is used only to revoke Everly's access when you delete your account.</li>
  <li><strong>Events you create.</strong> The event name, type, dates, reveal setting, shot limit, filter, guest allowance and cover photo.</li>
  <li><strong>Photos.</strong> The photos taken or uploaded to an event, by the host and by guests, along with a thumbnail we generate and the time they were added.</li>
  <li><strong>Guests.</strong> Guests do not create an account. If a guest types a name, it is shown with their photos. Each guest's device gets a random token so we can count how many photos they have left; it does not identify the person.</li>
  <li><strong>Purchases.</strong> Guest packs are paid through Apple. We never see your card or payment details. Our purchase processor, RevenueCat, receives the purchase record and your Everly user identifier so the pack can be credited to the right event.</li>
  <li><strong>Analytics and attribution.</strong> We use PostHog to understand how the app and this website are used (for example, which screens or pages are opened, and which link brought you here) and AppsFlyer to measure which ads lead to installs. AppsFlyer only uses your device's advertising identifier if you allow tracking when iOS asks.</li>
  <li><strong>Technical data.</strong> Our servers record IP addresses in logs and use them to prevent abuse, such as too many uploads in a short time.</li>
</ul>

<h2>How we use it</h2>
<ul>
  <li>To run your events: store the photos, apply the filter, hold the gallery until the reveal and then open it.</li>
  <li>To credit guest packs to the event you bought them for.</li>
  <li>To keep the service secure and prevent abuse.</li>
  <li>To understand and improve the app, and to measure our marketing.</li>
</ul>
<p>We do not sell your personal information, and we do not use your photos for advertising or to train AI models.</p>

<h2>Who can see the photos</h2>
<p>Everly has no public feed. An event's photos are visible to its host. Guests see them only after the reveal, and only if the host chose to let everyone see every photo; otherwise each guest sees just the photos they took. Anyone with an event's QR code or invite link can join that event as a guest, so share it only with people you want there.</p>

<h2>Who we share it with</h2>
<p>Only the service providers that run Everly on our behalf: our hosting provider (servers and photo storage), Apple (sign-in and payments), RevenueCat (purchases), PostHog (analytics) and AppsFlyer (attribution). We may also disclose information if the law requires it.</p>

<h2>How long we keep it, and how to delete it</h2>
<ul>
  <li>We keep your account and events for as long as your account exists.</li>
  <li>Deleting an event deletes its photos from our storage.</li>
  <li>You can delete your account at any time in the app under <strong>Settings &rarr; Delete Account</strong>. This immediately deletes your account and every event you host, including their photos, and revokes Everly's access to your Apple ID.</li>
  <li>A host can remove any photo from their event, or remove everything a guest sent and block that guest, from the photo's menu in the app. Removed photos are deleted from our storage.</li>
  <li>Guests who want a photo of theirs removed can ask the event's host, or email us.</li>
</ul>

<h2>Children</h2>
<p>Everly is not directed to children under 13, and we do not knowingly collect their personal information. If you believe a child has given us personal information, contact us and we will delete it.</p>

<h2>Security and where data is stored</h2>
<p>Data is sent over encrypted connections and stored on servers run by our hosting provider, which may be outside your country. No system is perfectly secure, but we limit access to what is needed to run the service.</p>

<h2>Your rights</h2>
<p>Depending on where you live, you may have the right to access, correct, delete or export your personal information, or to object to how we use it. Email <a href="mailto:{{ $email }}">{{ $email }}</a> and we will respond within 30 days.</p>

<h2>Changes</h2>
<p>If we change this policy we will update the date above, and tell you in the app when the change is significant.</p>
@endsection
