<?php

namespace App\Modules\Admin\Filament\Resources\ExternalSourceResource\Pages;

use App\Models\ExternalSource;
use App\Modules\Admin\Filament\Resources\ExternalSourceResource;
use App\Modules\External\Services\Source\ExternalSourceTokensService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditExternalSource extends EditRecord
{
    protected static string $resource = ExternalSourceResource::class;

    /**
     * Regenerate access token after saving the record.
     *
     * @return void
     */
    protected function afterSave(): void
    {
        /** @var ExternalSource $record */
        $record = $this->getRecord();

        app(ExternalSourceTokensService::class)->setAccessToken($record->id);

        Notification::make()
            ->title('Токен обновлён')
            ->body('Не забудьте обновить токен в ваших интеграциях.')
            ->warning()
            ->send();
    }

    /**
     * @return array<Action|DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshToken')
                ->label('Обновить токен')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Обновление токена')
                ->modalDescription('Текущий токен будет аннулирован и заменён новым. Все интеграции, использующие старый токен, перестанут работать.')
                ->modalSubmitActionLabel('Обновить')
                ->action(function (): void {
                    /** @var ExternalSource $record */
                    $record = $this->getRecord();

                    app(ExternalSourceTokensService::class)->setAccessToken($record->id);

                    Notification::make()
                        ->title('Токен обновлён')
                        ->success()
                        ->send();
                }),
            Action::make('copyToken')
                ->label('Копировать токен')
                ->icon('heroicon-o-clipboard-document')
                ->color('gray')
                ->action(function (): void {
                    /** @var ExternalSource $record */
                    $record = $this->getRecord();
                    $token = $record->accessTokens()->where('active', true)->value('token');

                    if (empty($token)) {
                        Notification::make()
                            ->title('Токен не найден')
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->js("navigator.clipboard.writeText('" . addslashes($token) . "')");

                    Notification::make()
                        ->title('Токен скопирован')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
