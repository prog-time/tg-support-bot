<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Pre-save connectivity checks for AI provider credentials.
 *
 * Mirrors WebhookRegistrationService (channel integrations): each verifyX()
 * runs a lightweight authenticated call against the provider API and returns a
 * structured result, so the AiProviderAccessPage can verify entered credentials
 * BEFORE persisting them. Secrets are never logged.
 *
 * Verification strategy per provider:
 *  - OpenAI / DeepSeek: a minimal chat-completion request (max_tokens=1) that
 *    validates the key, base URL and model in one shot.
 *  - GigaChat: an OAuth token request (the same call the provider makes at
 *    runtime), validating the Basic secret and the trusted-root certificate.
 *
 * All calls use a 10 s timeout and convert any transport error into
 * success=false instead of throwing.
 */
class AiProviderVerificationService
{
    /** Request timeout for verification calls, in seconds. */
    private const TIMEOUT = 10;

    /**
     * Verify OpenAI credentials with a minimal chat-completion call.
     *
     * @param string $apiKey  OpenAI API key (Bearer).
     * @param string $baseUrl Base URL, e.g. https://api.openai.com/v1.
     * @param string $model   Model name to probe.
     *
     * @return array{success: bool, message: string}
     */
    public function verifyOpenai(string $apiKey, string $baseUrl, string $model): array
    {
        if ($apiKey === '') {
            return ['success' => false, 'message' => 'Не указан API-ключ OpenAI.'];
        }

        if ($baseUrl === '' || $model === '') {
            return ['success' => false, 'message' => 'Укажите base URL и модель OpenAI.'];
        }

        return $this->probeChatCompletion(
            url: rtrim($baseUrl, '/') . '/chat/completions',
            bearer: $apiKey,
            model: $model,
            label: 'OpenAI',
        );
    }

    /**
     * Verify DeepSeek credentials with a minimal chat-completion call.
     *
     * DeepSeek is OpenAI-compatible and the provider posts directly to the
     * configured base URL (the full chat-completions endpoint).
     *
     * @param string $clientSecret DeepSeek client secret (Bearer).
     * @param string $baseUrl      Full chat-completions endpoint URL.
     * @param string $model        Model name to probe.
     *
     * @return array{success: bool, message: string}
     */
    public function verifyDeepseek(string $clientSecret, string $baseUrl, string $model): array
    {
        if ($clientSecret === '') {
            return ['success' => false, 'message' => 'Не указан client secret DeepSeek.'];
        }

        if ($baseUrl === '' || $model === '') {
            return ['success' => false, 'message' => 'Укажите base URL и модель DeepSeek.'];
        }

        return $this->probeChatCompletion(
            url: $baseUrl,
            bearer: $clientSecret,
            model: $model,
            label: 'DeepSeek',
        );
    }

    /**
     * Verify GigaChat credentials by requesting an OAuth access token.
     *
     * @param string $clientSecret GigaChat client secret (Basic auth header).
     * @param string $certPath     Absolute path to the trusted-root CA bundle
     *                             used to verify the OAuth host's TLS cert.
     *
     * @return array{success: bool, message: string}
     */
    public function verifyGigachat(string $clientSecret, string $certPath, string $scope = 'GIGACHAT_API_PERS'): array
    {
        if ($clientSecret === '') {
            return ['success' => false, 'message' => 'Не указан client secret GigaChat.'];
        }

        if ($certPath === '') {
            return ['success' => false, 'message' => 'Загрузите сертификат GigaChat.'];
        }

        $scope = $scope !== '' ? $scope : 'GIGACHAT_API_PERS';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $clientSecret,
                'RqUID' => (string) Str::uuid(),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])
                ->withOptions(['verify' => $certPath])
                ->timeout(self::TIMEOUT)
                ->asForm()
                ->post('https://ngw.devices.sberbank.ru:9443/api/v2/oauth', [
                    'scope' => $scope,
                ]);

            if ($response->successful() && ! empty($response->json()['access_token'])) {
                return ['success' => true, 'message' => 'Доступы GigaChat прошли проверку.'];
            }

            return [
                'success' => false,
                'message' => 'GigaChat: проверка не пройдена (HTTP ' . $response->status() . ')' . $this->gigachatErrorHint($response) . '.',
            ];
        } catch (\Throwable) {
            return ['success' => false, 'message' => 'Не удалось связаться с API GigaChat.'];
        }
    }

    /**
     * Build a human hint for a failed GigaChat OAuth response.
     *
     * Surfaces the provider's error body (no secret is present in an OAuth
     * error payload) plus a status-specific tip — most 400s here are a scope
     * mismatch (PERS vs B2B/CORP) or a malformed Authorization key.
     *
     * @param \Illuminate\Http\Client\Response $response
     *
     * @return string Leading-space hint, or '' when nothing useful is available.
     */
    private function gigachatErrorHint(\Illuminate\Http\Client\Response $response): string
    {
        $hint = match ($response->status()) {
            400 => ' — проверьте scope (PERS / B2B / CORP) и формат Authorization key',
            401 => ' — неверные авторизационные данные',
            default => '',
        };

        $detail = $response->json('message') ?? $response->json('error');
        if (! is_string($detail) || $detail === '') {
            $body = trim((string) $response->body());
            $detail = $body !== '' ? mb_substr($body, 0, 200) : '';
        }

        if ($detail !== '') {
            $hint .= ' [' . $detail . ']';
        }

        return $hint;
    }

    /**
     * Run a minimal OpenAI-compatible chat-completion probe.
     *
     * @param string $url    Full chat-completions endpoint.
     * @param string $bearer Bearer token (API key / client secret).
     * @param string $model  Model name.
     * @param string $label  Human-readable provider name for messages.
     *
     * @return array{success: bool, message: string}
     */
    private function probeChatCompletion(string $url, string $bearer, string $model, string $label): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $bearer,
                'Content-Type' => 'application/json',
            ])
                ->timeout(self::TIMEOUT)
                ->post($url, [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => 'ping']],
                    'max_tokens' => 1,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => "Доступы {$label} прошли проверку."];
            }

            $hint = $response->status() === 401
                ? ' — неверный ключ'
                : ($response->status() === 404 ? ' — проверьте base URL и модель' : '');

            return ['success' => false, 'message' => "{$label}: проверка не пройдена (HTTP {$response->status()}{$hint})."];
        } catch (\Throwable) {
            return ['success' => false, 'message' => "Не удалось связаться с API {$label}."];
        }
    }
}
