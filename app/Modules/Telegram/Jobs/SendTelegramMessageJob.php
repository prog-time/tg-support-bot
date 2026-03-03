<?php

namespace App\Modules\Telegram\Jobs;

use App\Jobs\SendMessage\AbstractSendMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TelegramAnswerDto;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use Illuminate\Support\Facades\Log;

class SendTelegramMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    /** @var TelegramUpdateDto */
    public mixed $updateDto;

    /** @var TGTextMessageDto */
    public mixed $queryParams;

    public string $typeMessage;

    private TelegramMethods $telegramMethods;

    public function __construct(
        int $botUserId,
        TelegramUpdateDto $updateDto,
        TGTextMessageDto $queryParams,
        string $typeMessage,
        ?TelegramMethods $telegramMethods = null,
    ) {
        $this->botUserId = $botUserId;
        $this->updateDto = $updateDto;
        $this->queryParams = $queryParams;
        $this->typeMessage = $typeMessage;

        $this->telegramMethods = $telegramMethods ?? new TelegramMethods();
    }

    public function handle(): void
    {
        try {
            $botUser = BotUser::find($this->botUserId);

            $methodQuery = $this->queryParams->methodQuery;
            $params = $this->queryParams->toArray();

            if ($this->typeMessage === 'incoming') {
                if ($botUser->topic_id) {
                    $response = $this->telegramMethods->sendQueryTelegram(
                        'editForumTopic',
                        [
                            'chat_id' => config('traffic_source.settings.telegram.group_id'),
                            'message_thread_id' => $botUser->topic_id,
                            'icon_custom_emoji_id' => __('icons.incoming'),
                        ]
                    );

                    if ($response->isTopicNotFound) {
                        $botUser->update([
                           'topic_id' => null,
                        ]);

                        $botUser->refresh();
                    } else {
                        $params['message_thread_id'] = $botUser->topic_id;
                    }
                }

                if (!$botUser->topic_id) {
                    TopicCreateJob::withChain([
                        new SendTelegramMessageJob(
                            $this->botUserId,
                            $this->updateDto,
                            $this->queryParams,
                            $this->typeMessage
                        ),
                    ])->dispatch($this->botUserId);
                    return;
                }
            }

            $response = $this->telegramMethods->sendQueryTelegram(
                $methodQuery,
                $params,
                $this->queryParams->token
            );

            if ($response->ok === true) {
                if ($methodQuery !== 'editMessageText' && $methodQuery !== 'editMessageCaption') {
                    $this->saveMessage($botUser, $response);
                    $this->updateTopic($botUser, $this->typeMessage);
                    return;
                }
            } else {
                $this->telegramResponseHandler($response);
            }
        } catch (\Throwable $e) {
            Log::channel('loki')->log($e->getCode() === 1 ? 'warning' : 'error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }

    /**
     * Save message to database after successful sending.
     *
     * @param BotUser $botUser
     * @param mixed   $resultQuery
     *
     * @return void
     */
    protected function saveMessage(BotUser $botUser, mixed $resultQuery): void
    {
        if (!$resultQuery instanceof TelegramAnswerDto) {
            throw new \Exception('Expected TelegramAnswerDto', 1);
        }

        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->platform,
            'message_type' => $this->typeMessage,
            'from_id' => $this->updateDto->messageId,
            'to_id' => $resultQuery->message_id,
        ]);
    }

    /**
     * Edit message in database.
     *
     * @param mixed $resultQuery
     *
     * @return void
     */
    protected function editMessage(BotUser $botUser, mixed $resultQuery): void
    {
        //
    }
}
