<?php

namespace Tests\Unit\Logging;

use App\Logging\LokiLogger;
use Exception;
use Tests\TestCase;

class LokiLoggerTest extends TestCase
{
    protected LokiLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new LokiLogger();
    }

    public function testLogReturnsBool(): void
    {
        $result = $this->logger->log('info', 'Test message');

        $this->assertTrue($result);
    }

    public function testSendBasicLogDoesNotThrow(): void
    {
        $exception = new Exception('Test exception');

        try {
            $this->logger->sendBasicLog($exception);
        } catch (\Throwable $e) {
            $this->fail('sendBasicLog should not throw an exception.');
        }
    }

    public function testLogExceptionReturnsBool(): void
    {
        $exception = new Exception('Test logException', 1);

        $result = $this->logger->logException($exception);

        $this->assertTrue($result);
    }
}
