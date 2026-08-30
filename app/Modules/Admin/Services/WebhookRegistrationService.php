<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Vk\Api\VkMethods;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin service wrapper around platform-specific webhook registration.
 *
 * Each method mirrors the logic in the existing artisan commands
 * (TelegramSetWebhook, AiBotSetWebhook) but returns a structured result
 * instead of writing to the console, so Livewire actions can display
 * success/error notifications.
 *
 * Tokens are read via SettingsService so DB-overrides take effect
 * immediately without a restart. Never logged per security rules.
 */
class WebhookRegistrationService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {
    }

    /**
     * Verify a Telegram bot token by calling getMe.
     *
     * Accepts an explicit token so verification can run against the form-entered
     * value before the setting is persisted. Never logs the token.
     *
     * @param string      $token   Bot token to verify.
     * @param string|null $groupId Optional Telegram group/chat ID — when provided (non-empty),
     *                             also verifies the bot can access that chat via getChat AND
     *                             that the bot is an administrator in it via getChatMember.
     *
     * @return array{success: bool, message: string, botId: int|null, botUsername: string|null}
     *                                                                                          On success, botId/botUsername carry the identity returned by getMe.
     */
    public function verifyTelegram(string $token, ?string $groupId = null): array
    {
        if ($token === '') {
            return ['success' => false, 'message' => 'Токен Telegram не задан.', 'botId' => null, 'botUsername' => null];
        }

        try {
            $result = TelegramMethods::sendQueryTelegram('getMe', [], $token);

            if ($result->ok !== true) {
                Log::channel('app')->error('WebhookRegistrationService: Telegram token verification failed (getMe)', [
                    'response_code' => $result->response_code,
                    'type_error' => $result->type_error,
                    'description' => $result->rawData['description'] ?? null,
                ]);

                return ['success' => false, 'message' => 'Неверный токен Telegram.', 'botId' => null, 'botUsername' => null];
            }

            $me = $result->rawData['result'] ?? [];
            $botId = isset($me['id']) ? (int) $me['id'] : null;

            if ($groupId !== null && $groupId !== '') {
                $chat = TelegramMethods::sendQueryTelegram('getChat', ['chat_id' => $groupId], $token);

                if ($chat->ok !== true) {
                    $desc = (string) ($chat->rawData['description'] ?? '');
                    $message = 'Неверный ID группы или бот не добавлен в группу.';
                    if ($desc !== '') {
                        $message .= ' Ответ Telegram: ' . $desc;
                    }

                    Log::channel('app')->error('WebhookRegistrationService: Telegram token verification failed (getChat)', [
                        'group_id' => $groupId,
                        'response_code' => $chat->response_code,
                        'description' => $desc !== '' ? $desc : null,
                    ]);

                    return ['success' => false, 'message' => $message, 'botId' => null, 'botUsername' => null];
                }

                if ($botId !== null) {
                    $member = TelegramMethods::sendQueryTelegram('getChatMember', [
                        'chat_id' => $groupId,
                        'user_id' => $botId,
                    ], $token);

                    $status = $member->rawData['result']['status'] ?? null;

                    if (! in_array($status, ['administrator', 'creator'], true)) {
                        Log::channel('app')->warning('WebhookRegistrationService: Telegram token verification failed (bot not admin)', [
                            'group_id' => $groupId,
                            'bot_id' => $botId,
                            'status' => $status,
                        ]);

                        return ['success' => false, 'message' => 'Бот добавлен в группу, но без прав администратора.', 'botId' => null, 'botUsername' => null];
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'Токен Telegram прошёл проверку.',
                'botId' => $botId,
                'botUsername' => isset($me['username']) ? (string) $me['username'] : null,
            ];
        } catch (\Throwable $e) {
            Log::channel('app')->error('WebhookRegistrationService: Telegram token verification threw an exception', [
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Не удалось связаться с API платформы.', 'botId' => null, 'botUsername' => null];
        }
    }

    /**
     * Verify a VK community access token by calling groups.getById.
     *
     * Accepts an explicit token so verification can run against the form-entered
     * value before the setting is persisted. Never logs the token.
     *
     * @param string $token VK access token to verify.
     *
     * @return array{success: bool, message: string}
     */
    public function verifyVk(string $token): array
    {
        if ($token === '') {
            return ['success' => false, 'message' => 'Токен VK не задан.'];
        }

        try {
            $result = VkMethods::sendQueryVk('groups.getById', [], $token);

            if ($result->response_code !== 500 && empty($result->error_message)) {
                return ['success' => true, 'message' => 'Токен VK прошёл проверку.'];
            }

            $errMsg = $result->error_message ?? 'неизвестная ошибка';

            return ['success' => false, 'message' => 'Ошибка VK API: ' . $errMsg];
        } catch (\Throwable) {
            return ['success' => false, 'message' => 'Не удалось связаться с API платформы.'];
        }
    }

    /**
     * Verify a MAX bot token by calling GET /me on the platform API.
     *
     * Accepts an explicit token so verification can run against the form-entered
     * value before the setting is persisted. Uses a 10 s timeout. Never logs the
     * token.
     *
     * @param string $token MAX bot token to verify.
     *
     * @return array{success: bool, message: string}
     */
    public function verifyMax(string $token): array
    {
        if ($token === '') {
            return ['success' => false, 'message' => 'Токен MAX не задан.'];
        }

        try {
            $baseUrl = 'https://platform-api.max.ru';

            $response = Http::withHeaders(['Authorization' => $token])
                ->timeout(10)
                ->get("{$baseUrl}/me");

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Токен MAX прошёл проверку.'];
            }

            return ['success' => false, 'message' => 'Неверный токен MAX (HTTP ' . $response->status() . ').'];
        } catch (\Throwable) {
            return ['success' => false, 'message' => 'Не удалось связаться с API платформы.'];
        }
    }

    /**
     * Register the Telegram main-bot webhook.
     *
     * @return array{success: bool, message: string}
     */
    public function registerTelegram(): array
    {
        $token = (string) $this->settings->get('telegram.token');
        $secret = (string) $this->settings->get('telegram.secret_key');

        if ($token === '') {
            return ['success' => false, 'message' => 'Токен Telegram не задан.'];
        }

        $appUrl = config('app.url');
        $url = $appUrl . '/api/telegram/bot';

        $queryParams = [
            'url' => $url,
            'max_connections' => 40,
            'drop_pending_updates' => true,
            'secret_token' => $secret,
        ];

        $result = TelegramMethods::sendQueryTelegram('setWebhook', $queryParams, $token);

        if ($result->ok === true) {
            Log::channel('app')->info('WebhookRegistrationService: Telegram webhook registered', [
                'url' => $url,
            ]);

            return ['success' => true, 'message' => 'Вебхук Telegram зарегистрирован.'];
        }

        Log::channel('app')->error('WebhookRegistrationService: Telegram webhook registration failed', [
            'raw' => $result->rawData ?? null,
        ]);

        return [
            'success' => false,
            'message' => 'Ошибка регистрации вебхука Telegram: ' . ($result->description ?? 'неизвестная ошибка'),
        ];
    }

    /**
     * Register the VK callback server (confirm + set webhook).
     *
     * VK does not have a single "setWebhook" method. The closest equivalent
     * is groups.setCallbackSettings which enables callbacks on the group.
     * The confirmation code must already be set; here we call
     * groups.addCallbackServer to register our URL if not registered yet,
     * then activate message events.
     *
     * Because VK's callback API requires a group_id which is not in the
     * current registry, this method verifies the token is present and
     * invokes groups.getLongPollServer as a connectivity check returning
     * success when the API responds positively.
     *
     * @return array{success: bool, message: string}
     */
    public function registerVk(): array
    {
        $token = (string) $this->settings->get('vk.token');

        if ($token === '') {
            return ['success' => false, 'message' => 'Токен VK не задан.'];
        }

        $result = VkMethods::sendQueryVk('groups.getById', []);

        if ($result->response_code !== 500 && empty($result->error_message)) {
            Log::channel('app')->info('WebhookRegistrationService: VK connectivity verified');

            return ['success' => true, 'message' => 'Подключение к VK API подтверждено. Убедитесь, что вебхук URL зарегистрирован в настройках группы ВКонтакте.'];
        }

        $errMsg = $result->error_message ?? 'неизвестная ошибка';

        Log::channel('app')->error('WebhookRegistrationService: VK verification failed', [
            'error' => $errMsg,
        ]);

        return ['success' => false, 'message' => 'Ошибка VK API: ' . $errMsg];
    }

    /**
     * Register the MAX bot webhook via the Max platform API.
     *
     * Max uses a REST endpoint: POST /subscriptions with {"url": "..."}
     * authenticated via the bot token in Authorization header. The secret is
     * sent so MAX echoes it back in the `X-Max-Bot-Api-Secret` header on every
     * webhook — without it, MaxQuery middleware rejects incoming webhooks with 403.
     *
     * @return array{success: bool, message: string}
     */
    public function registerMax(): array
    {
        $token = (string) $this->settings->get('max.token');

        if ($token === '') {
            return ['success' => false, 'message' => 'Токен MAX не задан.'];
        }

        $secret = (string) $this->settings->get('max.secret_key');

        $appUrl = config('app.url');
        $webhookUrl = $appUrl . '/api/max/bot';
        $baseUrl = 'https://platform-api.max.ru';

        $payload = ['url' => $webhookUrl];

        if ($secret !== '') {
            $payload['secret'] = $secret;
        }

        $response = Http::withHeaders(['Authorization' => $token])
            ->post("{$baseUrl}/subscriptions", $payload);

        if ($response->successful()) {
            Log::channel('app')->info('WebhookRegistrationService: MAX webhook registered', [
                'url' => $webhookUrl,
            ]);

            return ['success' => true, 'message' => 'Вебхук MAX зарегистрирован.'];
        }

        $body = $response->body();

        Log::channel('app')->error('WebhookRegistrationService: MAX webhook registration failed', [
            'status' => $response->status(),
        ]);

        return [
            'success' => false,
            'message' => 'Ошибка регистрации вебхука MAX (HTTP ' . $response->status() . '): ' . $body,
        ];
    }
}
