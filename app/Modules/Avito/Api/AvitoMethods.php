<?php

namespace App\Modules\Avito\Api;

use App\Modules\Avito\DTOs\AvitoAnswerDto;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Direct Avito API calls. Mirrors the host's MaxMethods.
 *
 * Auth is authoritative (Avito "Объявления"/Items OpenAPI, shared across all
 * Avito APIs): OAuth2 client_credentials at POST https://api.avito.ru/token,
 * Bearer token, scopes messenger:read / messenger:write. The messenger
 * endpoints below follow the public Avito Messenger API (v1/v3); verify them
 * against the messenger OpenAPI and adjust the paths/bodies if Avito differs.
 */
class AvitoMethods
{
    private const TOKEN_CACHE_KEY = 'avito_access_token';

    /**
     * Dispatch entry point used by the send jobs.
     *
     * @param string $methodQuery
     * @param array  $params
     *
     * @return AvitoAnswerDto
     */
    public function sendQuery(string $methodQuery, array $params): AvitoAnswerDto
    {
        try {
            $response = match ($methodQuery) {
                'sendMessage' => $this->sendMessage(
                    (string) ($params['chat_id'] ?? ''),
                    (string) ($params['text'] ?? ''),
                ),
                default => throw new \RuntimeException("Unknown method: {$methodQuery}", 1),
            };

            return AvitoAnswerDto::fromData([
                'response_code' => 200,
                'response' => $response,
            ]);
        } catch (\Throwable $e) {
            Log::channel('app')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                'AvitoMethods::sendQuery failed | ' . get_class($e) . ': ' . $e->getMessage(),
                ['methodQuery' => $methodQuery],
            );

            return AvitoAnswerDto::fromData([
                'response_code' => 500,
                'error_message' => $e->getCode() === 1 ? $e->getMessage() : 'Request sending error',
                'response' => null,
            ]);
        }
    }

    /**
     * Send a text message to an Avito chat.
     * POST /messenger/v1/accounts/{user_id}/chats/{chat_id}/messages
     *
     * @param string $chatId Avito chat id (conversation key)
     * @param string $text
     *
     * @return array
     *
     * @throws \RuntimeException On API error.
     */
    public function sendMessage(string $chatId, string $text): array
    {
        $userId = app(SettingsService::class)->get('avito.user_id');

        $response = $this->client()->post(
            $this->url("messenger/v1/accounts/{$userId}/chats/{$chatId}/messages"),
            ['type' => 'text', 'message' => ['text' => $text]],
        );

        if ($response->failed()) {
            throw new \RuntimeException('Avito sendMessage failed: ' . $response->body(), 1);
        }

        return (array) $response->json();
    }

    /**
     * Resolve the authenticated account. GET /core/v1/accounts/self
     *
     * @return array
     *
     * @throws \RuntimeException On API error.
     */
    public function getSelf(): array
    {
        $response = $this->client()->get($this->url('core/v1/accounts/self'));

        if ($response->failed()) {
            throw new \RuntimeException('Avito getSelf failed: ' . $response->body(), 1);
        }

        return (array) $response->json();
    }

    /**
     * Subscribe a webhook URL. POST /messenger/v3/webhook
     *
     * @param string $url
     *
     * @return array
     *
     * @throws \RuntimeException On API error.
     */
    public function subscribeWebhook(string $url): array
    {
        $response = $this->client()->post($this->url('messenger/v3/webhook'), ['url' => $url]);

        if ($response->failed()) {
            throw new \RuntimeException('Avito webhook subscribe failed: ' . $response->body(), 1);
        }

        return (array) $response->json();
    }

    /**
     * Unsubscribe a webhook URL. POST /messenger/v1/webhook/unsubscribe
     *
     * @param string $url
     *
     * @return array
     *
     * @throws \RuntimeException On API error.
     */
    public function unsubscribeWebhook(string $url): array
    {
        $response = $this->client()->post($this->url('messenger/v1/webhook/unsubscribe'), ['url' => $url]);

        if ($response->failed()) {
            throw new \RuntimeException('Avito webhook unsubscribe failed: ' . $response->body(), 1);
        }

        return (array) $response->json();
    }

    /**
     * Obtain (and cache) an OAuth2 client_credentials access token.
     * POST https://api.avito.ru/token (application/x-www-form-urlencoded).
     *
     * @return string
     *
     * @throws \RuntimeException On token error.
     */
    public function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $settings = app(SettingsService::class);

        $response = Http::asForm()->post($this->url('token'), [
            'grant_type' => 'client_credentials',
            'client_id' => $settings->get('avito.client_id'),
            'client_secret' => $settings->get('avito.client_secret'),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Avito token request failed: ' . $response->body(), 1);
        }

        $token = (string) $response->json('access_token');
        if ($token === '') {
            throw new \RuntimeException('Avito token response missing access_token', 1);
        }

        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addSeconds(max(60, $expiresIn - 60)));

        return $token;
    }

    /**
     * Authorized JSON HTTP client.
     *
     * @return PendingRequest
     */
    private function client(): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson();
    }

    /**
     * Build an absolute API URL from the configured base.
     *
     * @param string $path
     *
     * @return string
     */
    private function url(string $path): string
    {
        return rtrim((string) app(SettingsService::class)->get('avito.base_url'), '/') . '/' . ltrim($path, '/');
    }
}
