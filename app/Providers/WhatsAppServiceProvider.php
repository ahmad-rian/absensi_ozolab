<?php

namespace App\Providers;

use App\Services\Notification\DefaultEmailGateway;
use App\Services\Notification\DefaultTelegramGateway;
use App\Services\Notification\DefaultWhatsAppGateway;
use App\Services\Notification\EmailGateway;
use App\Services\Notification\TelegramGateway;
use App\Services\Notification\WhatsAppGateway;
use Illuminate\Support\ServiceProvider;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsAppGateway::class, function () {
            return new DefaultWhatsAppGateway(
                timeout: (int) config('whatsapp.timeout', 10),
            );
        });

        $this->app->singleton(TelegramGateway::class, function () {
            return new DefaultTelegramGateway(
                timeout: (int) config('telegram.timeout', 10),
            );
        });

        $this->app->singleton(EmailGateway::class, function () {
            return new DefaultEmailGateway;
        });
    }
}
