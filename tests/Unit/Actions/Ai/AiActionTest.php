<?php

namespace Tests\Unit\Actions\Ai;

use App\Actions\Telegram\SendMessage;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Models\AiMessage;
use App\Models\BotUser;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class AiActionTest extends TestCase
{
    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->botUser = $this->botTestUser();
    }

    /**
     * @param int         $messageId
     * @param string      $textMessage
     * @param string|null $messageData
     *
     * @return array
     */
    public function generateMessage(int $messageId, string $textMessage, ?string $messageData = null): array
    {
        return [
            'update_id' => time(),
            'callback_query' => [
                'id' => time(),
                'from' => [
                    'id' => config('testing.tg_private.chat_id'),
                    'is_bot' => false,
                    'first_name' => config('testing.tg_private.first_name'),
                    'last_name' => config('testing.tg_private.last_name'),
                    'username' => config('testing.tg_private.username'),
                    'language_code' => 'ru',
                ],
                'message' => [
                    'message_id' => $messageId,
                    'from' => [
                        'id' => time(),
                        'is_bot' => true,
                        'first_name' => 'Prog-Time AI',
                        'username' => config('testing.tg_bot_ai.username'),
                    ],
                    'chat' => [
                        'id' => config('traffic_source.settings.telegram.group_id'),
                        'title' => 'Prog-Time | Чаты',
                        'is_forum' => true,
                        'type' => 'supergroup',
                    ],
                    'date' => time(),
                    'edit_date' => time(),
                    'message_thread_id' => $this->botUser->topic_id,
                    'text' => $textMessage,
                    'is_topic_message' => true,
                ],
                'data' => $messageData,
            ],
        ];
    }

    /**
     * @return BotUser
     */
    public function botTestUser(): BotUser
    {
        return BotUser::where('chat_id', config('testing.tg_private.chat_id'))->first();
    }

    /**
     * @return array
     */
    public function getQueryParams(): array
    {
        return [
            'methodQuery' => 'sendMessage',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
        ];
    }

    /**
     * @param string $textMessage
     *
     * @return TelegramAnswerDto
     */
    public function sendGenerateAiMessage(string $textMessage = 'Hello'): TelegramAnswerDto
    {
        $generateAiMessage = "/ai_generate {$textMessage}";
        $dtoQueryParams = TGTextMessageDto::from(array_merge($this->getQueryParams(), [
            'text' => $generateAiMessage,
        ]));

        return SendMessage::execute($this->botUser, $dtoQueryParams);
    }

    /**
     * @param int         $messageId
     * @param string|null $callbackData
     * @param string|null $textMessage
     *
     * @return TelegramUpdateDto
     */
    public function createDto(int $messageId, ?string $callbackData = null, ?string $textMessage = null): TelegramUpdateDto
    {
        $generateAiMessage = $textMessage ?? '/ai_generate Напиши приветствие';

        $request = Request::create(
            'api/telegram/ai/bot',
            'POST',
            $this->generateMessage($messageId, $generateAiMessage, $callbackData)
        );

        return TelegramUpdateDto::fromRequest($request);
    }

    /**
     * @param int $userBotId
     * @param int $messageId
     *
     * @return AiMessage
     */
    public function createAiMessage(int $userBotId, int $messageId): AiMessage
    {
        return AiMessage::create([
            'bot_user_id' => $userBotId,
            'message_id' => $messageId,
            'text_ai' => 'Это тестовый ответ от AI',
            'text_manager' => 'Это тестовый запрос от менеджера',
        ]);
    }
}
