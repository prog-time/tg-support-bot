<?php

namespace App\Modules\Ai\Actions;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Avito\DTOs\AvitoTextMessageDto;
use App\Modules\Avito\Jobs\SendAvitoSimpleMessageJob;
use App\Modules\Max\DTOs\MaxTextMessageDto;
use App\Modules\Max\Jobs\SendMaxSimpleMessageJob;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Modules\Vk\DTOs\VkTextMessageDto;
use App\Modules\Vk\Jobs\SendVkSimpleMessageJob;
use App\Platform\PlatformChannelRegistry;
use Illuminate\Support\Facades\Log;

/**
 * Persist an AI-generated answer to the `messages` table and dispatch a
 * best-effort platform send using the "simple" (non-saving) send job.
 *
 * The outgoing `Message` row is created BEFORE the send job is dispatched,
 * so the AI answer ALWAYS appears in the admin chat thread at `/admin/chats`
 * regardless of whether the platform send ultimately succeeds. This mirrors
 * the pattern used by {@see \App\Modules\Admin\Actions\SendReplyAction}.
 *
 * Built-in platforms (telegram/vk/max) are handled directly. Any other
 * platform is delegated to a {@see \App\Contracts\PlatformChannel} registered
 * in the {@see PlatformChannelRegistry} by a pluggable module (e.g. the paid
 * Avito package) — the core needs no edits to support a new platform; those
 * channels are responsible for their own persistence.
 */
class DeliverAiAnswerToUser
{
    /**
     * Persist the AI answer and dispatch the platform send job.
     *
     * For telegram/vk/max:
     * 1. Strip HTML markup to obtain the plain-text version for `messages.text`.
     *    Telegram users still receive the HTML-formatted message (via parse_mode=html).
     * 2. Create the `Message` row with `message_type = 'outgoing'` immediately.
     * 3. Dispatch a "simple" (non-saving) send job so there is exactly one row.
     *
     * For pluggable platforms (default branch), delivery is delegated to the
     * registered {@see PlatformChannel}; that channel owns its own persistence.
     *
     * @param BotUser                $botUser   Target user
     * @param string                 $text      AI answer text (may contain Telegram HTML markup)
     * @param TelegramUpdateDto|null $updateDto Optional originating TG update (not used for
     *                                          persistence in this action; kept for signature
     *                                          compatibility with callers)
     *
     * @return bool true if delivery was attempted (or a registered channel handled it),
     *              false if the platform is unsupported and no channel is registered
     */
    public function execute(BotUser $botUser, string $text, ?TelegramUpdateDto $updateDto = null): bool
    {
        Log::channel('app')->info('DeliverAiAnswerToUser: routing', [
            'source' => 'ai_deliver_routing',
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->platform,
            'chat_id' => $botUser->chat_id,
            'text_length' => mb_strlen($text),
        ]);

        switch ($botUser->platform) {
            case 'telegram':
                $plainText = $this->stripHtmlForPlainText($text);

                Message::create([
                    'bot_user_id' => $botUser->id,
                    'platform' => $botUser->platform,
                    'message_type' => 'outgoing',
                    'from_id' => 0,
                    'to_id' => 0,
                    'text' => $plainText ?: null,
                ]);

                // Send PLAIN text with parse_mode explicitly disabled (null → omitted
                // by toArray()). The DTO defaults parse_mode to 'html'; left at the
                // default, Telegram rejects AI output that isn't valid Telegram HTML
                // (stray '<', '&', code) with 400 "can't parse entities" and the
                // answer never reaches the user. Plain delivery is robust for any text.
                SendTelegramSimpleQueryJob::dispatch(
                    TGTextMessageDto::from([
                        'methodQuery' => 'sendMessage',
                        'chat_id' => $botUser->chat_id,
                        'text' => $plainText,
                        'parse_mode' => null,
                    ]),
                );
                return true;

            case 'vk':
                $plainText = $this->stripHtmlForPlainText($text);

                Message::create([
                    'bot_user_id' => $botUser->id,
                    'platform' => $botUser->platform,
                    'message_type' => 'outgoing',
                    'from_id' => 0,
                    'to_id' => 0,
                    'text' => $plainText ?: null,
                ]);

                SendVkSimpleMessageJob::dispatch(
                    VkTextMessageDto::from([
                        'methodQuery' => 'messages.send',
                        'peer_id' => $botUser->chat_id,
                        'message' => $plainText,
                    ]),
                );
                return true;

            case 'max':
                $plainText = $this->stripHtmlForPlainText($text);

                Message::create([
                    'bot_user_id' => $botUser->id,
                    'platform' => $botUser->platform,
                    'message_type' => 'outgoing',
                    'from_id' => 0,
                    'to_id' => 0,
                    'text' => $plainText ?: null,
                ]);

                SendMaxSimpleMessageJob::dispatch(
                    MaxTextMessageDto::from([
                        'methodQuery' => 'sendMessage',
                        'user_id' => $botUser->chat_id,
                        'text' => $plainText,
                    ]),
                );
                return true;

            case 'avito':
                $plainText = $this->stripHtmlForPlainText($text);

                Message::create([
                    'bot_user_id' => $botUser->id,
                    'platform' => $botUser->platform,
                    'message_type' => 'outgoing',
                    'from_id' => 0,
                    'to_id' => 0,
                    'text' => $plainText ?: null,
                ]);

                SendAvitoSimpleMessageJob::dispatch(
                    AvitoTextMessageDto::from([
                        'methodQuery' => 'sendMessage',
                        'chat_id' => $botUser->chat_id,
                        'text' => $plainText,
                    ]),
                );
                return true;

            default:
                $channel = app(PlatformChannelRegistry::class)->for($botUser->platform);

                if ($channel !== null) {
                    $channel->deliverAiAnswer($botUser, $text, $updateDto);

                    Log::channel('app')->info('DeliverAiAnswerToUser: delivered via registered channel', [
                        'source' => 'ai_deliver_registered_channel',
                        'bot_user_id' => $botUser->id,
                        'platform' => $botUser->platform,
                    ]);

                    return true;
                }

                Log::channel('app')->warning('DeliverAiAnswerToUser: unsupported platform', [
                    'source' => 'ai_deliver_unsupported_platform',
                    'bot_user_id' => $botUser->id,
                    'platform' => $botUser->platform,
                ]);
                return false;
        }
    }

    /**
     * AI drafts are stored with Telegram HTML markup (`<b>`, `<i>`, …) because
     * the supergroup post uses `parse_mode=html`. VK and Max channels expect
     * plain text and would otherwise render the literal tags. Strip them and
     * decode HTML entities so the user sees a clean message.
     *
     * @param string $text
     *
     * @return string
     */
    private function stripHtmlForPlainText(string $text): string
    {
        $plain = strip_tags($text);
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim($plain);
    }
}
