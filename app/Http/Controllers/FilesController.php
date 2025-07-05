<?php

namespace App\Http\Controllers;

use App\Logging\LokiLogger;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class FilesController
 *
 * @package App\Http\Controllers
 */
class FilesController
{
    /**
     * Передать файл на скачивание
     *
     * @param string $fileId
     *
     * @return StreamedResponse
     */
    public function getFile(string $fileId): StreamedResponse
    {
        try {
            if (empty($fileId)) {
                throw new \Exception('File id не найден!');
            }

            $botToken = config('traffic_source.settings.telegram.token');

            $fileData = Http::get("https://api.telegram.org/bot{$botToken}/getFile", [
                'file_id' => $fileId,
            ])->json();

            $filePath = $fileData['result']['file_path'] ?? null;

            if (!$filePath) {
                abort(404, 'Файл не найден!');
            }

            $fileUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";

            // Загружаем файл напрямую
            $fileResponse = Http::get($fileUrl);

            if (!$fileResponse->ok()) {
                abort(502, 'Не удалось получить файл');
            }

            $mimeType = $fileResponse->header('Content-Type');

            return response()->stream(function () use ($fileResponse) {
                echo $fileResponse->body();
            }, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
            ]);
        } catch (\Exception $e) {
            (new LokiLogger())->log('tg_request', json_encode($e->getMessage()));
            die();
        }
    }
}
