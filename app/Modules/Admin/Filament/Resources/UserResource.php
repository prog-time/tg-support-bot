<?php

namespace App\Modules\Admin\Filament\Resources;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Admin\Filament\Resources\UserResource\Pages\CreateUser;
use App\Modules\Admin\Filament\Resources\UserResource\Pages\EditUser;
use App\Modules\Admin\Filament\Resources\UserResource\Pages\ListUsers;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Пользователи';

    protected static ?string $navigationGroup = 'Настройки';

    protected static ?string $modelLabel = 'Пользователь';

    protected static ?string $pluralModelLabel = 'Пользователи';

    protected static ?int $navigationSort = 10;

    /**
     * Only admins can access user management.
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
                ->label('Имя')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('password')
                ->label('Пароль')
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->afterStateHydrated(fn ($component) => $component->state(null))
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? bcrypt($state) : null)
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->rules(['nullable', 'min:8'])
                ->maxLength(255),
            TextInput::make('password_confirmation')
                ->label('Подтверждение пароля')
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->afterStateHydrated(fn ($component) => $component->state(null))
                ->dehydrated(false)
                ->same('password'),
            Select::make('role')
                ->label('Роль')
                ->options(UserRole::options())
                ->default(UserRole::Manager->value)
                ->required(),
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
                    ->label('Имя')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Роль')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label())
                    ->color(fn (UserRole $state): string => $state->color()),
                IconColumn::make('email_verified_at')
                    ->label('Верифицирован')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
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
            'index'  => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit'   => EditUser::route('/{record}/edit'),
        ];
    }
}
