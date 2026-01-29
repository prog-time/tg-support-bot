<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Button;

use App\Services\Button\ButtonParser;
use App\Services\Button\KeyboardBuilder;
use PHPUnit\Framework\TestCase;

class KeyboardBuilderTest extends TestCase
{
    private ButtonParser $parser;

    private KeyboardBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ButtonParser();
        $this->builder = new KeyboardBuilder();
    }

    public function test_builds_telegram_inline_keyboard(): void
    {
        $message = "Выберите:\n[[Да|callback:yes]] [[Нет|callback:no]]";
        $parsed = $this->parser->parse($message);

        $keyboard = $this->builder->buildTelegramInlineKeyboard($parsed);

        $this->assertNotNull($keyboard);
        $this->assertArrayHasKey('inline_keyboard', $keyboard);
        $this->assertCount(1, $keyboard['inline_keyboard']);
        $this->assertCount(2, $keyboard['inline_keyboard'][0]);

        $this->assertEquals('Да', $keyboard['inline_keyboard'][0][0]['text']);
        $this->assertEquals('yes', $keyboard['inline_keyboard'][0][0]['callback_data']);

        $this->assertEquals('Нет', $keyboard['inline_keyboard'][0][1]['text']);
        $this->assertEquals('no', $keyboard['inline_keyboard'][0][1]['callback_data']);
    }

    public function test_builds_telegram_inline_keyboard_with_url(): void
    {
        $message = '[[Перейти|url:https://example.com]]';
        $parsed = $this->parser->parse($message);

        $keyboard = $this->builder->buildTelegramInlineKeyboard($parsed);

        $this->assertNotNull($keyboard);
        $this->assertEquals('Перейти', $keyboard['inline_keyboard'][0][0]['text']);
        $this->assertEquals('https://example.com', $keyboard['inline_keyboard'][0][0]['url']);
    }

    public function test_builds_telegram_reply_keyboard(): void
    {
        $message = "Выберите:\n[[Вариант 1]] [[Вариант 2]]";
        $parsed = $this->parser->parse($message);

        $keyboard = $this->builder->buildTelegramReplyKeyboard($parsed);

        $this->assertNotNull($keyboard);
        $this->assertArrayHasKey('keyboard', $keyboard);
        $this->assertTrue($keyboard['one_time_keyboard']);
        $this->assertTrue($keyboard['resize_keyboard']);
        $this->assertCount(1, $keyboard['keyboard']);
        $this->assertCount(2, $keyboard['keyboard'][0]);

        $this->assertEquals('Вариант 1', $keyboard['keyboard'][0][0]['text']);
        $this->assertEquals('Вариант 2', $keyboard['keyboard'][0][1]['text']);
    }

    public function test_builds_telegram_reply_keyboard_with_phone(): void
    {
        $message = '[[Поделиться номером|phone]]';
        $parsed = $this->parser->parse($message);

        $keyboard = $this->builder->buildTelegramReplyKeyboard($parsed);

        $this->assertNotNull($keyboard);
        $this->assertEquals('Поделиться номером', $keyboard['keyboard'][0][0]['text']);
        $this->assertTrue($keyboard['keyboard'][0][0]['request_contact']);
    }

    public function test_builds_telegram_keyboard_auto_selects_inline(): void
    {
        $message = '[[Кнопка|callback:action]]';
        $parsed = $this->parser->parse($message);

        $keyboard = $this->builder->buildTelegramKeyboard($parsed);

        $this->assertNotNull($keyboard);
        $this->assertArrayHasKey('inline_keyboard', $keyboard);
    }

    public function test_builds_telegram_keyboard_auto_selects_reply(): void
    {
        $message = '[[Текст кнопки]]';
        $parsed = $this->parser->parse($message);

        $keyboard = $this->builder->buildTelegramKeyboard($parsed);

        $this->assertNotNull($keyboard);
        $this->assertArrayHasKey('keyboard', $keyboard);
    }

    public function test_builds_telegram_keyboard_multiple_rows(): void
    {
        $message = "[[Кнопка 1|callback:one]]\n[[Кнопка 2|callback:two]]\n[[Кнопка 3|callback:three]]";
        $parsed = $this->parser->parse($message);

        $keyboard = $this->builder->buildTelegramInlineKeyboard($parsed);

        $this->assertNotNull($keyboard);
        $this->assertCount(3, $keyboard['inline_keyboard']);
        $this->assertEquals('Кнопка 1', $keyboard['inline_keyboard'][0][0]['text']);
        $this->assertEquals('Кнопка 2', $keyboard['inline_keyboard'][1][0]['text']);
        $this->assertEquals('Кнопка 3', $keyboard['inline_keyboard'][2][0]['text']);
    }

    public function test_builds_vk_keyboard(): void
    {
        $message = '[[Да|callback:yes]] [[Нет|callback:no]]';
        $parsed = $this->parser->parse($message);

        $keyboard = $this->builder->buildVkKeyboard($parsed);

        $this->assertNotNull($keyboard);
        $this->assertFalse($keyboard['inline']);
        $this->assertFalse($keyboard['one_time']);
        $this->assertCount(1, $keyboard['buttons']);
        $this->assertCount(2, $keyboard['buttons'][0]);

        $this->assertEquals('callback', $keyboard['buttons'][0][0]['action']['type']);
        $this->assertEquals('Да', $keyboard['buttons'][0][0]['action']['label']);
    }

    public function test_builds_vk_keyboard_with_url(): void
    {
        $message = '[[Перейти|url:https://example.com]]';
        $parsed = $this->parser->parse($message);

        $keyboard = $this->builder->buildVkKeyboard($parsed);

        $this->assertNotNull($keyboard);
        $this->assertEquals('open_link', $keyboard['buttons'][0][0]['action']['type']);
        $this->assertEquals('Перейти', $keyboard['buttons'][0][0]['action']['label']);
        $this->assertEquals('https://example.com', $keyboard['buttons'][0][0]['action']['link']);
    }

    public function test_builds_vk_keyboard_inline(): void
    {
        $message = '[[Кнопка|callback:action]]';
        $parsed = $this->parser->parse($message);

        $keyboard = $this->builder->buildVkKeyboard($parsed, inline: true);

        $this->assertNotNull($keyboard);
        $this->assertTrue($keyboard['inline']);
    }

    public function test_returns_null_when_no_buttons(): void
    {
        $message = 'Обычный текст без кнопок';
        $parsed = $this->parser->parse($message);

        $this->assertNull($this->builder->buildTelegramKeyboard($parsed));
        $this->assertNull($this->builder->buildVkKeyboard($parsed));
    }

    public function test_returns_null_when_no_inline_buttons_for_inline_keyboard(): void
    {
        $message = '[[Текст]]';
        $parsed = $this->parser->parse($message);

        $this->assertNull($this->builder->buildTelegramInlineKeyboard($parsed));
    }

    public function test_returns_null_when_no_reply_buttons_for_reply_keyboard(): void
    {
        $message = '[[Кнопка|callback:action]]';
        $parsed = $this->parser->parse($message);

        $this->assertNull($this->builder->buildTelegramReplyKeyboard($parsed));
    }
}
