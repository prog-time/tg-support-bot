<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\AiFaqEntry;
use App\Modules\Admin\Services\ChannelStatusService;
use App\Modules\Ai\Services\AiSystemPromptLoader;
use App\Services\Settings\SettingsService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * AI assistant settings page.
 *
 * Manages the master AI toggle, provider selection, auto-reply mode, the
 * clarifying-questions (grill me) mode and the system prompt. All values are
 * persisted via SettingsService (DB-backed). The system prompt is stored only
 * in the DB under `ai.system_prompt` (empty until an admin saves one); FAQ
 * entries are edited on {@see AiFaqPage} and appended to it at request time.
 *
 * Access: authenticated admin only (enforced in route middleware).
 * Layout: custom dark-sidebar admin layout (layouts.admin-settings).
 *
 * Route: GET /admin/settings/ai
 */
#[Layout('layouts.admin-settings')]
class AiAssistantPage extends Component
{
    /** @var bool Master AI on/off toggle */
    public bool $ai_enabled = false;

    /** @var string Active provider: openai|deepseek|gigachat */
    public string $default_provider = 'openai';

    /** @var bool Auto-reply mode (true = auto, false = draft) */
    public bool $auto_reply = false;

    /** @var bool «Clarifying questions» (grill me) mode — AI asks one question at a time before answering */
    public bool $clarify_mode = false;

    /** @var int Number of FAQ entries currently fed to the AI */
    public int $faqEnabledCount = 0;

    /** @var string System prompt text — stored in the DB (`ai.system_prompt`) */
    public string $system_prompt = '';

    /** @var array<string, bool> Whether each provider has its credentials configured */
    public array $providerConfigured = [];

    /** @var array<string, string> Configured model name per provider */
    public array $providerModels = [];

    /** @var bool Whether the «Бот AI помощника» integration (telegram_ai.token) is configured */
    public bool $aiBotConnected = false;

    /** @var bool Show auto-reply warning/confirm */
    public bool $showAutoReplyWarning = false;

    /** @var bool Pending auto-reply value awaiting confirmation */
    public bool $pendingAutoReply = false;

    /** @var bool Success banner visible */
    public bool $saved = false;

    /** @var array<string, string> Validation errors keyed by field name */
    public array $formErrors = [];

    /**
     * Load current values from SettingsService on mount.
     */
    public function mount(SettingsService $settings): void
    {
        $this->loadFields($settings);
    }

    /**
     * Called when the master AI toggle changes via wire:model.live.
     *
     * The «Включить AI помощника» switch persists immediately — it does not
     * wait for the «Сохранить» button. All other fields still require save.
     *
     * AI can be enabled even without the AI bot configured. Without it, drafts
     * are only shown in the admin panel and not duplicated to the supergroup.
     * Disabling is always allowed.
     *
     * @param bool $value New toggle state
     */
    public function updatedAiEnabled(bool $value): void
    {
        app(SettingsService::class)->set('ai.enabled', $value);
    }

    /**
     * Called when the auto-reply toggle changes via wire:model.live.
     *
     * When enabling auto-reply, show a confirmation warning instead of
     * saving immediately (BR-002: warn before enabling auto-reply).
     */
    public function updatedAutoReply(bool $value): void
    {
        if ($value) {
            $this->auto_reply = false;
            $this->pendingAutoReply = true;
            $this->showAutoReplyWarning = true;
        } else {
            $this->showAutoReplyWarning = false;
            $this->pendingAutoReply = false;
        }
    }

    /**
     * Confirm auto-reply activation after the user accepted the warning.
     */
    public function confirmAutoReply(): void
    {
        $this->auto_reply = true;
        $this->pendingAutoReply = false;
        $this->showAutoReplyWarning = false;
    }

    /**
     * Cancel auto-reply activation — keep draft mode.
     */
    public function cancelAutoReply(): void
    {
        $this->auto_reply = false;
        $this->pendingAutoReply = false;
        $this->showAutoReplyWarning = false;
    }

    /**
     * Save all AI settings via SettingsService.
     *
     * The system prompt is persisted first and unconditionally, so a
     * provider-validation failure never blocks saving it; the toggles persist
     * only after validation passes.
     */
    public function save(SettingsService $settings): void
    {
        $this->formErrors = [];
        $this->saved = false;

        $settings->set(AiSystemPromptLoader::SETTING_KEY, $this->system_prompt);

        if ($this->ai_enabled) {
            if (! in_array($this->default_provider, ['openai', 'deepseek', 'gigachat'], true)) {
                $this->formErrors['default_provider'] = 'Выберите допустимый провайдер.';
            } elseif (! $this->providerHasAccess($settings, $this->default_provider)) {
                $this->formErrors['default_provider'] = 'У выбранного провайдера не указаны доступы.';
            }
        }

        if (! empty($this->formErrors)) {
            return;
        }

        $settings->set('ai.enabled', $this->ai_enabled);
        $settings->set('ai.default_provider', $this->default_provider);
        $settings->set('ai.auto_reply', $this->auto_reply);
        $settings->set(AiSystemPromptLoader::CLARIFY_MODE_KEY, $this->clarify_mode);

        $this->saved = true;
    }

    /**
     * Render the component view.
     *
     * @return \Illuminate\View\View
     */
    public function render(): \Illuminate\View\View
    {
        return view('livewire.settings.ai-assistant-page');
    }

    /**
     * Populate component properties from SettingsService.
     *
     * A provider without configured access is never pre-selected: the stored
     * choice is kept only when configured, otherwise the first configured one.
     */
    private function loadFields(SettingsService $settings): void
    {
        $this->ai_enabled = (bool) ($settings->get('ai.enabled') ?? false);
        $this->auto_reply = (bool) ($settings->get('ai.auto_reply') ?? false);
        $this->clarify_mode = (bool) ($settings->get(AiSystemPromptLoader::CLARIFY_MODE_KEY) ?? false);
        $this->faqEnabledCount = AiFaqEntry::query()->where('enabled', true)->count();
        $stored = $settings->get(AiSystemPromptLoader::SETTING_KEY);
        $this->system_prompt = is_string($stored) ? $stored : '';

        $this->providerConfigured = [
            'openai' => $this->providerHasAccess($settings, 'openai'),
            'deepseek' => $this->providerHasAccess($settings, 'deepseek'),
            'gigachat' => $this->providerHasAccess($settings, 'gigachat'),
        ];

        $stored = (string) ($settings->get('ai.default_provider') ?? '');
        $this->default_provider = ($stored !== '' && ($this->providerConfigured[$stored] ?? false))
            ? $stored
            : $this->firstConfiguredProvider();

        $this->providerModels = [
            'openai' => (string) ($settings->get('ai.openai_model') ?? ''),
            'deepseek' => (string) ($settings->get('ai.deepseek_model') ?? ''),
            'gigachat' => (string) ($settings->get('ai.gigachat_model') ?? ''),
        ];

        $this->aiBotConnected = app(ChannelStatusService::class)->telegramAi()['connected'];
    }

    /**
     * Whether a provider has its access credentials configured.
     *
     * A provider with no credentials cannot be selected as the active AI
     * provider — the UI shows a «Доступы не указаны» flag instead of the
     * select button, and save() rejects it.
     *
     * @param SettingsService $settings Settings persistence layer
     * @param string          $provider Provider slug: openai|deepseek|gigachat
     *
     * @return bool True when the provider's primary credential is present
     */
    private function providerHasAccess(SettingsService $settings, string $provider): bool
    {
        return match ($provider) {
            'openai' => filled($settings->get('ai.openai_api_key')),
            'deepseek' => filled($settings->get('ai.deepseek_client_secret')),
            'gigachat' => filled($settings->get('ai.gigachat_client_secret')),
            default => false,
        };
    }

    /**
     * First provider (in card order) that has its access configured.
     *
     * Used as the default active selection so an unconfigured provider is
     * never pre-selected. Returns an empty string when no provider is set up.
     *
     * @return string Provider slug, or '' when none is configured
     */
    private function firstConfiguredProvider(): string
    {
        foreach ($this->providerConfigured as $slug => $configured) {
            if ($configured) {
                return $slug;
            }
        }

        return '';
    }
}
