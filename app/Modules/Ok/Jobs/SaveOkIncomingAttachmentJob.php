<?php

namespace App\Modules\Ok\Jobs;

use App\Models\Message;
use App\Modules\Ok\DTOs\OkAttachmentDto;
use App\Modules\Ok\DTOs\OkUpdateDto;
use App\Modules\Ok\Services\OkAttachmentStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Save an incoming attachment for the admin workspace when no Telegram group is
 * configured. A file that cannot be downloaded is saved as its link.
 */
class SaveOkIncomingAttachmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    /**
     * @param int             $botUserId
     * @param OkUpdateDto     $update
     * @param OkAttachmentDto $attachment
     */
    public function __construct(
        public int $botUserId,
        public OkUpdateDto $update,
        public OkAttachmentDto $attachment,
    ) {
    }

    /**
     * @param OkAttachmentStorage $storage
     *
     * @return void
     */
    public function handle(OkAttachmentStorage $storage): void
    {
        $file = $this->attachment->url !== null ? $storage->download($this->attachment->url) : null;

        $message = Message::create([
            'bot_user_id' => $this->botUserId,
            'platform' => 'ok',
            'message_type' => 'incoming',
            'from_id' => $this->update->numericSenderId(),
            'to_id' => 0,
            'text' => $file === null ? $this->attachment->text : null,
        ]);

        if ($file !== null) {
            $message->attachments()->create([
                'file_id' => $file['path'],
                'file_type' => $this->attachment->fileType ?? 'document',
                'file_name' => $file['name'],
            ]);
        }
    }
}
