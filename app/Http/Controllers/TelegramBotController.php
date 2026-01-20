<?php

namespace App\Http\Controllers;

use App\Actions\Ai\EditAiMessage;
use App\Actions\Telegram\BannedContactMessage;
use App\Actions\Telegram\CloseTopic;
use App\Actions\Telegram\SendAiAnswerMessage;
use App\Actions\Telegram\SendBannedMessage;
use App\Actions\Telegram\SendContactMessage;
use App\Actions\Telegram\SendStartMessage;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\SendTelegramSimpleQueryJob;
use App\Models\BotUser;
use App\Services\Tg\TgEditMessageService;
use App\Services\Tg\TgMessageService;
use App\Services\TgExternal\TgExternalEditService;
use App\Services\TgExternal\TgExternalMessageService;
use App\Services\TgVk\TgVkEditService;
use App\Services\TgVk\TgVkMessageService;
use Illuminate\Http\Request;

class TelegramBotController
{
    private TelegramUpdateDto $dataHook;

    protected ?string $platform;

    private ?BotUser $botUser;

    public function __construct(Request $request)
    {
        $dataHook = TelegramUpdateDto::fromRequest($request);
        $this->dataHook = !empty($dataHook) ? $dataHook : die();

        if ($this->dataHook->typeSource === 'private') {
            $this->botUser = (new BotUser())->getUserByChatId($this->dataHook->chatId, 'telegram');
            $this->platform = 'telegram';
        } else {
            $this->botUser = (new BotUser())->getByTopicId($this->dataHook->messageThreadId);
            $this->platform = $this->botUser->platform ?? null;
        }

        if (empty($this->platform)) {
            die();
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
            die();
        }

        if ($this->dataHook->typeQuery === 'callback_query') {
            if (str_contains($this->dataHook->callbackData, 'topic_user_ban_')) {
                $banStatus = $this->dataHook->callbackData === 'topic_user_ban_true';
                (new BannedContactMessage())->execute($this->botUser, $banStatus, $this->dataHook->messageId);
            } elseif ($this->dataHook->callbackData === 'close_topic') {
                (new CloseTopic())->execute($this->botUser);
            }

            die();
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
                    (new SendContactMessage())->execute($this->botUser);
                    die();
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
            (new SendBannedMessage())->execute($this->botUser);
            die();
        } elseif ($this->dataHook->aiTechMessage) {
            if (str_contains($this->dataHook->text, 'ai_message_edit_')) {
                (new EditAiMessage())->execute($this->dataHook);
            }
        } else {
            switch ($this->dataHook->typeQuery) {
                case 'message':
                    if ($this->dataHook->text === '/start' && !$this->isSupergroup()) {
                        (new SendStartMessage())->execute($this->dataHook);
                    } elseif (str_contains($this->dataHook->text, '/ai_generate') && $this->isSupergroup()) {
                        (new SendAiAnswerMessage())->execute($this->dataHook);
                    } else {
                        (new TgMessageService($this->dataHook))->handleUpdate();
                    }
                    break;

                case 'edited_message':
                    (new TgEditMessageService($this->dataHook))->handleUpdate();
                    break;

                default:
                    throw new \Exception("Неизвестный тип события: {$this->dataHook->typeQuery}");
            }
        }
    }

    /**
     * Controller vk message
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
                throw new \Exception("Неизвестный тип события: {$this->dataHook->typeQuery}");
        }
    }

    /**
     * Controller external message
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
                throw new \Exception("Неизвестный тип события: {$this->dataHook->typeQuery}");
        }
    }
}
