<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Services\Shipping\Contracts\ShippingProviderInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ShippingProviderInterface::class, function ($app) {
            $baseUrl = config('services.rajaongkir.base_url', 'https://api.rajaongkir.com/starter');
            if (str_contains($baseUrl, 'komerce.id')) {
                return new \App\Services\Shipping\KomerceShippingService();
            }
            return new \App\Services\Shipping\RajaOngkirShippingService();
        });

        $this->app->singleton(\App\Services\Shipping\ShippingService::class, function ($app) {
            return new \App\Services\Shipping\ShippingService(
                $app->make(ShippingProviderInterface::class)
            );
        });

        $this->app->singleton(\App\Services\Payment\IPaymuService::class, function ($app) {
            return new \App\Services\Payment\IPaymuService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        
        \App\Models\Review::observe(\App\Observers\ReviewObserver::class);
        \App\Models\Promotion::observe(\App\Observers\PromotionObserver::class);

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\OrderStatusChanged::class,
            [\App\Listeners\RecordActivityLog::class, 'handleOrderStatus']
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\ReturnRequestStatusChanged::class,
            [\App\Listeners\RecordActivityLog::class, 'handleReturnStatus']
        );
    }
}
