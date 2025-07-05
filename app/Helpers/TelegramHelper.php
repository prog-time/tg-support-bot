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

    /**
     * @param array $data
     *
     * @return string|null
     */
    public static function extractFileId(array $data): ?string
    {
        if (!empty($data['message']['photo'])) {
            $fileId = end($data['message']['photo'])['file_id'];
        } elseif (!empty($data['message']['document'])) {
            $fileId = $data['message']['document']['file_id'];
        } elseif (!empty($data['message']['voice'])) {
            $fileId = $data['message']['voice']['file_id'];
        } elseif (!empty($data['message']['sticker'])) {
            $fileId = $data['message']['sticker']['file_id'];
        } elseif (!empty($data['message']['video_note'])) {
            $fileId = $data['message']['video_note']['file_id'];
        }

        return $fileId ?? null;
    }
}
