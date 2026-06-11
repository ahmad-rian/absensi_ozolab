<?php

namespace App\Services\Notification;

interface EmailGateway
{
    /**
     * @param  array<string, string>  $variables
     */
    public function sendTemplate(string $email, string $templateKey, array $variables, ?string $schoolId = null): bool;

    public function sendText(string $email, string $message, ?string $schoolId = null): bool;
}
