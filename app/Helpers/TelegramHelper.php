<?php

namespace App\Helpers;

class TelegramHelper
{
    /**
     * Генерация путей к файлам
     *
     * @param string $localFilePath
     * @return string
     */
    public static function getFilePath(string $localFilePath): string
    {
        $telegramToken = env('TELEGRAM_TOKEN');
        return "https://api.telegram.org/file/bot{$telegramToken}/{$localFilePath}";
    }
}
