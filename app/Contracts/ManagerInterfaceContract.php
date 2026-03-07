<?php

namespace App\Contracts;

use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;

interface ManagerInterfaceContract
{
    /**
     * Уведомить менеджеров о входящем сообщении от пользователя.
     * В режиме telegram_group — постит сообщение в форум-топик.
     * В режиме admin_panel — сохраняет событие для отображения в веб-панели.
     *
     * @param BotUser           $botUser Пользователь, от которого пришло сообщение
     * @param TelegramUpdateDto $dto     Данные сообщения
     *
     * @return void
     */
    public function notifyIncomingMessage(BotUser $botUser, TelegramUpdateDto $dto): void;

    /**
     * Инициировать новый диалог (первое сообщение от пользователя).
     * В режиме telegram_group — создаёт форум-топик.
     * В режиме admin_panel — ничего дополнительного (диалог виден в панели).
     *
     * @param int $botUserId ID нового пользователя
     *
     * @return void
     */
    public function createConversation(int $botUserId): void;
}
