<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Modules\Ai\Services\AiProviderVerificationService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Per-provider AI credentials configuration page.
 *
 * Handles access configuration for OpenAI, DeepSeek, and GigaChat.
 * Reads/writes via SettingsService (secrets stored encrypted).
 *
 * Secret fields (api_key, client_secret) are write-only in the UI:
 * they are never pre-filled from storage, and an empty value on save
 * does NOT overwrite the existing stored secret (blank-secret guard).
 *
 * Route: GET /admin/settings/ai/{provider}  (provider ∈ {openai, deepseek, gigachat})
 *
 * Access: authenticated admin only (enforced in route middleware).
 * Layout: custom dark-sidebar admin layout (layouts.admin-settings).
 */
#[Layout('layouts.admin-settings')]
class AiProviderAccessPage extends Component
{
    use WithFileUploads;

    /** @var string The current provider slug (openai|deepseek|gigachat) */
    public string $provider = 'openai';

    // ── OpenAI fields ─────────────────────────────────────────────────────────

    /** @var string|null OpenAI API key (secret — never pre-filled) */
    public ?string $openai_api_key = null;

    /** @var string|null OpenAI base URL */
    public ?string $openai_base_url = null;

    /** @var string|null OpenAI model name */
    public ?string $openai_model = null;

    /** @var int|null OpenAI max tokens */
    public ?int $openai_max_tokens = null;

    /** @var string|null OpenAI temperature */
    public ?string $openai_temperature = null;

    // ── DeepSeek fields ───────────────────────────────────────────────────────

    /** @var string|null DeepSeek client ID */
    public ?string $deepseek_client_id = null;

    /** @var string|null DeepSeek client secret (secret — never pre-filled) */
    public ?string $deepseek_client_secret = null;

    /** @var string|null DeepSeek base URL */
    public ?string $deepseek_base_url = null;

    /** @var string|null DeepSeek model name */
    public ?string $deepseek_model = null;

    /** @var int|null DeepSeek max tokens */
    public ?int $deepseek_max_tokens = null;

    /** @var string|null DeepSeek temperature */
    public ?string $deepseek_temperature = null;

    // ── GigaChat fields ───────────────────────────────────────────────────────

    /** @var string|null GigaChat client ID */
    public ?string $gigachat_client_id = null;

    /** @var string|null GigaChat client secret (secret — never pre-filled) */
    public ?string $gigachat_client_secret = null;

    /** @var string|null GigaChat base URL */
    public ?string $gigachat_base_url = null;

    /** @var string|null GigaChat model name */
    public ?string $gigachat_model = null;

    /** @var int|null GigaChat max tokens */
    public ?int $gigachat_max_tokens = null;

    /** @var string|null GigaChat temperature */
    public ?string $gigachat_temperature = null;

    /** @var string|null GigaChat OAuth scope (GIGACHAT_API_PERS | GIGACHAT_API_B2B | GIGACHAT_API_CORP) */
    public ?string $gigachat_scope = null;

    /** @var string|null Stored relative path of the GigaChat certificate (read-only display) */
    public ?string $gigachat_path_cert = null;

    /**
     * Uploaded GigaChat certificate file. Saved to storage/certs/russian_trusted_root_ca_pem.crt.
     *
     * @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null
     */
    public $gigachat_cert_file = null;

    /** @var bool Whether a GigaChat certificate file is already stored on disk */
    public bool $gigachat_cert_uploaded = false;

    /** Fixed on-disk name + storage-relative path for the GigaChat certificate. */
    private const GIGACHAT_CERT_NAME = 'russian_trusted_root_ca_pem.crt';

    private const GIGACHAT_CERT_RELATIVE = 'certs/russian_trusted_root_ca_pem.crt';

    /** Allowed GigaChat OAuth scopes (account types). */
    private const GIGACHAT_SCOPES = ['GIGACHAT_API_PERS', 'GIGACHAT_API_B2B', 'GIGACHAT_API_CORP'];

    // ── State ─────────────────────────────────────────────────────────────────

    /** @var bool Config persisted successfully */
    public bool $saved = false;

    /** @var string|null Verification failure notice (set when the connection check fails) */
    public ?string $verifyError = null;

    /** @var array<string, string> Validation errors keyed by field name */
    public array $formErrors = [];

    /**
     * Mount with the provider slug from the route.
     */
    public function mount(string $provider, SettingsService $settings): void
    {
        $this->provider = $provider;
        $this->loadFields($settings);
    }

    /**
     * Save the current provider's form values via SettingsService.
     *
     * Settings-only path (no connectivity check). Kept for programmatic use and
     * tests; the UI «Сохранить» button calls connect() (verify-before-save).
     */
    public function save(SettingsService $settings): void
    {
        $this->formErrors = [];
        $this->saved = false;
        $this->verifyError = null;

        match ($this->provider) {
            'openai' => $this->saveOpenAi($settings),
            'deepseek' => $this->saveDeepSeek($settings),
            'gigachat' => $this->saveGigaChat($settings),
            default => $this->formErrors['provider'] = 'Неизвестный провайдер.',
        };
    }

    /**
     * Verify the entered credentials against the provider API, then save.
     *
     * Mirrors the channel-integration «verify-before-save» flow:
     *   1. Resolve the secret to verify — entered value, else the stored one
     *      (blank-secret guard). Resolve base URL / model / cert from the form.
     *   2. Call AiProviderVerificationService::verifyX(). On failure → set
     *      $verifyError and return WITHOUT persisting anything.
     *   3. On success → persist via the existing saveX() path.
     */
    public function connect(SettingsService $settings, AiProviderVerificationService $verifier): void
    {
        $this->formErrors = [];
        $this->saved = false;
        $this->verifyError = null;

        $result = match ($this->provider) {
            'openai' => $this->verifyOpenAi($settings, $verifier),
            'deepseek' => $this->verifyDeepSeek($settings, $verifier),
            'gigachat' => $this->verifyGigaChat($settings, $verifier),
            default => ['success' => false, 'message' => 'Неизвестный провайдер.'],
        };

        if (! $result['success']) {
            // Field-level problems (empty key, etc.) are surfaced inline; a
            // connection failure goes to the dedicated notice.
            if (empty($this->formErrors)) {
                $this->verifyError = $result['message'];
            }

            return;
        }

        // Verified — persist via the settings-only path.
        $this->save($settings);
    }

    /**
     * Render the component view.
     *
     * @return \Illuminate\View\View
     */
    public function render(): \Illuminate\View\View
    {
        return view('livewire.settings.ai-provider-access-page');
    }

    // ── Field validation ──────────────────────────────────────────────────────

    /**
     * Validate OpenAI form fields into $formErrors.
     */
    private function validateOpenAi(): void
    {
        if ($this->openai_max_tokens !== null && $this->openai_max_tokens < 1) {
            $this->formErrors['openai_max_tokens'] = 'Макс. токенов должно быть положительным числом.';
        }
    }

    /**
     * Validate DeepSeek form fields into $formErrors.
     */
    private function validateDeepSeek(): void
    {
        if ($this->deepseek_max_tokens !== null && $this->deepseek_max_tokens < 1) {
            $this->formErrors['deepseek_max_tokens'] = 'Макс. токенов должно быть положительным числом.';
        }
    }

    /**
     * Validate GigaChat form fields (incl. uploaded certificate) into $formErrors.
     */
    private function validateGigaChat(): void
    {
        if ($this->gigachat_max_tokens !== null && $this->gigachat_max_tokens < 1) {
            $this->formErrors['gigachat_max_tokens'] = 'Макс. токенов должно быть положительным числом.';
        }

        if ($this->gigachat_scope !== null && $this->gigachat_scope !== ''
            && ! in_array($this->gigachat_scope, self::GIGACHAT_SCOPES, true)) {
            $this->formErrors['gigachat_scope'] = 'Недопустимый scope GigaChat.';
        }

        if ($this->gigachat_cert_file !== null) {
            $ext = strtolower((string) $this->gigachat_cert_file->getClientOriginalExtension());

            if (! in_array($ext, ['crt', 'pem', 'cer'], true)) {
                $this->formErrors['gigachat_cert_file'] = 'Допустимы файлы .crt, .pem, .cer.';
            } elseif ($this->gigachat_cert_file->getSize() > 1024 * 1024) {
                $this->formErrors['gigachat_cert_file'] = 'Файл слишком большой (макс. 1 МБ).';
            }
        }
    }

    // ── Pre-save verification (verify-before-save for connect()) ───────────────

    /**
     * Validate + verify OpenAI credentials against the API.
     *
     * @return array{success: bool, message: string}
     */
    private function verifyOpenAi(SettingsService $settings, AiProviderVerificationService $verifier): array
    {
        $this->validateOpenAi();

        if (! empty($this->formErrors)) {
            return ['success' => false, 'message' => ''];
        }

        $key = ($this->openai_api_key !== null && $this->openai_api_key !== '')
            ? $this->openai_api_key
            : (string) ($settings->get('ai.openai_api_key') ?? '');

        return $verifier->verifyOpenai($key, (string) ($this->openai_base_url ?? ''), (string) ($this->openai_model ?? ''));
    }

    /**
     * Validate + verify DeepSeek credentials against the API.
     *
     * @return array{success: bool, message: string}
     */
    private function verifyDeepSeek(SettingsService $settings, AiProviderVerificationService $verifier): array
    {
        $this->validateDeepSeek();

        if (! empty($this->formErrors)) {
            return ['success' => false, 'message' => ''];
        }

        $secret = ($this->deepseek_client_secret !== null && $this->deepseek_client_secret !== '')
            ? $this->deepseek_client_secret
            : (string) ($settings->get('ai.deepseek_client_secret') ?? '');

        return $verifier->verifyDeepseek($secret, (string) ($this->deepseek_base_url ?? ''), (string) ($this->deepseek_model ?? ''));
    }

    /**
     * Validate + verify GigaChat credentials against the OAuth endpoint.
     *
     * @return array{success: bool, message: string}
     */
    private function verifyGigaChat(SettingsService $settings, AiProviderVerificationService $verifier): array
    {
        $this->validateGigaChat();

        if (! empty($this->formErrors)) {
            return ['success' => false, 'message' => ''];
        }

        $secret = ($this->gigachat_client_secret !== null && $this->gigachat_client_secret !== '')
            ? $this->gigachat_client_secret
            : (string) ($settings->get('ai.gigachat_client_secret') ?? '');

        return $verifier->verifyGigachat(
            $secret,
            $this->resolveGigachatCertPath(),
            (string) ($this->gigachat_scope ?: 'GIGACHAT_API_PERS'),
        );
    }

    /**
     * Resolve the absolute path of the GigaChat CA certificate for verification:
     * a freshly uploaded file's temp path, else the already-stored cert, else ''.
     */
    private function resolveGigachatCertPath(): string
    {
        if ($this->gigachat_cert_file !== null) {
            return (string) ($this->gigachat_cert_file->getRealPath() ?: '');
        }

        $stored = storage_path(self::GIGACHAT_CERT_RELATIVE);

        return File::exists($stored) ? $stored : '';
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Populate non-secret fields from SettingsService.
     * Secret fields are intentionally left null (never pre-filled in UI).
     */
    private function loadFields(SettingsService $settings): void
    {
        // OpenAI non-secrets
        $this->openai_base_url = (string) ($settings->get('ai.openai_base_url') ?? '');
        $this->openai_model = (string) ($settings->get('ai.openai_model') ?? '');
        $rawOpenAiMax = $settings->get('ai.openai_max_tokens');
        $this->openai_max_tokens = $rawOpenAiMax !== null ? (int) $rawOpenAiMax : null;
        $this->openai_temperature = (string) ($settings->get('ai.openai_temperature') ?? '');

        // DeepSeek non-secrets
        $this->deepseek_client_id = (string) ($settings->get('ai.deepseek_client_id') ?? '');
        $this->deepseek_base_url = (string) ($settings->get('ai.deepseek_base_url') ?? '');
        $this->deepseek_model = (string) ($settings->get('ai.deepseek_model') ?? '');
        $rawDeepSeekMax = $settings->get('ai.deepseek_max_tokens');
        $this->deepseek_max_tokens = $rawDeepSeekMax !== null ? (int) $rawDeepSeekMax : null;
        $this->deepseek_temperature = (string) ($settings->get('ai.deepseek_temperature') ?? '');

        // GigaChat non-secrets
        $this->gigachat_client_id = (string) ($settings->get('ai.gigachat_client_id') ?? '');
        $this->gigachat_base_url = (string) ($settings->get('ai.gigachat_base_url') ?? '');
        $this->gigachat_model = (string) ($settings->get('ai.gigachat_model') ?? '');
        $rawGigaMax = $settings->get('ai.gigachat_max_tokens');
        $this->gigachat_max_tokens = $rawGigaMax !== null ? (int) $rawGigaMax : null;
        $this->gigachat_temperature = (string) ($settings->get('ai.gigachat_temperature') ?? '');
        $this->gigachat_path_cert = (string) ($settings->get('ai.gigachat_path_cert') ?? '');
        $this->gigachat_scope = (string) ($settings->get('ai.gigachat_scope') ?: 'GIGACHAT_API_PERS');
        $this->gigachat_cert_uploaded = File::exists(storage_path(self::GIGACHAT_CERT_RELATIVE));

        // Secret fields: intentionally left null — never pre-filled
        $this->openai_api_key = null;
        $this->deepseek_client_secret = null;
        $this->gigachat_client_secret = null;
    }

    /**
     * Validate and save OpenAI credentials.
     */
    private function saveOpenAi(SettingsService $settings): void
    {
        $this->validateOpenAi();

        if (! empty($this->formErrors)) {
            return;
        }

        // Secret: only overwrite if non-empty
        if ($this->openai_api_key !== null && $this->openai_api_key !== '') {
            $settings->set('ai.openai_api_key', $this->openai_api_key);
        }

        $settings->set('ai.openai_base_url', $this->openai_base_url ?? '');
        $settings->set('ai.openai_model', $this->openai_model ?? '');

        if ($this->openai_max_tokens !== null) {
            $settings->set('ai.openai_max_tokens', $this->openai_max_tokens);
        }

        $settings->set('ai.openai_temperature', $this->openai_temperature ?? '');

        $this->saved = true;
    }

    /**
     * Validate and save DeepSeek credentials.
     */
    private function saveDeepSeek(SettingsService $settings): void
    {
        $this->validateDeepSeek();

        if (! empty($this->formErrors)) {
            return;
        }

        $settings->set('ai.deepseek_client_id', $this->deepseek_client_id ?? '');

        // Secret: only overwrite if non-empty
        if ($this->deepseek_client_secret !== null && $this->deepseek_client_secret !== '') {
            $settings->set('ai.deepseek_client_secret', $this->deepseek_client_secret);
        }

        $settings->set('ai.deepseek_base_url', $this->deepseek_base_url ?? '');
        $settings->set('ai.deepseek_model', $this->deepseek_model ?? '');

        if ($this->deepseek_max_tokens !== null) {
            $settings->set('ai.deepseek_max_tokens', $this->deepseek_max_tokens);
        }

        $settings->set('ai.deepseek_temperature', $this->deepseek_temperature ?? '');

        $this->saved = true;
    }

    /**
     * Validate and save GigaChat credentials.
     */
    private function saveGigaChat(SettingsService $settings): void
    {
        $this->validateGigaChat();

        if (! empty($this->formErrors)) {
            return;
        }

        $settings->set('ai.gigachat_client_id', $this->gigachat_client_id ?? '');

        // Secret: only overwrite if non-empty
        if ($this->gigachat_client_secret !== null && $this->gigachat_client_secret !== '') {
            $settings->set('ai.gigachat_client_secret', $this->gigachat_client_secret);
        }

        $settings->set('ai.gigachat_base_url', $this->gigachat_base_url ?? '');
        $settings->set('ai.gigachat_model', $this->gigachat_model ?? '');

        if ($this->gigachat_max_tokens !== null) {
            $settings->set('ai.gigachat_max_tokens', $this->gigachat_max_tokens);
        }

        $settings->set('ai.gigachat_temperature', $this->gigachat_temperature ?? '');
        $settings->set('ai.gigachat_scope', $this->gigachat_scope ?: 'GIGACHAT_API_PERS');

        // Certificate: when a new file is uploaded, store it under storage/certs with the
        // fixed name and persist its storage-relative path. Otherwise keep the current cert.
        if ($this->gigachat_cert_file !== null) {
            $dir = storage_path('certs');
            File::ensureDirectoryExists($dir);
            File::put($dir . '/' . self::GIGACHAT_CERT_NAME, $this->gigachat_cert_file->get());

            $settings->set('ai.gigachat_path_cert', self::GIGACHAT_CERT_RELATIVE);
            $this->gigachat_path_cert = self::GIGACHAT_CERT_RELATIVE;
            $this->gigachat_cert_uploaded = true;
            $this->gigachat_cert_file = null;
        }

        $this->saved = true;
    }
}
