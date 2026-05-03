<?php

namespace App\Modules\Ai\Jobs;

use App\Models\BotUser;
use App\Modules\Ai\Services\AiBotApi;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAiReplyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param int               $botUserId BotUser primary key
     * @param TelegramUpdateDto $updateDto Parsed webhook update
     * @param string            $replyText Pre-generated AI reply text
     */
    public function __construct(
        public readonly int $botUserId,
        public readonly TelegramUpdateDto $updateDto,
        public readonly string $replyText,
    ) {
    }

    /**
     * Post the AI-generated reply directly to the supergroup topic as the AI bot.
     *
     * The message is posted from the AI bot account. Once it appears in the topic,
     * it triggers the main bot's SendReplyAction flow and is delivered to the end user.
     *
     * @param AiBotApi $aiBotApi
     *
     * @return void
     */
    public function handle(AiBotApi $aiBotApi): void
    {
        try {
            $botUser = BotUser::find($this->botUserId);
            if ($botUser === null) {
                throw new \RuntimeException('BotUser not found: ' . $this->botUserId, 1);
            }

            $response = $aiBotApi->send('sendMessage', [
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'message_thread_id' => $botUser->topic_id,
                'text' => $this->replyText,
                'parse_mode' => 'html',
            ]);

            if ($response->ok !== true) {
                throw new \RuntimeException('Telegram API error sending AI reply: ' . json_encode((array) $response), 1);
            }
        } catch (\Throwable $e) {
            Log::channel('loki')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                $e->getMessage(),
                ['source' => 'send_ai_reply_error', 'file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }
}
