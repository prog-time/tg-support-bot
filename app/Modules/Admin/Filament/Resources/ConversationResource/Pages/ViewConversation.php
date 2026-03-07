<?php

namespace App\Modules\Admin\Filament\Resources\ConversationResource\Pages;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Admin\Actions\SendReplyAction;
use App\Modules\Admin\Filament\Resources\ConversationResource;
use App\Modules\Telegram\Actions\GetChat;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;

class ViewConversation extends ViewRecord
{
    use WithFileUploads;

    protected static string $resource = ConversationResource::class;

    protected static string $view = 'filament.resources.conversation-resource.pages.view-conversation';

    #[Locked]
    public Collection $chatMessages;

    public string $replyText = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $attachment = null;

    #[Locked]
    public ?string $telegramUsername = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->loadMessages();
        $this->loadTelegramUsername();
    }

    /**
     * Fetch Telegram username once on page load via getChat API.
     *
     * @return void
     */
    protected function loadTelegramUsername(): void
    {
        /** @var BotUser $botUser */
        $botUser = $this->getRecord();

        if ($botUser->platform !== 'telegram') {
            return;
        }

        try {
            $chat = (new GetChat())->execute((int) $botUser->chat_id);
            $this->telegramUsername = $chat->rawData['result']['username'] ?? null;
        } catch (\Throwable) {
            $this->telegramUsername = null;
        }
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
            'botUser'           => $this->getRecord(),
            'messages'          => $this->chatMessages,
            'telegramUsername'  => $this->telegramUsername,
        ];
    }

    /**
     * Send reply to the bot user.
     *
     * @return void
     */
    public function sendReply(): void
    {
        $this->validate([
            'replyText'  => ['nullable', 'string', 'required_without:attachment'],
            'attachment' => ['nullable', 'file', 'max:51200'],
        ]);

        /** @var BotUser $botUser */
        $botUser = $this->getRecord();

        /** @var UploadedFile|null $attachment */
        $attachment = $this->attachment instanceof UploadedFile ? $this->attachment : null;

        SendReplyAction::execute($botUser, $this->replyText, $attachment);

        $this->replyText = '';
        $this->reset('attachment');
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
