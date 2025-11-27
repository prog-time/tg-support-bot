<?php

namespace App\Jobs\SendMessage;

use App\DTOs\TelegramTopicDto;
use App\Models\BotUser;
use App\Services\TgTopicService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

abstract class AbstractSendMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 20;

    public BotUser $botUser;

    public mixed $updateDto;

    public mixed $queryParams;

    public string $typeMessage = '';

    public TgTopicService $tgTopicService;

    abstract public function handle(): void;

    /**
     * Сохраняем сообщение в базу после успешной отправки
     *
     * @param BotUser $botUser
     * @param mixed   $resultQuery
     */
    abstract protected function saveMessage(BotUser $botUser, mixed $resultQuery): void;

    /**
     * Сохраняем сообщение в базу после успешной отправки
     *
     * @param mixed   $resultQuery
     * @param BotUser $botUser
     */
    abstract protected function editMessage(BotUser $botUser, mixed $resultQuery): void;

    /**
     * Обновляем тему в зависимости от типа источника
     *
     * @return void
     */
    protected function updateTopic(BotUser $botUser, string $typeMessage): void
    {
        $this->tgTopicService->editTgTopic(
            TelegramTopicDto::fromData([
                'message_thread_id' => $botUser->topic_id,
                'icon_custom_emoji_id' => __('icons.' . $typeMessage),
            ])
        );
    }
}
