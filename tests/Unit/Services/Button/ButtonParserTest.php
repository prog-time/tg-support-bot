<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Button;

use App\Enums\ButtonType;
use App\Services\Button\ButtonParser;
use PHPUnit\Framework\TestCase;

class ButtonParserTest extends TestCase
{
    private ButtonParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ButtonParser();
    }

    public function test_parses_message_without_buttons(): void
    {
        $message = 'Привет, как дела?';

        $result = $this->parser->parse($message);

        $this->assertEquals('Привет, как дела?', $result->text);
        $this->assertEmpty($result->buttons);
        $this->assertFalse($result->hasButtons());
    }

    public function test_parses_url_button(): void
    {
        $message = "Добрый день!\n[[Открыть сайт|url:https://example.com]]";

        $result = $this->parser->parse($message);

        $this->assertEquals('Добрый день!', $result->text);
        $this->assertCount(1, $result->buttons);
        $this->assertEquals('Открыть сайт', $result->buttons[0]->text);
        $this->assertEquals(ButtonType::URL, $result->buttons[0]->type);
        $this->assertEquals('https://example.com', $result->buttons[0]->value);
    }

    public function test_parses_callback_button(): void
    {
        $message = "Выберите действие\n[[Назад|callback:back]]";

        $result = $this->parser->parse($message);

        $this->assertEquals('Выберите действие', $result->text);
        $this->assertCount(1, $result->buttons);
        $this->assertEquals('Назад', $result->buttons[0]->text);
        $this->assertEquals(ButtonType::CALLBACK, $result->buttons[0]->type);
        $this->assertEquals('back', $result->buttons[0]->value);
    }

    public function test_parses_phone_button(): void
    {
        $message = "Поделитесь контактом\n[[Отправить номер|phone]]";

        $result = $this->parser->parse($message);

        $this->assertEquals('Поделитесь контактом', $result->text);
        $this->assertCount(1, $result->buttons);
        $this->assertEquals('Отправить номер', $result->buttons[0]->text);
        $this->assertEquals(ButtonType::PHONE, $result->buttons[0]->type);
        $this->assertNull($result->buttons[0]->value);
    }

    public function test_parses_text_button_without_command(): void
    {
        $message = "Выберите вариант\n[[Вариант 1]]";

        $result = $this->parser->parse($message);

        $this->assertEquals('Выберите вариант', $result->text);
        $this->assertCount(1, $result->buttons);
        $this->assertEquals('Вариант 1', $result->buttons[0]->text);
        $this->assertEquals(ButtonType::TEXT, $result->buttons[0]->type);
        $this->assertNull($result->buttons[0]->value);
    }

    public function test_parses_multiple_buttons_same_row(): void
    {
        $message = "Выберите:\n[[Да|callback:yes]] [[Нет|callback:no]]";

        $result = $this->parser->parse($message);

        $this->assertEquals('Выберите:', $result->text);
        $this->assertCount(2, $result->buttons);
        $this->assertEquals(0, $result->buttons[0]->row);
        $this->assertEquals(0, $result->buttons[1]->row);
    }

    public function test_parses_multiple_buttons_different_rows(): void
    {
        $message = "Выберите:\n[[Кнопка 1|callback:one]]\n[[Кнопка 2|callback:two]]";

        $result = $this->parser->parse($message);

        $this->assertEquals('Выберите:', $result->text);
        $this->assertCount(2, $result->buttons);
        $this->assertEquals(0, $result->buttons[0]->row);
        $this->assertEquals(1, $result->buttons[1]->row);
    }

    public function test_parses_complex_message(): void
    {
        $message = <<<'MSG'
Добрый день, какой у вас вопрос?
[[Открыть сайт|url:https://example.com]]
[[Вернуться назад|callback:back]]
[[Позвать оператора]]
MSG;

        $result = $this->parser->parse($message);

        $this->assertEquals('Добрый день, какой у вас вопрос?', $result->text);
        $this->assertCount(3, $result->buttons);

        // URL button
        $this->assertEquals('Открыть сайт', $result->buttons[0]->text);
        $this->assertEquals(ButtonType::URL, $result->buttons[0]->type);
        $this->assertEquals('https://example.com', $result->buttons[0]->value);
        $this->assertEquals(0, $result->buttons[0]->row);

        // Callback button
        $this->assertEquals('Вернуться назад', $result->buttons[1]->text);
        $this->assertEquals(ButtonType::CALLBACK, $result->buttons[1]->type);
        $this->assertEquals('back', $result->buttons[1]->value);
        $this->assertEquals(1, $result->buttons[1]->row);

        // Text button
        $this->assertEquals('Позвать оператора', $result->buttons[2]->text);
        $this->assertEquals(ButtonType::TEXT, $result->buttons[2]->type);
        $this->assertNull($result->buttons[2]->value);
        $this->assertEquals(2, $result->buttons[2]->row);
    }

    public function test_has_buttons_returns_true_when_buttons_exist(): void
    {
        $this->assertTrue($this->parser->hasButtons('Текст [[Кнопка]]'));
    }

    public function test_has_buttons_returns_false_when_no_buttons(): void
    {
        $this->assertFalse($this->parser->hasButtons('Обычный текст'));
    }

    public function test_has_inline_buttons(): void
    {
        $message = "Текст\n[[Ссылка|url:https://example.com]]";

        $result = $this->parser->parse($message);

        $this->assertTrue($result->hasInlineButtons());
        $this->assertFalse($result->hasReplyKeyboardButtons());
    }

    public function test_has_reply_keyboard_buttons(): void
    {
        $message = "Текст\n[[Отправить|phone]]";

        $result = $this->parser->parse($message);

        $this->assertFalse($result->hasInlineButtons());
        $this->assertTrue($result->hasReplyKeyboardButtons());
    }

    public function test_preserves_markdown_in_text(): void
    {
        $message = "**Важно!**\nОбратите _внимание_\n[[Понятно|callback:ok]]";

        $result = $this->parser->parse($message);

        $this->assertStringContainsString('**Важно!**', $result->text);
        $this->assertStringContainsString('_внимание_', $result->text);
    }

    public function test_handles_url_with_query_params(): void
    {
        $message = '[[Перейти|url:https://example.com/page?param=value&other=123]]';

        $result = $this->parser->parse($message);

        $this->assertEquals('https://example.com/page?param=value&other=123', $result->buttons[0]->value);
    }

    public function test_handles_callback_with_complex_data(): void
    {
        $message = '[[Действие|callback:action_123_data]]';

        $result = $this->parser->parse($message);

        $this->assertEquals('action_123_data', $result->buttons[0]->value);
    }
}
