<?php

namespace App\Services\External;

use App\DTOs\External\ExternalMessageAnswerDto;
use App\Models\BotUser;
use App\Models\ExternalUser;
use App\DTOs\TGTextMessageDto;
use App\Services\TgTopicService;
use App\DTOs\External\ExternalMessageDto;
use phpDocumentor\Reflection\Exception;

abstract class ExternalService
{
    protected string $typeMessage = '';
    protected ExternalMessageDto $update;
    protected TgTopicService $tgTopicService;
    protected ?BotUser $botUser;
    protected ?ExternalUser $externalUser;
    protected TGTextMessageDto $messageParamsDTO;

    public function __construct(ExternalMessageDto $update) {
        $this->update = $update;
        $this->tgTopicService = new TgTopicService();

        $this->botUser = $this->getBotUser($this->update);

        if (empty($this->botUser)) {
            throw new \Exception('Пользователя не существует!');
        }

        $this->messageParamsDTO = TGTextMessageDto::from([
            'methodQuery' => 'editTextMessage',
            'typeSource' => 'private',
            'chat_id' => env('TELEGRAM_GROUP_ID'),
            'message_thread_id' => $this->botUser->topic_id,
        ]);
    }

    /**
     * Get user data
     *
     * @param ExternalMessageDto $updateData
     * @return BotUser|null
     */
    protected function getBotUser(ExternalMessageDto $updateData): ?BotUser
    {
        try {
            $this->externalUser = ExternalUser::firstOrCreate([
                'external_id' => $updateData->external_id,
                'source' => $updateData->source
            ]);

            if (empty($this->externalUser)) {
                throw new Exception('External user not found!');
            }

            return BotUser::firstOrCreate([
                'chat_id' => $this->externalUser->id,
                'platform' => 'external_source',
            ]);
        } catch (\Exception $e) {
            return null;
        }
    }

    abstract public function handleUpdate(): ExternalMessageAnswerDto;

}
