<?php

namespace App\Providers;

use App\Services\Ingestion\IngestionFileStorageService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(IngestionFileStorageService::class, fn (): IngestionFileStorageService => IngestionFileStorageService::makeFromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
