<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pre-save verification for the paid Avito module's API credentials.
 *
 * Mirrors {@see WebhookRegistrationService}'s verifyX() methods: it runs against
 * the form-entered credentials before anything is persisted, so the integration
 * screen can fail fast on bad credentials and auto-capture the account id.
 *
 * The Avito Messenger API uses OAuth2 client_credentials. This service requests
 * an access token (POST {base}/token) and then resolves the authenticated
 * account (GET {base}/core/v1/accounts/self) — the `id` of which is the
 * `user_id` every messenger call is scoped by. A 10 s timeout guards each call;
 * client secret and access token are never logged.
 */
class AvitoVerificationService
{
    /** Default Avito API base URL when none is provided. */
    private const DEFAULT_BASE_URL = 'https://api.avito.ru';

    /**
     * Verify Avito API credentials and resolve the owning account id.
     *
     * @param string $clientId     OAuth client id.
     * @param string $clientSecret OAuth client secret.
     * @param string $baseUrl      API base URL (blank → Avito default).
     *
     * @return array{success: bool, message: string, accountId: string|null, accountName: string|null}
     *                                                                                                 On success, accountId carries the `id` from accounts/self (the messenger user_id).
     */
    public function verify(string $clientId, string $clientSecret, string $baseUrl = ''): array
    {
        if ($clientId === '' || $clientSecret === '') {
            return $this->fail('Укажите Client ID и Client Secret.');
        }

        $base = rtrim($baseUrl !== '' ? $baseUrl : self::DEFAULT_BASE_URL, '/');

        try {
            // ── 1. OAuth client_credentials → access token ────────────────────
            $tokenResponse = Http::asForm()
                ->timeout(10)
                ->post("{$base}/token", [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);

            if (! $tokenResponse->successful()) {
                Log::channel('app')->warning('AvitoVerificationService: token request failed', [
                    'status' => $tokenResponse->status(),
                ]);

                return $this->fail('Не удалось получить токен Avito: проверьте Client ID и Client Secret (HTTP ' . $tokenResponse->status() . ').');
            }

            $accessToken = (string) ($tokenResponse->json('access_token') ?? '');

            if ($accessToken === '') {
                return $this->fail('Avito не вернул access_token — проверьте права приложения (scope messenger).');
            }

            // ── 2. Resolve the authenticated account ──────────────────────────
            $selfResponse = Http::withToken($accessToken)
                ->timeout(10)
                ->get("{$base}/core/v1/accounts/self");

            if (! $selfResponse->successful()) {
                Log::channel('app')->warning('AvitoVerificationService: accounts/self failed', [
                    'status' => $selfResponse->status(),
                ]);

                return $this->fail('Токен получен, но запрос аккаунта не прошёл (HTTP ' . $selfResponse->status() . ').');
            }

            $id = $selfResponse->json('id');

            if ($id === null || $id === '') {
                return $this->fail('Avito не вернул ID аккаунта.');
            }

            return [
                'success' => true,
                'message' => 'Подключение к Avito подтверждено.',
                'accountId' => (string) $id,
                'accountName' => ($name = $selfResponse->json('name')) !== null ? (string) $name : null,
            ];
        } catch (\Throwable) {
            return $this->fail('Не удалось связаться с API Avito.');
        }
    }

    /**
     * Build a failure result.
     *
     * @return array{success: bool, message: string, accountId: null, accountName: null}
     */
    private function fail(string $message): array
    {
        return ['success' => false, 'message' => $message, 'accountId' => null, 'accountName' => null];
    }
}
