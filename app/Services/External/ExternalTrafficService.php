<?php

namespace App\Services\External;

use App\Actions\External\DeleteMessage;
use App\DTOs\External\ExternalListMessageAnswerDto;
use App\DTOs\External\ExternalListMessageDto;
use App\DTOs\External\ExternalMessageAnswerDto;
use App\DTOs\External\ExternalMessageDto;
use App\DTOs\External\ExternalMessageResponseDto;
use App\Helpers\TelegramHelper;
use App\Models\BotUser;
use App\Models\ExternalUser;
use App\Models\Message;
use phpDocumentor\Reflection\Exception;

/**
 * Сервис для работы с внешним трафиком (CRUD для Message)
 *
 * @package App\Services
 */
class ExternalTrafficService
{
    /**
     * Получить списка сообщений
     *
     * @param ExternalListMessageDto $filterParams
     *
     * @return array|null
     */
    public function list(ExternalListMessageDto $filterParams): ?array
    {
        try {
            $externalUser = ExternalUser::where([
                'external_id' => $filterParams->external_id,
                'source' => $filterParams->source,
            ])->first();
            if (empty($externalUser)) {
                throw new Exception('Чат не найден!', 1);
            }

            $botUser = BotUser::where([
                'chat_id' => $externalUser->id,
                'platform' => $externalUser->source,
            ])->first();
            if (empty($botUser)) {
                throw new Exception('Чат не найден!', 1);
            }

            $query = Message::where([
                'bot_user_id' => $botUser->id,
                'platform' => $botUser->externalUser->source,
            ]);

            if (!empty($filterParams->date_start)) {
                $query->where('created_at', '>=', $filterParams->date_start);
            }

            if (!empty($filterParams->date_end)) {
                $query->where('created_at', '<=', $filterParams->date_end);
            }

            if (!empty($filterParams->after_id)) {
                $query->where('id', '>', $filterParams->after_id);
            }

            $sortDirection = strtolower($filterParams->type_sort ?? 'asc');
            if (!in_array($sortDirection, ['asc', 'desc'])) {
                $sortDirection = 'desc';
            }
            $query->orderBy('id', $sortDirection);

            if (!empty($filterParams->limit)) {
                $query->limit($filterParams->limit);
            }

            if (!empty($filterParams->offset)) {
                $query->offset($filterParams->offset);
            }

            $totalCount = $query->count();
            $listMessagesData = $query->get();

            $resultMessages = [
                'status' => true,
                'source' => $filterParams->source,
                'external_id' => $filterParams->external_id,
                'total_count' => $totalCount,
                'messages' => [],
            ];

            if (!$listMessagesData->isEmpty()) {
                foreach ($listMessagesData as $message) {
                    $resultMessages['messages'][] = ExternalMessageResponseDto::fromArray([
                        'message_type' => $message->message_type,
                        'to_id' => $message->to_id,
                        'from_id' => $message->from_id,
                        'text' => $message->externalMessage->text,
                        'date' => $message->created_at->format('d.m.Y H:i:s'),
                        'content_type' => $message->externalMessage->file_type ?? 'text',
                        'file_id' => $message->externalMessage->file_id,
                        'file_url' => !empty($message->externalMessage->file_id) ? TelegramHelper::getFilePublicPath($message->externalMessage->file_id) : null,
                        'file_type' => $message->externalMessage->file_type,
                    ])->toArray();
                }
            }

            return ExternalListMessageAnswerDto::from($resultMessages)->toArray();
        } catch (Exception $e) {
            return [
                'status' => false,
                'error' => $e->getCode() === 1 ? $e->getMessage() : 'Неизвестная ошибка!',
            ];
        }
    }

    /**
     * Получить сообщение по ID
     *
     * @param int $id
     *
     * @return Message|null
     */
    public function show(int $id): ?Message
    {
        return Message::find($id);
    }

    /**
     * Создать новое текстовое сообщение
     *
     * @param ExternalMessageDto $dto
     *
     * @return ExternalMessageAnswerDto
     */
    public function store(ExternalMessageDto $dto): ExternalMessageAnswerDto
    {
        return (new ExternalMessageService($dto))->handleUpdate();
    }

    /**
     * Отправить файл
     *
     * @param ExternalMessageDto $dto
     *
     * @return ExternalMessageAnswerDto
     */
    public function sendFile(ExternalMessageDto $dto): ExternalMessageAnswerDto
    {
        return (new ExternalFileService($dto))->handleUpdate();
    }

    /**
     * Обновить сообщение
     *
     * @param ExternalMessageDto $dto
     *
     * @return ExternalMessageAnswerDto
     */
    public function update(ExternalMessageDto $dto): ExternalMessageAnswerDto
    {
        return (new ExternalEditedMessageService($dto))->handleUpdate();
    }

    /**
     * Обновить сообщение
     *
     * @param ExternalMessageDto $dto
     *
     * @return ExternalMessageAnswerDto
     */
    public function destroy(ExternalMessageDto $dto): ExternalMessageAnswerDto
    {
        return DeleteMessage::execute($dto);
    }
}
