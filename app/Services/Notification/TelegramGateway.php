<?php

namespace App\Services\Notification;

interface TelegramGateway
{
    /**
     * @param  array<string, string>  $variables
     */
    public function sendTemplate(string $chatId, string $templateKey, array $variables, ?string $schoolId = null): bool;

    public function sendText(string $chatId, string $message, ?string $schoolId = null): bool;
}
