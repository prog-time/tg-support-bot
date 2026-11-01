<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

use App\Models\AiFaqEntry;
use App\Services\Settings\SettingsService;

class AiSystemPromptLoader
{
    /** Settings key under which the prompt is stored. */
    public const SETTING_KEY = 'ai.system_prompt';

    /** Settings key of the «clarifying questions» (grill me) mode toggle. */
    public const CLARIFY_MODE_KEY = 'ai.clarify_mode';

    /** Heading of the FAQ section appended to the prompt. */
    public const FAQ_HEADING = 'Ответы на частые вопросы. Это проверенные факты: если вопрос клиента совпадает по смыслу с одним из них, отвечай на его основе, не противоречь им и не выдумывай того, чего в них нет.';

    /** Instructions appended to the prompt when the clarify mode is on. */
    public const CLARIFY_INSTRUCTIONS = <<<'TEXT'
        Режим уточняющих вопросов.
        Если вопрос клиента неоднозначен или для точного ответа не хватает данных, не угадывай и не отвечай сразу. Задай ровно один уточняющий вопрос — самый важный на данный момент — и дождись ответа клиента.
        Задавай вопросы строго по одному, каждый следующий — с учётом предыдущих ответов. Когда информации достаточно, дай полный ответ.
        Не спрашивай то, что клиент уже сообщил в переписке. Если ответ есть в частых вопросах или данных уже хватает — отвечай сразу, без уточнений. Не задавай больше трёх уточняющих вопросов подряд.
        TEXT;

    private ?string $cached = null;

    /**
     * The system prompt for AI requests.
     *
     * Composed of up to three sections joined by blank lines: the admin's base
     * prompt (`ai.system_prompt`, verbatim — no templating), the enabled FAQ
     * entries ({@see AiFaqEntry}) and the clarify-mode instructions when
     * `ai.clarify_mode` is on. Returns '' when every section is empty.
     * Memoized for the object's lifetime.
     *
     * @return string Prompt text
     */
    public function render(): string
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $settings = app(SettingsService::class);
        $stored = $settings->get(self::SETTING_KEY);

        $sections = array_filter([
            is_string($stored) ? $stored : '',
            $this->faqSection(),
            (bool) ($settings->get(self::CLARIFY_MODE_KEY) ?? false) ? self::CLARIFY_INSTRUCTIONS : '',
        ], static fn (string $section): bool => trim($section) !== '');

        return $this->cached = implode("\n\n", $sections);
    }

    /**
     * Render the enabled FAQ entries as a prompt section.
     *
     * @return string Section text, or '' when there are no enabled entries
     */
    private function faqSection(): string
    {
        $entries = AiFaqEntry::query()
            ->where('enabled', true)
            ->orderBy('id')
            ->get(['question', 'answer']);

        if ($entries->isEmpty()) {
            return '';
        }

        $items = $entries->map(
            static fn (AiFaqEntry $entry): string => 'Вопрос: ' . trim($entry->question) . "\nОтвет: " . trim($entry->answer)
        );

        return self::FAQ_HEADING . "\n\n" . $items->implode("\n\n");
    }
}
