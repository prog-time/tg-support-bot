<?php

namespace App\Console\Commands;

use App\Services\Swagger\SwaggerGenerateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

class GenerateSwagger extends Command
{
    protected $signature = 'swagger:generate';
    protected $description = 'Генерация Swagger документации';
    private SwaggerGenerateService $swaggerService;

    public function __construct()
    {
        parent::__construct();
        $this->swaggerService = new SwaggerGenerateService();
    }

    public function handle()
    {
        try {
            $this->info('Генерация Swagger документации началась...');

            $this->info('Сбор всех схем (paths/*.json)');
            $paths = $this->swaggerService->getSwaggerFragments(resource_path('swagger/paths'));

            $this->info('Сбор всех схем (requests/*.json)');
            $schemas = $this->swaggerService->getSwaggerFragments(resource_path('swagger/requests'));

            $this->info('Сбор всех схем (responses/*.json)');
            $responses = $this->swaggerService->getSwaggerFragments(resource_path('swagger/responses'));

            $schemas = array_merge($schemas, $responses);

            $this->info('Собираем финальный OpenAPI JSON');
            $swagger = [
                'openapi' => '3.0.0',
                'info' => [
                    'title' => 'Telegram Support Bot API',
                    'version' => '1.0.0',
                ],
                'paths' => $paths,
                'components' => [
                    'schemas' => $schemas,
                ],
            ];

            $swagger = $this->swaggerService->replaceLangStrings($swagger);

            $this->info('Сохраняем файл...');
            $path = storage_path('app/swagger.json');
            File::put($path, json_encode($swagger, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->info('Генерация завершена успешно!');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

}
