<?php

namespace App\DTOs\External;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

/**
 * DTO for API messages.
 *
 * @property string          $source
 * @property string          $external_id
 * @property string          $text
 * @property string|int|null $message_id
 * @property ?array          $attachments
 */
class ExternalMessageDto extends Data
{
    /**
     * @param string          $source
     * @param string          $external_id
     * @param ?string         $text
     * @param string|int|null $message_id
     * @param ?string         $file_type
     * @param ?string         $file_name
     */
    public function __construct(
        public string $source,
        public string $external_id,
        public string|int|null $message_id,
        public ?string $text,
        public ?UploadedFile $uploaded_file,
        public ?string $uploaded_file_path,
        public ?string $file_type,
        public ?string $file_name,
    ) {
    }

    /**
     * @param Request $request
     *
     * @return self|null
     */
    public static function fromRequest(Request $request): ?self
    {
        try {
            $data = $request->all();

            $fileType = null;
            $fileName = null;
            if (!empty($data['uploaded_file'])) {
                $mime = $data['uploaded_file']->getMimeType();
                $fileType = $mime && str_starts_with($mime, 'image/') ? 'photo' : 'document';
                $fileName = $data['uploaded_file']->getClientOriginalName();
                $uploadedFilePath = $data['uploaded_file']->store('uploads', 'public');
            }

            return new self(
                source: $data['source'],
                external_id: $data['external_id'],
                message_id: $data['message_id'] ?? null,
                text: $data['text'] ?? null,
                uploaded_file: $data['uploaded_file'] ?? null,
                uploaded_file_path: $uploadedFilePath ?? null,
                file_type: $fileType,
                file_name: $fileName,
            );
        } catch (\Throwable $e) {
            return null;
        }
    }
}
