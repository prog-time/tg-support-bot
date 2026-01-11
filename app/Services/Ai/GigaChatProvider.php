<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\DTOs\Ai\AiRequestDto;
use App\DTOs\Ai\AiResponseDto;
use App\Logging\LokiLogger;
use Illuminate\Support\Facades\Http;
use phpDocumentor\Reflection\Exception;

class GigaChatProvider extends BaseAiProvider
{
    private ?string $accessToken = null;

    private ?int $tokenExpiresAt = null;

    public function __construct()
    {
        parent::__construct('gigachat');
    }

    /**
     * Обработать сообщение пользователя через GigaChat API.
     *
     * @param AiRequestDto $request DTO с данными запроса
     *
     * @return AiResponseDto|null DTO с ответом AI
     */
    public function processMessage(AiRequestDto $request): ?AiResponseDto
    {
        try {
            if (!$this->checkRateLimit()) {
                throw new Exception('GigaChat rate limit exceeded');
            }

            // Получить или обновить токен доступа
            $this->ensureValidToken();

            // Выполнить API-вызов
            $response = $this->makeApiCall($request);

            return $this->parseApiResponse($response, $request);
        } catch (\Throwable $e) {
            (new LokiLogger())->log('ai_error', [
                'error' => $e->getMessage(),
                'user_id' => $request->userId,
                'platform' => $request->platform,
            ]);

            return null;
        }
    }

    /**
     * Проверить, доступен ли провайдер и правильно настроен.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return !empty($this->config['client_secret']) &&
               !empty($this->config['base_url']);
    }

    /**
     * Получить название провайдера.
     *
     * @return string
     */
    public function getProviderName(): string
    {
        return 'gigachat';
    }

    /**
     * Получить название используемой модели.
     *
     * @return string
     */
    public function getModelName(): string
    {
        return $this->config['model'] ?? 'GigaChat-2-Max';
    }

    /**
     * Убедиться, что токен доступа действителен.
     *
     * @throws \Exception
     */
    private function ensureValidToken(): void
    {
        if (!$this->accessToken || !$this->tokenExpiresAt || $this->tokenExpiresAt <= time()) {
            $this->refreshAccessToken();
        }
    }

    /**
     * Обновить токен доступа.
     *
     * @throws \Exception
     */
    private function refreshAccessToken(): void
    {
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $this->config['client_secret'],
            'RqUID' => (string) \Illuminate\Support\Str::uuid(),
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->withOptions([
            'verify' => storage_path($this->config['path_cert']),
        ])->asForm()->post('https://ngw.devices.sberbank.ru:9443/api/v2/oauth', [
            'scope' => 'GIGACHAT_API_PERS',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to obtain GigaChat token: ' . $response->body());
        }

        $data = $response->json();
        $this->accessToken = $data['access_token'];
        $this->tokenExpiresAt = $data['expires_at'];
    }

    /**
     * Выполнить API-вызов к GigaChat.
     *
     * @param AiRequestDto $request DTO с данными запроса
     *
     * @return array Ответ от GigaChat API
     *
     * @throws \Exception
     */
    private function makeApiCall(AiRequestDto $request): array
    {
        $messages = $this->buildMessages($request);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
            'RqUID' => (string) \Illuminate\Support\Str::uuid(),
        ])->withOptions([
            'verify' => storage_path($this->config['path_cert']),
        ])->post($this->config['base_url'] . '/chat/completions', [
            'model' => $this->config['model'],
            'messages' => $messages,
            'max_tokens' => (int)$this->config['max_tokens'],
            'temperature' => (float)$this->config['temperature'],
            'stream' => false,
        ]);

        if (!$response->successful()) {
            throw new \Exception('GigaChat API request failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Построить массив сообщений для GigaChat API.
     *
     * @param AiRequestDto $request DTO с данными запроса
     *
     * @return array Массив сообщений в формате GigaChat
     */
    private function buildMessages(AiRequestDto $request): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt(),
            ],
        ];

        // Добавить контекстные сообщения, если доступны
        foreach ($request->context as $contextMessage) {
            $messages[] = [
                'role' => $contextMessage['role'] ?? 'user',
                'content' => $contextMessage['content'] ?? '',
            ];
        }

        // Добавить текущее сообщение пользователя
        $messages[] = [
            'role' => 'user',
            'content' => $request->message,
        ];

        return $messages;
    }

    /**
     * Разобрать ответ от GigaChat API и создать DTO.
     *
     * @param array        $response Ответ от GigaChat API
     * @param AiRequestDto $request  Исходный запрос
     *
     * @return AiResponseDto DTO с ответом AI
     */
    private function parseApiResponse(array $response, AiRequestDto $request): AiResponseDto
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $usage = $response['usage'] ?? [];

        // Попытаться разобрать структурированный ответ
        $parsedContent = $this->parseStructuredResponse($content);

        $confidenceScore = $parsedContent['confidence_score'] ?? 0.8;
        $shouldEscalate = $parsedContent['should_escalate'] ?? $this->shouldEscalate($confidenceScore);
        $aiResponse = $parsedContent['response'] ?? $content;

        return $this->createResponse(
            response: $aiResponse,
            confidenceScore: $confidenceScore,
            shouldEscalate: $shouldEscalate,
            tokensUsed: $usage['total_tokens'] ?? 0,
            responseTime: microtime(true) - microtime(true),
            metadata: [
                'finish_reason' => $response['choices'][0]['finish_reason'] ?? null,
                'model' => $response['model'] ?? null,
                'parsed_content' => $parsedContent,
                'provider' => 'GigaChat',
            ]
        );
    }

    /**
     * Разобрать структурированный ответ от AI.
     *
     * @param string $content Текст ответа от AI
     *
     * @return array Разобранные данные с уверенностью и флагом эскалации
     */
    private function parseStructuredResponse(string $content): array
    {
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Резервный вариант: попытаться извлечь информацию об уверенности и эскалации из текста
        $confidenceScore = 0.8;
        $shouldEscalate = false;

        if (preg_match('/confidence[:\s]+(\d+\.?\d*)/i', $content, $matches)) {
            $confidenceScore = (float) $matches[1];
        }

        if (preg_match('/escalat(e|ion)/i', $content)) {
            $shouldEscalate = true;
        }

        return [
            'response' => $content,
            'confidence_score' => $confidenceScore,
            'should_escalate' => $shouldEscalate,
        ];
    }
}
