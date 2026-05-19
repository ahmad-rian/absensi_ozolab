<?php

namespace App\Providers;

use App\Services\Notification\DefaultWhatsAppGateway;
use App\Services\Notification\WhatsAppGateway;
use Illuminate\Support\ServiceProvider;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsAppGateway::class, function () {
            return new DefaultWhatsAppGateway(
                baseUrl: (string) config('whatsapp.base_url'),
                apiKey: (string) config('whatsapp.api_key'),
                senderId: (string) config('whatsapp.sender_id'),
                timeout: (int) config('whatsapp.timeout', 10),
            );
        });
    }
}
