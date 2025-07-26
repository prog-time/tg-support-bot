<?php

namespace App\Http\Controllers;

use Illuminate\Console\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Mockery\Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SwaggerController
{
    /**
     * Swagger UI
     *
     * @return Application|Factory|View
     */
    public function swaggerUi(): Application|Factory|View
    {
        return view('swagger-ui');
    }

    /**
     * @return JsonResponse|BinaryFileResponse
     */
    public function showSwagger(): JsonResponse|BinaryFileResponse
    {
        try {
            $path = storage_path('app/swagger.json');

            if (!File::exists($path)) {
                throw new Exception('Файл swagger.json не найден. Сначала вызовите /generate-swagger', 1);
            }

            return response()->file($path, [
                'Content-Type' => 'application/json',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getCode() === 1 ? $e->getMessage() : 'Ошибка в swagger документе!',
            ], 404);
        }
    }
}
