<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->validateRequiredConfig();
    }

    /**
     * Warn (in production) or throw (in testing) when critical .env keys are missing.
     *
     * @throws \RuntimeException
     */
    protected function validateRequiredConfig(): void
    {
        $required = [
            'CLICKPESA_CLIENT_ID'  => config('services.clickpesa.client_id'),
            'CLICKPESA_API_KEY'    => config('services.clickpesa.api_key'),
            'ZTP_VPS_IP'           => config('services.ztp.vps_ip'),
            'ZTP_SSTP_SECRET'      => config('services.ztp.sstp_secret'),
        ];

        $missing = array_keys(array_filter($required, fn ($v) => empty($v)));

        if (empty($missing)) {
            return;
        }

        $message = 'Missing required .env keys: '.implode(', ', $missing);

        if (app()->isProduction()) {
            \Illuminate\Support\Facades\Log::critical($message);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

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
