<?php

namespace App\DTOs\Redis;

class WebhookMessageDto
{
    public function __construct(
        public string $source,
        public string $externalId,
        public string $messageType,
        public int $toId,
        public int $fromId,
        public string $date,
        public ?string $text = null,
        public ?string $attachmentPath = null,
        public ?array $dopParams = [],
    ) {
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'external_id' => $this->externalId,
            'message_type' => $this->messageType,

            'to_id' => $this->toId,
            'from_id' => $this->fromId,

            'text' => $this->text,
            'file_path' => $this->attachmentPath,

            'dop_params' => $this->dopParams,

            'date' => $this->date,
        ];
    }

    /**
     * @param array $data
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            source: $data['source'],
            externalId: $data['external_id'],
            messageType: $data['message_type'],
            toId: $data['to_id'],
            fromId: $data['from_id'],
            date: $data['date'],
            text: $data['text'],
            attachmentPath: $data['file_path'],
            dopParams: $data['dop_params'] ?? [],
        );
    }
}
