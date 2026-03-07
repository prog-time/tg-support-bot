<?php

namespace App\Modules\Admin\Filament\Resources;

use App\Models\ExternalSource;
use App\Modules\Admin\Filament\Resources\ExternalSourceResource\Pages\CreateExternalSource;
use App\Modules\Admin\Filament\Resources\ExternalSourceResource\Pages\EditExternalSource;
use App\Modules\Admin\Filament\Resources\ExternalSourceResource\Pages\ListExternalSources;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Illuminate\Support\HtmlString;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExternalSourceResource extends Resource
{
    protected static ?string $model = ExternalSource::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'External Sources';

    protected static ?string $modelLabel = 'External Source';

    protected static ?string $pluralModelLabel = 'External Sources';

    protected static ?int $navigationSort = 3;

    /**
     * Only admins can manage external sources.
     *
     * @return bool
     */
    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->isAdmin() ?? false;
    }

    /**
     * @param Form $form
     *
     * @return Form
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Название')
                ->required()
                ->maxLength(255),
            TextInput::make('webhook_url')
                ->label('Webhook URL')
                ->url()
                ->maxLength(500),
            Placeholder::make('token_warning')
                ->label('')
                ->content(new HtmlString(
                    '<div class="flex gap-2 rounded-lg bg-warning-50 p-3 text-sm text-warning-700 dark:bg-warning-400/10 dark:text-warning-400">'
                    . '<span>⚠️</span>'
                    . '<span>После сохранения токен доступа будет автоматически обновлён. Все интеграции, использующие текущий токен, перестанут работать.</span>'
                    . '</div>'
                ))
                ->columnSpanFull()
                ->hiddenOn('create'),
        ]);
    }

    /**
     * @param Table $table
     *
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                TextColumn::make('webhook_url')
                    ->label('Webhook URL')
                    ->limit(50),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListExternalSources::route('/'),
            'create' => CreateExternalSource::route('/create'),
            'edit' => EditExternalSource::route('/{record}/edit'),
        ];
    }
}
