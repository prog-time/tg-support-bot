<?php

namespace App\Services;

use App\Actions\Telegram\DeleteMessage;
use App\Actions\Telegram\GetChat;
use App\DTOs\TelegramTopicDto;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\Models\ExternalUser;
use App\TelegramBot\TelegramMethods;
use Mockery\Exception;

class TgTopicService
{
    /**
     * Get parts chat data
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

    /**
     * Generate topic name
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
     * Create new topic
     *
     * @param BotUser $botUser
     *
     * @return TelegramTopicDto|null
     */
    public function createNewTgTopic(BotUser $botUser): ?TelegramTopicDto
    {
        try {
            $topicName = $this->generateNameTopic($botUser);
            $resultQuery = TelegramMethods::sendQueryTelegram('createForumTopic', [
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'name' => $topicName,
                'icon_custom_emoji_id' => __('icons.incoming'),
            ]);

            if (!$resultQuery->ok) {
                throw new \Exception();
            }

            return TelegramTopicDto::from($resultQuery->rawData['result']);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Edit topic
     *
     * @param TelegramTopicDto $telegramTopicDto
     *
     * @return TelegramTopicDto|null
     */
    public function editTgTopic(TelegramTopicDto $telegramTopicDto): ?TelegramTopicDto
    {
        try {
            $queryParams = array_merge(
                [
                    'chat_id' => config('traffic_source.settings.telegram.group_id'),
                ],
                $telegramTopicDto->toArray()
            );

            $resultQuery = TelegramMethods::sendQueryTelegram('editForumTopic', $queryParams);
            if (!$resultQuery->ok) {
                throw new \Exception('Error sending query');
            }

            return TelegramTopicDto::from($resultQuery->rawData['result']);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Delete system message
     *
     * @param int $messageId
     *
     * @return void
     */
    public static function deleteNoteInTopic(int $messageId): void
    {
        $messageParamsDTO = TGTextMessageDto::from([
            'methodQuery' => 'deleteMessage',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_id' => $messageId,
        ]);
        DeleteMessage::execute($messageParamsDTO);
    }
}
