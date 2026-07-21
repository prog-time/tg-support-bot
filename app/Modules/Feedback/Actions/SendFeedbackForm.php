<?php

namespace App\Modules\Feedback\Actions;

use App\Models\BotUser;
use App\Models\Feedback;
use App\Modules\Avito\DTOs\AvitoTextMessageDto;
use App\Modules\Avito\Jobs\SendAvitoSimpleMessageJob;
use App\Modules\Email\DTOs\EmailMessageDto;
use App\Modules\Email\Jobs\SendEmailMessageJob;
use App\Modules\Email\Services\EmailThreadStore;
use App\Modules\Max\DTOs\MaxTextMessageDto;
use App\Modules\Max\Jobs\SendMaxMessageJob;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Modules\Vk\DTOs\VkTextMessageDto;
use App\Modules\Vk\Jobs\SendVkSimpleMessageJob;
use App\Platform\PlatformChannelRegistry;
use Illuminate\Support\Facades\Log;

/**
 * Send the post-close feedback form to the user on their platform.
 *
 * Creates a Feedback record with status='awaiting_rating' and dispatches
 * a platform-appropriate message carrying the rating keyboard.
 *
 * Platform delivery:
 * - telegram — SendTelegramSimpleQueryJob with inline_keyboard (5 star buttons)
 * - vk       — SendVkSimpleMessageJob with VK callback keyboard (5 buttons)
 * - max      — SendMaxMessageJob with Max inline keyboard (5 buttons)
 * - other    — delegated to a PlatformChannel registered in PlatformChannelRegistry
 *              by a pluggable module (e.g. the paid Avito package)
 *
 * callback_data / payload format: feedback_rate_{botUserId}_{score}
 * e.g. feedback_rate_42_3
 */
class SendFeedbackForm
{
    /**
     * Send feedback form to the user after their conversation is closed.
     *
     * @param BotUser $botUser The user whose conversation was just closed.
     *
     * @return void
     */
    public function execute(BotUser $botUser): void
    {
        $feedback = Feedback::create([
            'bot_user_id' => $botUser->id,
            'status' => 'awaiting_rating',
            'closed_at' => now(),
        ]);

        Log::channel('app')->info('SendFeedbackForm: created feedback record', [
            'source' => 'feedback_form_created',
            'bot_user_id' => $botUser->id,
            'feedback_id' => $feedback->id,
            'platform' => $botUser->platform,
        ]);

        switch ($botUser->platform) {
            case 'telegram':
                $this->sendTelegram($botUser, $feedback->id);
                break;

            case 'vk':
                $this->sendVk($botUser, $feedback->id);
                break;

            case 'max':
                $this->sendMax($botUser, $feedback->id);
                break;

            case 'avito':
                $this->sendAvito($botUser, $feedback->id);
                break;

            case 'email':
                $this->sendEmail($botUser, $feedback->id);
                break;

            default:
                $channel = app(PlatformChannelRegistry::class)->for($botUser->platform);

                if ($channel !== null) {
                    $channel->sendFeedbackForm($botUser, $feedback->id);

                    Log::channel('app')->info('SendFeedbackForm: delivered via registered channel', [
                        'source' => 'feedback_form_registered_channel',
                        'bot_user_id' => $botUser->id,
                        'feedback_id' => $feedback->id,
                        'platform' => $botUser->platform,
                    ]);
                    break;
                }

                Log::channel('app')->warning('SendFeedbackForm: unsupported platform, skipping delivery', [
                    'source' => 'feedback_form_unsupported_platform',
                    'bot_user_id' => $botUser->id,
                    'platform' => $botUser->platform,
                ]);
                break;
        }
    }

    /**
     * Send Telegram inline keyboard with 5 star rating buttons.
     *
     * @param BotUser $botUser
     * @param int     $feedbackId
     *
     * @return void
     */
    private function sendTelegram(BotUser $botUser, int $feedbackId): void
    {
        // TODO: move text to lang/ru/messages.php when i18n infrastructure is added
        $text = 'Пожалуйста, оцените качество нашей поддержки:';

        $keyboard = $this->buildTelegramKeyboard($botUser->id, $feedbackId);

        SendTelegramSimpleQueryJob::dispatch(TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $botUser->chat_id,
            'text' => $text,
            'parse_mode' => 'html',
            'reply_markup' => ['inline_keyboard' => [$keyboard]],
        ]));
    }

    /**
     * Send VK message with inline callback keyboard (5 rating buttons).
     *
     * VK supports callback buttons via the `keyboard` JSON parameter on messages.send.
     * Callback events arrive at the VK webhook as type=message_event and are routed
     * by VkBotController to HandleFeedbackRating.
     *
     * @param BotUser $botUser
     * @param int     $feedbackId
     *
     * @return void
     */
    private function sendVk(BotUser $botUser, int $feedbackId): void
    {
        // TODO: move text to lang/ru/messages.php when i18n infrastructure is added
        $text = 'Пожалуйста, оцените качество нашей поддержки:';

        $keyboard = $this->buildVkKeyboard($botUser->id, $feedbackId);

        SendVkSimpleMessageJob::dispatch(VkTextMessageDto::from([
            'methodQuery' => 'messages.send',
            'peer_id' => $botUser->chat_id,
            'message' => $text,
            'keyboard' => json_encode($keyboard),
        ]));
    }

    /**
     * Send Max message with inline keyboard (5 rating buttons).
     *
     * Max supports inline keyboards via the keyboard array in MaxTextMessageDto.
     * Callback events arrive as update_type=message_callback and are routed
     * by MaxBotController to HandleFeedbackRating.
     *
     * @param BotUser $botUser
     * @param int     $feedbackId
     *
     * @return void
     */
    /**
     * Send the rating prompt to an Avito user.
     *
     * Avito Messenger has no inline-keyboard equivalent, so this is a plain
     * text prompt. It is therefore a ONE-WAY prompt: there is no callback to
     * carry feedback_rate_{botUserId}_{feedbackId}_{score} back, so
     * HandleFeedbackRating is never reached and the Feedback row stays at
     * status='awaiting_rating'. Capturing the score would require parsing the
     * user's next inbound message — deliberately out of scope here.
     *
     * @param BotUser $botUser
     * @param int     $feedbackId
     *
     * @return void
     */
    private function sendAvito(BotUser $botUser, int $feedbackId): void
    {
        $text = 'Пожалуйста, оцените качество нашей поддержки от 1 до 5.';

        SendAvitoSimpleMessageJob::dispatch(
            AvitoTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'chat_id' => $botUser->chat_id,
                'text' => $text,
            ]),
        );
    }

    /**
     * Send the rating prompt to an email user.
     *
     * Email has no inline-keyboard equivalent (like Avito), so this is a
     * plain text prompt. It is therefore a ONE-WAY prompt: there is no
     * callback to carry feedback_rate_{botUserId}_{feedbackId}_{score} back,
     * so HandleFeedbackRating is never reached and the Feedback row stays at
     * status='awaiting_rating'. Capturing the score would require parsing the
     * user's next inbound email reply — deliberately out of scope here.
     *
     * @param BotUser $botUser
     * @param int     $feedbackId
     *
     * @return void
     */
    private function sendEmail(BotUser $botUser, int $feedbackId): void
    {
        $text = 'Пожалуйста, оцените качество нашей поддержки от 1 до 5.';

        $headers = app(EmailThreadStore::class)->replyHeaders($botUser->id);

        SendEmailMessageJob::dispatch(
            EmailMessageDto::from([
                'to' => $botUser->chat_id,
                'subject' => $headers['subject'],
                'text' => $text,
                'inReplyTo' => $headers['inReplyTo'],
                'references' => $headers['references'],
            ]),
        );
    }

    private function sendMax(BotUser $botUser, int $feedbackId): void
    {
        // TODO: move text to lang/ru/messages.php when i18n infrastructure is added
        $text = 'Пожалуйста, оцените качество нашей поддержки:';

        $keyboard = $this->buildMaxKeyboard($botUser->id, $feedbackId);

        SendMaxMessageJob::dispatch(
            $botUser->id,
            null,
            MaxTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'user_id' => $botUser->chat_id,
                'text' => $text,
                'keyboard' => $keyboard,
            ]),
        );
    }

    /**
     * Build Telegram inline keyboard row with 5 rating buttons.
     *
     * callback_data format: feedback_rate_{botUserId}_{feedbackId}_{score}
     *
     * @param int $botUserId
     * @param int $feedbackId
     *
     * @return array<int, array<string, string>>
     */
    private function buildTelegramKeyboard(int $botUserId, int $feedbackId): array
    {
        $buttons = [];

        for ($score = 1; $score <= 5; $score++) {
            $buttons[] = [
                'text' => (string) $score,
                'callback_data' => "feedback_rate_{$botUserId}_{$feedbackId}_{$score}",
            ];
        }

        return $buttons;
    }

    /**
     * Build VK callback keyboard with 5 rating buttons in one row.
     *
     * @param int $botUserId
     * @param int $feedbackId
     *
     * @return array<string, mixed>
     */
    private function buildVkKeyboard(int $botUserId, int $feedbackId): array
    {
        $buttons = [];

        for ($score = 1; $score <= 5; $score++) {
            $buttons[] = [
                'action' => [
                    'type' => 'callback',
                    'label' => (string) $score,
                    'payload' => json_encode(['command' => "feedback_rate_{$botUserId}_{$feedbackId}_{$score}"]),
                ],
            ];
        }

        return [
            'inline' => true,
            'buttons' => [$buttons],
        ];
    }

    /**
     * Build Max inline keyboard with 5 rating buttons in one row.
     *
     * @param int $botUserId
     * @param int $feedbackId
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildMaxKeyboard(int $botUserId, int $feedbackId): array
    {
        $buttons = [];

        for ($score = 1; $score <= 5; $score++) {
            $buttons[] = [
                'type' => 'callback',
                'text' => (string) $score,
                'payload' => "feedback_rate_{$botUserId}_{$feedbackId}_{$score}",
            ];
        }

        return [$buttons];
    }
}
