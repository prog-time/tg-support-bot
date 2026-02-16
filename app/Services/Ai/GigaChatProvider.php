<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\DTOs\Ai\AiRequestDto;
use App\DTOs\Ai\AiResponseDto;
use Illuminate\Support\Facades\Log;
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
     * Process user message through GigaChat API.
     *
     * @param AiRequestDto $request Request DTO
     *
     * @return AiResponseDto|null AI response DTO
     */
    public function processMessage(AiRequestDto $request): ?AiResponseDto
    {
        try {
            if (!$this->checkRateLimit()) {
                throw new Exception('GigaChat rate limit exceeded');
            }

            $this->ensureValidToken();

            $response = $this->makeApiCall($request);

            return $this->parseApiResponse($response, $request);
        } catch (\Throwable $e) {
            Log::channel('loki')->error($e->getMessage(), [
                'source' => 'ai_error',
                'user_id' => $request->userId,
                'platform' => $request->platform,
            ]);

            return null;
        }
    }

    /**
     * Check if provider is available and properly configured.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return !empty($this->config['client_secret']) &&
               !empty($this->config['base_url']);
    }

    /**
     * Get provider name.
     *
     * @return string
     */
    public function getProviderName(): string
    {
        return 'gigachat';
    }

    /**
     * Get model name.
     *
     * @return string
     */
    public function getModelName(): string
    {
        return $this->config['model'] ?? 'GigaChat-2-Max';
    }

    /**
     * Ensure access token is valid.
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
     * Refresh access token.
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
     * Make API call to GigaChat.
     *
     * @param AiRequestDto $request Request DTO
     *
     * @return array GigaChat API response
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
     * Build messages array for GigaChat API.
     *
     * @param AiRequestDto $request Request DTO
     *
     * @return array Messages array in GigaChat format
     */
    private function buildMessages(AiRequestDto $request): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt(),
            ],
        ];

        foreach ($request->context as $contextMessage) {
            $messages[] = [
                'role' => $contextMessage['role'] ?? 'user',
                'content' => $contextMessage['content'] ?? '',
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $request->message,
        ];

        return $messages;
    }

    /**
     * Parse GigaChat API response and create DTO.
     *
     * @param array        $response GigaChat API response
     * @param AiRequestDto $request  Original request
     *
     * @return AiResponseDto AI response DTO
     */
    private function parseApiResponse(array $response, AiRequestDto $request): AiResponseDto
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $usage = $response['usage'] ?? [];

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
     * Parse structured response from AI.
     *
     * @param string $content AI response text
     *
     * @return array Parsed data with confidence and escalation flag
     */
    private function parseStructuredResponse(string $content): array
    {
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

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
