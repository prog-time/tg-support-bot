<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class PreviewController extends Controller
{

    public function chat()
    {
        return view('preview.chat');
    }

}
