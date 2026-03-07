<?php

namespace App\Modules\Admin\Filament\Resources;

use App\Models\BotUser;
use App\Modules\Admin\Filament\Resources\ConversationResource\Pages\ListConversations;
use App\Modules\Admin\Filament\Resources\ConversationResource\Pages\ViewConversation;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConversationResource extends Resource
{
    protected static ?string $model = BotUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Диалоги';

    protected static ?string $modelLabel = 'Диалог';

    protected static ?string $pluralModelLabel = 'Диалоги';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('chat_id')
                    ->label('Chat ID')
                    ->sortable(),
                TextColumn::make('platform')
                    ->label('Платформа')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'telegram' => 'info',
                        'vk' => 'primary',
                        default => 'warning',
                    }),
                TextColumn::make('lastMessage.created_at')
                    ->label('Последнее сообщение')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('platform')
                    ->label('Платформа')
                    ->options([
                        'telegram' => 'Telegram',
                        'vk' => 'VK',
                    ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    /**
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListConversations::route('/'),
            'view' => ViewConversation::route('/{record}'),
        ];
    }
}
