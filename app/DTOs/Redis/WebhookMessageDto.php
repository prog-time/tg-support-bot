<?php

namespace App\DTOs\Redis;

use App\DTOs\External\ExternalMessageResponseDto;

class WebhookMessageDto
{
    public function __construct(
        public string $source,
        public string $externalId,
        public ExternalMessageResponseDto $message,
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
            'message' => $this->message->toArray(),
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
            message: ExternalMessageResponseDto::fromArray($data['message']),
        );
    }
}
