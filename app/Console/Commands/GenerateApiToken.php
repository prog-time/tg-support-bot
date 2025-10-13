<?php

namespace App\Console\Commands;

use App\DTOs\ExternalSourceDto;
use App\Models\ExternalSource;
use App\Models\ExternalSourceAccessTokens;
use App\Services\External\Source\ExternalSourceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use phpDocumentor\Reflection\Exception;

/**
 * Пример запроса
 * php artisan app:generate-token live_chat http://{домен бота}:3001/push-message
 */
class GenerateApiToken extends Command
{
    protected $signature = 'app:generate-token {source} {hook_url}';

    protected $description = 'Генерирует токен для пользователя, создаёт пользователя если нет';

    public function handle(): int
    {
        try {
            $sourceName = $this->argument('source');
            $hookUrl = $this->argument('hook_url');

            $validator = Validator::make([
                'source' => $sourceName,
                'hook_url' => $hookUrl,
            ], [
                'source' => ['required', 'string', 'min:3', 'max:100'],
                'hook_url' => ['required', 'url'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }
                return 1;
            }

            DB::transaction(function () use ($sourceName, $hookUrl) {
                $externalSourceData = [
                    'name' => $sourceName,
                    'webhook_url' => $hookUrl,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                $sourceItem = ExternalSource::where('name', $sourceName)->first();
                if (!$sourceItem) {
                    $this->info('Добавляем новый ресурс...');

                    $sourceData = ExternalSourceDto::from(array_merge($externalSourceData, [
                        'created_at' => date('Y-m-d H:i:s'),
                    ]));

                    $sourceItem = (new ExternalSourceService())->create($sourceData);
                } else {
                    $this->info("Обновляем ресурс {$sourceName}...");

                    $sourceData = ExternalSourceDto::from(array_merge($externalSourceData, [
                        'id' => $sourceItem->id,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]));

                    (new ExternalSourceService())->update($sourceData);
                }

                $accessToken = (new ExternalSourceAccessTokens())
                    ->where('external_source_id', $sourceItem->id)
                    ->first();

                if (!$accessToken) {
                    throw new Exception('Токен не создан!', 1);
                }

                $this->info("Токен успешно сгенерирован! {$sourceItem->name} : {$accessToken->token}");
            });

            return 0;
        } catch (\Exception $exception) {
            if ($exception->getCode() === 1) {
                $this->error("Не удалось добавить ресурс: {$exception->getMessage()}");
            }
            return 1;
        }
    }
}
