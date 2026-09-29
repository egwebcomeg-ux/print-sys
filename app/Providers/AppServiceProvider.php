<?php

namespace App\Providers;

use App\Services\Odoo\FakeOdooClient;
use App\Services\Odoo\OdooClient;
use App\Services\Odoo\OdooClientInterface;
use App\Support\Abilities;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;
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
        // ODOO_FAKE=true (default until a sandbox exists) keeps every call in memory.
        $this->app->singleton(OdooClientInterface::class, fn () => config('odoo.fake')
            ? new FakeOdooClient
            : new OdooClient(
                (string) config('odoo.url'),
                (string) config('odoo.db'),
                (string) config('odoo.username'),
                (string) config('odoo.api_key'),
                (int) config('odoo.timeout'),
            ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Abilities::register();

        // Resources are passed straight to React components as props.
        JsonResource::withoutWrapping();
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
