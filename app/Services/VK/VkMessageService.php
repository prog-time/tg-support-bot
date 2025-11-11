<?php

namespace App\Services\VK;

use App\DTOs\TGTextMessageDto;
use App\DTOs\Vk\VkUpdateDto;
use App\Jobs\SendVkTelegramMessageJob;
use App\Models\BotUser;
use App\Services\ActionService\Send\ToTgMessageService;
use App\Services\TgTopicService;
use phpDocumentor\Reflection\Exception;

class VkMessageService extends ToTgMessageService
{
    protected string $source = 'vk';

    protected string $typeMessage = 'incoming';

    protected mixed $update;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    protected TgTopicService $tgTopicService;

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
                throw new \Exception('Неизвестный тип события', 1);
            }

            if (!empty($this->update->listFileUrl)) {
                $this->sendDocument();
            } elseif (!empty($this->update->text)) {
                $this->sendMessage();
            } elseif (!empty($this->update->geo)) {
                $this->sendLocation();
            }

            echo 'ok';
        } catch (Exception $e) {
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
            $this->botUser,
            $this->update,
            $this->messageParamsDTO,
            $this->typeMessage,
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
            $this->botUser,
            $this->update,
            $this->messageParamsDTO,
            $this->typeMessage,
        );
    }

    /**
     * @return void
     */
    protected function sendMessage(): void
    {
        $this->messageParamsDTO->text = $this->update->text;

        SendVkTelegramMessageJob::dispatch(
            $this->botUser,
            $this->update,
            $this->messageParamsDTO,
            $this->typeMessage,
        );
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
