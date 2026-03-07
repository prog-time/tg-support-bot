<?php

namespace Tests\Unit\Modules\Telegram\Actions;

use App\Modules\Telegram\Actions\ConversionMessageText;
use Tests\TestCase;

class ConversionMessageTextTest extends TestCase
{
    public function test_converts_bold_text(): void
    {
        $text = 'Это жирный текст';
        $entities = [
            ['offset' => 4, 'length' => 6, 'type' => 'bold'],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Это *жирный* текст', $result);
    }

    public function test_converts_italic_text(): void
    {
        $text = 'Это курсивный текст';
        $entities = [
            ['offset' => 4, 'length' => 9, 'type' => 'italic'],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Это _курсивный_ текст', $result);
    }

    public function test_converts_code_text(): void
    {
        $text = 'Выполни команду ls -la';
        $entities = [
            ['offset' => 16, 'length' => 6, 'type' => 'code'],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Выполни команду `ls -la`', $result);
    }

    public function test_converts_pre_text(): void
    {
        $text = 'Код: php artisan test';
        $entities = [
            ['offset' => 5, 'length' => 16, 'type' => 'pre'],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals("Код: ```\nphp artisan test\n```", $result);
    }

    public function test_converts_text_link(): void
    {
        $text = 'Перейди на GitHub';
        $entities = [
            ['offset' => 11, 'length' => 6, 'type' => 'text_link', 'url' => 'https://github.com'],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Перейди на [GitHub](https://github.com)', $result);
    }

    public function test_handles_unknown_type(): void
    {
        $text = 'Обычный текст';
        $entities = [
            ['offset' => 0, 'length' => 7, 'type' => 'unknown_type'],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Обычный текст', $result);
    }

    public function test_handles_multiple_entities(): void
    {
        $text = 'Это жирный и курсивный текст';
        $entities = [
            ['offset' => 4, 'length' => 6, 'type' => 'bold'],
            ['offset' => 13, 'length' => 9, 'type' => 'italic'],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Это *жирный* и _курсивный_ текст', $result);
    }

    public function test_handles_nested_entities(): void
    {
        $text = 'Жирный курсивный текст';
        $entities = [
            ['offset' => 0, 'length' => 22, 'type' => 'bold'],
            ['offset' => 7, 'length' => 9, 'type' => 'italic'],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        // Outer bold opens first, inner italic wraps "курсивный"
        $this->assertEquals('*Жирный _курсивный_ текст*', $result);
    }

    public function test_handles_empty_entities(): void
    {
        $text = 'Обычный текст без форматирования';
        $entities = [];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Обычный текст без форматирования', $result);
    }

    public function test_handles_empty_text(): void
    {
        $text = '';
        $entities = [];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('', $result);
    }

    public function test_handles_unicode_text(): void
    {
        $text = 'Привет 👋 мир 🌍!';
        $entities = [
            ['offset' => 0, 'length' => 6, 'type' => 'bold'],
            ['offset' => 9, 'length' => 3, 'type' => 'italic'],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        // '!' is a MarkdownV2 special char and must be escaped
        $this->assertEquals('*Привет* 👋 _мир_ 🌍\!', $result);
    }

    public function test_handles_formatting_at_start(): void
    {
        $text = 'Жирный текст в начале';
        $entities = [
            ['offset' => 0, 'length' => 6, 'type' => 'bold'],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('*Жирный* текст в начале', $result);
    }

    public function test_handles_formatting_at_end(): void
    {
        $text = 'Текст с жирным концом';
        $entities = [
            ['offset' => 8, 'length' => 13, 'type' => 'bold'],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Текст с *жирным концом*', $result);
    }

    public function test_handles_full_text_formatting(): void
    {
        $text = 'Весь текст курсивный';
        $entities = [
            ['offset' => 0, 'length' => 20, 'type' => 'italic'],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('_Весь текст курсивный_', $result);
    }

    public function test_entities_are_sorted_by_offset_asc(): void
    {
        $text = 'Первый второй третий';
        $entities = [
            ['offset' => 0, 'length' => 6, 'type' => 'bold'],
            ['offset' => 14, 'length' => 6, 'type' => 'code'],
            ['offset' => 7, 'length' => 6, 'type' => 'italic'],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('*Первый* _второй_ `третий`', $result);
    }

    public function test_escapes_special_chars_in_plain_text(): void
    {
        $text = 'Привет! Как дела?';
        $entities = [
            ['offset' => 0, 'length' => 7, 'type' => 'bold'],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        // '!' inside bold is escaped, '?' outside is not special
        $this->assertEquals('*Привет\!* Как дела?', $result);
    }

    public function test_has_formatting_entities_returns_false_for_url_only(): void
    {
        $entities = [
            ['offset' => 0, 'length' => 23, 'type' => 'url'],
        ];

        $this->assertFalse(ConversionMessageText::hasFormattingEntities($entities));
    }

    public function test_has_formatting_entities_returns_true_for_bold(): void
    {
        $entities = [
            ['offset' => 0, 'length' => 23, 'type' => 'url'],
            ['offset' => 24, 'length' => 6, 'type' => 'bold'],
        ];

        $this->assertTrue(ConversionMessageText::hasFormattingEntities($entities));
    }

    public function test_has_formatting_entities_returns_false_for_non_formatting(): void
    {
        $entities = [
            ['offset' => 0, 'length' => 10, 'type' => 'mention'],
            ['offset' => 11, 'length' => 5, 'type' => 'hashtag'],
            ['offset' => 17, 'length' => 6, 'type' => 'bot_command'],
        ];

        $this->assertFalse(ConversionMessageText::hasFormattingEntities($entities));
    }
}
