<?php

namespace App\Modules\Admin;

use App\Livewire\Admin\ThanksPage;
use App\Livewire\Auth\LoginPage;
use App\Livewire\Chat\ConversationPage;
use App\Livewire\Settings\AiAssistantPage;
use App\Livewire\Settings\AiProviderAccessPage;
use App\Livewire\Settings\ApiWebhookSourcePage;
use App\Livewire\Settings\ApiWebhooksPage;
use App\Livewire\Settings\AutoRepliesPage;
use App\Livewire\Settings\AutoReplyFormPage;
use App\Livewire\Settings\AvitoIntegrationPage;
use App\Livewire\Settings\GeneralSettingsPage;
use App\Livewire\Settings\IntegrationChannelPage;
use App\Livewire\Settings\IntegrationsListPage;
use App\Livewire\Settings\TeamMemberCreatePage;
use App\Livewire\Settings\TeamMemberEditPage;
use App\Livewire\Settings\TeamPage;
use App\Modules\Admin\Controllers\BotUserAvatarController;
use App\Modules\Admin\Controllers\ChatAttachmentController;
use App\Modules\Admin\Controllers\PwaController;
use App\Modules\Admin\Controllers\UserAvatarController;
use App\Modules\Admin\Middleware\EnsureSettingsAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    /**
     * Зарегистрировать Admin-модуль.
     *
     * Все admin-роуты (вход, выход, чаты, настройки) регистрируются здесь на
     * чистом Livewire + стандартном Laravel-аутентификации (Filament удалён).
     */
    public function boot(): void
    {
        // ── Auth routes ────────────────────────────────────────────────────────
        // Login: guest-only full-page Livewire screen. Named `login` so Laravel's
        // `auth` middleware redirects unauthenticated visitors here automatically.
        Route::middleware(['web', 'guest'])
            ->get('/admin/login', LoginPage::class)
            ->name('login');

        // Logout: POST, clears the session and returns to the login screen.
        Route::middleware(['web', 'auth'])
            ->post('/admin/logout', function () {
                Auth::guard('web')->logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();

                return redirect()->route('login');
            })
            ->name('admin.logout');

        // /admin root → chat workspace (former Filament panel home).
        Route::middleware(['web', 'auth'])
            ->get('/admin', fn () => redirect()->route('admin.chats'))
            ->name('admin.home');

        // ── Chat workspace route ───────────────────────────────────────────────
        // Full-screen standalone Livewire route at /admin/chats.
        // The admin panel is an always-active manager surface (Telegram group is an optional addition).
        // Middleware: web session + standard auth guard.
        Route::middleware(['web', 'auth'])
            ->get('/admin/chats', ConversationPage::class)
            ->name('admin.chats');

        // PWA manifest + service worker — public (the browser fetches them
        // outside the session); served under /admin/ so the SW scope is /admin/.
        Route::middleware(['web'])
            ->get('/admin/manifest.webmanifest', [PwaController::class, 'manifest'])
            ->name('admin.pwa.manifest');
        Route::middleware(['web'])
            ->get('/admin/sw.js', [PwaController::class, 'serviceWorker'])
            ->name('admin.pwa.sw');

        // Streams locally-stored manager-reply attachments (e.g. MAX files) to the
        // chat thread — auth-gated, no public-disk/symlink dependency.
        Route::middleware(['web', 'auth'])
            ->get('/admin/chat-attachments/{attachment}', [ChatAttachmentController::class, 'show'])
            ->name('admin.chat-attachment')
            ->where('attachment', '[0-9]+');

        // Streams locally-stored bot user avatars — fetched async by EnrichBotUserProfileJob.
        Route::middleware(['web', 'auth'])
            ->get('/admin/bot-user-avatars/{botUser}', [BotUserAvatarController::class, 'show'])
            ->name('admin.bot-user-avatar')
            ->where('botUser', '[0-9]+');

        // Streams locally-stored team member (operator) avatars — uploaded via TeamMemberCreatePage / TeamMemberEditPage.
        Route::middleware(['web', 'auth'])
            ->get('/admin/team-member-avatars/{user}', [UserAvatarController::class, 'show'])
            ->name('admin.team-member-avatar')
            ->where('user', '[0-9]+');

        // «Поблагодарить автора» — static support/thanks page.
        // Deliberately NOT under the /admin/settings prefix: it is not a setting
        // and must stay reachable by every signed-in operator, whereas
        // EnsureSettingsAccess redirects non-admins away from all settings
        // screens except «Основные».
        Route::middleware(['web', 'auth'])
            ->get('/admin/thanks', ThanksPage::class)
            ->name('admin.thanks');

        // Custom Livewire Settings routes.
        // Prefix: /admin/settings.
        // Middleware: 'web' session stack + standard `auth` guard so
        // unauthenticated visitors are redirected to /admin/login.
        Route::middleware(['web', 'auth', EnsureSettingsAccess::class])
            ->prefix('admin/settings')
            ->name('admin.settings.')
            ->group(function (): void {
                Route::get('/general', GeneralSettingsPage::class)
                    ->name('general');

                // Integrations index — renders the mobile card-list page.
                // Desktop users are redirected client-side (window.innerWidth check)
                // by a script embedded in the list page view, so we avoid UA sniffing
                // and keep the Livewire route simple.
                // The list page is also directly accessible via /integrations/list.
                Route::get('/integrations', IntegrationsListPage::class)
                    ->name('integrations');

                Route::get('/integrations/list', IntegrationsListPage::class)
                    ->name('integrations.list');

                Route::get('/integrations/{channel}', IntegrationChannelPage::class)
                    ->name('integrations.channel')
                    ->where('channel', 'telegram|telegram_ai|vk|max');

                // AI assistant settings.
                Route::get('/ai', AiAssistantPage::class)
                    ->name('ai');

                Route::get('/ai/{provider}', AiProviderAccessPage::class)
                    ->name('ai.provider')
                    ->where('provider', 'openai|deepseek|gigachat');

                // API and webhooks — External Sources token and webhook management.
                Route::get('/api-webhooks', ApiWebhooksPage::class)
                    ->name('api-webhooks');

                Route::get('/api-webhooks/{source}', ApiWebhookSourcePage::class)
                    ->name('api-webhooks.source')
                    ->where('source', '[0-9]+');

                // Team — manage operators, add new members, delete existing ones.
                Route::get('/team', TeamPage::class)
                    ->name('team');

                Route::get('/team/create', TeamMemberCreatePage::class)
                    ->name('team.create');

                Route::get('/team/{user}/edit', TeamMemberEditPage::class)
                    ->name('team.edit')
                    ->where('user', '[0-9]+');

                // Auto-replies — automatic responses to frequent questions (UI stage).
                Route::get('/auto-replies', AutoRepliesPage::class)
                    ->name('auto-replies');

                Route::get('/auto-replies/create', AutoReplyFormPage::class)
                    ->name('auto-replies.create');

                Route::get('/auto-replies/{rule}/edit', AutoReplyFormPage::class)
                    ->name('auto-replies.edit')
                    ->where('rule', '[0-9]+');

                // Avito integration screen — Avito is a built-in core module
                // (same as Telegram/VK/Max), so the route is always registered.
                Route::get('/avito', AvitoIntegrationPage::class)
                    ->name('avito');
            });
    }
}
