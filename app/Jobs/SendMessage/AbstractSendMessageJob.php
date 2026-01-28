<?php

namespace App\Jobs\SendMessage;

use App\Actions\Telegram\BanMessage;
use App\DTOs\External\ExternalMessageDto;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\DTOs\Vk\VkUpdateDto;
use App\Jobs\SendTelegramSimpleQueryJob;
use App\Jobs\TopicCreateJob;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
     * Сохраняем сообщение в базу после успешной отправки
     *
     * @param BotUser $botUser
     * @param mixed   $resultQuery
     */
    abstract protected function saveMessage(BotUser $botUser, mixed $resultQuery): void;

    /**
     * Сохраняем сообщение в базу после успешной отправки
     *
     * @param mixed   $resultQuery
     * @param BotUser $botUser
     */
    abstract protected function editMessage(BotUser $botUser, mixed $resultQuery): void;

    /**
     * Обновляем тему в зависимости от типа источника
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
        // ✅ 429 Too Many Requests
        if ($response->response_code === 429) {
            $retryAfter = $response->parameters->retry_after ?? 3;
            (new LokiLogger())->log('warning', "429 Too Many Requests. Replay {$retryAfter}");
            sleep(10);
            // Job автоматически будет повторён Laravel Queue
            $this->release($retryAfter);
            return;
        }

        // ✅ 400 MARKDOWN_ERROR
        if ($response->response_code === 400 && $response->type_error === 'MARKDOWN_ERROR') {
            (new LokiLogger())->log('warning', "MARKDOWN_ERROR → переключаем parse_mode в HTML");
            $this->queryParams->parse_mode = 'html';
            // Повторяем отправку с исправленным parse_mode
            $this->release(1);
            return;
        }

        // ✅ 400 TOPIC_NOT_FOUND / TOPIC_DELETED
        if ($response->response_code === 400 && in_array($response->type_error, ['TOPIC_NOT_FOUND', 'TOPIC_DELETED', 'TOPIC_ID_INVALID'])) {
            (new LokiLogger())->log('warning', "TOPIC_NOT_FOUND/TOPIC_DELETED → создаём новую тему");

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

        // ✅ 403 — пользователь заблокировал бота
        if ($response->response_code === 403) {
            (new LokiLogger())->log('warning', "403 — пользователь заблокировал бота");
            BanMessage::execute($this->botUserId, $this->updateDto);
            return;
        }

        // ✅ Неизвестная ошибка
        (new LokiLogger())->log('error', [
            'response' => (array)$response,
        ]);
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
