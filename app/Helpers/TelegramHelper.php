<?php

namespace App\Helpers;

class TelegramHelper
{
    /**
     * Генерация пути к файлу
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
     * Генерация публичного пути к файлу
     *
     * @param string $fileId
     *
     * @return string
     */
    public static function getFilePublicPath(string $fileId): string
    {
        $appUrl = config('app.url');
        return "{$appUrl}/api/files/{$fileId}";
    }

    /**
     * @param array $data
     *
     * @return string|null
     */
    public static function extractFileId(array $data): ?string
    {
        if (!empty($data['message']['photo'])) {
            $fileId = end($data['message']['photo'])['file_id'] ?? null;
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
