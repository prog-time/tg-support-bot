<?php

namespace App\Modules\Telegram\Jobs;

use App\Jobs\SendMessage\AbstractSendMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Email\DTOs\EmailUpdateDto;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TelegramAnswerDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;

/**
 * Forward an incoming email into the user's Telegram forum topic.
 *
 * Mirrors {@see SendAvitoTelegramMessageJob}, plus a single-attachment path:
 * when the email carried one attachment, `queryParams` is a `sendPhoto`/
 * `sendDocument` call with `uploaded_file_path` set (built by
 * `EmailMessageService::sendMessage()`) instead of `sendMessage`. The row in
 * `messages` is written only after the Telegram call succeeds, keeping the
 * "any persisted row was delivered" invariant the chat-history assembler
 * relies on.
 *
 * There is no numeric sender id for an email (unlike Avito's author_id), so
 * `from_id` is persisted as 0 — the sender is identified by the BotUser
 * (chat_id = email address), same as VK/Max already do when no better id exists.
 */
class SendEmailTelegramMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    /** @var EmailUpdateDto */
    public mixed $updateDto;

    /** @var TGTextMessageDto */
    public mixed $queryParams;

    public string $typeMessage = 'incoming';

    private TelegramMethods $telegramMethods;

    public function __construct(
        int $botUserId,
        EmailUpdateDto $updateDto,
        TGTextMessageDto $queryParams,
        ?TelegramMethods $telegramMethods = null,
    ) {
        $this->botUserId = $botUserId;
        $this->updateDto = $updateDto;
        $this->queryParams = $queryParams;

        $this->telegramMethods = $telegramMethods ?? new TelegramMethods();
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        try {
            $botUser = BotUser::find($this->botUserId);
            if ($botUser === null) {
                throw new \RuntimeException('BotUser not found for Email forward', 1);
            }

            $methodQuery = $this->queryParams->methodQuery;
            $params = $this->queryParams->toArray();

            if ($botUser->topic_id) {
                $response = $this->telegramMethods->sendQueryTelegram(
                    'editForumTopic',
                    [
                        'chat_id' => (string) app(SettingsService::class)->get('telegram.group_id'),
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
                    if ($botUser->isClosed()) {
                        $this->telegramMethods->sendQueryTelegram(
                            'reopenForumTopic',
                            [
                                'chat_id' => (string) app(SettingsService::class)->get('telegram.group_id'),
                                'message_thread_id' => $botUser->topic_id,
                            ]
                        );
                        $botUser->update(['is_closed' => false, 'closed_at' => null]);
                    }
                }
            }

            if (!$botUser->topic_id) {
                TopicCreateJob::withChain([
                    new SendEmailTelegramMessageJob(
                        $this->botUserId,
                        $this->updateDto,
                        $this->queryParams,
                    ),
                ])->dispatch($this->botUserId);

                return;
            }

            $response = $this->telegramMethods->sendQueryTelegram(
                $methodQuery,
                $params,
                $this->queryParams->token
            );

            if ($response->ok === true) {
                $this->saveMessage($botUser, $response);
                $this->updateTopic($botUser, $this->typeMessage);

                return;
            }

            $this->telegramResponseHandler($response);
        } catch (\Throwable $e) {
            Log::channel('app')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }

    /**
     * Persist the forwarded message after Telegram accepted it.
     *
     * @param BotUser $botUser
     * @param mixed   $resultQuery
     *
     * @return void
     *
     * @throws \Exception When the API layer returned an unexpected type.
     */
    protected function saveMessage(BotUser $botUser, mixed $resultQuery): void
    {
        if (!$resultQuery instanceof TelegramAnswerDto) {
            throw new \Exception('Expected TelegramAnswerDto', 1);
        }

        $message = Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->platform,
            'message_type' => $this->typeMessage,
            'from_id' => 0,
            'to_id' => $resultQuery->message_id,
            // The readable form (subject + body), NOT $this->queryParams->text —
            // that's HTML-escaped for the Telegram wire payload (EmailMessageService
            // escapes '<'/'>'/'&' so a reply-quote's "<address>" doesn't break
            // Telegram's HTML parser); persisting the escaped string would show
            // literal "&lt;" once the admin workspace's Blade view re-escapes it.
            'text' => $this->updateDto->displayText(),
        ]);

        // The Telegram-bound copy (queryParams' uploaded_file_path) is already
        // gone — ParserMethods::attachQuery() deletes it after upload. The
        // permanent admin-workspace copy lives at a separate path
        // (EmailImapClient::extractAttachments()'s `storedPath`).
        $attachment = $this->updateDto->attachments[0] ?? null;
        if ($attachment !== null && !empty($attachment['storedPath'])) {
            $message->attachments()->create([
                'file_id' => $attachment['storedPath'],
                'file_type' => str_starts_with($attachment['mime'], 'image/') ? 'photo' : 'document',
                'file_name' => $attachment['name'],
            ]);
        }
    }

    /**
     * Editing forwarded emails is not supported.
     *
     * @param BotUser $botUser
     * @param mixed   $resultQuery
     *
     * @return void
     */
    protected function editMessage(BotUser $botUser, mixed $resultQuery): void
    {
        //
    }
}
