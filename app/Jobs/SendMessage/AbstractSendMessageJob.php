<?php

namespace App\Jobs\SendMessage;

use App\Models\BotUser;
use App\Modules\External\DTOs\ExternalMessageDto;
use App\Modules\Telegram\Actions\BanMessage;
use App\Modules\Telegram\DTOs\TelegramAnswerDto;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendExternalTelegramMessageJob;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Modules\Telegram\Jobs\SendVkTelegramMessageJob;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use App\Modules\Vk\DTOs\VkUpdateDto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

abstract class AbstractSendMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    public mixed $updateDto;

    public mixed $queryParams;

    public string $typeMessage = '';

    abstract public function handle(): void;

    /**
     * Save message to database after successful sending.
     *
     * @param BotUser $botUser
     * @param mixed   $resultQuery
     */
    abstract protected function saveMessage(BotUser $botUser, mixed $resultQuery): void;

    /**
     * Edit message in database.
     *
     * @param mixed   $resultQuery
     * @param BotUser $botUser
     */
    abstract protected function editMessage(BotUser $botUser, mixed $resultQuery): void;

    /**
     * Update topic depending on source type.
     *
     * @return void
     */
    protected function updateTopic(BotUser $botUser, string $typeMessage): void
    {
        SendTelegramSimpleQueryJob::dispatch(TGTextMessageDto::from([
            'methodQuery' => 'editForumTopic',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $botUser->topic_id,
            'icon_custom_emoji_id' => __('icons.' . $typeMessage),
        ]));
    }

    protected function telegramResponseHandler(TelegramAnswerDto $response): void
    {
        if ($response->response_code === 429) {
            $retryAfter = $response->parameters->retry_after ?? 3;
            Log::channel('loki')->warning("429 Too Many Requests. Replay {$retryAfter}");
            $this->release($retryAfter);
            return;
        }

        if ($response->response_code === 400 && $response->type_error === 'MARKDOWN_ERROR') {
            Log::channel('loki')->warning('MARKDOWN_ERROR -> switching parse_mode to HTML');
            $this->queryParams->parse_mode = 'html';
            $this->release(1);
            return;
        }

        if ($response->response_code === 400 && in_array($response->type_error, ['TOPIC_NOT_FOUND', 'TOPIC_DELETED', 'TOPIC_ID_INVALID'])) {
            Log::channel('loki')->warning('TOPIC_NOT_FOUND/TOPIC_DELETED -> creating new topic');

            $retryJob = $this->getRetryJobInstance();
            if ($retryJob !== null) {
                if (!empty($this->botUserId)) {
                    BotUser::find($this->botUserId)->update([
                        'topic_id' => null,
                    ]);

                    TopicCreateJob::withChain([$retryJob])->dispatch($this->botUserId);
                }
            }

            return;
        }

        if ($response->response_code === 403) {
            Log::channel('loki')->warning('403 - user blocked the bot');
            app(BanMessage::class)->execute($this->botUserId, $this->updateDto);
            return;
        }

        Log::channel('loki')->error('Unknown error', ['response' => (array)$response]);
    }

    /**
     * @return ShouldQueue|null
     */
    protected function getRetryJobInstance(): ?ShouldQueue
    {
        if (!empty($this->updateDto)) {
            if ($this->updateDto instanceof ExternalMessageDto) {
                return new SendExternalTelegramMessageJob(
                    $this->botUserId,
                    $this->updateDto,
                    $this->queryParams,
                    $this->typeMessage
                );
            }

            if ($this->updateDto instanceof TelegramUpdateDto) {
                return new SendTelegramMessageJob(
                    $this->botUserId,
                    $this->updateDto,
                    $this->queryParams,
                    $this->typeMessage
                );
            }

            if ($this->updateDto instanceof VkUpdateDto) {
                return new SendVkTelegramMessageJob(
                    $this->botUserId,
                    $this->updateDto,
                    $this->queryParams,
                );
            }
        }

        return null;
    }
}
