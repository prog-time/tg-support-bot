<?php

namespace App\Helpers;

class TelegramHelper
{
    /**
     * Get telegram file path
     * @param string $localFilePath
     * @return string
     */
    public static function getFilePath(string $localFilePath): string
    {
        $telegramToken = env('TELEGRAM_TOKEN');
        return "https://api.telegram.org/file/bot{$telegramToken}/{$localFilePath}";
    }
}
