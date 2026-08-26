<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

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
        // Fora de produção, lazy load vira exceção: N+1 aparece no teste,
        // não no servidor.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
