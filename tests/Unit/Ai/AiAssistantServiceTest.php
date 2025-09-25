<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\DTOs\Ai\AiRequestDto;
use App\DTOs\Ai\AiResponseDto;
use Tests\TestCase;

class AiAssistantServiceTest extends TestCase
{
    /**
     * Тест создания DTO для AI-запроса.
     */
    public function test_ai_request_dto_creation(): void
    {
        $request = new AiRequestDto(
            message: 'Test message',
            userId: 1,
            platform: 'telegram'
        );

        $this->assertEquals('Test message', $request->message);
        $this->assertEquals(1, $request->userId);
        $this->assertEquals('telegram', $request->platform);
    }

    /**
     * Тест создания DTO для AI-ответа.
     */
    public function test_ai_response_dto_creation(): void
    {
        $response = new AiResponseDto(
            response: 'Test response',
            confidenceScore: 0.9,
            shouldEscalate: false,
            provider: 'openai',
            modelUsed: 'gpt-3.5-turbo',
            tokensUsed: 50,
            responseTime: 0.5
        );

        $this->assertEquals('Test response', $response->response);
        $this->assertEquals(0.9, $response->confidenceScore);
        $this->assertFalse($response->shouldEscalate);
        $this->assertEquals('openai', $response->provider);
    }

    /**
     * Тест проверки уверенности AI-ответа.
     */
    public function test_ai_response_confidence_check(): void
    {
        $highConfidenceResponse = new AiResponseDto(
            response: 'Confident response',
            confidenceScore: 0.9,
            shouldEscalate: false,
            provider: 'openai',
            modelUsed: 'gpt-3.5-turbo',
            tokensUsed: 50,
            responseTime: 0.5
        );

        $lowConfidenceResponse = new AiResponseDto(
            response: 'Uncertain response',
            confidenceScore: 0.6,
            shouldEscalate: true,
            provider: 'openai',
            modelUsed: 'gpt-3.5-turbo',
            tokensUsed: 30,
            responseTime: 0.3
        );

        $this->assertTrue($highConfidenceResponse->isConfident());
        $this->assertFalse($lowConfidenceResponse->isConfident());
    }

    /**
     * Тест преобразования DTO AI-запроса в массив.
     */
    public function test_ai_request_dto_to_array(): void
    {
        $request = new AiRequestDto(
            message: 'Test message',
            userId: 1,
            platform: 'telegram',
            context: ['test' => 'context'],
            provider: 'openai',
            maxConfidence: 0.9,
            forceEscalation: false
        );

        $array = $request->toArray();

        $this->assertEquals('Test message', $array['message']);
        $this->assertEquals(1, $array['user_id']);
        $this->assertEquals('telegram', $array['platform']);
        $this->assertEquals(['test' => 'context'], $array['context']);
        $this->assertEquals('openai', $array['provider']);
        $this->assertEquals(0.9, $array['max_confidence']);
        $this->assertFalse($array['force_escalation']);
    }

    /**
     * Тест преобразования DTO AI-ответа в массив.
     */
    public function test_ai_response_dto_to_array(): void
    {
        $response = new AiResponseDto(
            response: 'Test response',
            confidenceScore: 0.9,
            shouldEscalate: false,
            provider: 'openai',
            modelUsed: 'gpt-3.5-turbo',
            tokensUsed: 50,
            responseTime: 0.5,
            metadata: ['test' => 'data']
        );

        $array = $response->toArray();

        $this->assertEquals('Test response', $array['response']);
        $this->assertEquals(0.9, $array['confidence_score']);
        $this->assertFalse($array['should_escalate']);
        $this->assertEquals('openai', $array['provider']);
        $this->assertEquals('gpt-3.5-turbo', $array['model_used']);
        $this->assertEquals(50, $array['tokens_used']);
        $this->assertEquals(0.5, $array['response_time']);
        $this->assertEquals(['test' => 'data'], $array['metadata']);
    }
}
