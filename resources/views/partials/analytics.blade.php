{{--
    PostHog on the public pages, same project as the app (config/everly.php,
    "Web analytics"). Async, so it never holds up the page. Pageviews are
    captured automatically and PostHog reads utm_* from the URL on its own,
    so a visit from /app?utm_source=instagram&utm_campaign=ig_bio arrives
    with those as event and person properties.

    Production only: local and test runs never send anything.
--}}
@production
@if (config('everly.analytics.posthog_key'))
<script>
!function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init capture register register_once register_for_session unregister unregister_for_session getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags on onFeatureFlags onSessionId identify setPersonProperties group resetGroups reset get_distinct_id get_session_id alias set_config startSessionRecording stopSessionRecording captureException get_property createPersonProfile opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing debug".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
posthog.init(@json(config('everly.analytics.posthog_key')), {
    api_host: @json(config('everly.analytics.posthog_host')),
    person_profiles: 'identified_only',
    loaded: function (ph) { ph.register({ platform: 'web', site: 'everly-web' }); }
});
</script>
@endif
@endproduction
