<?php

namespace App\DTOs\External;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

/**
 * DTO for message list request.
 *
 * @param string  $source
 * @param string  $external_id
 * @param ?int    $limit
 * @param ?int    $offset
 * @param ?string $date_start
 * @param ?string $date_end
 */
class ExternalListMessageDto extends Data
{
    /**
     * @param string  $source
     * @param string  $external_id
     * @param ?string $type_sort
     * @param ?int    $limit
     * @param ?int    $offset
     * @param ?string $date_start
     * @param ?string $date_end
     */
    public function __construct(
        public string $source,
        public string $external_id,
        public ?string $type_sort,
        public ?int $limit,
        public ?int $offset,
        public ?int $after_id,
        public ?string $date_start,
        public ?string $date_end,
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
            return new self(
                source: $data['source'],
                external_id: $data['external_id'],
                type_sort: $data['type_sort'] ?? null,
                limit: $data['limit'] ?? null,
                offset: $data['offset'] ?? null,
                after_id: $data['after_id'] ?? null,
                date_start: $data['date_start'] ?? null,
                date_end: $data['date_end'] ?? null
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return array_filter(parent::toArray(), fn ($value) => !is_null($value));
    }
}
