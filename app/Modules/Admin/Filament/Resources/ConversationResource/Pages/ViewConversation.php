<?php

namespace App\Modules\Admin\Filament\Resources\ConversationResource\Pages;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Admin\Actions\SendReplyAction;
use App\Modules\Admin\Filament\Resources\ConversationResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;

class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    protected static string $view = 'filament.resources.conversation-resource.pages.view-conversation';

    #[Locked]
    public Collection $chatMessages;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->loadMessages();
    }

    public function getPollingInterval(): ?string
    {
        return '5s';
    }

    /**
     * Load messages for the conversation.
     *
     * @return void
     */
    public function loadMessages(): void
    {
        /** @var BotUser $botUser */
        $botUser = $this->getRecord();

        $this->chatMessages = Message::where('bot_user_id', $botUser->id)
            ->with(['externalMessage', 'attachments'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'botUser'  => $this->getRecord(),
            'messages' => $this->chatMessages,
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        if (!$this->shouldShowReplyForm()) {
            return [];
        }

        return [
            Action::make('sendReply')
                ->label('Ответить')
                ->icon('heroicon-o-paper-airplane')
                ->form([
                    Textarea::make('text')
                        ->label('Сообщение')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    /** @var BotUser $botUser */
                    $botUser = $this->getRecord();
                    SendReplyAction::execute($botUser, $data['text']);

                    $this->loadMessages();

                    Notification::make()
                        ->title('Сообщение отправлено')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Show reply form only in admin_panel mode.
     *
     * @return bool
     */
    protected function shouldShowReplyForm(): bool
    {
        return config('app.manager_interface') === 'admin_panel';
    }
}
