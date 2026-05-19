<?php

namespace App\Services\Notification;

use App\Models\Setting;

class MessageTemplateRenderer
{
    /**
     * @param  array<string, string>  $variables
     */
    public function render(string $templateKey, array $variables): string
    {
        $template = Setting::getValue($templateKey, '');

        if (empty($template)) {
            return '';
        }

        $placeholders = [];
        foreach ($variables as $key => $value) {
            $placeholders["{{$key}}"] = $value;
        }

        return strtr($template, $placeholders);
    }
}
