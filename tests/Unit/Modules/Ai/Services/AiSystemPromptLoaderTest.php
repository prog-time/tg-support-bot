<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Ai\Services;

use App\Models\AiFaqEntry;
use App\Modules\Ai\Services\AiSystemPromptLoader;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiSystemPromptLoaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_render_returns_empty_string_when_no_db_value(): void
    {
        $this->assertSame('', (new AiSystemPromptLoader())->render());
    }

    public function test_render_returns_db_value_when_set(): void
    {
        app(SettingsService::class)->set(AiSystemPromptLoader::SETTING_KEY, 'Промпт из БД');

        $this->assertSame('Промпт из БД', (new AiSystemPromptLoader())->render());
    }

    public function test_render_memoizes_for_the_object_lifetime(): void
    {
        $loader = new AiSystemPromptLoader();
        app(SettingsService::class)->set(AiSystemPromptLoader::SETTING_KEY, 'Первый');

        $first = $loader->render();
        app(SettingsService::class)->set(AiSystemPromptLoader::SETTING_KEY, 'Второй');

        $this->assertSame('Первый', $first);
        $this->assertSame('Первый', $loader->render());
    }

    public function test_render_appends_enabled_faq_entries_after_base_prompt(): void
    {
        app(SettingsService::class)->set(AiSystemPromptLoader::SETTING_KEY, 'Базовый промпт');
        AiFaqEntry::create(['question' => 'Сколько стоит доставка?', 'answer' => '300 ₽', 'enabled' => true]);
        AiFaqEntry::create(['question' => 'Скрытый вопрос', 'answer' => 'Скрытый ответ', 'enabled' => false]);
        AiFaqEntry::create(['question' => 'Есть ли самовывоз?', 'answer' => 'Да, ул. Ленина, 1', 'enabled' => true]);

        $expected = "Базовый промпт\n\n"
            . AiSystemPromptLoader::FAQ_HEADING . "\n\n"
            . "Вопрос: Сколько стоит доставка?\nОтвет: 300 ₽\n\n"
            . "Вопрос: Есть ли самовывоз?\nОтвет: Да, ул. Ленина, 1";

        $this->assertSame($expected, (new AiSystemPromptLoader())->render());
    }

    public function test_render_returns_only_faq_section_when_base_prompt_is_empty(): void
    {
        AiFaqEntry::create(['question' => 'Q', 'answer' => 'A', 'enabled' => true]);

        $this->assertSame(
            AiSystemPromptLoader::FAQ_HEADING . "\n\nВопрос: Q\nОтвет: A",
            (new AiSystemPromptLoader())->render(),
        );
    }

    public function test_render_skips_faq_section_when_all_entries_are_disabled(): void
    {
        app(SettingsService::class)->set(AiSystemPromptLoader::SETTING_KEY, 'Базовый промпт');
        AiFaqEntry::create(['question' => 'Q', 'answer' => 'A', 'enabled' => false]);

        $this->assertSame('Базовый промпт', (new AiSystemPromptLoader())->render());
    }

    public function test_render_appends_clarify_instructions_last_when_mode_is_on(): void
    {
        app(SettingsService::class)->set(AiSystemPromptLoader::SETTING_KEY, 'Базовый промпт');
        app(SettingsService::class)->set(AiSystemPromptLoader::CLARIFY_MODE_KEY, true);
        AiFaqEntry::create(['question' => 'Q', 'answer' => 'A', 'enabled' => true]);

        $prompt = (new AiSystemPromptLoader())->render();

        $this->assertStringStartsWith('Базовый промпт', $prompt);
        $this->assertStringEndsWith("\n\n" . AiSystemPromptLoader::CLARIFY_INSTRUCTIONS, $prompt);
        $this->assertStringContainsString('Вопрос: Q', $prompt);
    }

    public function test_render_omits_clarify_instructions_when_mode_is_off(): void
    {
        app(SettingsService::class)->set(AiSystemPromptLoader::SETTING_KEY, 'Базовый промпт');
        app(SettingsService::class)->set(AiSystemPromptLoader::CLARIFY_MODE_KEY, false);

        $this->assertSame('Базовый промпт', (new AiSystemPromptLoader())->render());
    }

    public function test_render_returns_clarify_instructions_alone_without_base_prompt(): void
    {
        app(SettingsService::class)->set(AiSystemPromptLoader::CLARIFY_MODE_KEY, true);

        $this->assertSame(AiSystemPromptLoader::CLARIFY_INSTRUCTIONS, (new AiSystemPromptLoader())->render());
    }
}
