<?php

namespace App\Modules\Telegram\Services;

use App\Contracts\ManagerInterfaceContract;
use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use App\Modules\Telegram\Services\Tg\TgMessageService;

class TelegramGroupInterface implements ManagerInterfaceContract
{
    /**
     * Переслать входящее сообщение в форум-топик Telegram-группы.
     *
     * @param BotUser           $botUser Пользователь, от которого пришло сообщение
     * @param TelegramUpdateDto $dto     Данные сообщения
     *
     * @return void
     */
    public function notifyIncomingMessage(BotUser $botUser, TelegramUpdateDto $dto): void
    {
        (new TgMessageService($dto))->handleUpdate();
    }

    /**
     * Создать форум-топик для нового пользователя.
     *
     * @param int $botUserId ID нового пользователя
     *
     * @return void
     */
    public function createConversation(int $botUserId): void
    {
        TopicCreateJob::dispatch($botUserId);
    }
}
