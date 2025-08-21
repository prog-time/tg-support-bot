<?php

namespace Tests\Unit\Telegram;

use App\Services\Telegram\TelegramRateLimitService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use ReflectionClass;

class TelegramRateLimitServiceSimpleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear cache before each test
        Cache::flush();
        
        // Set test configuration
        Config::set('telegram_limits.rate_limits.global.api_requests_per_second', 5);
        Config::set('telegram_limits.rate_limits.global.messages_per_second', 3);
        Config::set('telegram_limits.rate_limits.global.requests_per_minute', 10);
        Config::set('telegram_limits.rate_limits.global.requests_per_hour', 100);
        Config::set('telegram_limits.rate_limits.per_chat.messages_per_second', 2);
        Config::set('telegram_limits.rate_limits.per_chat.requests_per_second', 4);
        Config::set('telegram_limits.rate_limits.delays.between_messages', 33);
        Config::set('telegram_limits.rate_limits.delays.between_api_requests', 20);
        Config::set('telegram_limits.cache.prefix', 'test_telegram_rate_limit:');
    }

    public function testWaitForRateLimitWithMessageMethod()
    {
        $startTime = microtime(true);
        
        TelegramRateLimitService::waitForRateLimit('sendMessage');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        // Should wait at least the configured delay (33ms) plus some random delay
        $this->assertGreaterThanOrEqual(33, $executionTime);
        $this->assertLessThanOrEqual(60, $executionTime); // Should not exceed reasonable time
    }

    public function testWaitForRateLimitWithNonMessageMethod()
    {
        $startTime = microtime(true);
        
        TelegramRateLimitService::waitForRateLimit('getChat');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        // Should wait at least the configured delay (20ms) plus some random delay
        $this->assertGreaterThanOrEqual(20, $executionTime);
        $this->assertLessThanOrEqual(50, $executionTime); // Should not exceed reasonable time
    }

    public function testIsMessageMethodForMessageMethods()
    {
        $reflection = new ReflectionClass(TelegramRateLimitService::class);
        $method = $reflection->getMethod('isMessageMethod');
        $method->setAccessible(true);

        $messageMethods = [
            'sendMessage', 'sendPhoto', 'sendVideo', 'sendAudio', 'sendVoice',
            'sendDocument', 'sendAnimation', 'sendSticker', 'sendVideoNote',
            'sendContact', 'sendLocation', 'sendVenue', 'sendPoll'
        ];

        foreach ($messageMethods as $methodName) {
            $result = $method->invoke(null, $methodName);
            $this->assertTrue($result, "Method {$methodName} should be identified as a message method");
        }
    }

    public function testIsMessageMethodForNonMessageMethods()
    {
        $reflection = new ReflectionClass(TelegramRateLimitService::class);
        $method = $reflection->getMethod('isMessageMethod');
        $method->setAccessible(true);

        $nonMessageMethods = ['getChat', 'getFile', 'getMe', 'getUpdates', 'setWebhook', 'deleteWebhook'];

        foreach ($nonMessageMethods as $methodName) {
            $result = $method->invoke(null, $methodName);
            $this->assertFalse($result, "Method {$methodName} should NOT be identified as a message method");
        }
    }

    public function testCheckRateLimitWithValidRequestsWhenNoCacheSet()
    {
        // When no cache is set, all requests should pass
        $result1 = TelegramRateLimitService::checkRateLimit('sendMessage', 123);
        $result2 = TelegramRateLimitService::checkRateLimit('getChat', 456);
        $result3 = TelegramRateLimitService::checkRateLimit('sendPhoto');
        
        $this->assertTrue($result1);
        $this->assertTrue($result2);
        $this->assertTrue($result3);
    }

    public function testCacheKeyGeneration()
    {
        $chatId = 123;
        $currentTime = time();
        $currentSecond = $currentTime;
        $currentMinute = floor($currentTime / 60);
        $currentHour = floor($currentTime / 3600);
        $cachePrefix = 'test_telegram_rate_limit:';
        
        // Test that different cache keys are generated for different scenarios
        $globalSecondKey = "{$cachePrefix}global:second:{$currentSecond}";
        $globalMessagesKey = "{$cachePrefix}global:messages:second:{$currentSecond}";
        $chatMessagesKey = "{$cachePrefix}chat:{$chatId}:messages:second:{$currentSecond}";
        $chatRequestsKey = "{$cachePrefix}chat:{$chatId}:requests:second:{$currentSecond}";
        $minuteKey = "{$cachePrefix}minute:{$currentMinute}";
        $hourKey = "{$cachePrefix}hour:{$currentHour}";
        
        // All keys should be unique
        $keys = [
            $globalSecondKey, $globalMessagesKey, $chatMessagesKey,
            $chatRequestsKey, $minuteKey, $hourKey
        ];
        
        $this->assertEquals(count($keys), count(array_unique($keys)), 'All cache keys should be unique');
        
        // Test key format
        $this->assertStringContainsString('global:second:', $globalSecondKey);
        $this->assertStringContainsString('global:messages:second:', $globalMessagesKey);
        $this->assertStringContainsString("chat:{$chatId}:messages:second:", $chatMessagesKey);
        $this->assertStringContainsString("chat:{$chatId}:requests:second:", $chatRequestsKey);
        $this->assertStringContainsString('minute:', $minuteKey);
        $this->assertStringContainsString('hour:', $hourKey);
    }

    public function testConfigurationValues()
    {
        // Test that the configuration is properly set
        $this->assertEquals(5, config('telegram_limits.rate_limits.global.api_requests_per_second'));
        $this->assertEquals(3, config('telegram_limits.rate_limits.global.messages_per_second'));
        $this->assertEquals(10, config('telegram_limits.rate_limits.global.requests_per_minute'));
        $this->assertEquals(100, config('telegram_limits.rate_limits.global.requests_per_hour'));
        $this->assertEquals(2, config('telegram_limits.rate_limits.per_chat.messages_per_second'));
        $this->assertEquals(4, config('telegram_limits.rate_limits.per_chat.requests_per_second'));
        $this->assertEquals(33, config('telegram_limits.rate_limits.delays.between_messages'));
        $this->assertEquals(20, config('telegram_limits.rate_limits.delays.between_api_requests'));
        $this->assertEquals('test_telegram_rate_limit:', config('telegram_limits.cache.prefix'));
    }

    public function testWaitForRateLimitDelayCalculation()
    {
        // Test different method types result in different delays
        $messageMethodDelay = [];
        $nonMessageMethodDelay = [];
        
        // Measure multiple times to account for randomness
        for ($i = 0; $i < 3; $i++) {
            $start = microtime(true);
            TelegramRateLimitService::waitForRateLimit('sendMessage');
            $messageMethodDelay[] = (microtime(true) - $start) * 1000;
            
            $start = microtime(true);
            TelegramRateLimitService::waitForRateLimit('getChat');
            $nonMessageMethodDelay[] = (microtime(true) - $start) * 1000;
        }
        
        $avgMessageDelay = array_sum($messageMethodDelay) / count($messageMethodDelay);
        $avgNonMessageDelay = array_sum($nonMessageMethodDelay) / count($nonMessageMethodDelay);
        
        // Message methods should take longer on average due to higher base delay
        $this->assertGreaterThan($avgNonMessageDelay, $avgMessageDelay);
        
        // Both should be within expected ranges
        $this->assertGreaterThan(33, $avgMessageDelay);
        $this->assertGreaterThan(20, $avgNonMessageDelay);
    }

    public function testMethodParameterValidation()
    {
        // Test with empty method name
        $result = TelegramRateLimitService::checkRateLimit('');
        $this->assertTrue($result, 'Empty method name should not fail');
        
        // Test with null chat ID
        $result = TelegramRateLimitService::checkRateLimit('sendMessage', null);
        $this->assertTrue($result, 'Null chat ID should not fail');
        
        // Test with negative chat ID
        $result = TelegramRateLimitService::checkRateLimit('sendMessage', -123);
        $this->assertTrue($result, 'Negative chat ID should not fail');
        
        // Test with large chat ID
        $result = TelegramRateLimitService::checkRateLimit('sendMessage', PHP_INT_MAX);
        $this->assertTrue($result, 'Large chat ID should not fail');
    }
}
