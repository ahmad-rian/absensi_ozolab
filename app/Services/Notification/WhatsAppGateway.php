<?php

namespace App\Services\Notification;

interface WhatsAppGateway
{
    /**
     * @param  array<string, string>  $variables
     */
    public function sendTemplate(string $to, string $templateKey, array $variables): bool;

    public function sendText(string $to, string $message): bool;
}
