<?php

namespace App\Services\External;

use App\Actions\External\DeleteMessage;
use App\DTOs\External\ExternalListMessageAnswerDto;
use App\DTOs\External\ExternalListMessageDto;
use App\DTOs\External\ExternalMessageAnswerDto;
use App\DTOs\External\ExternalMessageDto;
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
                'platform' => 'external_source',
            ])->first();
            if (empty($botUser)) {
                throw new Exception('Чат не найден!', 1);
            }

            $query = Message::where([
                'bot_user_id' => $botUser->id,
                'platform' => 'external_source',
            ]);

            if (!empty($filterParams->date_start)) {
                $query->where('created_at', '>=', $filterParams->date_start);
            }

            if (!empty($filterParams->date_end)) {
                $query->where('created_at', '<=', $filterParams->date_end);
            }

            $sortDirection = strtolower($filterParams->type_sort ?? 'desc');
            if (!in_array($sortDirection, ['asc', 'desc'])) {
                $sortDirection = 'desc';
            }
            $query->orderBy('created_at', $sortDirection);

            if (!empty($filterParams->limit)) {
                $query->limit($filterParams->limit);
            }

            if (!empty($filterParams->offset)) {
                $query->offset($filterParams->offset);
            }

            $totalCount = $query->count();
            $listMessagesData = $query->get();

            if ($listMessagesData->isEmpty()) {
                throw new Exception('Сообщения не найдены!', 1);
            }

            $resultMessages = [
                'source' => $filterParams->source,
                'external_id' => $filterParams->external_id,
                'total_count' => $totalCount,
                'messages' => [],
            ];

            foreach ($listMessagesData as $message) {
                $resultMessages['messages'][] = [
                    'message_id' => $message->from_id,
                    'message_type' => $message->message_type,
                    'text' => $message->text ?? null,
                    'date' => $message->created_at->format('d.m.Y H:i:s'),
                ];
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
     * Создать новое сообщение
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
