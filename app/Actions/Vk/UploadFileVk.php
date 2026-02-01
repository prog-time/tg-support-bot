<?php

namespace App\Actions\Vk;

use Illuminate\Support\Facades\Http;

/**
 * Upload file to VK server.
 */
class UploadFileVk
{
    /**
     * Upload file to VK server.
     *
     * @param string $typeFile
     * @param string $upload_url
     * @param string $fullFilePath
     *
     * @return array|null
     */
    public static function execute(string $upload_url, string $fullFilePath, string $typeFile = 'doc'): ?array
    {
        try {
            $urlQuery = $upload_url;

            $responseFile = Http::get($fullFilePath);

            if ($responseFile->failed()) {
                throw new \Exception("Failed to download Telegram file: {$fullFilePath}");
            }

            $stream = $responseFile->body();
            $filename = basename(parse_url($fullFilePath, PHP_URL_PATH));

            if ($typeFile === 'doc' || $typeFile === 'audio_message') {
                $typeFile = 'file';
            }

            $response = Http::attach(
                $typeFile,
                $stream,
                $filename
            )->post($urlQuery);
            return $response->json();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
