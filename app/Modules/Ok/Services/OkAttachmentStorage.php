<?php

namespace App\Modules\Ok\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

/**
 * Downloads files into `chat-attachments/` for the admin workspace. File URLs
 * are signed, so they are never logged.
 */
class OkAttachmentStorage
{
    /** Telegram's limit for files sent by a bot. */
    public const MAX_BYTES = 52428800;

    private const DOWNLOAD_TIMEOUT_SECONDS = 60;

    /**
     * Download a file. A web page instead of a file counts as a failure:
     * OK may give a player page for a video.
     *
     * @param string $url
     *
     * @return array{path: string, name: string, mime: string, contents: string}|null
     */
    public function download(string $url): ?array
    {
        try {
            $response = Http::timeout(self::DOWNLOAD_TIMEOUT_SECONDS)->get($url);
            $contents = $response->body();
            $mime = strtok((string) $response->header('Content-Type'), ';') ?: 'application/octet-stream';

            if ($response->failed() || $contents === '' || str_starts_with($mime, 'text/html') || strlen($contents) > self::MAX_BYTES) {
                Log::channel('app')->warning('OkAttachmentStorage: file is not downloadable', [
                    'source' => 'ok_attachment_download_failed',
                    'status' => $response->status(),
                    'mime' => $mime,
                ]);

                return null;
            }

            return $this->store($contents, $mime) + ['contents' => $contents];
        } catch (\Throwable $e) {
            Log::channel('app')->warning('OkAttachmentStorage: download failed', [
                'source' => 'ok_attachment_download_failed',
                'exception' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Save file contents for the admin workspace.
     *
     * @param string      $contents
     * @param string      $mime
     * @param string|null $name     Original name, generated when absent
     *
     * @return array{path: string, name: string, mime: string}
     */
    public function store(string $contents, string $mime, ?string $name = null): array
    {
        $extension = MimeTypes::getDefault()->getExtensions($mime)[0] ?? '';
        $generated = (string) Str::uuid() . ($extension !== '' ? '.' . $extension : '');
        $path = 'chat-attachments/' . $generated;

        Storage::disk('local')->put($path, $contents);

        return ['path' => $path, 'name' => $name ?? $generated, 'mime' => $mime];
    }
}
