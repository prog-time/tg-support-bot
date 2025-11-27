<?php

namespace App\Jobs;

use App\Actions\Telegram\GetChat;
use App\Actions\Telegram\SendContactMessage;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use App\Models\ExternalUser;
use App\TelegramBot\TelegramMethods;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;

class TopicCreateJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 180, 300];

    private BotUser $botUser;

    private TelegramMethods $telegramMethods;

    private int $botUserId;

    public function __construct(
        int $botUserId,
        TelegramMethods $telegramMethods = null,
    ) {
        $this->botUserId = $botUserId;

        $this->telegramMethods = $telegramMethods ?? new TelegramMethods();
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        try {
            $this->botUser = BotUser::find($this->botUserId); // всегда свежие данные

            $topicName = $this->generateNameTopic($this->botUser);

            $response = $this->telegramMethods->sendQueryTelegram('createForumTopic', [
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'name' => $topicName,
                'icon_custom_emoji_id' => __('icons.incoming'),
            ]);

            // ✅ Успешная отправка
            if ($response->ok === true) {
                $this->botUser->topic_id = $response->message_thread_id;
                $this->botUser->save();

                (new SendContactMessage())->executeByBotUser($this->botUser);
                return;
            }

            // ✅ 429 Too Many Requests
            if ($response->response_code === 429) {
                $retryAfter = $response->parameters->retry_after ?? 3;
                Log::warning("429 Too Many Requests. Повтор через {$retryAfter} сек.");
                $this->release($retryAfter);
                return;
            }

            // ✅ Неизвестная ошибка
            Log::error('TopicCreateJob: неизвестная ошибка', [
                'response' => (array)$response,
            ]);
        } catch (\Exception $e) {
            (new LokiLogger())->logException($e);
        }
    }

    /**
     * Генерируем название чата
     *
     * @param BotUser $botUser
     *
     * @return string
     */
    protected function generateNameTopic(BotUser $botUser): string
    {
        try {
            if ($botUser->platform === 'external_source') {
                $source = ExternalUser::getSourceById($botUser->chat_id);
                return "#{$botUser->chat_id} ({$source})";
            }

            $templateTopicName = config('traffic_source.settings.telegram.template_topic_name');
            if (empty($templateTopicName)) {
                throw new \Exception('Template not found');
            }

            if (preg_match('/(\{platform})/', $templateTopicName)) {
                $templateTopicName = str_replace('{platform}', $botUser->platform, $templateTopicName);
            }

            $nameParts = $this->getPartsGenerateName($botUser->chat_id);
            if (empty($nameParts)) {
                throw new \Exception('Name parts not found');
            }

            // parsing template
            preg_match_all('/{([^}]+)}/', $templateTopicName, $matches);
            if (empty($matches[1])) {
                throw new \Exception('Params template topic name not found');
            }

            $paramsParts = array_combine($matches[0], $matches[1]);

            $topicName = $templateTopicName;
            foreach ($paramsParts as $key => $param) {
                if (empty($nameParts[$param])) {
                    throw new \Exception('Params template topic name not found');
                }
                $topicName = str_replace($key, $nameParts[$param], $topicName);
            }

            return $topicName;
        } catch (\Exception $e) {
            return '#' . $botUser->chat_id . ' (' . $botUser->platform . ')';
        }
    }

    /**
     * Получаем части для генерации названия чата
     *
     * @param int $chatId
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function getPartsGenerateName(int $chatId): array
    {
        try {
            $chatDataQuery = GetChat::execute($chatId);
            if (!$chatDataQuery->ok) {
                throw new \Exception('ChatData not found');
            }

            $chatData = $chatDataQuery->rawData['result'];
            if (empty($chatData)) {
                throw new \Exception('ChatData not found');
            }

            $neededKeys = [
                'id',
                'email',
                'first_name',
                'last_name',
                'username',
            ];
            return array_intersect_key($chatData, array_flip($neededKeys));
        } catch (Exception $e) {
            return [];
        }
    }
}
