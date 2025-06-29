<?php

namespace App\Helpers;

class TelegramHelper
{
    /**
     * Генерация путей к файлам
     *
     * @param string $localFilePath
     *
     * @return string
     */
    public static function getFilePath(string $localFilePath): string
    {
        $telegramToken = config('traffic_source.settings.telegram.token');
        return "https://api.telegram.org/file/bot{$telegramToken}/{$localFilePath}";
    }
}
