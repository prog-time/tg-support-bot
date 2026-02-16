<?php

namespace App\TelegramBot;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use phpDocumentor\Reflection\Exception as phpDocumentorException;

class ParserMethods
{
    /**
     * Send POST
     *
     * @param string       $urlQuery
     * @param array|string $queryParams
     * @param array        $queryHeading
     *
     * @return array
     */
    public static function postQuery(string $urlQuery, array|string $queryParams = [], array $queryHeading = []): array
    {
        try {
            $resultQuery = Http::withHeaders($queryHeading)
                ->when(config('traffic_source.telegram.force_ipv4'), fn ($client) => $client->withOptions(['force_ip_resolve' => 'v4']))
                ->post($urlQuery, $queryParams)
                ->json();

            if (empty($resultQuery)) {
                throw new \RuntimeException('Request caused an error');
            }

            return $resultQuery;
        } catch (\Throwable $e) {
            Log::channel('loki')->log($e->getCode() === 1 ? 'warning' : 'error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return [
                'ok' => false,
                'response_code' => 500,
                'result' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send GET
     *
     * @param string       $urlQuery
     * @param array|string $queryParams
     * @param array        $queryHeading
     *
     * @return array
     */
    public static function getQuery(string $urlQuery, array|string $queryParams = [], array $queryHeading = []): array
    {
        try {
            if (!empty($queryParams)) {
                $urlQuery .= '?' . http_build_query($queryParams);
            }

            $resultQuery = Http::withHeaders($queryHeading)
                ->when(config('traffic_source.telegram.force_ipv4'), fn ($client) => $client->withOptions(['force_ip_resolve' => 'v4']))
                ->withoutVerifying()
                ->get($urlQuery)
                ->json();

            if (empty($resultQuery)) {
                throw new \RuntimeException('Request caused an error');
            }

            return $resultQuery;
        } catch (\Throwable $e) {
            Log::channel('loki')->log($e->getCode() === 1 ? 'warning' : 'error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return [
                'ok' => false,
                'response_code' => 500,
                'result' => $e->getMessage(),
            ];
        }
    }

    public static function attachQuery(string $urlQuery, array|string $queryParams = [], string $attachType = 'document'): array
    {
        try {
            if (empty($attachType)) {
                $attachType = 'document';
            }

            if (empty($queryParams['uploaded_file']) || !$queryParams['uploaded_file'] instanceof UploadedFile) {
                throw new phpDocumentorException('File not provided!');
            }

            /** @var UploadedFile $attachData */
            $attachData = $queryParams['uploaded_file'];
            unset($queryParams['uploaded_file']);

            if ($attachData->getSize() > 50 * 1024 * 1024) {
                throw new phpDocumentorException('File is too large for Telegram (max 50 MB)');
            }

            if ($attachData->getSize() === 0) {
                throw new phpDocumentorException('File is empty and cannot be sent');
            }

            if (!$attachData->isValid()) {
                throw new phpDocumentorException('File is invalid');
            }

            $tempPath = $attachData->getRealPath();

            if (!$tempPath || !file_exists($tempPath) || !is_readable($tempPath)) {
                throw new phpDocumentorException('Temporary file does not exist or is not readable');
            }

            $extension = $attachData->getClientOriginalExtension();
            $safeName = Str::uuid() . ($extension ? '.' . $extension : '');

            $resultQuery = Http::attach(
                $attachType,
                fopen($tempPath, 'rb'),
                $safeName
            )
                ->when(config('traffic_source.telegram.force_ipv4'), fn ($client) => $client->withOptions(['force_ip_resolve' => 'v4']))
                ->post($urlQuery, $queryParams)
                ->json();

            if (empty($resultQuery)) {
                throw new \RuntimeException('Request caused an error');
            }

            return $resultQuery;
        } catch (\Throwable $e) {
            Log::channel('loki')->log($e->getCode() === 1 ? 'warning' : 'error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return [
                'ok' => false,
                'response_code' => 500,
                'result' => $e->getMessage(),
            ];
        }
    }
}
