<?php

namespace App\Modules\Admin\Filament\Pages;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Admin\Actions\SendReplyAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;

/**
 * @property Form $form
 */
class ConversationPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = null;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.conversation-page';

    public int $botUserId;

    public ?BotUser $botUser = null;

    #[Locked]
    public Collection $chatMessages;

    public ?array $replyData = [];

    public function mount(int $botUserId): void
    {
        $this->botUserId = $botUserId;
        $this->botUser = BotUser::with(['externalUser'])->find($botUserId);
        $this->chatMessages = collect();
        $this->loadMessages();
        $this->form->fill();
    }

    public function getPollingInterval(): ?string
    {
        return '5s';
    }

    /**
     * Load messages for the current conversation.
     *
     * @return void
     */
    public function loadMessages(): void
    {
        if ($this->botUser) {
            $this->chatMessages = Message::where('bot_user_id', $this->botUserId)
                ->with(['externalMessage', 'attachments'])
                ->orderBy('created_at')
                ->get();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Textarea::make('text')
                    ->label('Сообщение')
                    ->required()
                    ->rows(3)
                    ->placeholder('Введите ответ...'),
            ])
            ->statePath('replyData');
    }

    /**
     * Send manager reply.
     *
     * @return void
     */
    public function sendReply(): void
    {
        if (!$this->shouldShowReplyForm() || empty($this->botUser)) {
            return;
        }

        $data = $this->form->getState();

        SendReplyAction::execute($this->botUser, $data['text']);

        $this->form->fill();
        $this->loadMessages();

        Notification::make()
            ->title('Сообщение отправлено')
            ->success()
            ->send();
    }

    /**
     * Show reply form only in admin_panel mode.
     *
     * @return bool
     */
    public function shouldShowReplyForm(): bool
    {
        return config('app.manager_interface') === 'admin_panel';
    }
}
