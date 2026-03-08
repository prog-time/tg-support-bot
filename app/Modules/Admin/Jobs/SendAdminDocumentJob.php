<?php

namespace App\Modules\Admin\Jobs;

use App\Models\Message;
use App\Modules\Telegram\Api\TelegramMethods;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAdminDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public int $dbMessageId,
        public int $chatId,
        public string $filePath,
        public ?string $caption,
        public string $originalName,
        public string $mimeType,
    ) {
    }

    public function handle(): void
    {
        try {
            $response = TelegramMethods::sendQueryTelegram('sendDocument', [
                'chat_id' => $this->chatId,
                'caption' => $this->caption,
                'uploaded_file_path' => $this->filePath,
            ]);

            if (!$response->ok) {
                Log::channel('loki')->error('SendAdminDocumentJob: Telegram rejected document', [
                    'response_code' => $response->response_code,
                    'type_error' => $response->type_error,
                ]);
                return;
            }

            $result = $response->rawData['result'] ?? [];

            // Telegram returns 'document' for all files sent via sendDocument
            $fileId = $result['document']['file_id'] ?? null;
            $fileName = $result['document']['file_name'] ?? $this->originalName;

            $fileType = str_starts_with($this->mimeType, 'image/') ? 'photo' : 'document';

            if ($fileId) {
                $message = Message::find($this->dbMessageId);
                $message?->attachments()->create([
                    'file_id' => $fileId,
                    'file_type' => $fileType,
                    'file_name' => $fileName,
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('loki')->error($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}
