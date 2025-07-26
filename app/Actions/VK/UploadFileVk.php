<?php

namespace App\Actions\VK;

use Illuminate\Support\Facades\Http;

/**
 * Загрузка файла на сервер VK
 */
class UploadFileVk
{
    /**
     * Загрузка файла на сервер VK
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
            $stream = fopen($fullFilePath, 'r');

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
        } catch (\Exception $e) {
            return null;
        }
    }
}
