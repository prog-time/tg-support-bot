<?php

namespace App\Modules\Api\Controllers;

use Illuminate\Routing\Controller;

class PreviewController extends Controller
{
    /**
     * @return mixed
     */
    public function chat(): mixed
    {
        return view('preview.chat');
    }
}
