<?php

namespace App\TelegramBot;

use App\Logging\LokiLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use phpDocumentor\Reflection\Exception;

class ParserMethods
{
    /**
     * Отправка POST запроса
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
            $response = Http::withHeaders($queryHeading)->post($urlQuery, $queryParams);
            $resultQuery = $response->json();

            if (empty($resultQuery)) {
                throw new \Exception('Запрос вызвал ошибку');
            }

            return $resultQuery;
        } catch (\Exception $e) {
            (new LokiLogger())->logException($e);
            return [
                'ok' => false,
                'response_code' => 500,
                'result' => 'Ошибка отправки запроса',
            ];
        }
    }

    /**
     * Отправка GET запроса
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
                $urlQuery = $urlQuery . '?' . http_build_query($queryParams);
            }

            $response = Http::withHeaders($queryHeading)->withoutVerifying()->get($urlQuery);
            $resultQuery = $response->json();

            if (empty($resultQuery)) {
                throw new \Exception('Запрос вызвал ошибку');
            }

            return $resultQuery;
        } catch (\Exception $e) {
            (new LokiLogger())->logException($e);
            return [
                'ok' => false,
                'response_code' => 500,
                'result' => 'Ошибка отправки запроса',
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
                throw new Exception('Файл не передан!', 1);
            }

            /** @var UploadedFile $attachData */
            $attachData = $queryParams['uploaded_file'];
            unset($queryParams['uploaded_file']);

            // Проверка размера файла (макс. 50 МБ для Telegram)
            if ($attachData->getSize() > 50 * 1024 * 1024) {
                throw new Exception('Файл слишком большой для Telegram (макс. 50 МБ)', 1);
            }

            if ($attachData->getSize() === 0) {
                throw new Exception('Файл пустой и не может быть отправлен', 1);
            }

            // Проверка валидности файла
            if (!$attachData->isValid()) {
                throw new Exception('Файл невалиден', 1);
            }

            // Получение пути к временному файлу
            $tempPath = $attachData->getRealPath();

            if (!$tempPath || !file_exists($tempPath) || !is_readable($tempPath)) {
                throw new Exception('Временный файл не существует или недоступен для чтения', 1);
            }

            // Генерация уникального имени с UUID
            $extension = $attachData->getClientOriginalExtension();
            $safeName = Str::uuid() . ($extension ? '.' . $extension : '');

            // Отправка файла в Telegram
            $response = Http::attach(
                $attachType,
                fopen($tempPath, 'r'),
                $safeName
            )->post($urlQuery, $queryParams);

            $resultQuery = $response->json();

            if (empty($resultQuery)) {
                throw new \Exception('Запрос вызвал ошибку', 1);
            }

            return $resultQuery;
        } catch (\Exception $e) {
            (new LokiLogger())->logException($e);
            return [
                'ok' => false,
                'response_code' => 500,
                'result' => $e->getCode() === 1 ? $e->getMessage() : 'Ошибка отправки запроса',
            ];
        }
    }
}
