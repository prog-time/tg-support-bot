<?php

namespace App\Helpers;

use App\Modules\Api\Services\FileService;
use App\Services\Settings\SettingsService;
use phpDocumentor\Reflection\Exception;

class TelegramHelper
{
    /**
     * Generate file path.
     *
     * @param string $localFilePath
     *
     * @return string
     */
    public static function getFilePath(string $localFilePath): string
    {
        $telegramToken = (string) app(SettingsService::class)->get('telegram.token');
        return "https://api.telegram.org/file/bot{$telegramToken}/{$localFilePath}";
    }

    /**
     * Generate public file path.
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
     * @param string           $fileId
     * @param FileService|null $fileService
     *
     * @return string|null
     */
    public static function getFileTelegramPath(string $fileId, ?FileService $fileService = null): ?string
    {
        $botToken = (string) app(SettingsService::class)->get('telegram.token');
        $fileService = $fileService ?? new FileService();

        try {
            $tgFileData = $fileService->getTelegramFile($fileId);
            if (empty($tgFileData['result']['file_path'])) {
                throw new Exception('File not found');
            }

            $tgFilePath = $tgFileData['result']['file_path'];
            return "https://api.telegram.org/file/bot{$botToken}/{$tgFilePath}";
        } catch (\Throwable $e) {
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

    /**
     * @param array $data
     *
     * @return string|null
     */
    public static function extractFileType(array $data): ?string
    {
        if (!empty($data['message']['photo'])) {
            return 'photo';
        } elseif (!empty($data['message']['document'])) {
            return 'document';
        } elseif (!empty($data['message']['voice'])) {
            return 'voice';
        } elseif (!empty($data['message']['sticker'])) {
            return 'sticker';
        } elseif (!empty($data['message']['video_note'])) {
            return 'video_note';
        } elseif (!empty($data['message']['contact'])) {
            return 'contact';
        }

        return null;
    }

    /**
     * Detect a Telegram message content type this bot does not handle
     * (video, GIF, audio, poll, dice, venue, game), returning a
     * human-readable Russian label for the unsupported-message notice.
     *
     * Returns null for any recognized/handled type, so callers can tell
     * "genuinely unsupported" apart from "just has no text" (e.g. service
     * messages).
     *
     * @param array $data
     *
     * @return string|null
     */
    public static function detectUnsupportedType(array $data): ?string
    {
        $message = $data['message'] ?? [];

        return match (true) {
            !empty($message['video']) => 'видео',
            !empty($message['animation']) => 'GIF-анимация',
            !empty($message['audio']) => 'аудио',
            !empty($message['poll']) => 'опрос',
            !empty($message['dice']) => 'игральная кость',
            !empty($message['venue']) => 'место (геометка)',
            !empty($message['game']) => 'игра',
            default => null,
        };
    }

    /**
     * Build the placeholder notice text for a message content type this
     * bot does not handle, so the conversation is not silently dropped
     * (issue #46). Returns null when the update is not one of the
     * recognized unsupported types — callers should fall back to their
     * normal text/caption resolution in that case.
     *
     * @param array $data
     *
     * @return string|null
     */
    public static function buildUnsupportedTypeNotice(array $data): ?string
    {
        $type = self::detectUnsupportedType($data);

        if ($type === null) {
            return null;
        }

        return "⚠️ Клиент отправил сообщение неподдерживаемого типа ({$type}). Попросите переслать текстом, фото, документом или голосовым.";
    }
}
