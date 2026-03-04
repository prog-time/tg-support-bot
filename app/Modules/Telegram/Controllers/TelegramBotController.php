<?php

namespace App\Modules\Telegram\Controllers;

use App\Actions\Ai\EditAiMessage;
use App\Models\BotUser;
use App\Modules\Telegram\Actions\BannedContactMessage;
use App\Modules\Telegram\Actions\CloseTopic;
use App\Modules\Telegram\Actions\SendAiAnswerMessage;
use App\Modules\Telegram\Actions\SendBannedMessage;
use App\Modules\Telegram\Actions\SendContactMessage;
use App\Modules\Telegram\Actions\SendStartMessage;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Modules\Telegram\Services\Tg\TgEditMessageService;
use App\Modules\Telegram\Services\Tg\TgMessageService;
use App\Modules\Telegram\Services\TgExternal\TgExternalEditService;
use App\Modules\Telegram\Services\TgExternal\TgExternalMessageService;
use App\Modules\Telegram\Services\TgVk\TgVkEditService;
use App\Modules\Telegram\Services\TgVk\TgVkMessageService;
use Illuminate\Http\Request;

class TelegramBotController
{
    private TelegramUpdateDto $dataHook;

    protected ?string $platform;

    private ?BotUser $botUser;

    public function __construct(Request $request)
    {
        $dataHook = TelegramUpdateDto::fromRequest($request);
        if (empty($dataHook)) {
            abort(200);
        }
        $this->dataHook = $dataHook;

        if ($this->dataHook->typeSource === 'private') {
            $this->botUser = (new BotUser())->getUserByChatId($this->dataHook->chatId, 'telegram');
            $this->platform = 'telegram';
        } else {
            $this->botUser = (new BotUser())->getByTopicId($this->dataHook->messageThreadId);
            $this->platform = $this->botUser->platform ?? null;
        }

        if (empty($this->botUser) || empty($this->platform)) {
            abort(200);
        }
    }

    /**
     * Check type source
     *
     * @return bool
     */
    protected function isSupergroup(): bool
    {
        return $this->dataHook->typeSource === 'supergroup';
    }

    /**
     * Check message
     *
     * @return void
     */
    protected function checkBotQuery(): void
    {
        if ($this->dataHook->pinnedMessageStatus) {
            return;
        }

        if ($this->dataHook->typeQuery === 'callback_query') {
            if (str_contains($this->dataHook->callbackData, 'topic_user_ban_')) {
                $banStatus = $this->dataHook->callbackData === 'topic_user_ban_true';
                app(BannedContactMessage::class)->execute($this->botUser, $banStatus, $this->dataHook->messageId);
            } elseif ($this->dataHook->callbackData === 'close_topic') {
                app(CloseTopic::class)->execute($this->botUser);
            }

            return;
        }
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    public function bot_query(): void
    {
        $this->checkBotQuery();
        if ($this->dataHook->editedTopicStatus && $this->dataHook->typeSource === 'supergroup') {
            SendTelegramSimpleQueryJob::dispatch(TGTextMessageDto::from([
                'methodQuery' => 'deleteMessage',
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'message_id' => $this->dataHook->messageId,
            ]));
        } elseif (!$this->dataHook->isBot) {
            if ($this->dataHook->typeSource === 'supergroup') {
                if ($this->dataHook->text === '/contact' && $this->isSupergroup()) {
                    app(SendContactMessage::class)->execute($this->botUser);
                    return;
                }
            }

            switch ($this->platform) {
                case 'telegram':
                    $this->controllerPlatformTg();
                    break;

                case 'vk':
                    $this->controllerPlatformVk();
                    break;

                case 'ignore':
                    return;

                default:
                    $this->controllerExternalPlatform();
                    break;
            }
        }
    }

    /**
     * Controller tg message
     *
     * @return void
     */
    private function controllerPlatformTg(): void
    {
        if ($this->botUser->isBanned() && $this->dataHook->typeSource === 'private') {
            app(SendBannedMessage::class)->execute($this->botUser);
            return;
        } elseif ($this->dataHook->aiTechMessage) {
            if (str_contains($this->dataHook->text, 'ai_message_edit_')) {
                app(EditAiMessage::class)->execute($this->dataHook);
            }
        } else {
            switch ($this->dataHook->typeQuery) {
                case 'message':
                    if ($this->dataHook->text === '/start' && !$this->isSupergroup()) {
                        app(SendStartMessage::class)->execute($this->dataHook);
                    } elseif (str_contains($this->dataHook->text, '/ai_generate') && $this->isSupergroup()) {
                        app(SendAiAnswerMessage::class)->execute($this->dataHook);
                    } else {
                        (new TgMessageService($this->dataHook))->handleUpdate();
                    }
                    break;

                case 'edited_message':
                    (new TgEditMessageService($this->dataHook))->handleUpdate();
                    break;

                default:
                    throw new \Exception("Unknown event type: {$this->dataHook->typeQuery}");
            }
        }
    }

    /**
     * Controller VK message.
     *
     * @return void
     */
    private function controllerPlatformVk(): void
    {
        switch ($this->dataHook->typeQuery) {
            case 'message':
                (new TgVkMessageService($this->dataHook))->handleUpdate();
                break;

            case 'edited_message':
                (new TgVkEditService($this->dataHook))->handleUpdate();
                break;

            default:
                throw new \Exception("Unknown event type: {$this->dataHook->typeQuery}");
        }
    }

    /**
     * Controller external message.
     *
     * @return void
     */
    private function controllerExternalPlatform(): void
    {
        switch ($this->dataHook->typeQuery) {
            case 'message':
                (new TgExternalMessageService($this->dataHook))->handleUpdate();
                break;

            case 'edited_message':
                (new TgExternalEditService($this->dataHook))->handleUpdate();
                break;

            default:
                throw new \Exception("Unknown event type: {$this->dataHook->typeQuery}");
        }
    }
}
