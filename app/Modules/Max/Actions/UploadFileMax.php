<?php

namespace App\Modules\Max\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MaxBotApi\Config;
use MaxBotApi\MaxClient;

class UploadFileMax
{
    /**
     * Download a file from Telegram and upload it to Max.
     * Returns the attachment token for use in a message, or null on failure.
     *
     * Token sources differ by upload type:
     *   - image : CDN returns {"photos": {"<key>": {"token": "..."}}}
     *   - file  : CDN returns {"token": "..."}
     *   - video / audio: token is returned by the /uploads API call (getUploadUrl)
     *
     * @param string $telegramFileUrl Full Telegram file URL to download from.
     * @param string $filename        Original filename.
     * @param string $type            Max upload type: 'image', 'file', 'video', 'audio'.
     *
     * @return string|null
     */
    public function execute(string $telegramFileUrl, string $filename, string $type): ?string
    {
        try {
            $fileResponse = Http::get($telegramFileUrl);
            if ($fileResponse->failed()) {
                throw new \Exception("Failed to download from Telegram: status={$fileResponse->status()} url={$telegramFileUrl}");
            }

            $client = new MaxClient(new Config(
                token: config('traffic_source.settings.max.token'),
            ));

            $uploadResult = $client->uploads->getUploadUrl($type);

            Log::channel('loki')->info('UploadFileMax: uploading to CDN', [
                'type' => $type,
                'filename' => $filename,
                'size' => strlen($fileResponse->body()),
            ]);

            $cdnResponse = Http::attach('data', $fileResponse->body(), $filename)
                ->post($uploadResult->url);

            if ($cdnResponse->failed()) {
                throw new \Exception("CDN upload failed: status={$cdnResponse->status()} body={$cdnResponse->body()}");
            }

            $cdnData = $cdnResponse->json() ?? [];

            Log::channel('loki')->info('UploadFileMax: CDN response | type=' . $type . ' data=' . json_encode($cdnData));

            $token = $uploadResult->token
                ?? $cdnData['token']
                ?? (isset($cdnData['photos']) ? array_values($cdnData['photos'])[0]['token'] ?? null : null)
                ?? null;

            if ($token === null) {
                throw new \RuntimeException('No token received. CDN response: ' . json_encode($cdnData));
            }

            Log::channel('loki')->info('UploadFileMax: upload succeeded', ['type' => $type]);

            return $token;
        } catch (\Throwable $e) {
            Log::channel('loki')->error('UploadFileMax: upload failed | ' . get_class($e) . ': ' . $e->getMessage(), [
                'type' => $type,
                'filename' => $filename,
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return null;
        }
    }
}
