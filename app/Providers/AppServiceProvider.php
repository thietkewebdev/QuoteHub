<?php

namespace App\Providers;

use App\Services\AI\Contracts\QuotationExtractionProviderInterface;
use App\Services\AI\Providers\MockQuotationExtractionProvider;
use App\Services\AI\Providers\OpenAiQuotationExtractionProvider;
use App\Services\Ingestion\IngestionFileStorageService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(IngestionFileStorageService::class, fn (): IngestionFileStorageService => IngestionFileStorageService::makeFromConfig());

        $this->app->bind(QuotationExtractionProviderInterface::class, function ($app): QuotationExtractionProviderInterface {
            return match (strtolower((string) config('quotation_ai.driver', 'openai'))) {
                'openai' => $app->make(OpenAiQuotationExtractionProvider::class),
                'mock' => $app->make(MockQuotationExtractionProvider::class),
                default => throw new \InvalidArgumentException(
                    'Unknown QUOTATION_AI_DRIVER: '.config('quotation_ai.driver').'. Use openai or mock.'
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
