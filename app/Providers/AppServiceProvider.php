<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $identity = Str::lower($request->string('email')->toString()).'|'.$request->ip();

            return [
                Limit::perMinute(5)->by($identity),
                Limit::perHour(30)->by((string) $request->ip()),
            ];
        });

        RateLimiter::for('registration', fn (Request $request): array => [
            Limit::perMinute(3)->by((string) $request->ip()),
            Limit::perHour(10)->by((string) $request->ip()),
        ]);

        RateLimiter::for('public-api', fn (Request $request): Limit => Limit::perMinute(120)->by((string) $request->ip())
        );

        RateLimiter::for('authenticated-api', fn (Request $request): Limit => Limit::perMinute(120)->by((string) ($request->user()?->id ?? $request->ip()))
        );

        RateLimiter::for('uploads', fn (Request $request): Limit => Limit::perMinute(10)->by((string) ($request->user()?->id ?? $request->ip()))
        );
    }
}
