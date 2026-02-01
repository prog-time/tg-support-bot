<?php

namespace App\Console\Commands;

use App\Services\Swagger\SwaggerGenerateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSwagger extends Command
{
    protected $signature = 'swagger:generate';

    protected $description = 'Generate Swagger documentation';

    private SwaggerGenerateService $swaggerService;

    public function __construct()
    {
        parent::__construct();
        $this->swaggerService = new SwaggerGenerateService();
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        try {
            $this->info('Swagger documentation generation started...');

            $this->info('Collecting all schemas (paths/*.json)');
            $paths = $this->swaggerService->getSwaggerFragments(resource_path('swagger/paths'));

            $this->info('Collecting all schemas (requests/*.json)');
            $schemas = $this->swaggerService->getSwaggerFragments(resource_path('swagger/requests'));

            $this->info('Collecting all schemas (responses/*.json)');
            $responses = $this->swaggerService->getSwaggerFragments(resource_path('swagger/responses'));

            $schemas = array_merge($schemas, $responses);

            $this->info('Building final OpenAPI JSON');

            $appUrl = config('app.url');
            $swagger = [
                'openapi' => '3.0.0',
                'info' => [
                    'title' => 'Telegram Support Bot API',
                    'version' => '1.0.0',
                ],
                'servers' => [
                    ['url' => $appUrl],
                ],
                'components' => [
                    'schemas' => $schemas,
                    'securitySchemes' => [
                        'BearerAuth' => [
                            'type' => 'http',
                            'scheme' => 'bearer',
                        ],
                    ],
                ],
                'security' => [
                    ['BearerAuth' => []],
                ],
                'paths' => $paths,
            ];

            $swagger = $this->swaggerService->replaceLangStrings($swagger);

            $this->info('Saving file...');
            $path = storage_path('app/swagger.json');
            File::put($path, json_encode($swagger, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->info('Generation completed successfully!');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
    }
}
