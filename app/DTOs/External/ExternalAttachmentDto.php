<?php

namespace App\DTOs\External;

/**
 * DTO для вложений
 *
 * @property string $url
 * @property string $filename
 * @property string $mime
 */
readonly class ExternalAttachmentDto
{
    public function __construct(
        public string $url,
        public string $filename,
        public string $mime,
    ) {}
}
