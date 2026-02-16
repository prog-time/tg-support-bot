<?php

namespace Tests\Unit\Logging;

use App\Logging\LokiHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

class LokiHandlerTest extends TestCase
{
    public function test_handle_sends_log_to_loki(): void
    {
        /** @var Client&Mockery\MockInterface $clientMock */
        $clientMock = Mockery::mock(Client::class);

        /** @phpstan-ignore-next-line */
        $clientMock->shouldReceive('post')
            ->once()
            ->with(
                'http://loki-test.com',
                Mockery::on(fn ($args) => isset($args['json']['streams']))
            )
            ->andReturn(new Response(204));

        $handler = new class('http://loki-test.com', Level::Debug, true) extends LokiHandler {
            /** @param Client&\Mockery\MockInterface $client */
            public function setClient(Client $client): void
            {
                $this->client = $client;
            }
        };

        $handler->setClient($clientMock);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'loki',
            level: Level::Error,
            message: 'Test error message',
            context: [],
            extra: [],
        );

        $handler->handle($record);
    }

    public function test_write_builds_correct_payload(): void
    {
        $capturedPayload = null;

        /** @var Client&Mockery\MockInterface $clientMock */
        $clientMock = Mockery::mock(Client::class);

        /** @phpstan-ignore-next-line */
        $clientMock->shouldReceive('post')
            ->once()
            ->withArgs(function (string $url, array $options) use (&$capturedPayload) {
                $capturedPayload = $options['json'] ?? null;
                return true;
            })
            ->andReturn(new Response(204));

        $handler = new class('http://loki-test.com', Level::Debug, true) extends LokiHandler {
            /** @param Client&\Mockery\MockInterface $client */
            public function setClient(Client $client): void
            {
                $this->client = $client;
            }
        };

        $handler->setClient($clientMock);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'loki',
            level: Level::Error,
            message: 'Test error message',
            context: ['source' => 'tg_request'],
            extra: [],
        );

        $handler->handle($record);

        $this->assertNotNull($capturedPayload);
        $this->assertArrayHasKey('streams', $capturedPayload);

        $stream = $capturedPayload['streams'][0];
        $this->assertEquals('error', $stream['stream']['level']);
        $this->assertEquals('tg_request', $stream['stream']['source']);
        $this->assertEquals('Test error message', $stream['values'][0][1]);
    }

    public function test_write_does_not_throw_on_client_failure(): void
    {
        /** @var Client&Mockery\MockInterface $clientMock */
        $clientMock = Mockery::mock(Client::class);

        /** @phpstan-ignore-next-line */
        $clientMock->shouldReceive('post')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused'));

        $handler = new class('http://loki-test.com', Level::Debug, true) extends LokiHandler {
            /** @param Client&\Mockery\MockInterface $client */
            public function setClient(Client $client): void
            {
                $this->client = $client;
            }
        };

        $handler->setClient($clientMock);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'loki',
            level: Level::Error,
            message: 'Test error',
            context: [],
            extra: [],
        );

        $handler->handle($record);
    }
}
