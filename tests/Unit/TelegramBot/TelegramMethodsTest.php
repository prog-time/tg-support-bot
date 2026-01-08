<?php

namespace Tests\Unit\TelegramBot;

use App\TelegramBot\TelegramMethods;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramMethodsTest extends TestCase
{
    private int $chatId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chatId = time();
    }

    protected function getMessageParams(): array
    {
        return [
            'chat_id' => $this->chatId,
        ];
    }

    public function test_send_text_message(): void
    {
        $testMessage = 'Тестовое сообщение';

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => time(),
                    'from' => [
                        'id' => time(),
                        'is_bot' => true,
                        'first_name' => 'Prog-Time |Администратор сайта',
                        'username' => 'prog_time_bot',
                    ],
                    'chat' => [
                        'id' => time(),
                        'first_name' => 'Test',
                        'last_name' => 'test_file_id',
                        'username' => 'usertest',
                        'type' => 'private',
                    ],
                    'date' => time(),
                    'text' => $testMessage,
                ],
            ]),
        ]);

        $queryParams = array_merge($this->getMessageParams(), [
            'text' => $testMessage,
        ]);

        $resultQuery = TelegramMethods::sendQueryTelegram('sendMessage', $queryParams);

        $this->assertTrue($resultQuery->ok);

        $this->assertNotEmpty($resultQuery->response_code);
        $this->assertEquals($testMessage, $resultQuery->text);
    }

    public function test_send_document_and_caption(): void
    {
        $testMessage = 'Тестовое сообщение';

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => time(),
                    'from' => [
                        'id' => time(),
                        'is_bot' => true,
                        'first_name' => 'Prog-Time |Администратор сайта',
                        'username' => 'prog_time_bot',
                    ],
                    'chat' => [
                        'id' => time(),
                        'first_name' => 'Test',
                        'last_name' => 'test_file_id',
                        'username' => 'usertest',
                        'type' => 'private',
                    ],
                    'date' => time(),
                    'document' => [
                        'file_name' => '119f98712538b4d27f0290c798d1f011.png',
                        'mime_type' => 'image/png',
                        'thumbnail' => [
                            'file_id' => 'test_file_id',
                            'file_unique_id' => 'AQADVoQAAi678Uly',
                            'file_size' => 13279,
                            'width' => 320,
                            'height' => 210,
                        ],
                        'thumb' => [
                            'file_id' => 'test_file_id',
                            'file_unique_id' => 'AQADVoQAAi678Uly',
                            'file_size' => 13279,
                            'width' => 320,
                            'height' => 210,
                        ],
                        'file_id' => 'test_file_id',
                        'file_unique_id' => 'AgADVoQAAi678Uk',
                        'file_size' => 1052715,
                    ],
                    'caption' => $testMessage,
                ],
            ]),
        ]);

        $queryParams = array_merge($this->getMessageParams(), [
            'caption' => $testMessage,
            'document' => 'BQACAgIAAxkBAAIHOmi-0ihwIBW1gZH2kie-2qZ39FKUAAJWhAACLrvxSdnwd0Zd4TtpNgQ',
        ]);

        $resultQuery = TelegramMethods::sendQueryTelegram('sendDocument', $queryParams);

        $this->assertTrue($resultQuery->ok);

        $this->assertEquals($resultQuery->response_code, 200);
        $this->assertEquals($testMessage, $resultQuery->text);
    }

    public function test_send_photo_and_caption(): void
    {
        $testMessage = 'Тестовое сообщение';

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => time(),
                    'from' => [
                        'id' => time(),
                        'is_bot' => true,
                        'first_name' => 'Prog-Time |Администратор сайта',
                        'username' => 'prog_time_bot',
                    ],
                    'chat' => [
                        'id' => time(),
                        'first_name' => 'Тестовый',
                        'last_name' => 'test_file_id',
                        'username' => config('testing.tg_private.username'),
                        'type' => 'private',
                    ],
                    'date' => time(),
                    'photo' => [
                        [
                            'file_id' => time(),
                            'file_unique_id' => 'AQADcPoxGy67-Ul4',
                            'file_size' => 899,
                            'width' => 90,
                            'height' => 58,
                        ],
                        [
                            'file_id' => 'test_file_id',
                            'file_unique_id' => 'AQADcPoxGy67-Uly',
                            'file_size' => 12933,
                            'width' => 320,
                            'height' => 208,
                        ],
                        [
                            'file_id' => 'test_file_id',
                            'file_unique_id' => 'AQADcPoxGy67-Ul9',
                            'file_size' => 56681,
                            'width' => 800,
                            'height' => 521,
                        ],
                        [
                            'file_id' => 'test_file_id',
                            'file_unique_id' => 'AQADcPoxGy67-Ul-',
                            'file_size' => 83643,
                            'width' => 1280,
                            'height' => 833,
                        ],
                    ],
                    'caption' => $testMessage,
                ],
            ]),
        ]);

        $queryParams = array_merge($this->getMessageParams(), [
            'caption' => $testMessage,
            'photo' => 'AgACAgIAAxkBAAIHO2i-0nqM0rxqaqBPjrcf9937EzNRAAJw-jEbLrv5SSpf9j0qc59iAQADAgADeQADNgQ',
        ]);

        $resultQuery = TelegramMethods::sendQueryTelegram('sendPhoto', $queryParams);

        $this->assertTrue($resultQuery->ok);

        $this->assertEquals($resultQuery->response_code, 200);
        $this->assertEquals($testMessage, $resultQuery->text);
    }
}
