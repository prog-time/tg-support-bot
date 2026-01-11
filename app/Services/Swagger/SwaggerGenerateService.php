<?php

namespace App\Services\Swagger;

use Illuminate\Support\Facades\File;

/**
 * SwaggerGenerateService - работа со swagger
 */
class SwaggerGenerateService
{
    /**
     * Получаем фрагменты для генерации Swagger документа
     *
     * @param string $path
     *
     * @return array
     */
    public function getSwaggerFragments(string $path): array
    {
        try {
            // Сбор всех path-файлов (JSON)
            $pathFiles = File::allFiles($path);

            $paths = [];
            foreach ($pathFiles as $file) {
                $json = File::get($file->getRealPath());
                $data = json_decode($json, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $paths = array_merge_recursive($paths, $data);
                } else {
                    logger()->error('Ошибка JSON в файле: ' . $file->getFilename());
                }
            }

            return $paths;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Рекурсивно заменяем ключи на значения из файла локализации swagger.php
     *
     * @param array $swagger
     *
     * @return array
     */
    public function replaceLangStrings(array $swagger): array
    {
        array_walk_recursive($swagger, function (&$value) {
            if (is_string($value) && str_starts_with($value, '@lang(') && str_ends_with($value, ')')) {
                $key = substr($value, 6, -1);
                $value = __($key);
            }
        });

        return $swagger;
    }
}
