<?php

namespace App\Helpers;

use App\Services\File\FileService;
use phpDocumentor\Reflection\Exception;

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
        $appUrl = trim(config('app.url'), '/');
        return "{$appUrl}/api/files/{$fileId}";
    }

    /**
     * @param string $fileId
     *
     * @return string|null
     */
    public static function getFileTelegramPath(string $fileId): ?string
    {
        try {
            $botToken = config('traffic_source.settings.telegram.token');

            $tgFileData = (new FileService())->getTelegramFile($fileId);
            if (empty($tgFileData['result']['file_path'])) {
                throw new Exception('Файд не найден');
            }

            $tgFilePath = $tgFileData['result']['file_path'];
            return "https://api.telegram.org/file/bot{$botToken}/{$tgFilePath}";
        } catch (\Exception $e) {
            return null;
        }
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
