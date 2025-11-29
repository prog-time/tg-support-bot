<?php

namespace App\Jobs\SendMessage;

use App\Actions\Telegram\BanMessage;
use App\DTOs\External\ExternalMessageDto;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramTopicDto;
use App\DTOs\TelegramUpdateDto;
use App\Jobs\TopicCreateJob;
use App\Models\BotUser;
use App\Services\TgTopicService;
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
        (new TgTopicService())->editTgTopic(
            TelegramTopicDto::fromData([
                'message_thread_id' => $botUser->topic_id,
                'icon_custom_emoji_id' => __('icons.' . $typeMessage),
            ])
        );
    }

    protected function telegramResponseHandler(TelegramAnswerDto $response): void
    {
        // ✅ 429 Too Many Requests
        if ($response->response_code === 429) {
            $retryAfter = $response->parameters->retry_after ?? 3;
            Log::warning("429 Too Many Requests. Повтор через {$retryAfter} сек.");
            $this->release($retryAfter);
            return;
        }

        // ✅ 400 MARKDOWN_ERROR
        if ($response->response_code === 400 && $response->type_error === 'MARKDOWN_ERROR') {
            Log::warning('MARKDOWN_ERROR → переключаем parse_mode в HTML');
            $this->queryParams->parse_mode = 'html';
            $this->release(1);
            return;
        }

        // ✅ 400 TOPIC_NOT_FOUND
        if ($response->response_code === 400 && $response->type_error === 'TOPIC_NOT_FOUND') {
            Log::warning('TOPIC_NOT_FOUND → создаём новую тему');

            if ($this->updateDto instanceof ExternalMessageDto) {
                TopicCreateJob::withChain([
                    new SendExternalTelegramMessageJob(
                        $this->botUserId,
                        $this->updateDto,
                        $this->queryParams,
                        $this->typeMessage
                    ),
                ])->dispatch($this->botUserId);
            } elseif ($this->updateDto instanceof TelegramUpdateDto) {
                TopicCreateJob::withChain([
                    new SendTelegramMessageJob(
                        $this->botUserId,
                        $this->updateDto,
                        $this->queryParams,
                        $this->typeMessage
                    ),
                ])->dispatch($this->botUserId);
            }

            return;
        }

        // ✅ 403 — пользователь заблокировал бота
        if ($response->response_code === 403) {
            Log::warning('403 — пользователь заблокировал бота');
            BanMessage::execute($this->botUserId, $this->updateDto);
            return;
        }

        // ✅ Неизвестная ошибка
        Log::error('SendVkTelegramMessageJob: неизвестная ошибка', [
            'response' => (array)$response,
        ]);
    }
}
