<?php

namespace App\DTOs\External;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

/**
 * DTO для запроса на получение списка сообщений
 *
 * @param string $source
 * @param string $external_id
 * @param ?int $limit
 * @param ?int $offset
 * @param ?string $date_start
 * @param ?string $date_end
 */
class ExternalListMessageDto extends Data
{
    /**
     * @param string $source
     * @param string $external_id
     * @param ?string $type_sort
     * @param ?int $limit
     * @param ?int $offset
     * @param ?string $date_start
     * @param ?string $date_end
     */
    public function __construct(
        public string $source,
        public string $external_id,
        public ?string $type_sort,
        public ?int $limit,
        public ?int $offset,
        public ?string $date_start,
        public ?string $date_end,
    ) {
    }

    public static function fromRequest($request): ?self
    {
        try {
            $data = $request->all();
            return new self(
                source: $data['source'],
                external_id: $data['external_id'],
                type_sort: $data['type_sort'] ?? null,
                limit: $data['limit'] ?? null,
                offset: $data['offset'] ?? null,
                date_start: $data['date_start'] ?? null,
                date_end: $data['date_end'] ?? null
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return array_filter(parent::toArray(), fn($value) => !is_null($value));
    }

}
