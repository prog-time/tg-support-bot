<?php

namespace Tests\Mocks\Max;

use App\Modules\Max\DTOs\MaxUpdateDto;
use Illuminate\Support\Facades\Request;

class MaxUpdateDtoMock
{
    /**
     * @return array
     */
    public static function getDtoParams(): array
    {
        return [
            'update_type' => 'message_created',
            'timestamp' => time() * 1000,
            'message' => [
                'sender' => [
                    'user_id' => time(),
                    'name' => 'Test User',
                ],
                'recipient' => [
                    'user_id' => time(),
                ],
                'timestamp' => time() * 1000,
                'body' => [
                    'mid' => 'msg-' . time(),
                    'seq' => 1,
                    'text' => 'Test text',
                    'attachments' => [],
                ],
            ],
        ];
    }

    /**
     * @param array $dtoParams
     *
     * @return MaxUpdateDto
     */
    public static function getDto(array $dtoParams = []): MaxUpdateDto
    {
        if (empty($dtoParams)) {
            $dtoParams = self::getDtoParams();
        }

        $request = Request::create('api/max/bot', 'POST', $dtoParams);
        return MaxUpdateDto::fromRequest($request);
    }
}
