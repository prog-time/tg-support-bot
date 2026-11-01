<?php

namespace App\Modules\Admin;

use App\Livewire\Admin\ThanksPage;
use App\Livewire\Auth\LoginPage;
use App\Livewire\Chat\ConversationPage;
use App\Livewire\Settings\AiAssistantPage;
use App\Livewire\Settings\AiFaqFormPage;
use App\Livewire\Settings\AiFaqPage;
use App\Livewire\Settings\AiProviderAccessPage;
use App\Livewire\Settings\ApiWebhookSourcePage;
use App\Livewire\Settings\ApiWebhooksPage;
use App\Livewire\Settings\AutoRepliesPage;
use App\Livewire\Settings\AutoReplyFormPage;
use App\Livewire\Settings\AvitoIntegrationPage;
use App\Livewire\Settings\EmailIntegrationPage;
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
     *
     * Страница «Поблагодарить автора» намеренно вне /admin/settings:
     * EnsureSettingsAccess редиректит оттуда не-админов.
     * Avito и Email — встроенные модули со своими экранами: их поля не подходят
     * под общий маршрут integrations/{channel}.
     */
    public function boot(): void
    {
        Route::middleware(['web', 'guest'])
            ->get('/admin/login', LoginPage::class)
            ->name('login');

        Route::middleware(['web', 'auth'])
            ->post('/admin/logout', function () {
                Auth::guard('web')->logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();

                return redirect()->route('login');
            })
            ->name('admin.logout');

        Route::middleware(['web', 'auth'])
            ->get('/admin', fn () => redirect()->route('admin.chats'))
            ->name('admin.home');

        Route::middleware(['web', 'auth'])
            ->get('/admin/chats', ConversationPage::class)
            ->name('admin.chats');

        Route::middleware(['web'])
            ->get('/admin/manifest.webmanifest', [PwaController::class, 'manifest'])
            ->name('admin.pwa.manifest');
        Route::middleware(['web'])
            ->get('/admin/sw.js', [PwaController::class, 'serviceWorker'])
            ->name('admin.pwa.sw');

        Route::middleware(['web', 'auth'])
            ->get('/admin/chat-attachments/{attachment}', [ChatAttachmentController::class, 'show'])
            ->name('admin.chat-attachment')
            ->where('attachment', '[0-9]+');

        Route::middleware(['web', 'auth'])
            ->get('/admin/bot-user-avatars/{botUser}', [BotUserAvatarController::class, 'show'])
            ->name('admin.bot-user-avatar')
            ->where('botUser', '[0-9]+');

        Route::middleware(['web', 'auth'])
            ->get('/admin/team-member-avatars/{user}', [UserAvatarController::class, 'show'])
            ->name('admin.team-member-avatar')
            ->where('user', '[0-9]+');

        Route::middleware(['web', 'auth'])
            ->get('/admin/thanks', ThanksPage::class)
            ->name('admin.thanks');

        Route::middleware(['web', 'auth', EnsureSettingsAccess::class])
            ->prefix('admin/settings')
            ->name('admin.settings.')
            ->group(function (): void {
                Route::get('/general', GeneralSettingsPage::class)
                    ->name('general');

                Route::get('/integrations', IntegrationsListPage::class)
                    ->name('integrations');

                Route::get('/integrations/list', IntegrationsListPage::class)
                    ->name('integrations.list');

                Route::get('/integrations/{channel}', IntegrationChannelPage::class)
                    ->name('integrations.channel')
                    ->where('channel', 'telegram|telegram_ai|vk|max');

                Route::get('/ai', AiAssistantPage::class)
                    ->name('ai');

                Route::get('/ai/faq', AiFaqPage::class)
                    ->name('ai.faq');

                Route::get('/ai/faq/create', AiFaqFormPage::class)
                    ->name('ai.faq.create');

                Route::get('/ai/faq/{entry}/edit', AiFaqFormPage::class)
                    ->name('ai.faq.edit')
                    ->where('entry', '[0-9]+');

                Route::get('/ai/{provider}', AiProviderAccessPage::class)
                    ->name('ai.provider')
                    ->where('provider', 'openai|deepseek|gigachat');

                Route::get('/api-webhooks', ApiWebhooksPage::class)
                    ->name('api-webhooks');

                Route::get('/api-webhooks/{source}', ApiWebhookSourcePage::class)
                    ->name('api-webhooks.source')
                    ->where('source', '[0-9]+');

                Route::get('/team', TeamPage::class)
                    ->name('team');

                Route::get('/team/create', TeamMemberCreatePage::class)
                    ->name('team.create');

                Route::get('/team/{user}/edit', TeamMemberEditPage::class)
                    ->name('team.edit')
                    ->where('user', '[0-9]+');

                Route::get('/auto-replies', AutoRepliesPage::class)
                    ->name('auto-replies');

                Route::get('/auto-replies/create', AutoReplyFormPage::class)
                    ->name('auto-replies.create');

                Route::get('/auto-replies/{rule}/edit', AutoReplyFormPage::class)
                    ->name('auto-replies.edit')
                    ->where('rule', '[0-9]+');

                Route::get('/avito', AvitoIntegrationPage::class)
                    ->name('avito');

                Route::get('/email', EmailIntegrationPage::class)
                    ->name('email');
            });
    }
}
