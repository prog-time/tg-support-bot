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
     * @return array{success: bool, message: string, accountId: string|null, accountName: string|null, messengerStatus: string, messengerMessage: string|null}
     *                                                                                                                                                         messengerStatus is ok|no_subscription|unknown — see {@see self::checkMessengerAccess()}.
     *                                                                                                                                                         Valid credentials alone do not mean the channel will receive anything.
     *                                                                                                                                                         On success, accountId carries the `id` from accounts/self (the messenger user_id).
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

            $messenger = $this->checkMessengerAccess($accessToken, (string) $id, $base);

            return [
                'success' => true,
                'message' => 'Подключение к Avito подтверждено.',
                'accountId' => (string) $id,
                'accountName' => ($name = $selfResponse->json('name')) !== null ? (string) $name : null,
                'messengerStatus' => $messenger['status'],
                'messengerMessage' => $messenger['message'],
            ];
        } catch (\Throwable) {
            return $this->fail('Не удалось связаться с API Avito.');
        }
    }

    /**
     * Determine whether the account has Avito's paid Messenger API plan.
     *
     * Valid credentials are NOT enough for the channel to work: without the
     * «API мессенджера» subscription Avito accepts the webhook subscription and
     * reports it in the list, but never delivers a callback, and it replaces the
     * message text in the chat list with an upsell string. Nothing in the OAuth
     * or accounts/self responses hints at this, which makes a green "connected"
     * badge actively misleading.
     *
     * Probed through the real paywall — a 402 from the messages endpoint —
     * rather than by matching Avito's marketing copy, which can change at any
     * time. The endpoint needs a chat id, so an account with no chats yet cannot
     * be classified; that case is reported as unknown rather than guessed.
     *
     * @param string $accessToken
     * @param string $userId
     * @param string $base        API base URL, no trailing slash.
     *
     * @return array{status: string, message: string} status: ok|no_subscription|unknown
     */
    private function checkMessengerAccess(string $accessToken, string $userId, string $base): array
    {
        try {
            $chats = Http::withToken($accessToken)
                ->timeout(10)
                ->get("{$base}/messenger/v2/accounts/{$userId}/chats", ['limit' => 1]);

            if (! $chats->successful()) {
                return $this->messengerUnknown('не удалось получить список чатов (HTTP ' . $chats->status() . ').');
            }

            $chatId = $chats->json('chats.0.id');

            if ($chatId === null || $chatId === '') {
                return $this->messengerUnknown('в аккаунте пока нет чатов, проверить доступ невозможно.');
            }

            $messages = Http::withToken($accessToken)
                ->timeout(10)
                ->get("{$base}/messenger/v3/accounts/{$userId}/chats/{$chatId}/messages", ['limit' => 1]);

            if ($messages->status() === 402) {
                return [
                    'status' => 'no_subscription',
                    'message' => 'Нет подписки «API мессенджера» Avito. Учётные данные верны, но Avito не будет присылать сообщения — ни вебхуком, ни через API. Канал не заработает, пока подписка не оформлена.',
                ];
            }

            if ($messages->successful()) {
                return ['status' => 'ok', 'message' => 'Подписка «API мессенджера» активна.'];
            }

            return $this->messengerUnknown('неожиданный ответ от API мессенджера (HTTP ' . $messages->status() . ').');
        } catch (\Throwable) {
            return $this->messengerUnknown('не удалось связаться с API мессенджера.');
        }
    }

    /**
     * @param string $reason
     *
     * @return array{status: string, message: string}
     */
    private function messengerUnknown(string $reason): array
    {
        return [
            'status' => 'unknown',
            'message' => 'Не удалось проверить подписку «API мессенджера»: ' . $reason,
        ];
    }

    /**
     * Build a failure result.
     *
     * @return array{success: bool, message: string, accountId: null, accountName: null, messengerStatus: string, messengerMessage: null}
     */
    private function fail(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'accountId' => null,
            'accountName' => null,
            'messengerStatus' => 'unknown',
            'messengerMessage' => null,
        ];
    }
}
