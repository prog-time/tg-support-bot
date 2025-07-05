<?php

namespace App\Http\Controllers;

use App\Logging\LokiLogger;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class TestController extends Controller
{
    /**
     * Проверка работы хука
     *
     * @return void
     */
    public function webhook(): void
    {
        try {
            $dataQuery = request()->all();
            if (!empty($dataQuery)) {
                (new LokiLogger())->log('tg_request', json_encode(request()->all()));
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
