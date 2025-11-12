<?php

namespace Tests\Unit\Services\VK;

use App\DTOs\Vk\VkUpdateDto;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\VK\VkMessageService;
use Tests\Mocks\Vk\VkUpdateDtoMock;
use Tests\TestCase;

class VkMessageServiceTest extends TestCase
{
    public function sendTestQuery(VkUpdateDto $dto): Message
    {
        $botUser = BotUser::getUserByChatId($dto->from_id, 'vk');

        (new VkMessageService($dto))->handleUpdate();

        // Проверяем, что сообщение сохранилось в базе
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'incoming',
            'platform' => 'vk',
        ]);

        return Message::where('bot_user_id', $botUser->id)->first();
    }

    public function test_send_text_message(): void
    {
        Message::truncate();

        $vkDto = VkUpdateDtoMock::getDto();
        $this->sendTestQuery($vkDto);
    }

    public function test_send_document(): void
    {
        Message::truncate();

        $dtoParams = VkUpdateDtoMock::getDtoParams();

        $dtoParams['object']['message']['attachments'] = [
            [
                'type' => 'photo',
                'photo' => [
                    'album_id' => -6,
                    'date' => 1724143787,
                    'id' => 457241856,
                    'owner_id' => 222232176,
                    'sizes' => [
                        ['height' => 96, 'type' => 's', 'width' => 72, 'url' => 'https://sun9-24.userapi.com/s/v1/ig1/f8U0fLrTzTKLLY5eZ3n6guHhUySLdj8S6p5SbhUuUt0aBainj07WzEkTpK1_Hl2bYGPVW2cV.jpg?quality=96&as=32x43,48x64,72x96,108x144,160x213,240x320,360x480,480x640,540x720,640x853,720x960,1080x1440,1280x1707,1440x1920,1536x2048&from=bu&cs=72x0'],
                        ['height' => 213, 'type' => 'm', 'width' => 160, 'url' => 'https://sun9-24.userapi.com/s/v1/ig1/f8U0fLrTzTKLLY5eZ3n6guHhUySLdj8S6p5SbhUuUt0aBainj07WzEkTpK1_Hl2bYGPVW2cV.jpg?quality=96&as=32x43,48x64,72x96,108x144,160x213,240x320,360x480,480x640,540x720,640x853,720x960,1080x1440,1280x1707,1440x1920,1536x2048&from=bu&cs=160x0'],
                        ['height' => 853, 'type' => 'x', 'width' => 640, 'url' => 'https://sun9-24.userapi.com/s/v1/ig1/f8U0fLrTzTKLLY5eZ3n6guHhUySLdj8S6p5SbhUuUt0aBainj07WzEkTpK1_Hl2bYGPVW2cV.jpg?quality=96&as=32x43,48x64,72x96,108x144,160x213,240x320,360x480,480x640,540x720,640x853,720x960,1080x1440,1280x1707,1440x1920,1536x2048&from=bu&cs=640x0'],
                        ['height' => 1440, 'type' => 'y', 'width' => 1080, 'url' => 'https://sun9-24.userapi.com/s/v1/ig1/f8U0fLrTzTKLLY5eZ3n6guHhUySLdj8S6p5SbhUuUt0aBainj07WzEkTpK1_Hl2bYGPVW2cV.jpg?quality=96&as=32x43,48x64,72x96,108x144,160x213,240x320,360x480,480x640,540x720,640x853,720x960,1080x1440,1280x1707,1440x1920,1536x2048&from=bu&cs=1080x0'],
                        ['height' => 1707, 'type' => 'z', 'width' => 1280, 'url' => 'https://sun9-24.userapi.com/s/v1/ig1/f8U0fLrTzTKLLY5eZ3n6guHhUySLdj8S6p5SbhUuUt0aBainj07WzEkTpK1_Hl2bYGPVW2cV.jpg?quality=96&as=32x43,48x64,72x96,108x144,160x213,240x320,360x480,480x640,540x720,640x853,720x960,1080x1440,1280x1707,1440x1920,1536x2048&from=bu&cs=1280x0'],
                        ['height' => 2048, 'type' => 'w', 'width' => 1536, 'url' => 'https://sun9-24.userapi.com/s/v1/ig1/f8U0fLrTzTKLLY5eZ3n6guHhUySLdj8S6p5SbhUuUt0aBainj07WzEkTpK1_Hl2bYGPVW2cV.jpg?quality=96&as=32x43,48x64,72x96,108x144,160x213,240x320,360x480,480x640,540x720,640x853,720x960,1080x1440,1280x1707,1440x1920,1536x2048&from=bu&cs=1536x0'],
                        ['height' => 144, 'type' => 'o', 'width' => 108, 'url' => 'https://sun9-24.userapi.com/s/v1/ig1/f8U0fLrTzTKLLY5eZ3n6guHhUySLdj8S6p5SbhUuUt0aBainj07WzEkTpK1_Hl2bYGPVW2cV.jpg?quality=96&as=32x43,48x64,72x96,108x144,160x213,240x320,360x480,480x640,540x720,640x853,720x960,1080x1440,1280x1707,1440x1920,1536x2048&from=bu&cs=108x0'],
                        ['height' => 320, 'type' => 'p', 'width' => 240, 'url' => 'https://sun9-24.userapi.com/s/v1/ig1/f8U0fLrTzTKLLY5eZ3n6guHhUySLdj8S6p5SbhUuUt0aBainj07WzEkTpK1_Hl2bYGPVW2cV.jpg?quality=96&as=32x43,48x64,72x96,108x144,160x213,240x320,360x480,480x640,540x720,640x853,720x960,1080x1440,1280x1707,1440x1920,1536x2048&from=bu&cs=240x0'],
                        ['height' => 480, 'type' => 'q', 'width' => 360, 'url' => 'https://sun9-24.userapi.com/s/v1/ig1/f8U0fLrTzTKLLY5eZ3n6guHhUySLdj8S6p5SbhUuUt0aBainj07WzEkTpK1_Hl2bYGPVW2cV.jpg?quality=96&as=32x43,48x64,72x96,108x144,160x213,240x320,360x480,480x640,540x720,640x853,720x960,1080x1440,1280x1707,1440x1920,1536x2048&from=bu&cs=360x0'],
                        ['height' => 720, 'type' => 'r', 'width' => 540, 'url' => 'https://sun9-24.userapi.com/s/v1/ig1/f8U0fLrTzTKLLY5eZ3n6guHhUySLdj8S6p5SbhUuUt0aBainj07WzEkTpK1_Hl2bYGPVW2cV.jpg?quality=96&as=32x43,48x64,72x96,108x144,160x213,240x320,360x480,480x640,540x720,640x853,720x960,1080x1440,1280x1707,1440x1920,1536x2048&from=bu&cs=540x0'],
                        ['height' => 2048, 'type' => 'base', 'width' => 1536, 'url' => 'https://sun9-24.userapi.com/s/v1/ig1/f8U0fLrTzTKLLY5eZ3n6guHhUySLdj8S6p5SbhUuUt0aBainj07WzEkTpK1_Hl2bYGPVW2cV.jpg?quality=96&as=32x43,48x64,72x96,108x144,160x213,240x320,360x480,480x640,540x720,640x853,720x960,1080x1440,1280x1707,1440x1920,1536x2048&from=bu'],
                    ],
                    'square_crop' => '387,137,618',
                    'text' => null,
                    'web_view_token' => 'edf2bc5f79afdf0bf1',
                    'has_tags' => false,
                    'orig_photo' => [
                        'height' => 2048,
                        'type' => 'base',
                        'url' => 'https://sun9-24.userapi.com/s/v1/ig1/f8U0fLrTzTKLLY5eZ3n6guHhUySLdj8S6p5SbhUuUt0aBainj07WzEkTpK1_Hl2bYGPVW2cV.jpg?quality=96&as=32x43,48x64,72x96,108x144,160x213,240x320,360x480,480x640,540x720,640x853,720x960,1080x1440,1280x1707,1440x1920,1536x2048&from=bu',
                        'width' => 1536,
                    ],
                ],
            ],
        ];

        $vkDto = VkUpdateDtoMock::getDto($dtoParams);
        $this->sendTestQuery($vkDto);
    }

    public function test_send_location(): void
    {
        Message::truncate();

        $dtoParams = VkUpdateDtoMock::getDtoParams();

        $dtoParams['object']['message']['text'] = null;
        $dtoParams['object']['message']['geo'] = [
            'coordinates' => [
                'latitude' => 55.524442,
                'longitude' => 37.705064,
            ],
            'place' => [
                'city' => 'деревня Сапроново',
                'country' => 'Россия',
                'title' => 'деревня Сапроново, Россия',
            ],
            'type' => 'point',
        ];

        $vkDto = VkUpdateDtoMock::getDto($dtoParams);
        $this->sendTestQuery($vkDto);
    }
}
