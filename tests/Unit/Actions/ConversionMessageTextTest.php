<?php

namespace Tests\Unit\Actions;

use App\Actions\Telegram\ConversionMessageText;
use Tests\TestCase;

class ConversionMessageTextTest extends TestCase
{
    /**
     * Тест конвертирования жирного текста
     */
    public function test_converts_bold_text(): void
    {
        $text = 'Это жирный текст';
        $entities = [
            [
                'offset' => 4,
                'length' => 6,
                'type' => 'bold',
            ],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Это **жирный** текст', $result);
    }

    /**
     * Тест конвертирования курсивного текста
     */
    public function test_converts_italic_text(): void
    {
        $text = 'Это курсивный текст';
        $entities = [
            [
                'offset' => 4,
                'length' => 9,
                'type' => 'italic',
            ],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Это _курсивный_ текст', $result);
    }

    /**
     * Тест конвертирования кода
     */
    public function test_converts_code_text(): void
    {
        $text = 'Выполни команду ls -la';
        $entities = [
            [
                'offset' => 16,
                'length' => 6,
                'type' => 'code',
            ],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Выполни команду `ls -la`', $result);
    }

    /**
     * Тест конвертирования блока кода
     */
    public function test_converts_pre_text(): void
    {
        $text = 'Код: php artisan test';
        $entities = [
            [
                'offset' => 5,
                'length' => 19,
                'type' => 'pre',
            ],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $expected = "Код: ```\nphp artisan test\n```";
        $this->assertEquals($expected, $result);
    }

    /**
     * Тест конвертирования текстовых ссылок
     */
    public function test_converts_text_link(): void
    {
        $text = 'Перейди на GitHub';
        $entities = [
            [
                'offset' => 11,
                'length' => 6,
                'type' => 'text_link',
                'url' => 'https://github.com',
            ],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Перейди на [GitHub](https://github.com)', $result);
    }

    /**
     * Тест обработки неизвестного типа
     */
    public function test_handles_unknown_type(): void
    {
        $text = 'Обычный текст';
        $entities = [
            [
                'offset' => 0,
                'length' => 7,
                'type' => 'unknown_type',
            ],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Обычный текст', $result);
    }

    /**
     * Тест множественных форматирований
     */
    public function test_handles_multiple_entities(): void
    {
        $text = 'Это жирный и курсивный текст';
        $entities = [
            [
                'offset' => 4,
                'length' => 6,
                'type' => 'bold',
            ],
            [
                'offset' => 13,
                'length' => 9,
                'type' => 'italic',
            ],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Это **жирный** и _курсивный_ текст', $result);
    }

    /**
     * Тест вложенных форматирований (обрабатываются в обратном порядке)
     */
    public function test_handles_nested_entities(): void
    {
        $text = 'Жирный курсивный текст';
        $entities = [
            [
                'offset' => 0,
                'length' => 24,
                'type' => 'bold',
            ],
            [
                'offset' => 7,
                'length' => 9,
                'type' => 'italic',
            ],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        // Из-за обратной сортировки сначала обработается italic, затем bold
        $this->assertEquals('**Жирный _курсивный_ текст**', $result);
    }

    /**
     * Тест с пустым массивом entities
     */
    public function test_handles_empty_entities(): void
    {
        $text = 'Обычный текст без форматирования';
        $entities = [];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Обычный текст без форматирования', $result);
    }

    /**
     * Тест с пустым текстом
     */
    public function test_handles_empty_text(): void
    {
        $text = '';
        $entities = [];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('', $result);
    }

    /**
     * Тест с Unicode символами
     */
    public function test_handles_unicode_text(): void
    {
        $text = 'Привет 👋 мир 🌍!';
        $entities = [
            [
                'offset' => 0,
                'length' => 6,
                'type' => 'bold',
            ],
            [
                'offset' => 9,
                'length' => 3,
                'type' => 'italic',
            ],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('**Привет** 👋 _мир_ 🌍!', $result);
    }

    /**
     * Тест с форматированием в начале текста
     */
    public function test_handles_formatting_at_start(): void
    {
        $text = 'Жирный текст в начале';
        $entities = [
            [
                'offset' => 0,
                'length' => 6,
                'type' => 'bold',
            ],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('**Жирный** текст в начале', $result);
    }

    /**
     * Тест с форматированием в конце текста
     */
    public function test_handles_formatting_at_end(): void
    {
        $text = 'Текст с жирным концом';
        $entities = [
            [
                'offset' => 8,
                'length' => 13,
                'type' => 'bold',
            ],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('Текст с **жирным концом**', $result);
    }

    /**
     * Тест с полным форматированием текста
     */
    public function test_handles_full_text_formatting(): void
    {
        $text = 'Весь текст курсивный';
        $entities = [
            [
                'offset' => 0,
                'length' => 20,
                'type' => 'italic',
            ],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        $this->assertEquals('_Весь текст курсивный_', $result);
    }

    /**
     * Тест сортировки entities по offset в обратном порядке
     */
    public function test_entities_are_sorted_by_offset_desc(): void
    {
        $text = 'Первый второй третий';
        $entities = [
            [
                'offset' => 0,
                'length' => 6,
                'type' => 'bold',
            ],
            [
                'offset' => 14,
                'length' => 6,
                'type' => 'code',
            ],
            [
                'offset' => 7,
                'length' => 6,
                'type' => 'italic',
            ],
        ];

        $result = ConversionMessageText::conversionMarkdownFormat($text, $entities);

        // Должны обрабатываться в порядке: третий (14), второй (7), первый (0)
        $this->assertEquals('**Первый** _второй_ `третий`', $result);
    }
}
