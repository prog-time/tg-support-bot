<?php

namespace App\Modules\Vk\Services;

use App\Models\BotUser;
use App\Modules\Ai\Jobs\SendAiDraftJob;
use App\Modules\Ai\Jobs\SendAiReplyJob;
use App\Modules\Ai\Services\ShouldAiReply;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendVkTelegramMessageJob;
use App\Modules\Telegram\Services\ActionService\Send\ToTgMessageService;
use App\Modules\Vk\DTOs\VkUpdateDto;
use Illuminate\Support\Facades\Log;

class VkMessageService extends ToTgMessageService
{
    protected string $source = 'vk';

    protected string $typeMessage = 'incoming';

    protected mixed $update;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    public function __construct(VkUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    public function handleUpdate(): void
    {
        try {
            if ($this->update->type !== 'message_new') {
                throw new \Exception('Unknown event type', 1);
            }

            if (!empty($this->update->listFileUrl)) {
                $this->sendDocument();
            } elseif (!empty($this->update->text)) {
                $this->sendMessage();
            } elseif (!empty($this->update->geo)) {
                $this->sendLocation();
            }
        } catch (\Throwable $e) {
            Log::channel('loki')->log($e->getCode() === 1 ? 'warning' : 'error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }

    /**
     * @return void
     */
    protected function sendDocument(): void
    {
        $this->messageParamsDTO->methodQuery = 'sendDocument';
        $this->messageParamsDTO->document = $this->update->listFileUrl[0];

        $this->messageParamsDTO->caption = $this->update->text ?? '';

        SendVkTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
        );
    }

    /**
     * @return void
     */
    protected function sendLocation(): void
    {
        $this->messageParamsDTO->methodQuery = 'sendLocation';
        $this->messageParamsDTO->latitude = $this->update->geo['coordinates']['latitude'];
        $this->messageParamsDTO->longitude = $this->update->geo['coordinates']['longitude'];

        SendVkTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
        );
    }

    /**
     * @return void
     */
    protected function sendMessage(): void
    {
        $this->messageParamsDTO->text = $this->update->text;

        SendVkTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
        );

        $this->maybeDispatchAi($this->update->text);
    }

    /**
     * Trigger AI generation for an incoming VK text message, when gating allows.
     *
     * The draft/auto-reply job is dispatched without a TelegramUpdateDto — the
     * jobs derive the platform from BotUser and assemble the supergroup post
     * and the platform-specific user delivery themselves.
     *
     * @param string|null $text
     *
     * @return void
     */
    protected function maybeDispatchAi(?string $text): void
    {
        if ($this->botUser === null) {
            return;
        }

        $shouldAiReply = app(ShouldAiReply::class);
        if (!$shouldAiReply->shouldGenerateForBotUserText($this->botUser, $text)) {
            return;
        }

        if ((bool) config('ai.auto_reply', false)) {
            SendAiReplyJob::dispatch($this->botUser->id, null, (string) $text);
        } else {
            SendAiDraftJob::dispatch($this->botUser->id, null, (string) $text);
        }
    }

    /**
     * @return void
     */
    protected function sendPhoto(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendSticker(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendContact(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendVideoNote(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendVoice(): void
    {
        //
    }
}
