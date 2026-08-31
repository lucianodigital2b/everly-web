<?php

namespace App\Providers;

use App\Services\Pix\FakePixGateway;
use App\Services\Pix\PixGateway;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Thumbnails are generated with GD; Imagick isn't installed.
        $this->app->singleton(ImageManager::class, fn (): ImageManager => new ImageManager(new Driver));

        $this->app->singleton(PixGateway::class, function (): PixGateway {
            return match (config('everly.pix.driver')) {
                'fake' => new FakePixGateway,
                default => throw new InvalidArgumentException(
                    'Unsupported PIX driver ['.config('everly.pix.driver').'].'
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * The guest upload endpoint is unauthenticated — anyone holding the QR link
     * can post to it — so it's capped per IP.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('guest-uploads', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));

        // The RevenueCat webhook is public; cap it per IP to blunt abuse while
        // staying well above any realistic legitimate delivery rate.
        RateLimiter::for('revenuecat-webhook', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // The mobile app reads list endpoints as bare arrays (Plan[], Event[]),
        // not as {"data": [...]}, so keep resources unwrapped.
        JsonResource::withoutWrapping();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
