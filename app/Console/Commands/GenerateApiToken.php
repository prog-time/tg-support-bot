<?php

namespace App\Console\Commands;

use App\Models\ExternalSource;
use App\Models\ExternalSourceAccessTokens;
use App\Modules\External\DTOs\ExternalSourceDto;
use App\Modules\External\Services\Source\ExternalSourceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use phpDocumentor\Reflection\Exception;

/**
 * Example request:
 * php artisan app:generate-token live_chat http://{bot-domain}:3001/push-message
 */
class GenerateApiToken extends Command
{
    protected $signature = 'app:generate-token {source} {hook_url}';

    protected $description = 'Generate token for user, create user if not exists';

    public function __construct(private ExternalSourceService $externalSourceService)
    {
        parent::__construct();
    }

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
                    $this->info('Adding new resource...');

                    $sourceData = ExternalSourceDto::from(array_merge($externalSourceData, [
                        'created_at' => date('Y-m-d H:i:s'),
                    ]));

                    $sourceItem = $this->externalSourceService->create($sourceData);
                } else {
                    $this->info("Updating resource {$sourceName}...");

                    $sourceData = ExternalSourceDto::from(array_merge($externalSourceData, [
                        'id' => $sourceItem->id,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]));

                    $this->externalSourceService->update($sourceData);
                }

                $accessToken = (new ExternalSourceAccessTokens())
                    ->where('external_source_id', $sourceItem->id)
                    ->first();

                if (!$accessToken) {
                    throw new Exception('Token not created!', 1);
                }

                $this->info("Token generated successfully! {$sourceItem->name} : {$accessToken->token}");
            });

            return 0;
        } catch (\Throwable $exception) {
            if ($exception->getCode() === 1) {
                $this->error("Failed to add resource: {$exception->getMessage()}");
            }
            return 1;
        }
    }
}
