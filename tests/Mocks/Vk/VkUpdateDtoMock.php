<?php

namespace Tests\Mocks\Vk;

use App\DTOs\Vk\VkUpdateDto;
use Illuminate\Support\Facades\Request;

class VkUpdateDtoMock
{
    /**
     * @return array
     */
    public static function getDtoParams(): array
    {
        return [
            'group_id' => time(),
            'type' => 'message_new',
            'event_id' => '23ff3b705c7ee0ac3e762d40fa4016b88ed384a1',
            'v' => '5.199',
            'object' => [
                'client_info' => [
                    'button_actions' => [
                        'text',
                        'vkpay',
                        'open_app',
                        'location',
                        'open_link',
                        'open_photo',
                        'callback',
                        'intent_subscribe',
                        'intent_unsubscribe',
                    ],
                    'keyboard' => true,
                    'inline_keyboard' => true,
                    'carousel' => true,
                    'lang_id' => 0,
                ],
                'message' => [
                    'date' => time(),
                    'from_id' => time(),
                    'id' => time(),
                    'version' => time(),
                    'out' => 0,
                    'fwd_messages' => [],
                    'important' => false,
                    'is_hidden' => false,
                    'attachments' => [],
                    'conversation_message_id' => time(),
                    'text' => 'Test text',
                    'peer_id' => time(),
                    'random_id' => 0,
                ],
            ],
            'secret' => time(),
        ];
    }

    /**
     * @param array $dtoParams
     *
     * @return VkUpdateDto
     */
    public static function getDto(array $dtoParams = []): VkUpdateDto
    {
        if (empty($dtoParams)) {
            $dtoParams = self::getDtoParams();
        }

        $request = Request::create('api/telegram/bot', 'POST', $dtoParams);
        return VkUpdateDto::fromRequest($request);
    }
}
