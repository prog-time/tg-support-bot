<?php

namespace App\Modules\Admin\Filament\Resources;

use App\Models\BotUser;
use App\Modules\Admin\Filament\Resources\BotUserResource\Pages\ListBotUsers;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BotUserResource extends Resource
{
    protected static ?string $model = BotUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Чаты';

    protected static ?string $modelLabel = 'Чат';

    protected static ?string $pluralModelLabel = 'Чаты';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
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
                TextColumn::make('created_at')
                    ->label('Зарегистрирован')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Action::make('ban')
                    ->label('Заблокировать')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (BotUser $record): bool => !$record->isBanned())
                    ->action(fn (BotUser $record) => $record->update([
                        'is_banned' => true,
                        'banned_at' => now(),
                    ])),
                Action::make('unban')
                    ->label('Разблокировать')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (BotUser $record): bool => $record->isBanned())
                    ->action(fn (BotUser $record) => $record->update([
                        'is_banned' => false,
                        'banned_at' => null,
                    ])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListBotUsers::route('/'),
        ];
    }
}
