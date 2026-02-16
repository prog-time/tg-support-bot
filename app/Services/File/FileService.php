<?php

namespace App\Services\File;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class FileService
 *
 * @package App\Http\Services
 */
class FileService
{
    /**
     * @var string
     */
    private string $botToken;

    public function __construct()
    {
        $this->botToken = config('traffic_source.settings.telegram.token');
    }

    /**
     * Передать файл на просмотр
     *
     * @param string $fileId
     *
     * @return StreamedResponse
     */
    public function streamFile(string $fileId): StreamedResponse
    {
        try {
            if (empty($fileId)) {
                throw new \Exception('File id не найден!');
            }

            $fileData = $this->getTelegramFile($fileId);

            $filePath = $fileData['result']['file_path'] ?? null;
            if (!$filePath) {
                abort(404, 'Файл не найден!');
            }

            $fileResponse = $this->downloadTelegramFile($filePath);

            $contentType = $this->getFileContentType($filePath);
            return response()->stream(function () use ($fileResponse) {
                echo $fileResponse->body();
            }, 200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
            ]);
        } catch (\Throwable $e) {
            Log::channel('loki')->info($e->getMessage(), ['source' => 'tg_request']);
            die();
        }
    }

    /**
     * @param string $fileId
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadFile(string $fileId): \Illuminate\Http\Response
    {
        try {
            if (empty($fileId)) {
                throw new \Exception('File id не найден!');
            }

            $fileData = $this->getTelegramFile($fileId);

            $filePath = $fileData['result']['file_path'] ?? null;
            if (!$filePath) {
                abort(404, 'Файл не найден!');
            }

            $fileResponse = $this->downloadTelegramFile($filePath);

            $contentType = $this->getFileContentType($filePath);
            return response($fileResponse->body())
                ->header('Content-Type', $contentType)
                ->header('Content-Disposition', 'attachment; filename="' . basename($filePath) . '"');
        } catch (\Throwable $e) {
            Log::channel('loki')->info($e->getMessage(), ['source' => 'tg_request']);
            die();
        }
    }

    /**
     * @param string $fileId
     *
     * @return array
     */
    public function getTelegramFile(string $fileId): array
    {
        return Http::get("https://api.telegram.org/bot{$this->botToken}/getFile", [
            'file_id' => $fileId,
        ])->json();
    }

    /**
     * @param string $filePath
     *
     * @return Response
     */
    protected function downloadTelegramFile(string $filePath): Response
    {
        $fileUrl = "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";

        $fileResponse = Http::get($fileUrl);
        if (!$fileResponse->ok()) {
            abort(502, 'Не удалось получить файл');
        }

        return $fileResponse;
    }

    /**
     * @param string $filePath
     *
     * @return string
     */
    protected function getFileContentType(string $filePath): string
    {
        $mapping = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
        ];

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        return !empty($mapping[$extension]) ? $mapping[$extension] : 'application/octet-stream';
    }
}
