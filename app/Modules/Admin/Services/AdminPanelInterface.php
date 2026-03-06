<?php

namespace App\Modules\Admin\Services;

use App\Contracts\ManagerInterfaceContract;
use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;

class AdminPanelInterface implements ManagerInterfaceContract
{
    /**
     * In admin_panel mode the incoming message is already persisted by
     * AbstractSendMessageJob::saveMessage(). Livewire polling will pick
     * it up on the next interval.
     *
     * @param BotUser           $botUser User who sent the message
     * @param TelegramUpdateDto $dto     Message data
     *
     * @return void
     */
    public function notifyIncomingMessage(BotUser $botUser, TelegramUpdateDto $dto): void
    {
        // Message is already in DB. Livewire polling refreshes the UI automatically.
    }

    /**
     * In admin_panel mode a Telegram forum topic is not required.
     * Conversations are visible via ConversationResource using BotUser model.
     *
     * @param int $botUserId New user ID
     *
     * @return void
     */
    public function createConversation(int $botUserId): void
    {
        // No-op: conversation is automatically visible in ConversationResource.
    }
}
