<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * GET /app — the one link to put anywhere outside the app (Instagram bio,
 * TikTok, X, a QR on a flyer). It lands on a page that records the visit in
 * PostHog, utm_* included, and then sends the phone to the App Store.
 *
 * A page and not a 302 on purpose: a server redirect would leave before the
 * analytics script ever ran. The page still works with JavaScript off or
 * PostHog blocked (meta refresh plus a plain link).
 */
class StoreLinkController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('store-link', [
            'storeUrl' => $this->storeUrl($request),
        ]);
    }

    /**
     * The App Store URL, with Apple's campaign parameters when the provider
     * token is configured: `pt` is the team's token, `ct` the campaign name
     * App Store Connect shows (40 chars max).
     */
    private function storeUrl(Request $request): string
    {
        $url = config('everly.invite.app_store_url');
        $providerToken = config('everly.invite.apple_provider_token');

        if (! $providerToken) {
            return $url;
        }

        $campaign = collect([
            $request->query('utm_source'),
            $request->query('utm_campaign'),
        ])->filter(fn ($part) => is_string($part) && $part !== '')
            ->map(fn (string $part) => Str::slug($part, '_'))
            ->implode('_');

        $query = http_build_query([
            'pt' => $providerToken,
            'ct' => Str::limit($campaign !== '' ? $campaign : 'web', 40, ''),
            'mt' => 8,
        ]);

        return $url.(str_contains($url, '?') ? '&' : '?').$query;
    }
}
