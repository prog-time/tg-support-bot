<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class PreviewController extends Controller
{
    public function chat(): mixed
    {
        return view('preview.chat');
    }
}
