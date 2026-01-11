<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\DTOs\Ai\AiRequestDto;
use App\DTOs\Ai\AiResponseDto;
use App\Logging\LokiLogger;
use Illuminate\Support\Facades\Http;
use phpDocumentor\Reflection\Exception;

class OpenAiProvider extends BaseAiProvider
{
    public function __construct()
    {
        parent::__construct('openai');
    }

    /**
     * Обработать сообщение пользователя через OpenAI API.
     *
     * @param AiRequestDto $request DTO с данными запроса
     *
     * @return AiResponseDto|null DTO с ответом AI
     */
    public function processMessage(AiRequestDto $request): ?AiResponseDto
    {
        try {
            if (!$this->checkRateLimit()) {
                throw new Exception('OpenAI rate limit exceeded');
            }

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
     * Выполнить API-вызов к OpenAI.
     *
     * @param AiRequestDto $request DTO с данными запроса
     *
     * @return array Ответ от OpenAI API
     *
     * @throws \Exception
     */
    private function makeApiCall(AiRequestDto $request): array
    {
        $messages = $this->buildMessages($request);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/chat/completions', [
            'model' => $this->modelName,
            'messages' => $messages,
            'max_tokens' => (int)$this->config['max_tokens'],
            'temperature' => (float)$this->config['temperature'],
        ]);

        if (!$response->successful()) {
            throw new \Exception('OpenAI API request failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Построить массив сообщений для OpenAI API.
     *
     * @param AiRequestDto $request DTO с данными запроса
     *
     * @return array Массив сообщений в формате OpenAI
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
     * Разобрать ответ от OpenAI API и создать DTO.
     *
     * @param array        $response Ответ от OpenAI API
     * @param AiRequestDto $request  Исходный запрос
     *
     * @return AiResponseDto DTO с ответом AI
     */
    private function parseApiResponse(array $response, AiRequestDto $request): AiResponseDto
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $usage = $response['usage'] ?? [];

        // Попытаться разобрать JSON-ответ для структурированных данных
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

    /**
     * Проверить, доступен ли провайдер и правильно настроен.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return !empty($this->config['api_key']) && !empty($this->config['base_url']);
    }
}
