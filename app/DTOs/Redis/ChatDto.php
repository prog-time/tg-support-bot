<?php

namespace App\DTOs\Redis;

class ChatDto
{
    public function __construct(
        public string $source,
        public string $external_id,
        public array $messages = [],
    ) {
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'external_id' => $this->external_id,
            'messages' => $this->messages,
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
            external_id: $data['external_id'],
            messages: $data['messages'],
        );
    }
}
