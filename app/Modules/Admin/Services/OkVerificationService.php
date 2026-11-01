<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pre-save check of an OK group token with a read-only request. The token is
 * never logged: a transport error is not quoted because its text contains the URL.
 */
class OkVerificationService
{
    private const SUBSCRIPTIONS_URL = 'https://api.ok.ru/graph/me/subscriptions';

    /**
     * @param string $token Group token
     *
     * @return array{success: bool, message: string}
     */
    public function verify(string $token): array
    {
        if ($token === '') {
            return ['success' => false, 'message' => 'Укажите токен группы.'];
        }

        try {
            $response = Http::timeout(10)->get(self::SUBSCRIPTIONS_URL, ['access_token' => $token]);
        } catch (\Throwable) {
            return ['success' => false, 'message' => 'Не удалось связаться с API Одноклассников.'];
        }

        $errorCode = $response->json('error_code');

        if ($response->failed() || $errorCode !== null) {
            Log::channel('app')->warning('OkVerificationService: token check failed', [
                'status' => $response->status(),
                'error_code' => $errorCode,
            ]);

            return ['success' => false, 'message' => 'Одноклассники не приняли токен: проверьте его в настройках группы.'];
        }

        return ['success' => true, 'message' => 'Подключение к Одноклассникам подтверждено.'];
    }
}
