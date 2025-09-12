<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\SendMessage;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    public int $userChatId = 1424646511;

    public string $photoFileId = 'AgACAgIAAxkBAAIHO2i-0nqM0rxqaqBPjrcf9937EzNRAAJw-jEbLrv5SSpf9j0qc59iAQADAgADeQADNgQ';

    public string $documentFileId = 'BQACAgIAAxkBAAIHOmi-0ihwIBW1gZH2kie-2qZ39FKUAAJWhAACLrvxSdnwd0Zd4TtpNgQ';

    public string $stickerFileId = 'CAACAgIAAxkBAAIHVWi_MH_hQ-8lleOscD7H45RueIwuAAKUFQAC6knQSbL78Ag0M2AyNgQ';

    public function botTestUser(): BotUser
    {
        return BotUser::where('chat_id', $this->userChatId)->first();
    }

    private function getQueryParams(): array
    {
        return [
            'methodQuery' => 'sendMessage',
            'chat_id' => $this->botTestUser()->chat_id,
        ];
    }

    public function test_send_text_message(): void
    {
        // Arrange
        $dtoQueryParams = TGTextMessageDto::from(array_merge($this->getQueryParams(), [
            'text' => 'Тестовое сообщение',
        ]));

        // Act
        $result = SendMessage::execute($this->botTestUser(), $dtoQueryParams);

        // Assert
        $this->assertTrue($result->ok);
        $this->assertEquals($result->error_code, 200);

        $this->assertNotEmpty($result->rawData);
    }

    public function test_send_photo_message(): void
    {
        // Arrange
        $dtoQueryParams = TGTextMessageDto::from(array_merge($this->getQueryParams(), [
            'methodQuery' => 'sendPhoto',
            'photo' => $this->photoFileId,
        ]));

        // Act
        $result = SendMessage::execute($this->botTestUser(), $dtoQueryParams);

        // Assert
        $this->assertTrue($result->ok);
        $this->assertEquals($result->error_code, 200);

        $this->assertNotEmpty($result->rawData);
    }

    public function test_send_document_message(): void
    {
        // Arrange
        $dtoQueryParams = TGTextMessageDto::from(array_merge($this->getQueryParams(), [
            'methodQuery' => 'sendDocument',
            'document' => $this->documentFileId,
        ]));

        // Act
        $result = SendMessage::execute($this->botTestUser(), $dtoQueryParams);

        // Assert
        $this->assertTrue($result->ok);
        $this->assertEquals($result->error_code, 200);

        $this->assertNotEmpty($result->rawData);
    }

    public function test_send_sticker(): void
    {
        // Arrange
        $dtoQueryParams = TGTextMessageDto::from(array_merge($this->getQueryParams(), [
            'methodQuery' => 'sendSticker',
            'sticker' => $this->stickerFileId,
        ]));

        // Act
        $result = SendMessage::execute($this->botTestUser(), $dtoQueryParams);

        // Assert
        $this->assertTrue($result->ok);
        $this->assertEquals($result->error_code, 200);

        $this->assertNotEmpty($result->rawData);
    }

    public function test_send_location(): void
    {
        // Arrange
        $dtoQueryParams = TGTextMessageDto::from(array_merge($this->getQueryParams(), [
            'methodQuery' => 'sendLocation',
            'latitude' => 37.334601,
            'longitude' => -122.009199,
        ]));

        // Act
        $result = SendMessage::execute($this->botTestUser(), $dtoQueryParams);

        // Assert
        $this->assertTrue($result->ok);
        $this->assertEquals($result->error_code, 200);

        $this->assertNotEmpty($result->rawData);
    }

    public function test_message_thread_not_found(): void
    {
        // Arrange
        $dtoQueryParams = TGTextMessageDto::from(array_merge($this->getQueryParams(), [
            'chat_id' => -1002635013459,
            'text' => 'Тестовое сообщение!',
            'message_thread_id' => 111,
        ]));

        // Act
        $result = SendMessage::execute($this->botTestUser(), $dtoQueryParams);

        // Assert
        $this->assertTrue($result->ok);
        $this->assertEquals($result->error_code, 200);
    }

    public function test_message_text_empty(): void
    {
        // Arrange
        $dtoQueryParams = TGTextMessageDto::from($this->getQueryParams());

        // Act
        $result = SendMessage::execute($this->botTestUser(), $dtoQueryParams);

        // Assert
        $this->assertFalse($result->ok);
        $this->assertEquals($result->error_code, 400);

        $this->assertEquals($result->type_error, 'MESSAGE_TEXT_IS_EMPTY');
    }

    public function test_message_markdown_error(): void
    {
        // Arrange
        $testTextMessage = '*Это жирный текст без закрывающей звездочки';

        $dtoQueryParams = TGTextMessageDto::from(array_merge($this->getQueryParams(), [
            'chat_id' => -1002635013459,
            'text' => $testTextMessage,
            'message_thread_id' => 111,
        ]));

        // Act
        $result = SendMessage::execute($this->botTestUser(), $dtoQueryParams);

        // Assert
        $this->assertTrue($result->ok);
        $this->assertEquals($result->error_code, 200);
        $this->assertEquals($testTextMessage, $dtoQueryParams->text);
    }

    public function test_message_not_modified(): void
    {
        // Arrange
        $botUser = $this->botTestUser();
        $queryParams = $this->getQueryParams();
        $testTextMessage = 'Тестовое сообщение!';

        $dtoQueryParamsCreate = TGTextMessageDto::from(array_merge($queryParams, [
            'text' => $testTextMessage,
        ]));
        $resultCreate = SendMessage::execute($botUser, $dtoQueryParamsCreate);

        $dtoQueryParamsEdit = TGTextMessageDto::from(array_merge($queryParams, [
            'methodQuery' => 'editMessageText',
            'text' => $testTextMessage,
            'message_id' => $resultCreate->message_id,
        ]));
        $resultEdit = SendMessage::execute($botUser, $dtoQueryParamsEdit);

        // Assert
        $this->assertFalse($resultEdit->ok);
        $this->assertEquals($resultEdit->error_code, 400);
        $this->assertEquals($resultEdit->type_error, 'MESSAGE_NOT_MODIFIED');
    }

    public function test_chat_not_found(): void
    {
        // Arrange
        $dtoQueryParamsCreate = TGTextMessageDto::from(array_merge($this->getQueryParams(), [
            'chat_id' => 0,
            'text' => 'Тестовое сообщение!',
        ]));
        $result = SendMessage::execute($this->botTestUser(), $dtoQueryParamsCreate);

        // Assert
        $this->assertFalse($result->ok);
        $this->assertEquals($result->error_code, 400);
        $this->assertEquals($result->type_error, 'CHAT_NOT_FOUND');
    }
}
